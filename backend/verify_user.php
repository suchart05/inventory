<?php
/**
 * verify_user.php — API ตรวจสอบ username/password จาก system_users
 * รับ JSON POST: {username, password}
 * ส่งคืน JSON: {ok: true/false, msg: string}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db_inventory.php';

$body = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');

if (!$username || !$password) {
    echo json_encode(['ok' => false, 'msg' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน']);
    exit;
}

$user = inv_row(
    "SELECT id, username, full_name, role, password_hash FROM system_users WHERE username = ? AND is_active = 1 LIMIT 1",
    [$username]
);

if ($user && password_verify($password, $user['password_hash'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['inv_user_id']  = $user['id'];
    $_SESSION['inv_username'] = $user['username'];
    $_SESSION['inv_fullname'] = $user['full_name'];
    $_SESSION['inv_role']     = $user['role'];
    
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
}
