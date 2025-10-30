<?php
/**
 * Export Attendance to CSV
 * Exports filtered attendance records to CSV format
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Filter parameters (same as index.php)
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$employee_filter = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

// Build query with filters
$where_conditions = ["a.attendance_date BETWEEN '$from_date' AND '$to_date'"];

if ($employee_filter > 0) {
    $where_conditions[] = "e.id = $employee_filter";
}

if (!empty($status_filter)) {
    $status_filter_safe = mysqli_real_escape_string($conn, $status_filter);
    $where_conditions[] = "a.status = '$status_filter_safe'";
}

if (!empty($department_filter)) {
    $department_filter_safe = mysqli_real_escape_string($conn, $department_filter);
    $where_conditions[] = "e.department = '$department_filter_safe'";
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch attendance records
$sql = "SELECT 
    a.attendance_date,
    e.employee_code,
    CONCAT(e.first_name, ' ', IFNULL(e.middle_name, ''), ' ', e.last_name) as employee_name,
    e.department,
    e.designation,
    a.status,
    a.check_in_time,
    a.check_out_time,
    a.checkin_latitude,
    a.checkin_longitude,
    a.checkout_latitude,
    a.checkout_longitude,
    a.total_hours,
    a.late_by_minutes,
    a.early_leave_minutes,
    a.overtime_minutes,
    a.work_from_home,
    a.leave_type,
    a.remarks,
    a.approval_status
        FROM attendance a
        INNER JOIN employees e ON a.employee_id = e.id
        WHERE $where_clause
        ORDER BY a.attendance_date DESC, e.employee_code";

$result = mysqli_query($conn, $sql);

if (!$result) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: index.php?export_error=1');
    exit;
}

// Set headers for CSV download
$filename = "attendance_report_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
$headers = [
    'Attendance Date',
    'Employee Code',
    'Employee Name',
    'Department',
    'Designation',
    'Status',
    'Check-In Time',
    'Check-Out Time',
    'Check-In Latitude',
    'Check-In Longitude',
    'Check-Out Latitude',
    'Check-Out Longitude',
    'Total Hours',
    'Late (minutes)',
    'Early Leave (minutes)',
    'Overtime (minutes)',
    'Work From Home',
    'Leave Type',
    'Remarks',
    'Approval Status'
];

fputcsv($output, $headers);

// Write data rows
while ($row = mysqli_fetch_assoc($result)) {
    $csv_row = [
        $row['attendance_date'],
        $row['employee_code'],
    trim(preg_replace('/\s+/', ' ', $row['employee_name'] ?? '')),
        $row['department'] ?? '',
        $row['designation'] ?? '',
        $row['status'],
        $row['check_in_time'] ?? '',
        $row['check_out_time'] ?? '',
        $row['checkin_latitude'] ?? '',
        $row['checkin_longitude'] ?? '',
        $row['checkout_latitude'] ?? '',
        $row['checkout_longitude'] ?? '',
        $row['total_hours'] ?? '',
        $row['late_by_minutes'] ?? '',
        $row['early_leave_minutes'] ?? '',
        $row['overtime_minutes'] ?? '',
        $row['work_from_home'] ?? '',
        $row['leave_type'] ?? '',
        $row['remarks'] ?? '',
        $row['approval_status'] ?? ''
    ];
    
    fputcsv($output, $csv_row);
}

fclose($output);
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
exit;
?>
