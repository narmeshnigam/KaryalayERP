<?php
if (!function_exists('reimbursements_table_exists')) {
    function reimbursements_table_exists(mysqli $conn): bool
    {
        $result = mysqli_query($conn, "SHOW TABLES LIKE 'reimbursements'");
        $exists = ($result && mysqli_num_rows($result) > 0);
        if ($result) {
            mysqli_free_result($result);
        }
        return $exists;
    }
}

if (!function_exists('reimbursements_current_employee_id')) {
    function reimbursements_current_employee_id(mysqli $conn, int $user_id): ?int
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

if (!function_exists('reimbursements_fetch_employees')) {
    function reimbursements_fetch_employees(mysqli $conn): array
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

if (!function_exists('reimbursements_fetch_categories')) {
    function reimbursements_fetch_categories(mysqli $conn): array
    {
        $rows = [];
        $sql = "SELECT DISTINCT category FROM reimbursements WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category";
        if ($result = mysqli_query($conn, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row['category'];
            }
            mysqli_free_result($result);
        }
        return $rows;
    }
}

if (!function_exists('reimbursements_stmt_bind')) {
    function reimbursements_stmt_bind(mysqli_stmt $stmt, string $types, array &$params): void
    {
        $bind = [$stmt, $types];
        foreach ($params as $index => &$value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bind);
    }
}

if (!function_exists('reimbursements_allowed_statuses')) {
    function reimbursements_allowed_statuses(): array
    {
        return ['All', 'Pending', 'Approved', 'Rejected'];
    }
}

if (!function_exists('reimbursements_ensure_upload_directory')) {
    function reimbursements_ensure_upload_directory(): bool
    {
        $dir = reimbursements_upload_directory_path();
        if (is_dir($dir)) {
            return true;
        }
        return mkdir($dir, 0755, true);
    }
}

if (!function_exists('reimbursements_upload_directory_path')) {
    function reimbursements_upload_directory_path(): string
    {
        return __DIR__ . '/../../uploads/reimbursements';
    }
}

if (!function_exists('reimbursements_allowed_file_extensions')) {
    function reimbursements_allowed_file_extensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png'];
    }
}

if (!function_exists('reimbursements_store_proof_file')) {
    function reimbursements_store_proof_file(array $file, array &$errors): ?string
    {
        $error_code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error_code === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please upload a supporting document.';
            return null;
        }

        if ($error_code !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error (code ' . (int) $error_code . ').';
            return null;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, reimbursements_allowed_file_extensions(), true)) {
            $errors[] = 'Unsupported file type. Allowed: ' . implode(', ', reimbursements_allowed_file_extensions()) . '.';
        }

        $size = (int) ($file['size'] ?? 0);
        $max_size = 2 * 1024 * 1024; // 2 MB cap for proofs
        if ($size <= 0) {
            $errors[] = 'Uploaded file is empty.';
        } elseif ($size > $max_size) {
            $errors[] = 'File size exceeds the 2 MB limit.';
        }

        if (!empty($errors)) {
            return null;
        }

        if (!reimbursements_ensure_upload_directory()) {
            $errors[] = 'Unable to prepare uploads directory.';
            return null;
        }

        $tmp_path = (string) ($file['tmp_name'] ?? '');
        if ($tmp_path === '' || !is_uploaded_file($tmp_path)) {
            $errors[] = 'Uploaded file could not be verified.';
            return null;
        }

        try {
            $token = bin2hex(random_bytes(4));
        } catch (Exception $e) {
            $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
        }

        $destination_name = 'reimb_' . date('YmdHis') . '_' . $token . '.' . $extension;
        $destination_path = reimbursements_upload_directory_path() . DIRECTORY_SEPARATOR . $destination_name;

        if (!move_uploaded_file($tmp_path, $destination_path)) {
            $errors[] = 'Failed to store the uploaded file.';
            return null;
        }

        return 'uploads/reimbursements/' . $destination_name;
    }
}

if (!function_exists('reimbursements_delete_proof_file')) {
    function reimbursements_delete_proof_file(?string $relative_path): void
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

if (!function_exists('reimbursements_fetch_claim')) {
    function reimbursements_fetch_claim(mysqli $conn, int $claim_id): ?array
    {
        $sql = 'SELECT r.*, e.employee_code, e.first_name, e.last_name, e.department, e.designation '
            . 'FROM reimbursements r INNER JOIN employees e ON r.employee_id = e.id WHERE r.id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

    $params = [$claim_id];
    reimbursements_stmt_bind($stmt, 'i', $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $claim = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $claim ?: null;
    }
}