<?php
require_once __DIR__ . '/config/db_inventory.php';

$sql = "ALTER TABLE procurement_orders 
        MODIFY money_group ENUM('operation','salary','special','income','subsidy','investment','support') 
        NOT NULL DEFAULT 'operation'";

if ($inv_conn->query($sql)) {
    echo "Successfully updated money_group column.\n";
} else {
    echo "Error updating column: " . $inv_conn->error . "\n";
}
?>
