<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['inv_user_id']);
require_once __DIR__ . '/backend/config/db_inventory.php';

// ---- Handle DELETE ----
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['order_id'] ?? 0);
    if ($del_id) {
        inv_exec("DELETE FROM procurement_orders WHERE id=?", [$del_id]);
    }
    header('Location: procurement.php'); exit;
}

include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

// ---- Labels ----
$moneyGroupLabel = [
    'operation'  => 'งบดำเนินงาน',
    'salary'     => 'จ้างเหมา/เงินเดือน',
    'investment' => 'ลงทุน/ซ่อมแซม',
    'support'    => 'งบสนับสนุน',
    // Legacy groups for backward compatibility
    'special'    => 'โครงการพิเศษ',
    'income'     => 'รายได้สถานศึกษา',
    'subsidy'    => 'อุดหนุน อปท.',
];
$moneyGroupColor = [
    'operation'  => '#4e73df',
    'salary'     => '#6f42c1',
    'investment' => '#795548',
    'support'    => '#ff9800',
    // Legacy colors
    'special'    => '#f39c12',
    'income'     => '#27ae60',
    'subsidy'    => '#e74c3c',
];

// Options allowed to be selected in Add/Edit form
$activeMoneyGroups = ['operation', 'salary', 'investment', 'support'];
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

// Pagination Logic
$items_per_page = 15;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$total_rows_query = inv_row("SELECT COUNT(*) AS total FROM procurement_orders $where", $params);
$total_rows = $total_rows_query['total'] ?? 0;
$total_pages = ceil($total_rows / $items_per_page);

$orders = inv_query("SELECT * FROM procurement_orders $where ORDER BY id DESC LIMIT $items_per_page OFFSET $offset", $params);

