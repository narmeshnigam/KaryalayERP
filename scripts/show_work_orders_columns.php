<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
$conn = createConnection();
if (!$conn) { die('DB connection failed'); }
$table = 'work_orders';
$res = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
if (!$res) { die('Error: '.mysqli_error($conn)); }
echo "Columns in $table:\n";
while ($r = mysqli_fetch_assoc($res)) { echo $r['Field'] . "\n"; }
mysqli_close($conn);
?>