<?php
/**
 * CRM Tasks - Common Helpers
 */

require_once __DIR__ . '/../helpers.php';

/** Task statuses */
function crm_task_statuses(): array {
    return ['Pending','In Progress','Completed'];
}

/** Follow-up types */
function crm_task_follow_up_types(): array {
    return ['Call','Meeting','Visit','Task'];
}

/** Check if a column exists in crm_tasks */
function crm_tasks_has_column(mysqli $conn, string $column): bool {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = mysqli_query($conn, "SHOW COLUMNS FROM crm_tasks");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) { $cache[$row['Field']] = true; }
            mysqli_free_result($res);
        }
    }
    return isset($cache[$column]);
}

/** Helper to check arbitrary table/column existence */
function crm_tasks_has_column_in_table(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $t = mysqli_real_escape_string($conn, $table);
        $c = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        if ($res) mysqli_free_result($res);
    }
    return $cache[$key];
}

/** Build safe SELECT for tasks */
function crm_tasks_select_columns(mysqli $conn): string {
    $base = ['id','lead_id','title','description','status','due_date','completion_notes','completed_at','closed_by','follow_up_date','follow_up_type','created_by','assigned_to','location','attachment','created_at','updated_at'];
    $cols = [];
    foreach ($base as $c) { if (crm_tasks_has_column($conn, $c)) { $cols[] = $c; } }
    if (!$cols) { return 'c.id, c.created_at'; }
    return 'c.' . implode(', c.', $cols);
}

/** Safe get */
function crm_task_get(array $task, string $key, $default='') { return $task[$key] ?? $default; }

/** Validate due date: today or future */
function crm_task_validate_due_date(string $date): bool {
    $ts = strtotime($date);
    if ($ts === false) return false;
    return $ts >= strtotime('today');
}

/** Format employee label */
function crm_task_employee_label(string $code, string $first, string $last): string {
    $name = trim($first . ' ' . $last);
    if ($code) { return trim($code . ' - ' . ($name ?: '')); }
    return $name ?: 'Unassigned';
}

/** Update lead last_contacted_at if available */
function crm_task_touch_lead(mysqli $conn, int $lead_id): void {
    if (!crm_tasks_has_column_in_table($conn, 'crm_leads', 'last_contacted_at')) return;
    $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET last_contacted_at = NOW() WHERE id = ?');
    if ($stmt) { mysqli_stmt_bind_param($stmt,'i',$lead_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);}    
}
