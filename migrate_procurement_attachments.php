<?php
require_once __DIR__ . '/backend/config/db_inventory.php';

echo "Migrating procurement_orders...\n";

try {
    inv_exec("ALTER TABLE procurement_orders ADD COLUMN attachment_path VARCHAR(255) NULL AFTER note");
    echo "Added column: attachment_path\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column attachment_path already exists.\n";
    } else {
        echo "Error attachment_path: " . $e->getMessage() . "\n";
    }
}

try {
    inv_exec("ALTER TABLE procurement_orders ADD COLUMN is_asset_related TINYINT(1) DEFAULT 0 AFTER attachment_path");
    echo "Added column: is_asset_related\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column is_asset_related already exists.\n";
    } else {
        echo "Error is_asset_related: " . $e->getMessage() . "\n";
    }
}

echo "Migration finished.\n";
