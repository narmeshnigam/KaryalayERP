<?php
/**
 * Migration script to fix CRM leads status column ENUM values
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db_connect.php';

echo "Starting CRM leads status column migration...\n";

// Check if connection was established
if (!$conn) {
    echo "❌ Error: Could not establish database connection\n";
    exit(1);
}

// Update the status column to include all the status values used by the application
$sql = "ALTER TABLE crm_leads MODIFY COLUMN status ENUM('Prospecting','Potential','Hot','Not Interested','Junk','Negotiation','Unqualified','Interested','Demo Completed','New','Contacted','Converted','Dropped') NOT NULL DEFAULT 'Prospecting'";

if (mysqli_query($conn, $sql)) {
    echo "✅ Successfully updated crm_leads status column ENUM values\n";
    
    // Update any existing 'New' status records to 'Prospecting' to match the application logic
    $updateSql = "UPDATE crm_leads SET status = 'Prospecting' WHERE status = 'New'";
    if (mysqli_query($conn, $updateSql)) {
        $affected = mysqli_affected_rows($conn);
        echo "✅ Updated $affected existing records from 'New' to 'Prospecting' status\n";
    } else {
        echo "⚠️  Warning: Could not update existing records: " . mysqli_error($conn) . "\n";
    }
    
    echo "🎉 Migration completed successfully!\n";
} else {
    echo "❌ Error updating status column: " . mysqli_error($conn) . "\n";
    exit(1);
}

closeConnection($conn);
?>