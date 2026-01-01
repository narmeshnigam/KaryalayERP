<?php
/**
 * Setup script for Contacts Management Module
 * Creates all required tables for the Contacts module
 */

require_once __DIR__ . '/../config/db_connect.php';

function setup_contacts_module($conn) {
    $errors = [];
    $tables_created = [];
    
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'contacts'");
    $already_exists = $check && mysqli_num_rows($check) > 0;

    // Table 1: contacts
    $sql_contacts = "CREATE TABLE IF NOT EXISTS `contacts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `organization` VARCHAR(150) NULL,
        `designation` VARCHAR(100) NULL,
        `contact_type` ENUM('Client','Vendor','Partner','Personal','Other') NOT NULL DEFAULT 'Personal',
        `phone` VARCHAR(20) NULL,
        `alt_phone` VARCHAR(20) NULL,
        `email` VARCHAR(150) NULL,
        `whatsapp` VARCHAR(20) NULL,
        `linkedin` VARCHAR(200) NULL,
        `address` VARCHAR(255) NULL,
        `tags` TEXT NULL,
        `notes` TEXT NULL,
        `linked_entity_id` INT NULL,
        `linked_entity_type` ENUM('Client','Project','Lead','Other') NULL,
        `share_scope` ENUM('Private','Team','Organization') NOT NULL DEFAULT 'Private',
        `created_by` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_name` (`name`),
        INDEX `idx_contact_type` (`contact_type`),
        INDEX `idx_email` (`email`),
        INDEX `idx_phone` (`phone`),
        INDEX `idx_created_by` (`created_by`),
        INDEX `idx_share_scope` (`share_scope`),
        INDEX `idx_linked_entity` (`linked_entity_id`, `linked_entity_type`),
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_contacts) === TRUE) {
        $tables_created[] = 'contacts';
    } else {
        $errors[] = "contacts: " . $conn->error;
    }

    // Table 2: contact_groups
    $sql_groups = "CREATE TABLE IF NOT EXISTS `contact_groups` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `description` TEXT NULL,
        `created_by` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_name` (`name`),
        INDEX `idx_created_by` (`created_by`),
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_groups) === TRUE) {
        $tables_created[] = 'contact_groups';
    } else {
        $errors[] = "contact_groups: " . $conn->error;
    }

    // Table 3: contact_group_map
    $sql_group_map = "CREATE TABLE IF NOT EXISTS `contact_group_map` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `group_id` INT NOT NULL,
        `contact_id` INT NOT NULL,
        INDEX `idx_group_id` (`group_id`),
        INDEX `idx_contact_id` (`contact_id`),
        UNIQUE KEY `unique_group_contact` (`group_id`, `contact_id`),
        FOREIGN KEY (`group_id`) REFERENCES `contact_groups`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_group_map) === TRUE) {
        $tables_created[] = 'contact_group_map';
    } else {
        $errors[] = "contact_group_map: " . $conn->error;
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('; ', $errors)];
    }
    
    if ($already_exists) {
        return ['success' => true, 'message' => 'Contacts tables already exist or were verified successfully.'];
    }
    
    return ['success' => true, 'message' => 'Contacts module tables created: ' . implode(', ', $tables_created)];
}

// Only run HTML output if called directly
if (php_sapi_name() !== 'cli' && !defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $conn = createConnection();
    $result = setup_contacts_module($conn);
    $conn->close();
    
    echo "<h2>Setting up Contacts Management Module</h2>";
    echo "<p>" . ($result['success'] ? "✅ " : "❌ ") . htmlspecialchars($result['message']) . "</p>";
    if ($result['success']) {
        echo "<p><a href='/KaryalayERP/public/contacts/'>Go to Contacts Module</a></p>";
    }
}
