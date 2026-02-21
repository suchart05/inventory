<?php
/**
 * setup_procurement.php — สร้างตารางฐานข้อมูลสำหรับระบบงานพัสดุ
 * เปิดหน้านี้ครั้งเดียว แล้วลบทิ้งหรือ rename ได้เลย
 */
require_once __DIR__ . '/backend/config/db_inventory.php';

$results = [];

// =====================================================
// 1. ตารางหลัก: ใบสั่งซื้อ / จ้าง
// =====================================================
$sql1 = "
CREATE TABLE IF NOT EXISTS procurement_orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_no      VARCHAR(30) NOT NULL,
    order_type    ENUM('buy','hire') NOT NULL DEFAULT 'buy',
    order_date    DATE NOT NULL,
    fiscal_year   SMALLINT NOT NULL,
    title         TEXT NOT NULL,
    total_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    money_group   ENUM('operation','salary','special','income','subsidy','investment')
                  NOT NULL DEFAULT 'operation',
    proc_method   ENUM('specific','select','ebidding','price_check','donation')
                  NOT NULL DEFAULT 'specific',
    vendor_name   VARCHAR(200) DEFAULT NULL,
    vendor_address TEXT DEFAULT NULL,
    vendor_tel    VARCHAR(30) DEFAULT NULL,
    status        ENUM('pending','approved','received','cancelled')
                  NOT NULL DEFAULT 'pending',
    doc_ref       VARCHAR(100) DEFAULT NULL,
    note          TEXT DEFAULT NULL,
    created_by    INT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_no (order_no),
    INDEX idx_fiscal_year (fiscal_year),
    INDEX idx_order_type  (order_type),
    INDEX idx_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$results[] = ['table' => 'procurement_orders', 'ok' => $inv_conn->query($sql1), 'err' => $inv_conn->error];

// =====================================================
// 2. ตารางรายการย่อยในใบสั่งซื้อ
// =====================================================
$sql2 = "
CREATE TABLE IF NOT EXISTS procurement_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    item_name   VARCHAR(500) NOT NULL,
    unit        VARCHAR(50) DEFAULT NULL,
    qty         DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
    INDEX idx_order_id (order_id),
    FOREIGN KEY (order_id) REFERENCES procurement_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$results[] = ['table' => 'procurement_items', 'ok' => $inv_conn->query($sql2), 'err' => $inv_conn->error];

// =====================================================
// 3. เพิ่ม column procurement_order_id ใน assets (ถ้ายังไม่มี)
//    เพื่อเชื่อมว่าทรัพย์สินมาจากใบจัดซื้อไหน
// =====================================================
$colCheck = $inv_conn->query("SHOW COLUMNS FROM assets LIKE 'procurement_order_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $sql3 = "ALTER TABLE assets ADD COLUMN procurement_order_id INT DEFAULT NULL AFTER doc_ref,
             ADD INDEX idx_proc_order (procurement_order_id);";
    $results[] = ['table' => 'assets (add column procurement_order_id)', 'ok' => $inv_conn->query($sql3), 'err' => $inv_conn->error];
} else {
    $results[] = ['table' => 'assets (procurement_order_id)', 'ok' => true, 'err' => 'คอลัมน์มีอยู่แล้ว (ข้าม)'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Setup Procurement — Inventory</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body { font-family: Kanit, sans-serif; background: #f0f2f5; display: flex; align-items: center;
           justify-content: center; min-height: 100vh; margin: 0; }
    .box { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.1);
           padding: 40px 48px; max-width: 640px; width: 100%; }
    h3 { color: #4e54c8; margin: 0 0 24px; font-size: 22px; }
    .row { display: flex; align-items: center; gap: 12px; padding: 12px 0;
           border-bottom: 1px solid #f0f0f0; font-size: 14px; }
    .row:last-child { border-bottom: none; }
    .badge-ok  { background: #e8f5e9; color: #2e7d32; border-radius: 20px;
                 padding: 3px 12px; font-weight: 600; font-size: 12px; flex-shrink: 0; }
    .badge-err { background: #ffebee; color: #c62828; border-radius: 20px;
                 padding: 3px 12px; font-weight: 600; font-size: 12px; flex-shrink: 0; }
    .table-name { flex: 1; font-weight: 600; color: #333; }
    .err-msg { font-size: 12px; color: #c62828; }
    .warn { background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px;
            padding: 12px 16px; margin-top: 24px; font-size: 13px; color: #795548; }
    .btn { display: inline-block; margin-top: 20px; padding: 10px 28px;
           background: #4e54c8; color: #fff; border-radius: 8px; text-decoration: none;
           font-weight: 600; font-size: 14px; }
</style>
</head>
<body>
<div class="box">
    <h3>🗄️ ตั้งค่าฐานข้อมูล — ระบบงานพัสดุ</h3>

    <?php foreach ($results as $r): ?>
    <div class="row">
        <span class="table-name">📋 <?= htmlspecialchars($r['table']) ?></span>
        <?php if ($r['ok']): ?>
            <span class="badge-ok">✅ สำเร็จ</span>
        <?php else: ?>
            <span class="badge-err">❌ Error</span>
        <?php endif; ?>
        <?php if (!$r['ok'] && $r['err']): ?>
            <span class="err-msg"><?= htmlspecialchars($r['err']) ?></span>
        <?php elseif ($r['ok'] && $r['err']): ?>
            <span style="font-size:12px;color:#888;"><?= htmlspecialchars($r['err']) ?></span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="warn">
        ⚠️ เมื่อสร้างตารางสำเร็จแล้ว ควรลบหรือ rename ไฟล์นี้ทิ้ง:<br>
        <code>inventory/setup_procurement.php</code>
    </div>

    <a href="procurement.php" class="btn">➡️ ไปหน้าระบบงานพัสดุ</a>
</div>
</body>
</html>
