<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['inv_user_id'])) { header('Location: asset.php'); exit; }

require_once __DIR__ . '/backend/config/db_inventory.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $user = inv_row("SELECT * FROM system_users WHERE username=? AND is_active=1 LIMIT 1", [$username]);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['inv_user_id']   = $user['id'];
            $_SESSION['inv_username']  = $user['username'];
            $_SESSION['inv_fullname']  = $user['full_name'];
            $_SESSION['inv_role']      = $user['role'];
            inv_exec("UPDATE system_users SET last_login=NOW() WHERE id=?", [$user['id']]);
            header('Location: index.php'); exit;
        }
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ — ระบบพัสดุโรงเรียน</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Kanit', sans-serif;
    min-height: 100vh;
    display: flex;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
  }
  .bg-blur {
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(78,84,200,0.3) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(17,153,142,0.25) 0%, transparent 55%),
                radial-gradient(ellipse at 60% 80%, rgba(31,162,255,0.2) 0%, transparent 50%);
    pointer-events: none;
  }
  .card {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 24px;
    padding: 48px 44px;
    width: 100%;
    max-width: 420px;
    position: relative;
    z-index: 1;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
  }
  .logo-wrap {
    text-align: center;
    margin-bottom: 32px;
  }
  .logo-icon {
    width: 70px; height: 70px;
    background: linear-gradient(135deg, #4e54c8, #11998e);
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    color: #fff;
    margin-bottom: 14px;
    box-shadow: 0 8px 24px rgba(78,84,200,0.4);
  }
  .logo-title { font-size: 20px; font-weight: 700; color: #fff; line-height: 1.3; }
  .logo-sub   { font-size: 13px; color: rgba(255,255,255,0.5); margin-top: 4px; }

  .form-group { margin-bottom: 18px; }
  .form-label { font-size: 13px; color: rgba(255,255,255,0.7); display: block; margin-bottom: 6px; }
  .input-wrap { position: relative; }
  .input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 20px; color: rgba(255,255,255,0.4); pointer-events: none; }
  .form-input {
    width: 100%; padding: 12px 14px 12px 44px;
    background: rgba(255,255,255,0.08);
    border: 1.5px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    color: #fff;
    font-family: 'Kanit', sans-serif;
    font-size: 15px;
    transition: border-color 0.2s, background 0.2s;
  }
  .form-input::placeholder { color: rgba(255,255,255,0.3); }
  .form-input:focus { outline: none; border-color: #4e54c8; background: rgba(78,84,200,0.15); }

  .toggle-pw {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 20px;
    padding: 0; display: flex;
  }
  .btn-login {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #4e54c8, #11998e);
    color: #fff; font-family: 'Kanit', sans-serif; font-size: 16px; font-weight: 600;
    border: none; border-radius: 12px; cursor: pointer;
    transition: opacity 0.2s, transform 0.15s;
    box-shadow: 0 6px 20px rgba(78,84,200,0.4);
    margin-top: 6px;
  }
  .btn-login:hover  { opacity: 0.9; transform: translateY(-1px); }
  .btn-login:active { transform: translateY(0); }

  .error-msg {
    background: rgba(231,76,60,0.18); border: 1px solid rgba(231,76,60,0.4);
    border-radius: 10px; padding: 10px 14px; margin-bottom: 18px;
    color: #ff8a80; font-size: 14px; display: flex; align-items: center; gap: 8px;
  }
  .footer-text { text-align: center; margin-top: 28px; font-size: 12px; color: rgba(255,255,255,0.25); }
</style>
</head>
<body>
<div class="bg-blur"></div>
<div class="card">
    <div class="logo-wrap">
        <div class="logo-icon"><i class='bx bx-package'></i></div>
        <div class="logo-title">ระบบบริหารพัสดุ</div>
        <div class="logo-sub">โรงเรียนบ้านโคกวิทยา</div>
    </div>

    <?php if ($error): ?>
    <div class="error-msg"><i class='bx bx-error-circle'></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= !empty($_GET['next']) ? '?next='.urlencode($_GET['next']) : '' ?>">
        <div class="form-group">
            <label class="form-label">ชื่อผู้ใช้</label>
            <div class="input-wrap">
                <i class='bx bx-user'></i>
                <input id="username" type="text" name="username" class="form-input" placeholder="กรอกชื่อผู้ใช้" autofocus required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">รหัสผ่าน</label>
            <div class="input-wrap">
                <i class='bx bx-lock-alt'></i>
                <input id="password" type="password" name="password" class="form-input" placeholder="กรอกรหัสผ่าน" required>
                <button type="button" class="toggle-pw" onclick="togglePw()"><i class='bx bx-hide' id="eyeIcon"></i></button>
            </div>
        </div>
        <button type="submit" class="btn-login"><i class='bx bx-log-in me-2'></i>เข้าสู่ระบบ</button>
    </form>
    <p class="footer-text">Inventory Management System v1.0</p>
</div>

<script>
function togglePw() {
    const pw = document.getElementById('password');
    const ic = document.getElementById('eyeIcon');
    if (pw.type === 'password') { pw.type = 'text';     ic.className = 'bx bx-show'; }
    else                        { pw.type = 'password'; ic.className = 'bx bx-hide'; }
}
</script>
</body>
</html>
