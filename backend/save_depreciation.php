<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$assetId = intval($input['asset_id'] ?? 0);

if (!$assetId) {
    die(json_encode(['success' => false, 'error' => 'รหัสทรัพย์สินไม่ถูกต้อง']));
}

$asset = inv_row("SELECT * FROM assets WHERE id=?", [$assetId]);
if (!$asset) {
    die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลทรัพย์สิน']));
}

if ($asset['category_code'] === '17') {
    die(json_encode(['success' => false, 'error' => 'ครุภัณฑ์ต่ำกว่าเกณฑ์ ไม่มีการคิดค่าเสื่อมราคา']));
}

if (!$asset['receive_date'] || !$asset['cost'] || !$asset['useful_life'] || !$asset['dep_rate']) {
    die(json_encode(['success' => false, 'error' => 'ข้อมูลที่จำเป็นสำหรับการคำนวณไม่ครบถ้วน (วันรับ, ราคา, อายุ, อัตรา)']));
}

// ลบข้อมูลการคำนวณเดิมออกก่อน เพื่อบันทึกใหม่
inv_exec("DELETE FROM depreciation_log WHERE asset_id=?", [$assetId]);

$receiveDateStr = $asset['receive_date'];
$startDate = new DateTime($receiveDateStr);

// ตรวจสอบและแปลงปี พ.ศ. เป็น ค.ศ. สำหรับคำนวณ
$year = intval($startDate->format('Y'));
if ($year > 2400) {
    $startDate = new DateTime(($year - 543) . $startDate->format('-m-d'));
    $year = intval($startDate->format('Y'));
}

$cost = floatval($asset['cost']);
$usefulLife = intval($asset['useful_life']);
$depRate = floatval($asset['dep_rate']);

$accDep = 0;
$currentNet = $cost;

// คำนวณค่าเสื่อมราคาต่อปีตามเกณฑ์มาตรฐาน
$annualBase = round($cost / $usefulLife, 2);

$month = intval($startDate->format('m'));
// กำหนดวันสิ้นปีงบประมาณแรก
$fiscalYearEnd = new DateTime(($month >= 10 ? $year + 1 : $year) . '-09-30');

// กำหนดวันหมดอายุของครุภัณฑ์ (วันรับ + อายุการใช้งาน - 1 วัน)
$endDate = clone $startDate;
$endDate->modify("+{$usefulLife} years");
$endDate->modify("-1 day");

$currentPeriodStart = clone $startDate;
$i = 1;
$success = true;

// วนลูปคำนวณจนกว่ามูลค่าสุทธิจะเหลือ 1.00 บาท
while ($currentNet > 1) {
    $currentPeriodEnd = clone $fiscalYearEnd;
    if ($currentPeriodEnd > $endDate) {
        $currentPeriodEnd = clone $endDate;
    }

    $diff = $currentPeriodStart->diff($currentPeriodEnd);
    $days = $diff->days + 1; 
    
    $months = round($days / 30.42);
    if ($months > 12) $months = 12;

    // คำนวณยอดค่าเสื่อมราคา
    if ($currentPeriodEnd == $endDate) {
        // งวดสุดท้าย บังคับหักให้เหลือ 1.00 บาทพอดี
        $depAmount = $currentNet - 1;
    } else {
        if ($days >= 365) {
            $depAmount = $annualBase;
        } else {
            $depAmount = round($annualBase * $days / 365, 2);
        }
    }
    
    // ป้องกันการหักจนติดลบ
    if ($currentNet - $depAmount < 1) {
        $depAmount = $currentNet - 1;
    }
    
    $accDep += $depAmount;
    $currentNet = $cost - $accDep;
    
    // บันทึกลงฐานข้อมูล
    $ok = inv_exec(
        "INSERT INTO depreciation_log (asset_id, log_date, year_no, months, dep_amount, acc_depreciation, net_value)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$assetId, $currentPeriodEnd->format('Y-m-d'), $i, $months, $depAmount, $accDep, $currentNet]
    );
    
    if (!$ok) {
        $success = false;
        break;
    }
    
    $i++;
    // ขยับตัวแปรไปรอบปีงบประมาณถัดไป
    $currentPeriodStart = clone $currentPeriodEnd;
    $currentPeriodStart->modify('+1 day');
    $fiscalYearEnd->modify('+1 year');
    
    // ออกจากลูปถ้าเกินกำหนดอายุ
    if ($currentPeriodStart > $endDate) break;
}

echo json_encode(['success' => $success]);