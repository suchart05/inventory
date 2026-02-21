if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['inv_user_id']);
require_once __DIR__ . '/backend/auth_check.php';
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$asset = inv_row("SELECT a.*, ac.name AS cat_name FROM assets a LEFT JOIN asset_categories ac ON a.category_code=ac.code WHERE a.id=? AND a.status='available'", [$id]);
if (!$asset) { header('Location: asset.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bname   = trim($_POST['borrower_name']??'');
    $bdept   = trim($_POST['borrower_dept']??'') ?: null;
    $btel    = trim($_POST['borrower_tel']??'') ?: null;
    $bdate   = $_POST['borrow_date']??'';
    $rdue    = $_POST['return_due_date']??'';
    $purpose = trim($_POST['purpose']??'') ?: null;

    if (!$bname || !$bdate || !$rdue) {
        $error = 'กรุณากรอกชื่อผู้ยืม วันที่ยืม และกำหนดคืน';
    } else {
        global $inv_conn;
        $inv_conn->begin_transaction();
        try {
            inv_exec("INSERT INTO borrow_records (asset_id,borrower_name,borrower_dept,borrower_tel,borrow_date,return_due_date,purpose,status) VALUES (?,?,?,?,?,?,?,'borrowing')",
                [$id,$bname,$bdept,$btel,$bdate,$rdue,$purpose]);
            inv_exec("UPDATE assets SET status='inuse' WHERE id=?", [$id]);
            $inv_conn->commit();
            header('Location: asset.php?msg=borrowed'); exit;
        } catch (Exception $e) {
            $inv_conn->rollback();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title"><h4 class="mb-0 fw-bold text-dark">บันทึกการยืมทรัพย์สิน</h4><small class="text-muted">Asset Borrowing</small></div>
        <div class="user-profile d-flex align-items-center">
            <i class='bx bx-user-circle' style='font-size:32px;color:#1FA2FF;margin-right:10px;'></i>
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
        <?php if ($error): ?><div class="alert alert-danger rounded-3 mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="card border-0 shadow-sm" style="border-radius:16px;max-width:700px;margin:0 auto;">
            <div class="card-header border-0 py-3 px-4" style="background:linear-gradient(135deg,#1FA2FF,#12D8FA);border-radius:16px 16px 0 0;">
                <h5 class="mb-0 text-white fw-bold"><i class='bx bx-log-out me-2'></i>บันทึกการยืมทรัพย์สิน</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info py-3 mb-4" style="border-radius:12px;font-size:14px;">
                    <div class="fw-bold mb-1"><i class='bx bx-cube me-1'></i>ทรัพย์สินที่ต้องการยืม</div>
                    <div>รหัส: <strong><?= htmlspecialchars($asset['code']) ?></strong> &nbsp;|&nbsp; ชื่อ: <strong><?= htmlspecialchars($asset['name']) ?></strong></div>
                    <div>หมวดหมู่: <strong><?= htmlspecialchars($asset['category_code'].' - '.($asset['cat_name']??'')) ?></strong></div>
                </div>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">ชื่อผู้ยืม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="borrower_name" required placeholder="ชื่อ-นามสกุลผู้ขอยืม">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">หน่วยงาน/ห้องเรียน</label>
                            <input type="text" class="form-control" name="borrower_dept" placeholder="เช่น ห้องเรียน ป.5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทรติดต่อ</label>
                            <input type="tel" class="form-control" name="borrower_tel" placeholder="08x-xxx-xxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันที่ยืม <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="borrow_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">กำหนดคืน <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="return_due_date" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">วัตถุประสงค์</label>
                            <textarea class="form-control" name="purpose" rows="2" placeholder="ระบุเหตุผล/วัตถุประสงค์..."></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                        <a href="asset.php" class="btn btn-light rounded-pill px-4"><i class='bx bx-arrow-back me-1'></i>ยกเลิก</a>
                        <button type="submit" class="btn rounded-pill px-5 fw-medium text-white" style="background:linear-gradient(135deg,#1FA2FF,#12D8FA);">
                            <i class='bx bx-log-out me-1'></i>ยืนยันการยืม
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'frontend/includes/footer.php'; ?>
