<?php
/**
 * Shared helper utilities for Document Vault module.
 */

if (!function_exists('documents_table_exists')) {
    function documents_table_exists(mysqli $conn): bool
    {
        $table = mysqli_real_escape_string($conn, 'documents');
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $exists = ($result && mysqli_num_rows($result) > 0);
        if ($result) {
            mysqli_free_result($result);
        }
        return $exists;
    }
}

if (!function_exists('documents_current_employee_id')) {
    function documents_current_employee_id(mysqli $conn, int $user_id): ?int
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

if (!function_exists('documents_visibility_hierarchy')) {
    function documents_visibility_hierarchy(): array
    {
        return ['employee' => 1, 'manager' => 2, 'admin' => 3];
    }
}

if (!function_exists('documents_visibility_label')) {
    function documents_visibility_label(string $key): string
    {
        $labels = ['employee' => 'All Employees', 'manager' => 'Managers Only', 'admin' => 'Admins Only'];
        return $labels[$key] ?? ucfirst($key);
    }
}

if (!function_exists('documents_allowed_visibilities')) {
    function documents_allowed_visibilities(string $role): array
    {
        switch ($role) {
            case 'admin':
                return ['employee', 'manager', 'admin'];
            case 'manager':
                return ['employee', 'manager'];
            default:
                return ['employee'];
        }
    }
}

if (!function_exists('documents_fetch_employees')) {
    function documents_fetch_employees(mysqli $conn): array
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

if (!function_exists('documents_parse_tags')) {
    function documents_parse_tags(?string $raw): array
    {
        if (!$raw) {
            return [];
        }
        $parts = preg_split('/[,;]+/', $raw);
        $cleaned = [];
        foreach ($parts as $part) {
            $tag = trim($part);
            if ($tag !== '') {
                $cleaned[] = $tag;
            }
        }
        return $cleaned;
    }
}

if (!function_exists('documents_stmt_bind')) {
    function documents_stmt_bind(mysqli_stmt $stmt, string $types, array &$params): void
    {
        $bind = [$stmt, $types];
        foreach ($params as $index => &$value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bind);
    }
}

if (!function_exists('documents_format_employee')) {
    function documents_format_employee(?string $code, ?string $first, ?string $last): string
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

if (!function_exists('documents_visibility_badge')) {
    function documents_visibility_badge(string $visibility): string
    {
        $palette = [
            'admin' => ['#1b2a57', '#ccd6f6'],
            'manager' => ['#155724', '#d4edda'],
            'employee' => ['#0c5460', '#d1ecf1'],
        ];
        $colors = $palette[$visibility] ?? ['#343a40', '#e2e3e5'];
        $label = documents_visibility_label($visibility);
        return '<span style="display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;color:'
            . $colors[0] . ';background:' . $colors[1] . ';">' . htmlspecialchars($label) . '</span>';
    }
}
