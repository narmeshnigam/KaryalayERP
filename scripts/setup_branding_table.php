<?php
/**
 * Database Setup Script: Branding Settings Table
 * Creates the branding_settings table for managing organization identity and logos
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Branding - Module Setup';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function branding_setup_table_exists(mysqli $conn): bool {
    $res = @mysqli_query($conn, "SHOW TABLES LIKE 'branding_settings'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $exists;
}

function branding_setup_ensure_upload_dir(): bool {
    $dir = __DIR__ . '/../uploads/branding';
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function branding_setup_create(): array {
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Create branding_settings table (create without FK first, add FK later if referenced table exists)
    $sql = "CREATE TABLE IF NOT EXISTS branding_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        org_name VARCHAR(150) NOT NULL,
        legal_name VARCHAR(150) DEFAULT NULL,
        tagline VARCHAR(150) DEFAULT NULL,
        address_line1 VARCHAR(255) DEFAULT NULL,
        address_line2 VARCHAR(255) DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        state VARCHAR(100) DEFAULT NULL,
        zip VARCHAR(20) DEFAULT NULL,
        country VARCHAR(100) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        website VARCHAR(150) DEFAULT NULL,
        gstin VARCHAR(50) DEFAULT NULL,
        footer_text TEXT DEFAULT NULL,
        login_page_logo TEXT DEFAULT NULL,
        sidebar_header_full_logo TEXT DEFAULT NULL,
        favicon TEXT DEFAULT NULL,
        sidebar_square_logo TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!mysqli_query($conn, $sql)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Failed to create branding_settings table: ' . $error];
    }

    // Check if initial record exists
    $check = mysqli_query($conn, "SELECT id FROM branding_settings LIMIT 1");
    if ($check && mysqli_num_rows($check) === 0) {
        // Insert default skeleton record
        $default = "INSERT INTO branding_settings (org_name, footer_text) 
                    VALUES ('Karyalay ERP', '© " . date('Y') . " Karyalay ERP. All rights reserved.')";
        if (!mysqli_query($conn, $default)) {
            if ($check) mysqli_free_result($check);
            closeConnection($conn);
            return ['success' => false, 'message' => 'Table created but failed to insert default record.'];
        }
    }
    if ($check) mysqli_free_result($check);

    // Try to add foreign key constraint if employees table exists and constraint not present
    $emp_check = mysqli_query($conn, "SHOW TABLES LIKE 'employees'");
    if ($emp_check && mysqli_num_rows($emp_check) > 0) {
        // ensure index exists on created_by
        $idx_check = mysqli_query($conn, "SHOW INDEX FROM branding_settings WHERE Key_name = 'idx_branding_created_by'");
        if (!($idx_check && mysqli_num_rows($idx_check) > 0)) {
            @mysqli_query($conn, "CREATE INDEX idx_branding_created_by ON branding_settings (created_by)");
        }

        // check if fk already exists
        $fk_check = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branding_settings' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_branding_created_by'");
        if (!($fk_check && mysqli_num_rows($fk_check) > 0)) {
            // attempt to add foreign key; don't fail setup if this step errors
            @mysqli_query($conn, "ALTER TABLE branding_settings ADD CONSTRAINT fk_branding_created_by FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL");
        }
        if ($idx_check) mysqli_free_result($idx_check);
        if ($fk_check) mysqli_free_result($fk_check);
    }

    if ($emp_check) mysqli_free_result($emp_check);

    closeConnection($conn);

    if (!branding_setup_ensure_upload_dir()) {
        return ['success' => true, 'message' => 'Table created successfully, but uploads/branding directory could not be created. Please create it manually with write permissions.'];
    }

    return ['success' => true, 'message' => 'Branding module setup completed successfully!'];
}

$result = null;
$auto_redirect = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = branding_setup_create();
    if ($result['success']) { $auto_redirect = true; }
}

$conn_check = createConnection(true);
$has_table = $conn_check ? branding_setup_table_exists($conn_check) : false;
if ($conn_check) { closeConnection($conn_check); }
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🏢 Branding Settings Setup</h1>
                    <p>Configure your organization's identity and visual branding.</p>
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
                    setTimeout(function(){ window.location.href = '../public/branding/index.php'; }, 2000);
                </script>
                <div class="alert alert-info" style="margin-top:16px;">Redirecting to Branding Settings in 2 seconds...</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card" style="max-width:820px;margin:0 auto;">
            <div style="text-align:center;margin-bottom:32px;">
                <div style="font-size:64px;margin-bottom:16px;">�</div>
                <h2 style="margin:0 0 8px 0;color:#003581;">Branding & Organization Settings</h2>
                <p style="color:#6c757d;font-size:15px;">Set up your organization's identity and branding elements</p>
            </div>

            <div style="background:#f8f9fa;border-left:4px solid #003581;padding:20px;margin-bottom:32px;border-radius:6px;">
                <h3 style="margin:0 0 12px 0;font-size:16px;color:#1b2a57;">📋 What this module does:</h3>
                <ul style="margin:0;padding-left:20px;color:#495057;line-height:1.8;">
                    <li>Upload and manage company logos (light, dark, square_light and square_light variants)</li>
                    <li>Configure organization details (name, address, contact info)</li>
                    <li>Set custom tagline and footer text for system-wide use</li>
                    <li>Automatically integrate branding across reports, PDFs, and interface</li>
                </ul>
            </div>

            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin-bottom:32px;border-radius:6px;">
                <h3 style="margin:0 0 12px 0;font-size:16px;color:#856404;">⚠️ Before you proceed:</h3>
                <ul style="margin:0;padding-left:20px;color:#856404;line-height:1.8;">
                    <li>Prepare logo files in PNG, JPG, or SVG format (max 2MB each)</li>
                    <li>Have organization details ready (legal name, address, GSTIN, etc.)</li>
                    <li>Only <strong>Admin</strong> role can configure branding settings</li>
                </ul>
            </div>

            <h3 style="margin:20px 0 16px 0;color:#003581;">Setup Steps</h3>
            <ol style="line-height:1.7;margin-left:18px;color:#495057;">
                <li>Click the setup button to create the <code>branding_settings</code> table.</li>
                <li>We will create the <code>uploads/branding</code> directory for logo storage.</li>
                <li>After setup, configure your organization details and upload logos.</li>
            </ol>

            <div style="margin:32px 0;text-align:center;">
                <?php if ($has_table): ?>
                    <div class="alert alert-info" style="margin-bottom:16px;">Branding module is already set up. You can start configuring.</div>
                    <a href="../public/branding/index.php" class="btn btn-primary" style="padding:12px 32px;font-size:16px;">
                        ⚙️ Configure Branding
                    </a>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" class="btn btn-primary" style="padding:12px 32px;font-size:16px;">
                            🚀 Setup Branding Module
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6c757d;text-align:center;">
                <p style="margin:0;">After setup, access branding settings from <strong>Settings &gt; Branding Settings</strong> in the sidebar</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
