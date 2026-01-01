<?php
/**
 * Quotations Module - Database Setup
 * Creates quotations, quotation_items, and quotation_activity_log tables
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Quotations Module - Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function quotations_table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function quotations_ensure_upload_dir(): bool
{
    $dir = __DIR__ . '/../uploads/quotations';
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function quotations_setup_create(): array
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Check prerequisites
    $required_tables = ['items_master', 'clients'];
    foreach ($required_tables as $table) {
        if (!quotations_table_exists($conn, $table)) {
            closeConnection($conn);
            return ['success' => false, 'message' => "Required table '$table' not found. Please install Catalog and Clients modules first."];
        }
    }

    if (quotations_table_exists($conn, 'quotations')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Quotations tables already exist.'];
    }

    // Create main quotations table
    $sql_quotations = "CREATE TABLE IF NOT EXISTS quotations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quotation_no VARCHAR(30) UNIQUE NOT NULL,
        client_id INT UNSIGNED DEFAULT NULL,
        lead_id INT DEFAULT NULL,
        project_id INT DEFAULT NULL,
        title VARCHAR(200) NOT NULL,
        quotation_date DATE NOT NULL,
        validity_date DATE DEFAULT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'INR',
        status ENUM('Draft','Sent','Accepted','Rejected','Expired') NOT NULL DEFAULT 'Draft',
        notes TEXT DEFAULT NULL,
        terms TEXT DEFAULT NULL,
        brochure_pdf TEXT DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_quotation_no (quotation_no),
        INDEX idx_client_id (client_id),
        INDEX idx_lead_id (lead_id),
        INDEX idx_project_id (project_id),
        INDEX idx_status (status),
        INDEX idx_quotation_date (quotation_date),
        INDEX idx_validity_date (validity_date),
        CONSTRAINT fk_quotations_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
        CONSTRAINT fk_quotations_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quotations master table';";

    if (!mysqli_query($conn, $sql_quotations)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating quotations table: ' . $error];
    }

    // Create quotation items table
    $sql_items = "CREATE TABLE IF NOT EXISTS quotation_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quotation_id INT UNSIGNED NOT NULL,
        item_id INT NOT NULL,
        description TEXT DEFAULT NULL,
        quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_quotation_id (quotation_id),
        INDEX idx_item_id (item_id),
        CONSTRAINT fk_quotation_items_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
        CONSTRAINT fk_quotation_items_item FOREIGN KEY (item_id) REFERENCES items_master(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quotation line items';";

    if (!mysqli_query($conn, $sql_items)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating quotation_items table: ' . $error];
    }

    // Create activity log table
    $sql_log = "CREATE TABLE IF NOT EXISTS quotation_activity_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quotation_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        action ENUM('Create','Update','StatusChange','ConvertToInvoice','Send','Delete') NOT NULL,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_quotation_id (quotation_id),
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        CONSTRAINT fk_quotation_log_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
        CONSTRAINT fk_quotation_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quotation activity tracking';";

    if (!mysqli_query($conn, $sql_log)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating quotation_activity_log table: ' . $error];
    }

    closeConnection($conn);

    if (!quotations_ensure_upload_dir()) {
        return ['success' => true, 'message' => 'Tables created, but uploads/quotations directory is missing. Create it manually with write permissions.'];
    }

    return ['success' => true, 'message' => 'Quotations module tables created successfully!'];
}

$result = null;
$auto_redirect = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = quotations_setup_create();
    if ($result['success']) {
        $auto_redirect = true;
    }
}

$conn_check = createConnection(true);
$has_table = $conn_check ? quotations_table_exists($conn_check, 'quotations') : false;
if ($conn_check) {
    closeConnection($conn_check);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🧾 Quotations Module Setup</h1>
                    <p>Provision the database tables needed for quotation management.</p>
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
                        window.location.href = '../public/quotations/index.php';
                    }, 2000);
                </script>
                <div class="alert alert-info" style="margin-top:16px;">
                    Redirecting to Quotations module in 2 seconds...
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card" style="max-width:820px;margin:0 auto;">
            <h2 style="margin-top:0;color:#003581;">Setup Checklist</h2>
            <ol style="line-height:1.7;margin-left:18px;">
                <li>Ensure the <strong>Catalog (Items Master)</strong> and <strong>Clients</strong> modules are installed.</li>
                <li>Click the setup button once to create the following tables:
                    <ul style="margin-top:8px;">
                        <li><code>quotations</code> - Main quotation records</li>
                        <li><code>quotation_items</code> - Line items for each quotation</li>
                        <li><code>quotation_activity_log</code> - Activity tracking and audit trail</li>
                    </ul>
                </li>
                <li>We will create the <code>uploads/quotations</code> directory for brochure and document storage.</li>
            </ol>

            <div style="margin:24px 0;">
                <?php if ($has_table): ?>
                    <div class="alert alert-info" style="margin-bottom:16px;">
                        The quotations tables already exist. You can start using the module.
                    </div>
                    <a href="../public/quotations/index.php" class="btn" style="padding:12px 28px;">📋 Open Quotations Module</a>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" class="btn" style="padding:12px 28px;">🚀 Create Quotations Tables</button>
                    </form>
                <?php endif; ?>
            </div>

            <div style="padding:16px;background:#f8f9fa;border-radius:8px;color:#6c757d;">
                <strong>What you can do after setup:</strong>
                <ul style="margin:8px 0 0 20px;line-height:1.6;">
                    <li>Create professional quotations for clients and leads</li>
                    <li>Manage quotation lifecycle (Draft → Sent → Accepted/Rejected)</li>
                    <li>Convert accepted quotations into invoices</li>
                    <li>Export quotations as branded PDFs</li>
                    <li>Track all quotation activities and changes</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
