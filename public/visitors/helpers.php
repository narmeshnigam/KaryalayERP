<?php
if (!function_exists('visitor_logs_table_exists')) {
    function visitor_logs_table_exists(mysqli $conn): bool
    {
        $result = mysqli_query($conn, "SHOW TABLES LIKE 'visitor_logs'");
        $exists = ($result && mysqli_num_rows($result) > 0);
        if ($result) {
            mysqli_free_result($result);
        }
        return $exists;
    }
}

if (!function_exists('visitor_logs_stmt_bind')) {
    function visitor_logs_stmt_bind(mysqli_stmt $stmt, string $types, array &$params): void
    {
        $bind = [$stmt, $types];
        foreach ($params as $index => &$value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bind);
    }
}

if (!function_exists('visitor_logs_fetch_employees')) {
    function visitor_logs_fetch_employees(mysqli $conn): array
    {
        $rows = [];
        $sql = 'SELECT id, employee_code, first_name, last_name FROM employees ORDER BY first_name, last_name';
        if ($result = mysqli_query($conn, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        return $rows;
    }
}

if (!function_exists('visitor_logs_current_employee')) {
    function visitor_logs_current_employee(mysqli $conn, int $user_id): ?array
    {
        $stmt = mysqli_prepare($conn, 'SELECT id, employee_code, first_name, last_name FROM employees WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('visitor_logs_current_employee_id')) {
    function visitor_logs_current_employee_id(mysqli $conn, int $user_id): ?int
    {
        $employee = visitor_logs_current_employee($conn, $user_id);
        return $employee ? (int) $employee['id'] : null;
    }
}

if (!function_exists('visitor_logs_upload_directory_path')) {
    function visitor_logs_upload_directory_path(): string
    {
        return __DIR__ . '/../../uploads/visitor_logs';
    }
}

if (!function_exists('visitor_logs_ensure_upload_directory')) {
    function visitor_logs_ensure_upload_directory(): bool
    {
        $directory = visitor_logs_upload_directory_path();
        if (is_dir($directory)) {
            return true;
        }
        return mkdir($directory, 0755, true);
    }
}

if (!function_exists('visitor_logs_delete_file')) {
    function visitor_logs_delete_file(?string $relative_path): void
    {
        if (!$relative_path) {
            return;
        }
        $full_path = __DIR__ . '/../../' . ltrim($relative_path, '/');
        if (is_file($full_path)) {
            @unlink($full_path);
        }
    }
}
