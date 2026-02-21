<?php
/**
 * migrate_procurement_v2.php
 * เพิ่มคอลัมน์ due_date, inspect_date, egp_no ใน procurement_orders
 * รันครั้งเดียว แล้วลบทิ้ง
 */
require_once __DIR__ . '/backend/config/db_inventory.php';

$results = [];

$migrations = [
    'due_date'     => "ALTER TABLE procurement_orders ADD COLUMN due_date DATE DEFAULT NULL AFTER order_date",
    'inspect_date' => "ALTER TABLE procurement_orders ADD COLUMN inspect_date DATE DEFAULT NULL AFTER due_date",
    'egp_no'       => "ALTER TABLE procurement_orders ADD COLUMN egp_no VARCHAR(50) DEFAULT NULL AFTER doc_ref",
];

foreach ($migrations as $col => $sql) {
    // Check if column already exists
    $exists = $inv_conn->query("SHOW COLUMNS FROM procurement_orders LIKE '$col'");
    if ($exists && $exists->num_rows > 0) {
        $results[] = ['col' => $col, 'ok' => true, 'msg' => 'มีอยู่แล้ว (ข้าม)'];
    } else {
        $ok = $inv_conn->query($sql);
        $results[] = ['col' => $col, 'ok' => $ok, 'msg' => $ok ? 'เพิ่มสำเร็จ' : $inv_conn->error];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Migrate Procurement v2</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body { font-family:Kanit,sans-serif; background:#f0f2f5; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.1); padding:36px 44px; max-width:540px; width:100%; }
    h3 { color:#4e54c8; margin:0 0 20px; }
    .row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f0f0f0; font-size:14px; }
    .badge-ok  { background:#e8f5e9; color:#2e7d32; border-radius:20px; padding:3px 12px; font-weight:600; font-size:12px; }
    .badge-err { background:#ffebee; color:#c62828; border-radius:20px; padding:3px 12px; font-weight:600; font-size:12px; }
    .col-name  { flex:1; font-weight:600; color:#333; font-family:monospace; }
    .btn { display:inline-block; margin-top:20px; padding:10px 28px; background:#4e54c8; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; }
    .warn { background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:10px 14px; margin-top:16px; font-size:13px; color:#795548; }
</style>
</head>
<body>
<div class="box">
    <h3>🔧 Migrate Procurement v2</h3>
    <?php foreach ($results as $r): ?>
    <div class="row">
        <span class="col-name">📌 <?= $r['col'] ?></span>
        <span class="<?= $r['ok'] ? 'badge-ok' : 'badge-err' ?>"><?= $r['msg'] ?></span>
    </div>
    <?php endforeach; ?>
    <div class="warn">⚠️ หลังจาก migrate เสร็จ ให้ลบไฟล์นี้ทิ้งครับ</div>
    <a href="procurement.php" class="btn">➡️ ไปหน้าพัสดุ</a>
</div>
</body>
</html>
