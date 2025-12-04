<?php
/**
 * Unauthorized Access Page
 * Displayed when user attempts to access a page without proper permissions
 */

session_start();
require_once __DIR__ . '/../config/config.php';

$page_title = 'Access Denied - ' . APP_NAME;
$error_message = $_SESSION['permission_error'] ?? 'You do not have permission to access this page.';
$attempted_page = $_SESSION['attempted_page'] ?? null;

// Clear session messages
unset($_SESSION['permission_error']);
unset($_SESSION['attempted_page']);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body class="unauthorized-body">
    <div class="error-container">
        <div class="error-icon">🚫</div>
        <h1>Access Denied</h1>
        <div class="error-code">Error 403 - Forbidden</div>
        
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        
        <?php if ($attempted_page): ?>
        <div class="attempted-page">
            <strong>Attempted to access:</strong>
            <code><?php echo htmlspecialchars($attempted_page); ?></code>
        </div>
        <?php endif; ?>
        
        <div class="actions">
            <?php if ($is_logged_in): ?>
                <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-primary">
                    🏠 Go to Dashboard
                </a>
                <a href="javascript:history.back()" class="btn btn-secondary">
                    ← Go Back
                </a>
            <?php else: ?>
                <a href="<?php echo APP_URL; ?>/public/login.php" class="btn btn-primary">
                    🔐 Login
                </a>
            <?php endif; ?>
        </div>
        
        <div class="help-text">
            <p><strong>Need access to this page?</strong></p>
            <p>Contact your system administrator or <a href="mailto:admin@<?php echo parse_url(APP_URL, PHP_URL_HOST); ?>">request permission</a>.</p>
        </div>
    </div>
</body>
</html>
