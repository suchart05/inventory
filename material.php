<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

$is_logged_in = isset($_SESSION['inv_user_id']);

// ---- Stats ----
$stats = inv_row("SELECT COUNT(*) AS total, SUM(qty_in_stock <= min_stock AND qty_in_stock > 0) AS low_stock, SUM(qty_in_stock = 0) AS out_of_stock FROM materials");

// ---- Filters ----
$whereParts = [];
$params     = [];
if (!empty($_GET['search'])) {
    $s = '%' . $_GET['search'] . '%';
    $whereParts[] = "(code LIKE ? OR name LIKE ? OR category LIKE ?)";
    $params = array_merge($params, [$s, $s, $s]);
}

if (!empty($_GET['status'])) {
    if ($_GET['status'] === 'low') {
        $whereParts[] = "qty_in_stock <= min_stock AND qty_in_stock > 0";
    } elseif ($_GET['status'] === 'out') {
        $whereParts[] = "qty_in_stock = 0";
    } elseif ($_GET['status'] === 'normal') {
        $whereParts[] = "qty_in_stock > min_stock";
    }
}

$where  = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Pagination Logic
$items_per_page = 10;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$total_rows_query = inv_row("SELECT COUNT(*) AS total FROM materials $where", $params);
$total_rows_filtered = $total_rows_query['total'] ?? 0;
$total_pages = ceil($total_rows_filtered / $items_per_page);

