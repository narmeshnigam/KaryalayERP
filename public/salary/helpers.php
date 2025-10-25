<?php
/**
 * Shared helper utilities for the Salary Viewer module.
 */

if (!function_exists('salary_table_exists')) {
    function salary_table_exists(mysqli $conn): bool
    {
        $table = mysqli_real_escape_string($conn, 'salary_records');
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $exists = ($result && mysqli_num_rows($result) > 0);
        if ($result) {
            mysqli_free_result($result);
        }
        return $exists;
    }
}

if (!function_exists('salary_current_employee_id')) {
    function salary_current_employee_id(mysqli $conn, int $user_id): ?int
    {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        return $row ? (int) $row['id'] : null;
    }
}

if (!function_exists('salary_role_can_manage')) {
    function salary_role_can_manage(string $role): bool
    {
        return in_array($role, ['admin', 'accountant'], true);
    }
}

if (!function_exists('salary_role_can_edit')) {
    function salary_role_can_edit(string $role): bool
    {
        return $role === 'admin';
    }
}

if (!function_exists('salary_fetch_employees')) {
    function salary_fetch_employees(mysqli $conn): array
    {
        $rows = [];
        $sql = 'SELECT id, employee_code, first_name, last_name, department, designation, salary_type, basic_salary, hra, conveyance_allowance, medical_allowance, special_allowance, gross_salary FROM employees ORDER BY first_name, last_name';
        if ($result = mysqli_query($conn, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        return $rows;
    }
}

if (!function_exists('salary_format_employee')) {
    function salary_format_employee(?string $code, ?string $first, ?string $last): string
    {
        $name = trim(($first ?? '') . ' ' . ($last ?? ''));
        if ($code) {
            $label = $name !== '' ? $name : 'Employee';
            return htmlspecialchars($code, ENT_QUOTES) . ' · ' . htmlspecialchars($label, ENT_QUOTES);
        }
        if ($name !== '') {
            return htmlspecialchars($name, ENT_QUOTES);
        }
        return '—';
    }
}

if (!function_exists('salary_format_currency')) {
    function salary_format_currency($value): string
    {
        if ($value === null) {
            return '₹0.00';
        }
        return '₹' . number_format((float) $value, 2);
    }
}

if (!function_exists('salary_format_month_label')) {
    function salary_format_month_label(string $month): string
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return htmlspecialchars($month, ENT_QUOTES);
        }
        $date = DateTime::createFromFormat('Y-m', $month);
        return $date ? htmlspecialchars($date->format('F Y'), ENT_QUOTES) : htmlspecialchars($month, ENT_QUOTES);
    }
}

if (!function_exists('salary_upload_directory')) {
    function salary_upload_directory(): string
    {
        return __DIR__ . '/../../uploads/salary_slips';
    }
}

if (!function_exists('salary_ensure_upload_directory')) {
    function salary_ensure_upload_directory(): bool
    {
        $dir = salary_upload_directory();
        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }
        return is_writable($dir);
    }
}

if (!function_exists('salary_public_path')) {
    function salary_public_path(?string $relative): ?string
    {
        if ($relative === null || trim($relative) === '') {
            return null;
        }
        $relative = ltrim($relative, '/');
        return APP_URL . '/' . $relative;
    }
}

if (!function_exists('salary_stmt_bind')) {
    function salary_stmt_bind(mysqli_stmt $stmt, string $types, array &$params): void
    {
        $bind = [$stmt, $types];
        foreach ($params as $index => &$value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bind);
    }
}

if (!function_exists('salary_month_range_default')) {
    function salary_month_range_default(): array
    {
        $current = date('Y-m');
        $start = date('Y') . '-01';
        return [$start, $current];
    }
}

