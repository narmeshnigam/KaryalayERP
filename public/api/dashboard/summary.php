<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'unauthorized']); exit; }
$conn = createConnection(true);
if (!$conn) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'db']); exit; }

function t_exists(mysqli $c, string $t): bool {
  $t = mysqli_real_escape_string($c, $t);
  $r = mysqli_query($c, "SHOW TABLES LIKE '$t'");
  $ok = ($r && mysqli_num_rows($r) > 0);
  if ($r) mysqli_free_result($r);
  return $ok;
}
function scalar(mysqli $c, string $sql, array $params = [], string $types = '') {
  $val = null; $stmt = null;
  if ($params) { $stmt = mysqli_prepare($c, $sql); if ($stmt) { mysqli_stmt_bind_param($stmt, $types, ...$params); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); if ($res) { $row = mysqli_fetch_row($res); $val = $row ? $row[0] : null; mysqli_free_result($res);} mysqli_stmt_close($stmt);} }
  else { $res = mysqli_query($c, $sql); if ($res) { $row = mysqli_fetch_row($res); $val = $row ? $row[0] : null; mysqli_free_result($res);} }
  return $val === null ? 0 : $val;
}

$now = new DateTime();
$month = $now->format('Y-m');
$startMonth = $now->format('Y-m-01');
$endMonth = $now->format('Y-m-t');
$prevStart = (new DateTime($startMonth))->modify('-1 month')->format('Y-m-01');
$prevEnd   = (new DateTime($startMonth))->modify('-1 day')->format('Y-m-d');

$out = [
  'success' => true,
  'crm_total_leads' => 0,
  'crm_conversion_rate' => 0.0,
  'total_employees' => 0,
  'attendance_present_today' => 0,
  'my_attendance_status' => null,
  'my_hours_logged' => 0,
  'pending_tasks' => 0,
  'overdue_tasks' => 0,
  'expenses_month_total' => 0,
  'salary_payroll_completed_pct' => 0,
  'visitors_today' => 0,
  'visitors_note' => '',
  'deltas' => []
];

$employee_id = (int)($_SESSION['employee_id'] ?? 0);

// Employees
if (t_exists($conn, 'employees')) {
  $out['total_employees'] = (int)scalar($conn, "SELECT COUNT(*) FROM employees WHERE status = 'Active'");
}

// Attendance
if (t_exists($conn, 'attendance')) {
  $out['attendance_present_today'] = (int)scalar($conn, "SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'Present'");
  if ($employee_id) {
    $row = scalar($conn, "SELECT status FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()", [$employee_id], 'i');
    $out['my_attendance_status'] = $row ?: '—';
    $hours = scalar($conn, "SELECT COALESCE(SUM(total_hours),0) FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?", [$employee_id, $startMonth, $endMonth], 'iss');
    $out['my_hours_logged'] = (float)$hours;
  }
}

// CRM
if (t_exists($conn, 'crm_leads')) {
  $totalLeads = (int)scalar($conn, "SELECT COUNT(*) FROM crm_leads WHERE created_at BETWEEN ? AND ? AND deleted_at IS NULL", [$startMonth.' 00:00:00', $endMonth.' 23:59:59'], 'ss');
  $conv = (int)scalar($conn, "SELECT COUNT(*) FROM crm_leads WHERE status = 'Converted' AND created_at BETWEEN ? AND ? AND deleted_at IS NULL", [$startMonth.' 00:00:00', $endMonth.' 23:59:59'], 'ss');
  $out['crm_total_leads'] = $totalLeads;
  $out['crm_conversion_rate'] = $totalLeads ? round(($conv/$totalLeads)*100,1) : 0.0;

  $prevTotal = (int)scalar($conn, "SELECT COUNT(*) FROM crm_leads WHERE created_at BETWEEN ? AND ? AND deleted_at IS NULL", [$prevStart.' 00:00:00', $prevEnd.' 23:59:59'], 'ss');
  $prevConv = (int)scalar($conn, "SELECT COUNT(*) FROM crm_leads WHERE status = 'Converted' AND created_at BETWEEN ? AND ? AND deleted_at IS NULL", [$prevStart.' 00:00:00', $prevEnd.' 23:59:59'], 'ss');
  $prevRate = $prevTotal ? round(($prevConv/$prevTotal)*100,1) : 0.0;
  $out['deltas']['crm_total_leads'] = $totalLeads - $prevTotal;
  $out['deltas']['crm_conversion_rate'] = round($out['crm_conversion_rate'] - $prevRate, 1);
}

// Tasks
if (t_exists($conn, 'crm_tasks')) {
  $out['pending_tasks'] = (int)scalar($conn, "SELECT COUNT(*) FROM crm_tasks WHERE deleted_at IS NULL AND status <> 'Completed'");
  $out['overdue_tasks'] = (int)scalar($conn, "SELECT COUNT(*) FROM crm_tasks WHERE deleted_at IS NULL AND status <> 'Completed' AND due_date IS NOT NULL AND due_date < CURDATE()");
}

// Expenses
if (t_exists($conn, 'office_expenses')) {
  $out['expenses_month_total'] = (float)scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM office_expenses WHERE date BETWEEN ? AND ?", [$startMonth, $endMonth], 'ss');
  $prevExp = (float)scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM office_expenses WHERE date BETWEEN ? AND ?", [$prevStart, $prevEnd], 'ss');
  $out['deltas']['expenses_month_total'] = round($out['expenses_month_total'] - $prevExp, 2);
}

// Reimbursements (pending claims)
if (t_exists($conn, 'reimbursements')) {
  $out['claims_pending'] = (int)scalar($conn, "SELECT COUNT(*) FROM reimbursements WHERE status='Pending'");
}

// Salary payroll completion
if (t_exists($conn, 'salary_records') && $out['total_employees']>0) {
  $paid = (int)scalar($conn, "SELECT COUNT(DISTINCT employee_id) FROM salary_records WHERE month = ? AND is_locked = 1", [$month], 's');
  $out['salary_payroll_completed_pct'] = round(($paid / max(1,$out['total_employees'])) * 100, 0);
}

// Visitors today
if (t_exists($conn, 'visitor_logs')) {
  $out['visitors_today'] = (int)scalar($conn, "SELECT COUNT(*) FROM visitor_logs WHERE DATE(check_in_time) = CURDATE() AND deleted_at IS NULL");
  $out['visitors_note'] = 'Auto count since midnight';
}

echo json_encode($out);
?>