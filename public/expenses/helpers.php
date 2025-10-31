<?php
if (!function_exists('office_expenses_table_exists')) {
    function office_expenses_table_exists(mysqli $conn): bool
    {
        $result = mysqli_query($conn, "SHOW TABLES LIKE 'office_expenses'");
        $exists = ($result && mysqli_num_rows($result) > 0);
        if ($result) {
            mysqli_free_result($result);
        }
        return $exists;
    }
}

if (!function_exists('office_expenses_stmt_bind')) {
    function office_expenses_stmt_bind(mysqli_stmt $stmt, string $types, array &$params): void
    {
        $bind = [$stmt, $types];
        foreach ($params as $index => &$value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bind);
    }
}

if (!function_exists('office_expenses_default_categories')) {
    function office_expenses_default_categories(): array
    {
        return [
            'Utilities',
            'Office Supplies',
            'Staff Welfare',
            'Travel & Conveyance',
            'Maintenance',
            'Marketing',
            'IT & Software',
            'Miscellaneous',
        ];
    }
}

if (!function_exists('office_expenses_default_payment_modes')) {
    function office_expenses_default_payment_modes(): array
    {
        return ['Cash', 'Bank Transfer', 'UPI', 'Credit Card', 'Debit Card', 'Cheque'];
    }
}

if (!function_exists('office_expenses_fetch_categories')) {
    function office_expenses_fetch_categories(mysqli $conn): array
    {
        $categories = [];
        $sql = "SELECT DISTINCT category FROM office_expenses WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category";
        if ($result = mysqli_query($conn, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[] = $row['category'];
            }
            mysqli_free_result($result);
        }
        return $categories;
    }
}

if (!function_exists('office_expenses_fetch_payment_modes')) {
    function office_expenses_fetch_payment_modes(mysqli $conn): array
    {
        $modes = [];
        $sql = "SELECT DISTINCT payment_mode FROM office_expenses WHERE payment_mode IS NOT NULL AND TRIM(payment_mode) <> '' ORDER BY payment_mode";
        if ($result = mysqli_query($conn, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $modes[] = $row['payment_mode'];
            }
            mysqli_free_result($result);
        }
        return $modes;
    }
}

if (!function_exists('office_expenses_current_employee')) {
    function office_expenses_current_employee(mysqli $conn, int $user_id): ?array
    {
        $stmt = mysqli_prepare($conn, 'SELECT id, employee_code, first_name, last_name, department FROM employees WHERE user_id = ? LIMIT 1');
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

if (!function_exists('office_expenses_current_employee_id')) {
    function office_expenses_current_employee_id(mysqli $conn, int $user_id): ?int
    {
        $employee = office_expenses_current_employee($conn, $user_id);
        return $employee ? (int) $employee['id'] : null;
    }
}

if (!function_exists('office_expenses_ensure_upload_directory')) {
    function office_expenses_ensure_upload_directory(): bool
    {
        $dir = __DIR__ . '/../../uploads/office_expenses';
        if (is_dir($dir)) {
            return true;
        }
        return mkdir($dir, 0755, true);
    }
}

if (!function_exists('office_expenses_upload_directory_path')) {
    function office_expenses_upload_directory_path(): string
    {
        return __DIR__ . '/../../uploads/office_expenses';
    }
}

if (!function_exists('office_expenses_allowed_extensions')) {
    function office_expenses_allowed_extensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png'];
    }
}

if (!function_exists('office_expenses_store_receipt_file')) {
    function office_expenses_store_receipt_file(array $file, array &$errors): ?string
    {
        $error_code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error_code === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error_code !== UPLOAD_ERR_OK) {
            $errors[] = 'Receipt upload failed (code ' . (int) $error_code . ').';
            return null;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, office_expenses_allowed_extensions(), true)) {
            $errors[] = 'Unsupported receipt type. Allowed: ' . implode(', ', office_expenses_allowed_extensions()) . '.';
        }

        $size = (int) ($file['size'] ?? 0);
        $max_size = 2 * 1024 * 1024;
        if ($size <= 0) {
            $errors[] = 'Receipt file is empty.';
        } elseif ($size > $max_size) {
            $errors[] = 'Receipt file must be under 2 MB.';
        }

        if (!empty($errors)) {
            return null;
        }

        if (!office_expenses_ensure_upload_directory()) {
            $errors[] = 'Unable to prepare receipt uploads directory.';
            return null;
        }

        $tmp_path = (string) ($file['tmp_name'] ?? '');
        if ($tmp_path === '' || !is_uploaded_file($tmp_path)) {
            $errors[] = 'Uploaded receipt could not be verified.';
            return null;
        }

        try {
            $token = bin2hex(random_bytes(4));
        } catch (Exception $e) {
            $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
        }

        $destination_name = 'expense_' . date('YmdHis') . '_' . $token . '.' . $extension;
        $destination_path = office_expenses_upload_directory_path() . DIRECTORY_SEPARATOR . $destination_name;

        if (!move_uploaded_file($tmp_path, $destination_path)) {
            $errors[] = 'Failed to store the uploaded receipt.';
            return null;
        }

        return 'uploads/office_expenses/' . $destination_name;
    }
}

if (!function_exists('office_expenses_delete_receipt_file')) {
    function office_expenses_delete_receipt_file(?string $relative_path): void
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

if (!function_exists('office_expenses_fetch_expense')) {
    function office_expenses_fetch_expense(mysqli $conn, int $expense_id): ?array
    {
        $sql = 'SELECT e.*, emp.employee_code, emp.first_name, emp.last_name, emp.department '
            . 'FROM office_expenses e '
            . 'LEFT JOIN employees emp ON e.added_by = emp.id '
            . 'WHERE e.id = ? LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        office_expenses_stmt_bind($stmt, 'i', [$expense_id]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $expense = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $expense ?: null;
    }
}
