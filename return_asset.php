if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['inv_user_id']);
require_once __DIR__ . '/backend/auth_check.php';
require_once __DIR__ . '/backend/config/db_inventory.php';
include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

$assetId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$record = inv_row("SELECT br.*, a.code AS asset_code, a.name AS asset_name, a.category_code, ac.name AS cat_name FROM borrow_records br JOIN assets a ON br.asset_id=a.id LEFT JOIN asset_categories ac ON a.category_code=ac.code WHERE br.asset_id=? AND br.status='borrowing' ORDER BY br.id DESC LIMIT 1", [$assetId]);
if (!$record) { header('Location: asset.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $condition  = $_POST['condition_on_return']??'normal';
    $retDate    = $_POST['actual_return_date']??date('Y-m-d');
    $damage     = trim($_POST['damage_detail']??'') ?: null;
    $newStatus  = ($condition === 'normal') ? 'available' : 'repair';

    global $inv_conn;
    $inv_conn->begin_transaction();
    try {
        inv_exec("UPDATE borrow_records SET actual_return_date=?,condition_on_return=?,damage_detail=?,status='returned' WHERE id=?",
            [$retDate,$condition,$damage,$record['id']]);
        inv_exec("UPDATE assets SET status=? WHERE id=?", [$newStatus,$assetId]);
        $inv_conn->commit();
        header('Location: asset.php?msg=returned'); exit;
    } catch (Exception $e) {
        $inv_conn->rollback();
        $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title"><h4 class="mb-0 fw-bold text-dark">บันทึกการคืนทรัพย์สิน</h4><small class="text-muted">Asset Return</small></div>
        <div class="user-profile d-flex align-items-center">
            <i class='bx bx-user-circle' style='font-size:32px;color:#11998e;margin-right:10px;'></i>
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
            <div class="card-header border-0 py-3 px-4" style="background:linear-gradient(135deg,#11998e,#38ef7d);border-radius:16px 16px 0 0;">
                <h5 class="mb-0 text-white fw-bold"><i class='bx bx-log-in me-2'></i>บันทึกการคืนทรัพย์สิน</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-success py-3 mb-4" style="border-radius:12px;font-size:14px;">
                    <div class="fw-bold mb-1"><i class='bx bx-cube me-1'></i>ทรัพย์สินที่รอรับคืน</div>
                    <div>รหัส: <strong><?= htmlspecialchars($record['asset_code']) ?></strong> &nbsp;|&nbsp; ชื่อ: <strong><?= htmlspecialchars($record['asset_name']) ?></strong></div>
                    <div>ผู้ยืม: <strong><?= htmlspecialchars($record['borrower_name']) ?></strong> &nbsp;|&nbsp; กำหนดคืน: <strong><?= date('d/m/Y', strtotime($record['return_due_date'])) ?></strong></div>
                </div>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">วันที่คืนจริง <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="actual_return_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สภาพเมื่อคืน <span class="text-danger">*</span></label>
                            <select class="form-select" name="condition_on_return" onchange="document.getElementById('dmgBox').style.display=this.value!=='normal'?'block':'none'">
                                <option value="normal">ปกติ / สมบูรณ์</option>
                                <option value="damaged">ชำรุด</option>
                                <option value="lost">สูญหาย</option>
                            </select>
                        </div>
                        <div class="col-12" id="dmgBox" style="display:none;">
                            <label class="form-label">รายละเอียดการชำรุด/สูญหาย</label>
                            <textarea class="form-control" name="damage_detail" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                        <a href="asset.php" class="btn btn-light rounded-pill px-4"><i class='bx bx-arrow-back me-1'></i>ยกเลิก</a>
                        <button type="submit" class="btn btn-success rounded-pill px-5 fw-medium"><i class='bx bx-log-in me-1'></i>ยืนยันการคืน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'frontend/includes/footer.php'; ?>
