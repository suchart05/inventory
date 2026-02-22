<?php
/**
 * save_procurement.php — รับ POST จาก procurement.php (Add / Edit)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['inv_user_id'])) {
    header('Location: ../index.php'); exit;
}
require_once __DIR__ . '/../backend/config/db_inventory.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../procurement.php'); exit;
}

$id           = intval($_POST['order_id'] ?? 0);
$order_no     = trim($_POST['order_no']     ?? '');
$order_type   = in_array($_POST['order_type'] ?? '', ['buy','hire']) ? $_POST['order_type'] : 'buy';
$order_date   = $_POST['order_date']   ?? '';
$due_date     = $_POST['due_date']     ?? '' ?: null;
$inspect_date = $_POST['inspect_date'] ?? '' ?: null;
$fiscal_year  = intval($_POST['fiscal_year'] ?? (int)(date('Y') + 543));
$title        = trim($_POST['title']        ?? '');
$total_amount = floatval($_POST['total_amount'] ?? 0);
$money_group  = $_POST['money_group']  ?? 'operation';
$proc_method  = 'specific';
$status       = $_POST['status']       ?? 'received';
$doc_ref      = trim($_POST['doc_ref']      ?? '') ?: null;
$egp_no       = trim($_POST['egp_no']       ?? '') ?: null;
$vendor_name  = trim($_POST['vendor_name']  ?? '') ?: null;
$note         = trim($_POST['note']         ?? '') ?: null;
$created_by   = $_SESSION['inv_user_id'];

// Auto-generate order_no if not provided
if (!$order_no && $id === 0) {
    $prefix = ($order_type === 'buy') ? 'ซ' : 'จ';
    $count  = inv_row("SELECT COUNT(*)+1 AS n FROM procurement_orders WHERE order_type=? AND fiscal_year=?", [$order_type, $fiscal_year]);
    $order_no = $prefix . str_pad($count['n'] ?? 1, 2, '0', STR_PAD_LEFT) . '/' . $fiscal_year;
}

$allowed_money  = ['operation','salary','investment','support','special','income','subsidy'];
$allowed_status = ['pending','approved','received','cancelled'];

if (!$order_date || !$title) {
    header('Location: ../procurement.php?error=missing_fields'); exit;
}
if (!in_array($money_group, $allowed_money)) $money_group = 'operation';
if (!in_array($status, $allowed_status))     $status = 'received';
$order_date   = $order_date ?: null;

if ($id > 0) {
    // --- UPDATE ---
    inv_exec(
        "UPDATE procurement_orders SET
            order_no=?, order_type=?, order_date=?, due_date=?, inspect_date=?,
            fiscal_year=?, title=?, total_amount=?, money_group=?, proc_method=?,
            status=?, doc_ref=?, egp_no=?, vendor_name=?, note=?
         WHERE id=?",
        [$order_no, $order_type, $order_date, $due_date, $inspect_date,
         $fiscal_year, $title, $total_amount, $money_group, $proc_method,
         $status, $doc_ref, $egp_no, $vendor_name, $note, $id]
    );
} else {
    // --- INSERT ---
    inv_exec(
        "INSERT INTO procurement_orders
            (order_no, order_type, order_date, due_date, inspect_date,
             fiscal_year, title, total_amount, money_group, proc_method,
             status, doc_ref, egp_no, vendor_name, note, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$order_no, $order_type, $order_date, $due_date, $inspect_date,
         $fiscal_year, $title, $total_amount, $money_group, $proc_method,
         $status, $doc_ref, $egp_no, $vendor_name, $note, $created_by]
    );
}

header('Location: ../procurement.php?tab=' . $order_type . '&fiscal_year=' . $fiscal_year);
exit;
