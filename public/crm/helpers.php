<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/module_dependencies.php';

// Check CRM module prerequisites before loading
function crm_check_prerequisites(): void {
    $conn = createConnection(true);
    if (!$conn) {
        die('Database connection failed. Please check your configuration.');
    }
    
    $check = get_prerequisite_check_result($conn, 'crm');
    closeConnection($conn);
    
    if (!$check['allowed']) {
        display_prerequisite_error('crm', $check['missing_modules']);
    }
}

// Run prerequisite check
crm_check_prerequisites();

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

function crm_lead_statuses(): array {
    return ['New','Contacted','Converted','Dropped'];
}

function crm_lead_follow_up_types(): array {
    return ['Call','Meeting','Visit','Task'];
}

function crm_lead_sources(): array {
    return ['Web','Referral','Walk-in','Event','Inbound Call','Partner','Other'];
}

function crm_fetch_employee_map(mysqli $conn): array {
    $map = [];
    foreach (crm_fetch_employees($conn) as $emp) {
        $id = (int)($emp['id'] ?? 0);
        if (!$id) { continue; }
        $code = trim((string)($emp['employee_code'] ?? ''));
        $first = trim((string)($emp['first_name'] ?? ''));
        $last = trim((string)($emp['last_name'] ?? ''));
        $name = trim(($first . ' ' . $last));
        $label = $name !== '' ? $name : ('Employee #' . $id);
        if ($code !== '') { $label = $code . ' - ' . $label; }
        $map[$id] = $label;
    }
    return $map;
}

function crm_lead_allowed_statuses(string $current): array {
    $current = ucfirst(strtolower($current));
    return match ($current) {
        'New' => ['New','Contacted','Dropped'],
        'Contacted' => ['Contacted','Converted','Dropped'],
        'Converted' => ['Converted'],
        'Dropped' => ['Dropped'],
        default => crm_lead_statuses(),
    };
}

function crm_notify_lead_assigned(mysqli $conn, int $lead_id, int $assigned_to): void {
    // Placeholder for WhatsApp/Email integration
    error_log('CRM: Lead ' . $lead_id . ' assigned to employee ' . $assigned_to);
}

/**
 * Dashboard helpers
 */

function crm_date_bounds_for_week(DateTime $ref): array {
    // Start: Monday 00:00:00, End: Sunday 23:59:59
    $start = clone $ref;
    $start->setTime(0, 0, 0);
    if ((int)$start->format('N') !== 1) { // Not Monday
        $start->modify('last monday');
    }
    $end = clone $start;
    $end->modify('next sunday')->setTime(23, 59, 59);
    return [$start, $end];
}

function crm_counts_summary(mysqli $conn): array {
    $out = [
        'leads_total' => 0,
        'leads_open' => 0,
        'leads_converted' => 0,
        'leads_dropped' => 0,
        'tasks_open' => 0,
        'tasks_overdue' => 0,
        'tasks_due_today' => 0,
        'calls_today' => 0,
        'meetings_today' => 0,
        'visits_today' => 0,
    ];

    // Leads
    $res = mysqli_query($conn, "SELECT 
        COUNT(*) AS total,
        SUM(status IN ('New','Contacted')) AS open_cnt,
        SUM(status='Converted') AS conv_cnt,
        SUM(status='Dropped') AS drop_cnt
      FROM crm_leads WHERE deleted_at IS NULL");
    if ($res) {
        $r = mysqli_fetch_assoc($res) ?: [];
        $out['leads_total'] = (int)($r['total'] ?? 0);
        $out['leads_open'] = (int)($r['open_cnt'] ?? 0);
        $out['leads_converted'] = (int)($r['conv_cnt'] ?? 0);
        $out['leads_dropped'] = (int)($r['drop_cnt'] ?? 0);
        mysqli_free_result($res);
    }

    $today = (new DateTime('today'));
    $todayStr = $today->format('Y-m-d');

    // Tasks
    $res = mysqli_query($conn, "SELECT 
        SUM(status <> 'Completed') AS open_cnt,
        SUM(status <> 'Completed' AND due_date IS NOT NULL AND due_date < '$todayStr') AS overdue_cnt,
        SUM(status <> 'Completed' AND due_date = '$todayStr') AS today_cnt
      FROM crm_tasks WHERE deleted_at IS NULL");
    if ($res) {
        $r = mysqli_fetch_assoc($res) ?: [];
        $out['tasks_open'] = (int)($r['open_cnt'] ?? 0);
        $out['tasks_overdue'] = (int)($r['overdue_cnt'] ?? 0);
        $out['tasks_due_today'] = (int)($r['today_cnt'] ?? 0);
        mysqli_free_result($res);
    }

    // Today activities (calls/meetings/visits)
    $todayStart = $today->format('Y-m-d 00:00:00');
    $todayEnd = $today->format('Y-m-d 23:59:59');
    foreach ([
        'calls' => ["SELECT COUNT(*) AS c FROM crm_calls WHERE deleted_at IS NULL AND call_date BETWEEN '$todayStart' AND '$todayEnd'", 'calls_today'],
        'meetings' => ["SELECT COUNT(*) AS c FROM crm_meetings WHERE deleted_at IS NULL AND meeting_date BETWEEN '$todayStart' AND '$todayEnd'", 'meetings_today'],
        'visits' => ["SELECT COUNT(*) AS c FROM crm_visits WHERE deleted_at IS NULL AND visit_date BETWEEN '$todayStart' AND '$todayEnd'", 'visits_today'],
    ] as $spec) {
        $res = mysqli_query($conn, $spec[0]);
        if ($res) { $row = mysqli_fetch_assoc($res); $out[$spec[1]] = (int)($row['c'] ?? 0); mysqli_free_result($res);}    
    }

    return $out;
}

function crm_leads_by_status(mysqli $conn): array {
    $labels = ['New','Contacted','Converted','Dropped'];
    $data = array_fill_keys($labels, 0);
    $res = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM crm_leads WHERE deleted_at IS NULL GROUP BY status");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $status = $r['status'] ?? '';
            if (isset($data[$status])) { $data[$status] = (int)$r['c']; }
        }
        mysqli_free_result($res);
    }
    return ['labels' => $labels, 'data' => array_values($data)];
}

