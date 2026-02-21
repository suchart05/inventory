<?php
require_once __DIR__ . '/backend/auth_check.php';
require_once __DIR__ . '/backend/config/db_inventory.php';

// เฉพาะ admin เท่านั้น
if ($_SESSION['inv_role'] !== 'admin') { header('Location: index.php'); exit; }

include 'frontend/includes/header.php';
include 'frontend/includes/sidebar.php';

$msg = $error = '';

// ---- Add User ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['full_name'] ?? '');
    $role     = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$username || !$fullname || !$password) {
        $error = 'กรุณากรอกข้อมูลให้ครบ';
    } elseif ($password !== $confirm) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } else {
        $exists = inv_row("SELECT id FROM system_users WHERE username=?", [$username]);
        if ($exists) {
            $error = 'ชื่อผู้ใช้นี้มีในระบบแล้ว';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            inv_exec("INSERT INTO system_users (username, password_hash, full_name, role) VALUES (?,?,?,?)",
                [$username, $hash, $fullname, $role]);
            $msg = 'เพิ่มผู้ใช้ ' . htmlspecialchars($username) . ' สำเร็จ';
        }
    }
}

// ---- Toggle Active ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $uid     = intval($_POST['uid'] ?? 0);
    $current = intval($_POST['current'] ?? 1);
    if ($uid && $uid !== (int)$_SESSION['inv_user_id']) {
        inv_exec("UPDATE system_users SET is_active=? WHERE id=?", [$current ? 0 : 1, $uid]);
        $msg = 'อัปเดตสถานะสำเร็จ';
    }
}

// ---- Reset Password ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pw') {
    $uid  = intval($_POST['uid'] ?? 0);
    $pass = $_POST['new_password'] ?? '';
    if ($uid && strlen($pass) >= 6) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        inv_exec("UPDATE system_users SET password_hash=? WHERE id=?", [$hash, $uid]);
        $msg = 'รีเซ็ตรหัสผ่านสำเร็จ';
    } else {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    }
}

// ---- Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $uid = intval($_POST['uid'] ?? 0);
    if ($uid && $uid !== (int)$_SESSION['inv_user_id']) {
        inv_exec("DELETE FROM system_users WHERE id=?", [$uid]);
        $msg = 'ลบผู้ใช้สำเร็จ';
    }
}

