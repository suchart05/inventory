<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['inv_user_id']);
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { header('Location: asset.php'); exit; }

$asset   = inv_row("SELECT a.*, ac.name AS cat_name FROM assets a LEFT JOIN asset_categories ac ON a.category_code=ac.code WHERE a.id=?", [$id]);
if (!$asset) { header('Location: asset.php'); exit; }

$depRows = inv_query("SELECT * FROM depreciation_log WHERE asset_id=? ORDER BY log_date", [$id]);
$repairs = inv_query("SELECT * FROM repair_records WHERE asset_id=? ORDER BY send_date DESC", [$id]);

// --- Helper: Thai Date ---
function th_date($dateStr, $format = 'd M Y') {
    if (!$dateStr) return '-';
    $time = strtotime($dateStr);
    if (!$time) return $dateStr;
    $d = date('j', $time);
    $m = date('n', $time);
    $y = (int)date('Y', $time);
    if ($y < 2400) $y += 543; // Only add if it looks like A.D.
    $shortMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    if ($format === 'd M Y') {
        return "$d " . $shortMonths[$m] . " $y";
    }
    return date($format, $time);
}

// Generate base URL dynamically
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
if ($dir === '/') $dir = '';
$dynamic_base_url = "$protocol://$host$dir";

$scan_url  = $dynamic_base_url . '/scan_asset.php?id=' . $id;
$qr_img    = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=M&data=' . urlencode($scan_url);
$qr_img_lg = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&data=' . urlencode($scan_url);

