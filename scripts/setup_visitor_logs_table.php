<?php
/**
 * Visitor Log Module - Database Setup
 * Creates the visitor_logs table required for the Visitor Log module
 */

require_once __DIR__ . '/../config/db_connect.php';

function tableExists_visitors($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function ensureVisitorUploadDirectory()
{
    $dir = __DIR__ . '/../uploads/visitor_logs';
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true);
    }
    return true;
}

function setupVisitorLogModule()
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    if (!tableExists_visitors($conn, 'employees')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Employees table not found. Please install the Employee module first.'];
    }

    if (tableExists_visitors($conn, 'visitor_logs')) {
        closeConnection($conn);
        return ['success' => true, 'message' => 'visitor_logs table already exists.'];
    }

    $sql = "CREATE TABLE visitor_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_name VARCHAR(100) NOT NULL,
        phone VARCHAR(15) DEFAULT NULL,
        purpose VARCHAR(100) NOT NULL,
        check_in_time DATETIME NOT NULL,
        check_out_time DATETIME DEFAULT NULL,
        employee_id INT NOT NULL,
        photo TEXT DEFAULT NULL,
        added_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        CONSTRAINT fk_visitor_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
        CONSTRAINT fk_visitor_added_by FOREIGN KEY (added_by) REFERENCES employees(id) ON DELETE RESTRICT,
        INDEX idx_visitor_check_in (check_in_time),
        INDEX idx_visitor_employee (employee_id),
        INDEX idx_visitor_name (visitor_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Office visitor check-in records';";

    if (!mysqli_query($conn, $sql)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating table: ' . $error];
    }

    closeConnection($conn);

    if (!ensureVisitorUploadDirectory()) {
        return ['success' => true, 'message' => 'Table created successfully, but the visitor upload directory could not be created. Please create uploads/visitor_logs manually.'];
    }

    return ['success' => true, 'message' => 'Visitor Log table created successfully.'];
}

// Only run HTML output if called directly
if (!defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    require_once __DIR__ . '/../config/config.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }

    $page_title = 'Visitor Log Module - Database Setup';
    require_once __DIR__ . '/../includes/header_sidebar.php';
    require_once __DIR__ . '/../includes/sidebar.php';

    $result = null;
    $auto_redirect = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = setupVisitorLogModule();
        if ($result['success']) {
            $auto_redirect = true;
        }
    }

    $conn = createConnection(true);
    $has_table = $conn ? tableExists_visitors($conn, 'visitor_logs') : false;
    if ($conn) {
        closeConnection($conn);
    }
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>🛂 Visitor Log Setup</h1>
          <p>Create the database objects needed to track office visitors.</p>
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
        <script>setTimeout(function() { window.location.href = '../public/visitors/index.php'; }, 2000);</script>
        <div class="alert alert-info" style="margin-top:16px;">Redirecting to Visitor Log in 2 seconds...</div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="card" style="max-width:820px;margin:0 auto;">
      <h2 style="margin-top:0;color:#003581;">Setup Checklist</h2>
      <ol style="line-height:1.7;margin-left:18px;">
        <li>Confirm the Employee module is installed so visitors can be assigned to an employee.</li>
        <li>Click the setup button once to create the <code>visitor_logs</code> table and indexes.</li>
        <li>We will create the upload folder <code>uploads/visitor_logs</code> to store optional visitor photos.</li>
      </ol>

      <div style="margin:24px 0;">
        <?php if ($has_table): ?>
          <div class="alert alert-info" style="margin-bottom:16px;">The visitor_logs table already exists. You can start using the Visitor Log module.</div>
          <a href="../public/visitors/index.php" class="btn" style="padding:12px 28px;">📋 Go to Visitor Log</a>
        <?php else: ?>
          <form method="POST">
            <button type="submit" class="btn" style="padding:12px 28px;">🚀 Create Visitor Log Table</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php 
    require_once __DIR__ . '/../includes/footer_sidebar.php';
} // End of direct execution block
