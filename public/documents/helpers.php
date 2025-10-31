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

if (!function_exists('documents_allowed_visibilities_for_permissions')) {
    function documents_allowed_visibilities_for_permissions(array $permissions): array
    {
        // Map permission levels to visibility access
        // If user can view_all, they get admin-level visibility
        // If only view_assigned or view_own, they get employee-level visibility
        if (!empty($permissions['can_view_all'])) {
            return ['employee', 'manager', 'admin'];
        }
        if (!empty($permissions['can_view_assigned'])) {
            return ['employee', 'manager'];
        }
        return ['employee'];
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

if (!function_exists('documents_allowed_extensions')) {
    function documents_allowed_extensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    }
}

if (!function_exists('documents_upload_directory_path')) {
    function documents_upload_directory_path(): string
    {
        return __DIR__ . '/../../uploads/documents';
    }
}

if (!function_exists('documents_ensure_upload_directory')) {
    function documents_ensure_upload_directory(): bool
    {
        $dir = documents_upload_directory_path();
        if (is_dir($dir)) {
            return true;
        }
        return mkdir($dir, 0755, true);
    }
}

if (!function_exists('documents_generate_storage_filename')) {
    function documents_generate_storage_filename(string $title, string $extension): string
    {
        $safe_title = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($title));
        $safe_title = trim($safe_title, '-') ?: 'document';
        try {
            $token = bin2hex(random_bytes(4));
        } catch (Exception $e) {
            $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
        }
        return date('YmdHis') . '_' . $safe_title . '_' . $token . '.' . strtolower($extension);
    }
}

if (!function_exists('documents_store_uploaded_file')) {
    function documents_store_uploaded_file(array $file, string $title, array &$errors): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please choose a file to upload.';
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error (code ' . (int) $file['error'] . ').';
            return null;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, documents_allowed_extensions(), true)) {
            $errors[] = 'Unsupported file type. Allowed: ' . implode(', ', documents_allowed_extensions()) . '.';
        }

        $max_size = 10 * 1024 * 1024; // 10 MB
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $errors[] = 'Uploaded file is empty.';
        } elseif ($size > $max_size) {
            $errors[] = 'File size exceeds the 10 MB limit.';
        }

        if (!empty($errors)) {
            return null;
        }

        if (!documents_ensure_upload_directory()) {
            $errors[] = 'Unable to prepare uploads directory.';
            return null;
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');
        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            $errors[] = 'Uploaded file could not be verified.';
            return null;
        }

        $destination_name = documents_generate_storage_filename($title, $extension);
        $destination_path = documents_upload_directory_path() . DIRECTORY_SEPARATOR . $destination_name;
        if (!move_uploaded_file($tmp_name, $destination_path)) {
            $errors[] = 'Failed to store the uploaded file.';
            return null;
        }

        return 'uploads/documents/' . $destination_name;
    }
}

if (!function_exists('documents_delete_file')) {
    function documents_delete_file(?string $relative_path): void
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
