<?php
/**
 * Database Migration: Rename Branding Logo Columns
 * Renames columns for semantic clarity:
 * - logo_light -> login_page_logo
 * - logo_dark -> sidebar_header_full_logo
 * - logo_square_light -> favicon
 * - logo_square_dark -> sidebar_square_logo
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Branding - Column Rename Migration';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function migrate_column_names(): array {
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Check if table exists
    $res = @mysqli_query($conn, "SHOW TABLES LIKE 'branding_settings'");
    if (!$res || mysqli_num_rows($res) === 0) {
        if ($res) mysqli_free_result($res);
        closeConnection($conn);
        return ['success' => false, 'message' => 'branding_settings table does not exist. Run setup first.'];
    }
    if ($res) mysqli_free_result($res);

    // Check if migration already completed (check for new column names)
    $check_cols = mysqli_query($conn, "SHOW COLUMNS FROM branding_settings LIKE 'login_page_logo'");
    if ($check_cols && mysqli_num_rows($check_cols) > 0) {
        mysqli_free_result($check_cols);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Migration already completed. New column names exist.'];
    }
    if ($check_cols) mysqli_free_result($check_cols);

    // Get all existing data before migration
    $backup = mysqli_query($conn, "SELECT * FROM branding_settings");
    if (!$backup) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Failed to backup data: ' . mysqli_error($conn)];
    }
    $backup_data = [];
    while ($row = mysqli_fetch_assoc($backup)) {
        $backup_data[] = $row;
    }
    mysqli_free_result($backup);

    // Perform column renames
    $renames = [
        ['old' => 'logo_light', 'new' => 'login_page_logo'],
        ['old' => 'logo_dark', 'new' => 'sidebar_header_full_logo'],
        ['old' => 'logo_square_light', 'new' => 'favicon'],
        ['old' => 'logo_square_dark', 'new' => 'sidebar_square_logo']
    ];

    foreach ($renames as $rename) {
        $sql = "ALTER TABLE branding_settings CHANGE COLUMN `{$rename['old']}` `{$rename['new']}` TEXT DEFAULT NULL";
        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            closeConnection($conn);
            return ['success' => false, 'message' => "Failed to rename column {$rename['old']} to {$rename['new']}: " . $error];
        }
    }

    closeConnection($conn);
    return ['success' => true, 'message' => 'Migration completed successfully! All columns renamed with semantic names.'];
}

$result = null;
$auto_redirect = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = migrate_column_names();
    if ($result['success']) { $auto_redirect = true; }
}

$conn_check = createConnection(true);
$already_migrated = false;
if ($conn_check) {
    $check_cols = mysqli_query($conn_check, "SHOW COLUMNS FROM branding_settings LIKE 'login_page_logo'");
    $already_migrated = ($check_cols && mysqli_num_rows($check_cols) > 0);
    if ($check_cols) mysqli_free_result($check_cols);
    closeConnection($conn_check);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🔄 Branding Column Rename Migration</h1>
                    <p>Rename logo columns to semantic names for clarity.</p>
                </div>
                <div>
                    <a href="../public/branding/index.php" class="btn btn-accent">← Back to Branding</a>
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
                <div style="font-size:64px;margin-bottom:16px;">🔄</div>
                <h2 style="margin:0 0 8px 0;color:#003581;">Column Rename Migration</h2>
                <p style="color:#6c757d;font-size:15px;">Update branding schema with semantic column names</p>
            </div>

            <div style="background:#f8f9fa;border-left:4px solid #003581;padding:20px;margin-bottom:32px;border-radius:6px;">
                <h3 style="margin:0 0 12px 0;font-size:16px;color:#1b2a57;">📋 Column Name Changes:</h3>
                <ul style="margin:0;padding-left:20px;color:#495057;line-height:1.8;font-family:monospace;font-size:12px;">
                    <li><code>logo_light</code> → <code>login_page_logo</code></li>
                    <li><code>logo_dark</code> → <code>sidebar_header_full_logo</code></li>
                    <li><code>logo_square_light</code> → <code>favicon</code></li>
                    <li><code>logo_square_dark</code> → <code>sidebar_square_logo</code></li>
                </ul>
            </div>

            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin-bottom:32px;border-radius:6px;">
                <h3 style="margin:0 0 12px 0;font-size:16px;color:#856404;">⚠️ Before you proceed:</h3>
                <ul style="margin:0;padding-left:20px;color:#856404;line-height:1.8;">
                    <li>Backup your database before running migrations</li>
                    <li>This migration is one-time (safe to run multiple times)</li>
                    <li>All existing data will be preserved</li>
                    <li>Columns will retain their data but with new names</li>
                </ul>
            </div>

            <div style="margin:32px 0;text-align:center;">
                <?php if ($already_migrated): ?>
                    <div class="alert alert-info" style="margin-bottom:16px;">Migration already completed. New column names exist.</div>
                    <a href="../public/branding/index.php" class="btn btn-primary" style="padding:12px 32px;font-size:16px;">
                        ⚙️ Go to Branding Settings
                    </a>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" class="btn btn-primary" style="padding:12px 32px;font-size:16px;">
                            🚀 Run Migration
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
