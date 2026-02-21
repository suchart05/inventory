<?php
/**
 * auth_check.php — session guard สำหรับระบบ inventory
 * require_once ที่ต้นทุกหน้าที่ต้องการ login
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['inv_user_id'])) {
    header('Location: index.php');
    exit;
}