$repStatusLabel = [
    'repairing'  => ['text'=>'อยู่ระหว่างซ่อม', 'class'=>'badge-repair'],
    'done'       => ['text'=>'ซ่อมเสร็จ',       'class'=>'badge-available'],
    'cannot_fix' => ['text'=>'ซ่อมไม่ได้',       'class'=>'badge-writeoff'],
];
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
@media print {
    body * { visibility: hidden !important; }
    .print-target, .print-target * { visibility: visible !important; }
    .print-target { position: fixed; left: 0; top: 0; width: 100%; padding: 0; margin: 0; }
    .no-print { display: none !important; }
    .doc-table, .doc-table th, .doc-table td {
        border: 1px solid #333 !important; border-collapse: collapse !important;
        -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
    }
    .doc-table th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
.sticker-box { display:inline-block; border:2px dashed #bbb; border-radius:12px; padding:16px 20px; text-align:center; font-family:'Kanit',sans-serif; background:#fff; min-width:220px; }
.sticker-box img { display:block; margin:0 auto 8px; }
.sticker-box .sticker-school { font-size:11px; color:#666; margin-bottom:2px; }
.sticker-box .sticker-name   { font-size:14px; font-weight:700; color:#222; line-height:1.3; margin-bottom:4px; }
.sticker-box .sticker-code   { font-size:12px; color:#4e54c8; font-weight:600; }
.sticker-box .sticker-hint   { font-size:10px; color:#999; margin-top:4px; }
</style>

<section class="home-section">
    <nav class="top-navbar no-print">
        <div class="nav-title"><h4 class="mb-0 fw-bold text-dark">ทะเบียนคุมทรัพย์สิน</h4><small class="text-muted">Asset Detail & Register</small></div>
        <div class="user-profile d-flex align-items-center">
            <i class='bx bx-user-circle' style='font-size:32px;color:#4e54c8;margin-right:10px;'></i>
            <div>
                <?php if ($is_logged_in): ?>
                    <span class="d-block fw-semibold" style="line-height:1;"><?= htmlspecialchars($_SESSION['inv_fullname']) ?></span>
                    <small class="text-muted"><?= htmlspecialchars($_SESSION['inv_role']) ?></small>
                <?php else: ?>
                    <span class="d-block fw-semibold" style="line-height:1;">คนทั่วไป</span>
                    <small class="text-muted">User</small>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="card border-0 shadow-sm" style="border-radius:16px;">
            <div class="card-header bg-white no-print" style="border-radius:16px 16px 0 0;border-bottom:1px solid #eee;padding:12px 20px;">
                <div class="d-flex align-items-center gap-3">
                    <ul class="nav detail-tabs mb-0" id="docTab">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-print"><i class='bx bx-printer me-1'></i>ทะเบียนคุม</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-history"><i class='bx bx-history me-1'></i>ประวัติซ่อม (<?= count($repairs) ?>)</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-sticker"><i class='bx bx-qr me-1'></i>QR Sticker</button></li>
                    </ul>
                    <div class="ms-auto d-flex gap-2">
                        <a href="scan_asset.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-primary rounded-pill px-3 no-print" style="font-size:14px;"><i class='bx bx-qr-scan me-1'></i>ทดสอบ QR</a>
                        <?php if ($is_logged_in): ?>
                        <a href="add_asset.php?id=<?= $id ?>" class="btn btn-warning text-white rounded-pill px-3 no-print" style="font-size:14px;"><i class='bx bx-edit me-1'></i>แก้ไข</a>
                        <?php endif; ?>
                        <a href="asset.php" class="btn btn-light rounded-pill px-3 no-print" style="font-size:14px;"><i class='bx bx-arrow-back me-1'></i>กลับ</a>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="tab-content">

                    <div class="tab-pane fade show active" id="pane-print">
                        <div class="text-center py-3 no-print" style="background:#f8f9fa;border-bottom:1px solid #eee;">
                            <button class="btn btn-success px-5 fw-medium" onclick="window.print()"><i class='bx bx-printer me-2'></i>พิมพ์หน้านี้</button>
                        </div>
                        <div id="printableDoc" class="print-target" style="padding:30px 40px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                                <div style="width:100px;height:100px;border:2px solid #333;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                                    <img src="<?= $qr_img ?>" alt="QR" width="96" height="96" style="display:block;">
                                </div>
                                <div style="text-align:center;flex:1;padding:0 20px;"><div style="font-size:20px;font-weight:700;">ทะเบียนคุมทรัพย์สิน</div></div>
                                <div style="font-size:13px;min-width:240px;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <tr><td style="padding:2px 6px;">ส่วนราชการ</td><td style="border-bottom:1px solid #555;padding:2px 6px;font-weight:600;">โรงเรียนบ้านโคกวิทยา</td></tr>
                                        <tr><td style="padding:2px 6px;">หน่วยงาน</td><td style="border-bottom:1px solid #555;padding:2px 6px;">สังกัด สพป.ศรีสะเกษ เขต4</td></tr>
                                    </table>
                                </div>
                            </div>
                            <hr style="border-top:1px solid #aaa;margin:8px 0;">
                            <div style="display:flex;flex-wrap:wrap;gap:4px 16px;margin-bottom:6px;font-size:13px;">
                                <span>ประเภท <strong><?= htmlspecialchars($asset['category_code'].' '.($asset['cat_name']??'')) ?></strong></span>
                                <span>รหัส <strong><?= htmlspecialchars($asset['code']) ?></strong></span>
                                <span>รุ่น/แบบ <strong><?= htmlspecialchars($asset['model']??'-') ?></strong></span>
                            </div>
                            <div style="font-size:13px;margin-bottom:6px;">ชื่อรายการ <strong><?= htmlspecialchars($asset['name']) ?></strong></div>
                            <div style="font-size:13px;margin-bottom:6px;">สถานที่ตั้ง <strong><?= htmlspecialchars($asset['location']??'-') ?></strong> &nbsp; ผู้รับผิดชอบ <strong><?= htmlspecialchars($asset['custodian']??'-') ?></strong></div>
                            <div style="font-size:13px;margin-bottom:6px;">ที่อยู่/โทร <strong><?= htmlspecialchars(($asset['vendor_address']??'-').' โทร '.($asset['vendor_tel']??'-')) ?></strong></div>
                            <div style="font-size:13px;margin-bottom:4px;">ประเภทเงิน
                                <?php foreach (['budget'=>'เงินงบประมาณ','off_budget'=>'เงินนอกงบประมาณ','donation'=>'เงินบริจาค','other'=>'อื่นๆ'] as $v=>$t): ?>
                                <label style="margin:0 10px 0 6px;"><input type="checkbox" <?= ($asset['budget_type']===$v)?'checked':'' ?> disabled> <?= $t ?></label>
                                <?php endforeach; ?>
                            </div>
                            <div style="font-size:13px;margin-bottom:12px;">วิธีได้มา
                                <?php foreach (['specific'=>'เฉพาะเจาะจง','select'=>'คัดเลือก','ebidding'=>'e-bidding','price_check'=>'สอบราคา','donation'=>'รับบริจาค'] as $v=>$t): ?>
                                <label style="margin:0 10px 0 6px;"><input type="checkbox" <?= ($asset['proc_method']===$v)?'checked':'' ?> disabled> <?= $t ?></label>
                                <?php endforeach; ?>
                            </div>
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2">วัน เดือน ปี</th><th rowspan="2">ที่เอกสาร</th><th rowspan="2">รายการ</th>
                                        <th rowspan="2">จำนวน</th><th colspan="2">ราคาต่อหน่วย</th>
                                        <th rowspan="2">อายุ</th><th rowspan="2">อัตรา</th>
                                        <th rowspan="2">ค่าเสื่อมปี</th><th rowspan="2">ค่าเสื่อมสะสม</th>
                                        <th rowspan="2">มูลค่าสุทธิ</th><th rowspan="2">หมายเหตุ</th>
                                    </tr>
                                    <tr><th>ราคา</th><th>รวม</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?= th_date($asset['receive_date']) ?></td>
                                        <td><?= htmlspecialchars($asset['doc_ref']??'-') ?></td>
                                        <td><?= htmlspecialchars($asset['name']) ?></td>
                                        <td>1</td>
                                        <td><?= number_format($asset['cost'],2) ?></td>
                                        <td><?= number_format($asset['cost'],2) ?></td>
                                        <td>-</td><td>-</td><td>-</td><td>-</td>
                                        <td><?= number_format($asset['cost'],2) ?></td>
                                        <td>
                                            <?php if ($asset['category_code'] === '17'): ?>
                                                <span style="color:red; font-weight:bold;">ต่ำกว่าเกณฑ์</span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                    $displayRows = $depRows;
                                    $showSaveBtn = false;
                                    
                                    // If no log yet and not category 17, calculate preview
                                    if (empty($displayRows) && $asset['category_code'] !== '17' && $asset['receive_date'] && $asset['cost'] > 0 && $asset['useful_life'] > 0) {
                                        $receiveDateStr = $asset['receive_date'];
                                        $startDate = new DateTime($receiveDateStr);
                                        
                                        // If stored year is B.E. (>2400), convert to A.D. for PHP calculation
                                        $year = intval($startDate->format('Y'));
                                        if ($year > 2400) {
                                            $startDate = new DateTime(($year - 543) . $startDate->format('-m-d'));
                                            $year = intval($startDate->format('Y'));
                                        }

                                        $cost = floatval($asset['cost']);
                                        $usefulLife = intval($asset['useful_life']);
                                        
                                        $accDep = 0;
                                        $currentNet = $cost;
                                        
                                        // Annual base matching the example: Round(Cost / Life, 2)
                                        $annualBase = round($cost / $usefulLife, 2);
                                        
                                        $month = intval($startDate->format('m'));
                                        // End of first fiscal year
                                        $fiscalYearEnd = new DateTime(($month >= 10 ? $year + 1 : $year) . '-09-30');
                                        
                                        // Calculate the exact end date of the asset's life (Start Date + Useful Life - 1 day)
                                        $endDate = clone $startDate;
                                        $endDate->modify("+{$usefulLife} years");
                                        $endDate->modify("-1 day");

                                        $currentPeriodStart = clone $startDate;
                                        $i = 1;

                                        // Loop until net value reaches 1
                                        while ($currentNet > 1) {
                                            $currentPeriodEnd = clone $fiscalYearEnd;
                                            if ($currentPeriodEnd > $endDate) {
                                                $currentPeriodEnd = clone $endDate;
                                            }

                                            $diff = $currentPeriodStart->diff($currentPeriodEnd);
                                            $days = $diff->days + 1; // inclusive count
                                            
                                            $months = round($days / 30.42); 
                                            if ($months > 12) $months = 12;

                                            // Calculate Depreciation Amount
                                            if ($currentPeriodEnd == $endDate) {
                                                // Last period: Must leave exactly 1.00 baht
                                                $depAmount = $currentNet - 1;
                                            } else {
                                                if ($days >= 365) {
                                                    $depAmount = $annualBase;
                                                } else {
                                                    $depAmount = round($annualBase * $days / 365, 2);
                                                }
                                            }
                                            
                                            // Safety check so it never drops below 1
                                            if ($currentNet - $depAmount < 1) {
                                                $depAmount = $currentNet - 1;
                                            }
                                            
                                            $accDep += $depAmount;
                                            $currentNet = $cost - $accDep;
                                            
                                            $displayRows[] = [
                                                'log_date' => $currentPeriodEnd->format('Y-m-d'),
                                                'year_no' => $i,
                                                'months' => $months,
                                                'dep_amount' => $depAmount,
                                                'acc_depreciation' => $accDep,
                                                'net_value' => $currentNet,
                                                'doc_ref' => '',
                                                'is_preview' => true
                                            ];
                                            
                                            $i++;
                                            // Move pointers to next year
                                            $currentPeriodStart = clone $currentPeriodEnd;
                                            $currentPeriodStart->modify('+1 day');
                                            $fiscalYearEnd->modify('+1 year');
                                            
                                            if ($currentPeriodStart > $endDate) break;
                                        }
                                        $showSaveBtn = !empty($displayRows);
                                    }

                                    foreach ($displayRows as $i => $row): 
                                    ?>
                                    <tr>
                                        <td><?= th_date($row['log_date']) ?></td>
                                        <td><?= htmlspecialchars($row['doc_ref']??'-') ?></td>
                                        <td>ปีที่ <?= $row['year_no'] ?> (<?= $row['months'] ?> เดือน)</td>
                                        <td>-</td><td>-</td><td>-</td>
                                        <td><?= $asset['useful_life'] ?></td>
                                        <td><?= number_format($asset['dep_rate'], 2) ?>%</td>
                                        <td><?= number_format($row['dep_amount'],2) ?></td>
                                        <td><?= number_format($row['acc_depreciation'],2) ?></td>
                                        <td style="font-weight:<?= ($i===count($displayRows)-1)?'700':'normal' ?>;"><?= number_format($row['net_value'],2) ?></td>
                                        <td>
                                            <?= htmlspecialchars($row['note']??'-') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($displayRows) && $asset['category_code'] !== '17'): ?>
                                    <tr><td colspan="12" style="text-align:center;color:#aaa;font-size:12px;">ยังไม่มีข้อมูลค่าเสื่อม และไม่สามารถคำนวณเบื้องต้นได้ (กรุณาเช็ควันรับและหมวดหมู่)</td></tr>
                                    <?php elseif ($asset['category_code'] === '17'): ?>
                                    <tr><td colspan="12" style="text-align:center;color:#e74c3c;font-size:12px;font-weight:bold;">*** ครุภัณฑ์ต่ำกว่าเกณฑ์ ไม่มีการคิดค่าเสื่อมราคา ***</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
                            <?php if ($showSaveBtn && $is_logged_in): ?>
                            <div class="no-print mt-3 text-end">
                                <button class="btn btn-outline-primary btn-sm rounded-pill" onclick="saveDepreciation()">
                                    <i class='bx bx-save me-1'></i>บันทึกตารางค่าเสื่อมลงฐานข้อมูล
                                </button>
                            </div>
                            <?php endif; ?>

                            <div style="display:flex;justify-content:space-around;margin-top:40px;font-size:13px;text-align:center;">
                                <div><div style="margin-bottom:36px;">ลงชื่อ............................................</div><div>( <?= htmlspecialchars($asset['custodian']??'...............') ?> )</div><div>ผู้รับผิดชอบ/ผู้รักษา</div></div>
                                <div><div style="margin-bottom:36px;">ลงชื่อ............................................</div><div>( นายตฤณ ทีงาม )</div><div>ผู้อนุมัติ</div></div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pane-history">
                        <div style="padding:24px;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">ประวัติการซ่อมบำรุง</h6>
                                <?php if ($is_logged_in): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" data-bs-toggle="modal" data-bs-target="#addRepairModal" data-toggle="modal" data-target="#addRepairModal"><i class='bx bx-plus me-1'></i>เพิ่มประวัติซ่อม</button>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($repairs)): ?>
                            <div class="text-center text-muted py-4"><i class='bx bx-check-circle' style="font-size:40px;color:#aaa;display:block;"></i>ไม่มีประวัติการซ่อม</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle" style="font-size:13px;">
                                    <thead class="table-light">
                                        <tr><th>#</th><th>วันที่ส่งซ่อม</th><th>อาการ/ปัญหา</th><th>ผู้รับซ่อม</th><th>ค่าซ่อม</th><th>วันที่รับคืน</th><th>สถานะ</th></tr>
                                    </thead>
<tbody>
<?php $total=0; foreach ($repairs as $i=>$r): 
    $sl=$repStatusLabel[$r['status']]??['text'=>$r['status'],'class'=>'']; 
    $total+=$r['repair_cost']; 
?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= date('d/m/Y', strtotime($r['send_date'])) ?></td>
        <td><?= htmlspecialchars($r['problem_desc']) ?></td>
        <td><?= htmlspecialchars($r['repair_shop']??'-') ?></td>
        <td><?= $r['repair_cost']>0 ? number_format($r['repair_cost'],2) : '-' ?></td>
        <td><?= $r['return_date'] ? date('d/m/Y',strtotime($r['return_date'])) : '-' ?></td>
        <td><span class="<?= $sl['class'] ?>"><?= $sl['text'] ?></span></td>
        <td class="text-center">
            <?php if ($is_logged_in): ?>
                <div class="d-flex gap-1 justify-content-center">
                    <?php if ($r['status'] === 'repairing'): ?>
                        <button class="btn btn-sm btn-primary rounded-pill" onclick="openUpdateRepair(<?= $r['id'] ?>, <?= $r['repair_cost'] ?>)"><i class='bx bx-edit'></i> อัปเดต</button>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:12px;">ปิดงานแล้ว</span>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteRepair(<?= $r['id'] ?>)"><i class='bx bx-trash'></i></button>
                </div>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
                                </table>
                            </div>
                            <small class="text-muted">รวมค่าซ่อม: <strong><?= number_format($total,2) ?> บาท</strong></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pane-sticker">
                        <div style="padding:28px;">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0"><i class='bx bx-qr me-2'></i>สติกเกอร์ QR Code</h6>
                                <button class="btn btn-success rounded-pill px-4" onclick="printSticker()"><i class='bx bx-printer me-1'></i>พิมพ์สติกเกอร์ QR</button>
                            </div>
                            <div class="row g-4 align-items-start">
                                <div class="col-md-4 text-center">
                                    <div class="sticker-box" id="printableSticker">
                                        <div class="sticker-school">🏫 โรงเรียนบ้านโคกวิทยา</div>
                                        <img src="<?= $qr_img_lg ?>" alt="QR" width="160" height="160">
                                        <div class="sticker-name"><?= htmlspecialchars($asset['name']) ?></div>
                                        <div class="sticker-code"><?= htmlspecialchars($asset['code']) ?></div>
                                        <div class="sticker-hint">สแกน QR เพื่อดูข้อมูล</div>
                                    </div>
                                    <div class="text-muted mt-2" style="font-size:12px;">ตัวอย่างสติกเกอร์</div>
                                </div>
                                <div class="col-md-8">
                                    <div class="alert alert-info" style="border-radius:12px;font-size:14px;">
                                        <div class="fw-bold mb-1"><i class='bx bx-link me-1'></i>URL ที่ QR ชี้ไป</div>
                                        <code style="word-break:break-all;font-size:13px;"><?= htmlspecialchars($scan_url) ?></code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <script src="frontend/assets/js/asset.js"></script>
<div class="modal fade" id="addRepairModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold"><i class='bx bx-wrench me-2'></i>เพิ่มประวัติการซ่อมบำรุง</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="repairForm">
                    <input type="hidden" name="asset_id" value="<?= $id ?>">
                    <div class="mb-3">
                        <label class="form-label">วันที่ส่งซ่อม <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="send_date" required value="<?= date('Y-m-d') ?>" placeholder="เลือกวันที่">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">อาการ/ปัญหาที่พบ <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="problem_desc" rows="2" required placeholder="เช่น เปิดไม่ติด, จอฟ้า"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ร้าน/ผู้รับซ่อม</label>
                        <input type="text" class="form-control" name="repair_shop" placeholder="เช่น ร้านซ่อมคอมพิวเตอร์หน้าโรงเรียน">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">ค่าซ่อมประเมิน (บาท)</label>
                            <input type="number" step="0.01" class="form-control" name="repair_cost" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">สถานะ</label>
                            <select class="form-select" name="status">
                                <option value="repairing">อยู่ระหว่างซ่อม</option>
                                <option value="done">ซ่อมเสร็จ</option>
                                <option value="cannot_fix">ซ่อมไม่ได้/แทงจำหน่าย</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="saveRepair()">บันทึกข้อมูล</button>
            </div>
        </div>
    </div>
</div>
<script>
function printSticker() {
    const sticker = document.getElementById('printableSticker');
    const doc = document.getElementById('printableDoc');
    if (doc)     doc.classList.remove('print-target');
    if (sticker) sticker.classList.add('print-target');
    window.print();
    if (sticker) sticker.classList.remove('print-target');
    if (doc)     doc.classList.add('print-target');
}
function saveDepreciation() {
    Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: 'ระบบจะบันทึกตารางค่าเสื่อมที่คำนวณได้ลงในฐานข้อมูล เพื่อใช้ในการออกรายงาน',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4e54c8',
        confirmButtonText: 'ตกลง, บันทึกเลย',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('backend/save_depreciation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ asset_id: <?= $id ?> })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('สำเร็จ!', 'บันทึกข้อมูลค่าเสื่อมเรียบร้อยแล้ว', 'success').then(() => location.reload());
                } else {
                    Swal.fire('ผิดพลาด', data.error || 'เกิดข้อผิดพลาดในการบันทึก', 'error');
                }
            })
            .catch(e => Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error'));
        }
    });
}
function saveRepair() {
    const form = document.getElementById('repairForm');
    if (!form.checkValidity()) {
        form.reportValidity(); // แจ้งเตือนถ้ายังกรอกช่องบังคับไม่ครบ
        return;
    }
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    Swal.showLoading();
    fetch('backend/save_repair.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('สำเร็จ!', 'เพิ่มประวัติการซ่อมเรียบร้อยแล้ว', 'success').then(() => location.reload());
        } else {
            Swal.fire('ผิดพลาด', res.error || 'ไม่สามารถบันทึกได้', 'error');
        }
    })
 .catch(e => Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error'));
}
function openUpdateRepair(repairId, currentCost) {
    document.getElementById('upd_repair_id').value = repairId;
    document.getElementById('upd_repair_cost').value = currentCost > 0 ? currentCost : '';
    new bootstrap.Modal(document.getElementById('updateRepairModal')).show();
    
    // ตั้งค่า Flatpickr สำหรับวันที่รับคืนด้วย
    flatpickr("#upd_return_date", {
        locale: 'th',
        altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d',
        defaultDate: 'today',
        onReady: function(d, s, i) { if(i.currentYearElement) i.currentYearElement.value = i.currentYear + 543; },
        onYearChange: function(d, s, i) { if(i.currentYearElement) i.currentYearElement.value = i.currentYear + 543; },
        onMonthChange: function(d, s, i) { if(i.currentYearElement) i.currentYearElement.value = i.currentYear + 543; }
    });
}

