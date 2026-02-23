<?php
/**
 * get_next_order_no.php — คืนค่าเลขที่ถัดไปแบบ JSON
 * GET params: order_type (buy|hire), fiscal_year (int)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../backend/config/db_inventory.php';

header('Content-Type: application/json; charset=utf-8');

$order_type  = in_array($_GET['order_type'] ?? '', ['buy', 'hire']) ? $_GET['order_type'] : 'buy';
$fiscal_year = intval($_GET['fiscal_year'] ?? (int)(date('Y') + 543));

$prefix = ($order_type === 'buy') ? 'ซ' : 'จ';

// นับลำดับถัดไป (รวมทุกสถานะ ยกเลิกด้วย เพื่อไม่ให้เลขซ้ำ)
$count = inv_row(
    "SELECT COUNT(*)+1 AS n FROM procurement_orders WHERE order_type=? AND fiscal_year=?",
    [$order_type, $fiscal_year]
);
$next_no = $count['n'] ?? 1;

$order_no = $prefix . str_pad($next_no, 2, '0', STR_PAD_LEFT) . '/' . $fiscal_year;

echo json_encode(['order_no' => $order_no, 'next' => $next_no]);
