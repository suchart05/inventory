<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

$phpWord = new PhpWord();
$section = $phpWord->addSection();

// ใส่ข้อความทดสอบ
$section->addText("สวัสดีครับคุณครูสุชาติ", array('size' => 16));
$section->addText("ระบบจัดการทรัพย์สินโรงเรียน (School Asset Management)", array('bold' => true));

// บันทึกไฟล์
$fileName = 'test_report.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($fileName);

echo "สร้างไฟล์ $fileName สำเร็จแล้ว! ลองเช็คในโฟลเดอร์ดูนะครับ";