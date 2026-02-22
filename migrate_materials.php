<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/backend/config/db_inventory.php';

// Ensure user is logged in (optional, but good practice. For now we just check if it runs)
// if (!isset($_SESSION['inv_user_id'])) {
//     die("Unauthorized. Please log in first.");
// }

$migrations = [
    'location'   => "ALTER TABLE materials ADD COLUMN location VARCHAR(100) DEFAULT NULL COMMENT 'ที่เก็บ' AFTER min_stock",
    'max_stock'  => "ALTER TABLE materials ADD COLUMN max_stock INT DEFAULT NULL COMMENT 'จำนวนอย่างสูง' AFTER location",
    'tx_price'   => "ALTER TABLE material_transactions ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0.00 AFTER qty",
    'tx_bal_qty' => "ALTER TABLE material_transactions ADD COLUMN balance_qty INT DEFAULT 0 AFTER unit_price",
    'tx_bal_prc' => "ALTER TABLE material_transactions ADD COLUMN balance_price DECIMAL(12,2) DEFAULT 0.00 AFTER balance_qty",
];

$successCount = 0;
$errors = [];

foreach ($migrations as $key => $sql) {
    try {
        $result = inv_exec($sql);
        if ($result || $result === 0) { // inv_exec returns true (or affected rows 0)
            $successCount++;
        }
    } catch (Exception $e) {
        // Typically Duplicate column name error
        $errors[] = "Error on $key: " . $e->getMessage();
    }
}

echo "<h2>Migration Material Table</h2>";
echo "<p>Successfully ran $successCount / " . count($migrations) . " statements.</p>";
if (!empty($errors)) {
    echo "<h3>Skipped or Errors:</h3><ul>";
    foreach ($errors as $err) {
        echo "<li>" . htmlspecialchars($err) . "</li>";
    }
    echo "</ul><p>(Note: 'Duplicate column name' is safe to ignore if the column already exists)</p>";
} else {
    echo "<p style='color:green;'>All good! Database schema is ready.</p>";
}
echo "<br><a href='material.php'>Go to Material Page</a>";
?>