$materials = inv_query("SELECT * FROM materials $where ORDER BY name ASC LIMIT $items_per_page OFFSET $offset", $params);
?>
<style>
/* --- Modern UI Styles --- */
.stat-info-card {
    background: #fff; border-radius: 20px; padding: 20px; height: 100%;
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02);
    position: relative; overflow: hidden;
}
.stat-info-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
.stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; transition: transform 0.3s;
}
.stat-info-card:hover .stat-icon { transform: scale(1.1); }
.icon-blue   { background: linear-gradient(135deg, rgba(78,84,200,0.1), rgba(143,148,251,0.1)); color: #4e54c8; }
.icon-orange { background: linear-gradient(135deg, rgba(247,151,30,0.1), rgba(255,210,0,0.1)); color: #f7971e; }
.icon-red    { background: linear-gradient(135deg, rgba(235,87,87,0.1), rgba(0,0,0,0.05)); color: #e74c3c; }

.stat-number { font-size: 24px; font-weight: 700; line-height: 1.2; letter-spacing: -0.5px; }
.stat-label { font-size: 13px; color: #777; font-weight: 500; }

.page-title-gradient { background: linear-gradient(135deg, #11998e, #38ef7d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

/* Barcode Scanner Section */
.barcode-section {
    background: #fff;
    border-radius: 20px;
    padding: 25px 30px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.02);
    margin-bottom: 25px;
    display: flex; align-items: center; gap: 20px;
}
.barcode-icon {
    width: 60px; height: 60px; border-radius: 16px;
    background: linear-gradient(135deg, #4e54c8, #8f94fb);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 30px; box-shadow: 0 8px 20px rgba(78,84,200,0.3);
}
.barcode-input-wrapper { flex: 1; position: relative; }
.barcode-input-wrapper input {
    width: 100%; height: 50px; font-size: 18px; font-weight: 600; font-family: 'Courier New', Courier, monospace;
    border-radius: 12px; border: 2px solid #eef0ff; padding-left: 50px;
    transition: all 0.3s;
}
.barcode-input-wrapper input:focus { border-color: #4e54c8; box-shadow: 0 0 0 4px rgba(78,84,200,0.1); outline: none; }
.barcode-input-wrapper .bx-barcode-reader { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 24px; color: #4e54c8; }

.table-container-custom {
    background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
    border-radius: 20px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.18); overflow: hidden;
}
.card-header-custom { background: #f8faff; border-bottom: 1px solid #edf2f9; padding: 15px 20px 0; }
.asset-tabs .nav-link { font-size: 14px; font-weight: 600; color: #888; border: none; padding: 12px 20px; border-radius: 12px 12px 0 0; transition: all 0.3s; }
.asset-tabs .nav-link:hover { color: #11998e; background: #eefdf5; }
.asset-tabs .nav-link.active { color: #fff; background: linear-gradient(135deg, #11998e, #38ef7d); box-shadow: 0 -4px 15px rgba(17,153,142,0.2); }

.table-hover tbody tr { transition: all 0.2s ease; cursor: pointer; }
.table-hover tbody tr:hover { background-color: #f8faff !important; transform: scale(1.002); box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: relative; z-index: 10; }
.table th { background-color: #f8faff !important; color: #555; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; border-bottom: 2px solid #edf2f9; }

.badge-normal { background: rgba(17,153,142,0.1); color: #11998e; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; }
.badge-low    { background: rgba(247,151,30,0.1); color: #f7971e; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; }
.badge-out    { background: rgba(235,87,87,0.1); color: #e74c3c; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; }

.btn-open-ledger {
    background: linear-gradient(135deg, #4e54c8, #8f94fb);
    color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 13px; font-weight: 500;
    transition: all 0.2s; text-decoration: none; display: inline-block;
}
.btn-open-ledger:hover { background: linear-gradient(135deg, #3a3fb8, #7a7fdb); box-shadow: 0 4px 10px rgba(78,84,200,0.2); color:white; }
</style>

<section class="home-section">
    <div class="main-content">
        <nav class="top-navbar mb-4" style="border-radius: 20px;">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-md-none" id="sidebarToggle" aria-label="Toggle Navigation">
                    <i class='bx bx-menu' style="font-size: 24px;"></i>
                </button>
                <div class="nav-title"><h4 class="mb-0 fw-bold page-title-gradient" style="font-size:26px;">บัญชีวัสดุ</h4><small class="text-muted" style="letter-spacing:0.5px;">Material Accounting</small></div>
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
        <!-- Dashboard Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-blue"><i class='bx bx-box'></i></div>
                    <div><div class="stat-number" style="color:#4e54c8;"><?= $stats['total'] ?? 0 ?></div><div class="stat-label">วัสดุทั้งหมด (รายการ)</div></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-orange"><i class='bx bx-error'></i></div>
                    <div><div class="stat-number" style="color:#f7971e;"><?= $stats['low_stock'] ?? 0 ?></div><div class="stat-label">วัสดุใกล้หมด</div></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-info-card d-flex align-items-center gap-3">
                    <div class="stat-icon icon-red"><i class='bx bx-minus-circle'></i></div>
                    <div><div class="stat-number" style="color:#e74c3c;"><?= $stats['out_of_stock'] ?? 0 ?></div><div class="stat-label">วัสดุหมดสต็อก</div></div>
                </div>
            </div>
        </div>


        <?php if ($is_logged_in): ?>
        <!-- Barcode Scanner -->
        <form id="barcodeForm" onsubmit="handleBarcodeScan(event)">
            <div class="barcode-section">
                <div class="barcode-icon"><i class='bx bx-barcode-reader'></i></div>
                <div class="barcode-input-wrapper d-flex gap-2">
                    <div style="flex:1; position:relative;">
                        <i class='bx bx-barcode-reader' style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 24px; color: #4e54c8;"></i>
                        <input type="text" id="barcodeInput" placeholder="สแกน หรือพิมพ์รหัสวัสดุ..." autocomplete="off" autofocus>
                    </div>
                    <button type="button" class="btn btn-light" onclick="openCameraScanner()" style="border-radius:12px; border:2px solid #eef0ff; width:50px; display:flex; align-items:center; justify-content:center; color:#4e54c8;" title="สแกนด้วยกล้องมือถือ/คอมพิวเตอร์">
                        <i class='bx bx-camera' style="font-size:24px;"></i>
                    </button>
                </div>
                <div>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-medium" style="height:50px; background:linear-gradient(135deg,#11998e,#38ef7d); border:none; box-shadow:0 4px 10px rgba(17,153,142,0.2);">ดึงบัญชีวัสดุ</button>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <!-- Camera Scanner Modal -->
        <div class="modal fade" id="scannerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
                    <div class="modal-header border-0 bg-light pb-2">
                        <h6 class="modal-title fw-bold text-primary"><i class='bx bx-camera me-2'></i>สแกนบาร์โค้ดผ่านกล้อง</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 text-center" style="background:#000;">
                        <div id="reader" style="width:100%; min-height:300px;"></div>
                    </div>
                    <div class="modal-footer border-0 justify-content-center bg-light pt-2 pb-3">
                        <small class="text-muted">นำกล้องส่องไปที่บาร์โค้ด ระบบจะสแกนอัตโนมัติ</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Material List -->
        <div class="table-container-custom">
            <div class="card-header-custom d-flex justify-content-between align-items-end pb-0">
                <ul class="nav asset-tabs">
                    <li class="nav-item"><a class="nav-link <?= empty($_GET['status']) ?'active':'' ?>" href="material.php">ทั้งหมด (<?= $stats['total'] ?? 0 ?>)</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($_GET['status']??'')==='low' ?'active':'' ?>" href="material.php?status=low">ใกล้หมด (<?= $stats['low_stock'] ?? 0 ?>)</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($_GET['status']??'')==='out' ?'active':'' ?>" href="material.php?status=out">หมดสต็อก (<?= $stats['out_of_stock'] ?? 0 ?>)</a></li>
                </ul>
                <div class="mb-2">
                    <a href="print_material_slip.php" target="_blank"
                       class="btn fw-bold px-3 rounded-pill"
                       style="height:36px; background:linear-gradient(135deg,#11998e,#38ef7d); border:none; color:#fff; font-size:13px; display:flex; align-items:center; gap:5px; box-shadow:0 3px 8px rgba(17,153,142,0.2); white-space:nowrap;">
                        <i class='bx bx-printer' style="font-size:16px;"></i> พิมพ์ใบเบิกพัสดุ
                    </a>
                </div>
                <form method="GET" action="material.php" class="mb-2">
                    <div class="input-group" style="width: 250px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 20px; overflow:hidden;">
                        <input type="text" name="search" class="form-control border-0" placeholder="ค้นหาชื่อ/รหัส..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button class="btn btn-light border-0" type="submit"><i class='bx bx-search text-primary'></i></button>
                    </div>
                </form>
            </div>
            <div class="card-body-custom pt-0 pb-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="padding-left: 20px;">รหัสวัสดุ (บาร์โค้ด)</th>
                                <th>ชื่อ/ชนิดวัสดุ</th>
                                <th>หมวดหมู่</th>
                                <th class="text-center">คงเหลือ</th>
                                <th>หน่วยนับ</th>
                                <th>สถานะ</th>
                                <th class="text-end" style="padding-right: 20px;">ใบลงบัญชี</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($materials)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5" style="background:#fcfdff;">
                                <div style="background: rgba(78,84,200,0.05); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class='bx bx-package text-primary' style="font-size:40px; opacity: 0.7;"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">ยังไม่มีรายการวัสดุ</h6>
                                <small>สแกนบาร์โค้ดด้านบนเพื่อเพิ่มวัสดุใหม่</small>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($materials as $m): 
                                $qty = (int)$m['qty_in_stock'];
                                $min = (int)$m['min_stock'];
                                if ($qty == 0) {
                                    $statusClass = 'badge-out'; $statusText = 'หมดสต็อก';
                                } elseif ($qty <= $min) {
                                    $statusClass = 'badge-low'; $statusText = 'ใกล้หมด';
                                } else {
                                    $statusClass = 'badge-normal'; $statusText = 'ปกติ';
                                }
                            ?>
                            <tr onclick="window.location.href='material_ledger.php?code=<?= urlencode($m['code']) ?>'">
                                <td style="padding-left: 20px;"><code style="color:#4e54c8; background:rgba(78,84,200,0.05); padding:4px 8px; border-radius:6px; font-weight:600;"><?= htmlspecialchars($m['code']) ?></code></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($m['name']) ?></td>
                                <td><span style="color:#666; font-size:13px;"><?= htmlspecialchars($m['category'] ?: '-') ?></span></td>
                                <td class="text-center fw-bold" style="font-size: 15px; color: <?= $qty==0 ? '#e74c3c' : ($qty<=$min ? '#f7971e' : '#27ae60') ?>;"><?= $qty ?></td>
                                <td style="color:#555;"><?= htmlspecialchars($m['unit'] ?: '-') ?></td>
                                <td><span class="<?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td class="text-end" style="padding-right: 20px;">
                                    <?php if ($is_logged_in): ?>
                                    <a href="material_ledger.php?code=<?= urlencode($m['code']) ?>" class="btn-open-ledger w-100 text-center">ดูบัญชี <i class='bx bx-chevron-right'></i></a>
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
                <nav aria-label="Page navigation" class="mt-4 pb-2">
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

                <div class="d-flex justify-content-between align-items-center mt-3 px-1 pb-2">
                    <small class="text-muted">แสดงทั้งหมด <?= $total_rows_filtered ?> รายการ</small>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- HTML5 QR Code library for Barcode scanning -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let html5QrcodeScanner = null;
const scannerModalEl = document.getElementById('scannerModal');
const scannerModal = new bootstrap.Modal(scannerModalEl);

function handleBarcodeScan(e) {
    if(e) e.preventDefault();
    const barcode = document.getElementById('barcodeInput').value.trim();
    if (!barcode) return;
    
    window.location.href = 'material_ledger.php?code=' + encodeURIComponent(barcode);
}

// Keep focus on barcode input if we click anywhere outside of a link/button/input
document.addEventListener('click', function(e) {
    // Only auto-focus if scanner modal is not open
    if (document.body.classList.contains('modal-open')) return;
    
    if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT' && !e.target.closest('#sidebarToggle')) {
        const scanner = document.getElementById('barcodeInput');
        if (scanner) scanner.focus();
    }
});

// ---- Mobile Sidebar Toggle ----
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation(); // prevent auto-focus trigger
            sidebar.classList.toggle('active');
        });
        
        // click outside to close sidebar on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(event.target) && event.target !== sidebarToggle) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
});

// ---- Camera Scanner Logic ----
function openCameraScanner() {
    scannerModal.show();
}

scannerModalEl.addEventListener('shown.bs.modal', function () {
    if (!html5QrcodeScanner) {
        html5QrcodeScanner = new Html5Qrcode("reader");
    }
    
    const config = { fps: 10, qrbox: { width: 250, height: 150 } };
    
    html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
    .catch((err) => {
        Swal.fire({
            icon: 'error',
            title: 'เข้าถึงกล้องไม่ได้',
            text: 'กรุณาอนุญาตให้ใช้งานกล้อง หรือตรวจอุปกรณ์ของคุณ'
        });
        scannerModal.hide();
    });
});

scannerModalEl.addEventListener('hidden.bs.modal', function () {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then((ignore) => {
            // QR Code scanning is stopped.
        }).catch((err) => {
            console.log("Stop failed: ", err);
        });
    }
    document.getElementById('barcodeInput').focus();
});

function onScanSuccess(decodedText, decodedResult) {
    // Stop scanning
    scannerModal.hide();
    
    // Play a gentle beep sound (optional feedback)
    const audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU'+Array(1e3).join(123));
    audio.play().catch(e => {});

    // Set input value
    const input = document.getElementById('barcodeInput');
    input.value = decodedText;
    
    // Auto submit form
    handleBarcodeScan();
}

function onScanFailure(error) {
    // handle scan failure, usually better to ignore and keep scanning
    // console.warn(`Code scan error = ${error}`);
}
</script>

<?php include 'frontend/includes/footer.php'; ?>
