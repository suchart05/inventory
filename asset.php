<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

// ---- Stats ----
$stats = inv_row("SELECT COUNT(*) AS total, SUM(status='available') AS available, SUM(status='inuse') AS inuse, SUM(status='repair') AS repair, SUM(status='writeoff') AS writeoff FROM assets");

// ---- Filters ----
$whereParts = [];
$params     = [];
if (!empty($_GET['search'])) {
    $s = '%' . $_GET['search'] . '%';
    $whereParts[] = "(a.code LIKE ? OR a.name LIKE ? OR ac.name LIKE ?)";
    $params = array_merge($params, [$s, $s, $s]);
}
if (!empty($_GET['category'])) {
    $whereParts[] = "a.category_code = ?";
    $params[] = $_GET['category'];
}
if (!empty($_GET['status'])) {
    $whereParts[] = "a.status = ?";
    $params[] = $_GET['status'];
}
$where  = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Pagination Logic
$items_per_page = 15;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$total_rows_query = inv_row("SELECT COUNT(*) AS total FROM assets a LEFT JOIN asset_categories ac ON a.category_code=ac.code $where", $params);
$total_rows_filtered = $total_rows_query['total'] ?? 0;
$total_pages = ceil($total_rows_filtered / $items_per_page);

$assets = inv_query("SELECT a.*, ac.name AS cat_name FROM assets a LEFT JOIN asset_categories ac ON a.category_code=ac.code $where ORDER BY a.id DESC LIMIT $items_per_page OFFSET $offset", $params);

// ---- Categories for filter ----
$categories = inv_query("SELECT code, name FROM asset_categories ORDER BY code");

