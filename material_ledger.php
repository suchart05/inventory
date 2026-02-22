<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/backend/config/db_inventory.php';

$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: material.php');
    exit;
}

$is_logged_in = isset($_SESSION['inv_user_id']);

// Find material
$material = inv_row("SELECT * FROM materials WHERE code = ?", [$code]);
$is_new = !$material;

if (!$is_new) {
    // Get transactions
    $transactions = inv_query("SELECT * FROM material_transactions WHERE material_id = ? ORDER BY id ASC", [$material['id']]);
}

include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';
?>
<style>
.ledger-header-panel {
    background: #fff; border-radius: 16px; padding: 25px 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px;
    border-top: 5px solid #4e54c8;
}
.ledger-title { text-align: center; font-weight: 700; font-size: 22px; margin-bottom: 20px; color: #333; }
.ledger-info-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px 30px;
    font-size: 15px;
}
.info-row { display: flex; align-items: baseline; border-bottom: 1px dotted #ccc; padding-bottom: 4px; }
.info-label { font-weight: 600; color: #555; margin-right: 10px; white-space: nowrap; }
.info-value { color: #000; flex: 1; font-weight: 500; }

.table-ledger { width: 100%; border-collapse: collapse; background: #fff; font-size: 14px; }
.table-ledger th, .table-ledger td { border: 1px solid #ddd; padding: 8px 12px; text-align: center; vertical-align: middle; }
.table-ledger th { background: #f8f9fa; font-weight: 600; color: #444; }
.table-ledger thead tr:first-child th { border-top: 2px solid #aaa; border-bottom: 2px solid #aaa; }
.col-group-rcv { background: rgba(39, 174, 96, 0.05); }
.col-group-iss { background: rgba(231, 76, 60, 0.05); }
.col-group-bal { background: rgba(41, 128, 185, 0.05); font-weight: 600; }

/* Form Card */
/* Form Card */
.form-card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 8px 30px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto; }

/* --- Print Styles --- */
@media print {
    @page { size: A4 landscape; margin: 10mm; }
    body { background: #fff; font-size: 9pt; }
    .sidebar, .top-navbar, .mb-3, .mt-4.d-flex, .btn-print-ledger { display: none !important; }
    .home-section { left: 0 !important; width: 100% !important; margin: 0 !important; }
    .main-content { padding: 0 !important; }
    .ledger-header-panel { box-shadow: none !important; border: none !important; border-top: 3px solid #333 !important; margin: 0 0 8px 0 !important; padding: 10px !important; }
    .table-container-custom { box-shadow: none !important; border: none !important; padding: 0 !important; }
    .table-ledger th, .table-ledger td { border: 1px solid #000 !important; color: #000 !important; padding: 4px 6px !important; font-size: 8pt !important; }
    .table-ledger th { background: #f0f0f0 !important; font-size: 8pt !important; }
    .ledger-title { font-size: 14pt !important; margin-bottom: 8px !important; }
    .info-label { font-size: 8pt !important; color: #000 !important; }
    .info-value { font-size: 9pt !important; color: #000 !important; }
    .ledger-info-grid { gap: 6px 15px !important; font-size: 8pt !important; }
    /* Force background colors for print */
    .col-group-rcv, .col-group-iss, .col-group-bal { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<section class="home-section">
    <div class="main-content">
        <nav class="top-navbar mb-4" style="border-radius: 20px;">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-md-none" id="sidebarToggle" aria-label="Toggle Navigation">
                    <i class='bx bx-menu' style="font-size: 24px;"></i>
                </button>
                <div class="nav-title">
                    <h4 class="mb-0 fw-bold text-dark">บัญชีวัสดุ</h4>
                    <small class="text-muted">Material Ledger</small>
                </div>
            </div>
            <div class="user-profile d-flex align-items-center gap-2">
                <div style="background: rgba(17,153,142,0.1); width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #11998e;">
                    <i class='bx bx-user' style="font-size: 22px;"></i>
                </div>
                <div>
                    <?php if ($is_logged_in): ?>
                        <span class="d-block fw-bold text-dark" style="line-height:1.2; font-size:14px;"><?= htmlspecialchars($_SESSION['inv_fullname']) ?></span>
                    <?php else: ?>
                        <span class="d-block fw-bold text-dark" style="line-height:1.2; font-size:14px;">คนทั่วไป</span>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        <div class="mb-3 d-flex gap-2">
            <a href="material.php" class="btn btn-outline-secondary rounded-pill px-3"><i class='bx bx-arrow-back me-1'></i> กลับหน้าหลัก</a>
            <?php if (!$is_new): ?>
            <button class="btn btn-print-ledger rounded-pill px-3 fw-medium" onclick="window.print()" style="background: linear-gradient(135deg, #4e54c8, #8f94fb); color: #fff; border: none; box-shadow: 0 4px 10px rgba(78,84,200,0.2);">
                <i class='bx bx-printer me-1'></i> พิมพ์บัญชีวัสดุ
            </button>
            <?php endif; ?>
        </div>

        <?php if ($is_new): ?>
        <!-- =============== NEW MATERIAL FORM =============== -->
        <div class="alert alert-info" style="max-width: 800px; margin: 0 auto 20px; border-radius: 12px;">
            <i class='bx bx-info-circle me-2'></i> <strong>ไม่พบรหัสวัสดุ "<?= htmlspecialchars($code) ?>" ในระบบ</strong><br>
            กรุณากรอกข้อมูลด้านล่างเพื่อเพิ่มวัสดุใหม่เข้าสู่ระบบ
        </div>

        <div class="form-card">
            <h5 class="fw-bold mb-4" style="color: #4e54c8;"><i class='bx bx-plus-circle me-2'></i> เพิ่มรายการวัสดุใหม่</h5>
            <form id="newMaterialForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">รหัสวัสดุ (บาร์โค้ด)</label>
                        <input type="text" class="form-control bg-light" name="code" value="<?= htmlspecialchars($code) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">ชื่อหรือชนิดวัสดุ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small">ประเภท / หมวดหมู่</label>
                        <input type="text" class="form-control" name="category" placeholder="เช่น วัสดุสำนักงาน">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small">ขนาดหรือลักษณะ</label>
                        <input type="text" class="form-control" name="note" placeholder="เช่น A4 80 แกรม">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small">หน่วยนับ</label>
                        <input type="text" class="form-control" name="unit" placeholder="เช่น รีม, กล่อง, ด้าม">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small">ที่เก็บ</label>
                        <input type="text" class="form-control" name="location">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small">จำนวนอย่างสูง (Max Stock)</label>
                        <input type="number" class="form-control" name="max_stock">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small">จำนวนอย่างต่ำ (Min Stock)</label>
                        <input type="number" class="form-control" name="min_stock" value="5">
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary w-100 fw-bold" style="height: 48px; border-radius: 10px; background: linear-gradient(135deg, #4e54c8, #8f94fb); border: none;">บันทึกข้อมูลและเปิดบัญชีวัสดุ</button>
                    </div>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- =============== MATERIAL LEDGER VIEW =============== -->
        <div class="ledger-header-panel">
            <div class="ledger-title">บัญชีวัสดุ</div>
            <div class="ledger-info-grid">
                <div class="info-row"><span class="info-label">ประเภท:</span><span class="info-value"><?= htmlspecialchars($material['category'] ?: '-') ?></span></div>
                <div class="info-row"><span class="info-label">ชื่อหรือชนิดวัสดุ:</span><span class="info-value text-primary fs-5"><?= htmlspecialchars($material['name']) ?></span></div>
                <div class="info-row"><span class="info-label">รหัส (บาร์โค้ด):</span><span class="info-value"><?= htmlspecialchars($material['code']) ?></span></div>
                
                <div class="info-row"><span class="info-label">ขนาด/ลักษณะ:</span><span class="info-value"><?= htmlspecialchars($material['note'] ?: '-') ?></span></div>
                <div class="info-row"><span class="info-label">หน่วยนับ:</span><span class="info-value"><?= htmlspecialchars($material['unit'] ?: '-') ?></span></div>
                <div class="info-row"><span class="info-label">ที่เก็บ:</span><span class="info-value"><?= htmlspecialchars($material['location'] ?: '-') ?></span></div>
                
                <div class="info-row"><span class="info-label">จำนวนอย่างสูง:</span><span class="info-value"><?= htmlspecialchars($material['max_stock'] ?: '-') ?></span></div>
                <div class="info-row"><span class="info-label">จำนวนอย่างต่ำ:</span><span class="info-value"><?= htmlspecialchars($material['min_stock'] ?: '-') ?></span></div>
                <div class="info-row"><span class="info-label">คงเหลือปัจจุบัน:</span><span class="info-value text-success fs-5 fw-bold"><?= $material['qty_in_stock'] ?></span></div>
            </div>
            
            <?php if ($is_logged_in): ?>
            <div class="mt-4 d-flex justify-content-center gap-3 flex-wrap">
                <button class="btn btn-success fw-bold px-4 rounded-pill shadow-sm" style="height:44px;" onclick="openTxModal('in')"><i class='bx bx-plus me-1'></i> รับเข้าวัสดุ</button>
                <button class="btn btn-danger fw-bold px-4 rounded-pill shadow-sm" style="height:44px;" onclick="openTxModal('out')"><i class='bx bx-minus me-1'></i> เบิกจ่ายวัสดุ</button>
                <button class="btn fw-bold px-4 rounded-pill shadow-sm" style="height:44px; background:linear-gradient(135deg,#f7971e,#ffd200); border:none; color:#fff;" data-bs-toggle="modal" data-bs-target="#editMaterialModal"><i class='bx bx-edit-alt me-1'></i> แก้ไขข้อมูลวัสดุ</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-container-custom shadow-sm" style="overflow-x: auto;">
            <table class="table-ledger">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:100px;">วัน เดือน ปี</th>
                        <th rowspan="2" style="width:180px;">รับจาก / จ่ายให้</th>
                        <th colspan="2">เลขที่เอกสาร</th>
                        <th rowspan="2" style="width:80px;">ราคาต่อหน่วย<br>(บาท)</th>
                        <th colspan="2" class="col-group-rcv">รับ</th>
                        <th colspan="2" class="col-group-iss">จ่าย</th>
                        <th colspan="2" class="col-group-bal">คงเหลือ</th>
                        <th rowspan="2" style="width:120px;">หมายเหตุ</th>
                        <?php if ($is_logged_in): ?>
                        <th rowspan="2" style="width:70px;" class="text-center d-print-none"><i class='bx bx-cog'></i></th>
                        <?php endif; ?>
                    </tr>
                    <tr>
                        <th style="width:80px;">รับ</th>
                        <th style="width:80px;">จ่าย</th>
                        <th class="col-group-rcv" style="width:60px;">จำนวน</th>
                        <th class="col-group-rcv" style="width:80px;">ราคา</th>
                        <th class="col-group-iss" style="width:60px;">จำนวน</th>
                        <th class="col-group-iss" style="width:80px;">ราคา</th>
                        <th class="col-group-bal" style="width:60px;">จำนวน</th>
                        <th class="col-group-bal" style="width:80px;">ราคา</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="<?= $is_logged_in ? '13' : '12' ?>" class="text-center text-muted py-4">ยังไม่มีประวัติการรับ/จ่าย วัสดุนี้</td></tr>
                <?php else: ?>
                    <?php 
                        $th_months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                        foreach ($transactions as $tx): 
                        $isIn = $tx['type'] === 'in';
                        $isOut = $tx['type'] === 'out';
                        $docIn  = $isIn ? htmlspecialchars($tx['ref_doc']?:'-') : '';
                        $docOut = $isOut ? htmlspecialchars($tx['ref_doc']?:'-') : '';
                        $totalPrice = $tx['qty'] * $tx['unit_price'];
                        
                        $tx_ts = strtotime($tx['created_at']);
                        $th_date_str = date('j', $tx_ts) . ' ' . $th_months[(int)date('n', $tx_ts)] . ' ' . (date('Y', $tx_ts) + 543);
                    ?>
                    <tr>
                        <td class="text-muted"><?= $th_date_str ?></td>
                        <td class="text-start"><?= htmlspecialchars($tx['requester']) ?></td>
                        <td><?= $docIn ?></td>
                        <td><?= $docOut ?></td>
                        <td><?= number_format($tx['unit_price'], 2) ?></td>
                        
                        <!-- Receive -->
                        <td class="col-group-rcv text-success fw-bold"><?= $isIn ? $tx['qty'] : '' ?></td>
                        <td class="col-group-rcv"><?= $isIn ? number_format($totalPrice, 2) : '' ?></td>
                        
                        <!-- Issue -->
                        <td class="col-group-iss text-danger fw-bold"><?= $isOut ? $tx['qty'] : '' ?></td>
                        <td class="col-group-iss"><?= $isOut ? number_format($totalPrice, 2) : '' ?></td>
                        
                        <!-- Balance -->
                        <td class="col-group-bal text-primary fw-bold"><?= $tx['balance_qty'] ?></td>
                        <td class="col-group-bal"><?= number_format($tx['balance_price'], 2) ?></td>
                        
                        <td class="text-muted text-start" style="font-size:12px;"><?= htmlspecialchars($tx['note']?:'') ?></td>
                        
                        <?php if ($is_logged_in): ?>
                        <td class="text-center d-print-none">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary" style="border-radius: 6px; padding: 2px 8px;" 
                                        onclick="openEditTxModal(<?= $tx['id'] ?>, '<?= $tx['type'] ?>', '<?= htmlspecialchars($tx['requester'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tx['ref_doc'], ENT_QUOTES) ?>', <?= $tx['unit_price'] ?>, <?= $tx['qty'] ?>, '<?= date('Y-m-d', strtotime($tx['created_at'])) ?>', '<?= htmlspecialchars($tx['note'], ENT_QUOTES) ?>')" title="แก้ไขรายการ">
                                    <i class='bx bx-edit'></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" style="border-radius: 6px; padding: 2px 8px;" onclick="deleteTx(<?= $tx['id'] ?>, <?= $material['id'] ?>)" title="ลบรายการ">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Edit Material Modal -->
<?php if (!$is_new && $is_logged_in): ?>
<div class="modal fade" id="editMaterialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #f7971e, #ffd200); border-radius: 20px 20px 0 0; padding: 20px 25px;">
                <h5 class="modal-title fw-bold text-white"><i class='bx bx-edit-alt me-2'></i> แก้ไขข้อมูลวัสดุ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editMaterialForm">
                    <input type="hidden" name="id" value="<?= $material['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small">รหัส (บาร์โค้ด) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" value="<?= htmlspecialchars($material['code']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small">ชื่อหรือชนิดวัสดุ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($material['name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">ประเภท / หมวดหมู่</label>
                            <input type="text" class="form-control" name="category" value="<?= htmlspecialchars($material['category'] ?: '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">ขนาด/ลักษณะ</label>
                            <input type="text" class="form-control" name="note" value="<?= htmlspecialchars($material['note'] ?: '') ?>" placeholder="เช่น A4 80 แกรม">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">หน่วยนับ</label>
                            <input type="text" class="form-control" name="unit" value="<?= htmlspecialchars($material['unit'] ?: '') ?>" placeholder="เช่น รีม, กล่อง, ด้าม">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">ที่เก็บ</label>
                            <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($material['location'] ?: '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">จำนวนอย่างสูง (Max)</label>
                            <input type="number" class="form-control" name="max_stock" value="<?= $material['max_stock'] ?: '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">จำนวนอย่างต่ำ (Min)</label>
                            <input type="number" class="form-control" name="min_stock" value="<?= $material['min_stock'] ?: 5 ?>">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn fw-bold w-100 rounded-pill" style="height:46px; background:linear-gradient(135deg,#f7971e,#ffd200); border:none; color:#fff; font-size:15px;" id="editSubmitBtn">
                            <i class='bx bx-save me-1'></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Transaction Modal (Only if existing material) -->
<?php if (!$is_new): ?>
<div class="modal fade" id="txModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="txModalTitle">บันทึกรายการ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="txForm">
                    <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
                    <input type="hidden" name="type" id="txType">
                    
                    <!-- datalist ชื่อครู (ใช้ร่วมกันทั้งหน้า) -->
                    <datalist id="teacher-list">
                        <option value="น.ส.ณัฐนรี พื้นผา">
                        <option value="นางดอกจาน แก้วพิกุล">
                        <option value="น.ส.พิติยา ลาโสพันธ์">
                        <option value="น.ส.รัญญภัสร์ จิตธนาชัยพงศ์">
                        <option value="น.ส.รัตน์ดา เนียมมูล">
                        <option value="น.ส.ศิรินันท์ พันธ์ทอง">
                        <option value="นายสุชาติ คู่แก้ว">
                        <option value="นายสุริยะ พรมตวง">
                        <option value="นางอำนวย แก้วสง่า">
                        <option value="น.ส.เนตรศิริ ชินชัย">
                    </datalist>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" id="txRequesterLabel">รับจาก / จ่ายให้</label>
                        <input type="text" class="form-control" name="requester" list="teacher-list" autocomplete="off" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">เลขที่เอกสาร</label>
                            <div class="input-group" id="refDocGroup">
                                <input type="text" class="form-control" name="ref_doc" id="txRefDoc">
                                <button type="button" class="btn btn-outline-secondary" id="btnRefreshDocNo" title="สร้างรหัสใหม่" style="display:none;">
                                    <i class='bx bx-refresh'></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">ราคาต่อหน่วย (บาท)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="unit_price" value="<?= $material['unit_cost'] ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">จำนวน (<span class="text-primary fw-bold" id="txCurrentStock">คงเหลือ <?= $material['qty_in_stock'] ?></span>)</label>
                            <input type="number" min="1" class="form-control" name="qty" id="txQty" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">วันที่</label>
                            <input type="text" class="form-control fp-date" value="<?= date('Y-m-d') ?>" required autocomplete="off">
                            <input type="hidden" name="tx_date" id="tx_date_hidden" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">หมายเหตุ</label>
                        <input type="text" class="form-control" name="note">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold rounded-pill" style="height:44px;" id="txSubmitBtn">บันทึกรายการ</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Transaction Modal (Only if existing material) -->
<?php if (!$is_new): ?>
<div class="modal fade" id="editTxModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="editTxModalTitle"><i class='bx bx-edit text-primary me-2'></i>แก้ไขรายการเบิกจ่าย/รับเข้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="editTxForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tx_id" id="editTxId">
                    <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
                    <input type="hidden" name="type" id="editTxType">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold" id="editTxRequesterLabel">รับจาก / จ่ายให้</label>
                        <input type="text" class="form-control" name="requester" id="editTxRequester" list="teacher-list" autocomplete="off" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">เลขที่เอกสาร</label>
                            <input type="text" class="form-control" name="ref_doc" id="editTxRefDoc">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">ราคาต่อหน่วย (บาท)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="unit_price" id="editTxUnitPrice" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">จำนวน</label>
                            <input type="number" min="1" class="form-control" name="qty" id="editTxQty" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">วันที่</label>
                            <input type="text" class="form-control fp-date" id="editTxDateDisplay" required autocomplete="off">
                            <input type="hidden" name="tx_date" id="edit_tx_date_hidden">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">หมายเหตุ</label>
                        <input type="text" class="form-control" name="note" id="editTxNote">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold rounded-pill" style="height:44px;" id="editTxSubmitBtn">บันทึกการแก้ไข</button>
                    <div class="text-center mt-2">
                        <small class="text-muted"><i class='bx bx-info-circle'></i> ระบบจะคำนวณยอดคงเหลือใหม่ทั้งหมดอัตโนมัติ</small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="frontend/assets/js/asset.js"></script>

<!-- Flatpickr Thai Buddhist Year for Date Input -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function initFpThai(selector) {
        document.querySelectorAll(selector).forEach(function(el) {
            flatpickr(el, {
                locale: 'th',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: true,
                onChange: function(selectedDates, dateStr, fp) {
                    if (selectedDates.length) {
                        const d = selectedDates[0];
                        fp.altInput.value =
                            String(d.getDate()).padStart(2,'0') + '/' +
                            String(d.getMonth()+1).padStart(2,'0') + '/' +
                            (d.getFullYear() + 543);
                            
                        // Sync to hidden input for backend submission (Y-m-d)
                        const hiddenInput = document.getElementById('tx_date_hidden');
                        if (hiddenInput) {
                            hiddenInput.value = dateStr;
                        }
                    } else {
                        const hiddenInput = document.getElementById('tx_date_hidden');
                        if (hiddenInput) hiddenInput.value = '';
                    }
                }
            });
        });
    }
    
    // Init on page load
    initFpThai('.fp-date');
    
    // Re-init when modal opens (if needed)
    const modalEl = document.getElementById('txModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function () {
            initFpThai('.fp-date');
            
            // Re-apply current date inside fp-date if it's empty
            const dateInput = modalEl.querySelector('.fp-date');
            if(dateInput && dateInput._flatpickr && !dateInput.value) {
                 dateInput._flatpickr.setDate(new Date(), true);
            }
        });
    }
});

// ---- Mobile Sidebar Toggle ----
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(event.target) && event.target !== sidebarToggle) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
});

<?php if ($is_new): ?>
document.getElementById('newMaterialForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Disable button
    const btn = this.querySelector('button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';
    
    fetch('backend/save_material.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.ok) {
            Swal.fire({icon:'success', title:'บันทึกสำเร็จ', showConfirmButton:false, timer:1500})
            .then(() => location.reload());
        } else {
            Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', text:data.error});
            btn.disabled = false;
            btn.innerHTML = 'บันทึกข้อมูลและเปิดบัญชีวัสดุ';
        }
    }).catch(err => {
        Swal.fire({icon:'error', title:'Error', text:err.message});
        btn.disabled = false;
        btn.innerHTML = 'บันทึกข้อมูลและเปิดบัญชีวัสดุ';
    });
});
<?php else: ?>
const txModal = new bootstrap.Modal(document.getElementById('txModal'));
const maxQty = <?= $material['qty_in_stock'] ?>;

function loadNextOutDocNo() {
    const refDocInput = document.getElementById('txRefDoc');
    refDocInput.value = 'กำลังโหลด...';
    refDocInput.readOnly = true;
    fetch('backend/get_next_out_doc.php')
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                refDocInput.value = data.doc_no;
            } else {
                refDocInput.value = '';
                refDocInput.readOnly = false;
            }
        })
        .catch(() => {
            refDocInput.value = '';
            refDocInput.readOnly = false;
        });
}

function openTxModal(type) {
    document.getElementById('txForm').reset();
    document.getElementById('txType').value = type;
    const isOut = type === 'out';
    
    document.getElementById('txModalTitle').innerHTML = isOut ? "<i class='bx bx-minus text-danger me-2'></i>เบิกจ่ายวัสดุ" : "<i class='bx bx-plus text-success me-2'></i>รับเข้าวัสดุ";
    document.getElementById('txModalTitle').style.color = isOut ? '#e74c3c' : '#27ae60';
    document.getElementById('txSubmitBtn').className = isOut ? "btn btn-danger w-100 fw-bold rounded-pill" : "btn btn-success w-100 fw-bold rounded-pill";
    document.getElementById('txRequesterLabel').textContent = isOut ? "จ่ายให้ (ชื่อผู้เบิก/แผนก)" : "รับจาก (ชื่อร้านค้า/ผู้ส่งมอบ)";
    
    const refDocInput = document.getElementById('txRefDoc');
    const btnRefresh = document.getElementById('btnRefreshDocNo');

    if (isOut) {
        // Auto-generate OUT-YYMM-XXX
        btnRefresh.style.display = '';
        loadNextOutDocNo();
        document.getElementById('txQty').setAttribute('max', maxQty);
    } else {
        // Receiving: editable, no auto number
        refDocInput.readOnly = false;
        refDocInput.value = '';
        btnRefresh.style.display = 'none';
        document.getElementById('txQty').removeAttribute('max');
    }
    
    txModal.show();
}

document.getElementById('btnRefreshDocNo').addEventListener('click', function() {
    loadNextOutDocNo();
});

document.getElementById('txForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Validate stock for issue
    const type = document.getElementById('txType').value;
    const qty = parseInt(document.getElementById('txQty').value);
    if (type === 'out' && qty > maxQty) {
        Swal.fire({icon:'warning', title:'จำนวนคงเหลือไม่พอ', text:'ปัจจุบันมีแค่ '+maxQty+' หน่วย'});
        return;
    }

    const formData = new FormData(this);
    const btn = document.getElementById('txSubmitBtn');
    const oldText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>บันทึก...';
    
    fetch('backend/save_material_tx.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.ok) {
            Swal.fire({icon:'success', title:'บันทึกสำเร็จ', showConfirmButton:false, timer:1500})
            .then(() => location.reload());
        } else {
            Swal.fire({icon:'error', title:'Error', text:data.error});
            btn.disabled = false;
            btn.innerHTML = oldText;
        }
    }).catch(err => {
        Swal.fire({icon:'error', title:'Error', text:err.message});
        btn.disabled = false;
        btn.innerHTML = oldText;
    });
});
<?php endif; ?>

<?php if (!$is_new && $is_logged_in): ?>
// ---- Edit Material Form ----
document.getElementById('editMaterialForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = document.getElementById('editSubmitBtn');
    const oldText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';

    fetch('backend/update_material.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            Swal.fire({icon:'success', title:'บันทึกสำเร็จ!', showConfirmButton:false, timer:1500})
            .then(() => {
                // Redirect to new code if it changed
                window.location.href = 'material_ledger.php?code=' + encodeURIComponent(data.code);
            });
        } else {
            Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', text:data.error});
            btn.disabled = false;
            btn.innerHTML = oldText;
        }
    }).catch(err => {
        Swal.fire({icon:'error', title:'Error', text:err.message});
        btn.disabled = false;
        btn.innerHTML = oldText;
    });
});
<?php endif; ?>

