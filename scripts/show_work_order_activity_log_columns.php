<?php
require_once __DIR__ . '/../config/db_connect.php';
$conn = createConnection();
$res = mysqli_query($conn, 'SHOW COLUMNS FROM work_order_activity_log');
if (!$res) {
    echo "Query failed: " . mysqli_error($conn);
    exit(1);
}
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
closeConnection($conn);
