<?php
/**
 * Database Migration: Split Square Logo into Light/Dark Variants
 * Adds logo_square_light and logo_square_dark columns, migrates existing logo_square data
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Branding - Database Migration';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function migrate_square_logos(): array {
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

    // Check if new columns already exist
    $check_cols = mysqli_query($conn, "SHOW COLUMNS FROM branding_settings LIKE 'logo_square_light'");
    if ($check_cols && mysqli_num_rows($check_cols) > 0) {
        mysqli_free_result($check_cols);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Migration already completed. New columns exist.'];
    }
    if ($check_cols) mysqli_free_result($check_cols);

    // Get existing logo_square value before migration
    $existing = mysqli_query($conn, "SELECT logo_square FROM branding_settings LIMIT 1");
    $old_square = null;
    if ($existing && mysqli_num_rows($existing) > 0) {
        $row = mysqli_fetch_assoc($existing);
        $old_square = $row['logo_square'];
    }
    if ($existing) mysqli_free_result($existing);

    // Add new columns
    $sql1 = "ALTER TABLE branding_settings 
             ADD COLUMN logo_square_light TEXT DEFAULT NULL AFTER logo_square,
             ADD COLUMN logo_square_dark TEXT DEFAULT NULL AFTER logo_square_light";
    
    if (!mysqli_query($conn, $sql1)) {
        $error = mysqli_error($conn);
        closeConnection($conn);
        return ['success' => false, 'message' => 'Failed to add new columns: ' . $error];
    }

    // Migrate existing logo_square to logo_square_light (default assumption)
    if ($old_square) {
        $update = "UPDATE branding_settings SET logo_square_light = ? WHERE id = 1";
        $stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt, 's', $old_square);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Optionally drop old logo_square column (commented out for safety - you can manually drop later)
    // mysqli_query($conn, "ALTER TABLE branding_settings DROP COLUMN logo_square");

    closeConnection($conn);
    return ['success' => true, 'message' => 'Migration completed successfully! Added logo_square_light and logo_square_dark columns.'];
}

$result = null;
$auto_redirect = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = migrate_square_logos();
    if ($result['success']) { $auto_redirect = true; }
}

$conn_check = createConnection(true);
$already_migrated = false;
if ($conn_check) {
    $check_cols = mysqli_query($conn_check, "SHOW COLUMNS FROM branding_settings LIKE 'logo_square_light'");
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
                    <h1>🔄 Branding Database Migration</h1>
                    <p>Split square logo into light and dark background variants.</p>
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
                <h2 style="margin:0 0 8px 0;color:#003581;">Database Migration</h2>
                <p style="color:#6c757d;font-size:15px;">Update branding schema to support separate square logos</p>
            </div>

            <div style="background:#f8f9fa;border-left:4px solid #003581;padding:20px;margin-bottom:32px;border-radius:6px;">
                <h3 style="margin:0 0 12px 0;font-size:16px;color:#1b2a57;">📋 What this migration does:</h3>
                <ul style="margin:0;padding-left:20px;color:#495057;line-height:1.8;">
                    <li>Adds <code>logo_square_light</code> column (for light backgrounds)</li>
                    <li>Adds <code>logo_square_dark</code> column (for dark backgrounds)</li>
                    <li>Migrates existing <code>logo_square</code> data to <code>logo_square_light</code></li>
                    <li>Preserves old <code>logo_square</code> column for backward compatibility</li>
                </ul>
            </div>

            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin-bottom:32px;border-radius:6px;">
                <h3 style="margin:0 0 12px 0;font-size:16px;color:#856404;">⚠️ Before you proceed:</h3>
                <ul style="margin:0;padding-left:20px;color:#856404;line-height:1.8;">
                    <li>Backup your database before running migrations</li>
                    <li>This is a one-time operation (safe to run multiple times)</li>
                    <li>You can manually drop <code>logo_square</code> column later if needed</li>
                </ul>
            </div>

            <div style="margin:32px 0;text-align:center;">
                <?php if ($already_migrated): ?>
                    <div class="alert alert-info" style="margin-bottom:16px;">Migration already completed. New columns exist.</div>
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
