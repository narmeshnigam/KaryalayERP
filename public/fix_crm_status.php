<?php
/**
 * Web-based migration script to fix CRM leads status column ENUM values
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Simple authentication check - you can access this directly
$authenticated = true;

if (!$authenticated) {
    die('Access denied');
}

echo "<h2>CRM Leads Status Column Migration</h2>";

// Check if connection was established
if (!$conn) {
    echo "<p style='color: red;'>❌ Error: Could not establish database connection</p>";
    exit(1);
}

echo "<p>✅ Database connection established</p>";

// Check current table structure
echo "<h3>Current crm_leads table structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE crm_leads");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error describing table: " . mysqli_error($conn) . "</p>";
}

// Update the status column to include all the status values used by the application
echo "<h3>Updating status column...</h3>";
$sql = "ALTER TABLE crm_leads MODIFY COLUMN status ENUM('Prospecting','Potential','Hot','Not Interested','Junk','Negotiation','Unqualified','Interested','Demo Completed','New','Contacted','Converted','Dropped') NOT NULL DEFAULT 'Prospecting'";

if (mysqli_query($conn, $sql)) {
    echo "<p style='color: green;'>✅ Successfully updated crm_leads status column ENUM values</p>";
    
    // Update any existing 'New' status records to 'Prospecting' to match the application logic
    $updateSql = "UPDATE crm_leads SET status = 'Prospecting' WHERE status = 'New'";
    if (mysqli_query($conn, $updateSql)) {
        $affected = mysqli_affected_rows($conn);
        echo "<p style='color: green;'>✅ Updated $affected existing records from 'New' to 'Prospecting' status</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Warning: Could not update existing records: " . mysqli_error($conn) . "</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>🎉 Migration completed successfully!</p>";
    
    // Show updated table structure
    echo "<h3>Updated crm_leads table structure:</h3>";
    $result = mysqli_query($conn, "DESCRIBE crm_leads");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Error updating status column: " . mysqli_error($conn) . "</p>";
}

closeConnection($conn);

echo "<br><p><a href='crm/leads/add.php'>Test adding a new lead</a></p>";
?>