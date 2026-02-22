<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/backend/config/db_inventory.php';

// ---- ข้อมูลครู (hardcode) ----
$teachers = [
    'น.ส.ณัฐนรี พื้นผา',
    'นางดอกจาน แก้วพิกุล',
    'น.ส.พิติยา ลาโสพันธ์',
    'น.ส.รัญญภัสร์ จิตธนาชัยพงศ์',
    'น.ส.รัตน์ดา เนียมมูล',
    'น.ส.ศิรินันท์ พันธ์ทอง',
    'นายสุชาติ คู่แก้ว',
    'นายสุริยะ พรมตวง',
    'นางอำนวย แก้วสง่า',
    'น.ส.เนตรศิริ ชินชัย',
];

$th_months = [
    1  => 'มกราคม',   2  => 'กุมภาพันธ์', 3  => 'มีนาคม',
    4  => 'เมษายน',   5  => 'พฤษภาคม',    6  => 'มิถุนายน',
    7  => 'กรกฎาคม',  8  => 'สิงหาคม',    9  => 'กันยายน',
    10 => 'ตุลาคม',   11 => 'พฤศจิกายน',  12 => 'ธันวาคม',
];

// ---- ค่าตัวกรอง ----
$sel_month   = isset($_GET['month'])   ? (int)$_GET['month']   : (int)date('n');
$sel_year_be = isset($_GET['year_be']) ? (int)$_GET['year_be'] : ((int)date('Y') + 543);
$sel_year_ad = $sel_year_be - 543;
$sel_teacher = $_GET['teacher'] ?? '';
$do_preview  = !empty($_GET['preview']);

// ---- ดึงข้อมูลใบเบิก ----
function getSlipRows(int $month, int $year_ad, string $teacher): array {
    $sql = "
        SELECT mt.id, mt.requester, mt.qty, mt.note, mt.created_at,
               m.name AS mat_name, m.unit AS mat_unit
        FROM material_transactions mt
        JOIN materials m ON m.id = mt.material_id
        WHERE mt.type = 'out'
          AND MONTH(mt.created_at) = ?
          AND YEAR(mt.created_at)  = ?
          AND mt.requester = ?
        ORDER BY mt.created_at ASC
    ";
    return inv_query($sql, [$month, $year_ad, $teacher]);
}

// ---- สร้าง list ครูที่จะพิมพ์ ----
$print_teachers = [];
if ($do_preview) {
    if ($sel_teacher === '__all__') {
        $print_teachers = []; // วน loop ด้านล่าง
        foreach ($GLOBALS['teachers'] as $t) {
            $rows = getSlipRows($sel_month, $sel_year_ad, $t);
            if (!empty($rows)) {
                $print_teachers[] = ['name' => $t, 'rows' => $rows];
            }
        }
    } elseif ($sel_teacher !== '') {
        $rows = getSlipRows($sel_month, $sel_year_ad, $sel_teacher);
        $print_teachers[] = ['name' => $sel_teacher, 'rows' => $rows];
    }
}

