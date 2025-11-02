<?php
require_once __DIR__ . '/../../config/db_connect.php';

$id = 2;
$r = mysqli_query($conn, "SELECT * FROM items_master WHERE id=$id");
if ($r && mysqli_num_rows($r) > 0) {
    $row = mysqli_fetch_assoc($r);
    echo "<h3>Raw DB data for item ID $id:</h3>";
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "No record found for ID $id";
}
?>
