<?php
// 1. โหลด Library PHPWord
require_once 'vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

// 2. รับค่าจากหน้า UI
$year           = $_POST['year'] ?? '';
$start_date     = $_POST['start_date'] ?? ''; 
$report_date    = $_POST['report_date'] ?? ''; 
$send_date      = $_POST['send_date'] ?? ''; // <-- เพิ่มรับค่าวันที่หน้า 5
$doc_no_prefix  = $_POST['doc_no'] ?? 'ตส';
$order_no       = $_POST['order_no'] ?? '';
$school_name    = $_POST['school_name'] ?? 'บ้านโคกวิทยา'; // ดึงจาก UI หรือตั้ง Default
$director_name  = $_POST['director_name'] ?? '';
$head_staff     = $_POST['head_staff_name'] ?? '';
$staff_name     = $_POST['staff_name'] ?? '';

// รายชื่อและตำแหน่งกรรมการจาก Dropdown
$comm1_name = $_POST['comm1_name'] ?? '';
$comm1_pos  = $_POST['comm1_pos'] ?? '';
$comm2_name = $_POST['comm2_name'] ?? '';
$comm2_pos  = $_POST['comm2_pos'] ?? '';
$comm3_name = $_POST['comm3_name'] ?? '';
$comm3_pos  = $_POST['comm3_pos'] ?? '';

try {
    // 3. เลือกไฟล์เทมเพลต (ตรวจสอบว่าไฟล์อยู่ในโฟลเดอร์ templates)
    $templatePath = 'templates/ตรวจสอบพัสดุแบบที่ 1 .docx';
    $templateProcessor = new TemplateProcessor($templatePath);

    // 4. แทนที่ตัวแปรพื้นฐาน 
    $templateProcessor->setValue('year', $year);
    $templateProcessor->setValue('start_date', $start_date); 
    $templateProcessor->setValue('report_date', $report_date);
    $templateProcessor->setValue('send_date', $send_date); // <-- นำค่าไปใส่ใน Word หน้า 5
    $templateProcessor->setValue('doc_no', $doc_no_prefix);
    $templateProcessor->setValue('order_no', $order_no);
    $templateProcessor->setValue('school_name', $school_name);
    $templateProcessor->setValue('director_name', $director_name);
    $templateProcessor->setValue('head_staff_name', $head_staff);
    $templateProcessor->setValue('staff_name', $staff_name);

    // 5. แทนที่ชื่อและตำแหน่งกรรมการ (ที่เลือกจาก Dropdown) 
    $templateProcessor->setValue('comm1_name', $comm1_name);
    $templateProcessor->setValue('comm1_pos', $comm1_pos);
    $templateProcessor->setValue('comm2_name', $comm2_name);
    $templateProcessor->setValue('comm2_pos', $comm2_pos);
    $templateProcessor->setValue('comm3_name', $comm3_name);
    $templateProcessor->setValue('comm3_pos', $comm3_pos);

    // 6. จัดการตารางพัสดุหน้า 4 (กรณีแบบที่ 1: ไม่มีชำรุด) 
    $templateProcessor->setValue('n', '1');
    $templateProcessor->setValue('item_name', 'ไม่มีรายการครุภัณฑ์ ชำรุดเสียหาย');
    $templateProcessor->setValue('item_code', '-');
    $templateProcessor->setValue('price', '-');
    $templateProcessor->setValue('location', '-');

    // 7. ตั้งค่าการดาวน์โหลด
    $outputFileName = "รายงานตรวจสอบพัสดุ_" . $school_name . "_" . $year . ".docx";
    
    header("Content-Description: File Transfer");
    header('Content-Disposition: attachment; filename="' . $outputFileName . '"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    $templateProcessor->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>