function submitUpdateRepair() {
    const form = document.getElementById('updateRepairForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    
    const data = Object.fromEntries(new FormData(form).entries());

    Swal.showLoading();
    fetch('backend/update_repair.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('สำเร็จ!', 'อัปเดตสถานะเรียบร้อยแล้ว', 'success').then(() => location.reload());
        } else {
            Swal.fire('ผิดพลาด', res.error || 'ไม่สามารถบันทึกได้', 'error');
        }
    }).catch(e => Swal.fire('ผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error'));
}

function deleteRepair(repairId) {
    Swal.fire({
        title: 'ลบประวัติซ่อมนี้?',
        text: 'ยืนยันการลบรายการซ่อมนี้ออกจากระบบ ไม่สามารถยกเลิกได้',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'ลบเลย',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('backend/delete_repair.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ repair_id: repairId })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('ลบแล้ว!', 'ลบรายการซ่อมเรียบร้อยแล้ว', 'success').then(() => location.reload());
                } else {
                    Swal.fire('ผิดพลาด', res.error || 'ไม่สามารถลบได้', 'error');
                }
            }).catch(e => Swal.fire('ผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error'));
        }
    });
}
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<div class="modal fade" id="updateRepairModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold"><i class='bx bx-check-shield me-2'></i>อัปเดตสถานะการซ่อม (รับของคืน)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateRepairForm">
                    <input type="hidden" name="repair_id" id="upd_repair_id">
                    <input type="hidden" name="asset_id" value="<?= $id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">วันที่รับคืน <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="return_date" required value="<?= date('Y-m-d') ?>" id="upd_return_date">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">ค่าซ่อมจริง (บาท)</label>
                            <input type="number" step="0.01" class="form-control" name="repair_cost" id="upd_repair_cost" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">สถานะหลังซ่อม</label>
                            <select class="form-select" name="status">
                                <option value="done" selected>ซ่อมเสร็จ (พร้อมใช้งาน)</option>
                                <option value="cannot_fix">ซ่อมไม่ได้ (รอจำหน่าย)</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="submitUpdateRepair()">บันทึกอัปเดต</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    flatpickr("input[name=send_date]", {
        locale: 'th',
        altInput: true,
        altFormat: 'd/m/Y', // กำหนดรูปแบบแสดงผล
        dateFormat: 'Y-m-d', // รูปแบบส่งไปหลังบ้าน
        allowInput: true,
        defaultDate: 'today',
        
        // --- 1. แปลงปีในส่วนหัวของปฏิทินให้เป็น พ.ศ. ---
        onReady: function(selectedDates, dateStr, instance) {
            if (instance.currentYearElement) {
                instance.currentYearElement.value = instance.currentYear + 543;
            }
        },
        onYearChange: function(selectedDates, dateStr, instance) {
            if (instance.currentYearElement) {
                instance.currentYearElement.value = instance.currentYear + 543;
            }
        },
        onMonthChange: function(selectedDates, dateStr, instance) {
            if (instance.currentYearElement) {
                instance.currentYearElement.value = instance.currentYear + 543;
            }
        },
        
        // --- 2. แปลงปีในช่อง Text Input ให้เป็น พ.ศ. ---
        formatDate: function(date, format, locale) {
            if (format === 'd/m/Y') {
                const d = String(date.getDate()).padStart(2, '0');
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const y = date.getFullYear() + 543;
                return `${d}/${m}/${y}`;
            }
            return flatpickr.formatDate(date, format);
        },
        
        // --- 3. แปลงปี พ.ศ. กลับเป็น ค.ศ. กรณีพิมพ์ตัวเลขเอง ---
        parseDate: function(datestr, format) {
            if (format === 'd/m/Y') {
                const parts = datestr.split('/');
                if (parts.length === 3) {
                    const d = parseInt(parts[0]);
                    const m = parseInt(parts[1]) - 1;
                    let y = parseInt(parts[2]);
                    if (y > 2400) y -= 543; // ถ้ารับมาเป็น พ.ศ. ให้ลบ 543 กลับเป็น ค.ศ.
                    return new Date(y, m, d);
                }
            }
            return flatpickr.parseDate(datestr, format);
        }
    });
});
</script>

<?php include 'frontend/includes/footer.php'; ?>