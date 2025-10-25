<?php
/**
 * Salary Module - Attendance & Employee Snapshot Migration (v2)
 * Adds attendance summary fields and employee snapshot columns to salary_records
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Salary Module - Attendance & Snapshot Migration (v2)';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function col_exists(mysqli $conn, string $table, string $col): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . mysqli_real_escape_string($conn, DB_NAME) . "' AND TABLE_NAME='" . mysqli_real_escape_string($conn, $table) . "' AND COLUMN_NAME='" . mysqli_real_escape_string($conn, $col) . "' LIMIT 1";
    $res = mysqli_query($conn, $sql);
    $ok = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $ok;
}

function run_migration(): array {
    $conn = createConnection(true);
    if (!$conn) return ['success' => false, 'message' => 'Database connection failed.'];

    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'salary_records'");
    if (!$check_table || mysqli_num_rows($check_table) === 0) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'salary_records table not found. Run setup first.'];
    }

    $ddl = [];
    if (!col_exists($conn, 'salary_records', 'snapshot_department')) $ddl[] = "ADD COLUMN snapshot_department VARCHAR(100) DEFAULT NULL AFTER uploaded_by";
    if (!col_exists($conn, 'salary_records', 'snapshot_designation')) $ddl[] = "ADD COLUMN snapshot_designation VARCHAR(100) DEFAULT NULL AFTER snapshot_department";
    if (!col_exists($conn, 'salary_records', 'snapshot_salary_type')) $ddl[] = "ADD COLUMN snapshot_salary_type ENUM('Monthly','Hourly','Daily') DEFAULT NULL AFTER snapshot_designation";
    if (!col_exists($conn, 'salary_records', 'working_days_total')) $ddl[] = "ADD COLUMN working_days_total INT DEFAULT NULL AFTER snapshot_salary_type";
    if (!col_exists($conn, 'salary_records', 'days_worked')) $ddl[] = "ADD COLUMN days_worked DECIMAL(5,2) DEFAULT NULL AFTER working_days_total";
    if (!col_exists($conn, 'salary_records', 'leave_days')) $ddl[] = "ADD COLUMN leave_days DECIMAL(5,2) DEFAULT NULL AFTER days_worked";
    if (!col_exists($conn, 'salary_records', 'leave_breakdown')) $ddl[] = "ADD COLUMN leave_breakdown TEXT DEFAULT NULL AFTER leave_days";
    if (!col_exists($conn, 'salary_records', 'deduction_breakdown')) $ddl[] = "ADD COLUMN deduction_breakdown TEXT DEFAULT NULL AFTER leave_breakdown";
    if (!col_exists($conn, 'salary_records', 'unpaid_leave_days')) $ddl[] = "ADD COLUMN unpaid_leave_days DECIMAL(5,2) DEFAULT NULL AFTER deduction_breakdown";

    $errors = [];
    if (!empty($ddl)) {
        $sql = 'ALTER TABLE salary_records ' . implode(', ', $ddl);
        if (!mysqli_query($conn, $sql)) {
            $errors[] = mysqli_error($conn);
        }
    }

    closeConnection($conn);
    if (empty($errors)) {
        return ['success' => true, 'message' => empty($ddl) ? 'No changes needed; all columns already present.' : 'Migration completed successfully.'];
    }
    return ['success' => false, 'message' => 'Migration errors: ' . implode('; ', $errors)];
}
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h1>📦 Migrate Salary Records (v2)</h1>
        <p>Add attendance and employee snapshot fields to salary_records.</p>
      </div>
      <div>
        <a href="../public/salary/admin.php" class="btn btn-accent">← Back to Salary Manager</a>
      </div>
    </div>

    <?php
    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = run_migration();
    }
    if ($result): ?>
      <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($result['message']); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:800px;margin:0 auto;">
      <p>This migration will add the following columns to <code>salary_records</code> if missing:</p>
      <ul>
        <li>snapshot_department, snapshot_designation, snapshot_salary_type</li>
        <li>working_days_total, days_worked, leave_days, unpaid_leave_days</li>
        <li>leave_breakdown (JSON text), deduction_breakdown (JSON text)</li>
      </ul>
      <form method="POST">
        <button type="submit" class="btn" style="padding:10px 22px;">Run Migration</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