function crm_activities_last_days(mysqli $conn, int $days = 7): array {
    $days = max(1, min(90, $days));
    $start = new DateTime('-' . ($days - 1) . ' days');
    $start->setTime(0, 0, 0);
    $map = [];
    for ($i = 0; $i < $days; $i++) {
        $d = (clone $start)->modify("+$i day");
        $key = $d->format('Y-m-d');
        $map[$key] = ['Calls' => 0, 'Meetings' => 0, 'Visits' => 0, 'Tasks' => 0];
    }

    $startStr = $start->format('Y-m-d 00:00:00');
    $endStr = (new DateTime('today 23:59:59'))->format('Y-m-d H:i:s');

    // Calls
    $res = mysqli_query($conn, "SELECT DATE(call_date) AS d, COUNT(*) AS c FROM crm_calls WHERE deleted_at IS NULL AND call_date BETWEEN '$startStr' AND '$endStr' GROUP BY DATE(call_date)");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $k = $r['d']; if (isset($map[$k])) $map[$k]['Calls'] = (int)$r['c']; } mysqli_free_result($res);}    
    // Meetings
    $res = mysqli_query($conn, "SELECT DATE(meeting_date) AS d, COUNT(*) AS c FROM crm_meetings WHERE deleted_at IS NULL AND meeting_date BETWEEN '$startStr' AND '$endStr' GROUP BY DATE(meeting_date)");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $k = $r['d']; if (isset($map[$k])) $map[$k]['Meetings'] = (int)$r['c']; } mysqli_free_result($res);}    
    // Visits
    $res = mysqli_query($conn, "SELECT DATE(visit_date) AS d, COUNT(*) AS c FROM crm_visits WHERE deleted_at IS NULL AND visit_date BETWEEN '$startStr' AND '$endStr' GROUP BY DATE(visit_date)");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $k = $r['d']; if (isset($map[$k])) $map[$k]['Visits'] = (int)$r['c']; } mysqli_free_result($res);}    
    // Tasks (use created_at as activity)
    $res = mysqli_query($conn, "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM crm_tasks WHERE deleted_at IS NULL AND created_at BETWEEN '$startStr' AND '$endStr' GROUP BY DATE(created_at)");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $k = $r['d']; if (isset($map[$k])) $map[$k]['Tasks'] = (int)$r['c']; } mysqli_free_result($res);}    

    $labels = array_keys($map);
    $series = [
        'Calls' => array_column($map, 'Calls'),
        'Meetings' => array_column($map, 'Meetings'),
        'Visits' => array_column($map, 'Visits'),
        'Tasks' => array_column($map, 'Tasks'),
    ];
    return ['labels' => $labels, 'series' => $series];
}

/**
 * Activities trend for selected filters window (per-day counts).
 * Honors date range, employee/department filters, and lead source when applicable
 * (only if the activity table has a lead_id column to join with leads).
 */
function crm_activities_trend(mysqli $conn, array $filters): array {
    // Build day map from start..end inclusive
    $start = new DateTime($filters['start'] . ' 00:00:00');
    $end = new DateTime($filters['end'] . ' 23:59:59');
    if ($end < $start) { [$start, $end] = [$end, $start]; }

    $cursor = (clone $start)->setTime(0,0,0);
    $lastDay = (clone $end)->setTime(0,0,0);
    $map = [];
    while ($cursor <= $lastDay) {
        $k = $cursor->format('Y-m-d');
        $map[$k] = ['Calls' => 0, 'Meetings' => 0, 'Visits' => 0, 'Tasks' => 0];
        $cursor->modify('+1 day');
    }

    $s = $start->format('Y-m-d H:i:s');
    $e = $end->format('Y-m-d H:i:s');

    $empClause = !empty($filters['employee_id']) ? (' AND x.assigned_to='.(int)$filters['employee_id']) : '';
    $deptClause = !empty($filters['department']) ? (" AND EXISTS (SELECT 1 FROM employees e WHERE e.id = x.assigned_to AND e.department='".addslashes($filters['department'])."')") : '';

    $leadSourceProvided = !empty($filters['lead_source']);
    $leadSourceClause = $leadSourceProvided ? (" AND l.source='".addslashes($filters['lead_source'])."'") : '';

    // Helper to execute grouped query and merge into map
    $merge = function(string $sql, string $label) use (&$map, $conn) {
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) {
                $d = $r['d'] ?? null; $c = (int)($r['c'] ?? 0);
                if ($d && isset($map[$d])) { $map[$d][$label] = $c; }
            }
            mysqli_free_result($res);
        }
    };

    // Calls
    $callsHasLead = crm_column_exists($conn, 'crm_calls', 'lead_id');
    $sql = "SELECT DATE(x.call_date) AS d, COUNT(*) AS c FROM crm_calls x"
         . ($leadSourceProvided && $callsHasLead ? " LEFT JOIN crm_leads l ON l.id = x.lead_id" : '')
         . " WHERE x.deleted_at IS NULL AND x.call_date BETWEEN '$s' AND '$e'"
         . $empClause . $deptClause . ($leadSourceProvided && $callsHasLead ? $leadSourceClause : '')
         . " GROUP BY DATE(x.call_date)";
    $merge($sql, 'Calls');

    // Meetings
    $meetHasLead = crm_column_exists($conn, 'crm_meetings', 'lead_id');
    $sql = "SELECT DATE(x.meeting_date) AS d, COUNT(*) AS c FROM crm_meetings x"
         . ($leadSourceProvided && $meetHasLead ? " LEFT JOIN crm_leads l ON l.id = x.lead_id" : '')
         . " WHERE x.deleted_at IS NULL AND x.meeting_date BETWEEN '$s' AND '$e'"
         . $empClause . $deptClause . ($leadSourceProvided && $meetHasLead ? $leadSourceClause : '')
         . " GROUP BY DATE(x.meeting_date)";
    $merge($sql, 'Meetings');

    // Visits
    $visHasLead = crm_column_exists($conn, 'crm_visits', 'lead_id');
    $sql = "SELECT DATE(x.visit_date) AS d, COUNT(*) AS c FROM crm_visits x"
         . ($leadSourceProvided && $visHasLead ? " LEFT JOIN crm_leads l ON l.id = x.lead_id" : '')
         . " WHERE x.deleted_at IS NULL AND x.visit_date BETWEEN '$s' AND '$e'"
         . $empClause . $deptClause . ($leadSourceProvided && $visHasLead ? $leadSourceClause : '')
         . " GROUP BY DATE(x.visit_date)";
    $merge($sql, 'Visits');

    // Tasks (use created_at as activity date)
    $tasksHasLead = crm_column_exists($conn, 'crm_tasks', 'lead_id');
    $sql = "SELECT DATE(x.created_at) AS d, COUNT(*) AS c FROM crm_tasks x"
         . ($leadSourceProvided && $tasksHasLead ? " LEFT JOIN crm_leads l ON l.id = x.lead_id" : '')
         . " WHERE x.deleted_at IS NULL AND x.created_at BETWEEN '$s' AND '$e'"
         . $empClause . $deptClause . ($leadSourceProvided && $tasksHasLead ? $leadSourceClause : '')
         . " GROUP BY DATE(x.created_at)";
    $merge($sql, 'Tasks');

    $labels = array_keys($map);
    $series = [
        'Calls' => array_column($map, 'Calls'),
        'Meetings' => array_column($map, 'Meetings'),
        'Visits' => array_column($map, 'Visits'),
        'Tasks' => array_column($map, 'Tasks'),
    ];
    return ['labels' => $labels, 'series' => $series];
}

