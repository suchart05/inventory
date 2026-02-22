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

if (!isset($_SESSION['inv_user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$tx_id = intval($_POST['tx_id'] ?? 0);
$material_id = intval($_POST['material_id'] ?? 0);

if ($tx_id <= 0 || $material_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
    exit;
}

// Ensure the transaction exists
$tx = inv_row("SELECT * FROM material_transactions WHERE id = ? AND material_id = ?", [$tx_id, $material_id]);
if (!$tx) {
    echo json_encode(['ok' => false, 'error' => 'Transaction not found']);
    exit;
}

try {
    // 1. Delete the targeted transaction
    inv_exec("DELETE FROM material_transactions WHERE id = ?", [$tx_id]);

    // 2. Recalculate all remaining transactions for this material sequentially ordered by ID (or Date)
    // We'll use ID to keep the original chronological insert order.
    $all_tx = inv_query("SELECT * FROM material_transactions WHERE material_id = ? ORDER BY id ASC", [$material_id]);
    
    $running_qty = 0;
    $running_price = 0;
    $last_unit_price = 0;

    foreach ($all_tx as $row) {
        $tx_total_price = $row['qty'] * $row['unit_price'];

        if ($row['type'] === 'in') {
            $running_qty += $row['qty'];
            $running_price += $tx_total_price;
        } else {
            $running_qty -= $row['qty'];
            $running_price -= $tx_total_price;
        }
        $last_unit_price = $row['unit_price'];

        // Update this row's running balance
        inv_exec("UPDATE material_transactions SET balance_qty = ?, balance_price = ? WHERE id = ?", 
            [$running_qty, $running_price, $row['id']]);
    }

    // 3. Update the main materials table
    // If no transactions left, reset to qty 0 and cost 0. Otherwise use final running values.
    if (empty($all_tx)) {
         inv_exec("UPDATE materials SET qty_in_stock = 0, unit_cost = 0 WHERE id = ?", [$material_id]);
    } else {
         inv_exec("UPDATE materials SET qty_in_stock = ?, unit_cost = ? WHERE id = ?", [$running_qty, $last_unit_price, $material_id]);
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
}
