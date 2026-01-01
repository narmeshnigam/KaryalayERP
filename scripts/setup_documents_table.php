<?php
/**
 * Document Vault Module - Database Setup
 */

require_once __DIR__ . '/../config/db_connect.php';

function table_exists_documents($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function ensure_document_upload_dir()
{
    $dir = __DIR__ . '/../uploads/documents';
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true);
    }
    return true;
}

function setup_document_vault()
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    if (!table_exists_documents($conn, 'employees')) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Employees table not found. Install the Employee module first.'];
    }

    if (table_exists_documents($conn, 'documents')) {
        closeConnection($conn);
        return ['success' => true, 'message' => 'documents table already exists.'];
    }

    $sql = "CREATE TABLE documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        file_path TEXT NOT NULL,
        doc_type VARCHAR(50) DEFAULT NULL,
        employee_id INT DEFAULT NULL,
        project_id INT DEFAULT NULL,
        tags TEXT DEFAULT NULL,
        uploaded_by INT NOT NULL,
        visibility ENUM('admin','manager','employee') NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        CONSTRAINT fk_documents_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
        CONSTRAINT fk_documents_uploaded FOREIGN KEY (uploaded_by) REFERENCES employees(id) ON DELETE RESTRICT,
        INDEX idx_documents_type (doc_type),
        INDEX idx_documents_employee (employee_id),
        INDEX idx_documents_uploaded (uploaded_by),
        INDEX idx_documents_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secure document storage vault';";

    if (!mysqli_query($conn, $sql)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Error creating table: ' . $error];
    }

    closeConnection($conn);

    if (!ensure_document_upload_dir()) {
        return ['success' => true, 'message' => 'Table created, but uploads/documents directory could not be created. Please create it manually.'];
    }

    return ['success' => true, 'message' => 'Document Vault table created successfully.'];
}

// Only run HTML output if called directly
if (!defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    require_once __DIR__ . '/../config/config.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }

    $page_title = 'Document Vault Module - Database Setup';
    require_once __DIR__ . '/../includes/header_sidebar.php';
    require_once __DIR__ . '/../includes/sidebar.php';

    $result = null;
    $auto_redirect = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = setup_document_vault();
        if ($result['success']) {
            $auto_redirect = true;
        }
    }

    $conn = createConnection(true);
    $has_table = $conn ? table_exists_documents($conn, 'documents') : false;
    if ($conn) {
        closeConnection($conn);
    }
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h1>📁 Document Vault Setup</h1>
                    <p>Create the database objects required for secure document storage.</p>
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
                <script>setTimeout(function() { window.location.href = '../public/documents/index.php'; }, 2000);</script>
                <div class="alert alert-info" style="margin-top:16px;">Redirecting to Document Vault in 2 seconds...</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card" style="max-width:820px;margin:0 auto;">
            <h2 style="margin-top:0;color:#003581;">Setup Checklist</h2>
            <ol style="line-height:1.7;margin-left:18px;">
                <li>Ensure the Employee module is installed so documents can be tagged to employees.</li>
                <li>Click the setup button once to create the <code>documents</code> table with indexes.</li>
                <li>We will create the <code>uploads/documents</code> folder to store uploaded files securely.</li>
            </ol>

            <div style="margin:24px 0;">
                <?php if ($has_table): ?>
                    <div class="alert alert-info" style="margin-bottom:16px;">The documents table already exists. You can start using the Document Vault.</div>
                    <a href="../public/documents/index.php" class="btn" style="padding:12px 28px;">📂 Go to Document Vault</a>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" class="btn" style="padding:12px 28px;">🚀 Create Document Vault Table</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
    require_once __DIR__ . '/../includes/footer_sidebar.php';
} // End of direct execution block
