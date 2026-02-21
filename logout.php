<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['inv_user_id'] = null;
$_SESSION['inv_username'] = null;
$_SESSION['inv_fullname'] = null;
$_SESSION['inv_role']     = null;
session_destroy();
header('Location: index.php');
exit;