$statusLabel = [
    'available' => ['text'=>'พร้อมใช้งาน', 'class'=>'badge-available'],
    'inuse'     => ['text'=>'กำลังยืมใช้',  'class'=>'badge-inuse'],
    'repair'    => ['text'=>'ซ่อมบำรุง',    'class'=>'badge-repair'],
    'writeoff'  => ['text'=>'จำหน่ายออก',   'class'=>'badge-writeoff'],
];
$is_logged_in = isset($_SESSION['inv_user_id']);
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
.img-thumb { width:44px; height:44px; object-fit:cover; border-radius:8px; border:2px solid #eef0ff; cursor:zoom-in; transition:transform .3s,box-shadow .3s; }
.img-thumb:hover { transform:scale(1.2); box-shadow:0 8px 20px rgba(0,0,0,.15); position:relative; z-index:10; }
.no-img { width:44px; height:44px; border-radius:8px; background:rgba(0,0,0,0.03); display:inline-flex; align-items:center; justify-content:center; color:#ccc; font-size:22px; }
#lightboxImg { max-width:100%; max-height:80vh; border-radius:16px; display:block; margin:0 auto; box-shadow:0 20px 60px rgba(0,0,0,0.3); }

/* --- Modern UI Styles matching Procurement --- */
.stat-info-card {
    background: #fff;
    border-radius: 20px;
    padding: 20px;
    height: 100%;
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.02);
    position: relative;
    overflow: hidden;
}
.stat-info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; transition: transform 0.3s;
}
.stat-info-card:hover .stat-icon { transform: scale(1.1); }
.icon-blue   { background: linear-gradient(135deg, rgba(78,84,200,0.1), rgba(143,148,251,0.1)); color: #4e54c8; }
.icon-green  { background: linear-gradient(135deg, rgba(17,153,142,0.1), rgba(56,239,125,0.1)); color: #11998e; }
.icon-teal   { background: linear-gradient(135deg, rgba(20,30,48,0.05), rgba(36,59,85,0.05)); color: #1FA2FF; }
.icon-orange { background: linear-gradient(135deg, rgba(247,151,30,0.1), rgba(255,210,0,0.1)); color: #f7971e; }
.icon-red    { background: linear-gradient(135deg, rgba(235,87,87,0.1), rgba(0,0,0,0.05)); color: #e74c3c; }

.stat-number { font-size: 24px; font-weight: 700; line-height: 1.2; letter-spacing: -0.5px; }
.stat-label { font-size: 13px; color: #777; font-weight: 500; }

.page-title-gradient { background: linear-gradient(135deg, #11998e, #38ef7d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

.table-container-custom {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.18);
    overflow: hidden;
    margin-top: 15px;
}
.card-header-custom {
    background: #f8faff;
    border-bottom: 1px solid #edf2f9;
    padding: 15px 20px 0;
}
.asset-tabs .nav-link {
    font-size: 14px; font-weight: 600; color: #888;
    border: none; padding: 12px 20px;
    border-radius: 12px 12px 0 0;
    transition: all 0.3s;
    position: relative;
}
.asset-tabs .nav-link:hover { color: #11998e; background: #eefdf5; }
.asset-tabs .nav-link.active {
    color: #fff;
    background: linear-gradient(135deg, #11998e, #38ef7d);
    box-shadow: 0 -4px 15px rgba(17,153,142,0.2);
}

.action-bar {
    background: #fff;
    border-radius: 20px;
    padding: 15px 20px;
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.search-box { position: relative; flex: 1; min-width: 250px; }
.search-box .bx { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #11998e; font-size: 18px; }
.search-box input { 
    padding-left: 42px; border-radius: 30px; border: 2px solid #eefdf5; 
    height: 42px; background: #fcfdfe; transition: all 0.3s; box-shadow: inset 0 2px 5px rgba(0,0,0,0.02);
}
.search-box input:focus { border-color: #11998e; box-shadow: 0 0 0 4px rgba(17,153,142,0.1); background: #fff; }

.table-hover tbody tr { transition: all 0.2s ease; }
.table-hover tbody tr:hover { background-color: #f8faff !important; transform: scale(1.002); box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: relative; z-index: 10; }
.table th { background-color: #f8faff !important; color: #555; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; border-bottom: 2px solid #edf2f9; }

.btn-action { transition: all 0.2s; border-radius: 10px; padding: 6px 10px; border: none; }
.btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); }

.badge-available, .badge-inuse, .badge-repair, .badge-writeoff {
    padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: inline-block;
}
.badge-available { background: linear-gradient(135deg,rgba(17,153,142,0.15),rgba(56,239,125,0.15)); color: #11998e; }
.badge-inuse     { background: linear-gradient(135deg,rgba(31,162,255,0.15),rgba(18,216,250,0.15)); color: #1FA2FF; }
.badge-repair    { background: linear-gradient(135deg,rgba(247,151,30,0.15),rgba(255,210,0,0.15)); color: #f39c12; }
.badge-writeoff  { background: linear-gradient(135deg,rgba(235,87,87,0.15),rgba(0,0,0,0.05)); color: #e74c3c; }

</style>

<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title"><h4 class="mb-0 fw-bold page-title-gradient" style="font-size:26px;">ควบคุมทรัพย์สิน</h4><small class="text-muted" style="letter-spacing:0.5px;">Asset Control Management</small></div>
        <div class="user-profile d-flex align-items-center gap-2">
            <div style="background: rgba(17,153,142,0.1); width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #11998e;">
                <i class='bx bx-user' style="font-size: 22px;"></i>
            </div>
            <div>
                <?php if ($is_logged_in): ?>
                    <span class="d-block fw-bold text-dark" style="line-height:1.2; font-size:14px;"><?= htmlspecialchars($_SESSION['inv_fullname']) ?></span>
                    <small class="text-muted" style="font-size:12px;"><?= htmlspecialchars($_SESSION['inv_role']) ?></small>
                <?php else: ?>
                    <span class="d-block fw-bold text-dark" style="line-height:1.2; font-size:14px;">คนทั่วไป</span>
                    <small class="text-muted" style="font-size:12px;">User</small>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-blue"><i class='bx bx-spreadsheet'></i></div>
                    <div><div class="stat-number" style="color:#4e54c8;"><?= $stats['total'] ?? 0 ?></div><div class="stat-label">รายการทั้งหมด</div></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-green"><i class='bx bx-check-circle'></i></div>
                    <div><div class="stat-number" style="color:#11998e;"><?= $stats['available'] ?? 0 ?></div><div class="stat-label">พร้อมใช้งาน</div></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-teal"><i class='bx bx-user-check'></i></div>
                    <div><div class="stat-number" style="color:#1FA2FF;"><?= $stats['inuse'] ?? 0 ?></div><div class="stat-label">กำลังยืมใช้</div></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-orange"><i class='bx bx-wrench'></i></div>
                    <div><div class="stat-number" style="color:#f7971e;"><?= $stats['repair'] ?? 0 ?></div><div class="stat-label">ซ่อมบำรุง</div></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-red"><i class='bx bx-trash-alt'></i></div>
                    <div><div class="stat-number" style="color:#e74c3c;"><?= $stats['writeoff'] ?? 0 ?></div><div class="stat-label">จำหน่ายออก</div></div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <form method="GET" action="asset.php">
        <div class="action-bar mb-4">
            <div class="search-box">
                <i class='bx bx-search'></i>
                <input type="text" name="search" class="form-control border-0" placeholder="ค้นหา (ชื่อ, รหัส, ประเภท)..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="d-flex align-items-center gap-2">
                <select name="category" class="form-select border-0" style="background:#fcfdfe; box-shadow:inset 0 2px 5px rgba(0,0,0,0.02); height:42px; border-radius:30px; padding:0 35px 0 20px; font-weight:500;">
                    <option value="">ทุกประเภท</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['code'] ?>" <?= (($_GET['category']??'') === $cat['code']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['code'].' - '.$cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn text-white rounded-pill px-4 fw-medium" style="height:42px; background:linear-gradient(135deg,#11998e,#38ef7d); border:none; box-shadow:0 4px 10px rgba(17,153,142,0.2);">ค้นหา</button>
            </div>
            
            <div class="ms-auto d-flex gap-2">
                <?php if ($is_logged_in): ?>
                <button type="button" class="btn text-white rounded-pill px-4 fw-medium" style="height:42px; background:linear-gradient(135deg,#4e54c8,#8f94fb); border:none; box-shadow:0 4px 10px rgba(78,84,200,0.2); display:inline-flex; align-items:center;" onclick="openAuthModal('add_asset.php')"><i class='bx bx-plus me-1'></i> ขึ้นทะเบียนทรัพย์สิน</button>
                <?php endif; ?>
                <a href="print_qr.php" class="btn btn-light rounded-pill px-3" style="height:42px; font-size:14px; display:inline-flex; align-items:center; font-weight:600; color:#555; border:1px solid #eee;"><i class='bx bx-qr me-1 text-primary'></i> พิมพ์ QR</a>
                <a href="asset.php" class="btn btn-light rounded-pill px-3" style="height:42px; font-size:14px; display:inline-flex; align-items:center; border:1px solid #eee;"><i class='bx bx-refresh text-secondary'></i></a>
            </div>
        </div>
        </form>

        <!-- Table -->
        <div class="table-container-custom">
            <div class="card-header-custom">
                <ul class="nav asset-tabs" id="assetTab">
                    <li class="nav-item"><a class="nav-link <?= empty($_GET['status'])               ?'active':'' ?>" href="asset.php">ทั้งหมด (<?= $stats['total']     ?? 0 ?>)</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($_GET['status']??'')==='available'  ?'active':'' ?>" href="asset.php?status=available">พร้อมใช้งาน (<?= $stats['available'] ?? 0 ?>)</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($_GET['status']??'')==='inuse'      ?'active':'' ?>" href="asset.php?status=inuse">กำลังยืมใช้ (<?= $stats['inuse']     ?? 0 ?>)</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($_GET['status']??'')==='repair'     ?'active':'' ?>" href="asset.php?status=repair">ซ่อมบำรุง (<?= $stats['repair']     ?? 0 ?>)</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($_GET['status']??'')==='writeoff'   ?'active':'' ?>" href="asset.php?status=writeoff">จำหน่ายออก (<?= $stats['writeoff']   ?? 0 ?>)</a></li>
                </ul>
            </div>
            <div class="card-body-custom pt-3">
                <?php if (!empty($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show mx-3" role="alert">
                    <?= ['borrowed'=>'บันทึกการยืมสำเร็จ ✅','returned'=>'บันทึกการคืนสำเร็จ ✅','writeoff'=>'จำหน่ายออกสำเร็จ ✅','updated'=>'อัปเดตสถานะสำเร็จ ✅','deleted'=>'ลบข้อมูลสำเร็จ ✅'][$_GET['msg']] ?? 'สำเร็จ' ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th><th>รูป</th><th>รหัสทรัพย์สิน</th><th>ชื่อทรัพย์สิน</th>
                                <th>หมวดหมู่</th><th>วันที่ได้รับ</th><th>ราคา (บาท)</th>
                                <th>สถานที่ตั้ง</th><th>สถานะ</th><th class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($assets)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-5" style="background:#fcfdff;">
                                <div style="background: rgba(17,153,142,0.05); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class='bx bx-box' style="font-size:40px; color:#11998e; opacity: 0.8;"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">ไม่พบข้อมูลครุภัณฑ์</h6>
                                <small>ลองปรับตัวคัดกรอง หรือ <a href="#" class="text-primary text-decoration-none fw-medium" onclick="openAuthModal('add_asset.php');return false;">เพิ่มรายการแรก</a></small>
                            </td></tr>
                        <?php else: ?>
                        <?php foreach ($assets as $i => $a):
                            $sl = $statusLabel[$a['status']] ?? ['text'=>$a['status'],'class'=>'badge-available'];
                            $img = $a['image_path'] ? htmlspecialchars($a['image_path']) : null;
                        ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td>
                                <?php if ($img): ?>
                                    <img class="img-thumb" src="<?= $img ?>" alt="รูป" onclick="openLightbox('<?= $img ?>','<?= htmlspecialchars($a['name'],ENT_QUOTES) ?>')">
                                <?php else: ?>
                                    <span class="no-img" title="ไม่มีรูป"><i class='bx bx-image-alt'></i></span>
                                <?php endif; ?>
                                </td>
                                <td><code style="color:#4e54c8; background:rgba(78,84,200,0.05); padding:4px 8px; border-radius:6px; font-weight:600;"><?= htmlspecialchars($a['code']) ?></code></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($a['name']) ?></td>
                                <td><span class="badge" style="background:rgba(20,30,48,0.06); color:#444; font-weight:500; padding:5px 8px;"><?= htmlspecialchars($a['category_code'].' - '.($a['cat_name']??'')) ?></span></td>
                                <td><?= $a['receive_date'] ? date('d/m/Y', strtotime($a['receive_date'])) : '-' ?></td>
                                <td><?= number_format($a['cost'],2) ?></td>
                                <td><?= htmlspecialchars($a['location'] ?? '-') ?></td>
                                <td><span class="<?= $sl['class'] ?>"><?= $sl['text'] ?></span></td>
                                <td class="text-center">
                                    <a href="asset_detail.php?id=<?= $a['id'] ?>" class="btn-action bg-primary text-white me-1" title="ดูทะเบียนคุม"><i class='bx bx-show'></i></a>
                                    <?php if ($is_logged_in): ?>
                                    <a href="add_asset.php?id=<?= $a['id'] ?>"    class="btn-action bg-warning text-white me-1" title="แก้ไข"><i class='bx bx-edit'></i></a>
                                    <?php if ($a['status']==='inuse'): ?>
                                    <a href="return_asset.php?id=<?= $a['id'] ?>" class="btn-action bg-success text-white me-1" title="คืน"><i class='bx bx-log-in'></i></a>
                                    <?php elseif ($a['status']==='available'): ?>
                                    <a href="borrow_asset.php?id=<?= $a['id'] ?>" class="btn-action bg-info text-white me-1" title="ยืม"><i class='bx bx-log-out'></i></a>
                                    <?php endif; ?>
                                    <button class="btn-action" style="background:#f39c12;color:#fff;" title="จำหน่ายออก" onclick="confirmWriteOff(<?= $a['id'] ?>)"><i class='bx bx-archive-in'></i></button>
                                    <button class="btn-action bg-danger text-white ms-1" title="ลบข้อมูลถาวร" onclick="confirmDelete(<?= $a['id'] ?>)"><i class='bx bx-trash'></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
                            $styles = ($active) ? 'background: linear-gradient(135deg, #11998e, #38ef7d); color: #fff; border:none;' : '';
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

                <div class="d-flex justify-content-between align-items-center mt-3 px-1">
                    <small class="text-muted">แสดงทั้งหมด <?= $total_rows_filtered ?> รายการ</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:transparent;border:none;">
            <div class="modal-body p-2 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute" style="top:8px;right:8px;z-index:10;" data-bs-dismiss="modal"></button>
                <div style="font-size:13px;color:#fff;margin-bottom:8px;" id="lightboxCaption"></div>
                <img id="lightboxImg" src="" alt="">
            </div>
        </div>
    </div>
</div>

<!-- Auth Modal -->
<div class="modal fade" id="authModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.25);">
            <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);padding:28px 28px 20px;text-align:center;">
                <div style="width:56px;height:56px;background:linear-gradient(135deg,#4e54c8,#11998e);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;color:#fff;margin-bottom:12px;box-shadow:0 6px 18px rgba(78,84,200,.35);">
                    <i class='bx bx-lock-alt'></i>
                </div>
                <h6 style="color:#fff;font-weight:700;margin:0;font-size:16px;">ยืนยันตัวตน</h6>
                <p style="color:rgba(255,255,255,.5);font-size:12px;margin:4px 0 0;" id="authModalSub">กรุณาเข้าสู่ระบบเพื่อดำเนินการ</p>
            </div>
            <div style="padding:24px 28px;">
                <div id="authError" style="display:none;background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.35);border-radius:10px;padding:9px 13px;margin-bottom:14px;color:#c0392b;font-size:13px;display:flex;align-items:center;gap:7px;">
                    <i class='bx bx-error-circle'></i> <span id="authErrorMsg">ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง</span>
                </div>
                <div class="mb-3">
                    <label style="font-size:13px;color:#555;display:block;margin-bottom:6px;"><i class='bx bx-user' style="margin-right:4px;"></i>ชื่อผู้ใช้</label>
                    <input type="text" id="authUser" class="form-control" placeholder="กรอกชื่อผู้ใช้" autocomplete="username" style="border-radius:10px;font-size:14px;">
                </div>
                <div class="mb-4">
                    <label style="font-size:13px;color:#555;display:block;margin-bottom:6px;"><i class='bx bx-lock-alt' style="margin-right:4px;"></i>รหัสผ่าน</label>
                    <div style="position:relative;">
                        <input type="password" id="authPass" class="form-control" placeholder="กรอกรหัสผ่าน" autocomplete="current-password" style="border-radius:10px;font-size:14px;padding-right:42px;">
                        <button type="button" onclick="toggleAuthPw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#aaa;cursor:pointer;font-size:18px;padding:0;"><i class='bx bx-hide' id="authEye"></i></button>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light flex-fill" style="border-radius:10px;font-size:14px;" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success flex-fill fw-medium" id="authSubmitBtn" style="border-radius:10px;font-size:14px;background:linear-gradient(135deg,#11998e,#38ef7d);border:none;" onclick="submitAuth()"><i class='bx bx-check me-1'></i>ยืนยัน</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="frontend/assets/js/asset.js"></script>
<script>
const IS_LOGGED_IN = <?= json_encode(isset($_SESSION['inv_user_id'])) ?>;

function openLightbox(src, caption) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxCaption').textContent = caption;
    new bootstrap.Modal(document.getElementById('lightboxModal')).show();
}
function confirmWriteOff(id) {
    Swal.fire({ title:'ยืนยันการจำหน่ายออก?', text:'ไม่สามารถยกเลิกได้', icon:'warning',
        showCancelButton:true, confirmButtonColor:'#e74c3c', cancelButtonColor:'#aaa',
        confirmButtonText:'ใช่, จำหน่ายออก', cancelButtonText:'ยกเลิก'
    }).then(r => { if (r.isConfirmed) window.location='asset_action.php?action=writeoff&id='+id; });
}
function confirmDelete(id) {
    Swal.fire({ title:'ลบข้อมูลถาวร?', text:'ข้อมูลจะรวมถึงรูปภาพและประวัติการยืมจะถูกลบออกทั้งหมด ไม่สามารถกู้คืนได้!', icon:'error',
        showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#aaa',
        confirmButtonText:'ใช่, ลบทิ้งเลย', cancelButtonText:'ยกเลิก'
    }).then(r => { if (r.isConfirmed) openAuthModal('asset_action.php?action=delete&id='+id, 'กรุณายืนยันตัวตนเพื่อลบข้อมูลถาวร'); });
}

let _authRedirect = 'add_asset.php';
function openAuthModal(redirectUrl, subText) {
    _authRedirect = redirectUrl || 'add_asset.php';
    if (IS_LOGGED_IN) {
        window.location.href = _authRedirect;
        return;
    }
    document.getElementById('authModalSub').textContent = subText || 'กรุณาเข้าสู่ระบบเพื่อคนทะเบียนทรัพย์สิน';
    document.getElementById('authUser').value = '';
    document.getElementById('authPass').value = '';
    document.getElementById('authError').style.display = 'none';
    document.getElementById('authSubmitBtn').disabled = false;
    document.getElementById('authSubmitBtn').innerHTML = "<i class='bx bx-check me-1'></i>ยืนยัน";
    new bootstrap.Modal(document.getElementById('authModal')).show();
    setTimeout(() => document.getElementById('authUser').focus(), 400);
}
function toggleAuthPw() {
    const p = document.getElementById('authPass'), e = document.getElementById('authEye');
    p.type = p.type === 'password' ? 'text' : 'password';
    e.className = p.type === 'password' ? 'bx bx-hide' : 'bx bx-show';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && document.getElementById('authModal').classList.contains('show')) submitAuth();
});
function submitAuth() {
    const u = document.getElementById('authUser').value.trim();
    const p = document.getElementById('authPass').value.trim();
    const errBox = document.getElementById('authError');
    const btn = document.getElementById('authSubmitBtn');
    if (!u || !p) {
        document.getElementById('authErrorMsg').textContent = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        errBox.style.display = 'flex'; return;
    }
    btn.disabled = true;
    btn.innerHTML = "<span class='spinner-border spinner-border-sm me-1'></span>กำลังตรวจสอบ...";
    errBox.style.display = 'none';
    fetch('backend/verify_user.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({username: u, password: p})
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            btn.innerHTML = "<i class='bx bx-check-circle me-1'></i>สำเร็จ! กำลังเปิดหน้า...";
            btn.style.background = 'linear-gradient(135deg,#27ae60,#2ecc71)';
            setTimeout(() => { window.location.href = _authRedirect; }, 600);
        } else {
            document.getElementById('authErrorMsg').textContent = data.msg || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            errBox.style.display = 'flex';
            btn.disabled = false;
            btn.innerHTML = "<i class='bx bx-check me-1'></i>ยืนยัน";
            document.getElementById('authPass').value = '';
            document.getElementById('authPass').focus();
        }
    }).catch(() => {
        document.getElementById('authErrorMsg').textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        errBox.style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = "<i class='bx bx-check me-1'></i>ยืนยัน";
    });
}
</script>
<?php include 'frontend/includes/footer.php'; ?>
