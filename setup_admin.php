<?php
/**
 * setup_admin.php — ตั้งรหัสผ่าน admin ครั้งแรก
 * เปิดหน้านี้ครั้งเดียว แล้วลบทิ้งหรือ lock ได้เลย
 * URL: http://edu.xpechat.uk:8080/inventory/setup_admin.php
 */
require_once __DIR__ . '/backend/config/db_inventory.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    if ($user && $pass && $name) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        // upsert: ถ้ามีแล้วให้ update hash
        $exists = inv_row("SELECT id FROM system_users WHERE username=?", [$user]);
        if ($exists) {
            inv_exec("UPDATE system_users SET password_hash=?, full_name=?, role='admin' WHERE username=?", [$hash, $name, $user]);
        } else {
            inv_exec("INSERT INTO system_users (username, password_hash, full_name, role) VALUES (?,?,?,'admin')", [$user, $hash, $name]);
        }
        $msg = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Setup Admin — Inventory</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body{font-family:Kanit,sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
    .box{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);padding:40px 48px;max-width:480px;width:100%;}
    h3{color:#4e54c8;margin:0 0 20px;font-size:22px;}
    label{font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:4px;}
    input{width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-family:Kanit,sans-serif;font-size:14px;margin-bottom:14px;}
    input:focus{border-color:#4e54c8;outline:none;}
    button{width:100%;padding:12px;background:#4e54c8;color:#fff;border:none;border-radius:8px;font-family:Kanit,sans-serif;font-size:15px;font-weight:600;cursor:pointer;}
    .ok{background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:14px;margin-bottom:16px;color:#2e7d32;font-size:14px;}
    .warn{background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;color:#795548;}
</style>
</head>
<body>
<div class="box">
    <h3>🔧 ตั้งค่า Admin Account</h3>
    <div class="warn">⚠️ ใช้หน้านี้ครั้งแรกเท่านั้น แล้วควรลบไฟล์ <code>setup_admin.php</code> ออก</div>
    <?php if ($msg === 'success'): ?>
    <div class="ok">✅ บันทึกสำเร็จ! สามารถ <a href="login.php">เข้าสู่ระบบ</a> ได้เลยครับ<br><br>
    <strong>จากนั้นลบหรือ rename ไฟล์นี้ทิ้ง:</strong><br><code>inventory/setup_admin.php</code></div>
    <?php else: ?>
    <form method="POST">
        <label>ชื่อผู้ใช้ (username)</label>
        <input type="text" name="username" value="admin" required>
        <label>ชื่อ-นามสกุล</label>
        <input type="text" name="full_name" value="ผู้ดูแลระบบ" required>
        <label>รหัสผ่าน</label>
        <input type="password" name="password" placeholder="ตั้งรหัสผ่าน" required>
        <button type="submit">💾 บันทึก Admin</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
