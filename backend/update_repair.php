<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$repairId   = intval($input['repair_id'] ?? 0);
$assetId    = intval($input['asset_id'] ?? 0);
$returnDate = $input['return_date'] ?? null;
$cost       = floatval($input['repair_cost'] ?? 0);
$status     = $input['status'] ?? 'done'; // done หรือ cannot_fix

if (!$repairId || !$assetId || !$returnDate) {
    die(json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']));
}

// 1. อัปเดตตารางประวัติการซ่อม (ใส่วันที่คืน, ค่าซ่อมจริง, ปิดงานซ่อม)
$ok = inv_exec(
    "UPDATE repair_records SET return_date = ?, repair_cost = ?, status = ? WHERE id = ?",
    [$returnDate, $cost, $status, $repairId]
);

if ($ok) {
    // 2. แปลงสถานะเพื่อไปอัปเดตตารางครุภัณฑ์หลัก
    $assetStatus = 'available'; // ถ้าซ่อมเสร็จ ให้กลับมาพร้อมใช้งาน
    if ($status === 'cannot_fix') {
        $assetStatus = 'writeoff'; // ถ้าซ่อมไม่ได้ ให้เป็นแทงจำหน่าย
    }

    // 3. อัปเดตสถานะกลับไปที่ทรัพย์สินหลัก
    inv_exec("UPDATE assets SET status = ? WHERE id = ?", [$assetStatus, $assetId]);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการอัปเดตฐานข้อมูล']);
}