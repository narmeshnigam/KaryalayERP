<?php
/**
 * Projects Module - Helper Functions
 * Comprehensive functions for project management
 */

// Mark that authz uses the connection
$GLOBALS['AUTHZ_CONN_MANAGED'] = true;

// Create connection if not exists
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/db_connect.php';
    require_once __DIR__ . '/../../includes/authz.php';
    $conn = createConnection();
}

require_once __DIR__ . '/../../config/module_dependencies.php';

// Check prerequisites
$conn_check = createConnection(true);
if ($conn_check) {
    $prereq_check = get_prerequisite_check_result($conn_check, 'projects');
    if (!$prereq_check['allowed']) {
        closeConnection($conn_check);
        display_prerequisite_error('projects', $prereq_check['missing_modules']);
    }
    closeConnection($conn_check);
}

/**
 * Check if projects tables exist
 */
function projects_tables_exist($conn) {
    $required_tables = ['projects', 'project_phases', 'project_tasks', 'project_members'];
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            return false;
        }
    }
    return true;
}

/**
 * Generate unique project code
 */
function generate_project_code($conn, $title) {
    // Get first 3 letters of title (uppercase)
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $title), 0, 3));
    if (strlen($prefix) < 3) {
        $prefix = str_pad($prefix, 3, 'X');
    }
    
    // Find next number
    $stmt = $conn->prepare("SELECT project_code FROM projects WHERE project_code LIKE ? ORDER BY project_code DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $next_num = 1;
    if ($row = $result->fetch_assoc()) {
        $last_code = $row['project_code'];
        $num_part = (int)preg_replace('/[^0-9]/', '', $last_code);
        $next_num = $num_part + 1;
    }
    
    $stmt->close();
    return $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Create new project
 */
function create_project($conn, $data) {
    $project_code = generate_project_code($conn, $data['title']);
    
    $stmt = $conn->prepare("
        INSERT INTO projects (project_code, title, type, client_id, owner_id, description, 
                             start_date, end_date, priority, status, tags, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("sssiisssssis", 
        $project_code,
        $data['title'],
        $data['type'],
        $data['client_id'],
        $data['owner_id'],
        $data['description'],
        $data['start_date'],
        $data['end_date'],
        $data['priority'],
        $data['status'],
        $data['tags'],
        $data['created_by']
    );
    
    if ($stmt->execute()) {
        $project_id = $stmt->insert_id;
        $stmt->close();
        
        // Auto-add owner as project member
        add_project_member($conn, $project_id, $data['owner_id'], 'Owner');
        
        // Log activity
        log_project_activity($conn, $project_id, $data['created_by'], 'General', null, 
                            "Project created: {$data['title']}");
        
        return $project_id;
    }
    
    $stmt->close();
    return false;
}

/**
 * Get project by ID
 */
function get_project_by_id($conn, $project_id) {
    $stmt = $conn->prepare("
        SELECT p.*, 
               u1.username as owner_username,
               u2.username as created_by_username,
               c.name as client_name,
               c.code as client_code
        FROM projects p
        LEFT JOIN users u1 ON p.owner_id = u1.id
        LEFT JOIN users u2 ON p.created_by = u2.id
        LEFT JOIN clients c ON p.client_id = c.id
        WHERE p.id = ?
    ");
    
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    $stmt->close();
    
    return $project;
}

/**
 * Update project
 */
function update_project($conn, $project_id, $data, $user_id) {
    $stmt = $conn->prepare("
        UPDATE projects 
        SET title = ?, type = ?, client_id = ?, owner_id = ?, description = ?,
            start_date = ?, end_date = ?, priority = ?, status = ?, tags = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param("ssiississis",
        $data['title'],
        $data['type'],
        $data['client_id'],
        $data['owner_id'],
        $data['description'],
        $data['start_date'],
        $data['end_date'],
        $data['priority'],
        $data['status'],
        $data['tags'],
        $project_id
    );
    
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        log_project_activity($conn, $project_id, $user_id, 'Status', $project_id,
                            "Project updated");
    }
    
    return $success;
}

/**
 * Get all projects with filters
 */
function get_all_projects($conn, $user_id, $filters = []) {
    $sql = "SELECT p.*, 
                   u1.username as owner_username,
                   c.name as client_name,
                   c.code as client_code,
                   COUNT(DISTINCT pm.id) as member_count,
                   COUNT(DISTINCT pt.id) as task_count,
                   COUNT(DISTINCT CASE WHEN pt.status = 'Completed' THEN pt.id END) as completed_tasks
            FROM projects p
            LEFT JOIN users u1 ON p.owner_id = u1.id
            LEFT JOIN clients c ON p.client_id = c.id
            LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.removed_at IS NULL
            LEFT JOIN project_tasks pt ON p.id = pt.project_id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Search filter
    if (!empty($filters['search'])) {
        $sql .= " AND (p.title LIKE ? OR p.project_code LIKE ? OR p.description LIKE ?)";
        $search = "%{$filters['search']}%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    
    // Status filter
    if (!empty($filters['status'])) {
        $sql .= " AND p.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    // Type filter
    if (!empty($filters['type'])) {
        $sql .= " AND p.type = ?";
        $params[] = $filters['type'];
        $types .= "s";
    }
    
    // Priority filter
    if (!empty($filters['priority'])) {
        $sql .= " AND p.priority = ?";
        $params[] = $filters['priority'];
        $types .= "s";
    }
    
    // Owner filter
    if (!empty($filters['owner_id'])) {
        $sql .= " AND p.owner_id = ?";
        $params[] = $filters['owner_id'];
        $types .= "i";
    }
    
    // Client filter
    if (!empty($filters['client_id'])) {
        $sql .= " AND p.client_id = ?";
        $params[] = $filters['client_id'];
        $types .= "i";
    }
    
    // My projects filter (member of)
    if (!empty($filters['my_projects'])) {
        $sql .= " AND pm.user_id = ? AND pm.removed_at IS NULL";
        $params[] = $user_id;
        $types .= "i";
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $projects;
}

/**
 * Get project statistics
 */
function get_project_statistics($conn, $user_id) {
    $stats = [
        'total' => 0,
        'active' => 0,
        'completed' => 0,
        'my_projects' => 0,
        'overdue' => 0
    ];
    
    // Total projects
    $result = $conn->query("SELECT COUNT(*) as cnt FROM projects");
    $stats['total'] = $result->fetch_assoc()['cnt'];
    
    // Active projects
    $result = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE status = 'Active'");
    $stats['active'] = $result->fetch_assoc()['cnt'];
    
    // Completed projects
    $result = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE status = 'Completed'");
    $stats['completed'] = $result->fetch_assoc()['cnt'];
    
    // My projects (member of)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.id) as cnt 
        FROM projects p
        INNER JOIN project_members pm ON p.id = pm.project_id 
        WHERE pm.user_id = ? AND pm.removed_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['my_projects'] = $result->fetch_assoc()['cnt'];
    $stmt->close();
    
    // Overdue projects
    $result = $conn->query("
        SELECT COUNT(*) as cnt FROM projects 
        WHERE status NOT IN ('Completed', 'Archived') 
        AND end_date < CURDATE()
    ");
    $stats['overdue'] = $result->fetch_assoc()['cnt'];
    
    return $stats;
}

/**
 * Add project member
 */
function add_project_member($conn, $project_id, $user_id, $role = 'Contributor') {
    // Check if exists (active or removed)
    $stmt = $conn->prepare("SELECT id, removed_at FROM project_members WHERE project_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        // If previously removed, restore
        if (!empty($row['removed_at'])) {
            $up = $conn->prepare("UPDATE project_members SET removed_at = NULL, role = ? WHERE id = ?");
            $up->bind_param("si", $role, $row['id']);
            $ok = $up->execute();
            $up->close();
            if ($ok) {
                log_project_activity($conn, $project_id, $_SESSION['user_id'], 'Member', $user_id,
                    "Member re-added with role: $role");
            }
            return $ok;
        }
        // Already active member
        return true;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $project_id, $user_id, $role);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        log_project_activity($conn, $project_id, $_SESSION['user_id'], 'Member', $user_id,
                            "Member added with role: $role");
    }
    
    return $success;
}

function remove_project_member($conn, $project_id, $user_id, $actor_user_id) {
    $stmt = $conn->prepare("UPDATE project_members SET removed_at = CURRENT_TIMESTAMP WHERE project_id = ? AND user_id = ? AND removed_at IS NULL");
    $stmt->bind_param("ii", $project_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $conn->affected_rows > 0) {
        log_project_activity($conn, $project_id, $actor_user_id, 'Member', $user_id, 'Member removed');
        return true;
    }
    return false;
}

function update_project_member_role($conn, $project_id, $user_id, $role, $actor_user_id) {
    $stmt = $conn->prepare("UPDATE project_members SET role = ? WHERE project_id = ? AND user_id = ? AND removed_at IS NULL");
    $stmt->bind_param("sii", $role, $project_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $conn->affected_rows > 0) {
        log_project_activity($conn, $project_id, $actor_user_id, 'Member', $user_id, 'Member role updated: ' . $role);
        return true;
    }
    return false;
}

function get_available_users_for_project($conn, $project_id) {
    $stmt = $conn->prepare("SELECT u.id, u.username, u.email
                            FROM users u
                            WHERE u.id NOT IN (
                                SELECT user_id FROM project_members WHERE project_id = ? AND removed_at IS NULL
                            )
                            ORDER BY u.username ASC");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

function get_removed_project_members($conn, $project_id) {
    $stmt = $conn->prepare("SELECT pm.*, u.username, u.email
                            FROM project_members pm
                            INNER JOIN users u ON pm.user_id = u.id
                            WHERE pm.project_id = ? AND pm.removed_at IS NOT NULL
                            ORDER BY pm.removed_at DESC");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Get project members
 */
function get_project_members($conn, $project_id) {
    $stmt = $conn->prepare("
        SELECT pm.*, u.username, u.email
        FROM project_members pm
        INNER JOIN users u ON pm.user_id = u.id
        WHERE pm.project_id = ? AND pm.removed_at IS NULL
        ORDER BY pm.role ASC, u.username ASC
    ");
    
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $members = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $members;
}

/**
 * Log project activity
 */
function log_project_activity($conn, $project_id, $user_id, $activity_type, $reference_id, $description) {
    $stmt = $conn->prepare("
        INSERT INTO project_activity_log (project_id, user_id, activity_type, reference_id, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("iisis", $project_id, $user_id, $activity_type, $reference_id, $description);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get recent project activity
 */
function get_project_activity($conn, $project_id, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT pal.*, u.username
        FROM project_activity_log pal
        INNER JOIN users u ON pal.user_id = u.id
        WHERE pal.project_id = ?
        ORDER BY pal.created_at DESC
        LIMIT ?
    ");
    
    $stmt->bind_param("ii", $project_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $activities;
}

/**
 * Phase helpers
 */

function validate_phase_data(array $data): array {
    $errors = [];
    if (empty(trim($data['title'] ?? ''))) {
        $errors[] = 'Phase title is required.';
    }
    $sd = $data['start_date'] ?? null;
    $ed = $data['end_date'] ?? null;
    if (!empty($sd) && !empty($ed) && strtotime($sd) > strtotime($ed)) {
        $errors[] = 'End date must be on or after start date.';
    }
    // Align with DB enum: Pending, In Progress, Completed, On Hold
    $valid_status = ['Pending','In Progress','On Hold','Completed'];
    if (!empty($data['status']) && !in_array($data['status'], $valid_status, true)) {
        $errors[] = 'Invalid phase status.';
    }
    return $errors;
}

function get_next_phase_sequence($conn, $project_id): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sequence_order),0) AS max_seq FROM project_phases WHERE project_id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $max_seq = (int)($stmt->get_result()->fetch_assoc()['max_seq'] ?? 0);
    $stmt->close();
    return $max_seq + 1;
}

function create_phase($conn, $project_id, array $data, $user_id) {
    $errors = validate_phase_data($data);
    if ($errors) return ['ok' => false, 'errors' => $errors];
    $seq = get_next_phase_sequence($conn, $project_id);
    $status = $data['status'] ?? 'Pending';
    $stmt = $conn->prepare("INSERT INTO project_phases (project_id, title, description, start_date, end_date, status, progress, sequence_order) VALUES (?,?,?,?,?,?,?,?)");
    $progress = 0.0;
    $stmt->bind_param("isssssdi", $project_id, $data['title'], $data['description'], $data['start_date'], $data['end_date'], $status, $progress, $seq);
    $ok = $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();
    if ($ok) {
        log_project_activity($conn, $project_id, $user_id, 'Phase', $new_id, 'Phase created: ' . $data['title']);
        recalc_phase_progress($conn, $new_id);
        recalc_project_progress($conn, $project_id);
        return ['ok' => true, 'id' => $new_id];
    }
    return ['ok' => false, 'errors' => ['Failed to create phase.']];
}

function update_phase($conn, $phase_id, array $data, $user_id) {
    $errors = validate_phase_data($data);
    if ($errors) return ['ok' => false, 'errors' => $errors];
    // fetch project_id for logging and recalculation
    $pid_stmt = $conn->prepare("SELECT project_id FROM project_phases WHERE id = ?");
    $pid_stmt->bind_param("i", $phase_id);
    $pid_stmt->execute();
    $pid_res = $pid_stmt->get_result()->fetch_assoc();
    $pid_stmt->close();
    if (!$pid_res) return ['ok' => false, 'errors' => ['Phase not found.']];
    $project_id = (int)$pid_res['project_id'];

    $stmt = $conn->prepare("UPDATE project_phases SET title = ?, description = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $data['title'], $data['description'], $data['start_date'], $data['end_date'], $data['status'], $phase_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        recalc_phase_progress($conn, $phase_id);
        recalc_project_progress($conn, $project_id);
        log_project_activity($conn, $project_id, $user_id, 'Phase', $phase_id, 'Phase updated: ' . $data['title']);
        return ['ok' => true];
    }
    return ['ok' => false, 'errors' => ['Failed to update phase.']];
}

function delete_phase($conn, $phase_id, $user_id) {
    // find project
    $pid_stmt = $conn->prepare("SELECT project_id, title FROM project_phases WHERE id = ?");
    $pid_stmt->bind_param("i", $phase_id);
    $pid_stmt->execute();
    $row = $pid_stmt->get_result()->fetch_assoc();
    $pid_stmt->close();
    if (!$row) return false;
    $project_id = (int)$row['project_id'];
    $title = $row['title'];
    $stmt = $conn->prepare("DELETE FROM project_phases WHERE id = ?");
    $stmt->bind_param("i", $phase_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        recalc_project_progress($conn, $project_id);
        log_project_activity($conn, $project_id, $user_id, 'Phase', $phase_id, 'Phase deleted: ' . $title);
    }
    return $ok;
}

function move_phase($conn, $project_id, $phase_id, string $direction): bool {
    // get current sequence
    $stmt = $conn->prepare("SELECT id, sequence_order FROM project_phases WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $phase_id, $project_id);
    $stmt->execute();
    $cur = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cur) return false;
    $cur_seq = (int)$cur['sequence_order'];
    $op = $direction === 'up' ? '<' : '>';
    $sort = $direction === 'up' ? 'DESC' : 'ASC';
    $stmt = $conn->prepare("SELECT id, sequence_order FROM project_phases WHERE project_id = ? AND sequence_order $op ? ORDER BY sequence_order $sort LIMIT 1");
    $stmt->bind_param("ii", $project_id, $cur_seq);
    $stmt->execute();
    $adj = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$adj) return false;
    // swap
    $conn->begin_transaction();
    $stmt1 = $conn->prepare("UPDATE project_phases SET sequence_order = ? WHERE id = ?");
    $stmt1->bind_param("ii", $adj['sequence_order'], $phase_id);
    $ok1 = $stmt1->execute();
    $stmt1->close();
    $stmt2 = $conn->prepare("UPDATE project_phases SET sequence_order = ? WHERE id = ?");
    $stmt2->bind_param("ii", $cur_seq, $adj['id']);
    $ok2 = $stmt2->execute();
    $stmt2->close();
    if ($ok1 && $ok2) { $conn->commit(); return true; }
    $conn->rollback();
    return false;
}

function recalc_phase_progress($conn, $phase_id): void {
    // progress from tasks in phase
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN status='Completed' THEN 1 END) AS completed FROM project_tasks WHERE phase_id = ?");
    $stmt->bind_param("i", $phase_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $total = (int)($r['total'] ?? 0);
    $completed = (int)($r['completed'] ?? 0);
    $progress = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
    $status = $progress >= 100 ? 'Completed' : null;
    if ($status) {
        $stmt = $conn->prepare("UPDATE project_phases SET progress = ?, status = ? WHERE id = ?");
        $stmt->bind_param("dsi", $progress, $status, $phase_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE project_phases SET progress = ? WHERE id = ?");
        $stmt->bind_param("di", $progress, $phase_id);
        $stmt->execute();
        $stmt->close();
    }
}

function recalc_project_progress($conn, $project_id): void {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN status='Completed' THEN 1 END) AS completed FROM project_tasks WHERE project_id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $total = (int)($r['total'] ?? 0);
    $completed = (int)($r['completed'] ?? 0);
    $progress = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
    $stmt = $conn->prepare("UPDATE projects SET progress = ? WHERE id = ?");
    $stmt->bind_param("di", $progress, $project_id);
    $stmt->execute();
    $stmt->close();
}

function get_phases_with_stats($conn, $project_id): array {
    $stmt = $conn->prepare("SELECT ph.*, 
        (SELECT COUNT(*) FROM project_tasks t WHERE t.phase_id = ph.id) AS tasks_total,
        (SELECT COUNT(*) FROM project_tasks t WHERE t.phase_id = ph.id AND t.status='Completed') AS tasks_completed,
        (SELECT COUNT(*) FROM project_tasks t WHERE t.phase_id = ph.id AND t.status!='Completed' AND t.due_date < CURDATE()) AS tasks_overdue
        FROM project_phases ph WHERE ph.project_id = ? ORDER BY ph.sequence_order ASC, ph.id ASC");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

/**
 * Validate project data
 */
function validate_project_data($data) {
    $errors = [];
    
    if (empty($data['title'])) {
        $errors[] = "Project title is required";
    }
    
    if (empty($data['type'])) {
        $errors[] = "Project type is required";
    }
    
    if (empty($data['owner_id'])) {
        $errors[] = "Project owner is required";
    }
    
    if (!empty($data['start_date']) && !empty($data['end_date'])) {
        if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
            $errors[] = "Start date cannot be after end date";
        }
    }
    
    if ($data['type'] === 'Client' && empty($data['client_id'])) {
        $errors[] = "Client is required for client-linked projects";
    }
    
    return $errors;
}

/**
 * Get status icon
 */
function get_project_status_icon($status) {
    $icons = [
        'Draft' => '📝',
        'Active' => '🚀',
        'On Hold' => '⏸️',
        'Completed' => '✅',
        'Archived' => '📦'
    ];
    return $icons[$status] ?? '📋';
}

/**
 * Get priority icon
 */
function get_priority_icon($priority) {
    $icons = [
        'Low' => '🔵',
        'Medium' => '🟡',
        'High' => '🟠',
        'Critical' => '🔴'
    ];
    return $icons[$priority] ?? '⚪';
}

/**
 * Get type icon
 */
function get_project_type_icon($type) {
    return $type === 'Client' ? '🏢' : '🏠';
}

/**
 * Get project initials
 */
function get_project_initials($title) {
    $words = explode(' ', $title);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($title, 0, 2));
}

/**
 * Check if user is project member
 */
function is_project_member($conn, $project_id, $user_id) {
    $stmt = $conn->prepare("
        SELECT id FROM project_members 
        WHERE project_id = ? AND user_id = ? AND removed_at IS NULL
    ");
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_member = $result->num_rows > 0;
    $stmt->close();
    
    return $is_member;
}

/**
 * Get project KPIs
 */
function get_project_kpis($conn, $project_id) {
    $kpis = [
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'overdue_tasks' => 0,
        'total_phases' => 0,
        'completed_phases' => 0,
        'total_members' => 0,
        'total_documents' => 0
    ];
    
    // Tasks
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed,
            COUNT(CASE WHEN status != 'Completed' AND due_date < CURDATE() THEN 1 END) as overdue
        FROM project_tasks WHERE project_id = ?
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $kpis['total_tasks'] = $result['total'];
    $kpis['completed_tasks'] = $result['completed'];
    $kpis['overdue_tasks'] = $result['overdue'];
    $stmt->close();
    
    // Phases
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed
        FROM project_phases WHERE project_id = ?
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $kpis['total_phases'] = $result['total'];
    $kpis['completed_phases'] = $result['completed'];
    $stmt->close();
    
    // Members
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM project_members WHERE project_id = ? AND removed_at IS NULL");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $kpis['total_members'] = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    
    // Documents
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM project_documents WHERE project_id = ? AND is_active = 1");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $kpis['total_documents'] = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    
    return $kpis;
}

/**
 * Document helpers
 */

function sanitize_upload_filename(string $name): string {
    $name = preg_replace('/[\\\/:*?"<>|]+/', '-', $name); // replace illegal
    $name = preg_replace('/\s+/', '_', $name);
    return preg_replace('/[^A-Za-z0-9_.-]/', '', $name);
}

/**
 * Activity helpers (filters + pagination)
 */

function get_project_activity_filtered($conn, int $project_id, array $filters = [], int $limit = 25, int $offset = 0): array {
    $sql = "SELECT pal.*, u.username
            FROM project_activity_log pal
            INNER JOIN users u ON pal.user_id = u.id
            WHERE pal.project_id = ?";
    $types = 'i';
    $params = [$project_id];

    if (!empty($filters['activity_type'])) { $sql .= " AND pal.activity_type = ?"; $types .= 's'; $params[] = $filters['activity_type']; }
    if (!empty($filters['user_id'])) { $sql .= " AND pal.user_id = ?"; $types .= 'i'; $params[] = (int)$filters['user_id']; }
    if (!empty($filters['date_from'])) { $sql .= " AND pal.created_at >= ?"; $types .= 's'; $params[] = $filters['date_from'] . ' 00:00:00'; }
    if (!empty($filters['date_to'])) { $sql .= " AND pal.created_at <= ?"; $types .= 's'; $params[] = $filters['date_to'] . ' 23:59:59'; }
    if (!empty($filters['search'])) { $sql .= " AND pal.description LIKE ?"; $types .= 's'; $params[] = '%' . $filters['search'] . '%'; }

    $sql .= " ORDER BY pal.created_at DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit; $params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function count_project_activity($conn, int $project_id, array $filters = []): int {
    $sql = "SELECT COUNT(*) AS cnt
            FROM project_activity_log pal
            WHERE pal.project_id = ?";
    $types = 'i';
    $params = [$project_id];
    if (!empty($filters['activity_type'])) { $sql .= " AND pal.activity_type = ?"; $types .= 's'; $params[] = $filters['activity_type']; }
    if (!empty($filters['user_id'])) { $sql .= " AND pal.user_id = ?"; $types .= 'i'; $params[] = (int)$filters['user_id']; }
    if (!empty($filters['date_from'])) { $sql .= " AND pal.created_at >= ?"; $types .= 's'; $params[] = $filters['date_from'] . ' 00:00:00'; }
    if (!empty($filters['date_to'])) { $sql .= " AND pal.created_at <= ?"; $types .= 's'; $params[] = $filters['date_to'] . ' 23:59:59'; }
    if (!empty($filters['search'])) { $sql .= " AND pal.description LIKE ?"; $types .= 's'; $params[] = '%' . $filters['search'] . '%'; }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $cnt;
}

/**
 * Dashboard helpers for organization-wide analytics
 */

function get_dashboard_kpis($conn): array {
    $kpis = [
        'total_projects' => 0,
        'active_projects' => 0,
        'completed_projects' => 0,
        'on_hold_projects' => 0,
        'internal_projects' => 0,
        'client_projects' => 0,
        'avg_progress' => 0.0,
        'overdue_tasks' => 0
    ];
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM projects")->fetch_assoc();
    $kpis['total_projects'] = (int)$r['cnt'];
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE status='Active'")->fetch_assoc();
    $kpis['active_projects'] = (int)$r['cnt'];
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE status='Completed'")->fetch_assoc();
    $kpis['completed_projects'] = (int)$r['cnt'];
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE status='On Hold'")->fetch_assoc();
    $kpis['on_hold_projects'] = (int)$r['cnt'];
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE type='Internal'")->fetch_assoc();
    $kpis['internal_projects'] = (int)$r['cnt'];
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM projects WHERE type='Client'")->fetch_assoc();
    $kpis['client_projects'] = (int)$r['cnt'];
    
    $r = $conn->query("SELECT AVG(progress) as avg_prog FROM projects")->fetch_assoc();
    $kpis['avg_progress'] = round((float)($r['avg_prog'] ?? 0), 2);
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM project_tasks WHERE status != 'Completed' AND due_date < CURDATE()")->fetch_assoc();
    $kpis['overdue_tasks'] = (int)$r['cnt'];
    
    return $kpis;
}

function get_dashboard_charts_data($conn): array {
    // Status distribution
    $status_stmt = $conn->query("SELECT status, COUNT(*) as cnt FROM projects GROUP BY status");
    $status_data = [];
    while ($row = $status_stmt->fetch_assoc()) {
        $status_data[] = ['label' => $row['status'], 'value' => (int)$row['cnt']];
    }
    
    // Type distribution
    $type_stmt = $conn->query("SELECT type, COUNT(*) as cnt FROM projects GROUP BY type");
    $type_data = [];
    while ($row = $type_stmt->fetch_assoc()) {
        $type_data[] = ['label' => $row['type'], 'value' => (int)$row['cnt']];
    }
    
    // Monthly trends (last 6 months)
    $trends_stmt = $conn->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt
        FROM projects
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $trends_data = [];
    while ($row = $trends_stmt->fetch_assoc()) {
        $trends_data[] = ['month' => $row['month'], 'count' => (int)$row['cnt']];
    }
    
    return [
        'status' => $status_data,
        'type' => $type_data,
        'trends' => $trends_data
    ];
}

function get_dashboard_workload($conn): array {
    $stmt = $conn->query("
        SELECT u.id, u.username,
               COUNT(DISTINCT ta.task_id) as assigned_tasks,
               COUNT(DISTINCT CASE WHEN t.status='Completed' THEN ta.task_id END) as completed_tasks
        FROM users u
        LEFT JOIN project_task_assignees ta ON u.id = ta.user_id
        LEFT JOIN project_tasks t ON ta.task_id = t.id
        GROUP BY u.id, u.username
        HAVING assigned_tasks > 0
        ORDER BY assigned_tasks DESC
        LIMIT 20
    ");
    
    $workload = [];
    while ($row = $stmt->fetch_assoc()) {
        $assigned = (int)$row['assigned_tasks'];
        $completed = (int)$row['completed_tasks'];
        $efficiency = $assigned > 0 ? round(($completed / $assigned) * 100, 1) : 0.0;
        $workload[] = [
            'user_id' => (int)$row['id'],
            'username' => $row['username'],
            'assigned_tasks' => $assigned,
            'completed_tasks' => $completed,
            'efficiency' => $efficiency
        ];
    }
    
    return $workload;
}

function get_dashboard_alerts($conn): array {
    $alerts = [];
    
    // Projects nearing deadline (within 7 days)
    $stmt = $conn->query("
        SELECT id, project_code, title, end_date
        FROM projects
        WHERE status NOT IN ('Completed', 'Archived')
          AND end_date IS NOT NULL
          AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY end_date ASC
        LIMIT 10
    ");
    while ($row = $stmt->fetch_assoc()) {
        $alerts[] = [
            'type' => 'deadline',
            'project_id' => (int)$row['id'],
            'project_code' => $row['project_code'],
            'title' => $row['title'],
            'end_date' => $row['end_date'],
            'message' => 'Deadline approaching: ' . date('M j, Y', strtotime($row['end_date']))
        ];
    }
    
    // Stagnant projects (no activity in 7+ days)
    $stmt = $conn->query("
        SELECT p.id, p.project_code, p.title, MAX(pal.created_at) as last_activity
        FROM projects p
        LEFT JOIN project_activity_log pal ON p.id = pal.project_id
        WHERE p.status NOT IN ('Completed', 'Archived')
        GROUP BY p.id, p.project_code, p.title
        HAVING last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY) OR last_activity IS NULL
        ORDER BY last_activity ASC
        LIMIT 10
    ");
    while ($row = $stmt->fetch_assoc()) {
        $alerts[] = [
            'type' => 'stagnant',
            'project_id' => (int)$row['id'],
            'project_code' => $row['project_code'],
            'title' => $row['title'],
            'last_activity' => $row['last_activity'],
            'message' => 'No activity since ' . ($row['last_activity'] ? date('M j, Y', strtotime($row['last_activity'])) : 'project creation')
        ];
    }
    
    return $alerts;
}

function get_dashboard_recent_projects($conn, int $limit = 10): array {
    $stmt = $conn->prepare("
        SELECT p.id, p.project_code, p.title, p.type, p.status, p.progress, p.created_at,
               u.username as owner_username,
               c.name as client_name
        FROM projects p
        LEFT JOIN users u ON p.owner_id = u.id
        LEFT JOIN clients c ON p.client_id = c.id
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $projects;
}

function get_dashboard_top_projects($conn): array {
    $stmt = $conn->query("
        SELECT p.id, p.project_code, p.title, p.progress, p.status,
               u.username as owner_username,
               c.name as client_name
        FROM projects p
        LEFT JOIN users u ON p.owner_id = u.id
        LEFT JOIN clients c ON p.client_id = c.id
        WHERE p.status NOT IN ('Archived')
        ORDER BY p.progress DESC
        LIMIT 5
    ");
    return $stmt->fetch_all(MYSQLI_ASSOC);
}

function get_dashboard_high_task_load_projects($conn): array {
    $stmt = $conn->query("
        SELECT p.id, p.project_code, p.title, COUNT(t.id) as task_count
        FROM projects p
        INNER JOIN project_tasks t ON p.id = t.project_id
        WHERE p.status NOT IN ('Completed', 'Archived')
        GROUP BY p.id, p.project_code, p.title
        ORDER BY task_count DESC
        LIMIT 5
    ");
    return $stmt->fetch_all(MYSQLI_ASSOC);
}

function get_documents($conn, int $project_id, array $filters = []): array {
    $sql = "SELECT d.*, u.username AS uploaded_by_name
            FROM project_documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.project_id = ?";
    $types = 'i';
    $params = [$project_id];
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'active') { $sql .= " AND d.is_active = 1"; }
        if ($filters['status'] === 'inactive') { $sql .= " AND d.is_active = 0"; }
    }
    if (!empty($filters['type'])) { $sql .= " AND d.doc_type = ?"; $types .= 's'; $params[] = $filters['type']; }
    if (!empty($filters['search'])) {
        $sql .= " AND (d.file_name LIKE ? OR d.file_path LIKE ?)";
        $s = '%' . $filters['search'] . '%';
        $types .= 'ss';
        $params[] = $s; $params[] = $s;
    }
    $sql .= " ORDER BY d.is_active DESC, d.uploaded_at DESC, d.version DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_document_types($conn, int $project_id): array {
    $stmt = $conn->prepare("SELECT DISTINCT doc_type FROM project_documents WHERE project_id = ? AND doc_type IS NOT NULL AND doc_type <> '' ORDER BY doc_type ASC");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $types = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $types[] = $row['doc_type']; }
    $stmt->close();
    return $types;
}

function get_next_document_version($conn, int $project_id, string $file_name): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(version),0) AS v FROM project_documents WHERE project_id = ? AND file_name = ?");
    $stmt->bind_param("is", $project_id, $file_name);
    $stmt->execute();
    $v = (int)($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();
    return $v + 1;
}

function upload_project_document($conn, int $project_id, array $fileArr, ?string $doc_type, int $user_id): array {
    if (empty($fileArr['name']) || $fileArr['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'errors' => ['No file uploaded or upload error.']];
    }

    $original_name = sanitize_upload_filename($fileArr['name']);
    if ($original_name === '') { return ['ok' => false, 'errors' => ['Invalid file name.']]; }

    // Determine version
    $version = get_next_document_version($conn, $project_id, $original_name);

    // Prepare directories
    $uploadDirFS = realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $project_id;
    if (!is_dir($uploadDirFS)) { @mkdir($uploadDirFS, 0755, true); }

    $ext = pathinfo($original_name, PATHINFO_EXTENSION);
    $base = pathinfo($original_name, PATHINFO_FILENAME);
    $stored_name = sanitize_upload_filename($base . '_v' . $version . ($ext ? ('.' . $ext) : ''));
    $targetFS = $uploadDirFS . DIRECTORY_SEPARATOR . $stored_name;

    if (!move_uploaded_file($fileArr['tmp_name'], $targetFS)) {
        return ['ok' => false, 'errors' => ['Failed to move uploaded file.']];
    }

    // Build web path
    $file_path = '/KaryalayERP/uploads/projects/' . $project_id . '/' . $stored_name;

    // Insert document row
    $stmt = $conn->prepare("INSERT INTO project_documents (project_id, file_name, file_path, doc_type, uploaded_by, version, is_active) VALUES (?,?,?,?,?,?,1)");
    $stmt->bind_param("isssii", $project_id, $original_name, $file_path, $doc_type, $user_id, $version);
    $ok = $stmt->execute();
    $doc_id = $stmt->insert_id;
    $stmt->close();
    if (!$ok) {
        @unlink($targetFS);
        return ['ok' => false, 'errors' => ['Failed to save document record.']];
    }

    // Deactivate previous active version with same file_name
    $stmt = $conn->prepare("UPDATE project_documents SET is_active = 0 WHERE project_id = ? AND file_name = ? AND id <> ?");
    $stmt->bind_param("isi", $project_id, $original_name, $doc_id);
    $stmt->execute();
    $stmt->close();

    log_project_activity($conn, $project_id, $user_id, 'Document', $doc_id, 'Document uploaded: ' . $original_name . ' (v' . $version . ')');
    return ['ok' => true, 'id' => $doc_id, 'version' => $version, 'file_path' => $file_path];
}

function deactivate_project_document($conn, int $doc_id, int $user_id): bool {
    $stmt = $conn->prepare("SELECT project_id, file_name FROM project_documents WHERE id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    $project_id = (int)$row['project_id'];
    $stmt = $conn->prepare("UPDATE project_documents SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $doc_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        log_project_activity($conn, $project_id, $user_id, 'Document', $doc_id, 'Document deactivated: ' . $row['file_name']);
    }
    return $ok;
}

function activate_project_document($conn, int $doc_id, int $user_id): bool {
    $stmt = $conn->prepare("SELECT project_id, file_name FROM project_documents WHERE id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    $project_id = (int)$row['project_id'];

    // Activate this version and deactivate others for same file_name
    $a = $conn->prepare("UPDATE project_documents SET is_active = 1 WHERE id = ?");
    $a->bind_param("i", $doc_id);
    $ok1 = $a->execute();
    $a->close();
    $d = $conn->prepare("UPDATE project_documents SET is_active = 0 WHERE project_id = ? AND file_name = ? AND id <> ?");
    $d->bind_param("isi", $project_id, $row['file_name'], $doc_id);
    $ok2 = $d->execute();
    $d->close();

    if ($ok1) {
        log_project_activity($conn, $project_id, $user_id, 'Document', $doc_id, 'Document activated: ' . $row['file_name']);
    }
    return $ok1 && $ok2;
}

/**
 * Task helpers
 */

function validate_task_data(array $data): array {
    $errors = [];
    if (empty(trim($data['title'] ?? ''))) {
        $errors[] = 'Task title is required.';
    }
    $valid_status = ['Pending','In Progress','Review','Completed'];
    if (!empty($data['status']) && !in_array($data['status'], $valid_status, true)) {
        $errors[] = 'Invalid task status.';
    }
    $valid_priority = ['Low','Medium','High','Critical'];
    if (!empty($data['priority']) && !in_array($data['priority'], $valid_priority, true)) {
        $errors[] = 'Invalid task priority.';
    }
    if (!empty($data['due_date']) && strtotime($data['due_date']) === false) {
        $errors[] = 'Invalid due date.';
    }
    return $errors;
}

function phase_belongs_to_project($conn, $phase_id, $project_id): bool {
    if (empty($phase_id)) return true;
    $stmt = $conn->prepare("SELECT id FROM project_phases WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $phase_id, $project_id);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

function create_task($conn, int $project_id, array $data, int $user_id) {
    $errors = validate_task_data($data);
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $phase_id = !empty($data['phase_id']) ? (int)$data['phase_id'] : null;
    if (!phase_belongs_to_project($conn, $phase_id, $project_id)) {
        $phase_id = null; // safety: do not allow cross-project linking
    }

    $title = trim($data['title']);
    $description = $data['description'] ?? null;
    $due_date = !empty($data['due_date']) ? $data['due_date'] : null;
    $priority = $data['priority'] ?? 'Medium';
    $status = $data['status'] ?? 'Pending';
    $progress = $status === 'Completed' ? 100.0 : (float)($data['progress'] ?? 0.0);

    $stmt = $conn->prepare("INSERT INTO project_tasks (project_id, phase_id, title, description, due_date, priority, status, progress, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("iisssssdi", $project_id, $phase_id, $title, $description, $due_date, $priority, $status, $progress, $user_id);
    $ok = $stmt->execute();
    $task_id = $stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'errors' => ['Failed to create task.']];
    }

    // Assignees
    if (!empty($data['assignees']) && is_array($data['assignees'])) {
        $ins = $conn->prepare("INSERT IGNORE INTO project_task_assignees (task_id, user_id) VALUES (?, ?)");
        foreach ($data['assignees'] as $uid) {
            $uid = (int)$uid;
            if ($uid > 0) {
                $ins->bind_param("ii", $task_id, $uid);
                $ins->execute();
            }
        }
        $ins->close();
    }

    log_project_activity($conn, $project_id, $user_id, 'Task', $task_id, 'Task created: ' . $title);
    if (!empty($phase_id)) { recalc_phase_progress($conn, $phase_id); }
    recalc_project_progress($conn, $project_id);

    return ['ok' => true, 'id' => $task_id];
}

function update_task($conn, int $task_id, array $data, int $user_id) {
    // Load existing
    $s = $conn->prepare("SELECT project_id, phase_id, title, progress FROM project_tasks WHERE id = ?");
    $s->bind_param("i", $task_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) return ['ok' => false, 'errors' => ['Task not found.']];
    $project_id = (int)$row['project_id'];
    $old_phase_id = $row['phase_id'] !== null ? (int)$row['phase_id'] : null;

    $errors = validate_task_data($data);
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $phase_id = !empty($data['phase_id']) ? (int)$data['phase_id'] : null;
    if (!phase_belongs_to_project($conn, $phase_id, $project_id)) { $phase_id = null; }
    $title = trim($data['title']);
    $description = $data['description'] ?? null;
    $due_date = !empty($data['due_date']) ? $data['due_date'] : null;
    $priority = $data['priority'] ?? 'Medium';
    $status = $data['status'] ?? 'Pending';
    $progress = isset($data['progress']) ? (float)$data['progress'] : ($status === 'Completed' ? 100.0 : (float)$row['progress']);

    $stmt = $conn->prepare("UPDATE project_tasks SET phase_id = ?, title = ?, description = ?, due_date = ?, priority = ?, status = ?, progress = ? WHERE id = ?");
    $stmt->bind_param("isssssdi", $phase_id, $title, $description, $due_date, $priority, $status, $progress, $task_id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) return ['ok' => false, 'errors' => ['Failed to update task.']];

    // Update assignees if provided
    if (array_key_exists('assignees', $data) && is_array($data['assignees'])) {
        $del = $conn->prepare("DELETE FROM project_task_assignees WHERE task_id = ?");
        $del->bind_param("i", $task_id);
        $del->execute();
        $del->close();
        if (!empty($data['assignees'])) {
            $ins = $conn->prepare("INSERT IGNORE INTO project_task_assignees (task_id, user_id) VALUES (?, ?)");
            foreach ($data['assignees'] as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) {
                    $ins->bind_param("ii", $task_id, $uid);
                    $ins->execute();
                }
            }
            $ins->close();
        }
    }

    if (!empty($phase_id)) { recalc_phase_progress($conn, $phase_id); }
    if (!empty($old_phase_id) && $old_phase_id !== $phase_id) { recalc_phase_progress($conn, $old_phase_id); }
    recalc_project_progress($conn, $project_id);
    log_project_activity($conn, $project_id, $user_id, 'Task', $task_id, 'Task updated: ' . $title);

    return ['ok' => true];
}

function delete_task($conn, int $task_id, int $user_id): bool {
    // Load existing
    $s = $conn->prepare("SELECT project_id, phase_id, title FROM project_tasks WHERE id = ?");
    $s->bind_param("i", $task_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) return false;
    $project_id = (int)$row['project_id'];
    $phase_id = $row['phase_id'] !== null ? (int)$row['phase_id'] : null;
    $title = $row['title'];

    $stmt = $conn->prepare("DELETE FROM project_tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        if (!empty($phase_id)) { recalc_phase_progress($conn, $phase_id); }
        recalc_project_progress($conn, $project_id);
        log_project_activity($conn, $project_id, $user_id, 'Task', $task_id, 'Task deleted: ' . $title);
    }
    return $ok;
}

function set_task_status($conn, int $task_id, string $status, int $user_id, ?string $closing_notes = null): bool {
    $valid_status = ['Pending','In Progress','Review','Completed'];
    if (!in_array($status, $valid_status, true)) { return false; }
    // Load existing
    $s = $conn->prepare("SELECT project_id, phase_id, progress, title FROM project_tasks WHERE id = ?");
    $s->bind_param("i", $task_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) return false;
    $project_id = (int)$row['project_id'];
    $phase_id = $row['phase_id'] !== null ? (int)$row['phase_id'] : null;
    $title = $row['title'];

    if ($status === 'Completed') {
        $progress = 100.0;
        $stmt = $conn->prepare("UPDATE project_tasks SET status = ?, progress = ?, marked_done_by = ?, closing_notes = ? WHERE id = ?");
        $stmt->bind_param("sdisi", $status, $progress, $user_id, $closing_notes, $task_id);
    } else {
        $progress = (float)$row['progress'];
        $stmt = $conn->prepare("UPDATE project_tasks SET status = ?, progress = ?, marked_done_by = NULL, closing_notes = NULL WHERE id = ?");
        $stmt->bind_param("sdi", $status, $progress, $task_id);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        if (!empty($phase_id)) { recalc_phase_progress($conn, $phase_id); }
        recalc_project_progress($conn, $project_id);
        log_project_activity($conn, $project_id, $user_id, 'Task', $task_id, 'Task status changed: ' . $title . ' → ' . $status);
    }
    return $ok;
}

function add_task_assignee($conn, int $task_id, int $assignee_user_id, int $actor_user_id): bool {
    $s = $conn->prepare("SELECT project_id, phase_id, title FROM project_tasks WHERE id = ?");
    $s->bind_param("i", $task_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) return false;
    $project_id = (int)$row['project_id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO project_task_assignees (task_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $task_id, $assignee_user_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        log_project_activity($conn, $project_id, $actor_user_id, 'Task', $task_id, 'Assignee added to task: ' . $row['title']);
    }
    return $ok;
}

function remove_task_assignee($conn, int $task_id, int $assignee_user_id, int $actor_user_id): bool {
    $s = $conn->prepare("SELECT project_id, title FROM project_tasks WHERE id = ?");
    $s->bind_param("i", $task_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) return false;
    $project_id = (int)$row['project_id'];
    $stmt = $conn->prepare("DELETE FROM project_task_assignees WHERE task_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $assignee_user_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        log_project_activity($conn, $project_id, $actor_user_id, 'Task', $task_id, 'Assignee removed from task: ' . $row['title']);
    }
    return $ok;
}

function get_tasks($conn, int $project_id, array $filters = []) : array {
    $sql = "SELECT t.*, ph.title AS phase_title,
                   GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') AS assignees,
                   GROUP_CONCAT(DISTINCT ta.user_id ORDER BY ta.user_id SEPARATOR ',') AS assignee_ids
            FROM project_tasks t
            LEFT JOIN project_phases ph ON t.phase_id = ph.id
            LEFT JOIN project_task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE t.project_id = ?";
    $params = [$project_id];
    $types = 'i';

    if (!empty($filters['status'])) { $sql .= " AND t.status = ?"; $params[] = $filters['status']; $types .= 's'; }
    if (!empty($filters['phase_id'])) { $sql .= " AND t.phase_id = ?"; $params[] = (int)$filters['phase_id']; $types .= 'i'; }
    if (!empty($filters['priority'])) { $sql .= " AND t.priority = ?"; $params[] = $filters['priority']; $types .= 's'; }
    if (!empty($filters['search'])) {
        $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params[] = $search; $params[] = $search; $types .= 'ss';
    }

    $sql .= " GROUP BY t.id ORDER BY ISNULL(t.due_date) ASC, t.due_date ASC, FIELD(t.priority,'Critical','High','Medium','Low'), t.created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}
