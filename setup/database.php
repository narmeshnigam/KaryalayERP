<?php
/**
 * Setup Wizard - Database Configuration
 * Step 1: Configure database connection
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';

$status = getSetupStatus();
$error = '';
$success = '';

// If already past this step, redirect to next step
if ($status['database_exists'] && $status['users_table_exists']) {
    header('Location: create_admin.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? 'karyalay_db');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';
    
    // Validate inputs
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Test database connection
        try {
            $test_conn = @new mysqli($db_host, $db_user, $db_pass);
            
            if ($test_conn->connect_error) {
                $error = 'Connection failed: ' . $test_conn->connect_error;
            } else {
                // Connection successful
                // Check if database exists, create if not
                $db_exists = $test_conn->query("SHOW DATABASES LIKE '$db_name'");
                
                if ($db_exists && $db_exists->num_rows > 0) {
                    $success = 'Database already exists. Using existing database.';
                } else {
                    // Create database
                    if ($test_conn->query("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                        $success = 'Database created successfully!';
                    } else {
                        $error = 'Could not create database: ' . $test_conn->error;
                        $test_conn->close();
                    }
                }
                
                if ($success) {
                    // Update config file with new credentials
                    if (updateDatabaseConfig($db_host, $db_user, $db_pass, $db_name)) {
                        $test_conn->close();
                        // Redirect to next step
                        header('Location: create_tables.php');
                        exit;
                    } else {
                        $error = 'Could not update configuration file. Please check file permissions.';
                    }
                }
                
                $test_conn->close();
            }
        } catch (Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Database Configuration - Setup';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow: hidden;
        }
        .logo-top {
            position: fixed;
            top: 30px;
            left: 40px;
            z-index: 100;
        }
        .logo-top img {
            height: 50px;
            width: auto;
        }
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            /* viewport-aware fixed height to avoid scroll while keeping boxes equal */
            height: calc(100vh - 120px);
            max-height: calc(100vh - 120px);
            padding: 30px 40px;
            text-align: center;
            display: flex;
            flex-direction: column;
        }
        h1 {
            color: #003581;
            font-size: 24px;
            margin-bottom: 4px;
            flex-shrink: 0;
        }
        .subtitle {
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 12px;
            flex-shrink: 0;
        }
        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
            flex-shrink: 0;
        }
        .step {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e1e8ed;
        }
        .step.active {
            background: #003581;
        }
        .content-area {
            flex: 1;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 16px;
            text-align: left;
            margin-top: 6px;
        }
        .form-group {
            margin-bottom: 0; /* grid gap controls vertical rhythm */
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 4px;
            color: #333;
            font-weight: 600;
            font-size: 13px;
        }
        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid #e1e8ed;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #003581;
            box-shadow: 0 0 0 3px rgba(0, 53, 129, 0.1);
        }
        .alert {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 12px;
            text-align: left;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .help-text {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
        }
        .btn {
            display: inline-block;
            padding: 12px 36px;
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 53, 129, 0.3);
        }
        .footer-links {
            text-align: center;
            flex-shrink: 0;
        }
        .back-link {
            display: inline-block;
            color: #003581;
            text-decoration: none;
            font-size: 12px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="logo-top">
        <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>">
    </div>
    
    <div class="setup-container">
        <br>
        <h1>Database Configuration</h1>
        <p class="subtitle">Step 1 of 3 - Configure your database connection</p>
        
        <div class="progress-steps">
            <div class="step active"></div>
            <div class="step"></div>
            <div class="step"></div>
        </div>
        
        <div class="content-area">
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <br>
            <div style="margin-bottom: 24px;">
                <div style="font-size:18px; color:#003581; font-weight:600; margin-bottom:2px;">Connect to your MySQL database</div>
                <div style="font-size:13px; color:#6c757d;">Enter your database details below. Your credentials are never shared.</div>
            </div>
            <form method="POST" action="">
                <div class="form-grid" style="margin-bottom: 18px;">
                    <div class="form-group">
                        <label for="db_host">Database Host *</label>
                        <input type="text" name="db_host" id="db_host" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                        <div class="help-text">Usually "localhost" for local installations</div>
                    </div>
                    <div class="form-group">
                        <label for="db_name">Database Name *</label>
                        <input type="text" name="db_name" id="db_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'karyalay_db'); ?>" required>
                        <div class="help-text">Will be created if it doesn't exist</div>
                    </div>
                    <div class="form-group">
                        <label for="db_user">Database Username *</label>
                        <input type="text" name="db_user" id="db_user" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                        <div class="help-text">Your MySQL username</div>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" name="db_pass" id="db_pass" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                        <div class="help-text">Leave empty if no password (XAMPP default)</div>
                    </div>
                </div>
                <div style="display:flex; justify-content:center; margin-bottom: 18px;">
                    <div style="text-align:center;">
                        <div style="font-size:15px; color:#003581; font-weight:600;">Secure Setup</div>
                        <div style="font-size:12px; color:#6c757d;">No credentials stored in browser</div>
                    </div>
                    <div style="width:32px;"></div>
                    <div style="text-align:center;">
                        <div style="font-size:15px; color:#003581; font-weight:600;">Auto-create DB</div>
                        <div style="font-size:12px; color:#6c757d;">Creates if not found</div>
                    </div>
                </div>
                <button type="submit" class="btn">Test & Continue →</button>
            </form>
        </div>
        
        <div class="footer-links">
            <a href="index.php" class="back-link">← Back to Welcome</a>
        </div>
    </div>
</body>
</html>
