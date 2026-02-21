<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['inv_user_id']);
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

// ---- Labels ----
$moneyGroupLabel = [
    'operation'  => 'งบดำเนินงาน',
    'salary'     => 'จ้างเหมา/เงินเดือน',
    'special'    => 'โครงการพิเศษ',
    'income'     => 'รายได้สถานศึกษา',
    'subsidy'    => 'อุดหนุน อปท.',
    'investment' => 'ลงทุน/ซ่อมแซม',
];
$moneyGroupColor = [
    'operation'  => '#4e73df',
    'salary'     => '#6f42c1',
    'special'    => '#f39c12',
    'income'     => '#27ae60',
    'subsidy'    => '#e74c3c',
    'investment' => '#795548',
];
$statusLabel = [
    'pending'   => ['text' => 'รอดำเนินการ', 'class' => 'bg-secondary text-white'],
    'approved'  => ['text' => 'อนุมัติแล้ว',  'class' => 'badge-inuse'],
    'received'  => ['text' => 'ตรวจรับแล้ว', 'class' => 'badge-available'],
    'cancelled' => ['text' => 'ยกเลิก',       'class' => 'badge-writeoff'],
];
$procMethodLabel = [
    'specific'    => 'เฉพาะเจาะจง',
    'select'      => 'คัดเลือก',
    'ebidding'    => 'e-bidding',
    'price_check' => 'สอบราคา',
    'donation'    => 'รับบริจาค',
];

// ---- Handle DELETE ----
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['order_id'] ?? 0);
    if ($del_id) {
        inv_exec("DELETE FROM procurement_orders WHERE id=?", [$del_id]);
    }
    header('Location: procurement.php'); exit;
}

// ---- Fiscal Year filter ----
$current_year = (int)date('Y') + 543;
$fiscal_year  = isset($_GET['fiscal_year']) ? intval($_GET['fiscal_year']) : $current_year;
$tab          = $_GET['tab'] ?? 'buy';

// ---- Stats by money group (current fiscal year) ----
$statsRaw = inv_query(
    "SELECT money_group, SUM(total_amount) AS total FROM procurement_orders
     WHERE fiscal_year=? GROUP BY money_group",
    [$fiscal_year]
);
$stats = [];
foreach ($statsRaw as $s) { $stats[$s['money_group']] = $s['total']; }
$grand_total = array_sum($stats);
$total_buy  = inv_row("SELECT COUNT(*) AS c, COALESCE(SUM(total_amount),0) AS t FROM procurement_orders WHERE fiscal_year=? AND order_type='buy'",  [$fiscal_year]);
$total_hire = inv_row("SELECT COUNT(*) AS c, COALESCE(SUM(total_amount),0) AS t FROM procurement_orders WHERE fiscal_year=? AND order_type='hire'", [$fiscal_year]);

