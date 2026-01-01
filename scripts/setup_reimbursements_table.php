<?php
/**
 * Reimbursement Module Database Setup
 * Creates reimbursements table and supporting resources
 */

require_once __DIR__ . '/../config/db_connect.php';

function reimbursementsTableExists($conn) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'reimbursements'");
    $exists = ($result && mysqli_num_rows($result) > 0);
    if ($result) {
        mysqli_free_result($result);
    }
    return $exists;
}

function ensureUploadDirectory() {
    $upload_dir = __DIR__ . '/../uploads/reimbursements';
    if (!is_dir($upload_dir)) {
        return @mkdir($upload_dir, 0755, true);
    }
    return true;
}

function setupReimbursementModule() {
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Confirm prerequisite employees table
    $employees = mysqli_query($conn, "SHOW TABLES LIKE 'employees'");
    $has_employees = ($employees && mysqli_num_rows($employees) > 0);
    if ($employees) {
        mysqli_free_result($employees);
    }
    if (!$has_employees) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Employees table not found. Please run the Employee Module setup first.'];
    }

    if (reimbursementsTableExists($conn)) {
        closeConnection($conn);
        return ['success' => true, 'message' => 'Reimbursements table already exists.'];
    }

    $create_sql = "CREATE TABLE reimbursements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        date_submitted DATE NOT NULL,
        expense_date DATE NOT NULL,
        category VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        payment_status ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending',
        paid_date DATE NULL,
        proof_file TEXT DEFAULT NULL,
        admin_remarks TEXT DEFAULT NULL,
        action_date TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_reimbursements_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        INDEX idx_reimbursements_employee (employee_id),
        INDEX idx_reimbursements_status (status),
        INDEX idx_reimbursements_expense_date (expense_date),
        INDEX idx_reimbursements_submitted (date_submitted)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee reimbursement claims';";

    if (!mysqli_query($conn, $create_sql)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating reimbursements table: ' . $error];
    }

    closeConnection($conn);

    if (!ensureUploadDirectory()) {
        return ['success' => true, 'message' => 'Table created successfully, but the proof upload directory could not be created. Please create uploads/reimbursements manually.'];
    }

    return ['success' => true, 'message' => 'Reimbursements table created successfully!'];
}

// Only run HTML output if called directly (not included)
if (!defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    require_once __DIR__ . '/../config/config.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }

    $page_title = "Reimbursement Module - Database Setup";
    require_once __DIR__ . '/../includes/header_sidebar.php';
    require_once __DIR__ . '/../includes/sidebar.php';

    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = setupReimbursementModule();
    }

    $conn = createConnection(true);
    $has_reimbursements = $conn ? reimbursementsTableExists($conn) : false;
    if ($conn) {
        closeConnection($conn);
    }
?>

<div class="main-wrapper">
	<div class="main-content">
		<div class="page-header">
			<div style="display:flex;justify-content:space-between;align-items:center;">
				<div>
					<h1>🧾 Reimbursement Module Setup</h1>
					<p>Prepare the database table required for expense reimbursements.</p>
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
			<h2 style="margin-top:0;color:#003581;">Step-by-step</h2>
			<ol style="line-height:1.7;margin-left:18px;">
				<li>Ensure the Employee module is installed (required for employee references).</li>
				<li>Click the button below to create the <code>reimbursements</code> table.</li>
				<li>After success, the upload directory <code>uploads/reimbursements</code> is prepared automatically.</li>
			</ol>

			<div style="margin:24px 0;">
				<?php if ($has_reimbursements): ?>
					<div class="alert alert-info" style="margin-bottom:16px;">
						The reimbursements table already exists. You can safely continue to the module pages.
					</div>
				<?php else: ?>
					<form method="POST">
						<button type="submit" class="btn" style="padding:12px 28px;">🚀 Create Reimbursements Table</button>
					</form>
				<?php endif; ?>
			</div>

			<div style="padding:16px;background:#f8f9fa;border-radius:8px;color:#6c757d;">
				After setup, employees can submit claims and admins can manage reimbursements from their respective sections.
			</div>
		</div>
	</div>
</div>

<?php 
    require_once __DIR__ . '/../includes/footer_sidebar.php';
} // End of direct execution block
