<?php
/**
 * CRM Meetings - Common Helper Functions
 * Shared utilities for the Meetings module
 */

require_once __DIR__ . '/../helpers.php';

/**
 * Get available follow-up types
 */
function crm_meeting_follow_up_types(): array {
    return ['Call', 'Meeting', 'Visit', 'Task'];
}

/**
 * Check if a column exists in crm_meetings table
 */
function crm_meetings_has_column(mysqli $conn, string $column): bool {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = mysqli_query($conn, "SHOW COLUMNS FROM crm_meetings");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $cache[$row['Field']] = true;
            }
            mysqli_free_result($res);
        }
    }
    return isset($cache[$column]);
}

/**
 * Build safe SELECT column list for meetings based on existing columns
 */
function crm_meetings_select_columns(mysqli $conn): string {
    $base = ['c.id', 'c.title', 'c.meeting_date', 'c.created_at'];
    $optional = ['description', 'notes', 'lead_id', 'outcome', 'assigned_to', 'follow_up_date', 'follow_up_type', 'location', 'attachment', 'created_by', 'updated_at', 'start_time', 'end_time', 'status', 'latitude', 'longitude'];
    
    foreach ($optional as $col) {
        if (crm_meetings_has_column($conn, $col)) {
            $base[] = "c.$col";
        }
    }
    
    return implode(', ', $base);
}

/**
 * Safe fetch from meeting array
 */
function crm_meeting_get(array $meeting, string $key, $default = '') {
    return $meeting[$key] ?? $default;
}

/**
 * Fetch active leads for dropdown with schema awareness
 */
function crm_fetch_active_leads_for_meetings(mysqli $conn): array {
    $leads = [];
    
    // Check which columns are available
    $has_company = crm_meetings_has_column_in_table($conn, 'crm_leads', 'company_name');
    $has_phone = crm_meetings_has_column_in_table($conn, 'crm_leads', 'phone');
    $has_status = crm_meetings_has_column_in_table($conn, 'crm_leads', 'status');
    
    $select = "SELECT id, name";
    if ($has_company) $select .= ", company_name";
    if ($has_phone) $select .= ", phone";
    
    $sql = "$select FROM crm_leads WHERE deleted_at IS NULL";
    if ($has_status) {
        $sql .= " AND status IN ('New', 'Contacted')";
    }
    $sql .= " ORDER BY name ASC";
    
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $leads[] = $row;
        }
        mysqli_free_result($res);
    }
    
    return $leads;
}

/**
 * Helper to check column existence in any table
 */
function crm_meetings_has_column_in_table(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = "$table.$column";
    
    if (!isset($cache[$key])) {
        $table_esc = mysqli_real_escape_string($conn, $table);
        $column_esc = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table_esc` LIKE '$column_esc'");
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        if ($res) mysqli_free_result($res);
    }
    
    return $cache[$key];
}

/**
 * Fetch a single meeting by ID with joins for employee and lead details
 */
function crm_meeting_fetch(mysqli $conn, int $meeting_id): ?array {
    $has_lead_id = crm_meetings_has_column($conn, 'lead_id');
    $has_assigned_to = crm_meetings_has_column($conn, 'assigned_to');
    $has_created_by = crm_meetings_has_column($conn, 'created_by');
    
    $select_cols = crm_meetings_select_columns($conn);
    
    $joins = "";
    $lead_select = "";
    $emp_select = "";
    
    if ($has_lead_id) {
        $joins .= "LEFT JOIN crm_leads l ON c.lead_id = l.id ";
        $lead_select = ", l.name AS lead_name, l.company_name AS lead_company, l.phone AS lead_phone, l.email AS lead_email";
    }
    
    if ($has_assigned_to) {
        $joins .= "LEFT JOIN employees e1 ON c.assigned_to = e1.id ";
        $emp_select .= ", e1.first_name AS assigned_first, e1.last_name AS assigned_last, e1.employee_code AS assigned_code";
    }
    
    if ($has_created_by) {
        $joins .= "LEFT JOIN employees e2 ON c.created_by = e2.id ";
        $emp_select .= ", e2.first_name AS created_first, e2.last_name AS created_last, e2.employee_code AS created_code";
    }
    
    $sql = "SELECT $select_cols $lead_select $emp_select
            FROM crm_meetings c
            $joins
            WHERE c.id = ? AND c.deleted_at IS NULL
            LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, 'i', $meeting_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
    
    return $row ?: null;
}

/**
 * Update lead's last contact timestamp after meeting
 */
function crm_update_lead_contact_after_meeting(mysqli $conn, int $lead_id): void {
    if (!crm_meetings_has_column_in_table($conn, 'crm_leads', 'last_contacted_at')) {
        return;
    }
    
    $sql = "UPDATE crm_leads SET last_contacted_at = NOW() WHERE id = ? AND deleted_at IS NULL";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $lead_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Validate meeting date is not in the past
 */
function crm_meeting_validate_date(?string $date): bool {
    if (!$date || trim($date) === '') {
        return false;
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return false;
    }
    
    // Allow today and future dates
    $today_start = strtotime('today');
    return $timestamp >= $today_start;
}

/**
 * Validate follow-up date (must be today or future)
 */
function crm_meeting_validate_followup_date(?string $date): bool {
    if (!$date || trim($date) === '') {
        return true; // Optional field
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return false;
    }
    
    $today_start = strtotime('today');
    return $timestamp >= $today_start;
}

/**
 * Format employee name for display
 */
function crm_meeting_employee_label(?string $code, ?string $first, ?string $last): string {
    $parts = [];
    if ($code && trim($code) !== '') {
        $parts[] = trim($code);
    }
    $name = trim(($first ?? '') . ' ' . ($last ?? ''));
    if ($name !== '') {
        $parts[] = $name;
    }
    return $parts ? implode(' - ', $parts) : 'Unassigned';
}
