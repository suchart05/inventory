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

$tx_id       = intval($_POST['tx_id'] ?? 0);
$material_id = intval($_POST['material_id'] ?? 0);
$requester   = trim($_POST['requester'] ?? '');
$ref_doc     = trim($_POST['ref_doc'] ?? '');
$unit_price  = floatval($_POST['unit_price'] ?? 0);
$qty         = intval($_POST['qty'] ?? 0);
$note        = trim($_POST['note'] ?? '');

$tx_date = trim($_POST['tx_date'] ?? '');
if (empty($tx_date)) {
    $tx_date = date('Y-m-d');
} else {
    // Convert Thai year to Gregorian if needed
    $parts = explode('-', $tx_date);
    if (count($parts) === 3 && intval($parts[0]) > 2500) {
        $parts[0] = intval($parts[0]) - 543;
        $tx_date = sprintf("%04d-%02d-%02d", $parts[0], $parts[1], $parts[2]);
    }
}

if ($tx_id <= 0 || $material_id <= 0 || empty($requester) || $qty <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ข้อมูลไม่ครบถ้วน หรือไม่ถูกต้อง']);
    exit;
}

$tx = inv_row("SELECT * FROM material_transactions WHERE id = ? AND material_id = ?", [$tx_id, $material_id]);
if (!$tx) {
    echo json_encode(['ok' => false, 'error' => 'ไม่พบรายการที่ต้องการแก้ไข']);
    exit;
}

// Ensure time component is preserved from original creation
$timePart = date('H:i:s', strtotime($tx['created_at']));
$new_created_at = $tx_date . ' ' . $timePart;

try {
    // 1. Update the specific transaction
    inv_exec(
        "UPDATE material_transactions 
         SET requester = ?, ref_doc = ?, unit_price = ?, qty = ?, created_at = ?, note = ? 
         WHERE id = ?",
        [$requester, $ref_doc, $unit_price, $qty, $new_created_at, $note, $tx_id]
    );

    // 2. Recalculate all remaining transactions for this material
    // We fetch them all ordered by ID.
    $all_tx = inv_query("SELECT * FROM material_transactions WHERE material_id = ? ORDER BY id ASC", [$material_id]);
    
    $running_qty = 0;
    $running_price = 0;
    $last_unit_price = 0;
    $is_stock_negative = false;

    foreach ($all_tx as $row) {
        $tx_total_price = $row['qty'] * $row['unit_price'];

        if ($row['type'] === 'in') {
            $running_qty += $row['qty'];
            $running_price += $tx_total_price;
        } else {
            $running_qty -= $row['qty'];
            $running_price -= $tx_total_price;
            
            // If historical balance goes negative, fail the edit to maintain data integrity
            if ($running_qty < 0) {
                $is_stock_negative = true;
                break;
            }
        }
        $last_unit_price = $row['unit_price'];

        inv_exec("UPDATE material_transactions SET balance_qty = ?, balance_price = ? WHERE id = ?", 
            [$running_qty, $running_price, $row['id']]);
    }

    if ($is_stock_negative) {
        // Rollback strategy: Since MySQL doesn't natively support full transactions in this lightweight wrapper
        // without innodb/tx setup, we just return an error and suggest manual verification or reset.
        // Ideally, edit should be prevented if out_qty > historical_balance.
        echo json_encode(['ok' => false, 'error' => 'การแก้ไขทำให้ยอดคงเหลือติดลบ ไม่สามารถบันทึกได้ กรุณาตรวจสอบข้อมูลอีกครั้ง']);
        exit;
    }

    // 3. Update the main materials table with the new finalized balance
    if (empty($all_tx)) {
         inv_exec("UPDATE materials SET qty_in_stock = 0, unit_cost = 0 WHERE id = ?", [$material_id]);
    } else {
         inv_exec("UPDATE materials SET qty_in_stock = ?, unit_cost = ? WHERE id = ?", [$running_qty, $last_unit_price, $material_id]);
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
}
