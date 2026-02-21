<?php
$host = "db"; // Adjust according to your docker setup
$user = "root";
$pass = "example";
$dbname = "school_system"; // Adjust to your actual database name if different

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
