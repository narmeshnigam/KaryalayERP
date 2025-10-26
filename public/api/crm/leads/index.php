<?php
require_once __DIR__ . '/../../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../../crm/leads/common.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

if (!crm_tables_exist($conn)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'crm_not_installed']);
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
$method = $_SERVER['REQUEST_METHOD'];

function respond(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($method === 'GET') {
    $lead_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($lead_id > 0) {
        $lead = crm_lead_fetch($conn, $lead_id);
        if (!$lead) {
            respond(['success' => false, 'error' => 'not_found'], 404);
        }
        respond(['success' => true, 'data' => $lead]);
    }

    $statuses = crm_lead_statuses();
    $filters = [
        'status' => $_GET['status'] ?? '',
        'assigned_to' => (int)($_GET['assigned_to'] ?? 0),
        'search' => trim($_GET['search'] ?? ''),
        'follow_from' => $_GET['follow_from'] ?? '',
        'follow_to' => $_GET['follow_to'] ?? ''
    ];
    $limit = (int)($_GET['limit'] ?? 200);
    if ($limit <= 0 || $limit > 500) { $limit = 200; }

    $where = ['l.deleted_at IS NULL'];
    $params = [];
    $types = '';

    if ($filters['status'] !== '' && in_array($filters['status'], $statuses, true)) {
        $where[] = 'l.status = ?';
        $types .= 's';
        $params[] = $filters['status'];
    }
    if ($filters['assigned_to'] > 0) {
        $where[] = 'l.assigned_to = ?';
        $types .= 'i';
        $params[] = $filters['assigned_to'];
    }
    if ($filters['search'] !== '') {
        $like = '%' . $filters['search'] . '%';
        $where[] = '(l.name LIKE ? OR l.company_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)';
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['follow_from'] !== '') {
        $where[] = 'l.follow_up_date >= ?';
        $types .= 's';
        $params[] = $filters['follow_from'];
    }
    if ($filters['follow_to'] !== '') {
        $where[] = 'l.follow_up_date <= ?';
        $types .= 's';
        $params[] = $filters['follow_to'];
    }

    $sql = 'SELECT l.* FROM crm_leads l WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC LIMIT ' . $limit;
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt && $types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    } else {
        $res = mysqli_query($conn, $sql);
    }
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);
        if ($stmt) { mysqli_stmt_close($stmt); }
    }
    respond(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $action = strtolower($_POST['action'] ?? '');
    $current_employee_id = crm_current_employee_id($conn, (int)($_SESSION['user_id'] ?? 0));
    if (!$current_employee_id) {
        $current_employee_id = (int)($_SESSION['employee_id'] ?? 0);
    }

    if ($action === 'add') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'source' => trim($_POST['source'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'interests' => trim($_POST['interests'] ?? ''),
            'follow_up_date' => trim($_POST['follow_up_date'] ?? ''),
            'follow_up_type' => trim($_POST['follow_up_type'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'assigned_to' => (int)($_POST['assigned_to'] ?? 0)
        ];
        $errors = [];
        if ($data['name'] === '') { $errors[] = 'name_required'; }
        if ($data['source'] === '') { $errors[] = 'source_required'; }
        if ($data['assigned_to'] <= 0 || !crm_employee_exists($conn, $data['assigned_to'])) {
            $errors[] = 'invalid_assignee';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'invalid_email';
        }
        if ($data['follow_up_date'] !== '' && !crm_lead_allowed_follow_up($data['follow_up_date'])) {
            $errors[] = 'followup_past';
        }
        if ($data['follow_up_date'] !== '' && ($data['follow_up_type'] === '' || !in_array($data['follow_up_type'], crm_lead_follow_up_types(), true))) {
            $errors[] = 'followup_type_required';
        }
        if ($data['follow_up_date'] === '') { $data['follow_up_type'] = ''; }

        $conflicts = crm_lead_contact_conflicts($conn, $data['phone'], $data['email']);
        if (($conflicts['phone'] ?? false) === true) { $errors[] = 'phone_exists'; }
        if (($conflicts['email'] ?? false) === true) { $errors[] = 'email_exists'; }

        if ($errors) {
            respond(['success' => false, 'error' => 'validation_failed', 'details' => $errors], 422);
        }

        $follow_up_date = $data['follow_up_date'] !== '' ? $data['follow_up_date'] : null;
        $follow_up_type = $data['follow_up_type'] !== '' ? $data['follow_up_type'] : null;
        $created_by = $current_employee_id ?: $data['assigned_to'];
        $stmt = mysqli_prepare($conn, 'INSERT INTO crm_leads (name, company_name, phone, email, source, status, notes, interests, follow_up_date, follow_up_type, follow_up_created, last_contacted_at, assigned_to, attachment, location, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,0,NULL,?,?,?,?)');
        if (!$stmt) {
            respond(['success' => false, 'error' => 'stmt_prepare_failed'], 500);
        }
        $status = 'New';
        $attachment = null;
        mysqli_stmt_bind_param(
            $stmt,
            'ssssssssssissi',
            $data['name'],
            $data['company_name'],
            $data['phone'],
            $data['email'],
            $data['source'],
            $status,
            $data['notes'],
            $data['interests'],
            $follow_up_date,
            $follow_up_type,
            $data['assigned_to'],
            $attachment,
            $data['location'],
            $created_by
        );
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($conn);
            mysqli_stmt_close($stmt);
            respond(['success' => false, 'error' => 'db_insert_failed', 'message' => $error], 500);
        }
        $new_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        crm_notify_lead_assigned($conn, $new_id, $data['assigned_to']);
        respond(['success' => true, 'id' => $new_id]);
    }

    if ($action === 'update') {
        if (!crm_role_can_manage($user_role)) {
            respond(['success' => false, 'error' => 'forbidden'], 403);
        }
        $lead_id = (int)($_POST['id'] ?? 0);
        if ($lead_id <= 0) {
            respond(['success' => false, 'error' => 'id_required'], 422);
        }
        $lead = crm_lead_fetch($conn, $lead_id);
        if (!$lead) {
            respond(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = [
            'name' => trim($_POST['name'] ?? $lead['name'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? $lead['company_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? $lead['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? $lead['email'] ?? ''),
            'source' => trim($_POST['source'] ?? $lead['source'] ?? ''),
            'status' => trim($_POST['status'] ?? $lead['status'] ?? 'New'),
            'notes' => trim($_POST['notes'] ?? $lead['notes'] ?? ''),
            'interests' => trim($_POST['interests'] ?? $lead['interests'] ?? ''),
            'follow_up_date' => trim($_POST['follow_up_date'] ?? ($lead['follow_up_date'] ?? '')),
            'follow_up_type' => trim($_POST['follow_up_type'] ?? ($lead['follow_up_type'] ?? '')),
            'location' => trim($_POST['location'] ?? $lead['location'] ?? ''),
            'assigned_to' => (int)($_POST['assigned_to'] ?? ($lead['assigned_to'] ?? 0))
        ];

        $errors = [];
        if ($data['name'] === '') { $errors[] = 'name_required'; }
        if ($data['source'] === '') { $errors[] = 'source_required'; }
        if ($data['assigned_to'] <= 0 || !crm_employee_exists($conn, $data['assigned_to'])) {
            $errors[] = 'invalid_assignee';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'invalid_email';
        }
        if (!in_array($data['status'], crm_lead_statuses(), true)) {
            $errors[] = 'invalid_status';
        } elseif (!in_array($data['status'], crm_lead_allowed_statuses($lead['status'] ?? ''), true)) {
            $errors[] = 'status_transition_blocked';
        }
        if ($data['follow_up_date'] !== '' && !crm_lead_allowed_follow_up($data['follow_up_date'])) {
            $errors[] = 'followup_past';
        }
        if ($data['follow_up_date'] !== '' && ($data['follow_up_type'] === '' || !in_array($data['follow_up_type'], crm_lead_follow_up_types(), true))) {
            $errors[] = 'followup_type_required';
        }
        if ($data['follow_up_date'] === '') { $data['follow_up_type'] = ''; }

        $conflicts = crm_lead_contact_conflicts($conn, $data['phone'], $data['email'], $lead_id);
        if (($conflicts['phone'] ?? false) === true) { $errors[] = 'phone_exists'; }
        if (($conflicts['email'] ?? false) === true) { $errors[] = 'email_exists'; }

        if ($errors) {
            respond(['success' => false, 'error' => 'validation_failed', 'details' => $errors], 422);
        }

        $follow_up_date = $data['follow_up_date'] !== '' ? $data['follow_up_date'] : null;
        $follow_up_type = $data['follow_up_type'] !== '' ? $data['follow_up_type'] : null;
        $follow_up_created = (int)($lead['follow_up_created'] ?? 0);
        if ($follow_up_date !== ($lead['follow_up_date'] ?? null) || $follow_up_type !== ($lead['follow_up_type'] ?? null)) {
            $follow_up_created = 0;
        }
        $update = [
            'name' => $data['name'],
            'company_name' => $data['company_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'source' => $data['source'],
            'status' => $data['status'],
            'notes' => $data['notes'],
            'interests' => $data['interests'],
            'follow_up_date' => $follow_up_date,
            'follow_up_type' => $follow_up_type,
            'follow_up_created' => $follow_up_created,
            'assigned_to' => $data['assigned_to'],
            'location' => $data['location'],
            'attachment' => $lead['attachment'] ?? null
        ];
        crm_lead_reset_follow_up_on_final_status($update);
        $follow_up_created = $update['follow_up_created'];

        $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET name = ?, company_name = ?, phone = ?, email = ?, source = ?, status = ?, notes = ?, interests = ?, follow_up_date = ?, follow_up_type = ?, follow_up_created = ?, assigned_to = ?, location = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        if (!$stmt) {
            respond(['success' => false, 'error' => 'stmt_prepare_failed'], 500);
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssssssssssiisi',
            $update['name'],
            $update['company_name'],
            $update['phone'],
            $update['email'],
            $update['source'],
            $update['status'],
            $update['notes'],
            $update['interests'],
            $update['follow_up_date'],
            $update['follow_up_type'],
            $follow_up_created,
            $update['assigned_to'],
            $update['location'],
            $lead_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($conn);
            mysqli_stmt_close($stmt);
            respond(['success' => false, 'error' => 'db_update_failed', 'message' => $error], 500);
        }
        mysqli_stmt_close($stmt);
        respond(['success' => true]);
    }

    respond(['success' => false, 'error' => 'unsupported_action'], 400);
}

if ($method === 'DELETE') {
    if (!crm_role_can_manage($user_role)) {
        respond(['success' => false, 'error' => 'forbidden'], 403);
    }
    $lead_id = (int)($_GET['id'] ?? 0);
    if ($lead_id <= 0) {
        respond(['success' => false, 'error' => 'id_required'], 422);
    }
    $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    if (!$stmt) {
        respond(['success' => false, 'error' => 'stmt_prepare_failed'], 500);
    }
    mysqli_stmt_bind_param($stmt, 'i', $lead_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    respond(['success' => $ok]);
}

respond(['success' => false, 'error' => 'unsupported_method'], 405);
