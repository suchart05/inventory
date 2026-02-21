<?php
require_once __DIR__ . '/backend/auth_check.php';
require_once __DIR__ . '/backend/config/db_inventory.php';

$action = $_GET['action'] ?? '';
$id     = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: asset.php'); exit; }

switch ($action) {
    case 'writeoff':
        inv_exec("UPDATE assets SET status='writeoff' WHERE id=? AND status!='writeoff'", [$id]);
        header('Location: asset.php?msg=writeoff'); exit;

    case 'set_status':
        $s = $_GET['status'] ?? '';
        if (in_array($s, ['available','inuse','repair','writeoff']))
            inv_exec("UPDATE assets SET status=? WHERE id=?", [$s,$id]);
        header('Location: asset.php?msg=updated'); exit;

    case 'delete':
        inv_exec("DELETE FROM assets WHERE id=?", [$id]);
        header('Location: asset.php?msg=deleted'); exit;

    default:
        header('Location: asset.php'); exit;
}