function crm_deadlines(mysqli $conn, ?int $employee_id = null, int $limit = 5): array {
    $today = new DateTime('today');
    $todayStr = $today->format('Y-m-d');
    [$weekStart, $weekEnd] = crm_date_bounds_for_week(new DateTime());
    $weekStartStr = $weekStart->format('Y-m-d');
    $weekEndStr = $weekEnd->format('Y-m-d');

    $whereAssignee = function(string $alias) use ($employee_id) {
        if (!$employee_id) return '';
        return " AND ($alias.assigned_to = " . (int)$employee_id . ")";
    };

    $out = [
        'overdue' => [],
        'today' => [],
        'week' => [],
    ];

    // Helper to push item
    $push = function(string $bucket, string $type, array $row) use (&$out) {
        $out[$bucket][] = [
            'type' => $type,
            'id' => (int)$row['id'],
            'title' => trim($row['title'] ?? ($row['name'] ?? 'Untitled')),
            'due' => $row['due'] ?? null,
            'link' => $row['link'] ?? '#',
        ];
    };

    // Build queries for each entity (overdue/today/week)
    // Tasks: use due_date
    foreach ([
        ['bucket' => 'overdue', 'cond' => "t.due_date IS NOT NULL AND t.due_date < '$todayStr' AND t.status <> 'Completed'", 'limit' => $limit],
        ['bucket' => 'today', 'cond' => "t.due_date = '$todayStr' AND t.status <> 'Completed'", 'limit' => $limit],
        ['bucket' => 'week', 'cond' => "t.due_date BETWEEN '$weekStartStr' AND '$weekEndStr' AND t.status <> 'Completed'", 'limit' => $limit],
    ] as $cfg) {
        $sql = "SELECT t.id, t.title, t.due_date AS due FROM crm_tasks t WHERE t.deleted_at IS NULL "
             . $whereAssignee('t')
             . " AND " . $cfg['cond']
             . " ORDER BY t.due_date ASC LIMIT " . (int)$cfg['limit'];
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) { $r['link'] = './tasks/view.php?id=' . (int)$r['id']; $push($cfg['bucket'], 'Task', $r); }
            mysqli_free_result($res);
        }
    }

    // Leads: follow_up_date
    foreach ([
        ['bucket' => 'overdue', 'cond' => "l.follow_up_date IS NOT NULL AND l.follow_up_date < '$todayStr'", 'limit' => $limit],
        ['bucket' => 'today', 'cond' => "l.follow_up_date = '$todayStr'", 'limit' => $limit],
        ['bucket' => 'week', 'cond' => "l.follow_up_date BETWEEN '$weekStartStr' AND '$weekEndStr'", 'limit' => $limit],
    ] as $cfg) {
        $sql = "SELECT l.id, l.name AS title, l.follow_up_date AS due FROM crm_leads l WHERE l.deleted_at IS NULL "
             . $whereAssignee('l')
             . " AND " . $cfg['cond']
             . " ORDER BY l.follow_up_date ASC LIMIT " . (int)$cfg['limit'];
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) { $r['link'] = './leads/view.php?id=' . (int)$r['id']; $push($cfg['bucket'], 'Lead Follow-up', $r); }
            mysqli_free_result($res);
        }
    }

    // Calls: follow_up_date
    foreach ([
        ['bucket' => 'overdue', 'cond' => "c.follow_up_date IS NOT NULL AND c.follow_up_date < '$todayStr'", 'limit' => $limit],
        ['bucket' => 'today', 'cond' => "c.follow_up_date = '$todayStr'", 'limit' => $limit],
        ['bucket' => 'week', 'cond' => "c.follow_up_date BETWEEN '$weekStartStr' AND '$weekEndStr'", 'limit' => $limit],
    ] as $cfg) {
        $sql = "SELECT c.id, c.title, c.follow_up_date AS due FROM crm_calls c WHERE c.deleted_at IS NULL "
             . $whereAssignee('c')
             . " AND " . $cfg['cond']
             . " ORDER BY c.follow_up_date ASC LIMIT " . (int)$cfg['limit'];
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) { $r['link'] = './calls/view.php?id=' . (int)$r['id']; $push($cfg['bucket'], 'Call Follow-up', $r); }
            mysqli_free_result($res);
        }
    }

    // Meetings: meeting_date deadlines + follow_up_date
    foreach ([
        ['bucket' => 'overdue', 'cond' => "m.meeting_date < '$todayStr 00:00:00'", 'limit' => $limit, 'field' => 'meeting_date', 'label' => 'Meeting'],
        ['bucket' => 'today', 'cond' => "m.meeting_date BETWEEN '$todayStr 00:00:00' AND '$todayStr 23:59:59'", 'limit' => $limit, 'field' => 'meeting_date', 'label' => 'Meeting'],
        ['bucket' => 'week', 'cond' => "m.meeting_date BETWEEN '$weekStartStr 00:00:00' AND '$weekEndStr 23:59:59'", 'limit' => $limit, 'field' => 'meeting_date', 'label' => 'Meeting'],
    ] as $cfg) {
        $sql = "SELECT m.id, m.title, m." . $cfg['field'] . " AS due FROM crm_meetings m WHERE m.deleted_at IS NULL "
             . $whereAssignee('m')
             . " AND " . $cfg['cond']
             . " ORDER BY " . $cfg['field'] . " ASC LIMIT " . (int)$cfg['limit'];
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) { $r['link'] = './meetings/view.php?id=' . (int)$r['id']; $push($cfg['bucket'], $cfg['label'], $r); }
            mysqli_free_result($res);
        }
    }

    // Visits: visit_date
    foreach ([
        ['bucket' => 'overdue', 'cond' => "v.visit_date < '$todayStr 00:00:00'", 'limit' => $limit, 'field' => 'visit_date', 'label' => 'Visit'],
        ['bucket' => 'today', 'cond' => "v.visit_date BETWEEN '$todayStr 00:00:00' AND '$todayStr 23:59:59'", 'limit' => $limit, 'field' => 'visit_date', 'label' => 'Visit'],
        ['bucket' => 'week', 'cond' => "v.visit_date BETWEEN '$weekStartStr 00:00:00' AND '$weekEndStr 23:59:59'", 'limit' => $limit, 'field' => 'visit_date', 'label' => 'Visit'],
    ] as $cfg) {
        $sql = "SELECT v.id, v.title, v." . $cfg['field'] . " AS due FROM crm_visits v WHERE v.deleted_at IS NULL "
             . $whereAssignee('v')
             . " AND " . $cfg['cond']
             . " ORDER BY " . $cfg['field'] . " ASC LIMIT " . (int)$cfg['limit'];
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) { $r['link'] = './visits/view.php?id=' . (int)$r['id']; $push($cfg['bucket'], $cfg['label'], $r); }
            mysqli_free_result($res);
        }
    }

    return $out;
}

