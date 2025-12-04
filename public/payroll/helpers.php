<?php
/**
 * Payroll Module - Helper Functions
 */

require_once __DIR__ . '/../../config/db_connect.php';

// Check if payroll tables exist
function payroll_tables_exist($conn) {
    $tables = ['payroll_master', 'payroll_items', 'payroll_activity_log'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) return false;
    }
    return true;
}

// Get all payrolls with filters
function get_all_payrolls($conn, $type = null, $month = null, $status = null) {
    $sql = "SELECT pm.*, COUNT(pi.id) as item_count, u.username as created_by_name
            FROM payroll_master pm
            LEFT JOIN payroll_items pi ON pm.id = pi.payroll_id
            LEFT JOIN users u ON pm.created_by = u.id
            WHERE 1=1";
    
    if ($type) $sql .= " AND pm.payroll_type = '" . $conn->real_escape_string($type) . "'";
    if ($month) $sql .= " AND pm.month_year = '" . $conn->real_escape_string($month) . "'";
    if ($status) $sql .= " AND pm.status = '" . $conn->real_escape_string($status) . "'";
    
    $sql .= " GROUP BY pm.id ORDER BY pm.created_at DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get payroll by ID
function get_payroll_by_id($conn, $payroll_id) {
    $stmt = $conn->prepare("SELECT pm.*, u.username as created_by_name 
                            FROM payroll_master pm
                            LEFT JOIN users u ON pm.created_by = u.id
                            WHERE pm.id = ?");
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get payroll items
function get_payroll_items($conn, $payroll_id) {
    $sql = "SELECT pi.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, 
            e.employee_code, e.department, e.designation
        FROM payroll_items pi
        LEFT JOIN employees e ON pi.employee_id = e.id
        WHERE pi.payroll_id = ?
        ORDER BY pi.transaction_number ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get payroll statistics
function get_payroll_statistics($conn) {
    $stats = [
        'total_payrolls' => 0,
        'salary_outflow' => 0,
        'reimbursement_pending' => 0,
        'locked_this_month' => 0
    ];
    
    // Total payrolls this month
    $current_month = date('Y-m');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payroll_master WHERE month_year = ?");
    $stmt->bind_param("s", $current_month);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $stats['total_payrolls'] = $row['count'];
    }
    
    // Total salary outflow (locked/paid)
    $result = $conn->query("SELECT SUM(total_amount) as total FROM payroll_master 
                            WHERE payroll_type = 'Salary' AND status IN ('Locked','Paid')");
    if ($row = $result->fetch_assoc()) {
        $stats['salary_outflow'] = $row['total'] ?? 0;
    }
    
    // Pending reimbursement amount
    $result = $conn->query("SELECT SUM(total_amount) as total FROM payroll_master 
                            WHERE payroll_type = 'Reimbursement' AND status = 'Draft'");
    if ($row = $result->fetch_assoc()) {
        $stats['reimbursement_pending'] = $row['total'] ?? 0;
    }
    
    // Locked this month
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payroll_master 
                            WHERE status IN ('Locked','Paid') AND month_year = ?");
    $stmt->bind_param("s", $current_month);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $stats['locked_this_month'] = $row['count'];
    }
    
    return $stats;
}

// Get active employees for salary payroll
function get_employees_for_payroll($conn, $month_year = null) {
    if (!$month_year) $month_year = date('Y-m');
    
    $sql = "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, e.employee_code, e.department, e.designation,
             e.basic_salary, e.hra, e.conveyance_allowance, e.medical_allowance, e.special_allowance, e.gross_salary
         FROM employees e
         WHERE e.status = 'Active'
         ORDER BY name";

    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get unpaid reimbursements
function get_unpaid_reimbursements($conn) {
    $sql = "SELECT r.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code
        FROM reimbursements r
        JOIN employees e ON r.employee_id = e.id
        WHERE r.status = 'Approved' AND r.payment_status = 'Pending'
        ORDER BY r.date_submitted DESC";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Create payroll draft
function create_payroll_draft($conn, $data) {
    $stmt = $conn->prepare(
        "INSERT INTO payroll_master 
        (payroll_type, month_year, total_employees, total_amount, status, created_by) 
        VALUES (?, ?, ?, ?, 'Draft', ?)"
    );
    
    $stmt->bind_param(
        "ssidi",
        $data['payroll_type'],
        $data['month_year'],
        $data['total_employees'],
        $data['total_amount'],
        $data['created_by']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

// Add payroll item with auto-generated transaction number
function add_payroll_item($conn, $data) {
    // Generate unique transaction number if not provided
    if (empty($data['transaction_number'])) {
        $data['transaction_number'] = generate_transaction_number($conn, $data['payroll_id']);
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO payroll_items 
        (transaction_number, payroll_id, employee_id, item_type, base_salary, allowances, deductions, payable, 
         attendance_days, reimbursement_id, transaction_ref, remarks, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->bind_param(
        "siisddddsisss",
        $data['transaction_number'],
        $data['payroll_id'],
        $data['employee_id'],
        $data['item_type'],
        $data['base_salary'],
        $data['allowances'],
        $data['deductions'],
        $data['payable'],
        $data['attendance_days'],
        $data['reimbursement_id'],
        $data['transaction_ref'],
        $data['remarks'],
        $data['status']
    );
    
    return $stmt->execute();
}

// Generate unique transaction number for payroll item
function generate_transaction_number($conn, $payroll_id) {
    // Get payroll month_year
    $stmt = $conn->prepare("SELECT month_year FROM payroll_master WHERE id = ?");
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $month_year = str_replace('-', '', $row['month_year']); // Convert 2025-11 to 202511
    } else {
        $month_year = date('Ym'); // Fallback to current month
    }
    
    // Get the last transaction number for this month
    $prefix = "PAY-{$month_year}-";
    $stmt = $conn->prepare("SELECT transaction_number FROM payroll_items 
                           WHERE transaction_number LIKE ? 
                           ORDER BY transaction_number DESC LIMIT 1");
    $search_pattern = $prefix . '%';
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $next_sequence = 1;
    if ($row = $result->fetch_assoc()) {
        // Extract sequence number from last transaction
        $last_number = $row['transaction_number'];
        $parts = explode('-', $last_number);
        if (count($parts) === 3) {
            $next_sequence = intval($parts[2]) + 1;
        }
    }
    
    // Generate new transaction number: PAY-YYYYMM-XXXXX
    $transaction_number = $prefix . str_pad($next_sequence, 5, '0', STR_PAD_LEFT);
    
    // Verify uniqueness (handle race conditions)
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM payroll_items WHERE transaction_number = ?");
    $check_stmt->bind_param("s", $transaction_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    
    if ($check_result['count'] > 0) {
        // Transaction number already exists, try next sequence
        $next_sequence++;
        $transaction_number = $prefix . str_pad($next_sequence, 5, '0', STR_PAD_LEFT);
    }
    
    return $transaction_number;
}

// Update payroll item (for editing transaction numbers and amounts)
function update_payroll_item($conn, $item_id, $data) {
    $update_fields = [];
    $param_types = "";
    $param_values = [];
    
    // Build dynamic UPDATE query based on provided data
    if (isset($data['transaction_number'])) {
        $update_fields[] = "transaction_number = ?";
        $param_types .= "s";
        $param_values[] = $data['transaction_number'];
    }
    if (isset($data['base_salary'])) {
        $update_fields[] = "base_salary = ?";
        $param_types .= "d";
        $param_values[] = $data['base_salary'];
    }
    if (isset($data['allowances'])) {
        $update_fields[] = "allowances = ?";
        $param_types .= "d";
        $param_values[] = $data['allowances'];
    }
    if (isset($data['deductions'])) {
        $update_fields[] = "deductions = ?";
        $param_types .= "d";
        $param_values[] = $data['deductions'];
    }
    if (isset($data['payable'])) {
        $update_fields[] = "payable = ?";
        $param_types .= "d";
        $param_values[] = $data['payable'];
    }
    if (isset($data['remarks'])) {
        $update_fields[] = "remarks = ?";
        $param_types .= "s";
        $param_values[] = $data['remarks'];
    }
    if (isset($data['status'])) {
        $update_fields[] = "status = ?";
        $param_types .= "s";
        $param_values[] = $data['status'];
    }
    
    if (empty($update_fields)) {
        return false; // Nothing to update
    }
    
    $sql = "UPDATE payroll_items SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $param_types .= "i";
    $param_values[] = $item_id;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$param_values);
    
    return $stmt->execute();
}

// Update payroll master
function update_payroll_master($conn, $payroll_id, $data) {
    $stmt = $conn->prepare(
        "UPDATE payroll_master 
        SET total_employees = ?, total_amount = ?
        WHERE id = ? AND status = 'Draft'"
    );
    
    $stmt->bind_param("idi", $data['total_employees'], $data['total_amount'], $payroll_id);
    return $stmt->execute();
}

// Lock payroll
function lock_payroll($conn, $payroll_id, $transaction_mode, $transaction_ref) {
    $stmt = $conn->prepare(
        "UPDATE payroll_master 
        SET status = 'Locked', 
            transaction_mode = ?,
            transaction_ref = ?,
            locked_at = NOW()
        WHERE id = ? AND status = 'Draft'"
    );
    
    $stmt->bind_param("ssi", $transaction_mode, $transaction_ref, $payroll_id);
    return $stmt->execute();
}

// Delete payroll draft
function delete_payroll_draft($conn, $payroll_id) {
    $payroll = get_payroll_by_id($conn, $payroll_id);
    if ($payroll['status'] !== 'Draft') return false;
    
    // Delete items first (cascade should handle this, but being explicit)
    $stmt = $conn->prepare("DELETE FROM payroll_items WHERE payroll_id = ?");
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    
    // Delete master
    $stmt = $conn->prepare("DELETE FROM payroll_master WHERE id = ?");
    $stmt->bind_param("i", $payroll_id);
    return $stmt->execute();
}

// Log activity
function log_payroll_activity($conn, $payroll_id, $action, $user_id, $description = null) {
    $stmt = $conn->prepare(
        "INSERT INTO payroll_activity_log (payroll_id, action, user_id, description) 
        VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isis", $payroll_id, $action, $user_id, $description);
    return $stmt->execute();
}

// Get activity log
function get_payroll_activity_log($conn, $payroll_id) {
    $sql = "SELECT pal.*, u.username
            FROM payroll_activity_log pal
            LEFT JOIN users u ON pal.user_id = u.id
            WHERE pal.payroll_id = ?
            ORDER BY pal.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Format currency
function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}

// Get month name
function get_month_name($month_str) {
    return date('F Y', strtotime($month_str . '-01'));
}

// Check if payroll exists for month and type
function payroll_exists_for_month($conn, $month_year, $payroll_type) {
    $stmt = $conn->prepare(
        "SELECT id FROM payroll_master 
        WHERE month_year = ? AND payroll_type = ? AND status != 'Draft'"
    );
    $stmt->bind_param("ss", $month_year, $payroll_type);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Calculate net pay
function calculate_net_pay($base_salary, $allowances, $deductions) {
    return ($base_salary + $allowances) - $deductions;
}
