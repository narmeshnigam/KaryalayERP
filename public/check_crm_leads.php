<?php
/**
 * Check existing CRM leads data
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "<h2>CRM Leads Data Check</h2>";

// Check if connection was established
if (!$conn) {
    echo "<p style='color: red;'>❌ Error: Could not establish database connection</p>";
    exit(1);
}

echo "<p>✅ Database connection established</p>";

// Check if crm_leads table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'crm_leads'");
if ($result && mysqli_num_rows($result) > 0) {
    echo "<p>✅ crm_leads table exists</p>";
    
    // Check current data
    $result = mysqli_query($conn, "SELECT id, name, status FROM crm_leads LIMIT 10");
    if ($result) {
        echo "<h3>Current leads data (first 10 records):</h3>";
        if (mysqli_num_rows($result) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No leads found in the database</p>";
        }
    } else {
        echo "<p style='color: red;'>Error querying leads: " . mysqli_error($conn) . "</p>";
    }
    
    // Check status column definition
    echo "<h3>Current status column definition:</h3>";
    $result = mysqli_query($conn, "SHOW COLUMNS FROM crm_leads LIKE 'status'");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        }
    }
    
} else {
    echo "<p style='color: red;'>❌ crm_leads table does not exist</p>";
}

closeConnection($conn);

echo "<br><p><a href='fix_crm_status.php'>Run migration to fix status column</a></p>";
?>