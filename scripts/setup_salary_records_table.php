<?php
/**
 * Salary Viewer Module - Database Setup
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Salary Viewer - Module Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function salary_setup_table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function salary_setup_ensure_upload_dir(): bool
{
    $dir = __DIR__ . '/../uploads/salary_slips';
    if (!is_dir($dir)) {
        return mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function salary_setup_create(): array
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    if (!salary_setup_table_exists($conn, 'employees')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Employees table not found. Please install the Employee module first.'];
    }

    if (salary_setup_table_exists($conn, 'salary_records')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'salary_records table already exists.'];
    }

    $sql = "CREATE TABLE salary_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month CHAR(7) NOT NULL,
        base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        allowances DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        net_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        slip_path TEXT DEFAULT NULL,
        is_locked TINYINT(1) NOT NULL DEFAULT 0,
        uploaded_by INT DEFAULT NULL,
        -- Employee snapshot for the month (denormalized for history)
        snapshot_department VARCHAR(100) DEFAULT NULL,
        snapshot_designation VARCHAR(100) DEFAULT NULL,
        snapshot_salary_type ENUM('Monthly','Hourly','Daily') DEFAULT NULL,
        -- Attendance summary for the month
        working_days_total INT DEFAULT NULL,
        days_worked DECIMAL(5,2) DEFAULT NULL,
        leave_days DECIMAL(5,2) DEFAULT NULL,
        leave_breakdown TEXT DEFAULT NULL,
        deduction_breakdown TEXT DEFAULT NULL,
        unpaid_leave_days DECIMAL(5,2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_employee_month (employee_id, month),
        KEY idx_salary_month (month),
        KEY idx_salary_locked (is_locked),
        CONSTRAINT fk_salary_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_salary_uploaded FOREIGN KEY (uploaded_by) REFERENCES employees(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Salary Viewer module records';";

    if (!mysqli_query($conn, $sql)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating table: ' . $error];
    }

    closeConnection($conn);

    if (!salary_setup_ensure_upload_dir()) {
        return ['success' => true, 'message' => 'Table created, but uploads/salary_slips directory is missing. Create it manually with write permissions.'];
    }

    return ['success' => true, 'message' => 'Salary Viewer table created successfully.'];
}

$result = null;
$auto_redirect = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = salary_setup_create();
    if ($result['success']) {
        $auto_redirect = true;
    }
}

$conn_check = createConnection(true);
$has_table = $conn_check ? salary_setup_table_exists($conn_check, 'salary_records') : false;
if ($conn_check) {
    closeConnection($conn_check);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>💰 Salary Viewer Setup</h1>
                    <p>Provision the database table and storage needed for salary slips.</p>
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
                    setTimeout(function() {
                        window.location.href = '../public/salary/admin.php';
                    }, 2000);
                </script>
                <div class="alert alert-info" style="margin-top:16px;">
                    Redirecting to Salary Manager in 2 seconds...
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card" style="max-width:820px;margin:0 auto;">
            <h2 style="margin-top:0;color:#003581;">Setup Checklist</h2>
            <ol style="line-height:1.7;margin-left:18px;">
                <li>Ensure the Employee module is installed so salaries map to employee records.</li>
                <li>Click the setup button once to create the <code>salary_records</code> table with required indexes.</li>
                <li>We will create the <code>uploads/salary_slips</code> directory for secure slip storage.</li>
            </ol>

            <div style="margin:24px 0;">
                <?php if ($has_table): ?>
                    <div class="alert alert-info" style="margin-bottom:16px;">
                        The salary_records table already exists. You can start using the module.
                    </div>
                    <a href="../public/salary/admin.php" class="btn" style="padding:12px 28px;">📊 Open Salary Manager</a>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" class="btn" style="padding:12px 28px;">🚀 Create Salary Records Table</button>
                    </form>
                <?php endif; ?>
            </div>

            <div style="padding:16px;background:#f8f9fa;border-radius:8px;color:#6c757d;">
                After setup, admins and accountants can upload monthly salary slips and employees can view their net pay history.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
