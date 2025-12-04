<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
$conn = createConnection();
if (!$conn) { die('DB connection failed'); }
$res = mysqli_query($conn, "SELECT DISTINCT delivery_status FROM work_order_deliverables");
if (!$res) { die('Error: '.mysqli_error($conn)); }
echo "Existing delivery_status values:\n";
while ($r = mysqli_fetch_assoc($res)) { echo $r['delivery_status'] . "\n"; }
mysqli_close($conn);
?>