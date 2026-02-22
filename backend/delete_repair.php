<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow logged-in users
if (!isset($_SESSION['inv_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$repair_id = intval($input['repair_id'] ?? 0);

if ($repair_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบ ID รายการซ่อม']);
    exit;
}

$ok = inv_exec("DELETE FROM repair_records WHERE id = ?", [$repair_id]);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการลบข้อมูล']);
}
