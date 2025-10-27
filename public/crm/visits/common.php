<?php
/**
 * CRM Visits - Common Helper Functions
 * Shared utilities for the Visits module
 */

require_once __DIR__ . '/../helpers.php';

/**
 * Get available follow-up types
 */
function crm_visit_follow_up_types(): array {
    return ['Call', 'Meeting', 'Visit', 'Task'];
}

/**
 * Check if a column exists in crm_visits table
 */
function crm_visits_has_column(mysqli $conn, string $column): bool {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = mysqli_query($conn, "SHOW COLUMNS FROM crm_visits");
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
 * Build safe SELECT column list for visits based on existing columns
 */
function crm_visits_select_columns(mysqli $conn): string {
    $base = ['c.id', 'c.title', 'c.visit_date', 'c.created_at'];
    $optional = ['purpose', 'description', 'notes', 'lead_id', 'outcome', 'assigned_to', 'follow_up_date', 'follow_up_type', 'location', 'attachment', 'created_by', 'updated_at', 'status', 'latitude', 'longitude', 'visit_proof_image'];
    
    foreach ($optional as $col) {
        if (crm_visits_has_column($conn, $col)) {
            $base[] = "c.$col";
        }
    }
    
    return implode(', ', $base);
}

/**
 * Safe fetch from visit array
 */
function crm_visit_get(array $visit, string $key, $default = '') {
    return $visit[$key] ?? $default;
}

/**
 * Fetch active leads for dropdown with schema awareness
 */
function crm_fetch_active_leads_for_visits(mysqli $conn): array {
    $leads = [];
    
    // Check which columns are available
    $has_company = crm_visits_has_column_in_table($conn, 'crm_leads', 'company_name');
    $has_phone = crm_visits_has_column_in_table($conn, 'crm_leads', 'phone');
    $has_status = crm_visits_has_column_in_table($conn, 'crm_leads', 'status');
    
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
function crm_visits_has_column_in_table(mysqli $conn, string $table, string $column): bool {
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
 * Fetch single visit by ID
 */
function crm_visit_fetch(mysqli $conn, int $id): ?array {
    $cols = crm_visits_select_columns($conn);
    $has_lead = crm_visits_has_column($conn, 'lead_id');
    $has_assigned = crm_visits_has_column($conn, 'assigned_to');
    
    $joins = "";
    $lead_select = "";
    $emp_select = "";
    $creator_select = "";
    
    if ($has_lead) {
        $joins .= "LEFT JOIN crm_leads l ON c.lead_id = l.id ";
        $lead_select = ", l.name AS lead_name, l.company_name AS lead_company, l.phone AS lead_phone";
    }
    
    if ($has_assigned) {
        $joins .= "LEFT JOIN employees e ON c.assigned_to = e.id ";
        $emp_select = ", e.first_name AS assigned_first, e.last_name AS assigned_last, e.employee_code AS assigned_code";
    }
    
    $joins .= "LEFT JOIN employees creator ON c.created_by = creator.id ";
    $creator_select = ", creator.first_name AS creator_first, creator.last_name AS creator_last, creator.employee_code AS creator_code";
    
    $sql = "SELECT $cols $lead_select $emp_select $creator_select
            FROM crm_visits c
            $joins
            WHERE c.id = ? AND c.deleted_at IS NULL
            LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $visit = $res ? mysqli_fetch_assoc($res) : null;
    
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
    
    return $visit;
}

/**
 * Update lead's last_contacted_at after visit
 */
function crm_update_lead_contact_after_visit(mysqli $conn, int $lead_id): void {
    if (!crm_visits_has_column_in_table($conn, 'crm_leads', 'last_contacted_at')) {
        return; // Column doesn't exist in this schema
    }
    
    $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET last_contacted_at = NOW() WHERE id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $lead_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Validate visit date: must be today or in the future.
 * Accepts date/time strings (ISO from datetime-local) and ensures the
 * date is not in the past. We compare against the start of today so
 * dates on the same day are allowed (consistent with follow-up validation).
 */
function crm_visit_validate_date(string $date): bool {
    $timestamp = strtotime($date);
    if ($timestamp === false) return false;
    return $timestamp >= strtotime('today');
}

/**
 * Validate follow-up date
 */
function crm_visit_validate_followup_date(string $date): bool {
    $timestamp = strtotime($date);
    if ($timestamp === false) return false;
    return $timestamp >= strtotime('today');
}

/**
 * Format employee label
 */
function crm_visit_employee_label(string $code, string $first, string $last): string {
    if (!$first && !$last && !$code) return 'Unassigned';
    $name = trim("$first $last");
    return $code ? "$code - $name" : $name;
}

/**
 * Check if CRM tables exist
 */
// crm_tables_exist() is defined centrally in public/crm/helpers.php
// and should not be redeclared here to avoid fatal errors.

/**
 * Check if user has manager/admin role
 */
// The following helper functions are defined centrally in `public/crm/helpers.php`:
// - crm_tables_exist()
// - crm_role_can_manage()
// - crm_current_employee_id()
// - crm_employee_exists()
// Do not redeclare them here to avoid fatal redeclaration errors.