function crm_top_owners(mysqli $conn, int $days = 30, int $limit = 5): array {
    $start = (new DateTime('-' . $days . ' days'))->format('Y-m-d 00:00:00');
    $end = (new DateTime('now'))->format('Y-m-d H:i:s');

    $owners = [];
    $push = function($ownerId, $type, $cnt) use (&$owners) {
        $ownerId = (int)$ownerId;
        if (!isset($owners[$ownerId])) {
            $owners[$ownerId] = ['Calls' => 0, 'Meetings' => 0, 'Visits' => 0, 'Tasks' => 0];
        }
        $owners[$ownerId][$type] += (int)$cnt;
    };

    // Calls by assigned_to
    $res = mysqli_query($conn, "SELECT assigned_to AS o, COUNT(*) AS c FROM crm_calls WHERE deleted_at IS NULL AND assigned_to IS NOT NULL AND call_date BETWEEN '$start' AND '$end' GROUP BY assigned_to");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $push($r['o'], 'Calls', $r['c']); } mysqli_free_result($res);}    
    // Meetings
    $res = mysqli_query($conn, "SELECT assigned_to AS o, COUNT(*) AS c FROM crm_meetings WHERE deleted_at IS NULL AND assigned_to IS NOT NULL AND meeting_date BETWEEN '$start' AND '$end' GROUP BY assigned_to");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $push($r['o'], 'Meetings', $r['c']); } mysqli_free_result($res);}    
    // Visits
    $res = mysqli_query($conn, "SELECT assigned_to AS o, COUNT(*) AS c FROM crm_visits WHERE deleted_at IS NULL AND assigned_to IS NOT NULL AND visit_date BETWEEN '$start' AND '$end' GROUP BY assigned_to");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $push($r['o'], 'Visits', $r['c']); } mysqli_free_result($res);}    
    // Tasks by assigned_to created
    $res = mysqli_query($conn, "SELECT assigned_to AS o, COUNT(*) AS c FROM crm_tasks WHERE deleted_at IS NULL AND assigned_to IS NOT NULL AND created_at BETWEEN '$start' AND '$end' GROUP BY assigned_to");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $push($r['o'], 'Tasks', $r['c']); } mysqli_free_result($res);}    

    // Sort owners by total desc
    uasort($owners, function($a, $b){
        $ta = array_sum($a); $tb = array_sum($b);
        return $tb <=> $ta;
    });
    $owners = array_slice($owners, 0, $limit, true);

    // Map owner id to name
    $map = crm_fetch_employee_map($conn);
    $labels = [];
    $series = ['Calls' => [], 'Meetings' => [], 'Visits' => [], 'Tasks' => []];
    foreach ($owners as $ownerId => $counts) {
        $labels[] = $map[$ownerId] ?? ('Emp #' . $ownerId);
        foreach ($series as $k => $_) { $series[$k][] = (int)($counts[$k] ?? 0); }
    }
    return ['labels' => $labels, 'series' => $series];
}

/**
 * Dashboard (Managers/Admins) – Filters and Advanced Metrics
 */

function crm_parse_filters(mysqli $conn): array {
    $today = new DateTime('today');
    $startDefault = (new DateTime('first day of this month'))->format('Y-m-d');
    $endDefault = $today->format('Y-m-d');

    $start = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : $startDefault;
    $end = isset($_GET['end']) ? substr($_GET['end'], 0, 10) : $endDefault;
    $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    $department = isset($_GET['department']) ? trim($_GET['department']) : '';
    $lead_source = isset($_GET['lead_source']) ? trim($_GET['lead_source']) : '';

    // Default department to current user's if available
    if ($department === '' && isset($_SESSION['user_id'])) {
        $empId = crm_current_employee_id($conn, (int)$_SESSION['user_id']);
        if ($empId) {
            $res = mysqli_query($conn, 'SELECT department FROM employees WHERE id='.(int)$empId.' LIMIT 1');
            if ($res) { $r = mysqli_fetch_assoc($res); $department = trim($r['department'] ?? ''); mysqli_free_result($res);}    
        }
    }

    return [
        'start' => $start,
        'end' => $end,
        'employee_id' => $employee_id,
        'department' => $department,
        'lead_source' => $lead_source,
    ];
}

function crm_filter_where(array $filters, string $entity, string $alias = 't'): string {
    // Date field per entity
    $dateField = match ($entity) {
        'leads' => 'created_at',
        'tasks' => 'created_at',
        'calls' => 'call_date',
        'meetings' => 'meeting_date',
        'visits' => 'visit_date',
        default => 'created_at',
    };
    $start = addslashes($filters['start']);
    $end = addslashes($filters['end']);
    $where = " ($alias.$dateField BETWEEN '$start 00:00:00' AND '$end 23:59:59')";

    if (!empty($filters['employee_id'])) {
        $where .= ' AND ' . $alias . '.assigned_to = ' . (int)$filters['employee_id'];
    }
    if (!empty($filters['lead_source']) && $entity === 'leads') {
        $ls = addslashes($filters['lead_source']);
        $where .= " AND $alias.source = '$ls'";
    }
    // Department via employees table (assigned_to)
    if (!empty($filters['department'])) {
        $dept = addslashes($filters['department']);
        $where .= " AND EXISTS (SELECT 1 FROM employees e WHERE e.id = $alias.assigned_to AND e.department = '$dept')";
    }
    return $where;
}

