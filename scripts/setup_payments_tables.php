<?php
/**
 * Payments Module - Database Setup
 * Creates payments, payment_invoice_map, and payment_activity_log tables
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Payments Module - Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function payments_table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function payments_ensure_upload_dir(): bool
{
    $dir = __DIR__ . '/../uploads/payments';
    if (!is_dir($dir)) {
        return mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function payments_setup_create(): array
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Check prerequisites
    $required_tables = ['invoices', 'clients', 'users'];
    foreach ($required_tables as $table) {
        if (!payments_table_exists($conn, $table)) {
            closeConnection($conn);
            return ['success' => false, 'message' => "Required table '$table' not found. Please install Invoices and Clients modules first."];
        }
    }

    if (payments_table_exists($conn, 'payments')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Payments tables already exist.'];
    }

    // Create main payments table
    $sql_payments = "CREATE TABLE IF NOT EXISTS payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_no VARCHAR(30) UNIQUE NOT NULL,
        client_id INT UNSIGNED NOT NULL,
        project_id INT DEFAULT NULL,
        payment_date DATE NOT NULL,
        payment_mode ENUM('Cash','Bank Transfer','UPI','Cheque','Other') NOT NULL DEFAULT 'Cash',
        reference_no VARCHAR(100) DEFAULT NULL COMMENT 'UTR/Cheque No./Ref ID',
        amount_received DECIMAL(12,2) NOT NULL,
        remarks TEXT DEFAULT NULL,
        attachment TEXT DEFAULT NULL COMMENT 'Payment proof document path',
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_payment_no (payment_no),
        INDEX idx_client_id (client_id),
        INDEX idx_project_id (project_id),
        INDEX idx_payment_date (payment_date),
        INDEX idx_payment_mode (payment_mode),
        INDEX idx_created_by (created_by),
        CONSTRAINT fk_payments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
        CONSTRAINT fk_payments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment receipts and collections';";

    if (!mysqli_query($conn, $sql_payments)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating payments table: ' . $error];
    }

    // Create payment-invoice mapping table
    $sql_payment_invoice_map = "CREATE TABLE IF NOT EXISTS payment_invoice_map (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_id INT UNSIGNED NOT NULL,
        invoice_id INT UNSIGNED NOT NULL,
        allocated_amount DECIMAL(12,2) NOT NULL COMMENT 'Amount applied to this invoice',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payment_id (payment_id),
        INDEX idx_invoice_id (invoice_id),
        CONSTRAINT fk_payment_map_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
        CONSTRAINT fk_payment_map_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        UNIQUE KEY unique_payment_invoice (payment_id, invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment to invoice allocation mapping';";

    if (!mysqli_query($conn, $sql_payment_invoice_map)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating payment_invoice_map table: ' . $error];
    }

    // Create payment activity log table
    $sql_activity_log = "CREATE TABLE IF NOT EXISTS payment_activity_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        action ENUM('Create','Update','Delete','AttachInvoice','DetachInvoice') NOT NULL,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payment_id (payment_id),
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at),
        CONSTRAINT fk_payment_log_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
        CONSTRAINT fk_payment_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment activity tracking';";

    if (!mysqli_query($conn, $sql_activity_log)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating payment_activity_log table: ' . $error];
    }

    // Ensure upload directory exists
    if (!payments_ensure_upload_dir()) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Failed to create uploads/payments directory.'];
    }

    closeConnection($conn);
    return ['success' => true, 'message' => 'Payments module tables created successfully!'];
}

// Handle setup request
$setup_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_payments'])) {
    $setup_result = payments_setup_create();
}
?>

<style>
    .setup-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .setup-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 40px;
        text-align: center;
    }

    .setup-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }

    .setup-title {
        font-size: 28px;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 16px;
    }

    .setup-description {
        font-size: 16px;
        color: #6c757d;
        line-height: 1.6;
        margin-bottom: 32px;
    }

    .features-list {
        text-align: left;
        margin: 32px 0;
        padding: 24px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .features-list h3 {
        font-size: 18px;
        font-weight: 600;
        color: #003581;
        margin-bottom: 16px;
    }

    .features-list ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .features-list li {
        padding: 8px 0;
        color: #495057;
        position: relative;
        padding-left: 28px;
    }

    .features-list li:before {
        content: "✓";
        position: absolute;
        left: 0;
        color: #28a745;
        font-weight: bold;
        font-size: 18px;
    }

    .btn-setup {
        background: #003581;
        color: white;
        padding: 14px 32px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-setup:hover {
        background: #002560;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 53, 129, 0.3);
    }

    .alert {
        padding: 16px 20px;
        border-radius: 6px;
        margin-bottom: 24px;
        font-size: 15px;
    }

    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .alert-error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .alert-warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <div class="setup-container">
            <div class="setup-card">
                <div class="setup-icon">💰</div>
                <h1 class="setup-title">Payments Module Setup</h1>
                <p class="setup-description">
                    The Payments Module helps you manage receipts and collections against invoices, track payment methods, and maintain client-level financial records.
                </p>

                <?php if ($setup_result): ?>
                    <?php if ($setup_result['success']): ?>
                        <div class="alert alert-success">
                            <strong>✅ Success!</strong><br>
                            <?php echo htmlspecialchars($setup_result['message']); ?>
                            <br><br>
                            <a href="../public/payments/" class="btn-setup" style="display: inline-block; text-decoration: none;">
                                Go to Payments Module →
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-error">
                            <strong>❌ Error:</strong><br>
                            <?php echo htmlspecialchars($setup_result['message']); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="features-list">
                    <h3>✨ What You'll Get:</h3>
                    <ul>
                        <li>Record full, partial, and advance payments</li>
                        <li>Multi-invoice allocation from single payment</li>
                        <li>Auto-update invoice balances and status</li>
                        <li>Support multiple payment modes (Cash, UPI, Bank, Cheque)</li>
                        <li>Track payment references and attach proofs</li>
                        <li>Client-wise payment history and ledgers</li>
                        <li>Outstanding receivables and cashflow reports</li>
                        <li>Activity logs for payment tracking</li>
                    </ul>
                </div>

                <div class="alert alert-warning" style="text-align: left;">
                    <strong>📋 Prerequisites:</strong><br>
                    • Invoices Module must be installed<br>
                    • Clients Module must be installed<br>
                    • Users Module must be active
                </div>

                <?php if (!$setup_result || !$setup_result['success']): ?>
                    <form method="POST" style="margin-top: 32px;">
                        <button type="submit" name="setup_payments" class="btn-setup">
                            🚀 Install Payments Module
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