// ---- ฟังก์ชัน render ใบเบิก 1 ใบ ----
function renderSlip(array $slipData, string $month_name, int $year_be, int $slip_no = 1, bool $isLast = false): void {
    $teacher_name = $slipData['name'];
    $rows         = $slipData['rows'];
    $total_rows   = 15; // จำนวนแถวในตารางทั้งหมด
    $page_break_style = $isLast ? '' : 'page-break-after: always;';
    $slip_no_fmt  = str_pad($slip_no, 3, '0', STR_PAD_LEFT) . '/' . $year_be;
    ?>
    <div class="slip-wrapper" style="<?= $page_break_style ?>">
        <!-- Header -->
        <div class="slip-header-right">
            <div>เลขที่ <strong><?= $slip_no_fmt ?></strong></div>
            <div>กลุ่มงานพัสดุและทรัพย์สิน</div>
        </div>
        <div class="slip-title-block">
            <div class="slip-title-th">ใบเบิกพัสดุ</div>
            <div class="slip-school">โรงเรียนบ้านโคกวิทยา</div>
        </div>

        <div class="slip-date-line">
            วันที่ ......... เดือน <strong><?= $month_name ?></strong>&nbsp;&nbsp; พ.ศ. <strong><?= $year_be ?></strong>
        </div>

        <div class="slip-requester-line">
            ข้าพเจ้าขอเบิกพัสดุตามรายการต่อไปนี้เพื่อใช้งาน
            <span class="underline-dotted" style="min-width:350px; display:inline-block;">&nbsp;</span>
        </div>

        <!-- ตารางรายการ -->
        <table class="slip-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width:40px;">ลำดับ<br>ที่</th>
                    <th rowspan="2">รายการ</th>
                    <th colspan="2" style="width:120px;">จำนวน</th>
                    <th rowspan="2" style="width:90px;">หมายเหตุ</th>
                </tr>
                <tr>
                    <th style="width:60px;">ขอเบิก</th>
                    <th style="width:60px;">เบิกได้</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 0;
                foreach ($rows as $row):
                    $count++;
                ?>
                <tr>
                    <td class="text-center"><?= $count ?></td>
                    <td><?= htmlspecialchars($row['mat_name']) ?>
                        <?php if (!empty($row['mat_unit'])): ?>
                            <span style="color:#555;">(<?= htmlspecialchars($row['mat_unit']) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int)$row['qty'] ?></td>
                    <td></td>
                    <td><?= htmlspecialchars($row['note'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>

                <?php
                // เติมแถวว่างให้ครบ total_rows
                for ($i = $count + 1; $i <= $total_rows; $i++):
                ?>
                <tr>
                    <td class="text-center"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- ส่วนลงนาม -->
        <table class="slip-sign-table">
            <tr>
                <td class="sign-cell">
                    <div class="sign-title">อนุญาตให้เบิกได้</div>
                    <div class="sign-line">................................................ผู้ส่งจ่ายพัสดุ</div>
                </td>
                <td class="sign-cell">
                    <div style="text-align:center;">ลงชื่อ..............................................ผู้เบิก</div>
                    <div style="text-align:center;">( <?= htmlspecialchars($teacher_name) ?> )</div>
                    <div class="sign-line-sm" style="text-align:center;">ตำแหน่ง หัวหน้ากลุ่มสาระฯ/งาน/ฝ่าย.....................</div>
                </td>
            </tr>
            <tr>
                <td class="sign-cell">
                    <div class="sign-title">ได้ตรวจ , หัก จำนวนแล้ว</div>
                    <div class="sign-line">................................................เจ้าหน้าที่</div>
                </td>
                <td class="sign-cell">
                    <div>ได้มอบให้................................................................</div>
                    <div>เป็นผู้รับของแทน.........................................................</div>
                    <div>ลงชื่อ..............................................ผู้มอบ</div>
                    <div class="sign-line">ลงชื่อ..............................................ผู้รับมอบ</div>
                </td>
            </tr>
            <tr>
                <td class="sign-cell" colspan="1">
                    <div class="sign-title">ได้รับของไปถูกต้องแล้ว</div>
                    <div class="sign-line" style="text-align:center;">................................................ผู้รับของ</div>
                    <div style="text-align:center;">( <?= htmlspecialchars($teacher_name) ?> )</div>
                    <div style="text-align:center;">......... / ................. / .................</div>
                </td>
                <td></td>
            </tr>
        </table>
    </div><!-- end slip-wrapper -->
    <?php
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>พิมพ์ใบเบิกพัสดุ — โรงเรียนบ้านโคกวิทยา</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ============================================================
   GLOBAL / SCREEN STYLES
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: 'Sarabun', sans-serif;
    background: #f0f4f8;
    margin: 0; padding: 0;
    color: #222;
}

/* ---- Filter Panel (screen only) ---- */
.filter-panel {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
    padding: 32px 36px;
    max-width: 680px;
    margin: 40px auto;
}
.filter-panel h2 {
    font-size: 22px; font-weight: 700; margin: 0 0 6px;
    background: linear-gradient(135deg, #4e54c8, #8f94fb);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.filter-panel p { color: #777; font-size: 14px; margin: 0 0 24px; }
.form-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 16px; margin-bottom: 20px;
}
.form-grid .full { grid-column: 1 / -1; }
.form-label { font-size: 13px; font-weight: 600; color: #555; display: block; margin-bottom: 6px; }
.form-control {
    width: 100%; height: 42px; border-radius: 10px;
    border: 1.5px solid #e0e2f0; padding: 0 12px;
    font-family: 'Sarabun', sans-serif; font-size: 14px;
    color: #333; background: #fff; outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-control:focus { border-color: #4e54c8; box-shadow: 0 0 0 3px rgba(78,84,200,0.12); }
.btn-group-action { display: flex; gap: 12px; }
.btn {
    flex: 1; height: 44px; border-radius: 10px; font-family: 'Sarabun', sans-serif;
    font-size: 15px; font-weight: 600; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: all 0.2s;
}
.btn-preview {
    background: linear-gradient(135deg, #4e54c8, #8f94fb);
    color: #fff; box-shadow: 0 4px 12px rgba(78,84,200,0.25);
}
.btn-preview:hover { box-shadow: 0 6px 18px rgba(78,84,200,0.35); transform: translateY(-1px); }
.btn-print {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: #fff; box-shadow: 0 4px 12px rgba(17,153,142,0.25);
}
.btn-print:hover { box-shadow: 0 6px 18px rgba(17,153,142,0.35); transform: translateY(-1px); }

/* ---- Slip Area (screen) ---- */
.slip-area { max-width: 794px; margin: 0 auto 60px; }

.slip-wrapper {
    background: #fff;
    border: 1px solid #ddd;
    padding: 20mm 18mm;
    margin-bottom: 30px;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

/* ---- Slip Content Styles ---- */
.slip-header-right {
    text-align: right;
    font-size: 13pt;
    line-height: 1.8;
    margin-bottom: 4px;
}
.slip-title-block { text-align: center; margin-bottom: 6px; }
.slip-title-th { font-size: 18pt; font-weight: 700; }
.slip-school    { font-size: 14pt; font-weight: 600; }

.slip-date-line {
    text-align: center;
    font-size: 13pt;
    margin: 8px 0 6px;
}
.slip-requester-line {
    font-size: 12.5pt;
    margin-bottom: 10px;
    line-height: 2;
    border-bottom: 1px dotted #999;
    padding-bottom: 4px;
}
.underline-dotted {
    border-bottom: 1px dotted #333;
    padding: 0 4px;
}

/* ---- Main Table ---- */
.slip-table {
    width: 100%; border-collapse: collapse;
    font-size: 12pt;
    margin-bottom: 0;
}
.slip-table th, .slip-table td {
    border: 1px solid #333;
    padding: 4px 8px;
    vertical-align: middle;
}
.slip-table th {
    text-align: center; font-weight: 600;
    background: #f5f5f5;
}
.slip-table tbody tr { height: 26px; }
.text-center { text-align: center; }

/* ---- Signature Table ---- */
.slip-sign-table {
    width: 100%; border-collapse: collapse;
    font-size: 11.5pt;
    margin-top: 0;
    border-top: none;
}
.slip-sign-table td {
    border: 1px solid #333;
    padding: 8px 12px;
    vertical-align: top;
    width: 50%;
}
.sign-title { font-weight: 600; margin-bottom: 6px; }
.sign-line  { margin-top: 24px; }
.sign-line-sm { margin-top: 8px; font-size: 11pt; }

/* ---- Alert / Empty state ---- */
.alert-info-slip {
    background: #eef0ff; border-left: 4px solid #4e54c8;
    border-radius: 10px; padding: 14px 18px;
    color: #444; font-size: 14px; margin: 20px 0;
}

/* ============================================================
   PRINT STYLES
   ============================================================ */
@media print {
    @page { size: A4 portrait; margin: 12mm 12mm 10mm; }

    body { background: #fff !important; }
    .filter-panel, .btn-toolbar { display: none !important; }
    .slip-area { margin: 0; max-width: 100%; }

    .slip-wrapper {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin-bottom: 0 !important;
        border-radius: 0 !important;
    }

    .slip-header-right { font-size: 11pt; }
    .slip-title-th { font-size: 16pt; }
    .slip-school   { font-size: 12pt; }
    .slip-date-line, .slip-requester-line { font-size: 11pt; }
    .slip-table, .slip-sign-table  { font-size: 10pt; }
    .slip-table th, .slip-table td,
    .slip-sign-table td { padding: 3px 6px; }
    .slip-table tbody tr { height: 22px; }
}
</style>
</head>
<body>

<!-- ============================================================
     FILTER PANEL (screen only, hidden on print)
     ============================================================ -->
<div class="filter-panel">
    <div style="margin-bottom:16px;">
        <a href="material.php" style="display:inline-flex; align-items:center; gap:6px; color:#4e54c8; font-size:14px; font-weight:600; text-decoration:none; padding:6px 14px; border:1.5px solid #4e54c8; border-radius:20px; transition:all 0.2s;"
           onmouseover="this.style.background='#4e54c8';this.style.color='#fff';" onmouseout="this.style.background='transparent';this.style.color='#4e54c8';">
            ← กลับ
        </a>
    </div>
    <h2>🖨️ พิมพ์ใบเบิกพัสดุ</h2>
    <p>โรงเรียนบ้านโคกวิทยา — เลือกเดือน / ปี / ชื่อครู แล้วคลิกดูตัวอย่าง</p>

    <form method="GET" action="">
        <div class="form-grid">
            <div>
                <label class="form-label">เดือน</label>
                <select name="month" class="form-control">
                    <?php foreach ($th_months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $sel_month == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">ปี พ.ศ.</label>
                <select name="year_be" class="form-control">
                    <?php
                    $current_be = (int)date('Y') + 543;
                    for ($y = $current_be; $y >= $current_be - 5; $y--):
                    ?>
                    <option value="<?= $y ?>" <?= $sel_year_be == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="full">
                <label class="form-label">ชื่อครู</label>
                <select name="teacher" class="form-control">
                    <option value="__all__" <?= $sel_teacher === '__all__' ? 'selected' : '' ?>>— ทั้งหมด (ทุกคนที่มีการเบิก) —</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $sel_teacher === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <input type="hidden" name="preview" value="1">
        <div class="btn-group-action">
            <button type="submit" class="btn btn-preview">
                🔍 ดูตัวอย่าง
            </button>
            <?php if ($do_preview && !empty($print_teachers)): ?>
            <button type="button" class="btn btn-print" onclick="window.print()">
                🖨️ พิมพ์ใบเบิก
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ============================================================
     SLIP AREA
     ============================================================ -->
<?php if ($do_preview): ?>
<div class="slip-area">
    <?php if (empty($print_teachers)): ?>
        <div class="alert-info-slip">
            ⚠️ ไม่พบรายการเบิกวัสดุในเดือน <strong><?= $th_months[$sel_month] ?> <?= $sel_year_be ?></strong>
            <?php if ($sel_teacher && $sel_teacher !== '__all__'): ?>
                สำหรับ <strong><?= htmlspecialchars($sel_teacher) ?></strong>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php 
        $total_slips = count($print_teachers);
        foreach ($print_teachers as $idx => $slipData):
            $isLast = ($idx === $total_slips - 1);
            renderSlip($slipData, $th_months[$sel_month], $sel_year_be, $idx + 1, $isLast);
        endforeach;
        ?>
    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
