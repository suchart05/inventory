<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['inv_user_id'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

$results = [];
$preview = [];
$error   = '';

// ---- Handle Upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'กรุณาอัปโหลดไฟล์ .csv เท่านั้น';
    } else {
        $action = $_POST['action'] ?? 'preview';

        // Map Thai Budget label → DB enum
        $moneyGroupMap = [
            'งบดำเนินงาน'       => 'operation',
            'จ้างเหมา'          => 'salary',
            'เงินเดือน'         => 'salary',
            'โครงการพิเศษ'      => 'special',
            'รายได้สถานศึกษา'   => 'income',
            'อุดหนุน'           => 'subsidy',
            'อุดหนุน อปท.'      => 'subsidy',
            'ลงทุน'             => 'investment',
            'ซ่อมแซม'           => 'investment',
            'ลงทุน/ซ่อมแซม'    => 'investment',
        ];
        $typeMap = [
            'จัดซื้อ'  => 'buy',
            'ซื้อ'     => 'buy',
            'buy'      => 'buy',
            'จัดจ้าง'  => 'hire',
            'จ้าง'     => 'hire',
            'hire'     => 'hire',
        ];
        $statusMap = [
            'รอดำเนินการ'  => 'pending',
            'อนุมัติแล้ว'  => 'approved',
            'ตรวจรับแล้ว'  => 'received',
            'ยกเลิก'       => 'cancelled',
            'pending'      => 'pending',
            'approved'     => 'approved',
            'received'     => 'received',
            'cancelled'    => 'cancelled',
        ];

        $handle = fopen($file['tmp_name'], 'r');
        // Strip UTF-8 BOM if present, then ensure UTF-8
        $raw = file_get_contents($file['tmp_name']);
        // Remove UTF-8 BOM (\xEF\xBB\xBF)
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw = substr($raw, 3);
        }
        // If not valid UTF-8, try converting from Windows CP874 (Thai)
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = @iconv('CP874', 'UTF-8//IGNORE', $raw);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $raw = $converted;
            }
        }
        file_put_contents($file['tmp_name'], $raw);
        fclose($handle);
        $handle = fopen($file['tmp_name'], 'r');

        $headers = fgetcsv($handle); // first row = headers
        // Trim BOM and whitespace
        $headers = array_map(fn($h) => trim($h, "\xEF\xBB\xBF \t\r\n\""), $headers);

        // Map column index by header name (case-insensitive)
        $colMap = [];
        foreach ($headers as $i => $h) {
            $colMap[strtolower($h)] = $i;
        }

        $col = fn($name) => $colMap[strtolower($name)] ?? null;
        $get = fn($row, $name) => isset($colMap[strtolower($name)]) ? trim($row[$colMap[strtolower($name)]] ?? '') : '';

        $imported = $skipped = $dupes = 0;
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) < 3) continue;

            $rowId      = $get($row, 'ID');
            $egpNo      = $get($row, 'EGP_No') ?: null;
            $orderType  = $typeMap[$get($row, 'Type')] ?? 'buy';
            $prefix     = ($orderType === 'buy') ? 'ซ' : 'จ';
            // Zero-pad: ซ01/2569, ซ02/2569
            $fiscalYear = (int) ($get($row, 'FiscalYear') ?: 2569);
            $orderNo    = $rowId ? ($prefix . str_pad($rowId, 2, '0', STR_PAD_LEFT) . '/' . $fiscalYear) : null;
            $orderDate  = $get($row, 'ReqDate') ?: $get($row, 'ResDate') ?: $get($row, 'order_date');
            $dueDate    = $get($row, 'DueDate')  ?: null;
            $inspDate   = $get($row, 'InspectDate') ?: null;
            $title      = $get($row, 'Item') ?: $get($row, 'title');
            $amount     = (float) preg_replace('/[^0-9.]/', '', $get($row, 'Amount'));
            $vendor     = $get($row, 'Seller') ?: $get($row, 'vendor_name');
            $noteVal    = $get($row, 'Note') ?: $get($row, 'note');
            $docRef     = null;
            $statusRaw  = $get($row, 'Status');
            $statusVal  = $statusMap[$statusRaw] ?? 'received';
            $budgetRaw  = $get($row, 'Budget') ?: $get($row, 'budgetType');
            $moneyGroup = 'operation';
            foreach ($moneyGroupMap as $k => $v) {
                if (mb_strpos($budgetRaw, $k) !== false) { $moneyGroup = $v; break; }
            }

            // Helper defined once outside would be ideal, here inline as closure
            // Supports: YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY, D/M/Y, serial number
            $convertDate = function($dateStr) {
                if (!$dateStr || $dateStr === '0' || $dateStr === '') return null;
                $s = trim($dateStr, " \t\r\n\"'");

                // Already ISO YYYY-MM-DD (ค.ศ. or พ.ศ.)
                if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
                    $y = (int)$m[1]; if ($y > 2400) $y -= 543;
                    return sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[3]);
                }
                // DD/MM/YYYY or D/M/YYYY
                if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
                    $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3];
                    // Guess: if day <= 12, ambiguous — assume DD/MM (Thai style)
                    if ($y > 2400) $y -= 543;
                    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
                }
                // MM/DD/YYYY (US format)
                if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $s, $m)) {
                    $y=(int)$m[3]; if ($y > 2400) $y -= 543;
                    return sprintf('%04d-%02d-%02d', $y, (int)$m[1], (int)$m[2]);
                }
                // Excel serial date number (e.g., 46234)
                if (preg_match('/^\d{5}$/', $s)) {
                    // Excel epoch 1900-01-01 = serial 1
                    $timestamp = ($s - 25569) * 86400;
                    return date('Y-m-d', $timestamp);
                }
                // Try strtotime as fallback
                $ts = strtotime($s);
                if ($ts !== false && $ts > 0) {
                    return date('Y-m-d', $ts);
                }
                return null; // cannot parse
            };
            $rawOrderDate = $orderDate; // keep raw for debug
            $orderDate = $convertDate($orderDate);
            $dueDate   = $convertDate($dueDate);
            $inspDate  = $convertDate($inspDate);

            if (!$title) { $skipped++; continue; }

            $row_data = compact('orderNo','orderType','orderDate','rawOrderDate','dueDate','inspDate','egpNo','title','amount','fiscalYear','vendor','noteVal','statusVal','moneyGroup');

            if ($action === 'preview') {
                $preview[] = array_merge($row_data, ['row' => $rowNum]);
                if (count($preview) >= 20) break; // Preview first 20 rows
            } else {
                // Dupe check on EGP number or order_no
                if ($egpNo) {
                    $exists = inv_row("SELECT id FROM procurement_orders WHERE egp_no=?", [$egpNo]);
                    if ($exists) { $dupes++; continue; }
                } elseif ($orderNo) {
                    $exists = inv_row("SELECT id FROM procurement_orders WHERE order_no=?", [$orderNo]);
                    if ($exists) { $dupes++; continue; }
                }
                $ok = inv_exec(
                    "INSERT INTO procurement_orders
                        (order_no, order_type, order_date, due_date, inspect_date, fiscal_year, title,
                         total_amount, money_group, proc_method, status, vendor_name, egp_no, doc_ref, note, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,'specific',?,?,?,?,?,?)",
                    [$orderNo, $orderType, $orderDate, $dueDate, $inspDate, $fiscalYear, $title,
                     $amount, $moneyGroup, $statusVal, $vendor ?: null, $egpNo, $docRef, $noteVal ?: null, $_SESSION['inv_user_id']]
                );
                if ($ok) $imported++; else $skipped++;
            }
        }
        fclose($handle);

        if ($action === 'import') {
            $results = compact('imported', 'skipped', 'dupes');
        }
    }
}
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
.import-card { background:#fff; border-radius:16px; box-shadow:0 2px 14px rgba(0,0,0,0.08); padding:28px 32px; max-width:900px; margin:0 auto; }
.column-map  { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
.column-tag  { background:#f0f1ff; color:#4e54c8; border-radius:20px; padding:4px 14px; font-size:13px; font-weight:600; }
</style>

<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title">
            <h4 class="mb-0 fw-bold text-dark"><i class='bx bx-import me-2 text-primary'></i>นำเข้าข้อมูลจาก Google Sheets / CSV</h4>
            <small class="text-muted">Import Procurement Data</small>
        </div>
        <div class="user-profile d-flex align-items-center">
            <i class='bx bx-user-circle' style='font-size:32px;color:#4e54c8;margin-right:10px;'></i>
            <div>
                <span class="d-block fw-semibold" style="line-height:1;"><?= htmlspecialchars($_SESSION['inv_fullname']) ?></span>
                <small class="text-muted"><?= htmlspecialchars($_SESSION['inv_role']) ?></small>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="mb-3"><a href="procurement.php" class="btn btn-outline-secondary rounded-pill px-4"><i class='bx bx-arrow-back me-1'></i>กลับ</a></div>
        <div class="import-card">
            <h5 class="fw-bold mb-1"><i class='bx bx-table me-2 text-success'></i>วิธีนำเข้าข้อมูล</h5>

            <div class="alert alert-info" style="border-radius:10px; font-size:13px; margin-top:12px;">
                <strong>ขั้นตอน:</strong>
                <ol class="mb-0 mt-1">
                    <li>เปิด Google Sheet → เมนู <strong>File → Download → Comma Separated Values (.csv)</strong></li>
                    <li>อัปโหลดไฟล์ .csv ด้านล่าง</li>
                    <li>กด <strong>ดูตัวอย่าง</strong> เพื่อตรวจสอบก่อน แล้วค่อย <strong>นำเข้าจริง</strong></li>
                </ol>
            </div>

            <div class="mb-3">
                <small class="fw-bold text-muted">คอลัมน์ที่รองรับ:</small>
                <div class="column-map">
                    <?php foreach ([
                        'FiscalYear'=> 'ปีงบประมาณ', 'Type'=> 'ประเภท (จัดซื้อ/จัดจ้าง)',
                        'ResDate'   => 'วันที่', 'Item'=> 'รายการ', 'Amount'=> 'วงเงิน',
                        'Seller'    => 'ผู้ขาย', 'EGP_No'=> 'เลขที่/เลขที่ EGP',
                        'Budget'    => 'กลุ่มเงิน', 'Status'=> 'สถานะ', 'Note'=> 'หมายเหตุ',
                    ] as $col => $desc): ?>
                    <span class="column-tag" title="<?= $desc ?>"><?= $col ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger rounded-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($results)): ?>
            <div class="alert alert-success rounded-3">
                <i class='bx bx-check-circle me-2'></i>
                <strong>นำเข้าสำเร็จ!</strong>
                นำเข้า <strong><?= $results['imported'] ?></strong> รายการ |
                ข้ามซ้ำ <strong><?= $results['dupes'] ?></strong> |
                ข้ามข้อมูลไม่ครบ <strong><?= $results['skipped'] ?></strong>
                &nbsp;<a href="procurement.php" class="btn btn-sm btn-success rounded-pill ms-2">ไปหน้าพัสดุ</a>
            </div>
            <?php endif; ?>

            <!-- Upload Form -->
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">เลือกไฟล์ CSV</label>
                    <input type="file" class="form-control" name="csv_file" id="csvFile" accept=".csv" required>
                    <small class="text-muted">รองรับ UTF-8 / TIS-620 (Windows-874) — ขนาดไม่เกิน 5MB</small>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="action" value="preview" class="btn btn-outline-primary rounded-pill px-4">
                        <i class='bx bx-show me-1'></i>ดูตัวอย่าง (20 แถวแรก)
                    </button>
                    <button type="submit" name="action" value="import" class="btn btn-success rounded-pill px-4"
                            onclick="return confirm('นำเข้าข้อมูลทั้งหมดเข้าระบบเลยใช่ไหม?')">
                        <i class='bx bx-import me-1'></i>นำเข้าจริง
                    </button>
                </div>
            </form>

            <!-- Preview Table -->
            <?php if (!empty($preview)): ?>
            <hr class="my-4">
            <h6 class="fw-bold mb-3"><i class='bx bx-table me-2'></i>ตัวอย่างข้อมูลที่จะนำเข้า (<?= count($preview) ?> แถวแรก)</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" style="font-size:12px;">
                    <thead class="table-primary">
                        <tr>
                            <th>แถว</th><th>เลขที่</th><th>ประเภท</th><th>วันที่</th><th>รายการ</th>
                            <th>วงเงิน</th><th>กลุ่มเงิน</th><th>ผู้ขาย</th><th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($preview as $p): ?>
                    <tr>
                        <td class="text-muted"><?= $p['row'] ?></td>
                        <td><?= htmlspecialchars($p['orderNo'] ?? '') ?></td>
                        <td><?= $p['orderType'] === 'buy' ? '<span class="badge bg-primary">จัดซื้อ</span>' : '<span class="badge bg-warning text-dark">จัดจ้าง</span>' ?></td>
                        <td>
                            <?php if ($p['orderDate']): ?>
                                <?= htmlspecialchars($p['orderDate']) ?>
                            <?php else: ?>
                                <span class="text-danger small">parse ไม่ได้</span><br>
                                <code style="font-size:10px;color:#888;"><?= htmlspecialchars($p['rawOrderDate'] ?? '(empty)') ?></code>
                            <?php endif; ?>
                        </td>
                        <td class="text-start"><?= htmlspecialchars(mb_substr($p['title'] ?? '', 0, 50)) ?><?= mb_strlen($p['title'] ?? '') > 50 ? '...' : '' ?></td>
                        <td><?= number_format($p['amount'] ?? 0, 2) ?></td>
                        <td><?= htmlspecialchars($p['moneyGroup'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['vendor'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['statusVal'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-warning" style="font-size:13px;border-radius:10px;">
                <i class='bx bx-info-circle me-1'></i>
                ถ้าข้อมูลถูกต้อง ให้อัปโหลดไฟล์เดิมอีกครั้งแล้วกด <strong>"นำเข้าจริง"</strong>
            </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'frontend/includes/footer.php'; ?>
