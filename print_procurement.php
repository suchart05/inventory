<?php
/**
 * print_procurement.php — พิมพ์ทะเบียนคุมเลขที่จัดซื้อ/จัดจ้าง
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/backend/config/db_inventory.php';

// ---- Params ----
$fiscal_year = isset($_GET['fiscal_year']) ? intval($_GET['fiscal_year']) : (int)(date('Y') + 543);
$order_type  = $_GET['order_type'] ?? 'buy'; // buy | hire | all

// ---- Fetch ----
$where  = "WHERE fiscal_year = ?";
$params = [$fiscal_year];
if ($order_type !== 'all') {
    $where .= " AND order_type = ?";
    $params[] = $order_type;
}
$orders = inv_query(
    "SELECT * FROM procurement_orders $where ORDER BY order_date ASC, id ASC",
    $params
);

// ---- Thai date helper ----
function thaiDate($date) {
    if (!$date || $date === '0000-00-00') return '-';
    $thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($date);
    if (!$ts) return '-';
    $d  = (int)date('j', $ts);
    $m  = (int)date('n', $ts);
    $y  = (int)date('Y', $ts) + 543;
    return $d . ' ' . $thMonths[$m] . ' ' . $y;
}

// ---- Money group label ----
$moneyLabel = [
    'operation'  => 'งบดำเนินงาน',
    'salary'     => 'จ้างเหมา',
    'special'    => 'โครงการพิเศษ',
    'income'     => 'รายได้สถานศึกษา',
    'subsidy'    => 'อุดหนุน อปท.',
    'investment' => 'ลงทุน/ซ่อมแซม',
];

$typeLabel = $order_type === 'hire' ? 'จัดจ้าง' : ($order_type === 'all' ? 'จัดซื้อ/จัดจ้าง' : 'จัดซื้อ');
$totalAmount = array_sum(array_column($orders, 'total_amount'));

// Available fiscal years for filter
$years = inv_query("SELECT DISTINCT fiscal_year FROM procurement_orders ORDER BY fiscal_year DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ทะเบียนคุมเลขที่<?= $typeLabel ?> ปีงบ <?= $fiscal_year ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ====== General ====== */
*, *::before, *::after { box-sizing: border-box; }
body {
    font-family: 'Sarabun', sans-serif;
    font-size: 13px;
    background: #f0f2f5;
    margin: 0;
    padding: 0;
    color: #333;
}

/* ====== Print Controls (screen only) ====== */
.print-controls {
    background: #4e54c8;
    color: #fff;
    padding: 12px 24px;
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}
.print-controls label { font-size: 13px; opacity: .85; }
.print-controls select, .print-controls input {
    border-radius: 8px;
    border: none;
    padding: 5px 10px;
    font-size: 13px;
    font-family: 'Sarabun', sans-serif;
}
.print-controls .btn-print {
    background: #fff;
    color: #4e54c8;
    border: none;
    border-radius: 8px;
    padding: 7px 20px;
    font-weight: 700;
    cursor: pointer;
    font-family: 'Sarabun', sans-serif;
    font-size: 13px;
}
.print-controls .btn-back {
    background: transparent;
    color: #fff;
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 8px;
    padding: 6px 16px;
    text-decoration: none;
    font-size: 13px;
}

/* ====== Report Page ====== */
.report-page {
    width: 297mm;
    min-height: 210mm;
    margin: 16px auto;
    background: #fff;
    padding: 12mm 10mm 10mm;
    box-shadow: 0 2px 16px rgba(0,0,0,.12);
}

/* ====== Title ====== */
.report-title {
    text-align: center;
    margin-bottom: 6px;
}
.report-title .main-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a237e;
}
.report-title .sub-title {
    font-size: 12px;
    color: #555;
    margin-top: 2px;
}

