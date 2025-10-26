<?php
require_once __DIR__ . '/../helpers.php';

function crm_leads_require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../login.php');
        exit;
    }
}

function crm_leads_require_tables(mysqli $conn): void {
    if (!crm_tables_exist($conn)) {
        closeConnection($conn);
        require_once __DIR__ . '/../onboarding.php';
        exit;
    }
}

function crm_lead_fetch(mysqli $conn, int $lead_id): ?array {
    $sql = "SELECT l.*, 
                assignee.employee_code AS assigned_code,
                assignee.first_name AS assigned_first,
                assignee.last_name AS assigned_last,
                creator.first_name AS creator_first,
                creator.last_name AS creator_last,
                creator.employee_code AS creator_code
            FROM crm_leads l
            LEFT JOIN employees assignee ON assignee.id = l.assigned_to
            LEFT JOIN employees creator ON creator.id = l.created_by
            WHERE l.id = ? AND l.deleted_at IS NULL
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return null; }
    mysqli_stmt_bind_param($stmt, 'i', $lead_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) { mysqli_free_result($res); }
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function crm_lead_contact_conflicts(mysqli $conn, ?string $phone, ?string $email, int $ignore_id = 0): array {
    $conflicts = [];
    $clauses = [];
    $params = [];
    $types = '';
    if ($phone !== null && $phone !== '') {
        $clauses[] = 'phone = ?';
        $params[] = $phone;
        $types .= 's';
        $conflicts['phone'] = false;
    }
    if ($email !== null && $email !== '') {
        $clauses[] = 'email = ?';
        $params[] = $email;
        $types .= 's';
        $conflicts['email'] = false;
    }
    if (!$clauses) { return $conflicts; }

    $sql = 'SELECT id, phone, email FROM crm_leads WHERE deleted_at IS NULL AND (' . implode(' OR ', $clauses) . ')';
    if ($ignore_id > 0) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $ignore_id;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $conflicts; }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (isset($conflicts['phone']) && $phone !== '' && ($row['phone'] ?? '') === $phone) {
            $conflicts['phone'] = true;
        }
        if (isset($conflicts['email']) && $email !== '' && ($row['email'] ?? '') === $email) {
            $conflicts['email'] = true;
        }
    }
    if ($res) { mysqli_free_result($res); }
    mysqli_stmt_close($stmt);
    return $conflicts;
}

function crm_lead_allowed_follow_up(?string $date): bool {
    if ($date === null || trim($date) === '') {
        return true;
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return false;
    }
    $today = strtotime('today');
    return $ts >= $today;
}

function crm_lead_employee_label(?string $code, ?string $first, ?string $last): string {
    $parts = [];
    $name = trim(trim((string)$first) . ' ' . trim((string)$last));
    if ($code !== null && $code !== '') {
        $parts[] = $code;
    }
    if ($name !== '') {
        $parts[] = $name;
    }
    if (!$parts) {
        return 'Unassigned';
    }
    return implode(' - ', $parts);
}

function crm_lead_status_badge_class(string $status): string {
    return match ($status) {
        'New' => 'badge-info',
        'Contacted' => 'badge-warning',
        'Converted' => 'badge-success',
        'Dropped' => 'badge-secondary',
        default => 'badge-light',
    };
}

function crm_lead_reset_follow_up_on_final_status(array &$data): void {
    $status = $data['status'] ?? '';
    if (in_array($status, ['Converted','Dropped'], true)) {
        $data['follow_up_date'] = null;
        $data['follow_up_type'] = null;
        $data['follow_up_created'] = 0;
    }
}
?>
