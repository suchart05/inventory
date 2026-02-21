<?php
require_once __DIR__ . '/backend/config/db_inventory.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo "<div style='padding:20px; text-align:center;'><h3>ไม่พบรหัสทรัพย์สิน</h3><a href='index.php'>กลับหน้าหลัก</a></div>";
    exit;
}

$a = inv_row("SELECT a.*, ac.name AS cat_name FROM assets a LEFT JOIN asset_categories ac ON a.category_code=ac.code WHERE a.id=?", [$id]);

if (!$a) {
    echo "<div style='padding:20px; text-align:center;'><h3>ไม่พบข้อมูลทรัพย์สินรหัสนี้</h3><a href='index.php'>กลับหน้าหลัก</a></div>";
    exit;
}

$statusText = [
    'available' => 'พร้อมใช้งาน',
    'inuse'     => 'กำลังยืมใช้',
    'repair'    => 'ซ่อมบำรุง',
    'writeoff'  => 'จำหน่ายออก',
];

$asset = [
    'id'          => $a['id'],
    'code'        => $a['code'],
    'name'        => $a['name'],
    'category'    => ($a['category_code'] ?? '') . ' - ' . ($a['cat_name'] ?? ''),
    'model'       => $a['model'] ?? '-',
    'location'    => $a['location'] ?? '-',
    'custodian'   => $a['custodian'] ?? '-',
    'receive_date'=> $a['receive_date'] ? date('d/m/Y', strtotime($a['receive_date'])) : '-',
    'cost'        => number_format($a['cost'], 2),
    'status'      => $a['status'],
    'status_text' => $statusText[$a['status']] ?? $a['status'],
];

// Generate QR URL dynamically based on current host
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
if ($dir === '/') $dir = '';
$current_base_url = "$protocol://$host$dir";

