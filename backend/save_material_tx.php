<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
    exit;
}

$material_id = intval($_POST['material_id'] ?? 0);
$type        = $_POST['type'] ?? ''; // 'in' or 'out'
$requester   = trim($_POST['requester'] ?? '');
$ref_doc     = trim($_POST['ref_doc'] ?? '');
$unit_price  = floatval($_POST['unit_price'] ?? 0);
$qty         = intval($_POST['qty'] ?? 0);
$tx_date     = trim($_POST['tx_date'] ?? '');
if (empty($tx_date)) {
    $tx_date = date('Y-m-d');
} else {
    // If date contains a year > 2500, it's likely Thai year (e.g. 2569-02-22)
    $parts = explode('-', $tx_date);
    if (count($parts) === 3 && intval($parts[0]) > 2500) {
        $parts[0] = intval($parts[0]) - 543;
        $tx_date = sprintf("%04d-%02d-%02d", $parts[0], $parts[1], $parts[2]);
    }
}

$note        = trim($_POST['note'] ?? '');

if ($material_id <= 0 || !in_array($type, ['in', 'out']) || empty($requester) || $qty <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ข้อมูลไม่ครบถ้วน หรือไม่ถูกต้อง']);
    exit;
}

// Check material exists
$material = inv_row("SELECT * FROM materials WHERE id = ?", [$material_id]);
if (!$material) {
    echo json_encode(['ok' => false, 'error' => 'ไม่พบวัสดุนี้ในระบบ']);
    exit;
}

// Get last transaction to calculate balance
$last_tx = inv_row("SELECT balance_qty, balance_price FROM material_transactions WHERE material_id = ? ORDER BY id DESC LIMIT 1", [$material_id]);
$prev_qty   = $last_tx ? intval($last_tx['balance_qty']) : 0;
$prev_price = $last_tx ? floatval($last_tx['balance_price']) : 0;

$tx_total_price = $qty * $unit_price;

if ($type === 'in') {
    $bal_qty = $prev_qty + $qty;
    $bal_price = $prev_price + $tx_total_price;
} else {
    if ($qty > $prev_qty) {
        echo json_encode(['ok' => false, 'error' => 'จำนวนคงเหลือไม่เพียงพอ']);
        exit;
    }
    $bal_qty = $prev_qty - $qty;
    $bal_price = $prev_price - $tx_total_price;
}

// Insert transaction
try {
    $ok = inv_exec(
        "INSERT INTO material_transactions 
         (material_id, type, qty, ref_doc, requester, note, created_at, unit_price, balance_qty, balance_price)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$material_id, $type, $qty, $ref_doc, $requester, $note, $tx_date . ' ' . date('H:i:s'), $unit_price, $bal_qty, $bal_price]
    );

    if ($ok) {
        // Update material summary
        inv_exec("UPDATE materials SET qty_in_stock = ?, unit_cost = ? WHERE id = ?", [$bal_qty, $unit_price, $material_id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
}
