<?php
/**
 * Invoices Module - Database Setup
 * Creates invoices, invoice_items, and invoice_activity_log tables
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Invoices Module - Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function invoices_table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function invoices_ensure_upload_dir(): bool
{
    $dir = __DIR__ . '/../uploads/invoices';
    if (!is_dir($dir)) {
        return mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function invoices_setup_create(): array
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Check prerequisites
    $required_tables = ['items_master', 'clients', 'users'];
    foreach ($required_tables as $table) {
        if (!invoices_table_exists($conn, $table)) {
            closeConnection($conn);
            return ['success' => false, 'message' => "Required table '$table' not found. Please install Catalog and Clients modules first."];
        }
    }

    if (invoices_table_exists($conn, 'invoices')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Invoices tables already exist.'];
    }

    // Create main invoices table
    $sql_invoices = "CREATE TABLE IF NOT EXISTS invoices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(30) UNIQUE NOT NULL,
        quotation_id INT UNSIGNED DEFAULT NULL,
        client_id INT UNSIGNED NOT NULL,
        project_id INT DEFAULT NULL,
        issue_date DATE NOT NULL,
        due_date DATE DEFAULT NULL,
        payment_terms VARCHAR(50) DEFAULT NULL COMMENT 'NET 7/15/30/Custom',
        currency VARCHAR(10) NOT NULL DEFAULT 'INR',
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        round_off DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        status ENUM('Draft','Issued','Partially Paid','Paid','Overdue','Cancelled') NOT NULL DEFAULT 'Draft',
        notes TEXT DEFAULT NULL,
        terms TEXT DEFAULT NULL,
        attachment TEXT DEFAULT NULL COMMENT 'PO/WO/Contract attachment path',
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_invoice_no (invoice_no),
        INDEX idx_client_id (client_id),
        INDEX idx_project_id (project_id),
        INDEX idx_quotation_id (quotation_id),
        INDEX idx_status (status),
        INDEX idx_issue_date (issue_date),
        INDEX idx_due_date (due_date),
        INDEX idx_created_by (created_by),
        CONSTRAINT fk_invoices_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
        CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoices master table';";

    if (!mysqli_query($conn, $sql_invoices)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating invoices table: ' . $error];
    }

    // Add foreign key for quotation_id if quotations table exists
    if (invoices_table_exists($conn, 'quotations')) {
        $fk_quotation = "ALTER TABLE invoices 
            ADD CONSTRAINT fk_invoices_quotation 
            FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE SET NULL";
        @mysqli_query($conn, $fk_quotation); // Ignore if already exists
    }

    // Create invoice items table
    $sql_invoice_items = "CREATE TABLE IF NOT EXISTS invoice_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT UNSIGNED NOT NULL,
        item_id INT NOT NULL,
        description TEXT DEFAULT NULL COMMENT 'Line description override',
        quantity DECIMAL(10,2) NOT NULL,
        unit VARCHAR(20) DEFAULT NULL COMMENT 'Unit label: pcs, hrs, kg',
        unit_price DECIMAL(12,2) NOT NULL,
        discount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Absolute discount amount',
        discount_type ENUM('Amount','Percent') NOT NULL DEFAULT 'Amount',
        tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        line_total DECIMAL(12,2) NOT NULL COMMENT 'Final line total after discount + tax',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice_id (invoice_id),
        INDEX idx_item_id (item_id),
        CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        CONSTRAINT fk_invoice_items_item FOREIGN KEY (item_id) REFERENCES items_master(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoice line items';";

    if (!mysqli_query($conn, $sql_invoice_items)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating invoice_items table: ' . $error];
    }

    // Create invoice activity log table
    $sql_activity_log = "CREATE TABLE IF NOT EXISTS invoice_activity_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        action ENUM('Create','Update','Issue','Cancel','PaymentLinked','PDF','Send','StockDeducted','StockRestored') NOT NULL,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice_id (invoice_id),
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at),
        CONSTRAINT fk_invoice_log_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        CONSTRAINT fk_invoice_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoice activity tracking';";

    if (!mysqli_query($conn, $sql_activity_log)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating invoice_activity_log table: ' . $error];
    }

    // Ensure upload directory exists
    if (!invoices_ensure_upload_dir()) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Failed to create uploads/invoices directory.'];
    }

    closeConnection($conn);
    return ['success' => true, 'message' => 'Invoices module tables created successfully!'];
}

// Handle setup request
$setup_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_invoices'])) {
    $setup_result = invoices_setup_create();
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
        display: inline-block;
        padding: 14px 32px;
        background: linear-gradient(135deg, #003581 0%, #0056b3 100%);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        border: none;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-setup:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 53, 129, 0.3);
    }

    .alert {
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 24px;
        font-size: 15px;
    }

    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .alert-danger {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .prerequisites {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 20px;
        margin: 24px 0;
    }

    .prerequisites h4 {
        color: #856404;
        margin-bottom: 12px;
        font-size: 16px;
    }

    .prerequisites p {
        color: #856404;
        margin: 0;
        font-size: 14px;
    }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <div class="setup-container">
            <div class="setup-card">
                <div class="setup-icon">🧾</div>
                <h1 class="setup-title">Invoices Module Setup</h1>
                <p class="setup-description">
                    Initialize the Invoices module to create, manage, and track client invoices with itemization, payments, and inventory integration.
                </p>

                <?php if ($setup_result): ?>
                    <?php if ($setup_result['success']): ?>
                        <div class="alert alert-success">
                            <strong>✓ Success!</strong><br>
                            <?php echo htmlspecialchars($setup_result['message']); ?>
                        </div>
                        <a href="../public/invoices/index.php" class="btn-setup">
                            Go to Invoices →
                        </a>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>✗ Error</strong><br>
                            <?php echo htmlspecialchars($setup_result['message']); ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="prerequisites">
                        <h4>📋 Prerequisites</h4>
                        <p>
                            Before proceeding, ensure that the <strong>Catalog (Items Master)</strong> and <strong>Clients</strong> modules are installed.
                        </p>
                    </div>

                    <div class="features-list">
                        <h3>What will be set up:</h3>
                        <ul>
                            <li><strong>invoices</strong> table - Master invoice records with numbering</li>
                            <li><strong>invoice_items</strong> table - Line items with pricing & tax</li>
                            <li><strong>invoice_activity_log</strong> table - Activity tracking & audit trail</li>
                            <li>Upload directory for attachments (PO/WO/Contract)</li>
                            <li>Integration with Clients, Items, Quotations & Projects</li>
                            <li>Payment tracking and status workflow</li>
                            <li>Inventory deduction on invoice issue</li>
                        </ul>
                    </div>

                    <form method="POST" style="margin-top: 32px;">
                        <button type="submit" name="setup_invoices" class="btn-setup">
                            🚀 Install Invoices Module
                        </button>
                    </form>

                    <p style="margin-top: 24px; color: #6c757d; font-size: 14px;">
                        <strong>Note:</strong> This will create new database tables. Existing data will not be affected.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
