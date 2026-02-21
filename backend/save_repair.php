<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json');

// รับข้อมูลจากหน้าจอเป็น JSON
$input = json_decode(file_get_contents('php://input'), true);

$assetId  = intval($input['asset_id'] ?? 0);
$sendDate = $input['send_date'] ?? null;
$problem  = trim($input['problem_desc'] ?? '');
$shop     = trim($input['repair_shop'] ?? '');
$cost     = floatval($input['repair_cost'] ?? 0);
$status   = $input['status'] ?? 'repairing'; // repairing, done, cannot_fix

// ตรวจสอบข้อมูลเบื้องต้น
if (!$assetId || !$sendDate || !$problem) {
    die(json_encode(['success' => false, 'error' => 'กรุณากรอกวันที่ส่งซ่อมและอาการให้ครบถ้วน']));
}

// 1. บันทึกลงตาราง repair_records (ประวัติซ่อม)
$ok = inv_exec(
    "INSERT INTO repair_records (asset_id, send_date, problem_desc, repair_shop, repair_cost, status) 
     VALUES (?, ?, ?, ?, ?, ?)",
    [$assetId, $sendDate, $problem, $shop, $cost, $status]
);

if ($ok) {
    // 2. แปลงสถานะจากการซ่อม ให้ตรงกับสถานะหลักของครุภัณฑ์
    $assetStatus = 'available'; // ค่าเริ่มต้น
    if ($status === 'repairing') {
        $assetStatus = 'repair'; // กำลังซ่อมบำรุง
    } elseif ($status === 'cannot_fix') {
        $assetStatus = 'writeoff'; // ซ่อมไม่ได้ = รอจำหน่ายออก/แทงจำหน่าย
    } elseif ($status === 'done') {
        $assetStatus = 'available'; // ซ่อมเสร็จ = กลับมาพร้อมใช้งาน
    }

    // 3. อัปเดตสถานะลงในตารางหลัก assets
    inv_exec("UPDATE assets SET status = ? WHERE id = ?", [$assetStatus, $assetId]);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล']);
}