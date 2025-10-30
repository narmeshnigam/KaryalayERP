<?php
/**
 * Export Employees (CSV for Excel)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$where = [];
if ($search) $where[] = "(employee_code LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR official_email LIKE '%$search%' OR mobile_number LIKE '%$search%')";
if ($department_filter) $where[] = "department = '$department_filter'";
if ($status_filter) $where[] = "status = '$status_filter'";
$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT * FROM employees $where_sql ORDER BY created_at DESC";
$res = mysqli_query($conn, $sql);

if (!$res) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: index.php?export_error=1');
    exit;
}

$filename = 'employees_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$fh = fopen('php://output', 'w');
// UTF-8 BOM for Excel so Excel reads UTF-8 correctly
fputs($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));

$fields = mysqli_fetch_fields($res);
$headers = [];
foreach ($fields as $field) {
    $headers[] = $field->name;
}
fputcsv($fh, $headers);

mysqli_data_seek($res, 0);
while ($row = mysqli_fetch_assoc($res)) {
    $line = [];
    foreach ($headers as $column) {
        $value = $row[$column];
        if (is_null($value)) {
            $line[] = '';
            continue;
        }

        if (is_bool($value)) {
            $line[] = $value ? '1' : '0';
            continue;
        }

        $line[] = $value;
    }
    fputcsv($fh, $line);
}

fclose($fh);
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
exit;
