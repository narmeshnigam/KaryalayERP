<?php
/**
 * Setup Wizard - Create Tables
 * Step 2: Create users table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/rbac_bootstrap.php';

$status = getSetupStatus();
$error = '';
$success = '';

// If not configured, redirect to database setup
if (!$status['database_exists']) {
    header('Location: database.php');
    exit;
}

// If already past this step, redirect to next step
if ($status['users_table_exists']) {
    header('Location: create_admin.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tables'])) {
    $transactionStarted = false;

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            throw new RuntimeException('Connection failed: ' . $conn->connect_error);
        }

        if (!$conn->set_charset(DB_CHARSET)) {
            throw new RuntimeException('Unable to set database charset to ' . DB_CHARSET);
        }

        $conn->begin_transaction();
        $transactionStarted = true;

        $usersSql = "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_id INT NULL,
            entity_type ENUM('Employee','Client','Other') NULL,
            username VARCHAR(100) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NULL,
            phone VARCHAR(20) NULL,
            password_hash VARCHAR(255) NOT NULL,
            role_id INT NULL,
            status ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_users_username (username),
            UNIQUE KEY uniq_users_email (email),
            INDEX idx_users_entity (entity_id, entity_type),
            INDEX idx_users_role (role_id),
            INDEX idx_users_status (status),
            INDEX idx_users_last_login (last_login)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($usersSql)) {
            throw new RuntimeException('Failed to create users table: ' . $conn->error);
        }

        $activitySql = "CREATE TABLE IF NOT EXISTS user_activity_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45) NULL,
            device VARCHAR(255) NULL COMMENT 'User agent string - truncated if needed',
            login_time DATETIME NOT NULL,
            status ENUM('Success','Failed') NOT NULL DEFAULT 'Success',
            failure_reason VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_activity_user (user_id),
            INDEX idx_user_activity_status (status),
            INDEX idx_user_activity_login (login_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($activitySql)) {
            throw new RuntimeException('Failed to create user activity log table: ' . $conn->error);
        }

        setup_rbac_bootstrap($conn);

        $conn->commit();
        $transactionStarted = false;
        $conn->close();

        header('Location: create_admin.php');
        exit;
    } catch (Throwable $e) {
        if (isset($conn) && $conn instanceof mysqli) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            $conn->close();
        }
        $error = 'Error creating tables: ' . $e->getMessage();
    }
}

$page_title = 'Create Tables - Setup';
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
            margin-bottom: 6px;
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
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 13px;
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
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 18px 20px;
            margin-bottom: 20px;
            text-align: left;
        }
        .info-box h3 {
            color: #003581;
            font-size: 15px;
            margin-bottom: 8px;
        }
        .info-box p {
            color: #1b2a57;
            font-size: 13px;
            line-height: 1.5;
        }
        .back-link {
            display: inline-block;
            margin-top: 12px;
            color: #003581;
            text-decoration: none;
            font-size: 13px;
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
        <h1>Create Database Tables</h1>
        <p class="subtitle">Step 2 of 3 - Set up the required database structure</p>
        
        <div class="progress-steps">
            <div class="step active"></div>
            <div class="step active"></div>
            <div class="step"></div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="info-box" style="flex:1;">
            <h3>What will be created?</h3>
            <p>
                This step initialises the <strong>Users</strong> table, activity log, and the
                <strong>Roles &amp; Permissions</strong> tables required for fine-grained access control.
                Default roles (Super Admin, Admin, Manager, Employee, etc.) and permission presets will be seeded automatically.
            </p>
            <p style="margin-top: 8px;">
                <strong>Database:</strong> <?php echo htmlspecialchars(DB_NAME); ?><br>
                <strong>Host:</strong> <?php echo htmlspecialchars(DB_HOST); ?>
            </p>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="create_tables" value="1">
            <button type="submit" class="btn">Create Core Tables →</button>
        </form>
        
        <div style="text-align: center;">
            <a href="database.php" class="back-link">← Back to Database Config</a>
        </div>
    </div>
</body>
</html>
