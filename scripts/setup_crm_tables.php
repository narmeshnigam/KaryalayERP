<?php
/**
 * CRM Module - Database Setup
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'CRM - Module Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

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
        assigned_to INT NOT NULL,
        status ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
        due_date DATE NULL,
        created_by INT NOT NULL,
        location TEXT NULL,
        attachment TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_due_date (due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_calls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        summary TEXT NULL,
        employee_id INT NOT NULL,
        call_date DATETIME NOT NULL,
        location TEXT NULL,
        attachment TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_employee_id (employee_id),
        INDEX idx_call_date (call_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        agenda TEXT NULL,
        employee_id INT NOT NULL,
        meeting_date DATETIME NOT NULL,
        location TEXT NULL,
        attachment TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_employee_id (employee_id),
        INDEX idx_meeting_date (meeting_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        notes TEXT NULL,
        employee_id INT NOT NULL,
        visit_date DATETIME NOT NULL,
        location TEXT NULL,
        attachment TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_employee_id (employee_id),
        INDEX idx_visit_date (visit_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $sql[] = "CREATE TABLE IF NOT EXISTS crm_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NULL,
        email VARCHAR(100) NULL,
        source VARCHAR(50) NULL,
        status ENUM('New','Contacted','Converted','Dropped') NOT NULL DEFAULT 'New',
        assigned_to INT NULL,
        notes TEXT NULL,
        attachment TEXT NULL,
        location TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $ok = true;
    foreach ($sql as $q) {
        if (!mysqli_query($conn, $q)) {
            $ok = false;
            $error = mysqli_error($conn);
            break;
        }
    }
    closeConnection($conn);

    if (!$ok) {
        return ['success' => false, 'message' => 'Failed to create CRM tables' . (isset($error) && $error ? (': ' . $error) : '.')];
    }

    if (!crm_setup_ensure_upload_dir()) {
        return ['success' => true, 'message' => 'Tables created, but uploads/crm_attachments directory could not be created. Please create it manually with write permissions.'];
    }

    return ['success' => true, 'message' => 'CRM tables created successfully.'];
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

        <div class="card" style="max-width:820px;margin:0 auto;">
            <h2 style="margin-top:0;color:#003581;">Setup Checklist</h2>
            <ol style="line-height:1.7;margin-left:18px;">
                <li>Click the setup button to create the CRM tables (<code>crm_tasks</code>, <code>crm_calls</code>, <code>crm_meetings</code>, <code>crm_visits</code>, <code>crm_leads</code>).</li>
                <li>We will create the <code>uploads/crm_attachments</code> directory for file uploads.</li>
                <li>After setup, assign roles (Admin/Manager) to manage CRM entries, and employees can view their tasks.</li>
            </ol>

            <div style="margin:24px 0;">
                <?php if ($has_tables): ?>
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