if (!function_exists('salary_month_bounds')) {
    function salary_month_bounds(string $month): array
    {
        // month in YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }
}

if (!function_exists('salary_fetch_employee_snapshot')) {
    function salary_fetch_employee_snapshot(mysqli $conn, int $employee_id): ?array
    {
        $sql = 'SELECT id, employee_code, first_name, last_name, department, designation, salary_type, basic_salary, hra, conveyance_allowance, medical_allowance, special_allowance, gross_salary FROM employees WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $employee_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) return null;
        // Compute a suggested allowances sum
        $allow_total = (float)($row['hra'] ?? 0) + (float)($row['conveyance_allowance'] ?? 0) + (float)($row['medical_allowance'] ?? 0) + (float)($row['special_allowance'] ?? 0);
        $row['suggested_allowances'] = $allow_total;
        return $row;
    }
}

if (!function_exists('salary_compute_monthly_attendance')) {
    function salary_compute_monthly_attendance(mysqli $conn, int $employee_id, string $month): array
    {
        [$fromDate, $toDate] = salary_month_bounds($month);
        // Fetch attendance rows for the month
        $sql = "SELECT a.attendance_date, a.status, a.leave_type, a.work_from_home FROM attendance a WHERE a.employee_id = ? AND a.attendance_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sql);
        $summary = [
            'from' => $fromDate,
            'to' => $toDate,
            'working_days_total' => 0,
            'days_worked' => 0.0,
            'leave_days' => 0.0,
            'leave_breakdown' => [],
            'week_off' => 0,
            'holidays' => 0,
        ];
        if (!$stmt) return $summary;
        mysqli_stmt_bind_param($stmt, 'iss', $employee_id, $fromDate, $toDate);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        // Load paid/unpaid flags for leave types
        $paidMap = [];
        if ($lt = mysqli_query($conn, "SELECT leave_type_name, is_paid FROM leave_types")) {
            while ($row = mysqli_fetch_assoc($lt)) {
                $paidMap[$row['leave_type_name']] = (int)$row['is_paid'] === 1;
            }
            mysqli_free_result($lt);
        }

        while ($row = $res ? mysqli_fetch_assoc($res) : null) {
            $status = $row['status'] ?? 'Absent';
            $leaveType = $row['leave_type'] ?? '';
            if ($status === 'Holiday') { $summary['holidays']++; continue; }
            if ($status === 'Week Off') { $summary['week_off']++; continue; }

            // Count as a working day if it's not holiday/week off
            $summary['working_days_total']++;

            if ($status === 'Present' || ((int)($row['work_from_home'] ?? 0) === 1)) {
                $summary['days_worked'] += 1.0;
            } elseif ($status === 'Half Day') {
                $summary['days_worked'] += 0.5;
                $summary['leave_days'] += 0.5;
                $summary['leave_breakdown']['Half Day'] = ($summary['leave_breakdown']['Half Day'] ?? 0) + 0.5;
            } elseif ($status === 'Leave') {
                $summary['leave_days'] += 1.0;
                $key = $leaveType !== '' ? $leaveType : 'Leave';
                $summary['leave_breakdown'][$key] = ($summary['leave_breakdown'][$key] ?? 0) + 1.0;
            } elseif ($status === 'Absent') {
                $summary['leave_days'] += 1.0;
                $summary['leave_breakdown']['Absent'] = ($summary['leave_breakdown']['Absent'] ?? 0) + 1.0;
            }
        }
        if ($res) { mysqli_free_result($res); }
        mysqli_stmt_close($stmt);

        // Compute unpaid leave deduction days using paidMap (optional informational)
        $unpaidDays = 0.0;
        foreach ($summary['leave_breakdown'] as $type => $days) {
            if ($type === 'Half Day') { $unpaidDays += 0.5; continue; }
            if ($type === 'Absent') { $unpaidDays += $days; continue; }
            $isPaid = $paidMap[$type] ?? true; // assume paid if unknown
            if (!$isPaid) $unpaidDays += (float)$days;
        }
        $summary['unpaid_leave_days'] = $unpaidDays;
        return $summary;
    }
}

if (!function_exists('salary_notify_salary_uploaded')) {
    function salary_notify_salary_uploaded(mysqli $conn, int $record_id): void
    {
        // First fetch the month and employee_id for this salary record
        $select = mysqli_prepare($conn, 'SELECT sr.month, sr.employee_id FROM salary_records sr WHERE sr.id = ? LIMIT 1');
        if (!$select) {
            return;
        }
        mysqli_stmt_bind_param($select, 'i', $record_id);
        mysqli_stmt_execute($select);
        $res = mysqli_stmt_get_result($select);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($select);
        if (!$row) {
            return;
        }

        $month = $row['month'] ?? '';
        $employee_id = isset($row['employee_id']) ? (int) $row['employee_id'] : 0;

        // Attempt to fetch employee email if the column exists
        $email = null;
        if ($employee_id > 0) {
            // Check if employees.email column exists to avoid SQL errors on older schemas
            $col_check_sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . mysqli_real_escape_string($conn, DB_NAME) . "' AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'email' LIMIT 1";
            $col_res = @mysqli_query($conn, $col_check_sql);
            $has_email_col = ($col_res && mysqli_num_rows($col_res) > 0);
            if ($col_res) {
                mysqli_free_result($col_res);
            }

            if ($has_email_col) {
                $e_stmt = mysqli_prepare($conn, 'SELECT email FROM employees WHERE id = ? LIMIT 1');
                if ($e_stmt) {
                    mysqli_stmt_bind_param($e_stmt, 'i', $employee_id);
                    mysqli_stmt_execute($e_stmt);
                    $e_res = mysqli_stmt_get_result($e_stmt);
                    $e_row = $e_res ? mysqli_fetch_assoc($e_res) : null;
                    mysqli_stmt_close($e_stmt);
                    if ($e_row) {
                        $email = $e_row['email'] ?? null;
                    }
                }
            }
        }

        if ($email) {
            error_log('[SalaryViewer] Salary uploaded notification queued for ' . $email . ' (month ' . $month . ').');
        } else {
            // Fallback logging when email is not available on this schema
            error_log('[SalaryViewer] Salary uploaded for employee_id=' . $employee_id . ' (month ' . $month . '). No email column available.');
        }
    }
}

if (!function_exists('salary_notify_slip_downloaded')) {
    function salary_notify_slip_downloaded(mysqli $conn, int $record_id, int $employee_id): void
    {
        $stmt = mysqli_prepare($conn, 'SELECT month FROM salary_records WHERE id = ? AND employee_id = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $record_id, $employee_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return;
        }
        error_log('[SalaryViewer] Salary slip downloaded for record #' . $record_id . ' (' . ($row['month'] ?? '') . ').');
    }
}

if (!function_exists('salary_get_employee_email')) {
    /**
     * Safely return the employee email if the column exists in the employees table.
     * Returns null when the column is missing or email is empty.
     */
    function salary_get_employee_email(mysqli $conn, int $employee_id): ?string
    {
        if ($employee_id <= 0) {
            return null;
        }

        $col_check_sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . mysqli_real_escape_string($conn, DB_NAME) . "' AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'email' LIMIT 1";
        $col_res = @mysqli_query($conn, $col_check_sql);
        $has_email_col = ($col_res && mysqli_num_rows($col_res) > 0);
        if ($col_res) {
            mysqli_free_result($col_res);
        }

        if (!$has_email_col) {
            return null;
        }

        $e_stmt = mysqli_prepare($conn, 'SELECT email FROM employees WHERE id = ? LIMIT 1');
        if (!$e_stmt) {
            return null;
        }
        mysqli_stmt_bind_param($e_stmt, 'i', $employee_id);
        mysqli_stmt_execute($e_stmt);
        $e_res = mysqli_stmt_get_result($e_stmt);
        $e_row = $e_res ? mysqli_fetch_assoc($e_res) : null;
        mysqli_stmt_close($e_stmt);
        if (!$e_row) {
            return null;
        }
        $email = trim((string) ($e_row['email'] ?? ''));
        return $email === '' ? null : $email;
    }
}
