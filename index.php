<?php
/**
 * index.php — หน้าหลัก Dashboard (เปิดให้ทุกคนเข้าถึงได้)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/backend/config/db_inventory.php';

// ---- Stats ----
$aStats  = inv_row("SELECT COUNT(*) AS total, SUM(status='available') AS avail, SUM(status='inuse') AS inuse, SUM(status='repair') AS repair, SUM(status='writeoff') AS writeoff FROM assets");
$bCount  = inv_row("SELECT COUNT(*) AS cnt FROM borrow_records WHERE status='borrowing'");
$pCount  = inv_row("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM procurement_orders WHERE fiscal_year=?", [(int)(date('Y')+543)]);

$stats = [
    'assets'   => (int)($aStats['total']   ?? 0),
    'avail'    => (int)($aStats['avail']   ?? 0),
    'inuse'    => (int)($aStats['inuse']   ?? 0),
    'repair'   => (int)($aStats['repair']  ?? 0),
    'writeoff' => (int)($aStats['writeoff']?? 0),
    'borrow'   => (int)($bCount['cnt']     ?? 0),
    'procure'  => (int)($pCount['cnt']     ?? 0),
    'budget'   => (float)($pCount['total'] ?? 0),
];

$is_logged_in = isset($_SESSION['inv_user_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['inv_fullname'] ?? 'ผู้ใช้') : 'คนทั่วไป';
$user_role    = $is_logged_in ? htmlspecialchars($_SESSION['inv_role'] ?? '')            : 'สาธารณะ';

// Thai date
$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$today    = date('j') . ' ' . $thMonths[(int)date('n')] . ' ' . (date('Y')+543);
$time     = date('H:i');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ระบบบริหารพัสดุ — โรงเรียนบ้านโคกวิทยา</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
/* ============================================================
   RESET & BASE
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Kanit', sans-serif;
    min-height: 100vh;
    background: #0a0e1a;
    color: #e0e6ff;
    overflow-x: hidden;
}

/* ============================================================
   ANIMATED BACKGROUND
   ============================================================ */
.bg-canvas {
    position: fixed; inset: 0; z-index: 0;
    background: radial-gradient(ellipse 80% 80% at 20% -20%, rgba(99,102,241,.35) 0%, transparent 60%),
                radial-gradient(ellipse 60% 60% at 80% 100%, rgba(16,185,129,.25) 0%, transparent 55%),
                radial-gradient(ellipse 70% 40% at 50% 50%, rgba(236,72,153,.1) 0%, transparent 60%),
                linear-gradient(180deg, #0a0e1a 0%, #0d1229 100%);
}
.orb {
    position: fixed; border-radius: 50%; filter: blur(80px); pointer-events: none;
    animation: orbFloat 8s ease-in-out infinite alternate;
}
.orb1 { width:500px; height:500px; top:-150px; left:-100px; background:rgba(99,102,241,.18); animation-delay:0s; }
.orb2 { width:400px; height:400px; top:30%; right:-100px; background:rgba(16,185,129,.15); animation-delay:-3s; }
.orb3 { width:350px; height:350px; bottom:-100px; left:30%; background:rgba(236,72,153,.12); animation-delay:-6s; }
@keyframes orbFloat {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(30px,40px) scale(1.08); }
}

/* ============================================================
   LAYOUT
   ============================================================ */
.page-wrapper {
    position: relative; z-index: 1;
    min-height: 100vh;
    display: flex; flex-direction: column;
}

/* ============================================================
   TOP NAV
   ============================================================ */
