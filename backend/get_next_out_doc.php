<?php
/**
 * Returns the next OUT document number in format OUT-YYMM-XXX
 * where YY = 2-digit year, MM = 2-digit month, XXX = sequential number (001, 002, ...)
 */
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db_inventory.php';

header('Content-Type: application/json; charset=utf-8');

// Current year/month (Gregorian)
$yy = date('y');   // e.g. "26" for 2026
$mm = date('m');   // e.g. "02" for February

$prefix = "OUT-{$yy}{$mm}-";

// Find the highest sequential number used this month
$row = inv_row(
    "SELECT ref_doc FROM material_transactions
     WHERE type = 'out' AND ref_doc LIKE ?
     ORDER BY ref_doc DESC LIMIT 1",
    [$prefix . '%']
);

$next_seq = 1;
if ($row) {
    // ref_doc format: OUT-YYMM-XXX  → extract last 3 digits
    $parts = explode('-', $row['ref_doc']);
    $last_seq = intval(end($parts));
    $next_seq = $last_seq + 1;
}

$next_doc = $prefix . str_pad($next_seq, 3, '0', STR_PAD_LEFT);

echo json_encode(['ok' => true, 'doc_no' => $next_doc]);
