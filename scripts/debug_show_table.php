<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$table = $_GET['table'] ?? 'users';
$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

$conn = createConnection(true);
if (!$conn) {
    die('Connection failed');
}

$res = $conn->query("SHOW CREATE TABLE `$table`");
if ($res && $res->num_rows) {
    $row = $res->fetch_assoc();
    echo '<pre>' . htmlspecialchars($row['Create Table']) . '</pre>';
} else {
    echo 'No table ' . htmlspecialchars($table);
}
?>