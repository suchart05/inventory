<?php
error_reporting(E_ALL & ~E_DEPRECATED);
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

// 1. รับค่าปีงบประมาณจากหน้าเว็บ Modal
$selected_year = isset($_GET['year']) ? $_GET['year'] : (date('Y') + 543);

// 2. เชื่อมต่อฐานข้อมูล
$host = "school_db"; // ชื่อ Service Database 
$user = "root";      
$pass = "root1234"; // <--- เปลี่ยนรหัสผ่านตรงนี้ด้วยนะครับ
$dbname = "inventory_db";
$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

// ฟังก์ชันแปลงวันที่ SQL (เช่น 2568-08-19) เป็นภาษาไทยแบบย่อ
function ThaiDate($date_str) {
    if (!$date_str || $date_str == '0000-00-00') return '-';
    $th_months = array(
        "01"=>"ม.ค.", "02"=>"ก.พ.", "03"=>"มี.ค.", "04"=>"เม.ย.", "05"=>"พ.ค.", "06"=>"มิ.ย.", 
        "07"=>"ก.ค.", "08"=>"ส.ค.", "09"=>"ก.ย.", "10"=>"ต.ค.", "11"=>"พ.ย.", "12"=>"ธ.ค."
    );
    $time = strtotime($date_str);
    $d = date("j", $time);
    $m = date("m", $time);
    $y = date("Y", $time);
    // ถ้าปีเก็บมาเป็น 202x ให้บวก 543 แต่ถ้าเก็บเป็น 256x อยู่แล้วให้คงเดิม
    if ($y < 2500) $y += 543; 
    return $d . " " . $th_months[$m] . " " . $y;
}

// 3. โหลดไฟล์ Word Template
$templateProcessor = new TemplateProcessor('template_report.docx');

// แทนที่ตัวแปรส่วนหัวกระดาษ
$templateProcessor->setValue('year', $selected_year);
$templateProcessor->setValue('report_date', '31 ตุลาคม ' . $selected_year);

// 4. ดึงข้อมูลพัสดุจากฐานข้อมูล 
// (สมมติว่าดึงเฉพาะรายการที่สถานะ repair(ซ่อม/ชำรุด) และ writeoff(รอจำหน่าย))
$sql = "SELECT * FROM assets WHERE status IN ('repair', 'writeoff') ORDER BY id ASC";
$result = $conn->query($sql);
$rowCount = $result->num_rows;

if ($rowCount > 0) {
    // โคลนบรรทัดตารางตามจำนวนข้อมูลที่เจอ
    $templateProcessor->cloneRow('n', $rowCount);
    
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        // รวมชื่อและรุ่นเข้าด้วยกัน
        $itemName = $row['name'] . ($row['model'] ? ' รุ่น ' . $row['model'] : '');
        
        $templateProcessor->setValue('n#' . $i, $i);
        $templateProcessor->setValue('item_name#' . $i, htmlspecialchars($itemName));
        $templateProcessor->setValue('item_code#' . $i, htmlspecialchars($row['code']));
        
        // เช็คทำเครื่องหมาย / ลงในช่องสถานะความเสียหาย
        $templateProcessor->setValue('s1#' . $i, ($row['status'] == 'repair') ? '/' : '');   // ชำรุด
        $templateProcessor->setValue('s2#' . $i, ($row['status'] == 'writeoff') ? '/' : ''); // เสื่อมสภาพ/รอจำหน่าย
        $templateProcessor->setValue('s3#' . $i, ''); // สูญไป
        $templateProcessor->setValue('s4#' . $i, ''); // ไม่จำเป็น
        
        // รูปแบบวันที่และราคา
        $templateProcessor->setValue('receive_date#' . $i, ThaiDate($row['receive_date']));
        $templateProcessor->setValue('cost#' . $i, number_format($row['cost'], 2));
        
        $templateProcessor->setValue('custodian#' . $i, htmlspecialchars($row['custodian']));
        $templateProcessor->setValue('location#' . $i, htmlspecialchars($row['location']));
        $templateProcessor->setValue('remark#' . $i, ''); 
        
        $i++;
    }
} else {
    // กรณีไม่มีของชำรุดเลยในปีนั้น
    $templateProcessor->cloneRow('n', 1);
    $templateProcessor->setValue('n#1', '-');
    $templateProcessor->setValue('item_name#1', 'ไม่มีรายการพัสดุชำรุดในปีงบประมาณนี้');
    $templateProcessor->setValue('item_code#1', '-');
    $templateProcessor->setValue('s1#1', '-');
    $templateProcessor->setValue('s2#1', '-');
    $templateProcessor->setValue('s3#1', '-');
    $templateProcessor->setValue('s4#1', '-');
    $templateProcessor->setValue('receive_date#1', '-');
    $templateProcessor->setValue('cost#1', '-');
    $templateProcessor->setValue('custodian#1', '-');
    $templateProcessor->setValue('location#1', '-');
    $templateProcessor->setValue('remark#1', '-');
}

// 5. ส่งออกไฟล์ให้เบราว์เซอร์ดาวน์โหลด
$fileName = 'รายงานตรวจสอบพัสดุประจำปี_' . $selected_year . '.docx';
header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$templateProcessor->saveAs("php://output");
exit;
?>