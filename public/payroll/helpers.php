<?php
/**
 * Payroll Module - Helper Functions
 * Core utility functions for payroll processing
 */

/**
 * Check if payroll tables exist
 */
function payroll_tables_exist($conn) {
    $required_tables = ['payroll_master', 'payroll_records', 'payroll_allowances', 'payroll_deductions', 'payroll_activity_log'];
    
    foreach ($required_tables as $table) {
        $result = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }
        @mysqli_free_result($result);
    }
    
    return true;
}

/**
 * Get all payroll batches with filters
 */
function get_all_payrolls($conn, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    if (!empty($filters['status'])) {
        $where_clauses[] = "pm.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    
    if (!empty($filters['year'])) {
        $where_clauses[] = "YEAR(STR_TO_DATE(CONCAT(pm.month, '-01'), '%Y-%m-%d')) = ?";
        $params[] = $filters['year'];
        $types .= 'i';
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $sql = "SELECT pm.*, 
            u1.username as created_by_name,
            u2.username as locked_by_name,
            u3.username as paid_by_name
            FROM payroll_master pm
            LEFT JOIN users u1 ON pm.created_by = u1.id
            LEFT JOIN users u2 ON pm.locked_by = u2.id
            LEFT JOIN users u3 ON pm.paid_by = u3.id
            $where_sql
            ORDER BY pm.month DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $payrolls = [];
    while ($row = $result->fetch_assoc()) {
        $payrolls[] = $row;
    }
    
    return $payrolls;
}

/**
 * Get payroll by ID
 */
function get_payroll_by_id($conn, $payroll_id) {
    $stmt = $conn->prepare("
        SELECT pm.*, 
        u1.username as created_by_name,
        u2.username as locked_by_name,
        u3.username as paid_by_name
        FROM payroll_master pm
        LEFT JOIN users u1 ON pm.created_by = u1.id
        LEFT JOIN users u2 ON pm.locked_by = u2.id
        LEFT JOIN users u3 ON pm.paid_by = u3.id
        WHERE pm.id = ?
    ");
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get payroll records for a payroll batch
 */
function get_payroll_records($conn, $payroll_id) {
    $stmt = $conn->prepare("
        SELECT pr.*, 
        CONCAT_WS(' ', e.first_name, e.last_name) as employee_name,
        e.employee_code,
        e.department,
        e.designation,
        e.official_email as employee_email
        FROM payroll_records pr
        INNER JOIN employees e ON pr.employee_id = e.id
        WHERE pr.payroll_id = ?
        ORDER BY e.first_name, e.last_name
    ");
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    return $records;
}

/**
 * Get single payroll record
 */
function get_payroll_record($conn, $record_id) {
    $stmt = $conn->prepare("
        SELECT pr.*, 
        CONCAT_WS(' ', e.first_name, e.last_name) as employee_name,
        e.employee_code,
        e.department,
        e.designation,
        e.official_email as employee_email,
        e.mobile_number as employee_phone,
        e.basic_salary,
        pm.month,
        pm.status as payroll_status
        FROM payroll_records pr
        INNER JOIN employees e ON pr.employee_id = e.id
        INNER JOIN payroll_master pm ON pr.payroll_id = pm.id
        WHERE pr.id = ?
    ");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get active employees for payroll generation
 */
function get_active_employees_for_payroll($conn) {
    $sql = "SELECT id, CONCAT_WS(' ', first_name, last_name) as employee_name, employee_code, department, designation, basic_salary, official_email
            FROM employees
            WHERE status = 'Active' AND basic_salary > 0
            ORDER BY first_name, last_name";
    $result = $conn->query($sql);
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    return $employees;
}

/**
 * Get attendance days for employee in a month
 */
function get_attendance_days($conn, $employee_id, $month) {
    // month format: YYYY-MM
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as present_days
        FROM attendance
        WHERE employee_id = ? 
        AND attendance_date BETWEEN ? AND ?
        AND status IN ('Present', 'Half Day', 'Work From Home')
    ");
    $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $present_days = (int)$row['present_days'];
    $total_days = date('t', strtotime($start_date));
    return [
        'present_days' => $present_days,
        'total_days' => $total_days
    ];
}

/**
 * Get approved reimbursements for employee in a month
 */
function get_approved_reimbursements($conn, $employee_id, $month) {
    // month format: YYYY-MM
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total_reimbursements
        FROM reimbursements
        WHERE employee_id = ? 
        AND status = 'Approved'
        AND expense_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return (float)($row['total_reimbursements'] ?? 0);
}

/**
 * Get active allowances
 */
function get_active_allowances($conn) {
    $sql = "SELECT * FROM payroll_allowances WHERE active = 1 ORDER BY name";
    $result = $conn->query($sql);
    
    $allowances = [];
    while ($row = $result->fetch_assoc()) {
        $allowances[] = $row;
    }
    
    return $allowances;
}

/**
 * Get active deductions
 */
function get_active_deductions($conn) {
    $sql = "SELECT * FROM payroll_deductions WHERE active = 1 ORDER BY name";
    $result = $conn->query($sql);
    
    $deductions = [];
    while ($row = $result->fetch_assoc()) {
        $deductions[] = $row;
    }
    
    return $deductions;
}

/**
 * Calculate allowances for base salary
 */
function calculate_allowances($conn, $base_salary) {
    $allowances = get_active_allowances($conn);
    $total = 0;
    
    foreach ($allowances as $allowance) {
        if ($allowance['type'] === 'Fixed') {
            $total += $allowance['value'];
        } else if ($allowance['type'] === 'Percent') {
            $total += ($base_salary * $allowance['value'] / 100);
        }
    }
    
    return round($total, 2);
}

/**
 * Calculate deductions for base salary
 */
function calculate_deductions($conn, $base_salary) {
    $deductions = get_active_deductions($conn);
    $total = 0;
    
    foreach ($deductions as $deduction) {
        if ($deduction['type'] === 'Fixed') {
            $total += $deduction['value'];
        } else if ($deduction['type'] === 'Percent') {
            $total += ($base_salary * $deduction['value'] / 100);
        }
    }
    
    return round($total, 2);
}

/**
 * Calculate net pay
 */
function calculate_net_pay($base_salary, $allowances, $reimbursements, $deductions, $bonus = 0, $penalties = 0) {
    $net_pay = $base_salary + $allowances + $reimbursements + $bonus - $deductions - $penalties;
    
    // Net pay cannot be negative
    if ($net_pay < 0) {
        $net_pay = 0;
    }
    
    return round($net_pay, 2);
}

/**
 * Calculate attendance-adjusted salary
 */
function calculate_attendance_based_salary($base_salary, $present_days, $total_days) {
    if ($total_days == 0) {
        return 0;
    }
    
    return round(($base_salary / $total_days) * $present_days, 2);
}

/**
 * Check if payroll exists for month
 */
function payroll_exists_for_month($conn, $month) {
    $stmt = $conn->prepare("SELECT id FROM payroll_master WHERE month = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Log payroll activity
 */
function log_payroll_activity($conn, $payroll_id, $user_id, $action, $details = null) {
    $stmt = $conn->prepare("INSERT INTO payroll_activity_log (payroll_id, user_id, action, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $payroll_id, $user_id, $action, $details);
    return $stmt->execute();
}

/**
 * Get payroll activity log
 */
function get_payroll_activity_log($conn, $payroll_id) {
    $stmt = $conn->prepare("
        SELECT pal.*, u.username
        FROM payroll_activity_log pal
        INNER JOIN users u ON pal.user_id = u.id
        WHERE pal.payroll_id = ?
        ORDER BY pal.created_at DESC
    ");
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    return $logs;
}

/**
 * Get payroll dashboard statistics
 */
function get_payroll_dashboard_stats($conn) {
    // Current month stats
    $current_month = date('Y-m');
    $stmt = $conn->prepare("SELECT * FROM payroll_master WHERE month = ?");
    $stmt->bind_param("s", $current_month);
    $stmt->execute();
    $current_payroll = $stmt->get_result()->fetch_assoc();
    
    // Total employees with salary
    $total_employees = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active' AND basic_salary > 0")->fetch_assoc()['count'];
    
    // Last 3 months payroll
    $sql = "SELECT * FROM payroll_master ORDER BY month DESC LIMIT 3";
    $result = $conn->query($sql);
    $recent_payrolls = [];
    while ($row = $result->fetch_assoc()) {
        $recent_payrolls[] = $row;
    }
    
    // Average salary (fix for MariaDB LIMIT & IN/ALL/ANY/SOME subquery)
    $latest_payroll_id = null;
    $res = $conn->query("SELECT id FROM payroll_master ORDER BY month DESC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $latest_payroll_id = $row['id'];
    }
    $avg_salary = 0;
    if ($latest_payroll_id) {
        $res2 = $conn->query("SELECT AVG(net_pay) as avg FROM payroll_records WHERE payroll_id = " . intval($latest_payroll_id));
        if ($res2 && ($row2 = $res2->fetch_assoc())) {
            $avg_salary = $row2['avg'] ?? 0;
        }
    }
    
    // Pending payouts (Locked but not Paid)
    $pending_payouts = $conn->query("SELECT SUM(total_amount) as total FROM payroll_master WHERE status = 'Locked'")->fetch_assoc()['total'] ?? 0;
    
    return [
        'current_payroll' => $current_payroll,
        'total_employees' => $total_employees,
        'recent_payrolls' => $recent_payrolls,
        'average_salary' => round($avg_salary, 2),
        'pending_payouts' => $pending_payouts
    ];
}

/**
 * Format month display
 */
function format_month_display($month) {
    $date = DateTime::createFromFormat('Y-m', $month);
    if ($date) {
        return $date->format('F Y');
    }
    return $month;
}

/**
 * Get status badge class
 */
function get_status_badge_class($status) {
    switch ($status) {
        case 'Draft':
            return 'badge-draft';
        case 'Reviewed':
            return 'badge-reviewed';
        case 'Locked':
            return 'badge-locked';
        case 'Paid':
            return 'badge-paid';
        default:
            return 'badge-default';
    }
}

/**
 * Get status badge HTML
 */
function get_status_badge($status) {
    $class = get_status_badge_class($status);
    return "<span class='status-badge $class'>$status</span>";
}

/**
 * Format currency
 */
function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}
?>
