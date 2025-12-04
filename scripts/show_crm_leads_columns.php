<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection();
$table = 'crm_leads';

$result = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
if (!$result) {
    die('Error fetching columns: ' . mysqli_error($conn));
}
echo "Columns in $table:\n";
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . "\n";
}
mysqli_close($conn);
?>
