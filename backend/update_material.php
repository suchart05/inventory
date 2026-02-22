<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow logged-in users
if (!isset($_SESSION['inv_user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
    exit;
}

$id        = intval($_POST['id'] ?? 0);
$code      = trim($_POST['code'] ?? '');
$name      = trim($_POST['name'] ?? '');
$category  = trim($_POST['category'] ?? '');
$note      = trim($_POST['note'] ?? '');
$unit      = trim($_POST['unit'] ?? '');
$location  = trim($_POST['location'] ?? '');
$max_stock = $_POST['max_stock'] !== '' ? intval($_POST['max_stock']) : null;
$min_stock = $_POST['min_stock'] !== '' ? intval($_POST['min_stock']) : 5;

if ($id <= 0 || empty($code) || empty($name)) {
    echo json_encode(['ok' => false, 'error' => 'กรุณากรอกรหัสและชื่อวัสดุ']);
    exit;
}

// Check for duplicate code (exclude current record)
$dup = inv_row("SELECT id FROM materials WHERE code = ? AND id != ?", [$code, $id]);
if ($dup) {
    echo json_encode(['ok' => false, 'error' => 'รหัสวัสดุนี้ซ้ำกับรายการอื่นในระบบ']);
    exit;
}

$ok = inv_exec(
    "UPDATE materials SET code=?, name=?, category=?, note=?, unit=?, location=?, max_stock=?, min_stock=? WHERE id=?",
    [$code, $name, $category, $note, $unit, $location, $max_stock, $min_stock, $id]
);

if ($ok) {
    echo json_encode(['ok' => true, 'code' => $code]);
} else {
    echo json_encode(['ok' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล']);
}