function crm_lead_funnel(mysqli $conn, array $filters): array {
    $stages = ['New','Contacted','Converted','Dropped'];
    $data = array_fill_keys($stages, 0);
    $w = crm_filter_where($filters, 'leads', 'l');
    $sql = "SELECT status, COUNT(*) AS c FROM crm_leads l WHERE l.deleted_at IS NULL AND $w GROUP BY status";
    if ($res = mysqli_query($conn, $sql)) {
        while ($r = mysqli_fetch_assoc($res)) {
            $s = $r['status'] ?? '';
            if (isset($data[$s])) $data[$s] = (int)$r['c'];
        }
        mysqli_free_result($res);
    }
    return ['labels' => array_keys($data), 'data' => array_values($data)];
}

function crm_avg_response_time_days(mysqli $conn, array $filters): float {
    // For leads in range, compute avg days to first activity (call/meeting/visit/task)
    $w = crm_filter_where($filters, 'leads', 'l');
    $q = "SELECT l.id, l.created_at FROM crm_leads l WHERE l.deleted_at IS NULL AND $w";
    $res = mysqli_query($conn, $q);
    if (!$res) return 0.0;
    $totalDays = 0.0; $count = 0;
    
    // Check if crm_tasks has lead_id column
    $tasks_has_lead_id = crm_column_exists($conn, 'crm_tasks', 'lead_id');
    
    while ($lead = mysqli_fetch_assoc($res)) {
        $lid = (int)$lead['id'];
        $created = new DateTime($lead['created_at']);
        $minDate = null;
        
        // Build activity queries (crm_tasks only if lead_id exists)
        $queries = [
            ["SELECT MIN(call_date) AS d FROM crm_calls WHERE deleted_at IS NULL AND lead_id=$lid", 'd'],
            ["SELECT MIN(meeting_date) AS d FROM crm_meetings WHERE deleted_at IS NULL AND lead_id=$lid", 'd'],
            ["SELECT MIN(visit_date) AS d FROM crm_visits WHERE deleted_at IS NULL AND lead_id=$lid", 'd'],
        ];
        if ($tasks_has_lead_id) {
            $queries[] = ["SELECT MIN(created_at) AS d FROM crm_tasks WHERE deleted_at IS NULL AND lead_id=$lid", 'd'];
        }
        
        foreach ($queries as $spec) {
            $r2 = mysqli_query($conn, $spec[0]);
            if ($r2) { $row = mysqli_fetch_assoc($r2); $d = $row['d'] ?? null; mysqli_free_result($r2); if ($d) { $dt = new DateTime($d); if ($minDate === null || $dt < $minDate) $minDate = $dt; } }
        }
        if ($minDate) {
            $diff = $created->diff($minDate);
            $days = (float)$diff->days + ($diff->h / 24.0);
            $totalDays += $days; $count++;
        }
    }
    mysqli_free_result($res);
    return $count ? round($totalDays / $count, 1) : 0.0;
}

