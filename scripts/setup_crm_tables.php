<?php
/**
 * CRM Module - Database Setup
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/module_dependencies.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'CRM - Module Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Check prerequisites
$conn_check = createConnection(true);
$prerequisite_check = $conn_check ? get_prerequisite_check_result($conn_check, 'crm') : ['allowed' => false, 'missing_modules' => []];
if ($conn_check) closeConnection($conn_check);

function crm_setup_table_exists(mysqli $conn, string $table): bool
{
    $t = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) { mysqli_free_result($res); }
    return $exists;
}

function crm_setup_all_exist(mysqli $conn): bool
{
    foreach (['crm_tasks','crm_calls','crm_meetings','crm_visits','crm_leads'] as $t) {
        if (!crm_setup_table_exists($conn, $t)) return false;
    }
    return true;
}

function crm_setup_ensure_upload_dir(): bool
{
    $dir = __DIR__ . '/../uploads/crm_attachments';
    if (!is_dir($dir)) {
        return mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function crm_setup_column_exists(mysqli $conn, string $table, string $column): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = mysqli_query($conn, $sql);
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) { mysqli_free_result($res); }
    return $exists;
}

function crm_setup_index_exists(mysqli $conn, string $table, string $index): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $index = mysqli_real_escape_string($conn, $index);
    $sql = "SHOW INDEX FROM `$table` WHERE Key_name = '$index'";
    $res = mysqli_query($conn, $sql);
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) { mysqli_free_result($res); }
    return $exists;
}

function crm_setup_has_duplicates(mysqli $conn, string $table, string $column): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $sql = "SELECT `$column` AS val FROM `$table` WHERE `$column` IS NOT NULL AND `$column` <> '' GROUP BY `$column` HAVING COUNT(*) > 1 LIMIT 1";
    $res = mysqli_query($conn, $sql);
    $has = ($res && mysqli_num_rows($res) > 0);
    if ($res) { mysqli_free_result($res); }
    return $has;
}

function crm_setup_create(): array
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Create tables
    $sql = [];
    $sql[] = "CREATE TABLE IF NOT EXISTS crm_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        lead_id INT NULL,
        assigned_to INT NOT NULL,
        status ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
        due_date DATE NULL,
        created_by INT NOT NULL,
        location TEXT NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        attachment TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_lead_id (lead_id),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_due_date (due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_calls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        summary TEXT NULL,
        notes TEXT NULL,
        lead_id INT NULL,
        call_date DATETIME NOT NULL,
        call_type VARCHAR(20) DEFAULT 'Logged',
        duration VARCHAR(50) NULL,
        outcome VARCHAR(100) NULL,
        location TEXT NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        attachment TEXT NULL,
        created_by INT NOT NULL,
        assigned_to INT NULL,
        follow_up_date DATE NULL,
        follow_up_type VARCHAR(50) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_lead_id (lead_id),
        INDEX idx_call_date (call_date),
        INDEX idx_call_type (call_type),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_outcome (outcome),
        INDEX idx_follow_up_date (follow_up_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        notes TEXT NULL,
        lead_id INT NULL,
        meeting_date DATETIME NOT NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        outcome TEXT NULL,
        status VARCHAR(50) DEFAULT 'Scheduled',
        follow_up_date DATE NULL,
        follow_up_type VARCHAR(50) NULL,
        assigned_to INT NULL,
        created_by INT NOT NULL,
        location TEXT NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        attachment TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_lead_id (lead_id),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_meeting_date (meeting_date),
        INDEX idx_status (status),
        INDEX idx_follow_up_date (follow_up_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        purpose TEXT NULL,
        description TEXT NULL,
        notes TEXT NULL,
        lead_id INT NULL,
        visit_date DATETIME NOT NULL,
        outcome TEXT NULL,
        status VARCHAR(50) DEFAULT 'Planned',
        follow_up_date DATE NULL,
        follow_up_type VARCHAR(50) NULL,
        assigned_to INT NULL,
        created_by INT NOT NULL,
        location TEXT NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        visit_proof_image TEXT NULL,
        attachment TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_lead_id (lead_id),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_visit_date (visit_date),
        INDEX idx_status (status),
        INDEX idx_follow_up_date (follow_up_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        company_name VARCHAR(150) NULL,
        phone VARCHAR(20) NULL,
        email VARCHAR(100) NULL,
        source VARCHAR(50) NULL,
        status ENUM('New','Contacted','Converted','Dropped') NOT NULL DEFAULT 'New',
        notes TEXT NULL,
        interests TEXT NULL,
        follow_up_date DATE NULL,
        follow_up_type ENUM('Call','Meeting','Visit','Task') NULL,
        follow_up_created TINYINT(1) NOT NULL DEFAULT 0,
        last_contacted_at DATETIME NULL,
        assigned_to INT NULL,
        attachment TEXT NULL,
        location TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uniq_leads_phone (phone),
        UNIQUE KEY uniq_leads_email (email),
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_follow_up_date (follow_up_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $ok = true;
    $error = '';
    foreach ($sql as $q) {
        if (!mysqli_query($conn, $q)) {
            $ok = false;
            $error = mysqli_error($conn);
            break;
        }
    }

    $warnings = [];
    if ($ok) {
        $columnStatements = [
            'company_name' => "ALTER TABLE crm_leads ADD COLUMN company_name VARCHAR(150) NULL AFTER name",
            'interests' => "ALTER TABLE crm_leads ADD COLUMN interests TEXT NULL AFTER notes",
            'follow_up_date' => "ALTER TABLE crm_leads ADD COLUMN follow_up_date DATE NULL AFTER interests",
            'follow_up_type' => "ALTER TABLE crm_leads ADD COLUMN follow_up_type ENUM('Call','Meeting','Visit','Task') NULL AFTER follow_up_date",
            'follow_up_created' => "ALTER TABLE crm_leads ADD COLUMN follow_up_created TINYINT(1) NOT NULL DEFAULT 0 AFTER follow_up_type",
            'last_contacted_at' => "ALTER TABLE crm_leads ADD COLUMN last_contacted_at DATETIME NULL AFTER follow_up_created",
            'updated_at' => "ALTER TABLE crm_leads ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            // CRM Tasks columns
            'tasks_lead_id' => "ALTER TABLE crm_tasks ADD COLUMN lead_id INT NULL AFTER description",
            'tasks_completion_notes' => "ALTER TABLE crm_tasks ADD COLUMN completion_notes TEXT NULL AFTER status",
            'tasks_completed_at' => "ALTER TABLE crm_tasks ADD COLUMN completed_at DATETIME NULL AFTER completion_notes",
            'tasks_closed_by' => "ALTER TABLE crm_tasks ADD COLUMN closed_by INT NULL AFTER completed_at",
            'tasks_follow_up_date' => "ALTER TABLE crm_tasks ADD COLUMN follow_up_date DATE NULL AFTER due_date",
            'tasks_follow_up_type' => "ALTER TABLE crm_tasks ADD COLUMN follow_up_type VARCHAR(50) NULL AFTER follow_up_date",
            'tasks_latitude' => "ALTER TABLE crm_tasks ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER location",
            'tasks_longitude' => "ALTER TABLE crm_tasks ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude",
            'tasks_updated_at' => "ALTER TABLE crm_tasks ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            // CRM Calls columns
            'calls_lead_id' => "ALTER TABLE crm_calls ADD COLUMN lead_id INT NULL AFTER summary",
            'calls_notes' => "ALTER TABLE crm_calls ADD COLUMN notes TEXT NULL AFTER summary",
            'calls_duration' => "ALTER TABLE crm_calls ADD COLUMN duration VARCHAR(50) NULL AFTER call_date",
            'calls_call_type' => "ALTER TABLE crm_calls ADD COLUMN call_type VARCHAR(20) DEFAULT 'Logged' AFTER call_date",
            'calls_outcome' => "ALTER TABLE crm_calls ADD COLUMN outcome VARCHAR(100) NULL AFTER duration",
            'calls_latitude' => "ALTER TABLE crm_calls ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER location",
            'calls_longitude' => "ALTER TABLE crm_calls ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude",
            'calls_assigned_to' => "ALTER TABLE crm_calls ADD COLUMN assigned_to INT NULL AFTER created_by",
            'calls_follow_up_date' => "ALTER TABLE crm_calls ADD COLUMN follow_up_date DATE NULL AFTER assigned_to",
            'calls_follow_up_type' => "ALTER TABLE crm_calls ADD COLUMN follow_up_type VARCHAR(50) NULL AFTER follow_up_date",
            'calls_updated_at' => "ALTER TABLE crm_calls ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            // CRM Meetings columns
            'meetings_lead_id' => "ALTER TABLE crm_meetings ADD COLUMN lead_id INT NULL AFTER description",
            'meetings_description' => "ALTER TABLE crm_meetings ADD COLUMN description TEXT NULL AFTER title",
            'meetings_notes' => "ALTER TABLE crm_meetings ADD COLUMN notes TEXT NULL AFTER description",
            'meetings_start_time' => "ALTER TABLE crm_meetings ADD COLUMN start_time TIME NULL AFTER meeting_date",
            'meetings_end_time' => "ALTER TABLE crm_meetings ADD COLUMN end_time TIME NULL AFTER start_time",
            'meetings_status' => "ALTER TABLE crm_meetings ADD COLUMN status VARCHAR(50) DEFAULT 'Scheduled' AFTER outcome",
            'meetings_outcome' => "ALTER TABLE crm_meetings ADD COLUMN outcome TEXT NULL AFTER meeting_date",
            'meetings_follow_up_date' => "ALTER TABLE crm_meetings ADD COLUMN follow_up_date DATE NULL AFTER outcome",
            'meetings_follow_up_type' => "ALTER TABLE crm_meetings ADD COLUMN follow_up_type VARCHAR(50) NULL AFTER follow_up_date",
            'meetings_assigned_to' => "ALTER TABLE crm_meetings ADD COLUMN assigned_to INT NULL AFTER follow_up_type",
            'meetings_latitude' => "ALTER TABLE crm_meetings ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER location",
            'meetings_longitude' => "ALTER TABLE crm_meetings ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude",
            'meetings_updated_at' => "ALTER TABLE crm_meetings ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            // CRM Visits columns
            'visits_lead_id' => "ALTER TABLE crm_visits ADD COLUMN lead_id INT NULL AFTER purpose",
            'visits_purpose' => "ALTER TABLE crm_visits ADD COLUMN purpose TEXT NULL AFTER title",
            'visits_description' => "ALTER TABLE crm_visits ADD COLUMN description TEXT NULL AFTER purpose",
            'visits_notes' => "ALTER TABLE crm_visits ADD COLUMN notes TEXT NULL AFTER description",
            'visits_status' => "ALTER TABLE crm_visits ADD COLUMN status VARCHAR(50) DEFAULT 'Planned' AFTER location",
            'visits_outcome' => "ALTER TABLE crm_visits ADD COLUMN outcome TEXT NULL AFTER visit_date",
            'visits_follow_up_date' => "ALTER TABLE crm_visits ADD COLUMN follow_up_date DATE NULL AFTER outcome",
            'visits_follow_up_type' => "ALTER TABLE crm_visits ADD COLUMN follow_up_type VARCHAR(50) NULL AFTER follow_up_date",
            'visits_assigned_to' => "ALTER TABLE crm_visits ADD COLUMN assigned_to INT NULL AFTER follow_up_type",
            'visits_latitude' => "ALTER TABLE crm_visits ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER location",
            'visits_longitude' => "ALTER TABLE crm_visits ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude",
            'visits_visit_proof_image' => "ALTER TABLE crm_visits ADD COLUMN visit_proof_image TEXT NULL AFTER longitude",
            'visits_updated_at' => "ALTER TABLE crm_visits ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ];

        foreach ($columnStatements as $column => $statement) {
            // If this entry is for crm_calls (key prefix 'calls_'), check crm_calls first
            if (strpos($column, 'calls_') === 0) {
                $colName = str_replace('calls_', '', $column);
                if (!crm_setup_column_exists($conn, 'crm_calls', $colName)) {
                    if (!mysqli_query($conn, $statement)) {
                        $ok = false;
                        $error = mysqli_error($conn);
                        break;
                    }
                }
            } elseif (strpos($column, 'meetings_') === 0) {
                // CRM Meetings columns
                $colName = str_replace('meetings_', '', $column);
                if (!crm_setup_column_exists($conn, 'crm_meetings', $colName)) {
                    if (!mysqli_query($conn, $statement)) {
                        $ok = false;
                        $error = mysqli_error($conn);
                        break;
                    }
                }
            } elseif (strpos($column, 'visits_') === 0) {
                // CRM Visits columns
                $colName = str_replace('visits_', '', $column);
                if (!crm_setup_column_exists($conn, 'crm_visits', $colName)) {
                    if (!mysqli_query($conn, $statement)) {
                        $ok = false;
                        $error = mysqli_error($conn);
                        break;
                    }
                }
            } elseif (strpos($column, 'tasks_') === 0) {
                // CRM Tasks columns
                $colName = str_replace('tasks_', '', $column);
                if (!crm_setup_column_exists($conn, 'crm_tasks', $colName)) {
                    if (!mysqli_query($conn, $statement)) {
                        $ok = false;
                        $error = mysqli_error($conn);
                        break;
                    }
                }
            } else {
                // Regular crm_leads columns
                if (!crm_setup_column_exists($conn, 'crm_leads', $column)) {
                    if (!mysqli_query($conn, $statement)) {
                        $ok = false;
                        $error = mysqli_error($conn);
                        break;
                    }
                }
            }
        }
    }

    if ($ok) {
        $indexStatements = [
            'uniq_leads_phone' => "ALTER TABLE crm_leads ADD UNIQUE KEY uniq_leads_phone (phone)",
            'uniq_leads_email' => "ALTER TABLE crm_leads ADD UNIQUE KEY uniq_leads_email (email)",
            'idx_follow_up_date' => "ALTER TABLE crm_leads ADD INDEX idx_follow_up_date (follow_up_date)",
            // CRM Tasks indexes
            'idx_tasks_lead_id' => "ALTER TABLE crm_tasks ADD INDEX idx_lead_id (lead_id)",
            'idx_tasks_assigned_to' => "ALTER TABLE crm_tasks ADD INDEX idx_assigned_to (assigned_to)",
            'idx_tasks_follow_up_date' => "ALTER TABLE crm_tasks ADD INDEX idx_follow_up_date (follow_up_date)",
            'idx_tasks_closed_by' => "ALTER TABLE crm_tasks ADD INDEX idx_closed_by (closed_by)",
            'idx_calls_lead_id' => "ALTER TABLE crm_calls ADD INDEX idx_lead_id (lead_id)",
            'idx_calls_assigned_to' => "ALTER TABLE crm_calls ADD INDEX idx_assigned_to (assigned_to)",
            'idx_calls_call_type' => "ALTER TABLE crm_calls ADD INDEX idx_call_type (call_type)",
            'idx_calls_outcome' => "ALTER TABLE crm_calls ADD INDEX idx_outcome (outcome)",
            'idx_calls_follow_up_date' => "ALTER TABLE crm_calls ADD INDEX idx_follow_up_date (follow_up_date)",
            'idx_meetings_lead_id' => "ALTER TABLE crm_meetings ADD INDEX idx_lead_id (lead_id)",
            'idx_meetings_assigned_to' => "ALTER TABLE crm_meetings ADD INDEX idx_assigned_to (assigned_to)",
            'idx_meetings_follow_up_date' => "ALTER TABLE crm_meetings ADD INDEX idx_follow_up_date (follow_up_date)",
            'idx_visits_lead_id' => "ALTER TABLE crm_visits ADD INDEX idx_lead_id (lead_id)",
            'idx_visits_assigned_to' => "ALTER TABLE crm_visits ADD INDEX idx_assigned_to (assigned_to)",
            'idx_visits_follow_up_date' => "ALTER TABLE crm_visits ADD INDEX idx_follow_up_date (follow_up_date)"
        ];

        foreach ($indexStatements as $index => $statement) {
            if ($index === 'uniq_leads_phone' && crm_setup_has_duplicates($conn, 'crm_leads', 'phone')) {
                $warnings[] = 'Duplicate phone numbers found; unique phone constraint skipped.';
                continue;
            }
            if ($index === 'uniq_leads_email' && crm_setup_has_duplicates($conn, 'crm_leads', 'email')) {
                $warnings[] = 'Duplicate email addresses found; unique email constraint skipped.';
                continue;
            }

            // Determine table and index name
            $table = 'crm_leads';
            $idxCheckName = $index;
            
            if (strpos($index, 'idx_tasks_') === 0) {
                $table = 'crm_tasks';
                $idxCheckName = str_replace('idx_tasks_', 'idx_', $index);
            } elseif (strpos($index, 'idx_calls_') === 0) {
                $table = 'crm_calls';
                $idxCheckName = str_replace('idx_calls_', 'idx_', $index);
            } elseif (strpos($index, 'idx_meetings_') === 0) {
                $table = 'crm_meetings';
                $idxCheckName = str_replace('idx_meetings_', 'idx_', $index);
            } elseif (strpos($index, 'idx_visits_') === 0) {
                $table = 'crm_visits';
                $idxCheckName = str_replace('idx_visits_', 'idx_', $index);
            }

            if (!crm_setup_index_exists($conn, $table, $idxCheckName)) {
                if (!mysqli_query($conn, $statement)) {
                    $ok = false;
                    $error = mysqli_error($conn);
                    break;
                }
            }
        }
    }

    closeConnection($conn);

    if (!$ok) {
        return ['success' => false, 'message' => 'Failed to create CRM tables' . (isset($error) && $error ? (': ' . $error) : '.')];
    }

    if (!crm_setup_ensure_upload_dir()) {
        return ['success' => true, 'message' => 'Tables created, but uploads/crm_attachments directory could not be created. Please create it manually with write permissions.'];
    }

    $message = 'CRM tables created successfully.';
    if ($warnings) {
        $message .= ' ' . implode(' ', $warnings);
    }

    return ['success' => true, 'message' => $message];
}

$result = null;
$auto_redirect = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = crm_setup_create();
    if ($result['success']) { $auto_redirect = true; }
}

$conn_check = createConnection(true);
$has_tables = $conn_check ? crm_setup_all_exist($conn_check) : false;
if ($conn_check) { closeConnection($conn_check); }
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>📇 CRM Setup</h1>
                    <p>Create database tables and prepare the attachments directory.</p>
                </div>
                <div>
                    <a href="../public/index.php" class="btn btn-accent">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <?php if ($result): ?>
            <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($result['message']); ?>
            </div>
            <?php if ($auto_redirect): ?>
                <script>
                    setTimeout(function(){ window.location.href = '../public/crm/index.php'; }, 2000);
                </script>
                <div class="alert alert-info" style="margin-top:16px;">Redirecting to CRM in 2 seconds...</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$prerequisite_check['allowed']): ?>
            <div class="alert alert-error" style="margin-bottom:24px;">
                <strong>⚠️ Prerequisites Not Met</strong><br>
                <?php echo htmlspecialchars($prerequisite_check['message']); ?>
                <div style="margin-top:12px;">
                    <strong>Required Modules:</strong>
                    <ul style="margin:8px 0 0 20px;">
                        <?php foreach ($prerequisite_check['missing_modules'] as $mod): ?>
                            <li>
                                <?php echo htmlspecialchars($mod['display_name']); ?>
                                <?php if ($mod['setup_path']): ?>
                                    - <a href="<?php echo htmlspecialchars($mod['setup_path']); ?>" style="color:#003581;font-weight:600;">Setup Now →</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:820px;margin:0 auto;">
            <h2 style="margin-top:0;color:#003581;">Setup Checklist</h2>
            <ol style="line-height:1.7;margin-left:18px;">
                <li>Click the setup button to create the CRM tables (<code>crm_tasks</code>, <code>crm_calls</code>, <code>crm_meetings</code>, <code>crm_visits</code>, <code>crm_leads</code>).</li>
                <li>We will create the <code>uploads/crm_attachments</code> directory for file uploads.</li>
                <li>After setup, assign roles (Admin/Manager) to manage CRM entries, and employees can view their tasks.</li>
            </ol>

            <div style="margin:24px 0;">
                <?php if (!$prerequisite_check['allowed']): ?>
                    <div class="alert alert-error">
                        Setup is disabled until all prerequisite modules are installed. Please set up the required modules first.
                    </div>
                <?php elseif ($has_tables): ?>
                    <div class="alert alert-info" style="margin-bottom:16px;">CRM tables already exist. You can start using the module.</div>
                    <a href="../public/crm/index.php" class="btn" style="padding:12px 28px;">📊 Open CRM</a>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" class="btn" style="padding:12px 28px;">🚀 Create CRM Tables</button>
                    </form>
                <?php endif; ?>
            </div>

            <div style="padding:16px;background:#f8f9fa;border-radius:8px;color:#6c757d;">
                The CRM module helps track tasks, calls, meetings, visits and leads with a unified calendar and reports.
            </div>
        </div>
    </div>
    
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
