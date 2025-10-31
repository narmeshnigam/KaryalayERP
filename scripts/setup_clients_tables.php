<?php
/**
 * Setup script for Clients Module
 * Creates all required tables for the Clients module
 */

require_once __DIR__ . '/../config/db_connect.php';

echo "<h2>Setting up Clients Module</h2>";

// Table 1: clients
$sql_clients = "
CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) UNIQUE NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `legal_name` VARCHAR(200) NULL,
    `industry` VARCHAR(100) NULL,
    `website` VARCHAR(150) NULL,
    `email` VARCHAR(150) NULL,
    `phone` VARCHAR(20) NULL,
    `gstin` VARCHAR(50) NULL,
    `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `owner_id` INT UNSIGNED NOT NULL,
    `lead_id` INT NULL,
    `tags` TEXT NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_code` (`code`),
    INDEX `idx_name` (`name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_owner_id` (`owner_id`),
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_phone` (`phone`),
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_clients) === TRUE) {
    echo "<p>✅ Table 'clients' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'clients' table: " . $conn->error . "</p>";
}

// Table 2: client_addresses
$sql_addresses = "
CREATE TABLE IF NOT EXISTS `client_addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `label` VARCHAR(50) NOT NULL,
    `line1` VARCHAR(255) NOT NULL,
    `line2` VARCHAR(255) NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `zip` VARCHAR(20) NOT NULL,
    `country` VARCHAR(100) NOT NULL DEFAULT 'India',
    `is_default` BOOLEAN DEFAULT 0,
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_label` (`label`),
    INDEX `idx_is_default` (`is_default`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_addresses) === TRUE) {
    echo "<p>✅ Table 'client_addresses' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'client_addresses' table: " . $conn->error . "</p>";
}

// Table 3: client_contacts_map
$sql_contacts_map = "
CREATE TABLE IF NOT EXISTS `client_contacts_map` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `contact_id` INT NOT NULL,
    `role_at_client` VARCHAR(100) NULL,
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_contact_id` (`contact_id`),
    UNIQUE KEY `unique_client_contact` (`client_id`, `contact_id`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_contacts_map) === TRUE) {
    echo "<p>✅ Table 'client_contacts_map' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'client_contacts_map' table: " . $conn->error . "</p>";
}

// Table 4: client_documents
$sql_documents = "
CREATE TABLE IF NOT EXISTS `client_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` TEXT NOT NULL,
    `doc_type` VARCHAR(100) NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_doc_type` (`doc_type`),
    INDEX `idx_uploaded_by` (`uploaded_by`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_documents) === TRUE) {
    echo "<p>✅ Table 'client_documents' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'client_documents' table: " . $conn->error . "</p>";
}

// Table 5: client_custom_fields
$sql_custom_fields = "
CREATE TABLE IF NOT EXISTS `client_custom_fields` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `field_key` VARCHAR(50) NOT NULL,
    `field_value` VARCHAR(200) NULL,
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_field_key` (`field_key`),
    UNIQUE KEY `unique_client_field` (`client_id`, `field_key`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_custom_fields) === TRUE) {
    echo "<p>✅ Table 'client_custom_fields' created successfully.</p>";
} else {
    echo "<p>❌ Error creating 'client_custom_fields' table: " . $conn->error . "</p>";
}

// Create uploads directory
$upload_dir = __DIR__ . '/../uploads/clients';
if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p>✅ Created uploads directory: /uploads/clients/</p>";
    } else {
        echo "<p>⚠️ Failed to create uploads directory. Please create manually: /uploads/clients/</p>";
    }
} else {
    echo "<p>✅ Uploads directory already exists: /uploads/clients/</p>";
}

echo "<hr>";
echo "<h3>✅ Clients Module setup complete!</h3>";
echo "<p><a href='/KaryalayERP/public/clients/'>Go to Clients Module</a></p>";

$conn->close();
?>
