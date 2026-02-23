<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['inv_user_id']);
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $id > 0;
$asset  = [];
$categories = inv_query("SELECT code, name, useful_life, dep_rate FROM asset_categories ORDER BY code");

$procurements = inv_query("SELECT id, order_no, title, vendor_name, note, total_amount, attachment_path FROM procurement_orders WHERE is_asset_related = 1 ORDER BY id DESC LIMIT 100");

// ---- Load existing asset if editing ----
if ($isEdit) {
    $asset = inv_row("SELECT * FROM assets WHERE id=?", [$id]);
    if (!$asset) { header('Location: asset.php'); exit; }
}

// ---- Handle form POST ----
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imagePath = $asset['image_path'] ?? null;
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $f        = $_FILES['image_file'];
        $allowed  = ['image/jpeg','image/png','image/webp'];
        $ext      = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$f['type']] ?? 'jpg';
        $filename = 'asset_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $upDir    = __DIR__ . '/uploads/assets/';
        $dest     = $upDir . $filename;

        if (!is_writable($upDir)) {
            $error = 'โฟลเดอร์เก็บรูปภาพ (uploads/assets) ไม่มีสิทธิ์เขียนไฟล์ กรุณาตรวจสอบสิทธิ์โฟลเดอร์';
        } elseif (!in_array($f['type'], $allowed)) {
            $error = 'รูปภาพต้องเป็นไฟล์ JPG, PNG หรือ WEBP เท่านั้น';
        } elseif ($f['size'] > 2*1024*1024) {
            $error = 'ขนาดไฟล์ใหญ่เกินไป (จำกัดไม่เกิน 2MB เพื่อตามค่าเซิร์ฟเวอร์)';
        } else {
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $imagePath = 'uploads/assets/' . $filename;
            } else {
                $error = 'เกิดข้อผิดพลาดในการย้ายไฟล์รูปภาพ';
            }
        }
    }

    $data = [
        'code'           => trim($_POST['asset_code'] ?? ''),
        'name'           => trim($_POST['asset_name'] ?? ''),
        'category_code'  => $_POST['category_code'] ?: null,
        'model'          => trim($_POST['model'] ?? '') ?: null,
        'location'       => $_POST['location'] ?: null,
        'custodian'      => trim($_POST['custodian'] ?? '') ?: null,
        'receive_date'   => $_POST['receive_date'] ?: null,
        'doc_ref'        => trim($_POST['doc_ref'] ?? '') ?: null,
        'budget_type'    => $_POST['budget_type'] ?: 'budget',
        'proc_method'    => $_POST['proc_method'] ?: 'specific',
        'vendor_name'    => trim($_POST['vendor_name'] ?? '') ?: null,
        'vendor_address' => trim($_POST['vendor_address'] ?? '') ?: null,
        'vendor_tel'     => trim($_POST['vendor_tel'] ?? '') ?: null,
        'cost'           => floatval($_POST['cost'] ?? 0),
        'useful_life'    => $_POST['useful_life'] !== '' ? intval($_POST['useful_life']) : null,
        'dep_rate'       => $_POST['dep_rate'] !== '' ? floatval($_POST['dep_rate']) : null,
        'image_path'     => $imagePath,
    ];

    if (empty($data['code']) || empty($data['name'])) {
        $error = 'กรุณากรอกเลขทะเบียนและชื่อรายการ';
    } else {
        $batchQty = isset($_POST['batch_qty']) ? intval($_POST['batch_qty']) : 1;
        if ($isEdit) {
            $ok = inv_exec(
                "UPDATE assets SET
                    code=?, name=?, category_code=?, model=?,
                    location=?, custodian=?, receive_date=?,
                    doc_ref=?, budget_type=?, proc_method=?,
                    vendor_name=?, vendor_address=?, vendor_tel=?,
                    cost=?, useful_life=?, dep_rate=?, image_path=?
                WHERE id=?",
                [
                    $data['code'], $data['name'], $data['category_code'], $data['model'],
                    $data['location'], $data['custodian'], $data['receive_date'],
                    $data['doc_ref'], $data['budget_type'], $data['proc_method'],
                    $data['vendor_name'], $data['vendor_address'], $data['vendor_tel'],
                    $data['cost'], $data['useful_life'], $data['dep_rate'], $data['image_path'],
                    $id
                ]
            );
        } else {
            if ($batchQty > 1) {
                // Batch Insert logic
                // Format: [Prefix]/[Sequence]/[Year] e.g. คว04/01/2568
                $parts = explode('/', $data['code']);
                if (count($parts) === 3) {
                    $prefix = $parts[0];
                    $startSeq = intval($parts[1]);
                    $year = $parts[2];
                    
                    $ok = true;
                    for ($i = 0; $i < $batchQty; $i++) {
                        $currentSeq = sprintf('%02d', $startSeq + $i);
                        $currentCode = "$prefix/$currentSeq/$year";
                        
                        // Check duplicate
                        $dup = inv_row("SELECT id FROM assets WHERE code=? LIMIT 1", [$currentCode]);
                        if ($dup) {
                            $error = "เลขทะเบียน $currentCode มีในระบบแล้ว (ข้ามการบันทึกรายการที่เหลือ)";
                            $ok = false;
                            break;
                        }

                        $insertOk = inv_exec(
                            "INSERT INTO assets
                                (code,name,category_code,model,location,custodian,receive_date,doc_ref,
                                 budget_type,proc_method,vendor_name,vendor_address,vendor_tel,
                                 cost,useful_life,dep_rate,image_path)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                            [
                                $currentCode, $data['name'], $data['category_code'], $data['model'],
                                $data['location'], $data['custodian'], $data['receive_date'],
                                $data['doc_ref'], $data['budget_type'], $data['proc_method'],
                                $data['vendor_name'], $data['vendor_address'], $data['vendor_tel'],
                                $data['cost'], $data['useful_life'], $data['dep_rate'], $data['image_path']
                            ]
                        );
                        if (!$insertOk) {
                            $ok = false;
                            $error = "เกิดข้อผิดพลาดขณะบันทึกรายการที่ " . ($i+1);
                            break;
                        }
                    }
                } else {
                    $error = 'รูปแบบเลขทะเบียนไม่ถูกต้องสำหรับการบันทึกแบบ Batch (ต้องเป็น รูปแบบ/ลำดับ/ปี เช่น คว04/01/2568)';
                    $ok = false;
                }
            } else {
                // Single Insert logic
                $dup = inv_row("SELECT id FROM assets WHERE code=? LIMIT 1", [$data['code']]);
                if ($dup) {
                    $error = 'เลขทะเบียนนี้มีในระบบแล้ว';
                    $ok = false;
                } else {
                    $ok = inv_exec(
                        "INSERT INTO assets
                            (code,name,category_code,model,location,custodian,receive_date,doc_ref,
                             budget_type,proc_method,vendor_name,vendor_address,vendor_tel,
                             cost,useful_life,dep_rate,image_path)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        [
                            $data['code'], $data['name'], $data['category_code'], $data['model'],
                            $data['location'], $data['custodian'], $data['receive_date'],
                            $data['doc_ref'], $data['budget_type'], $data['proc_method'],
                            $data['vendor_name'], $data['vendor_address'], $data['vendor_tel'],
                            $data['cost'], $data['useful_life'], $data['dep_rate'], $data['image_path']
                        ]
                    );
                }
            }
        }
        if (empty($error)) {
            if ($ok) {
                $success = true;
            } else {
                $error = $error ?: 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่';
            }
        }
    }
}
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
.page-card {
    background:#fff; border-radius:12px;
    box-shadow:0 2px 12px rgba(0,0,0,0.07);
    padding:28px 32px; max-width:980px; margin:0 auto;
}
.page-card .card-title-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
.page-card .card-title-row h5 { color:#4e54c8; font-weight:700; font-size:18px; margin:0; }
.batch-toggle-label { font-size:13px; color:#555; display:flex; align-items:center; gap:8px; cursor:pointer; }
.form-label { font-size:13px; font-weight:500; color:#333; margin-bottom:4px; }
.form-control, .form-select { font-family:'Kanit',sans-serif; font-size:14px; border-radius:8px; border:1px solid #d0d0d0; padding:8px 12px; height:40px; }
.form-control:focus, .form-select:focus { border-color:#4e54c8; box-shadow:0 0 0 0.15rem rgba(78,84,200,0.18); }
.btn-save { width:100%; height:46px; font-size:15px; font-weight:600; border-radius:8px; background:#4e54c8; color:#fff; border:none; letter-spacing:0.5px; transition:background 0.2s; }
.btn-save:hover { background:#3a3fb8; color:#fff; }
.auto-fill-input { background:#f3f4ff!important; color:#4e54c8; font-weight:600; }
</style>

<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title">
            <h4 class="mb-0 fw-bold text-dark">ควบคุมทรัพย์สิน</h4>
            <small class="text-muted"><?= $isEdit ? 'แก้ไขข้อมูล' : 'ขึ้นทะเบียนใหม่' ?></small>
        </div>
        <div class="user-profile d-flex align-items-center">
            <i class='bx bx-user-circle' style='font-size:32px; color:#11998e; margin-right:10px;'></i>
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
        <div class="mb-4"><a href="asset.php" class="btn btn-outline-secondary rounded-pill px-4"><i class='bx bx-arrow-back me-1'></i>ย้อนกลับ</a></div>

        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 mb-3"><i class='bx bx-error-circle me-2'></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="page-card">
            <div class="card-title-row">
                <h5><i class='bx bx-<?= $isEdit ? "edit" : "plus-circle" ?> me-2'></i><?= $isEdit ? 'แก้ไขข้อมูล' : 'เพิ่มข้อมูลใหม่' ?></h5>
                <label class="batch-toggle-label">
                    <input type="checkbox" id="batchMode" style="width:16px;height:16px;">
                    โหมด Batch (หลายชิ้น)
                </label>
            </div>

            <form id="assetForm" method="POST" action="add_asset.php<?= $isEdit ? '?id='.$id : '' ?>" enctype="multipart/form-data">
                <div class="row g-3">
                    <!-- Procurement Link -->
                    <div class="col-12 mb-2">
                        <div class="p-3" style="background:#f8f9ff; border:1px solid #c5caff; border-radius:12px;">
                            <label class="form-label fw-bold text-primary" style="font-size:14px;"><i class='bx bx-link me-2'></i>อ้างอิงข้อมูลจากการจัดซื้อ/จัดจ้าง <span class="fw-normal text-muted">(เพื่อให้แสดงไฟล์ใบเสร็จ และดึงข้อมูลราคา/ผู้ขาย)</span></label>
                            <div class="d-flex gap-2">
                                <select class="form-select w-100" id="procurement_ref" onchange="onProcurementChange(this)">
                                    <option value="">- เลือกรายการจัดซื้อ (ถ้ามี) -</option>
                                    <?php foreach ($procurements as $p): ?>
                                    <option value="<?= $p['id'] ?>"
                                            data-vendor="<?= htmlspecialchars($p['vendor_name'] ?? '') ?>"
                                            data-cost="<?= floatval($p['total_amount'] ?? 0) ?>"
                                            data-doc-ref="<?= htmlspecialchars($p['order_no'] ?? '') ?>"
                                            data-attachment="<?= htmlspecialchars($p['attachment_path'] ?? '') ?>">
                                        <?= htmlspecialchars($p['order_no']) ?>: <?= htmlspecialchars($p['title']) ?> (<?= number_format($p['total_amount'], 2) ?> บาท)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="#" id="btnPreviewAttachment" target="_blank" class="btn btn-outline-info" style="display:none; white-space:nowrap;"><i class='bx bx-file-find'></i> ดูไฟล์แนบ</a>
                                <button type="button" id="btnFillData" class="btn btn-primary" style="display:none; white-space:nowrap;" onclick="fillProcurementData()"><i class='bx bx-import'></i> ดึงข้อมูลมาเติม</button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 1: เลขทะเบียน | ประเภท | ชื่อรายการ -->
                    <div class="col-md-4">
                        <label class="form-label">เลขทะเบียน <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="asset_code"
                               placeholder="เช่น คว04/01/2569"
                               value="<?= htmlspecialchars($asset['code'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ประเภท</label>
                        <select class="form-select" id="assetCategorySelect" name="category_code" onchange="onCategoryChange()">
                            <option value="">- เลือก -</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['code'] ?>"
                                    data-age="<?= $cat['useful_life'] ?>"
                                    data-rate="<?= $cat['dep_rate'] ?>"
                                    <?= (($asset['category_code'] ?? '') === $cat['code']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['code'] . ' - ' . $cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ชื่อรายการ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="asset_name"
                               value="<?= htmlspecialchars($asset['name'] ?? '') ?>" required>
                    </div>

                    <!-- Row 2: สถานที่ | รุ่น/แบบ -->
                    <div class="col-md-6">
                        <label class="form-label">สถานที่ตั้ง/ใช้งาน</label>
                        <input type="text" class="form-control" name="location"
                               value="<?= htmlspecialchars($asset['location'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">รุ่น/แบบ</label>
                        <input type="text" class="form-control" name="model"
                               value="<?= htmlspecialchars($asset['model'] ?? '') ?>">
                    </div>

                    <!-- Row 3: รูปภาพ Upload -->
                    <div class="col-12">
                        <label class="form-label">รูปภาพครุภัณฑ์</label>
                        <?php if (!empty($asset['image_path'])): ?>
                        <div class="mb-2 d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($asset['image_path']) ?>" style="height:60px; border-radius:8px; border:2px solid #e0e0e0;">
                            <span class="text-muted" style="font-size:13px;">รูปปัจจุบัน (อัปโหลดใหม่เพื่อเปลี่ยน)</span>
                        </div>
                        <?php endif; ?>
                        <div id="dropZone"
                             onclick="document.getElementById('imageFileInput').click()"
                             ondragover="event.preventDefault(); this.classList.add('drag-over');"
                             ondragleave="this.classList.remove('drag-over');"
                             ondrop="handleDrop(event)"
                             style="border:2px dashed #c5caff; border-radius:12px; background:#f8f9ff; padding:28px 20px; text-align:center; cursor:pointer; transition:background 0.2s,border-color 0.2s;">
                            <div id="dropHint">
                                <i class='bx bx-image-add' style="font-size:40px; color:#a0a8ff;"></i>
                                <div style="font-size:14px; color:#666; margin-top:6px;"><strong>ลากไฟล์มาวาง</strong> หรือ <strong>คลิกเพื่อเลือก</strong></div>
                                <div style="font-size:12px; color:#aaa; margin-top:4px;">JPG, PNG, WEBP &bull; ไม่เกิน 5 MB</div>
                            </div>
                            <div id="previewWrap" style="display:none; flex-direction:column; align-items:center; gap:10px; position:relative;">
                                <img id="imgPreview" src="" alt="Preview" style="max-height:160px; max-width:100%; border-radius:10px; border:2px solid #c5caff; object-fit:cover;">
                                <div id="previewFilename" style="font-size:13px; color:#555;"></div>
                                <button type="button" onclick="clearImage(event)" style="position:absolute; top:-6px; right:-6px; background:#ff5c5c; border:none; color:#fff; border-radius:50%; width:26px; height:26px; font-size:16px; cursor:pointer; line-height:1;">&times;</button>
                            </div>
                        </div>
                        <input type="file" id="imageFileInput" name="image_file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleImageFile(this.files[0])">
                    </div>

                    <!-- Row 4: วันที่รับ | ที่เอกสาร | เงิน | วิธีได้มา -->
                    <div class="col-md-3">
                        <label class="form-label">วันที่รับ</label>
                        <input type="text" id="receiveDateInput" class="form-control" name="receive_date"
                               placeholder="วว/ดด/ปปปป (แสดง) — ส่งเป็น YYYY-MM-DD"
                               value="<?= htmlspecialchars($asset['receive_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ที่เอกสาร</label>
                        <input type="text" class="form-control" name="doc_ref"
                               placeholder="เลขที่ใบส่งของ/สัญญา"
                               value="<?= htmlspecialchars($asset['doc_ref'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ประเภทเงิน</label>
                        <select class="form-select" name="budget_type">
                            <?php foreach (['budget'=>'เงินงบประมาณ','off_budget'=>'เงินนอกงบประมาณ','donation'=>'เงินบริจาค','other'=>'อื่นๆ'] as $v=>$t): ?>
                            <option value="<?= $v ?>" <?= (($asset['budget_type']??'budget')===$v)?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">วิธีได้มา</label>
                        <select class="form-select" name="proc_method">
                            <?php foreach (['specific'=>'เฉพาะเจาะจง','select'=>'คัดเลือก','ebidding'=>'e-bidding','price_check'=>'สอบราคา','donation'=>'รับบริจาค'] as $v=>$t): ?>
                            <option value="<?= $v ?>" <?= (($asset['proc_method']??'specific')===$v)?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Row 5: ผู้ขาย | ที่อยู่ผู้ขาย -->
                    <div class="col-md-6">
                        <label class="form-label">ผู้ขาย (ชื่อร้าน/บริษัท)</label>
                        <input type="text" class="form-control" name="vendor_name"
                               value="<?= htmlspecialchars($asset['vendor_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ที่อยู่ผู้ขาย</label>
                        <input type="text" class="form-control" name="vendor_address"
                               placeholder="เลขที่ หมู่ ตำบล อำเภอ จังหวัด"
                               value="<?= htmlspecialchars($asset['vendor_address'] ?? '') ?>">
                    </div>

                    <!-- Row 6: เบอร์โทร | ราคา | ผู้รับผิดชอบ -->
                    <div class="col-md-4">
                        <label class="form-label">เบอร์โทร</label>
                        <input type="tel" class="form-control" name="vendor_tel"
                               value="<?= htmlspecialchars($asset['vendor_tel'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ราคา (บาท) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="cost" min="0" step="0.01"
                               value="<?= htmlspecialchars($asset['cost'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ผู้รับผิดชอบ/ผู้รักษา</label>
                        <input type="text" class="form-control" name="custodian"
                               value="<?= htmlspecialchars($asset['custodian'] ?? '') ?>">
                    </div>

                    <!-- Row 7: อายุ | ค่าเสื่อม (auto-fill) -->
                    <div class="col-md-6">
                        <label class="form-label">อายุตามเกณฑ์ (ปี)</label>
                        <input type="number" class="form-control auto-fill-input"
                               id="assetAgeInput" name="useful_life" readonly
                               value="<?= htmlspecialchars($asset['useful_life'] ?? '') ?>"
                               placeholder="ดึงจากประเภทอัตโนมัติ">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">อัตราค่าเสื่อม (%)</label>
                        <input type="number" class="form-control auto-fill-input"
                               id="assetRateInput" name="dep_rate" step="0.01" readonly
                               value="<?= htmlspecialchars($asset['dep_rate'] ?? '') ?>"
                               placeholder="ดึงจากประเภทอัตโนมัติ">
                    </div>

                    <!-- Batch section -->
                    <div class="col-12" id="batchSection" style="display:none;">
                        <div class="alert alert-warning py-2" style="border-radius:8px; font-size:13px;">
                            <i class='bx bx-layer me-1'></i><strong>โหมด Batch:</strong> ระบุจำนวนชิ้นที่ต้องการขึ้นทะเบียนพร้อมกัน
                        </div>
                        <label class="form-label">จำนวนชิ้น</label>
                        <input type="number" class="form-control" name="batch_qty" min="2" max="100" value="2" style="max-width:160px;">
                    </div>

                    <!-- Save -->
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn-save"><?= $isEdit ? 'บันทึกการแก้ไข' : 'บันทึก' ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="frontend/assets/js/asset.js"></script>
<!-- Flatpickr for nicer date input with Thai locale -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script>
    function onProcurementChange(sel) {
        let opt = sel.options[sel.selectedIndex];
        let fileBtn = document.getElementById('btnPreviewAttachment');
        let fillBtn = document.getElementById('btnFillData');
        
        if (sel.value) {
            let fileUrl = opt.getAttribute('data-attachment');
            if (fileUrl) {
                fileBtn.href = fileUrl;
                fileBtn.style.display = 'inline-block';
            } else {
                fileBtn.style.display = 'none';
            }
            fillBtn.style.display = 'inline-block';
        } else {
            fileBtn.style.display = 'none';
            fillBtn.style.display = 'none';
        }
    }

    function fillProcurementData() {
        let sel = document.getElementById('procurement_ref');
        let opt = sel.options[sel.selectedIndex];
        if (!sel.value) return;

        let vendor = opt.getAttribute('data-vendor');
        let cost = opt.getAttribute('data-cost');
        let docRef = opt.getAttribute('data-doc-ref');

        let costInput = document.querySelector('input[name="cost"]');
        let vendorInput = document.querySelector('input[name="vendor_name"]');
        let docRefInput = document.querySelector('input[name="doc_ref"]');

        let filledAnything = false;

        if (costInput && (!costInput.value || costInput.value == '0') && cost) { costInput.value = cost; filledAnything = true; }
        if (vendorInput && !vendorInput.value && vendor) { vendorInput.value = vendor; filledAnything = true; }
        if (docRefInput && !docRefInput.value && docRef) { docRefInput.value = docRef; filledAnything = true; }
        
        if (filledAnything) {
            Swal.fire({
                icon: 'success',
                title: 'ดึงข้อมูลสำเร็จ',
                text: 'เติม ราคา, ผู้ขาย และที่เอกสาร เรียบร้อยแล้ว (เฉพาะช่องว่าง)',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'info',
                title: 'ไม่มีข้อมูลให้เติม',
                text: 'อาจจะมีข้อมูลอยู่แล้ว หรือรายการจัดซื้อจัดจ้างนี้ไม่ได้ระบุรายละเอียดเอาไว้',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }

    <?php if ($success): ?>
    Swal.fire({ icon:'success', title:'<?= $isEdit ? "แก้ไขสำเร็จ!" : "บันทึกสำเร็จ!" ?>',
        text:'ข้อมูลทรัพย์สินถูกบันทึกเรียบร้อยแล้ว',
        confirmButtonText:'ตกลง', confirmButtonColor:'#4e54c8'
    }).then(() => { 
        window.location.href = '<?= $isEdit ? "asset_detail.php?id=" . $id : "asset.php" ?>'; 
    });
    <?php endif; ?>

    document.getElementById('batchMode').addEventListener('change', function() {
        document.getElementById('batchSection').style.display = this.checked ? 'block' : 'none';
    });

    function onCategoryChange() {
        // Map category_code to specific registry prefix
        const categoryPrefixMap = {
            '01': 'คว01/', // สำนักงาน
            '02': 'คว02/', // การศึกษา
            '03': 'คว03/', // ยานพาหนะ
            '04': 'คว04/', // คอมพิวเตอร์
            '05': 'คว05/', 
            '06': 'คว06/', // ก่อสร้าง
            '07': 'คว07/', // ไฟฟ้าและวิทยุ
            '08': 'คว08/', // โฆษณาและเผยแพร่
            '09': 'คว09/', // วิทยาศาสตร์
            '10': 'คว10/', // การแพทย์
            '11': 'คว11/', // งานบ้านงานครัว
            '12': 'คว12/', // กีฬา
            '13': 'คว13/', // ดนตรี/นาฏศิลป์
            '14': 'คว14/', // สนาม
            '15': 'คว15/', // สิ่งปลูกสร้าง (ถาวร)
            '16': 'คว16/', // สิ่งปลูกสร้าง (ชั่วคราว)
            '17': 'คว17/'  // ต่ำกว่าเกณฑ์
        };

        const sel   = document.getElementById('assetCategorySelect');
        const opt   = sel.options[sel.selectedIndex];
        const catId = sel.value;
        const age   = opt.dataset.age;
        const rate  = opt.dataset.rate;
        const ageEl = document.getElementById('assetAgeInput');
        const rateEl= document.getElementById('assetRateInput');
        
        // Handle Auto-Prefix
        const codeInput = document.querySelector('input[name="asset_code"]');
        if (catId && categoryPrefixMap[catId]) {
            // Only overwrite if it's empty or currently matches an old prefix
            let currentVal = codeInput.value.trim();
            let isOldPrefix = Object.values(categoryPrefixMap).some(p => currentVal === p || currentVal.startsWith(p));
            if (!currentVal || isOldPrefix) {
                // Keep the suffix if it exists (e.g., changes from คว01/05/2569 -> คว02/05/2569)
                let suffix = '';
                if (isOldPrefix && currentVal.includes('/')) {
                   let parts = currentVal.split('/');
                   parts.shift(); // remove prefix
                   suffix = parts.join('/');
                }
                
                codeInput.value = categoryPrefixMap[catId] + suffix;
                // Focus at the end of input
                setTimeout(() => { codeInput.focus(); codeInput.setSelectionRange(codeInput.value.length, codeInput.value.length); }, 10);
            }
        }

        if (age  && age  !== 'null') { ageEl.value  = age;  ageEl.style.color = '#4e54c8'; } else { ageEl.value = ''; }
        if (rate && rate !== 'null') { rateEl.value = rate; rateEl.style.color = '#4e54c8'; } else { rateEl.value = ''; }
    }

    function handleImageFile(file) {
        if (!file) return;
        if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
            Swal.fire({icon:'error',title:'ไฟล์ไม่รองรับ',text:'JPG, PNG, WEBP เท่านั้น'}); return;
        }
        if (file.size > 5*1024*1024) {
            Swal.fire({icon:'error',title:'ไฟล์ใหญ่เกินไป',text:'ไม่เกิน 5 MB'}); return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('imgPreview').src = e.target.result;
            document.getElementById('previewFilename').textContent = file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
            document.getElementById('dropHint').style.display   = 'none';
            document.getElementById('previewWrap').style.display = 'flex';
            document.getElementById('dropZone').style.borderColor = '#4e54c8';
            document.getElementById('dropZone').style.background  = '#f0f1ff';
        };
        reader.readAsDataURL(file);
    }
    function clearImage(e) {
        e.stopPropagation();
        document.getElementById('imageFileInput').value = '';
        document.getElementById('imgPreview').src = '';
        document.getElementById('dropHint').style.display   = 'block';
        document.getElementById('previewWrap').style.display = 'none';
        document.getElementById('dropZone').style.borderColor = '#c5caff';
        document.getElementById('dropZone').style.background  = '#f8f9ff';
    }
    function handleDrop(e) {
        e.preventDefault();
        document.getElementById('dropZone').classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer(); dt.items.add(file);
            document.getElementById('imageFileInput').files = dt.files;
            handleImageFile(file);
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        const s = document.createElement('style');
        s.textContent = '.drag-over{border-color:#4e54c8!important;background:#e8eaff!important;}';
        document.head.appendChild(s);
        // Initialize flatpickr for Thai language display (submission remains YYYY-MM-DD)
        try {
            flatpickr("input[name=receive_date]", {
                locale: 'th',
                altInput: true,
                altFormat: 'd/m/Y',
                dateFormat: 'Y-m-d',
                allowInput: true
            });
        } catch (e) {
            // If flatpickr fails to load, fall back to native date input (no-op)
            console.warn('flatpickr init failed', e);
        }
        // Validate receive_date on form submit to ensure server gets YYYY-MM-DD
        const form = document.getElementById('assetForm');
        if (form) {
            form.addEventListener('submit', function(ev){
                const inp = document.querySelector('input[name=receive_date]');
                if (!inp) return;
                const v = inp.value.trim();
                if (v && !/^\d{4}-\d{2}-\d{2}$/.test(v)) {
                    ev.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'รูปแบบวันที่ไม่ถูกต้อง',
                        text: 'วันที่จะส่งไปยังระบบต้องเป็นรูปแบบ YYYY-MM-DD (ระบบแสดงเป็น dd/mm/YYYY เพื่อความคุ้นเคย)',
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#4e54c8'
                    });
                }
            });
        }
    });
</script>

<?php include 'frontend/includes/footer.php'; ?>