$users = inv_query("SELECT * FROM system_users ORDER BY role, username");
$roleLabel = ['admin'=>'ผู้ดูแลระบบ','staff'=>'เจ้าหน้าที่','viewer'=>'ผู้สังเกตการณ์'];
$roleColor = ['admin'=>'badge-inuse','staff'=>'badge-available','viewer'=>'bg-secondary text-white'];
?>
<link rel="stylesheet" href="frontend/assets/css/asset.css">
<style>
.user-card { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,0.07); padding:20px 24px; margin-bottom:16px; display:flex; align-items:center; gap:16px; transition:box-shadow .2s; }
.user-card:hover { box-shadow:0 4px 20px rgba(0,0,0,0.12); }
.user-avatar { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:#fff; flex-shrink:0; }
.user-info { flex:1; }
.user-info .name { font-size:15px; font-weight:600; color:#1a1a2e; }
.user-info .uname { font-size:12px; color:#999; }
.role-badge { font-size:12px; padding:3px 10px; border-radius:20px; }
.add-card { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:28px 32px; }
.add-card h6 { font-size:16px; font-weight:700; color:#4e54c8; margin-bottom:20px; }
.form-label { font-size:13px; font-weight:500; color:#555; margin-bottom:4px; }
.form-control, .form-select { font-family:'Kanit',sans-serif; font-size:14px; border-radius:8px; height:40px; }
.inactive-badge { background:#f0f0f0; color:#999; border-radius:20px; padding:2px 10px; font-size:12px; }
</style>

<section class="home-section">
    <nav class="top-navbar">
        <div class="nav-title"><h4 class="mb-0 fw-bold text-dark">จัดการผู้ใช้งาน</h4><small class="text-muted">User Management</small></div>
        <div class="user-profile d-flex align-items-center">
            <i class='bx bx-user-circle' style='font-size:32px;color:#4e54c8;margin-right:10px;'></i>
            <div><span class="d-block fw-semibold" style="line-height:1;"><?= htmlspecialchars($_SESSION['inv_fullname']) ?></span><small class="text-muted"><?= htmlspecialchars($_SESSION['inv_role']) ?></small></div>
        </div>
    </nav>

    <div class="main-content">
        <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4"><i class='bx bx-check-circle me-2'></i><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4"><i class='bx bx-error-circle me-2'></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- LEFT: User List -->
            <div class="col-lg-7">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0">ผู้ใช้งานทั้งหมด (<?= count($users) ?>)</h5>
                </div>

                <?php foreach ($users as $u):
                    $initials = mb_substr($u['full_name'], 0, 1);
                    $colors   = ['admin'=>'linear-gradient(135deg,#4e54c8,#8f94fb)','staff'=>'linear-gradient(135deg,#11998e,#38ef7d)','viewer'=>'linear-gradient(135deg,#aaa,#ccc)'];
                    $bg       = $colors[$u['role']] ?? $colors['viewer'];
                    $isSelf   = ($u['id'] == $_SESSION['inv_user_id']);
                ?>
                <div class="user-card <?= !$u['is_active'] ? 'opacity-50' : '' ?>">
                    <div class="user-avatar" style="background:<?= $bg ?>"><?= $initials ?></div>
                    <div class="user-info">
                        <div class="name">
                            <?= htmlspecialchars($u['full_name']) ?>
                            <?php if ($isSelf): ?><span class="badge bg-light text-muted ms-1" style="font-size:11px;">คุณ</span><?php endif; ?>
                        </div>
                        <div class="uname">@<?= htmlspecialchars($u['username']) ?>
                            <?php if ($u['last_login']): ?>&nbsp;|&nbsp; เข้าระบบล่าสุด: <?= date('d/m/Y H:i', strtotime($u['last_login'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-2">
                        <span class="<?= $roleColor[$u['role']] ?? 'bg-secondary text-white' ?> role-badge"><?= $roleLabel[$u['role']] ?? $u['role'] ?></span>
                        <?php if (!$u['is_active']): ?>
                        <span class="inactive-badge">ปิดใช้งาน</span>
                        <?php endif; ?>
                        <?php if (!$isSelf): ?>
                        <div class="d-flex gap-1">
                            <!-- Reset PW -->
                            <button class="btn btn-sm btn-outline-warning rounded-pill px-2" style="font-size:12px;"
                                onclick="showResetPw(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>')">
                                <i class='bx bx-key'></i>
                            </button>
                            <!-- Toggle Active -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                <input type="hidden" name="current" value="<?= $u['is_active'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'secondary' : 'success' ?> rounded-pill px-2" style="font-size:12px;" title="<?= $u['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>">
                                    <i class='bx bx-<?= $u['is_active'] ? 'block' : 'check' ?>'></i>
                                </button>
                            </form>
                            <!-- Delete -->
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" style="font-size:12px;"
                                onclick="confirmDelete(<?= $u['id'] ?>,'<?= htmlspecialchars($u['full_name'],ENT_QUOTES) ?>')">
                                <i class='bx bx-trash'></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- RIGHT: Add User Form -->
            <div class="col-lg-5">
                <div class="add-card">
                    <h6><i class='bx bx-user-plus me-2'></i>เพิ่มผู้ใช้งานใหม่</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" placeholder="ชื่อ นามสกุล" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อผู้ใช้ (username) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" placeholder="ไม่มีช่องว่าง เช่น somchai" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">บทบาท</label>
                            <select class="form-select" name="role">
                                <option value="staff">เจ้าหน้าที่</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                                <option value="viewer">ผู้สังเกตการณ์</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="พิมพ์รหัสผ่านอีกครั้ง" required>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill w-100 fw-medium">
                            <i class='bx bx-plus me-1'></i>เพิ่มผู้ใช้
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
        <div class="modal-content" style="border-radius:16px;">
            <form method="POST">
                <input type="hidden" name="action" value="reset_pw">
                <input type="hidden" name="uid" id="resetUid">
                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title fw-bold"><i class='bx bx-key me-2 text-warning'></i>รีเซ็ตรหัสผ่าน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-1">
                    <p class="text-muted mb-3" style="font-size:13px;">ผู้ใช้: <strong id="resetUname"></strong></p>
                    <label class="form-label">รหัสผ่านใหม่</label>
                    <input type="password" class="form-control" name="new_password" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-medium">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="uid" id="deleteUid">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="frontend/assets/js/asset.js"></script>
<script>
function showResetPw(uid, uname) {
    document.getElementById('resetUid').value   = uid;
    document.getElementById('resetUname').textContent = uname;
    new bootstrap.Modal(document.getElementById('resetPwModal')).show();
}
function confirmDelete(uid, name) {
    Swal.fire({ title:'ลบผู้ใช้?', text:'ลบ "'+name+'" ออกจากระบบ — ไม่สามารถยกเลิกได้',
        icon:'warning', showCancelButton:true, confirmButtonColor:'#e74c3c',
        cancelButtonColor:'#aaa', confirmButtonText:'ลบ', cancelButtonText:'ยกเลิก'
    }).then(r => { if (r.isConfirmed) { document.getElementById('deleteUid').value = uid; document.getElementById('deleteForm').submit(); }});
}
</script>
<?php include 'frontend/includes/footer.php'; ?>