function crm_kpis(mysqli $conn, array $filters): array {
    // Total & Active Leads
    $wL = crm_filter_where($filters, 'leads', 'l');
    $tot = 0; $active = 0; $conv = 0;
    if ($r = mysqli_query($conn, "SELECT COUNT(*) c FROM crm_leads l WHERE l.deleted_at IS NULL AND $wL")) { $tot = (int)mysqli_fetch_assoc($r)['c']; mysqli_free_result($r);}    
    if ($r = mysqli_query($conn, "SELECT COUNT(*) c FROM crm_leads l WHERE l.deleted_at IS NULL AND $wL AND l.status IN ('New','Contacted')")) { $active = (int)mysqli_fetch_assoc($r)['c']; mysqli_free_result($r);}    
    if ($r = mysqli_query($conn, "SELECT COUNT(*) c FROM crm_leads l WHERE l.deleted_at IS NULL AND $wL AND l.status = 'Converted'")) { $conv = (int)mysqli_fetch_assoc($r)['c']; mysqli_free_result($r);}    

    $convRate = $tot ? round(($conv / $tot) * 100, 1) : 0.0;

    // Pending Tasks (uncompleted) within date window by created_at, show count
    $wT = crm_filter_where($filters, 'tasks', 't');
    $pendingTasks = 0; $overdueTasks = 0; $todayStr = (new DateTime('today'))->format('Y-m-d');
    if ($r = mysqli_query($conn, "SELECT 
        SUM(t.status <> 'Completed') AS open_cnt,
        SUM(t.status <> 'Completed' AND t.due_date IS NOT NULL AND t.due_date < '$todayStr') AS overdue_cnt
      FROM crm_tasks t WHERE t.deleted_at IS NULL AND $wT")) {
        $row = mysqli_fetch_assoc($r) ?: [];
        $pendingTasks = (int)($row['open_cnt'] ?? 0);
        $overdueTasks = (int)($row['overdue_cnt'] ?? 0);
        mysqli_free_result($r);
    }

        // Follow-up compliance (approx via tasks completion on-time)
        $onTime = 0; $hasDue = 0;
        if (crm_column_exists($conn, 'crm_tasks', 'completed_at')) {
                if ($r = mysqli_query($conn, "SELECT 
                        SUM(t.due_date IS NOT NULL) AS due_cnt,
                        SUM(t.completed_at IS NOT NULL AND t.due_date IS NOT NULL AND DATE(t.completed_at) <= t.due_date) AS on_time
                    FROM crm_tasks t WHERE t.deleted_at IS NULL AND $wT")) {
                        $row = mysqli_fetch_assoc($r) ?: [];
                        $hasDue = (int)($row['due_cnt'] ?? 0);
                        $onTime = (int)($row['on_time'] ?? 0);
                        mysqli_free_result($r);
                }
        } else {
                // Fallback: use status='Completed' as proxy for completion time (no completed_at available)
                if ($r = mysqli_query($conn, "SELECT 
                        SUM(t.due_date IS NOT NULL) AS due_cnt,
                        SUM(t.status = 'Completed' AND t.due_date IS NOT NULL) AS on_time
                    FROM crm_tasks t WHERE t.deleted_at IS NULL AND $wT")) {
                        $row = mysqli_fetch_assoc($r) ?: [];
                        $hasDue = (int)($row['due_cnt'] ?? 0);
                        $onTime = (int)($row['on_time'] ?? 0);
                        mysqli_free_result($r);
                }
        }
    $compliance = $hasDue ? round(($onTime / $hasDue) * 100, 1) : 0.0;

    $avgResp = crm_avg_response_time_days($conn, $filters);

    return [
        'total_leads' => $tot,
        'active_leads' => $active,
        'conversion_rate' => $convRate,
        'followup_compliance' => $compliance,
        'avg_response_days' => $avgResp,
        'pending_tasks' => $pendingTasks,
        'overdue_tasks' => $overdueTasks,
    ];
}

function crm_employee_performance(mysqli $conn, array $filters): array {
    // Return both aggregated list for table and chart datasets
    $perf = [];

    // Helper to ensure entry
    $touch = function(int $empId) use (&$perf) {
        if (!isset($perf[$empId])) {
            $perf[$empId] = [
                'leads' => 0, 'leads_converted' => 0,
                'calls' => 0, 'meetings' => 0, 'visits' => 0,
                'tasks_completed' => 0,
                'tasks_due' => 0, 'tasks_on_time' => 0,
                'avg_response_days_sum' => 0.0, 'avg_response_count' => 0,
            ];
        }
    };

    // Leads by assigned_to within range
    $wL = crm_filter_where($filters, 'leads', 'l');
    $sql = "SELECT assigned_to AS e, COUNT(*) AS c, SUM(status='Converted') AS conv FROM crm_leads l WHERE l.deleted_at IS NULL AND $wL AND assigned_to IS NOT NULL GROUP BY assigned_to";
    if ($res = mysqli_query($conn, $sql)) {
        while ($r = mysqli_fetch_assoc($res)) { $eid = (int)$r['e']; $touch($eid); $perf[$eid]['leads'] += (int)$r['c']; $perf[$eid]['leads_converted'] += (int)$r['conv']; }
        mysqli_free_result($res);
    }
    // Calls by assigned_to
    $wC = crm_filter_where($filters, 'calls', 'c');
    if ($res = mysqli_query($conn, "SELECT assigned_to AS e, COUNT(*) AS c FROM crm_calls c WHERE c.deleted_at IS NULL AND $wC AND assigned_to IS NOT NULL GROUP BY assigned_to")) {
        while ($r = mysqli_fetch_assoc($res)) { $eid = (int)$r['e']; $touch($eid); $perf[$eid]['calls'] += (int)$r['c']; }
        mysqli_free_result($res);
    }
    // Meetings
    $wM = crm_filter_where($filters, 'meetings', 'm');
    if ($res = mysqli_query($conn, "SELECT assigned_to AS e, COUNT(*) AS c FROM crm_meetings m WHERE m.deleted_at IS NULL AND $wM AND assigned_to IS NOT NULL GROUP BY assigned_to")) {
        while ($r = mysqli_fetch_assoc($res)) { $eid = (int)$r['e']; $touch($eid); $perf[$eid]['meetings'] += (int)$r['c']; }
        mysqli_free_result($res);
    }
    // Visits
    $wV = crm_filter_where($filters, 'visits', 'v');
    if ($res = mysqli_query($conn, "SELECT assigned_to AS e, COUNT(*) AS c FROM crm_visits v WHERE v.deleted_at IS NULL AND $wV AND assigned_to IS NOT NULL GROUP BY assigned_to")) {
        while ($r = mysqli_fetch_assoc($res)) { $eid = (int)$r['e']; $touch($eid); $perf[$eid]['visits'] += (int)$r['c']; }
        mysqli_free_result($res);
    }
    // Tasks completed and due/on-time by assigned_to
    $wT = crm_filter_where($filters, 'tasks', 't');
        if (crm_column_exists($conn, 'crm_tasks', 'completed_at')) {
                if ($res = mysqli_query($conn, "SELECT assigned_to AS e,
                        SUM(status='Completed') AS completed,
                        SUM(due_date IS NOT NULL) AS due_cnt,
                        SUM(completed_at IS NOT NULL AND due_date IS NOT NULL AND DATE(completed_at) <= due_date) AS on_time
                    FROM crm_tasks t WHERE t.deleted_at IS NULL AND $wT AND assigned_to IS NOT NULL GROUP BY assigned_to")) {
                        while ($r = mysqli_fetch_assoc($res)) { $eid = (int)$r['e']; $touch($eid); $perf[$eid]['tasks_completed'] += (int)$r['completed']; $perf[$eid]['tasks_due'] += (int)$r['due_cnt']; $perf[$eid]['tasks_on_time'] += (int)$r['on_time']; }
                        mysqli_free_result($res);
                }
        } else {
                // Fallback when completed_at not present: use status='Completed' as proxy for on_time
                if ($res = mysqli_query($conn, "SELECT assigned_to AS e,
                        SUM(status='Completed') AS completed,
                        SUM(due_date IS NOT NULL) AS due_cnt,
                        SUM(status='Completed' AND due_date IS NOT NULL) AS on_time
                    FROM crm_tasks t WHERE t.deleted_at IS NULL AND $wT AND assigned_to IS NOT NULL GROUP BY assigned_to")) {
                        while ($r = mysqli_fetch_assoc($res)) { $eid = (int)$r['e']; $touch($eid); $perf[$eid]['tasks_completed'] += (int)$r['completed']; $perf[$eid]['tasks_due'] += (int)$r['due_cnt']; $perf[$eid]['tasks_on_time'] += (int)$r['on_time']; }
                        mysqli_free_result($res);
                }
        }

    // Average response time per employee (based on leads assigned to them)
    // Check if crm_tasks has lead_id column
    $tasks_has_lead_id = crm_column_exists($conn, 'crm_tasks', 'lead_id');
    
    $res = mysqli_query($conn, "SELECT l.id, l.assigned_to, l.created_at FROM crm_leads l WHERE l.deleted_at IS NULL AND $wL AND assigned_to IS NOT NULL");
    if ($res) {
        while ($lead = mysqli_fetch_assoc($res)) {
            $eid = (int)$lead['assigned_to'];
            $touch($eid);
            $lid = (int)$lead['id'];
            $created = new DateTime($lead['created_at']);
            $minDate = null;
            
            // Build activity queries (crm_tasks only if lead_id exists)
            $queries = [
                ["SELECT MIN(call_date) AS d FROM crm_calls WHERE deleted_at IS NULL AND lead_id=$lid", 'd'],
                ["SELECT MIN(meeting_date) AS d FROM crm_meetings WHERE deleted_at IS NULL AND lead_id=$lid", 'd'],
                ["SELECT MIN(visit_date) AS d FROM crm_visits WHERE deleted_at IS NULL AND lead_id=$lid", 'd'],
            ];
            if ($tasks_has_lead_id) {
                $queries[] = ["SELECT MIN(created_at) AS d FROM crm_tasks WHERE deleted_at IS NULL AND lead_id=$lid", 'd'];
            }
            
            foreach ($queries as $spec) {
                $r2 = mysqli_query($conn, $spec[0]);
                if ($r2) { $row = mysqli_fetch_assoc($r2); $d = $row['d'] ?? null; mysqli_free_result($r2); if ($d) { $dt = new DateTime($d); if ($minDate === null || $dt < $minDate) $minDate = $dt; } }
            }
            if ($minDate) {
                $diff = $created->diff($minDate);
                $days = (float)$diff->days + ($diff->h / 24.0);
                $perf[$eid]['avg_response_days_sum'] += $days;
                $perf[$eid]['avg_response_count']++;
            }
        }
        mysqli_free_result($res);
    }

    // Map to final structures
    $empMap = crm_fetch_employee_map($conn);
    $rows = [];
    $chart = ['labels' => [], 'leads' => [], 'conv_pct' => [], 'followup_pct' => []];
    foreach ($perf as $eid => $p) {
        $name = $empMap[$eid] ?? ('Emp #'.$eid);
        $convPct = $p['leads'] ? round(($p['leads_converted'] / $p['leads']) * 100, 1) : 0.0;
        $followPct = $p['tasks_due'] ? round(($p['tasks_on_time'] / $p['tasks_due']) * 100, 1) : 0.0;
        $avgResp = $p['avg_response_count'] ? round($p['avg_response_days_sum'] / $p['avg_response_count'], 1) : 0.0;

        $rows[] = [
            'employee' => $name,
            'leads' => $p['leads'],
            'calls' => $p['calls'],
            'meetings' => $p['meetings'],
            'visits' => $p['visits'],
            'tasks_completed' => $p['tasks_completed'],
            'conv_pct' => $convPct,
            'followup_pct' => $followPct,
            'avg_response_days' => $avgResp,
        ];

        $chart['labels'][] = $name;
        $chart['leads'][] = $p['leads'];
        $chart['conv_pct'][] = $convPct;
        $chart['followup_pct'][] = $followPct;
    }

    return ['rows' => $rows, 'chart' => $chart];
}

function crm_recent_interactions(mysqli $conn, array $filters, int $limit = 20): array {
    // Union latest activities across modules within date window
    $s = addslashes($filters['start']);
    $e = addslashes($filters['end']);
    $empClause = !empty($filters['employee_id']) ? (' AND assigned_to='.(int)$filters['employee_id']) : '';
    $deptClause = !empty($filters['department']) ? (" AND EXISTS (SELECT 1 FROM employees e WHERE e.id = x.assigned_to AND e.department='".addslashes($filters['department'])."')") : '';
    
    // Check which columns exist in each table
    $calls_has_lead_id = crm_column_exists($conn, 'crm_calls', 'lead_id');
    $calls_has_followup = crm_column_exists($conn, 'crm_calls', 'follow_up_date');
    
    $meetings_has_lead_id = crm_column_exists($conn, 'crm_meetings', 'lead_id');
    $meetings_has_followup = crm_column_exists($conn, 'crm_meetings', 'follow_up_date');
    
    $visits_has_lead_id = crm_column_exists($conn, 'crm_visits', 'lead_id');
    $visits_has_followup = crm_column_exists($conn, 'crm_visits', 'follow_up_date');
    
    $tasks_has_lead_id = crm_column_exists($conn, 'crm_tasks', 'lead_id');
    $tasks_has_followup = crm_column_exists($conn, 'crm_tasks', 'follow_up_date');
    
    // Lead source filter applies only to leads
    $leadSourceJoin = "LEFT JOIN crm_leads l ON l.id = x.lead_id";
    $leadSourceClause = !empty($filters['lead_source']) ? (" AND l.source='".addslashes($filters['lead_source'])."'") : '';
    
    // Build UNION query with conditional column selects
    $queries = [];
    
    // Calls
    $lead_col = $calls_has_lead_id ? 'x.lead_id' : 'NULL';
    $followup_col = $calls_has_followup ? 'x.follow_up_date' : 'NULL';
    $join = ($calls_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceJoin : '';
    $clause = ($calls_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceClause : '';
    $queries[] = "(SELECT 'Call' AS type, x.id, x.call_date AS d, x.assigned_to, $lead_col AS lead_id, x.outcome AS outcome, $followup_col AS next_date
         FROM crm_calls x $join
         WHERE x.deleted_at IS NULL AND x.call_date BETWEEN '$s 00:00:00' AND '$e 23:59:59' $empClause $deptClause $clause)";
    
    // Meetings
    $lead_col = $meetings_has_lead_id ? 'x.lead_id' : 'NULL';
    $followup_col = $meetings_has_followup ? 'x.follow_up_date' : 'NULL';
    $join = ($meetings_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceJoin : '';
    $clause = ($meetings_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceClause : '';
    $queries[] = "(SELECT 'Meeting' AS type, x.id, x.meeting_date AS d, x.assigned_to, $lead_col AS lead_id, x.outcome AS outcome, $followup_col AS next_date
         FROM crm_meetings x $join
         WHERE x.deleted_at IS NULL AND x.meeting_date BETWEEN '$s 00:00:00' AND '$e 23:59:59' $empClause $deptClause $clause)";
    
    // Visits
    $lead_col = $visits_has_lead_id ? 'x.lead_id' : 'NULL';
    $followup_col = $visits_has_followup ? 'x.follow_up_date' : 'NULL';
    $join = ($visits_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceJoin : '';
    $clause = ($visits_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceClause : '';
    $queries[] = "(SELECT 'Visit' AS type, x.id, x.visit_date AS d, x.assigned_to, $lead_col AS lead_id, x.outcome AS outcome, $followup_col AS next_date
         FROM crm_visits x $join
         WHERE x.deleted_at IS NULL AND x.visit_date BETWEEN '$s 00:00:00' AND '$e 23:59:59' $empClause $deptClause $clause)";
    
    // Tasks
    $lead_col = $tasks_has_lead_id ? 'x.lead_id' : 'NULL';
    $followup_col = $tasks_has_followup ? 'x.follow_up_date' : 'NULL';
    $join = ($tasks_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceJoin : '';
    $clause = ($tasks_has_lead_id && !empty($filters['lead_source'])) ? $leadSourceClause : '';
    $queries[] = "(SELECT 'Task' AS type, x.id, x.created_at AS d, x.assigned_to, $lead_col AS lead_id, x.status AS outcome, $followup_col AS next_date
         FROM crm_tasks x $join
         WHERE x.deleted_at IS NULL AND x.created_at BETWEEN '$s 00:00:00' AND '$e 23:59:59' $empClause $deptClause $clause)";
    
    $sql = implode(' UNION ALL ', $queries) . ' ORDER BY d DESC LIMIT ' . (int)$limit;

    $rows = [];
    if ($res = mysqli_query($conn, $sql)) {
        $empMap = crm_fetch_employee_map($conn);
        while ($r = mysqli_fetch_assoc($res)) {
            $leadName = null;
            if (!empty($r['lead_id'])) {
                $res2 = mysqli_query($conn, 'SELECT name FROM crm_leads WHERE id='.(int)$r['lead_id'].' LIMIT 1');
                if ($res2) { $r2 = mysqli_fetch_assoc($res2); $leadName = $r2['name'] ?? null; mysqli_free_result($res2);}    
            }
            $rows[] = [
                'date' => $r['d'],
                'employee' => $empMap[(int)($r['assigned_to'] ?? 0)] ?? '—',
                'lead' => $leadName,
                'type' => $r['type'],
                'outcome' => $r['outcome'] ?? '',
                'next_followup' => $r['next_date'] ?? null,
            ];
        }
        mysqli_free_result($res);
    }
    return $rows;
}

function crm_followup_matrix(mysqli $conn, array $filters): array {
    // Approximated using due/on-time stats from tasks and counts of scheduled follow_ups elsewhere
    $tot = 0; $onTime = 0; $delayed = 0; $autoFromLeads = 0; $manuallyAdded = 0; $avgGap = 0.0;
    // Scheduled follow-ups: all non-null follow_up_date in all modules within filters
    $qSpec = [
        ['leads','l','follow_up_date'],
        ['calls','c','follow_up_date'],
        ['meetings','m','follow_up_date'],
        ['visits','v','follow_up_date'],
        ['tasks','t','follow_up_date'],
    ];
    $daysSum = 0; $daysCnt = 0;
    foreach ($qSpec as $sp) {
        [$entity,$alias,$col] = $sp;
        $w = crm_filter_where($filters, $entity, $alias);
        $table = 'crm_' . $entity;
        
        // Check if column exists before querying
        if (!crm_column_exists($conn, $table, $col)) {
            continue;
        }
        
        $res = mysqli_query($conn, "SELECT $col AS d FROM $table $alias WHERE $alias.deleted_at IS NULL AND $w AND $col IS NOT NULL");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $tot++;
                // Average follow-up gap: distance from today to planned date (as proxy)
                $d = $r['d'];
                if ($d) { $diff = (new DateTime('today'))->diff(new DateTime($d)); $daysSum += (float)$diff->days; $daysCnt++; }
            }
            mysqli_free_result($res);
        }
    }
    // On-time / delayed via Tasks (reliable)
    $wT = crm_filter_where($filters, 'tasks', 't');
        if (crm_column_exists($conn, 'crm_tasks', 'completed_at')) {
                if ($res = mysqli_query($conn, "SELECT 
                        SUM(t.due_date IS NOT NULL AND t.completed_at IS NOT NULL AND DATE(t.completed_at) <= t.due_date) AS `on_time`,
                        SUM(t.due_date IS NOT NULL AND (t.completed_at IS NULL OR DATE(t.completed_at) > t.due_date)) AS `delayed`
                    FROM crm_tasks t WHERE t.deleted_at IS NULL AND $wT")) {
                        $r = mysqli_fetch_assoc($res) ?: [];
                        $onTime += (int)($r['on_time'] ?? 0);
                        $delayed += (int)($r['delayed'] ?? 0);
                        mysqli_free_result($res);
                }
        } else {
                // Fallback when completed_at missing: approximate using status
                if ($res = mysqli_query($conn, "SELECT 
                        SUM(t.due_date IS NOT NULL AND t.status = 'Completed') AS `on_time`,
                        SUM(t.due_date IS NOT NULL AND t.status <> 'Completed') AS `delayed`
                    FROM crm_tasks t WHERE t.deleted_at IS NULL AND $wT")) {
                        $r = mysqli_fetch_assoc($res) ?: [];
                        $onTime += (int)($r['on_time'] ?? 0);
                        $delayed += (int)($r['delayed'] ?? 0);
                        mysqli_free_result($res);
                }
        }
    // Auto-generated from leads: leads.follow_up_created flag (only if columns exist)
    $wL = crm_filter_where($filters, 'leads', 'l');
    $leads_has_followup = crm_column_exists($conn, 'crm_leads', 'follow_up_date');
    $leads_has_created_flag = crm_column_exists($conn, 'crm_leads', 'follow_up_created');
    
    if ($leads_has_followup && $leads_has_created_flag) {
        if ($res = mysqli_query($conn, "SELECT 
            SUM(l.follow_up_date IS NOT NULL) AS scheduled,
            SUM(l.follow_up_created = 1) AS auto_created
          FROM crm_leads l WHERE l.deleted_at IS NULL AND $wL")) {
            $r = mysqli_fetch_assoc($res) ?: [];
            $manuallyAdded += max(0, ((int)($r['scheduled'] ?? 0)) - ((int)($r['auto_created'] ?? 0)));
            $autoFromLeads += (int)($r['auto_created'] ?? 0);
            mysqli_free_result($res);
        }
    } elseif ($leads_has_followup) {
        // If only follow_up_date exists but not follow_up_created flag
        if ($res = mysqli_query($conn, "SELECT SUM(l.follow_up_date IS NOT NULL) AS scheduled FROM crm_leads l WHERE l.deleted_at IS NULL AND $wL")) {
            $r = mysqli_fetch_assoc($res) ?: [];
            $manuallyAdded += (int)($r['scheduled'] ?? 0);
            mysqli_free_result($res);
        }
    }

    $avgGap = $daysCnt ? round($daysSum / $daysCnt, 1) : 0.0;
    $pctOn = $tot ? round(($onTime / $tot) * 100, 1) : 0.0;
    $pctDel = $tot ? round(($delayed / $tot) * 100, 1) : 0.0;
    $pctAuto = ($autoFromLeads + $manuallyAdded) ? round(($autoFromLeads / ($autoFromLeads + $manuallyAdded)) * 100, 1) : 0.0;

    return [
        'total_scheduled' => $tot,
        'completed_on_time_pct' => $pctOn,
        'delayed_pct' => $pctDel,
        'auto_from_leads_pct' => $pctAuto,
        'avg_followup_gap_days' => $avgGap,
    ];
}

function crm_filter_options(mysqli $conn): array {
    // Employee list
    $employees = crm_fetch_employees($conn);
    // Lead sources
    $srcs = crm_lead_sources();
    // Department filter removed from options
    return ['employees' => $employees, 'sources' => $srcs];
}

/**
 * Check if a column exists in a table (safe helper)
 */
function crm_column_exists(mysqli $conn, string $table, string $column): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $exists;
}

?>