<?php
/**
 * Catalog Module Database Setup Script
 * Creates tables for Products & Services (Catalog + Inventory) Module
 * 
 * Tables:
 * - items_master: Main catalog table (products and services)
 * - item_inventory_log: Stock movement history with full audit trail
 * - item_files: File attachments (images, brochures)
 * - item_change_log: Item modification audit trail
 */

require_once __DIR__ . '/../config/db_connect.php';

function setup_catalog_tables($conn) {
    $errors = [];
    $tables_created = [];
    
    // Check if main table already exists
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'items_master'");
    $already_exists = $check && mysqli_num_rows($check) > 0;
    
    $tables = [
        'items_master' => "CREATE TABLE IF NOT EXISTS items_master (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(40) NOT NULL UNIQUE,
            name VARCHAR(200) NOT NULL,
            type ENUM('Product', 'Service') NOT NULL DEFAULT 'Product',
            category VARCHAR(100) NULL,
            description_html LONGTEXT NULL,
            base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            default_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            primary_image TEXT NULL,
            brochure_pdf TEXT NULL,
            expiry_date DATE NULL,
            current_stock INT NOT NULL DEFAULT 0,
            low_stock_threshold INT NULL,
            status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_items_sku (sku),
            INDEX idx_items_type_status (type, status),
            INDEX idx_items_category (category),
            INDEX idx_items_status (status),
            INDEX idx_items_expiry (expiry_date),
            INDEX idx_items_stock (current_stock)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'item_inventory_log' => "CREATE TABLE IF NOT EXISTS item_inventory_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            action ENUM('Add', 'Reduce', 'InvoiceDeduct', 'Correction') NOT NULL,
            quantity_delta INT NOT NULL COMMENT 'Positive for adds, negative for reductions',
            qty_before INT NOT NULL,
            qty_after INT NOT NULL,
            reason VARCHAR(255) NULL,
            reference_type ENUM('Invoice', 'Manual', 'Other') NOT NULL DEFAULT 'Manual',
            reference_id INT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_inventory_item (item_id),
            INDEX idx_inventory_created (created_at),
            INDEX idx_inventory_action (action),
            INDEX idx_inventory_reference (reference_type, reference_id),
            FOREIGN KEY (item_id) REFERENCES items_master(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'item_files' => "CREATE TABLE IF NOT EXISTS item_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            file_type ENUM('PrimaryImage', 'Brochure') NOT NULL,
            file_path TEXT NOT NULL,
            uploaded_by INT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_files_item (item_id),
            INDEX idx_files_type (file_type),
            FOREIGN KEY (item_id) REFERENCES items_master(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'item_change_log' => "CREATE TABLE IF NOT EXISTS item_change_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            change_type ENUM('Create', 'Update', 'Activate', 'Deactivate', 'PriceChange', 'FileChange') NOT NULL,
            changed_fields TEXT NULL COMMENT 'JSON of before/after values',
            changed_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_change_item (item_id),
            INDEX idx_change_type (change_type),
            INDEX idx_change_created (created_at),
            FOREIGN KEY (item_id) REFERENCES items_master(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    // Execute table creation
    foreach ($tables as $name => $sql) {
        if ($conn->query($sql)) {
            $tables_created[] = $name;
        } else {
            $errors[] = "Error creating '$name': " . $conn->error;
        }
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('; ', $errors)];
    }
    
    if ($already_exists) {
        return ['success' => true, 'message' => 'Catalog tables already exist or were verified successfully.'];
    }
    
    return ['success' => true, 'message' => 'Catalog module tables created: ' . implode(', ', $tables_created)];
}

// Run setup if called directly (not included)
if (!defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $conn = createConnection();
    echo "Setting up Catalog Module Tables...\n";
    echo str_repeat("=", 50) . "\n";
    
    $result = setup_catalog_tables($conn);
    if ($result['success']) {
        echo "\n✓ " . $result['message'] . "\n";
    } else {
        echo "\n✗ " . $result['message'] . "\n";
    }
    
    $conn->close();
}