.topnav {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 32px;
    background: rgba(255,255,255,.03);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.brand-wrap { display:flex; align-items:center; gap:12px; }
.brand-icon {
    width:44px; height:44px; border-radius:12px;
    background: linear-gradient(135deg,#6366f1,#10b981);
    display:flex; align-items:center; justify-content:center;
    font-size:22px; color:#fff;
    box-shadow: 0 0 20px rgba(99,102,241,.5);
}
.brand-name { font-size:16px; font-weight:700; color:#fff; }
.brand-sub  { font-size:11px; color:rgba(255,255,255,.4); margin-top:1px; }

.nav-right { display:flex; align-items:center; gap:14px; }
.time-pill {
    background: rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
    border-radius:20px; padding:6px 14px;
    font-size:12px; color:rgba(255,255,255,.6);
    display:flex; align-items:center; gap:6px;
}
.time-pill i { color:#6366f1; }
.user-pill {
    display:flex; align-items:center; gap:8px;
    background: rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
    border-radius:20px; padding:5px 14px 5px 6px;
}
.user-avatar {
    width:30px; height:30px; border-radius:50%;
    background: linear-gradient(135deg,#6366f1,#ec4899);
    display:flex; align-items:center; justify-content:center;
    font-size:13px; color:#fff; font-weight:700;
}
.user-pill .name { font-size:13px; font-weight:600; color:#fff; }
.user-pill .role { font-size:10px; color:rgba(255,255,255,.4); }
.btn-action {
    display:flex; align-items:center; gap:6px;
    background: linear-gradient(135deg,#6366f1,#8b5cf6);
    color:#fff; border:none; border-radius:20px;
    padding:8px 18px; font-family:'Kanit',sans-serif; font-size:13px;
    font-weight:600; cursor:pointer; text-decoration:none;
    transition:all .2s;
    box-shadow: 0 0 20px rgba(99,102,241,.4);
}
.btn-action:hover { transform:translateY(-2px); box-shadow:0 6px 24px rgba(99,102,241,.6); color:#fff; }

/* ============================================================
   HERO SECTION
   ============================================================ */
.hero {
    text-align:center; padding: 56px 20px 40px;
}
.hero-badge {
    display:inline-flex; align-items:center; gap:6px;
    background: rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.3);
    border-radius:20px; padding:5px 14px; font-size:12px;
    color:#a5b4fc; margin-bottom:20px;
}
.hero-badge span { width:6px; height:6px; border-radius:50%; background:#6366f1; animation:pulse 2s infinite; display:inline-block; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }
.hero h1 {
    font-size: clamp(28px,5vw,52px);
    font-weight: 800;
    background: linear-gradient(135deg,#fff 30%,#a5b4fc 70%,#34d399 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.15; margin-bottom: 12px;
}
.hero p {
    font-size: 15px; color: rgba(255,255,255,.5);
    max-width: 480px; margin: 0 auto 0;
    line-height: 1.7;
}

/* ============================================================
   STAT CARDS
   ============================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px,1fr));
    gap: 14px;
    max-width: 1100px; margin: 0 auto; padding: 0 24px 40px;
}
.stat-card {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px; padding: 18px 16px;
    display:flex; align-items:center; gap:14px;
    transition: all .3s; cursor:default;
    animation: fadeUp .6s ease both;
}
.stat-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
    box-shadow: 0 8px 32px rgba(0,0,0,.3), 0 0 0 1px var(--accent) inset;
    background: rgba(255,255,255,.08);
}
.stat-icon {
    width:48px; height:48px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:24px; background: rgba(255,255,255,.07);
}
.stat-num  { font-size:26px; font-weight:800; line-height:1; }
.stat-lbl  { font-size:12px; color:rgba(255,255,255,.5); margin-top:3px; }

/* ============================================================
   MODULE CARDS
   ============================================================ */
.modules-grid {
    display:grid; grid-template-columns:repeat(auto-fit, minmax(300px,1fr));
    gap:20px; max-width:1100px; margin:0 auto; padding:0 24px 60px;
}
.mod-card {
    position:relative; overflow:hidden;
    border-radius:24px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    transition: all .3s;
    animation: fadeUp .7s ease both;
}
.mod-card::before {
    content:''; position:absolute; inset:0; opacity:0; transition:opacity .3s;
    background: var(--card-glow);
    border-radius:24px;
}
.mod-card:hover { transform:translateY(-8px); border-color:rgba(255,255,255,.18); }
.mod-card:hover::before { opacity:1; }

.mod-body { padding:32px 28px 24px; position:relative; z-index:1; }
.mod-icon-wrap {
    width:68px; height:68px; border-radius:18px;
    display:flex; align-items:center; justify-content:center;
    font-size:32px; margin-bottom:20px;
    background: var(--icon-bg);
    box-shadow: 0 8px 24px rgba(0,0,0,.3), 0 0 0 1px rgba(255,255,255,.1) inset;
    transition: transform .3s;
}
.mod-card:hover .mod-icon-wrap { transform: rotate(-8deg) scale(1.1); }
.mod-title { font-size:19px; font-weight:700; color:#fff; margin-bottom:8px; }
.mod-desc  { font-size:13px; color:rgba(255,255,255,.5); line-height:1.7; margin-bottom:20px; }

.mod-stats { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px; }
.mod-stat-pill {
    background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
    border-radius:20px; padding:4px 12px; font-size:12px;
    display:flex; align-items:center; gap:5px;
}
.mod-stat-pill b { color:#fff; }

.mod-btn {
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; padding:13px;
    background: var(--btn-grad);
    color:#fff; font-family:'Kanit',sans-serif; font-size:14px;
    font-weight:600; border:none; border-radius:14px;
    text-decoration:none; cursor:pointer;
    transition:all .25s;
    box-shadow: var(--btn-shadow);
    position:relative; overflow:hidden;
}
.mod-btn::after {
    content:''; position:absolute; inset:0;
    background:rgba(255,255,255,.1);
    opacity:0; transition:opacity .2s;
}
.mod-btn:hover { color:#fff; transform:translateY(-2px); }
.mod-btn:hover::after { opacity:1; }
.mod-btn i { font-size:18px; }

/* Ribbon badge on card */
.mod-ribbon {
    position:absolute; top:16px; right:16px;
    background:var(--ribbon); border-radius:10px;
    padding:3px 10px; font-size:11px; font-weight:700; color:#fff;
    z-index:2;
}

/* ============================================================
   FOOTER
   ============================================================ */
.footer {
    text-align:center; padding:24px;
    color:rgba(255,255,255,.2); font-size:12px;
    border-top:1px solid rgba(255,255,255,.05);
    margin-top:auto;
}

/* ============================================================
   ANIMATIONS
   ============================================================ */
@keyframes fadeUp {
    from { opacity:0; transform:translateY(30px); }
    to   { opacity:1; transform:translateY(0); }
}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.1s}
.stat-card:nth-child(3){animation-delay:.15s}
.stat-card:nth-child(4){animation-delay:.2s}
.stat-card:nth-child(5){animation-delay:.25s}
.stat-card:nth-child(6){animation-delay:.3s}
.mod-card:nth-child(1){animation-delay:.2s}
.mod-card:nth-child(2){animation-delay:.3s}
.mod-card:nth-child(3){animation-delay:.4s}

/* Counter animation */
.count-up { display:inline-block; }

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media(max-width:640px){
    .topnav { padding:12px 16px; }
    .hero { padding: 36px 16px 28px; }
    .stats-grid { padding:0 16px 28px; gap:10px; }
    .modules-grid { padding:0 16px 40px; }
    .time-pill, .brand-sub { display:none; }
}
</style>
</head>
<body>
<div class="bg-canvas"></div>
<div class="orb orb1"></div>
<div class="orb orb2"></div>
<div class="orb orb3"></div>

<div class="page-wrapper">

<!-- ===== TOP NAV ===== -->
<nav class="topnav">
    <div class="brand-wrap">
        <div class="brand-icon"><i class='bx bx-package'></i></div>
        <div>
            <div class="brand-name">ระบบบริหารพัสดุ</div>
            <div class="brand-sub">โรงเรียนบ้านโคกวิทยา</div>
        </div>
    </div>
    <div class="nav-right">
        <div class="time-pill">
            <i class='bx bx-calendar'></i>
            <?= $today ?> &nbsp;|&nbsp; <i class='bx bx-time' style="color:#10b981;"></i> <?= $time ?>
        </div>
        <div class="user-pill">
            <div class="user-avatar"><?= mb_substr($user_name,0,1) ?></div>
            <div>
                <div class="name"><?= $user_name ?></div>
                <div class="role"><?= $user_role ?></div>
            </div>
        </div>
        <?php if ($is_logged_in): ?>
        <a href="logout.php" class="btn-action" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
            <i class='bx bx-log-out'></i>ออกจากระบบ
        </a>
        <?php else: ?>
        <a href="login.php" class="btn-action">
            <i class='bx bx-log-in'></i>เข้าสู่ระบบ
        </a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== HERO ===== -->
<div class="hero">
    <div class="hero-badge"><span></span> School Inventory Management System</div>
    <h1>ระบบบริหารพัสดุ<br>โรงเรียนบ้านโคกวิทยา</h1>
    <p>จัดการครุภัณฑ์ วัสดุสิ้นเปลือง และการจัดซื้อจัดจ้าง<br>อย่างเป็นระบบ โปร่งใส และมีประสิทธิภาพ</p>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="stats-grid">
    <div class="stat-card" style="--accent:rgba(99,102,241,.5);">
        <div class="stat-icon" style="color:#818cf8;"><i class='bx bx-box'></i></div>
        <div>
            <div class="stat-num" style="color:#818cf8;">
                <span class="count-up" data-target="<?= $stats['assets'] ?>">0</span>
            </div>
            <div class="stat-lbl">ครุภัณฑ์ทั้งหมด</div>
        </div>
    </div>
    <div class="stat-card" style="--accent:rgba(16,185,129,.5);">
        <div class="stat-icon" style="color:#34d399;"><i class='bx bx-check-shield'></i></div>
        <div>
            <div class="stat-num" style="color:#34d399;">
                <span class="count-up" data-target="<?= $stats['avail'] ?>">0</span>
            </div>
            <div class="stat-lbl">พร้อมใช้งาน</div>
        </div>
    </div>
    <div class="stat-card" style="--accent:rgba(59,130,246,.5);">
        <div class="stat-icon" style="color:#60a5fa;"><i class='bx bx-user-check'></i></div>
        <div>
            <div class="stat-num" style="color:#60a5fa;">
                <span class="count-up" data-target="<?= $stats['borrow'] ?>">0</span>
            </div>
            <div class="stat-lbl">กำลังยืมใช้</div>
        </div>
    </div>
    <div class="stat-card" style="--accent:rgba(245,158,11,.5);">
        <div class="stat-icon" style="color:#fbbf24;"><i class='bx bx-wrench'></i></div>
        <div>
            <div class="stat-num" style="color:#fbbf24;">
                <span class="count-up" data-target="<?= $stats['repair'] ?>">0</span>
            </div>
            <div class="stat-lbl">ส่งซ่อม</div>
        </div>
    </div>
    <div class="stat-card" style="--accent:rgba(236,72,153,.5);">
        <div class="stat-icon" style="color:#f472b6;"><i class='bx bx-cart-alt'></i></div>
        <div>
            <div class="stat-num" style="color:#f472b6;">
                <span class="count-up" data-target="<?= $stats['procure'] ?>">0</span>
            </div>
            <div class="stat-lbl">จัดซื้อ/จ้าง ปี 2569</div>
        </div>
    </div>
    <div class="stat-card" style="--accent:rgba(20,184,166,.5);">
        <div class="stat-icon" style="color:#2dd4bf;"><i class='bx bx-money'></i></div>
        <div>
            <div class="stat-num" style="color:#2dd4bf; font-size:18px;">
                ฿<?= number_format($stats['budget'],0) ?>
            </div>
            <div class="stat-lbl">วงเงินจัดซื้อ ปี 2569</div>
        </div>
    </div>
</div>

<!-- ===== MODULE CARDS ===== -->
<div class="modules-grid">

    <!-- Procurement -->
    <div class="mod-card"
         style="--card-glow:linear-gradient(135deg,rgba(59,130,246,.12),transparent);
                --icon-bg:linear-gradient(135deg,#1d4ed8,#3b82f6);
                --btn-grad:linear-gradient(135deg,#2563eb,#3b82f6);
                --btn-shadow:0 8px 24px rgba(37,99,235,.4);
                --ribbon:#1d4ed8;">
        <div class="mod-ribbon">จัดซื้อ/จัดจ้าง</div>
        <div class="mod-body">
            <div class="mod-icon-wrap"><i class='bx bx-cart-add' style="color:#fff;"></i></div>
            <div class="mod-title">ควบคุมการจัดซื้อจัดจ้าง</div>
            <div class="mod-desc">ทะเบียนคุมเลขที่จัดซื้อ บันทึกรายการพัสดุ ตรวจสอบสถานะ และพิมพ์รายงาน</div>
            <div class="mod-stats">
                <div class="mod-stat-pill"><i class='bx bx-file' style="color:#60a5fa;"></i><b><?= $stats['procure'] ?></b> รายการ</div>
                <div class="mod-stat-pill"><i class='bx bx-money' style="color:#34d399;"></i><b>฿<?= number_format($stats['budget'],0) ?></b></div>
            </div>
            <a href="procurement.php" class="mod-btn">
                <i class='bx bx-right-arrow-circle'></i>เข้าสู่ระบบจัดซื้อ
            </a>
        </div>
    </div>

    <!-- Asset Control -->
    <div class="mod-card"
         style="--card-glow:linear-gradient(135deg,rgba(16,185,129,.12),transparent);
                --icon-bg:linear-gradient(135deg,#059669,#10b981);
                --btn-grad:linear-gradient(135deg,#059669,#34d399);
                --btn-shadow:0 8px 24px rgba(5,150,105,.4);
                --ribbon:#059669;">
        <div class="mod-ribbon">ครุภัณฑ์</div>
        <div class="mod-body">
            <div class="mod-icon-wrap"><i class='bx bx-package' style="color:#fff;"></i></div>
            <div class="mod-title">ควบคุมทรัพย์สิน</div>
            <div class="mod-desc">ขึ้นทะเบียนครุภัณฑ์ ยืม-คืน ตรวจสอบสถานะ สแกน QR Code และประเมินค่าเสื่อมราคา</div>
            <div class="mod-stats">
                <div class="mod-stat-pill"><i class='bx bx-box' style="color:#34d399;"></i><b><?= $stats['assets'] ?></b> รายการ</div>
                <div class="mod-stat-pill"><i class='bx bx-check-circle' style="color:#34d399;"></i><b><?= $stats['avail'] ?></b> พร้อมใช้</div>
                <div class="mod-stat-pill"><i class='bx bx-user-check' style="color:#60a5fa;"></i><b><?= $stats['borrow'] ?></b> ยืม</div>
            </div>
            <a href="asset.php" class="mod-btn">
                <i class='bx bx-right-arrow-circle'></i>เข้าสู่ระบบทรัพย์สิน
            </a>
        </div>
    </div>

    <!-- Materials -->
    <div class="mod-card"
         style="--card-glow:linear-gradient(135deg,rgba(245,158,11,.12),transparent);
                --icon-bg:linear-gradient(135deg,#d97706,#f59e0b);
                --btn-grad:linear-gradient(135deg,#d97706,#fbbf24);
                --btn-shadow:0 8px 24px rgba(217,119,6,.4);
                --ribbon:#d97706;">
        <div class="mod-ribbon">วัสดุ</div>
        <div class="mod-body">
            <div class="mod-icon-wrap"><i class='bx bx-spreadsheet' style="color:#fff;"></i></div>
            <div class="mod-title">บัญชีวัสดุสิ้นเปลือง</div>
            <div class="mod-desc">ตรวจสอบยอดคงเหลือ บันทึกการเบิก-จ่าย รายงานสรุปการใช้วัสดุ และวางแผนการจัดซื้อ</div>
            <div class="mod-stats">
                <div class="mod-stat-pill"><i class='bx bx-clipboard' style="color:#fbbf24;"></i>บันทึกการเบิก</div>
                <div class="mod-stat-pill"><i class='bx bx-bar-chart-alt' style="color:#fbbf24;"></i>รายงานสรุป</div>
            </div>
            <a href="material.php" class="mod-btn">
                <i class='bx bx-right-arrow-circle'></i>เข้าสู่ระบบวัสดุ
            </a>
        </div>
    </div>

</div><!-- end modules-grid -->

<!-- ===== FOOTER ===== -->
<div class="footer">
    ระบบบริหารพัสดุ &nbsp;|&nbsp; โรงเรียนบ้านโคกวิทยา &nbsp;|&nbsp;
    สำนักงานเขตพื้นที่การศึกษาประถมศึกษาศรีสะเกษ เขต 4
</div>

</div><!-- end page-wrapper -->

<script>
// ===== Counter Animation =====
function animateCount(el) {
    const target = +el.dataset.target;
    const duration = 1200;
    const start = performance.now();
    function update(now) {
        const t = Math.min((now - start) / duration, 1);
        const ease = 1 - Math.pow(1 - t, 3);
        el.textContent = Math.round(target * ease).toLocaleString();
        if (t < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
}

// Trigger when visible
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            animateCount(e.target);
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => observer.observe(el));
</script>
</body>
</html>
