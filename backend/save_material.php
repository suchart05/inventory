<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
    exit;
}

$code      = trim($_POST['code'] ?? '');
$name      = trim($_POST['name'] ?? '');
$category  = trim($_POST['category'] ?? '');
$unit      = trim($_POST['unit'] ?? '');
$location  = trim($_POST['location'] ?? '');
$note      = trim($_POST['note'] ?? '');
$max_stock = $_POST['max_stock'] !== '' ? intval($_POST['max_stock']) : null;
$min_stock = $_POST['min_stock'] !== '' ? intval($_POST['min_stock']) : 5;

if (empty($code) || empty($name)) {
    echo json_encode(['ok' => false, 'error' => 'กรุณากรอกรหัสและชื่อวัสดุ']);
    exit;
}

// Check duplicate
$dup = inv_row("SELECT id FROM materials WHERE code = ?", [$code]);
if ($dup) {
    echo json_encode(['ok' => false, 'error' => 'รหัสวัสดุนี้มีอยู่แล้วในระบบ']);
    exit;
}

$ok = inv_exec(
    "INSERT INTO materials (code, name, category, unit, location, max_stock, min_stock, note)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
    [$code, $name, $category, $unit, $location, $max_stock, $min_stock, $note]
);

if ($ok) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล']);
}
