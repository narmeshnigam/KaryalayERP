<?php
/**
 * Force Mark Setup as Complete
 * 
 * This script manually marks the setup as complete, allowing you to
 * proceed to the login page even if module installation had errors.
 * You can install remaining modules later from the admin panel.
 * 
 * Usage: Visit http://localhost/karyalayerp/setup/force_complete.php
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../config/db_connect.php';

// Check if user is logged in (optional - remove if you want to allow without login)
$require_login = false; // Set to true if you want to require login
if ($require_login && !isset($_SESSION['user_id'])) {
    die('Error: Please login first by visiting setup/create_admin.php');
}

$success = false;
$message = '';

try {
    $conn = createConnection(true);
    
    if ($conn) {
        // Try to mark as complete in database
        $success = markModuleInstallerComplete($conn);
        closeConnection($conn);
        
        if ($success) {
            $message = 'Setup marked as complete successfully!';
        } else {
            $message = 'Failed to mark setup as complete in database, but marker file may have been created.';
        }
    } else {
        // If no database connection, just create the marker file
        $marker_file = __DIR__ . '/../.module_installer_complete';
        if (file_put_contents($marker_file, date('Y-m-d H:i:s')) !== false) {
            $success = true;
            $message = 'Setup marked as complete using marker file!';
        } else {
            $message = 'Failed to create marker file.';
        }
    }
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Force Complete Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #003581;
            font-size: 28px;
            margin-bottom: 16px;
        }
        .message {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            margin: 8px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 53, 129, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #003581;
            padding: 16px;
            margin: 20px 0;
            text-align: left;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #003581;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .info-box p {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon"><?php echo $success ? '✅' : '❌'; ?></div>
        <h1><?php echo $success ? 'Setup Completed!' : 'Setup Failed'; ?></h1>
        <p class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
        
        <?php if ($success): ?>
        <div class="info-box">
            <h3>What's Next?</h3>
            <p>
                Your setup has been marked as complete. You can now access the application.
                Any modules that failed to install can be installed later from the admin panel.
            </p>
        </div>
        
        <a href="<?php echo APP_URL; ?>/public/login.php" class="btn">
            Go to Login Page →
        </a>
        <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-secondary">
            Go to Dashboard
        </a>
        <?php else: ?>
        <div class="info-box">
            <h3>Troubleshooting</h3>
            <p>
                If you're seeing this error, try the following:
                <br>• Make sure your database connection is working
                <br>• Check that you have write permissions in the application directory
                <br>• Verify that the config/config.php file has correct database credentials
            </p>
        </div>
        
        <a href="<?php echo APP_URL; ?>/setup/install_tables.php" class="btn">
            Back to Setup
        </a>
        <?php endif; ?>
    </div>
</body>
</html>
