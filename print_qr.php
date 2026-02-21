<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['inv_user_id']);
if (!$is_logged_in) { header('Location: index.php'); exit; }
require_once __DIR__ . '/backend/config/db_inventory.php';

include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

// Generate base URL dynamically
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
if ($dir === '/') $dir = '';
$base_url = "$protocol://$host$dir";

// ---- ดึงข้อมูลจริงจาก inventory_db ----
$assets = inv_query("SELECT id, code, name, COALESCE(location,'') AS location FROM assets WHERE status != 'writeoff' ORDER BY code");
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
/* ===== Page UI ===== */
.select-card {
    background:#fff; border-radius:14px;
    box-shadow:0 2px 12px rgba(0,0,0,0.07);
    padding:24px;
}
.asset-check-row {
    display:flex; align-items:center; gap:12px;
    padding:10px 14px; border-radius:10px;
    border:1px solid #eee; background:#fafafa;
    cursor:pointer; transition:background 0.15s;
    font-size:14px;
}
.asset-check-row:hover { background:#f0f4ff; border-color:#c5caff; }
.asset-check-row input[type=checkbox] { width:18px; height:18px; cursor:pointer; flex-shrink:0; }
.asset-check-row .asset-meta { flex:1; }
.asset-check-row .asset-meta .name { font-weight:600; color:#222; line-height:1.3; }
.asset-check-row .asset-meta .code { font-size:12px; color:#4e54c8; }
.asset-check-row .asset-meta .loc  { font-size:12px; color:#888; }

/* ===== Preview grid ===== */
#previewArea {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    padding: 16px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
}
.sticker-item {
    border: 1.5px dashed #bbb;
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    font-family: 'Kanit', sans-serif;
    background: #fff;
    display:flex; flex-direction:column; align-items:center;
}
.sticker-item img { width:100px; height:100px; display:block; }
.sticker-item .s-school { font-size:9px; color:#777; margin-bottom:2px; }
.sticker-item .s-name   { font-size:11px; font-weight:700; color:#111; line-height:1.3; margin:4px 0 2px; }
.sticker-item .s-code   { font-size:10px; color:#4e54c8; font-weight:600; }
.sticker-item .s-hint   { font-size:9px; color:#aaa; margin-top:2px; }

/* ===== PRINT: A4, 2 cols ===== */
@media print {
    /* ซ่อน UI ของหน้าเว็บปกติแบบไม่ให้กินพื้นที่ */
    body * { visibility: hidden !important; }
    body, html { background: #fff !important; margin: 0; padding: 0; }

    /* ตั้งค่าหน้ากระดาษ A4 และเว้นขอบกระดาษ 1 ซม. */
    @page {
        size: A4;
        margin: 10mm; 
    }

    /* ดึงเฉพาะส่วนที่จะพิมพ์ขึ้นมาแสดง */
    #printSheet, #printSheet * { visibility: visible !important; }
    
    #printSheet {
        position: absolute; /* เปลี่ยนจาก fixed เป็น absolute เพื่อให้ไหลขึ้นหน้าใหม่ได้ */
        left: 0; 
        top: 0;
        width: 100%; /* ใช้ 100% แทน 210mm เพื่อไม่ให้ล้นระยะ margin ของเครื่องปริ้น */
        display: grid !important;
        grid-template-columns: repeat(2, 1fr);
        align-content: start;
        gap: 5mm; /* ลดช่องว่างลงเล็กน้อย */
        margin: 0; 
        padding: 0;
    }

    .sticker-item {
        border: 1px dashed #aaa;
        border-radius: 6px;
        padding: 5mm;
        page-break-inside: avoid; /* สำคัญ! ป้องกันไม่ให้สติกเกอร์โดนหั่นครึ่งเมื่อข้ามหน้าใหม่ */
        break-inside: avoid;
        display: flex !important; 
        flex-direction: column; 
        align-items: center;
        box-sizing: border-box;
    }

    /* ปรับขนาดรูปและฟอนต์ให้สมดุลกับพื้นที่ */
    .sticker-item img { width: 45mm; height: 45mm; }
    .sticker-item .s-school { font-size: 8pt; margin-bottom: 2px; }
    .sticker-item .s-name   { font-size: 10pt; font-weight: 700; margin: 4px 0 2px; line-height: 1.2; text-align: center; }
    .sticker-item .s-code   { font-size: 9pt; }
    .sticker-item .s-hint   { font-size: 7pt; margin-top: 2px; }
    
    .no-print { display: none !important; }
}
</style>

<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title">
            <h4 class="mb-0 fw-bold text-dark">พิมพ์สติกเกอร์ QR</h4>
            <small class="text-muted">Batch QR Sticker Printing</small>
        </div>
        <div class="user-profile d-flex align-items-center">
            <i class='bx bx-user-circle' style='font-size:32px; color:#4e54c8; margin-right:10px;'></i>
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
        <div class="row g-4">

            <!-- ===== LEFT: Asset selector ===== -->
            <div class="col-lg-5 no-print">
                <div class="select-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class='bx bx-check-square me-2'></i>เลือกครุภัณฑ์</h6>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="selectAll()">เลือกทั้งหมด</button>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="clearAll()">ล้าง</button>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="position-relative mb-3">
                        <i class='bx bx-search' style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#aaa; font-size:18px;"></i>
                        <input type="text" id="assetSearch" class="form-control ps-5"
                               placeholder="ค้นหาชื่อ / รหัสครุภัณฑ์..."
                               oninput="filterAssets(this.value)">
                    </div>

                    <!-- Asset list -->
                    <div id="assetList" style="max-height:460px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
                        <?php foreach ($assets as $a): ?>
                        <label class="asset-check-row" id="row-<?= $a['id'] ?>">
                            <input type="checkbox" class="asset-cb" value="<?= $a['id'] ?>"
                                   data-name="<?= htmlspecialchars($a['name']) ?>"
                                   data-code="<?= htmlspecialchars($a['code']) ?>"
                                   data-loc="<?= htmlspecialchars($a['location']) ?>"
                                   onchange="updatePreview()">
                            <div class="asset-meta">
                                <div class="name"><?= htmlspecialchars($a['name']) ?></div>
                                <div class="code"><?= htmlspecialchars($a['code']) ?></div>
                                <div class="loc"><i class='bx bx-map-pin'></i> <?= htmlspecialchars($a['location']) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <small id="selectedCount" class="text-muted">เลือก 0 รายการ</small>
                        <button class="btn btn-success rounded-pill px-4 fw-medium"
                                onclick="doPrint()" id="printBtn" disabled>
                            <i class='bx bx-printer me-1'></i>พิมพ์สติกเกอร์
                        </button>
                    </div>
                </div>
            </div>

            <!-- ===== RIGHT: Preview ===== -->
            <div class="col-lg-7">
                <div class="d-flex justify-content-between align-items-center mb-2 no-print">
                    <h6 class="fw-bold mb-0"><i class='bx bx-grid-alt me-2'></i>ตัวอย่างก่อนพิมพ์ (A4 — 2×4 ดวง/หน้า)</h6>
                </div>

                <!-- Preview grid (on-screen: 4 cols, print: 2 cols) -->
                <div id="previewArea">
                    <div id="emptyHint" style="grid-column:1/-1; text-align:center; padding:48px; color:#bbb;">
                        <i class='bx bx-qr' style="font-size:48px; display:block; margin-bottom:8px;"></i>
                        เลือกครุภัณฑ์ด้านซ้ายเพื่อดูตัวอย่าง QR สติกเกอร์
                    </div>
                </div>

                <!-- Hidden A4 print sheet (2 cols) -->
                <div id="printSheet" style="display:none;"></div>
            </div>

        </div><!-- row -->
    </div><!-- main-content -->
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const BASE_URL  = '<?= $base_url ?>';
    const SCHOOL    = 'โรงเรียนบ้านโคกวิทยา';
    const QR_SIZE_PREVIEW = 100;
    const QR_SIZE_PRINT   = 280;

    function getQrUrl(assetId, size) {
        const scanUrl = BASE_URL + '/scan_asset.php?id=' + assetId;
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + 'x' + size + '&ecc=M&data=' + encodeURIComponent(scanUrl);
    }

    function makeStickerHTML(cb, size) {
        const id   = cb.value;
        const name = cb.dataset.name;
        const code = cb.dataset.code;
        return `<div class="sticker-item">
            <div class="s-school">🏫 ${SCHOOL}</div>
            <img src="${getQrUrl(id, size)}" alt="QR ${code}" crossorigin="anonymous">
            <div class="s-name">${name}</div>
            <div class="s-code">${code}</div>
            <div class="s-hint">สแกน QR เพื่อดูข้อมูล</div>
        </div>`;
    }

    function updatePreview() {
        const checked = [...document.querySelectorAll('.asset-cb:checked')];
        const area    = document.getElementById('previewArea');
        const hint    = document.getElementById('emptyHint');
        const printBtn = document.getElementById('printBtn');
        const count   = document.getElementById('selectedCount');

        count.textContent = 'เลือก ' + checked.length + ' รายการ';
        printBtn.disabled = checked.length === 0;

        // Clear preview
        area.innerHTML = '';
        if (checked.length === 0) {
            area.innerHTML = `<div id="emptyHint" style="grid-column:1/-1; text-align:center; padding:48px; color:#bbb;">
                <i class='bx bx-qr' style="font-size:48px; display:block; margin-bottom:8px;"></i>
                เลือกครุภัณฑ์ด้านซ้ายเพื่อดูตัวอย่าง QR สติกเกอร์
            </div>`;
            return;
        }

        checked.forEach(cb => {
            area.insertAdjacentHTML('beforeend', makeStickerHTML(cb, QR_SIZE_PREVIEW));
        });
    }

    function doPrint() {
        const checked = [...document.querySelectorAll('.asset-cb:checked')];
        if (!checked.length) return;

        const sheet = document.getElementById('printSheet');
        sheet.innerHTML = checked.map(cb => makeStickerHTML(cb, QR_SIZE_PRINT)).join('');

        // Make sheet visible temporarily so images can load (hidden elements block image loading)
        sheet.style.display = 'block';

        const imgs = [...sheet.querySelectorAll('img')];
        if (!imgs.length) { window.print(); return; }

        let loaded = 0;
        let printed = false;
        const doPrintNow = () => { 
    if (!printed) { 
        printed = true; 
        window.print(); 
        sheet.style.display = 'none'; // เพิ่มบรรทัดนี้เพื่อซ่อนแผ่นพิมพ์หลังสั่งพิมพ์เสร็จ
    } 
};
        const tryPrint  = () => { if (++loaded >= imgs.length) doPrintNow(); };

        // Timeout fallback: print after 4 seconds even if some images fail
        const timeout = setTimeout(doPrintNow, 4000);

        imgs.forEach(img => {
            if (img.complete && img.naturalWidth > 0) {
                tryPrint();
            } else {
                img.onload  = tryPrint;
                img.onerror = tryPrint;
            }
        });
    }

    function selectAll() {
        document.querySelectorAll('.asset-cb').forEach(cb => cb.checked = true);
        updatePreview();
    }

    function clearAll() {
        document.querySelectorAll('.asset-cb').forEach(cb => cb.checked = false);
        updatePreview();
    }

    function filterAssets(q) {
        q = q.toLowerCase();
        document.querySelectorAll('.asset-check-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    }
</script>

<?php include 'frontend/includes/footer.php'; ?>