// ---- Search / Filter ----
$search = trim($_GET['search'] ?? '');
$whereParts = ["order_type = ?", "fiscal_year = ?"];
$params     = [$tab, $fiscal_year];
if ($search) {
    $whereParts[] = "(order_no LIKE ? OR title LIKE ? OR vendor_name LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
$where = 'WHERE ' . implode(' AND ', $whereParts);

$orders = inv_query("SELECT * FROM procurement_orders $where ORDER BY order_date DESC, id DESC LIMIT 200", $params);

// ---- Available fiscal years for dropdown ----
$years = inv_query("SELECT DISTINCT fiscal_year FROM procurement_orders ORDER BY fiscal_year DESC");
if (!$years) $years = [['fiscal_year' => $current_year]];
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
.stat-card  { border-radius:14px; padding:18px 20px; color:#fff; display:flex; flex-direction:column; justify-content:space-between; min-height:90px; }
.table-container { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:24px; }
.badge-status { padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block; }
.proc-tabs .nav-link { font-size:14px; font-weight:600; border:none; padding:8px 20px; border-radius:8px 8px 0 0; }
.proc-tabs .nav-link.active { background:#4e54c8; color:#fff; }
.proc-tabs .nav-link:not(.active) { color:#666; }
.search-wrap { position:relative; }
.search-wrap .bx { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#aaa; font-size:18px; }
.search-wrap input { padding-left:36px; }
</style>

<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title">
            <h4 class="mb-0 fw-bold text-dark"><i class='bx bx-notepad text-primary me-2'></i>ระบบงานพัสดุ</h4>
            <small class="text-muted">Procurement Management</small>
        </div>
        <div class="user-profile d-flex align-items-center gap-2">
            <?php if ($is_logged_in): ?>
                <i class='bx bx-user-circle' style='font-size:32px; color:#4e54c8;'></i>
                <div>
                    <span class="d-block fw-semibold" style="line-height:1;"><?= htmlspecialchars($_SESSION['inv_fullname']) ?></span>
                    <small class="text-muted"><?= htmlspecialchars($_SESSION['inv_role']) ?></small>
                </div>
            <?php else: ?>
                <i class='bx bx-user-circle' style='font-size:32px; color:#aaa;'></i>
                <div>
                    <span class="d-block fw-semibold" style="line-height:1;">คนทั่วไป</span>
                    <small class="text-muted">User</small>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="main-content">

        <!-- ===== Header Row: Year + Buttons ===== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-0">ทะเบียนคุมการจัดซื้อจัดจ้าง</h5>
                <small class="text-muted">โรงเรียนบ้านโคกวิทยา</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <!-- Fiscal Year Filter -->
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <label class="mb-0 fw-bold text-muted" style="font-size:13px;"><i class='bx bx-calendar me-1'></i>ปีงบ:</label>
                    <select name="fiscal_year" class="form-select form-select-sm" style="width:90px;" onchange="this.form.submit()">
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y['fiscal_year'] ?>" <?= $y['fiscal_year'] == $fiscal_year ? 'selected' : '' ?>><?= $y['fiscal_year'] ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array($current_year, array_column($years, 'fiscal_year'))): ?>
                        <option value="<?= $current_year ?>" <?= $current_year == $fiscal_year ? 'selected' : '' ?>><?= $current_year ?></option>
                        <?php endif; ?>
                    </select>
                </form>
                <a href="print_procurement.php?fiscal_year=<?= $fiscal_year ?>&order_type=<?= $tab ?>"
                   target="_blank" class="btn btn-outline-secondary rounded-pill px-4 fw-medium" style="font-size:14px;">
                    <i class='bx bx-printer me-1'></i>พิมพ์รายงาน
                </a>
                <?php if ($is_logged_in): ?>
                <a href="import_procurement.php" class="btn btn-outline-primary rounded-pill px-4 fw-medium" style="font-size:14px;">
                    <i class='bx bx-import me-1'></i>นำเข้า CSV
                </a>
                <button class="btn btn-success rounded-pill px-4 fw-medium" style="font-size:14px;"
                        onclick="openAddModal()">
                    <i class='bx bx-plus me-1'></i>เพิ่มรายการ
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Summary Cards ===== -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#4e54c8,#8f94fb);">
                    <small style="opacity:.8;">รวมทั้งหมด</small>
                    <div>
                        <div class="fw-bold" style="font-size:22px;">฿<?= number_format($grand_total, 2) ?></div>
                        <small><?= (($total_buy['c'] ?? 0) + ($total_hire['c'] ?? 0)) ?> รายการ</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#11998e,#38ef7d);">
                    <small style="opacity:.8;">จัดซื้อ</small>
                    <div>
                        <div class="fw-bold" style="font-size:22px;">฿<?= number_format($total_buy['t'] ?? 0, 2) ?></div>
                        <small><?= $total_buy['c'] ?? 0 ?> รายการ</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#f7971e,#ffd200);">
                    <small style="color:#333;opacity:.8;">จัดจ้าง</small>
                    <div>
                        <div class="fw-bold" style="font-size:22px;color:#333;">฿<?= number_format($total_hire['t'] ?? 0, 2) ?></div>
                        <small style="color:#333;"><?= $total_hire['c'] ?? 0 ?> รายการ</small>
                    </div>
                </div>
            </div>
            <!-- Money Groups -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100" style="border-radius:14px;padding:14px 18px;">
                    <small class="fw-bold text-muted mb-2"><i class='bx bx-layer me-1'></i>แยกตามกลุ่มเงิน</small>
                    <?php foreach ($stats as $group => $total): ?>
                    <div class="d-flex justify-content-between" style="font-size:12px;margin-bottom:4px;">
                        <span style="color:<?= $moneyGroupColor[$group] ?? '#aaa' ?>;">● <?= $moneyGroupLabel[$group] ?? $group ?></span>
                        <span class="fw-bold">฿<?= number_format($total, 0) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($stats)): ?>
                    <small class="text-muted">ยังไม่มีข้อมูล</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== Main Table ===== -->
        <div class="table-container">

            <!-- Tabs + Search -->
            <div class="d-flex justify-content-between align-items-end mb-3">
                <ul class="nav proc-tabs">
                    <li class="nav-item">
                        <a href="?tab=buy&fiscal_year=<?= $fiscal_year ?>" class="nav-link <?= $tab === 'buy' ? 'active' : '' ?>">
                            <i class='bx bx-cart me-1'></i>จัดซื้อ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?tab=hire&fiscal_year=<?= $fiscal_year ?>" class="nav-link <?= $tab === 'hire' ? 'active' : '' ?>">
                            <i class='bx bx-wrench me-1'></i>จัดจ้าง
                        </a>
                    </li>
                </ul>
                <form method="GET" class="search-wrap" style="width:260px;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="fiscal_year" value="<?= $fiscal_year ?>">
                    <i class='bx bx-search'></i>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="ค้นหาเลขที่ / รายการ / ผู้ขาย..."
                           value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-center" style="font-size:13px;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:100px;">เลขที่</th>
                            <th style="width:90px;">วันที่</th>
                            <th class="text-start">รายการ</th>
                            <th style="width:110px;">วงเงิน (บาท)</th>
                            <th style="width:110px;">กลุ่มเงิน</th>
                            <th style="width:130px;">ผู้ขาย/ผู้รับจ้าง</th>
                            <th style="width:100px;">สถานะ</th>
                            <?php if ($is_logged_in): ?>
                            <th style="width:90px;">จัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="<?= $is_logged_in ? 9 : 8 ?>" class="text-center text-muted py-5">
                            <i class='bx bx-folder-open' style="font-size:40px;display:block;color:#ddd;"></i>
                            ยังไม่มีรายการ<?= $search ? " ที่ค้นหา \"$search\"" : '' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php $total_sum = 0; foreach ($orders as $i => $o):
                            $sl = $statusLabel[$o['status']] ?? ['text' => $o['status'], 'class' => 'bg-secondary text-white'];
                            $total_sum += $o['total_amount'];
                        ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="text-primary fw-bold"><?= htmlspecialchars($o['order_no']) ?></td>
                            <td><?= date('d/m/Y', strtotime($o['order_date'])) ?></td>
                            <td class="text-start"><?= htmlspecialchars($o['title']) ?></td>
                            <td class="fw-bold">฿<?= number_format($o['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge" style="background:<?= $moneyGroupColor[$o['money_group']] ?? '#aaa' ?>;">
                                    <?= $moneyGroupLabel[$o['money_group']] ?? $o['money_group'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($o['vendor_name'] ?? '-') ?></td>
                            <td><span class="badge-status <?= $sl['class'] ?>"><?= $sl['text'] ?></span></td>
                            <?php if ($is_logged_in): ?>
                            <td>
                                <button class="btn btn-sm btn-outline-warning" title="แก้ไข"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($o)) ?>)">
                                    <i class='bx bx-pencil'></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger ms-1" title="ลบ"
                                        onclick="confirmDelete(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_no'], ENT_QUOTES) ?>')">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">รวม</td>
                            <td>฿<?= number_format($total_sum, 2) ?></td>
                            <td colspan="<?= $is_logged_in ? 4 : 3 ?>"></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div><!-- end table-container -->

    </div><!-- end main-content -->
</section>

<?php if ($is_logged_in): ?>
<!-- ===== Add/Edit Modal ===== -->
<style>
.modal-header-blue { background: linear-gradient(135deg,#4e54c8,#8f94fb); border-radius:16px 16px 0 0; }
.modal-header-blue .modal-title { color:#fff; font-size:18px; }
.modal-header-blue .btn-close { filter:brightness(0) invert(1); }
.form-label.fw-bold { font-size:13px; color:#555; margin-bottom:4px; }
.money-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:6px; }
#form_money_group option { padding-left:8px; }
</style>
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:700px;">
        <div class="modal-content" style="border-radius:16px; overflow:hidden;">
            <form method="POST" action="backend/save_procurement.php">
                <input type="hidden" name="order_id" id="form_id">
                <div class="modal-header modal-header-blue border-0">
                    <h5 class="modal-title fw-bold" id="modalTitle">
                        <i class='bx bx-plus-circle me-2'></i>บันทึกรายการใหม่
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="row g-3">

                        <!-- Row 1: เลขที่ | ปีงบ | ประเภท | วันที่รายงาน -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">เลขที่ <small class="text-muted fw-normal">(ถ้าไม่ใส่จะ Auto)</small></label>
                            <input type="text" class="form-control" name="order_no" id="form_order_no"
                                   placeholder="เช่น ซ27/2569">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">ปีงบประมาณ</label>
                            <select class="form-select" name="fiscal_year" id="form_fiscal_year">
                                <option value="2569" selected>2569</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">ประเภท</label>
                            <select class="form-select" name="order_type" id="form_order_type">
                                <option value="buy">จัดซื้อ</option>
                                <option value="hire">จัดจ้าง</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">วันที่รายงาน <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fp-date" name="order_date" id="form_order_date"
                                   placeholder="วว/ดด/ปปปป (พ.ศ.)" required autocomplete="off">
                        </div>

                        <!-- Row 2: รายการ -->
                        <div class="col-12">
                            <label class="form-label fw-bold">รายการ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="form_title"
                                   placeholder="ระบุรายการจัดซื้อ/จัดจ้าง..." required>
                        </div>

                        <!-- Row 3: วงเงิน | กลุ่มเงิน -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วงเงิน (บาท) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="total_amount" id="form_total_amount"
                                   min="0" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary">กลุ่มเงิน</label>
                            <select class="form-select" name="money_group" id="form_money_group">
                                <?php foreach ($moneyGroupLabel as $v => $t): ?>
                                <option value="<?= $v ?>" data-color="<?= $moneyGroupColor[$v] ?? '#aaa' ?>">
                                    <?= $t ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Row 4: ผู้ขาย | เลข EGP -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ผู้ขาย/ผู้รับจ้าง</label>
                            <input type="text" class="form-control" name="vendor_name" id="form_vendor_name"
                                   placeholder="ชื่อร้าน/บริษัท">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">เลข EGP</label>
                            <input type="text" class="form-control" name="egp_no" id="form_egp_no"
                                   placeholder="-">
                        </div>

                        <!-- Row 5: กำหนดส่ง | วันตรวจรับ -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">กำหนดส่ง (ภายใน)</label>
                            <input type="text" class="form-control fp-date" name="due_date" id="form_due_date"
                                   placeholder="วว/ดด/ปปปป (พ.ศ.)" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วันตรวจรับ</label>
                            <input type="text" class="form-control fp-date" name="inspect_date" id="form_inspect_date"
                                   placeholder="วว/ดด/ปปปป (พ.ศ.)" autocomplete="off">
                        </div>

                        <!-- Row 6: รายละเอียดเพิ่มเติม (doc_ref) -->
                        <div class="col-12">
                            <label class="form-label fw-bold">รายละเอียดเพิ่มเติม (ถ้ามี)</label>
                            <input type="text" class="form-control" name="doc_ref" id="form_doc_ref"
                                   placeholder="เช่น งบสนับสนุนจากรัฐบาล">
                        </div>

                        <!-- Row 7: หมายเหตุ -->
                        <div class="col-12">
                            <label class="form-label fw-bold">หมายเหตุ</label>
                            <input type="text" class="form-control" name="note" id="form_note"
                                   placeholder="เช่น เบิกจ่ายผ่านเขต">
                        </div>

                        <!-- Row 8: สถานะ -->
                        <div class="col-12">
                            <label class="form-label fw-bold">สถานะดำเนินการ</label>
                            <select class="form-select" name="status" id="form_status">
                                <?php foreach ($statusLabel as $v => $s): ?>
                                <option value="<?= $v ?>"><?= $s['text'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-3 px-4" style="border-radius:0 0 16px 16px;">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">
                        <i class='bx bx-save me-1'></i>บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="order_id" id="deleteOrderId">
</form>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if ($is_logged_in): ?>
// Helper: set flatpickr date value (YYYY-MM-DD → display)
function fpSetDate(id, isoDate) {
    const fp = document.getElementById(id)._flatpickr;
    if (!fp) { document.getElementById(id).value = isoDate || ''; return; }
    if (isoDate) {
        fp.setDate(isoDate, true);
        const d = new Date(isoDate);
        const dd = String(d.getDate()).padStart(2,'0');
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const yy = d.getFullYear() + 543;
        fp.altInput.value = dd + '/' + mm + '/' + yy;
    } else {
        fp.clear();
    }
}

function openAddModal() {
    document.getElementById('form_id').value          = '';
    document.getElementById('form_order_no').value     = '';
    document.getElementById('form_order_type').value  = '<?= $tab ?>';
    document.getElementById('form_title').value       = '';
    document.getElementById('form_fiscal_year').value = '<?= $fiscal_year ?>';
    document.getElementById('form_total_amount').value= '';
    document.getElementById('form_money_group').value = 'operation';
    document.getElementById('form_status').value      = 'received';
    document.getElementById('form_doc_ref').value     = '';
    document.getElementById('form_vendor_name').value = '';
    document.getElementById('form_egp_no').value      = '';
    document.getElementById('form_note').value        = '';
    fpSetDate('form_order_date', '');
    fpSetDate('form_due_date', '');
    fpSetDate('form_inspect_date', '');
    document.getElementById('modalTitle').innerHTML = "<i class='bx bx-plus-circle me-2'></i>บันทึกรายการใหม่";
    new bootstrap.Modal(document.getElementById('orderModal')).show();
}

function openEditModal(data) {
    document.getElementById('form_id').value          = data.id;
    document.getElementById('form_order_no').value     = data.order_no || '';
    document.getElementById('form_order_type').value  = data.order_type;
    document.getElementById('form_title').value       = data.title;
    document.getElementById('form_fiscal_year').value = data.fiscal_year;
    document.getElementById('form_total_amount').value= data.total_amount;
    document.getElementById('form_money_group').value = data.money_group;
    document.getElementById('form_status').value      = data.status;
    document.getElementById('form_doc_ref').value     = data.doc_ref || '';
    document.getElementById('form_vendor_name').value = data.vendor_name || '';
    document.getElementById('form_egp_no').value      = data.egp_no || '';
    document.getElementById('form_note').value        = data.note || '';
    fpSetDate('form_order_date',   data.order_date   ? data.order_date.substring(0,10)   : '');
    fpSetDate('form_due_date',     data.due_date      ? data.due_date.substring(0,10)     : '');
    fpSetDate('form_inspect_date', data.inspect_date  ? data.inspect_date.substring(0,10) : '');
    document.getElementById('modalTitle').innerHTML = "<i class='bx bx-edit me-2'></i>แก้ไขรายการ";
    new bootstrap.Modal(document.getElementById('orderModal')).show();
}

function confirmDelete(id, orderNo) {
    Swal.fire({
        title: 'ลบรายการ?',
        text: 'ลบ "' + orderNo + '" — ไม่สามารถยกเลิกได้',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('deleteOrderId').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}
<?php endif; ?>
</script>

<!-- Flatpickr Thai Buddhist Year for ALL date fields -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script>
(function(){
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
                    }
                }
            });
        });
    }
    // Init on page load (for static elements)
    initFpThai('.fp-date');
    // Re-init when modal opens (modal may recreate DOM)
    document.getElementById('orderModal')?.addEventListener('shown.bs.modal', function() {
        initFpThai('.fp-date');
    });
})();
</script>

<?php include 'frontend/includes/footer.php'; ?>
