<?php
/**
 * CRM Calls - Common Helper Functions
 * Shared utilities for the Calls module
 */

require_once __DIR__ . '/../helpers.php';

/**
 * Get available call outcomes
 */
function crm_call_outcomes(): array {
    return [
        'Interested',
        'Not Interested',
        'No Answer',
        'Voicemail',
        'Callback Requested',
        'Follow-Up Required',
        'Meeting Scheduled',
        'Converted',
        'Declined',
        'Other'
    ];
}

/**
 * Check if a column exists in crm_calls table
 */
function crm_calls_has_column(mysqli $conn, string $column): bool {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = mysqli_query($conn, "SHOW COLUMNS FROM crm_calls");
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
 * Safe fetch from call array
 */
function crm_call_get(array $call, string $key, $default = '') {
    return $call[$key] ?? $default;
}

/**
 * Format call duration for display
 */
function crm_format_duration(?string $duration): string {
    if (!$duration || trim($duration) === '') {
        return 'N/A';
    }
    return htmlspecialchars(trim($duration));
}

/**
 * Get badge class for call outcome
 */
function crm_call_outcome_badge(?string $outcome): string {
    if (!$outcome || trim($outcome) === '') {
        return 'badge-secondary';
    }
    $outcome_lower = strtolower($outcome);
    if (str_contains($outcome_lower, 'interested') || str_contains($outcome_lower, 'converted')) {
        return 'badge-success';
    }
    if (str_contains($outcome_lower, 'not interested') || str_contains($outcome_lower, 'declined')) {
        return 'badge-danger';
    }
    if (str_contains($outcome_lower, 'follow-up') || str_contains($outcome_lower, 'callback')) {
        return 'badge-warning';
    }
    if (str_contains($outcome_lower, 'meeting')) {
        return 'badge-info';
    }
    return 'badge-secondary';
}

/**
 * Update lead's last_contacted_at when a call is logged
 */
function crm_update_lead_contact_time(mysqli $conn, int $lead_id): bool {
    // Check if last_contacted_at column exists in crm_leads
    $res = mysqli_query($conn, "SHOW COLUMNS FROM crm_leads WHERE Field = 'last_contacted_at'");
    if (!$res || mysqli_num_rows($res) === 0) {
        // Column doesn't exist, skip update
        return true;
    }
    mysqli_free_result($res);
    
    $stmt = mysqli_prepare($conn, "UPDATE crm_leads SET last_contacted_at = NOW() WHERE id = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'i', $lead_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Fetch leads for dropdown (active leads only)
 */
function crm_fetch_active_leads(mysqli $conn): array {
    $leads = [];
    
    // Build SELECT list based on available columns
    $base_cols = ['id', 'name'];
    $optional_cols = ['company_name', 'phone'];
    $select_cols = [];
    
    // Always include base columns
    $select_cols = array_merge($select_cols, $base_cols);
    
    // Include optional columns if they exist
    $res = mysqli_query($conn, "SHOW COLUMNS FROM crm_leads");
    $available_cols = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $available_cols[$row['Field']] = true;
        }
        mysqli_free_result($res);
    }
    
    foreach ($optional_cols as $col) {
        if (isset($available_cols[$col])) {
            $select_cols[] = $col;
        }
    }
    
    $select = implode(', ', $select_cols);
    
    // Build WHERE clause based on available columns
    $where = ['deleted_at IS NULL'];
    if (isset($available_cols['status'])) {
        $where[] = "status != 'Dropped'";
    }
    $where_clause = implode(' AND ', $where);
    
    $query = "SELECT {$select} FROM crm_leads 
              WHERE {$where_clause} 
              ORDER BY name ASC";
    $res = mysqli_query($conn, $query);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $leads[] = $row;
        }
        mysqli_free_result($res);
    }
    return $leads;
}

/**
 * Build safe call SELECT columns based on schema
 */
function crm_calls_select_columns(mysqli $conn): string {
    // Build column list only from columns that actually exist in the table
    $base = ['id', 'lead_id', 'title', 'summary', 'call_date', 'duration', 'outcome',
             'created_by', 'assigned_to', 'location', 'attachment', 'created_at', 'updated_at'];
    $optional = ['notes', 'follow_up_date', 'follow_up_type', 'call_type', 'latitude', 'longitude'];

    $columns = [];
    foreach ($base as $col) {
        if (crm_calls_has_column($conn, $col)) {
            $columns[] = $col;
        }
    }
    foreach ($optional as $col) {
        if (crm_calls_has_column($conn, $col)) {
            $columns[] = $col;
        }
    }

    // Fallback: if no columns detected (unexpected), select c.id and c.created_at
    if (empty($columns)) {
        return 'c.id, c.created_at';
    }

    return 'c.' . implode(', c.', $columns);
}

/**
 * Check if multiple columns exist
 */
function crm_calls_has_columns(mysqli $conn, array $columns): bool {
    foreach ($columns as $col) {
        if (!crm_calls_has_column($conn, $col)) {
            return false;
        }
    }
    return true;
}

/**
 * Create a follow-up activity when a call is scheduled with follow-up
 * Creates the activity in the appropriate module based on follow_up_type
 * @param mysqli $conn Database connection
 * @param int $lead_id Lead ID
 * @param int $assigned_to Employee ID to assign the activity to
 * @param string $follow_up_date Follow-up date (YYYY-MM-DD format)
 * @param string $follow_up_type Type of follow-up ('Call', 'Email', 'Meeting', 'Task', 'Visit')
 * @param string $call_title Original call title for context
 * @return bool Success status
 */
/**
 * Create follow-up task/activity for a lead
 * @deprecated Use crm_create_followup_activity() from helpers.php instead
 * This wrapper is kept for backward compatibility
 */
function crm_create_followup_task(mysqli $conn, int $lead_id, int $assigned_to, string $follow_up_date, string $follow_up_type, string $call_title): bool {
    return crm_create_followup_activity($conn, $lead_id, $assigned_to, $follow_up_date, $follow_up_type, $call_title);
}

/**
 * Update lead's follow_up_date when a call with follow-up is scheduled
 * @deprecated Use crm_update_lead_followup_date() from helpers.php instead
 * This wrapper is kept for backward compatibility
 * @param mysqli $conn Database connection
 * @param int $lead_id Lead ID
 * @param string $follow_up_date Follow-up date (YYYY-MM-DD format)
 * @param string $follow_up_type Type of follow-up for notes
 * @return bool Success status
 */
function crm_update_lead_followup_date_calls(mysqli $conn, int $lead_id, string $follow_up_date, string $follow_up_type): bool {
    return crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
}

?>