// ---- Available fiscal years for dropdown ----
$years = inv_query("SELECT DISTINCT fiscal_year FROM procurement_orders ORDER BY fiscal_year DESC");
if (!$years) $years = [['fiscal_year' => $current_year]];
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
.stat-card {
    border-radius: 20px;
    padding: 24px;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 120px;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
}
.stat-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 150px;
    height: 150px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    pointer-events: none;
}
.table-container { 
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px; 
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07); 
    padding: 24px; 
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition: all 0.3s ease;
}
.table-hover tbody tr { transition: all 0.2s ease; }
.table-hover tbody tr:hover { 
    background-color: #f8faff !important; 
    transform: scale(1.002);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    z-index: 10;
    position: relative;
}
.badge-status { padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 700; display: inline-block; letter-spacing: 0.5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.proc-tabs .nav-link { font-size: 15px; font-weight: 600; border: none; padding: 12px 24px; border-radius: 12px 12px 0 0; transition: all 0.3s ease; position: relative; overflow: hidden; }
.proc-tabs .nav-link.active { background: linear-gradient(135deg, #4e54c8, #8f94fb); color: #fff; box-shadow: 0 -4px 15px rgba(78, 84, 200, 0.2); }
.proc-tabs .nav-link:not(.active) { color: #888; background: #f8f9fa; }
.proc-tabs .nav-link:not(.active):hover { color: #4e54c8; background: #eef0ff; }
.search-wrap { position: relative; }
.search-wrap .bx { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #8f94fb; font-size: 20px; transition: all 0.3s ease; }
.search-wrap input { padding-left: 45px; border-radius: 30px; border: 2px solid #eef0ff; padding-top: 10px; padding-bottom: 10px; transition: all 0.3s ease; background: #fdfdff; box-shadow: inset 0 2px 5px rgba(0,0,0,0.02); }
.search-wrap input:focus { border-color: #8f94fb; box-shadow: 0 0 0 4px rgba(143, 148, 251, 0.15); background: #fff; }
.search-wrap input:focus + .bx { color: #4e54c8; transform: translateY(-50%) scale(1.1); }
.btn-action { transition: all 0.2s; border-radius: 10px; padding: 6px 10px; }
.btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.table-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #edf2f9; }
.table th { background-color: #f8faff !important; color: #4e54c8; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; border-bottom: 2px solid #eef0ff; }
.page-title-gradient { background: linear-gradient(135deg, #4e54c8, #8f94fb); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.money-group-card { transition: all 0.3s ease; border-radius: 20px; padding: 20px; border: 1px solid rgba(0,0,0,0.05); background: linear-gradient(to right bottom, #ffffff, #fcfcff); }
.money-group-card:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.08); transform: translateY(-5px); }
.money-group-item { padding: 8px 12px; border-radius: 8px; transition: background 0.2s; margin-bottom: 6px; }
.money-group-item:hover { background: rgba(0,0,0,0.03); }
.action-buttons .btn { border-radius: 30px; font-weight: 600; padding: 8px 20px; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.action-buttons .btn:hover { transform: translateY(-3px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
.action-buttons .btn:active { transform: translateY(0); }
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
                <h5 class="fw-bold mb-0 page-title-gradient" style="font-size: 26px;">ทะเบียนคุมการจัดซื้อจัดจ้าง</h5>
                <small class="text-muted" style="letter-spacing: 0.5px;">โรงเรียนบ้านโคกวิทยา</small>
            </div>
            <div class="action-buttons d-flex gap-2 align-items-center">
                <!-- Fiscal Year Filter -->
                <form method="GET" class="d-flex align-items-center gap-2 me-2 p-2" style="background: rgba(255,255,255,0.7); border-radius: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <label class="mb-0 fw-bold text-primary ms-2" style="font-size:14px;"><i class='bx bx-calendar me-1'></i>ปีงบ:</label>
                    <select name="fiscal_year" class="form-select border-0 bg-transparent fw-bold text-dark" style="width:100px; outline:none; box-shadow:none; cursor:pointer;" onchange="this.form.submit()">
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y['fiscal_year'] ?>" <?= $y['fiscal_year'] == $fiscal_year ? 'selected' : '' ?>><?= $y['fiscal_year'] ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array($current_year, array_column($years, 'fiscal_year'))): ?>
                        <option value="<?= $current_year ?>" <?= $current_year == $fiscal_year ? 'selected' : '' ?>><?= $current_year ?></option>
                        <?php endif; ?>
                    </select>
                </form>
                <a href="print_procurement.php?fiscal_year=<?= $fiscal_year ?>&order_type=<?= $tab ?>"
                   target="_blank" class="btn btn-outline-secondary">
                    <i class='bx bx-printer me-1'></i>พิมพ์รายงาน
                </a>
                <?php if ($is_logged_in): ?>
                <a href="import_procurement.php" class="btn btn-outline-primary">
                    <i class='bx bx-import me-1'></i>นำเข้า CSV
                </a>
                <button class="btn btn-primary" style="background: linear-gradient(135deg, #11998e, #38ef7d); border: none;"
                        onclick="openAddModal()">
                    <i class='bx bx-plus me-1'></i>เพิ่มรายการ
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Summary Cards ===== -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#4e54c8,#8f94fb);">
                    <div class="d-flex justify-content-between align-items-center">
                        <small style="opacity:0.85; font-size: 14px; letter-spacing: 0.5px; font-weight: 600;">รวมทั้งหมด</small>
                        <i class='bx bx-wallet' style="font-size: 24px; opacity: 0.8;"></i>
                    </div>
                    <div class="mt-3">
                        <div class="fw-bold" style="font-size:28px; letter-spacing: -0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">฿<?= number_format($grand_total, 2) ?></div>
                        <small style="opacity:0.9; font-weight: 500;"><i class='bx bx-list-ul me-1'></i><?= (($total_buy['c'] ?? 0) + ($total_hire['c'] ?? 0)) ?> รายการ</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#11998e,#38ef7d);">
                    <div class="d-flex justify-content-between align-items-center">
                        <small style="opacity:0.85; font-size: 14px; letter-spacing: 0.5px; font-weight: 600;">จัดซื้อ</small>
                        <i class='bx bx-cart' style="font-size: 24px; opacity: 0.8;"></i>
                    </div>
                    <div class="mt-3">
                        <div class="fw-bold" style="font-size:28px; letter-spacing: -0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">฿<?= number_format($total_buy['t'] ?? 0, 2) ?></div>
                        <small style="opacity:0.9; font-weight: 500;"><i class='bx bx-list-ul me-1'></i><?= $total_buy['c'] ?? 0 ?> รายการ</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#ff9966,#ff5e62);">
                    <div class="d-flex justify-content-between align-items-center">
                        <small style="opacity:0.85; font-size: 14px; letter-spacing: 0.5px; font-weight: 600;">จัดจ้าง</small>
                        <i class='bx bx-wrench' style="font-size: 24px; opacity: 0.8;"></i>
                    </div>
                    <div class="mt-3">
                        <div class="fw-bold" style="font-size:28px; letter-spacing: -0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">฿<?= number_format($total_hire['t'] ?? 0, 2) ?></div>
                        <small style="opacity:0.9; font-weight: 500;"><i class='bx bx-list-ul me-1'></i><?= $total_hire['c'] ?? 0 ?> รายการ</small>
                    </div>
                </div>
            </div>
            <!-- Money Groups -->
            <div class="col-md-3">
                <div class="card border-0 h-100 money-group-card">
                    <div class="d-flex align-items-center mb-3 pb-2" style="border-bottom: 1px dashed #eee;">
                        <i class='bx bx-layer fs-5 text-primary me-2'></i>
                        <small class="fw-bold text-dark mb-0 fs-6">แยกตามกลุ่มเงิน</small>
                    </div>
                    <div class="overflow-hidden" style="padding-right: 5px;">
                        <?php foreach ($stats as $group => $total): ?>
                        <div class="d-flex justify-content-between align-items-center money-group-item">
                            <span style="color:<?= $moneyGroupColor[$group] ?? '#aaa' ?>; font-size: 13px; font-weight: 600;">
                                <i class='bx bxs-circle me-1' style="font-size: 10px;"></i><?= $moneyGroupLabel[$group] ?? $group ?>
                            </span>
                            <span class="fw-bold text-dark" style="font-size: 13px;">฿<?= number_format($total, 0) ?></span>
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
                            <th style="width:100px;">สถานะ</th>
                            <?php if ($is_logged_in): ?>
                            <th style="width:90px;">จัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="<?= $is_logged_in ? 8 : 7 ?>" class="text-center text-muted py-5" style="background:#fcfdff;">
                            <div style="background: rgba(78,84,200,0.05); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                <i class='bx bx-folder-open text-primary' style="font-size:40px; opacity: 0.7;"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">ยังไม่มีรายการข้อมูล</h6>
                            <small><?= $search ? "ไม่พบผลลัพธ์ของการค้นหา \"$search\"" : 'เริ่มเพิ่มรายการจัดซื้อจัดจ้างของคุณได้เลย' ?></small>
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
                            <td class="text-start pe-3">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($o['title']) ?></div>
                                <?php if($o['vendor_name']): ?>
                                <small class="text-muted"><i class='bx bx-store-alt me-1'></i><?= htmlspecialchars($o['vendor_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark" style="font-size: 14px;">฿<?= number_format($o['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge" style="background:<?= $moneyGroupColor[$o['money_group']] ?? '#aaa' ?>; padding: 5px 10px; border-radius: 8px; font-weight: 500; font-size: 11px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                                    <?= $moneyGroupLabel[$o['money_group']] ?? $o['money_group'] ?>
                                </span>
                            </td>
                            <td class="text-center"><span class="badge-status <?= $sl['class'] ?>"><?= $sl['text'] ?></span></td>
                            <?php if ($is_logged_in): ?>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-action btn-outline-warning" title="แก้ไข"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($o)) ?>)">
                                        <i class='bx bx-pencil'></i>
                                    </button>
                                    <button class="btn btn-sm btn-action btn-outline-danger" title="ลบ"
                                            onclick="confirmDelete(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_no'], ENT_QUOTES) ?>')">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold" style="background: #f8faff;">
                            <td colspan="4" class="text-end">รวม</td>
                            <td class="text-primary" style="font-size: 14px;">฿<?= number_format($total_sum, 2) ?></td>
                            <td colspan="<?= $is_logged_in ? 3 : 2 ?>"></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <!-- Pagination UI -->
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $qs = $_GET; // preserve existing filters
                    if ($current_page > 1):
                        $qs['page'] = $current_page - 1;
                    ?>
                        <li class="page-item"><a class="page-link shadow-sm" href="?<?= http_build_query($qs) ?>" style="border-radius: 8px 0 0 8px;">ก่อนหน้า</a></li>
                    <?php endif; ?>
                    
                    <?php 
                    // To avoid too many links, let's limit page display if needed, or just show all for now
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        $qs['page'] = 1;
                        echo '<li class="page-item"><a class="page-link shadow-sm" href="?'.http_build_query($qs).'">1</a></li>';
                        if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                        $qs['page'] = $i;
                        $active = ($i === $current_page) ? 'active' : '';
                        $styles = ($active) ? 'background: linear-gradient(135deg, #4e54c8, #8f94fb); color: #fff; border:none;' : '';
                    ?>
                        <li class="page-item <?= $active ?>"><a class="page-link shadow-sm fw-bold" href="?<?= http_build_query($qs) ?>" style="<?= $styles ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    
                    <?php 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                        $qs['page'] = $total_pages;
                        echo '<li class="page-item"><a class="page-link shadow-sm" href="?'.http_build_query($qs).'">'.$total_pages.'</a></li>';
                    }
                    
                    if ($current_page < $total_pages):
                        $qs['page'] = $current_page + 1;
                    ?>
                        <li class="page-item"><a class="page-link shadow-sm" href="?<?= http_build_query($qs) ?>" style="border-radius: 0 8px 8px 0;">ถัดไป</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

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
                                <?php foreach ($activeMoneyGroups as $v): ?>
                                <option value="<?= $v ?>" data-color="<?= $moneyGroupColor[$v] ?? '#aaa' ?>">
                                    <?= $moneyGroupLabel[$v] ?? $v ?>
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
