<?php
/**
 * Office Expense Tracker - Database Setup
 * Creates the office_expenses table used by the Expense Tracker module
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Expense Tracker Module - Database Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function tableExists($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function ensureExpenseUploadDirectory()
{
    $dir = __DIR__ . '/../uploads/office_expenses';
    if (!is_dir($dir)) {
        return mkdir($dir, 0755, true);
    }
    return true;
}

function setupExpenseTracker()
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    if (!tableExists($conn, 'employees')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Employees table not found. Please set up the Employee module first.'];
    }

    if (tableExists($conn, 'office_expenses')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'office_expenses table already exists.'];
    }

    $sql = "CREATE TABLE office_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        category VARCHAR(50) NOT NULL,
        vendor_name VARCHAR(100) DEFAULT NULL,
        description TEXT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_mode VARCHAR(30) NOT NULL,
        receipt_file TEXT DEFAULT NULL,
        added_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_office_expenses_employee FOREIGN KEY (added_by) REFERENCES employees(id) ON DELETE RESTRICT,
        INDEX idx_expense_date (date),
        INDEX idx_expense_category (category),
        INDEX idx_expense_payment (payment_mode),
        INDEX idx_expense_added_by (added_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Internal office expenses';";

    if (!mysqli_query($conn, $sql)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating table: ' . $error];
    }

    closeConnection($conn);

    if (!ensureExpenseUploadDirectory()) {
        return ['success' => true, 'message' => 'Table created successfully, but the receipt upload directory could not be created. Please create uploads/office_expenses manually.'];
    }

    return ['success' => true, 'message' => 'Expense Tracker database objects created successfully!'];
}

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = setupExpenseTracker();
}

$conn = createConnection(true);
$has_expenses = $conn ? tableExists($conn, 'office_expenses') : false;
if ($conn) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>💸 Expense Tracker Setup</h1>
          <p>Create the database table required for managing office expenses.</p>
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
    <?php endif; ?>

    <div class="card" style="max-width:820px;margin:0 auto;">
      <h2 style="margin-top:0;color:#003581;">How to proceed</h2>
      <ol style="line-height:1.7;margin-left:18px;">
        <li>Confirm the Employee module is installed so expense entries can be linked to staff.</li>
        <li>Click the setup button once to create the <code>office_expenses</code> table.</li>
        <li>After success, the receipt upload folder <code>uploads/office_expenses</code> is ready for storing invoices.</li>
      </ol>

      <div style="margin:24px 0;">
        <?php if ($has_expenses): ?>
          <div class="alert alert-info" style="margin-bottom:16px;">
            The office_expenses table already exists. You can start using the module immediately.
          </div>
        <?php else: ?>
          <form method="POST">
            <button type="submit" class="btn" style="padding:12px 28px;">🚀 Create Expense Tracker Table</button>
          </form>
        <?php endif; ?>
      </div>

      <div style="padding:16px;background:#f8f9fa;border-radius:8px;color:#6c757d;">
        Once the table is created, navigate to the Expense Tracker pages to add and review internal expenses.
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