/* ====== Table ====== */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 11.5px;
}
thead tr:first-child th {
    background: #b8cce4;
    color: #1a237e;
    font-weight: 700;
    border: 1px solid #8bafd4;
    padding: 5px 4px;
    text-align: center;
    vertical-align: middle;
}
thead tr:last-child th {
    background: #dce6f1;
    color: #333;
    font-weight: 600;
    border: 1px solid #8bafd4;
    padding: 5px 4px;
    text-align: center;
    vertical-align: middle;
}
tbody tr td {
    border: 1px solid #b8cce4;
    padding: 4px 5px;
    vertical-align: top;
}
tbody tr:nth-child(even) td { background: #f0f5fb; }
tbody tr:nth-child(odd)  td { background: #ffffff; }
tbody tr:hover td { background: #e3edf8; }

.td-center { text-align: center; }
.td-right  { text-align: right; }
.td-no     { width: 22px; text-align: center; color: #777; }
.td-docno  { width: 55px; font-weight: 600; color: #1a237e; white-space: nowrap; }
.td-item   { min-width: 110px; }
.td-date   { width: 52px; text-align: center; white-space: nowrap; }
.td-amount { width: 58px; text-align: right; white-space: nowrap; }
.td-vendor { width: 85px; }
.td-egp    { width: 65px; text-align: center; color: #555; }
.td-group  { width: 60px; text-align: center; }
.td-note   { width: 70px; }

/* Total row */
.total-row td { font-weight: 700; background: #dce6f1 !important; border-top: 2px solid #8bafd4; }

/* ====== Print Styles ====== */
@media print {
    body { background: #fff; }
    .print-controls { display: none !important; }
    .report-page {
        margin: 0;
        padding: 8mm 8mm 8mm;
        box-shadow: none;
        width: 100%;
    }
    thead { display: table-header-group; }
    tbody tr { page-break-inside: avoid; }
    table { font-size: 11px; }
}

@page {
    size: A4 landscape;
    margin: 8mm;
}
</style>
</head>
<body>

<!-- ===== Print Controls (hidden on print) ===== -->
<div class="print-controls">
    <a href="procurement.php" class="btn-back">← กลับ</a>
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <label>ปีงบประมาณ:</label>
        <select name="fiscal_year" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?>
            <option value="<?= $y['fiscal_year'] ?>" <?= $y['fiscal_year'] == $fiscal_year ? 'selected' : '' ?>><?= $y['fiscal_year'] ?></option>
            <?php endforeach; ?>
            <?php $cy = (int)(date('Y')+543); if (!in_array($cy, array_column($years,'fiscal_year'))): ?>
            <option value="<?= $cy ?>" <?= $cy == $fiscal_year ? 'selected' : '' ?>><?= $cy ?></option>
            <?php endif; ?>
        </select>
        <label>ประเภท:</label>
        <select name="order_type" onchange="this.form.submit()">
            <option value="buy"  <?= $order_type==='buy'  ? 'selected':'' ?>>จัดซื้อ</option>
            <option value="hire" <?= $order_type==='hire' ? 'selected':'' ?>>จัดจ้าง</option>
            <option value="all"  <?= $order_type==='all'  ? 'selected':'' ?>>ทั้งหมด</option>
        </select>
    </form>
    <button class="btn-print" onclick="window.print()">🖨️ พิมพ์ / บันทึก PDF</button>
    <span style="opacity:.7;font-size:12px;">พบ <?= count($orders) ?> รายการ | รวม ฿<?= number_format($totalAmount,2) ?></span>
</div>

<!-- ===== Report Page ===== -->
<div class="report-page">
    <div class="report-title">
        <div class="main-title">ทะเบียนคุมเลขที่<?= $typeLabel ?> ปีงบประมาณ <?= $fiscal_year ?></div>
        <div class="sub-title">โรงเรียนบ้านโคกวิทยา ต.ตระการ กลุ่มโรงเรียนเมืองกันทร์ สำนักงานเขตพื้นที่การศึกษาประถมศึกษาศรีสะเกษ เขต 4</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="td-no">ที่</th>
                <th rowspan="2" class="td-docno">เลขที่<br>เอกสาร</th>
                <th rowspan="2" class="td-item">รายการ</th>
                <th rowspan="2" class="td-date">วันเดือนปี<br>รายงานขอซื้อ</th>
                <th rowspan="2" class="td-amount">จำนวนเงิน</th>
                <th colspan="2">วัน เดือน ปี</th>
                <th rowspan="2" class="td-vendor">ชื่อผู้ขาย</th>
                <th rowspan="2" class="td-egp">เลข EGP</th>
                <th rowspan="2" class="td-group">กลุ่มเงิน</th>
                <th rowspan="2" class="td-note">หมายเหตุ</th>
            </tr>
            <tr>
                <th class="td-date">วันที่ครบกำหนด<br>ส่งมอบพัสดุ</th>
                <th class="td-date">วัน เดือน ปี<br>ตรวจรับพัสดุ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
            <tr><td colspan="11" style="text-align:center;padding:20px;color:#aaa;">ไม่พบข้อมูล</td></tr>
        <?php else: ?>
            <?php foreach ($orders as $i => $o): ?>
            <tr>
                <td class="td-no"><?= $i + 1 ?></td>
                <td class="td-docno"><?= htmlspecialchars($o['order_no'] ?? '-') ?></td>
                <td class="td-item"><?= htmlspecialchars($o['title']) ?></td>
                <td class="td-date"><?= thaiDate($o['order_date']) ?></td>
                <td class="td-amount">฿<?= number_format($o['total_amount'], 2) ?></td>
                <td class="td-date"><?= thaiDate($o['due_date'] ?? null) ?></td>
                <td class="td-date"><?= thaiDate($o['inspect_date'] ?? null) ?></td>
                <td class="td-vendor"><?= htmlspecialchars($o['vendor_name'] ?? '-') ?></td>
                <td class="td-egp"><?= htmlspecialchars($o['egp_no'] ?? $o['doc_ref'] ?? '-') ?></td>
                <td class="td-group"><?= $moneyLabel[$o['money_group']] ?? $o['money_group'] ?></td>
                <td class="td-note"><?= htmlspecialchars($o['note'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <!-- Total Row -->
            <tr class="total-row">
                <td colspan="4" style="text-align:right;padding-right:8px;">รวมทั้งสิ้น</td>
                <td class="td-amount">฿<?= number_format($totalAmount, 2) ?></td>
                <td colspan="6"></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Signature area -->
    <div style="display:flex; justify-content:flex-end; margin-top:24px; gap:60px;">
        <div style="text-align:center;">
            <div style="border-top:1px solid #333; width:180px; padding-top:4px; margin-top:40px;">
                <div style="font-size:12px;">(............................................)</div>
                <div style="font-size:11px; color:#555;">ผู้จัดทำ</div>
            </div>
        </div>
        <div style="text-align:center;">
            <div style="border-top:1px solid #333; width:180px; padding-top:4px; margin-top:40px;">
                <div style="font-size:12px;">(............................................)</div>
                <div style="font-size:11px; color:#555;">เจ้าหน้าที่พัสดุ</div>
            </div>
        </div>
        <div style="text-align:center;">
            <div style="border-top:1px solid #333; width:180px; padding-top:4px; margin-top:40px;">
                <div style="font-size:12px;">(............................................)</div>
                <div style="font-size:11px; color:#555;">ผู้อำนวยการโรงเรียน</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
