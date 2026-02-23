<?php
echo "<pre>";
echo "กำลังดาวน์โหลดและติดตั้ง Composer...\n";
echo shell_exec("php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\"");
echo shell_exec("php composer-setup.php");
echo shell_exec("mv composer.phar /usr/local/bin/composer");
echo shell_exec("php -r \"unlink('composer-setup.php');\"");

echo "\nกำลังติดตั้ง PHPWord...\n";
// ใช้ 2>&1 เพื่อให้แสดง Error ออกมาบนหน้าเว็บด้วยถ้ามีปัญหา
echo shell_exec("composer require phpoffice/phpword 2>&1");

echo "\n✅ ติดตั้งเสร็จสมบูรณ์! ลองเช็คโฟลเดอร์ vendor ดูครับ";
echo "</pre>";
?>