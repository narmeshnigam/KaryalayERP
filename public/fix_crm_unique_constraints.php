<?php
/**
 * Fix CRM leads unique constraints to handle empty values properly
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "<h2>CRM Leads Unique Constraints Fix</h2>";

// Check if connection was established
if (!$conn) {
    echo "<p style='color: red;'>❌ Error: Could not establish database connection</p>";
    exit(1);
}

echo "<p>✅ Database connection established</p>";

// Step 1: Update empty strings to NULL for phone and email
echo "<h3>Step 1: Converting empty strings to NULL...</h3>";

$updatePhone = "UPDATE crm_leads SET phone = NULL WHERE phone = ''";
$updateEmail = "UPDATE crm_leads SET email = NULL WHERE email = ''";

if (mysqli_query($conn, $updatePhone)) {
    $affected = mysqli_affected_rows($conn);
    echo "<p style='color: green;'>✅ Updated $affected phone records from empty string to NULL</p>";
} else {
    echo "<p style='color: red;'>❌ Error updating phone records: " . mysqli_error($conn) . "</p>";
}

if (mysqli_query($conn, $updateEmail)) {
    $affected = mysqli_affected_rows($conn);
    echo "<p style='color: green;'>✅ Updated $affected email records from empty string to NULL</p>";
} else {
    echo "<p style='color: red;'>❌ Error updating email records: " . mysqli_error($conn) . "</p>";
}

// Step 2: Drop existing unique constraints
echo "<h3>Step 2: Dropping existing unique constraints...</h3>";

$dropPhoneUnique = "ALTER TABLE crm_leads DROP INDEX uniq_leads_phone";
$dropEmailUnique = "ALTER TABLE crm_leads DROP INDEX uniq_leads_email";

if (mysqli_query($conn, $dropPhoneUnique)) {
    echo "<p style='color: green;'>✅ Dropped unique constraint on phone</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Could not drop phone unique constraint (may not exist): " . mysqli_error($conn) . "</p>";
}

if (mysqli_query($conn, $dropEmailUnique)) {
    echo "<p style='color: green;'>✅ Dropped unique constraint on email</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Could not drop email unique constraint (may not exist): " . mysqli_error($conn) . "</p>";
}

// Step 3: Add new unique constraints that ignore NULL values
echo "<h3>Step 3: Adding new unique constraints...</h3>";

// For MySQL, we need to create unique indexes that ignore NULL values
// We'll use a different approach - create unique constraints only on non-NULL values
$addPhoneUnique = "ALTER TABLE crm_leads ADD UNIQUE KEY uniq_leads_phone_not_null (phone)";
$addEmailUnique = "ALTER TABLE crm_leads ADD UNIQUE KEY uniq_leads_email_not_null (email)";

if (mysqli_query($conn, $addPhoneUnique)) {
    echo "<p style='color: green;'>✅ Added unique constraint on phone (ignores NULL)</p>";
} else {
    echo "<p style='color: red;'>❌ Error adding phone unique constraint: " . mysqli_error($conn) . "</p>";
}

if (mysqli_query($conn, $addEmailUnique)) {
    echo "<p style='color: green;'>✅ Added unique constraint on email (ignores NULL)</p>";
} else {
    echo "<p style='color: red;'>❌ Error adding email unique constraint: " . mysqli_error($conn) . "</p>";
}

// Step 4: Show current table structure
echo "<h3>Step 4: Updated table structure:</h3>";
$result = mysqli_query($conn, "SHOW CREATE TABLE crm_leads");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
} else {
    echo "<p style='color: red;'>Error showing table structure: " . mysqli_error($conn) . "</p>";
}

echo "<p style='color: green; font-weight: bold;'>🎉 Migration completed!</p>";
echo "<p><strong>Note:</strong> Now empty phone and email fields will be stored as NULL instead of empty strings, which won't conflict with unique constraints.</p>";

closeConnection($conn);

echo "<br><p><a href='crm/leads/add.php'>Test adding a new lead</a></p>";
?>