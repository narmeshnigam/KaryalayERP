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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .error-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            max-width: 600px;
            text-align: center;
        }
        
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        h1 {
            color: #1b2a57;
            font-size: 32px;
            margin-bottom: 16px;
        }
        
        .error-code {
            color: #dc3545;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .error-message {
            color: #6c757d;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .attempted-page {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
            text-align: left;
        }
        
        .attempted-page strong {
            display: block;
            margin-bottom: 8px;
            color: #1b2a57;
        }
        
        .attempted-page code {
            background: white;
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            color: #dc3545;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #003581;
            color: white;
        }
        
        .btn-primary:hover {
            background: #002660;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 53, 129, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .help-text {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
        
        .help-text a {
            color: #003581;
            text-decoration: none;
            font-weight: 600;
        }
        
        .help-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
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