$scan_url = $current_base_url . '/scan_asset.php?id=' . $asset['id'];
$qr_api   = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($scan_url);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลทรัพย์สิน | <?= htmlspecialchars($asset['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Kanit', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
        }

        /* ===== Header ===== */
        .scan-header {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: #fff;
            text-align: center;
            padding: 24px 20px 48px;
            position: relative;
        }
        .scan-header .school-name {
            font-size: 13px;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .scan-header h1 {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.3;
        }
        .scan-header .asset-code-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 20px;
            padding: 3px 14px;
            font-size: 13px;
            margin-top: 8px;
            letter-spacing: 0.5px;
        }

        /* ===== Status pill ===== */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-available { background: #e8f5e9; color: #2e7d32; }
        .status-inuse     { background: #e3f2fd; color: #1565c0; }
        .status-repair    { background: #fff3e0; color: #e65100; }
        .status-writeoff  { background: #ffebee; color: #c62828; }

        /* ===== Card ===== */
        .card-wrap {
            max-width: 480px;
            margin: -28px auto 0;
            padding: 0 16px 32px;
        }
        .info-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.10);
            overflow: hidden;
            margin-bottom: 16px;
        }
        .info-card .card-head {
            background: #f8f9fa;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-row {
            display: flex;
            padding: 11px 20px;
            border-bottom: 1px solid #f5f5f5;
            font-size: 14px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            color: #888;
            min-width: 110px;
            flex-shrink: 0;
        }
        .info-value { font-weight: 500; color: #222; }

        /* ===== QR card ===== */
        .qr-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .qr-card img {
            width: 140px; height: 140px;
            border: 3px solid #e0e0e0;
            border-radius: 8px;
            padding: 4px;
        }
        .qr-card .qr-hint {
            font-size: 12px;
            color: #aaa;
            margin-top: 8px;
            text-align: center;
        }

        /* ===== Action buttons ===== */
        .action-wrap {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }
        .btn-action-main {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            text-align: center;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-action-main:hover { opacity: 0.88; }
        .btn-blue  { background: linear-gradient(135deg,#4e54c8,#8f94fb); color: #fff; }
        .btn-orange{ background: linear-gradient(135deg,#f7971e,#ffd200); color: #000; }
        .btn-gray  { background: #f0f0f0; color: #555; }

        /* ===== Footer ===== */
        .scan-footer {
            text-align: center;
            font-size: 12px;
            color: #bbb;
            padding-bottom: 20px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="scan-header">
        <div class="school-name">🏫 โรงเรียนบ้านโคกวิทยา &nbsp;|&nbsp; สพป.ศรีสะเกษ เขต4</div>
        <h1><?= htmlspecialchars($asset['name']) ?></h1>
        <div class="asset-code-badge"><?= htmlspecialchars($asset['code']) ?></div>
    </div>

    <div class="card-wrap">

        <!-- Status -->
        <div style="display:flex; justify-content:center; margin: 20px 0 16px;">
            <?php
            $statusClass = [
                'available' => 'status-available',
                'inuse'     => 'status-inuse',
                'repair'    => 'status-repair',
                'writeoff'  => 'status-writeoff',
            ][$asset['status']] ?? 'status-available';
            $statusIcon = [
                'available' => 'bx-check-circle',
                'inuse'     => 'bx-user-check',
                'repair'    => 'bx-wrench',
                'writeoff'  => 'bx-trash',
            ][$asset['status']] ?? 'bx-check-circle';
            ?>
            <span class="status-pill <?= $statusClass ?>">
                <i class='bx <?= $statusIcon ?>'></i>
                สถานะ: <?= htmlspecialchars($asset['status_text']) ?>
            </span>
        </div>

        <!-- Asset Info -->
        <div class="info-card">
            <div class="card-head"><i class='bx bx-info-circle'></i>ข้อมูลทรัพย์สิน</div>
            <div class="info-row">
                <span class="info-label">รหัสพัสดุ</span>
                <span class="info-value"><?= htmlspecialchars($asset['code']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ประเภท</span>
                <span class="info-value"><?= htmlspecialchars($asset['category']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">รุ่น/แบบ</span>
                <span class="info-value"><?= htmlspecialchars($asset['model']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">สถานที่ตั้ง</span>
                <span class="info-value"><?= htmlspecialchars($asset['location']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ผู้รับผิดชอบ</span>
                <span class="info-value"><?= htmlspecialchars($asset['custodian']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่ได้รับ</span>
                <span class="info-value"><?= htmlspecialchars($asset['receive_date']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ราคา (บาท)</span>
                <span class="info-value"><?= htmlspecialchars($asset['cost']) ?></span>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="action-wrap">
            <a href="asset_detail.php?id=<?= $asset['id'] ?>" class="btn-action-main btn-blue">
                <i class='bx bx-spreadsheet me-1'></i>ดูทะเบียนคุม (เต็มรูปแบบ)
            </a>
            <a href="borrow_asset.php?id=<?= $asset['id'] ?>" class="btn-action-main btn-orange">
                <i class='bx bx-log-out me-1'></i>ขอยืมทรัพย์สินนี้
            </a>
            <button onclick="reportIssue()" class="btn-action-main btn-gray">
                <i class='bx bx-error-circle me-1'></i>รายงานปัญหา / ชำรุด
            </button>
        </div>

        <!-- QR Code reference -->
        <div class="info-card">
            <div class="card-head"><i class='bx bx-qr'></i>QR Code ของรายการนี้</div>
            <div class="qr-card">
                <img src="<?= $qr_api ?>" alt="QR Code <?= $asset['code'] ?>">
                <div class="qr-hint">สแกนเพื่อเปิดหน้านี้อีกครั้ง</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="scan-footer">
            ระบบบริหารพัสดุ &bull; โรงเรียนบ้านโคกวิทยา<br>
            <?= date('d/m/Y H:i') ?>
        </div>

    </div><!-- end card-wrap -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function reportIssue() {
            Swal.fire({
                title: 'รายงานปัญหา',
                input: 'textarea',
                inputPlaceholder: 'อธิบายปัญหาหรืออาการที่พบ...',
                showCancelButton: true,
                confirmButtonText: 'ส่งรายงาน',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#e65100',
                inputValidator: (v) => !v.trim() && 'กรุณาระบุปัญหา'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ส่งรายงานเรียบร้อย!',
                        text: 'ระบบจะแจ้งเจ้าหน้าที่พัสดุทราบต่อไป',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }
    </script>
</body>
</html>
