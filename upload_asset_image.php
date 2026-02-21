<?php
/**
 * upload_asset_image.php
 * รับไฟล์รูปภาพ บันทึกลง uploads/assets/
 * เรียกผ่าน AJAX: POST multipart/form-data → image_file
 * คืน JSON: { success, filename, url, error }
 */
header('Content-Type: application/json');

$uploadDir   = __DIR__ . '/uploads/assets/';
$allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
$maxSize     = 5 * 1024 * 1024; // 5 MB

// --- Validate ---
if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์หรือเกิดข้อผิดพลาดในการอัปโหลด']);
    exit;
}

$file = $_FILES['image_file'];

if (!in_array($file['type'], $allowedMime)) {
    echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะ JPG, PNG, WEBP']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'ไฟล์ต้องไม่เกิน 5 MB']);
    exit;
}

// Additional MIME check via getimagesize
$imgInfo = getimagesize($file['tmp_name']);
if ($imgInfo === false) {
    echo json_encode(['success' => false, 'error' => 'ไฟล์ไม่ใช่รูปภาพที่ถูกต้อง']);
    exit;
}

// --- Generate safe filename ---
$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$file['type']];
$filename = 'asset_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $uploadDir . $filename;

// --- Create directory if missing ---
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// --- Move file ---
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'บันทึกไฟล์ไม่สำเร็จ กรุณาตรวจสอบสิทธิ์โฟลเดอร์']);
    exit;
}

$url = 'uploads/assets/' . $filename;
echo json_encode(['success' => true, 'filename' => $filename, 'url' => $url]);
