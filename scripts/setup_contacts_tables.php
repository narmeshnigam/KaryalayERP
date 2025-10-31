<?php
/**
 * Setup script for Contacts Management Module
 * Creates all required tables for the Contacts module
 */

require_once __DIR__ . '/../config/db_connect.php';

echo "<h2>Setting up Contacts Management Module</h2>";

// Table 1: contacts
$sql_contacts = "
CREATE TABLE IF NOT EXISTS `contacts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_contacts) === TRUE) {
    echo "<p>✅ Table 'contacts' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'contacts' table: " . $conn->error . "</p>";
}

// Table 2: contact_groups
$sql_groups = "
CREATE TABLE IF NOT EXISTS `contact_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`),
    INDEX `idx_created_by` (`created_by`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_groups) === TRUE) {
    echo "<p>✅ Table 'contact_groups' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'contact_groups' table: " . $conn->error . "</p>";
}

// Table 3: contact_group_map
$sql_group_map = "
CREATE TABLE IF NOT EXISTS `contact_group_map` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `contact_id` INT NOT NULL,
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_contact_id` (`contact_id`),
    UNIQUE KEY `unique_group_contact` (`group_id`, `contact_id`),
    FOREIGN KEY (`group_id`) REFERENCES `contact_groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_group_map) === TRUE) {
    echo "<p>✅ Table 'contact_group_map' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'contact_group_map' table: " . $conn->error . "</p>";
}

echo "<hr>";
echo "<h3>✅ Contacts Management Module setup complete!</h3>";
echo "<p><a href='/KaryalayERP/public/contacts/'>Go to Contacts Module</a></p>";

$conn->close();
?>