<?php if ($is_logged_in): ?>
function deleteTx(txId, materialId) {
    Swal.fire({
        title: 'ยืนยันการลบรายการ?',
        text: "คุณต้องการลบประวัติการรับ/จ่ายนี้ใช่หรือไม่ ระบบจะคำนวณยอดคงเหลือใหม่ทั้งหมด",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'ลบรายการ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('tx_id', txId);
            formData.append('material_id', materialId);
            
            fetch('backend/delete_material_tx.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    Swal.fire({icon:'success', title:'ลบสำเร็จ!', showConfirmButton:false, timer:1500})
                    .then(() => location.reload());
                } else {
                    Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', text:data.error});
                }
            }).catch(err => Swal.fire({icon:'error', title:'Error', text:err.message}));
        }
    });
}

function openEditTxModal(id, type, requester, refDoc, unitPrice, qty, dateStr, note) {
    document.getElementById('editTxForm').reset();
    document.getElementById('editTxId').value = id;
    document.getElementById('editTxType').value = type;
    document.getElementById('editTxRequester').value = requester;
    document.getElementById('editTxRefDoc').value = refDoc;
    document.getElementById('editTxUnitPrice').value = unitPrice;
    document.getElementById('editTxQty').value = qty;
    document.getElementById('editTxNote').value = note;
    
    // Set Flatpickr date explicitly
    const dateInput = document.getElementById('editTxDateDisplay');
    if (dateInput && dateInput._flatpickr) {
        dateInput._flatpickr.setDate(dateStr, true);
    }
    
    const isOut = type === 'out';
    document.getElementById('editTxModalTitle').innerHTML = isOut ? "<i class='bx bx-edit text-danger me-2'></i>แก้ไขรายการเบิกจ่าย" : "<i class='bx bx-edit text-success me-2'></i>แก้ไขรายการรับเข้า";
    document.getElementById('editTxRequesterLabel').textContent = isOut ? "จ่ายให้ (ชื่อผู้เบิก/แผนก)" : "รับจาก (ชื่อร้านค้า/ผู้ส่งมอบ)";
    
    new bootstrap.Modal(document.getElementById('editTxModal')).show();
}

document.getElementById('editTxForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = document.getElementById('editTxSubmitBtn');
    const oldText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>บันทึก...';
    
    fetch('backend/edit_material_tx.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.ok) {
            Swal.fire({icon:'success', title:'แก้ไขสำเร็จ', showConfirmButton:false, timer:1500})
            .then(() => location.reload());
        } else {
            Swal.fire({icon:'error', title:'Error', text:data.error});
            btn.disabled = false;
            btn.innerHTML = oldText;
        }
    }).catch(err => {
        Swal.fire({icon:'error', title:'Error', text:err.message});
        btn.disabled = false;
        btn.innerHTML = oldText;
    });
});
<?php endif; ?>

</script>

<?php include 'frontend/includes/footer.php'; ?>
