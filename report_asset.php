<?php
// ซ่อนคำเตือนที่ไม่จำเป็น
error_reporting(E_ALL & ~E_DEPRECATED);
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

// 1. ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = "school_db"; // หรือ "db" ตามที่คุณครูเชื่อมต่อผ่านตอนแรก
$user = "root";      
$pass = "root1234"; // <-- อย่าลืมเปลี่ยนตรงนี้นะครับ
$dbname = "inventory_db";

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

$phpWord = new PhpWord();

// 2. ตั้งค่าหน้ากระดาษเป็น "แนวนอน" (Landscape)
$section = $phpWord->addSection([
    'orientation' => 'landscape',
    'marginLeft' => 600,
    'marginRight' => 600,
    'marginTop' => 600,
    'marginBottom' => 600
]);

// 3. จัดหัวกระดาษให้อยู่กึ่งกลาง
$fontHeader = array('name' => 'TH Sarabun New', 'size' => 20, 'bold' => true);
$section->addText("บัญชีรายการครุภัณฑ์", $fontHeader, array('alignment' => Jc::CENTER));
$section->addText("โรงเรียนบ้านโคกวิทยา", array('name' => 'TH Sarabun New', 'size' => 18, 'bold' => true), array('alignment' => Jc::CENTER));
$section->addTextBreak(1); // เว้นบรรทัด

// 4. สร้างตารางและกำหนดสัดส่วนความกว้างให้สวยงาม
$styleTable = array('borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80);
$phpWord->addTableStyle('AssetTable', $styleTable);
$table = $section->addTable('AssetTable');

$fontTableHead = array('name' => 'TH Sarabun New', 'size' => 16, 'bold' => true);
$table->addRow();
// ตัวเลขด้านหน้าคือความกว้างของคอลัมน์ (หน่วย twip)
$table->addCell(800)->addText("ลำดับ", $fontTableHead, array('alignment' => Jc::CENTER));
$table->addCell(2500)->addText("รหัสครุภัณฑ์", $fontTableHead, array('alignment' => Jc::CENTER));
$table->addCell(5000)->addText("รายการ (รุ่น/แบบ)", $fontTableHead, array('alignment' => Jc::CENTER));
$table->addCell(2500)->addText("สถานที่ตั้ง", $fontTableHead, array('alignment' => Jc::CENTER));
$table->addCell(2500)->addText("ผู้รับผิดชอบ", $fontTableHead, array('alignment' => Jc::CENTER));

// 5. ดึงข้อมูลมาวนลูปใส่ตาราง
$sql = "SELECT code, name, model, location, custodian FROM assets ORDER BY id ASC";
$result = $conn->query($sql);

$fontTableBody = array('name' => 'TH Sarabun New', 'size' => 16);
$i = 1;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $table->addRow();
        // จัดให้ตัวเลขลำดับอยู่กึ่งกลาง
        $table->addCell(800)->addText($i++, $fontTableBody, array('alignment' => Jc::CENTER));
        $table->addCell(2500)->addText($row['code'], $fontTableBody);
        
        $itemName = $row['name'] . ($row['model'] ? ' (' . $row['model'] . ')' : '');
        $table->addCell(5000)->addText($itemName, $fontTableBody);
        
        $table->addCell(2500)->addText($row['location'], $fontTableBody);
        $table->addCell(2500)->addText($row['custodian'], $fontTableBody);
    }
}

// 6. บันทึกไฟล์
$fileName = 'asset_report_landscape.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($fileName);

echo "<h2>สำเร็จ! ดึงข้อมูลพัสดุมาจัดหน้าแบบแนวนอนเรียบร้อยแล้ว</h2>";
echo "<p>ระบบได้สร้างไฟล์ชื่อ <b>$fileName</b> ไว้ในโฟลเดอร์แล้วครับ</p>";
?>