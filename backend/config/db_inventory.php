<?php
/**
 * db_inventory.php  —  mysqli connection + helper functions
 * ใช้ require_once ในทุกไฟล์ที่ต้องการ DB
 */
define('INV_DB_HOST', 'db');
define('INV_DB_PORT', 3306);
define('INV_DB_NAME', 'inventory_db');
define('INV_DB_USER', 'root');
define('INV_DB_PASS', 'root1234');

$inv_conn = new mysqli(INV_DB_HOST, INV_DB_USER, INV_DB_PASS, INV_DB_NAME, INV_DB_PORT);

if ($inv_conn->connect_error) {
    $errMsg = $inv_conn->connect_error;
    error_log('[inventory_db] ' . $errMsg);
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']));
    }
    die('<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
    <title>ไม่สามารถเชื่อมต่อ inventory_db</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:Kanit,sans-serif;background:#f4f6fb;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
        .box{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.1);padding:40px 48px;max-width:600px;width:100%;}
        h2{color:#e74c3c;margin:0 0 8px;} p{color:#555;font-size:14px;}
        code{background:#fff3f3;border:1px solid #fcc;padding:2px 8px;border-radius:4px;font-size:13px;color:#c0392b;}
        .step{background:#f8f9ff;border-left:4px solid #4e54c8;padding:10px 16px;margin:8px 0;border-radius:0 8px 8px 0;font-size:14px;}
        .err-detail{background:#fff3f3;border:1px solid #fcc;border-radius:8px;padding:12px;font-size:12px;color:#c0392b;margin:12px 0;word-break:break-all;}
    </style></head><body>
    <div class="box">
        <h2>⚠️ เชื่อมต่อ <code>inventory_db</code> ไม่ได้</h2>
        <p>ระบบไม่สามารถเชื่อมต่อฐานข้อมูล <strong>inventory_db</strong> ได้</p>
        <div class="err-detail"><strong>Error:</strong> ' . htmlspecialchars($errMsg) . '</div>
        <div class="step">✅ ตรวจสอบ <strong>รหัสผ่าน</strong> ใน <code>backend/config/db_inventory.php</code></div>
        <div class="step">✅ ตรวจสอบว่า Docker container <code>db</code> กำลังรันอยู่</div>
        <p style="font-size:13px;color:#aaa;margin-top:16px;">host=<code>' . INV_DB_HOST . '</code> port=<code>' . INV_DB_PORT . '</code> db=<code>' . INV_DB_NAME . '</code> user=<code>' . INV_DB_USER . '</code></p>
    </div></body></html>');
}
$inv_conn->set_charset('utf8mb4');

/* ================================================================
   Helper functions — ใช้แทน PDO
   ================================================================ */

/**
 * คืนทุกแถว (array of assoc arrays)
 * inv_query("SELECT * FROM assets WHERE status=?", ['available'])
 */
function inv_query(string $sql, array $params = []): array {
    global $inv_conn;
    if (empty($params)) {
        $res = $inv_conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt = $inv_conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * คืนแถวเดียว (assoc array หรือ null)
 */
function inv_row(string $sql, array $params = []): ?array {
    $rows = inv_query($sql, $params);
    return $rows ? $rows[0] : null;
}

/**
 * INSERT / UPDATE / DELETE — คืน true/false
 * inv_exec("UPDATE assets SET status=? WHERE id=?", ['inuse', 5])
 */
function inv_exec(string $sql, array $params = []): bool {
    global $inv_conn;
    if (empty($params)) {
        return (bool) $inv_conn->query($sql);
    }
    $stmt = $inv_conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    return $stmt->execute();
}

/** คืน AUTO_INCREMENT id ล่าสุด */
function inv_last_id(): int {
    global $inv_conn;
    return (int) $inv_conn->insert_id;
}
