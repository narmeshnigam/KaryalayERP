<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

function crm_tables_exist(mysqli $conn): bool {
    $tables = ['crm_tasks','crm_calls','crm_meetings','crm_visits','crm_leads'];
    foreach ($tables as $t) {
        $tEsc = mysqli_real_escape_string($conn, $t);
        $res = @mysqli_query($conn, "SHOW TABLES LIKE '$tEsc'");
        $exists = ($res && mysqli_num_rows($res) > 0);
        if ($res) { mysqli_free_result($res); }
        if (!$exists) { return false; }
    }
    return true;
}

function crm_role_can_manage(string $role): bool {
    $role = strtolower($role);
    return in_array($role, ['admin', 'manager'], true);
}

function crm_upload_directory(): string {
    return __DIR__ . '/../../uploads/crm_attachments';
}

function crm_ensure_upload_directory(): bool {
    $dir = crm_upload_directory();
    if (!is_dir($dir)) {
        return @mkdir($dir, 0777, true);
    }
    return is_writable($dir);
}

function crm_allowed_mime_types(): array {
    return [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];
}

function crm_fetch_employees(mysqli $conn): array {
    $rows = [];
    $res = mysqli_query($conn, "SELECT id, employee_code, first_name, last_name FROM employees ORDER BY first_name, last_name");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
        mysqli_free_result($res);
    }
    return $rows;
}

function crm_employee_exists(mysqli $conn, int $employee_id): bool {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'i', $employee_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = $res && mysqli_fetch_assoc($res);
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function crm_current_employee_id(mysqli $conn, int $user_id): ?int {
    // Try mapping session user_id -> employees.id if such mapping exists
    $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) mysqli_free_result($res);
        mysqli_stmt_close($stmt);
        if ($row && isset($row['id'])) return (int)$row['id'];
    }
    return null;
}

function crm_notify_new_task(mysqli $conn, int $task_id): void {
    // Stub: log; could be extended to email/WhatsApp
    error_log('CRM: New task assigned, ID=' . $task_id);
}

function crm_notify_task_completed(mysqli $conn, int $task_id): void {
    error_log('CRM: Task completed, ID=' . $task_id);
}

?>