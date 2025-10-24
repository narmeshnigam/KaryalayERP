<?php
/**
 * Database Setup Script
 * 
 * This script automatically:
 * 1. Creates the database if it doesn't exist
 * 2. Creates the users table if it doesn't exist
 * 3. Inserts a default admin user if no users exist
 * 
 * Run this script once during initial setup or when database needs reset.
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Store setup messages
$setup_messages = [];
$setup_success = true;

/**
 * Create database and setup tables
 */
function setupDatabase() {
    global $setup_messages, $setup_success;
    
    try {
        // Step 1: Connect without database to create it
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Step 2: Create database if not exists
        $db_name = DB_NAME;
        $sql = "CREATE DATABASE IF NOT EXISTS `$db_name` 
                CHARACTER SET " . DB_CHARSET . " 
                COLLATE utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            $setup_messages[] = "✓ Database '$db_name' is ready";
        } else {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Step 3: Select the database
        $conn->select_db($db_name);
        
        // Step 4: Create users table if not exists
        $sql = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `full_name` VARCHAR(100) DEFAULT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `role` ENUM('admin', 'user', 'manager') DEFAULT 'user',
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_username` (`username`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . DB_CHARSET . " COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            $setup_messages[] = "✓ Users table is ready";
        } else {
            throw new Exception("Error creating users table: " . $conn->error);
        }
        
        // Step 5: Check if admin user exists
        $check_sql = "SELECT id FROM users WHERE username = 'admin' LIMIT 1";
        $result = $conn->query($check_sql);
        
        if ($result->num_rows == 0) {
            // Step 6: Insert default admin user
            $default_username = 'admin';
            $default_password = 'admin123';
            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
            $full_name = 'System Administrator';
            $email = 'admin@karyalay.com';
            
            $insert_sql = "INSERT INTO users (username, password, full_name, email, role, is_active) 
                          VALUES (?, ?, ?, ?, 'admin', 1)";
            
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssss", $default_username, $hashed_password, $full_name, $email);
            
            if ($stmt->execute()) {
                $setup_messages[] = "✓ Default admin user created successfully";
                $setup_messages[] = "→ Username: admin";
                $setup_messages[] = "→ Password: admin123";
                $setup_messages[] = "⚠ Please change the default password after first login!";
            } else {
                throw new Exception("Error creating admin user: " . $stmt->error);
            }
            
            $stmt->close();
        } else {
            $setup_messages[] = "✓ Admin user already exists";
        }
        
        $conn->close();
        return true;
        
    } catch (Exception $e) {
        $setup_success = false;
        $setup_messages[] = "✗ Error: " . $e->getMessage();
        return false;
    }
}

// Run setup if accessed directly
if (basename($_SERVER['PHP_SELF']) == 'setup_db.php') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Setup - <?php echo APP_NAME; ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f4f6f9;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            .setup-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                padding: 40px;
                max-width: 600px;
                width: 100%;
            }
            .setup-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .setup-header h1 {
                color: #003581;
                font-size: 28px;
                margin-bottom: 10px;
            }
            .setup-header p {
                color: #666;
                font-size: 14px;
            }
            .setup-messages {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .message {
                padding: 10px 0;
                font-size: 14px;
                line-height: 1.6;
            }
            .message.success {
                color: #28a745;
            }
            .message.error {
                color: #dc3545;
            }
            .message.warning {
                color: #faa718;
            }
            .message.info {
                color: #17a2b8;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: #003581;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                transition: transform 0.2s;
                text-align: center;
            }
            .btn:hover {
                background: #004aad;
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(0, 53, 129, 0.3);
            }
            .btn-container {
                text-align: center;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="setup-container">
            <div class="setup-header">
                <h1>🔧 Database Setup</h1>
                <p>Initializing your Karyalay ERP system</p>
            </div>
            
            <div class="setup-messages">
                <?php
                // Run setup
                setupDatabase();
                
                // Display messages
                foreach ($setup_messages as $message) {
                    $class = 'info';
                    if (strpos($message, '✓') !== false) {
                        $class = 'success';
                    } elseif (strpos($message, '✗') !== false) {
                        $class = 'error';
                    } elseif (strpos($message, '⚠') !== false) {
                        $class = 'warning';
                    }
                    echo "<div class='message $class'>$message</div>";
                }
                ?>
            </div>
            
            <div class="btn-container">
                <?php if ($setup_success): ?>
                    <a href="../index.php" class="btn">Go to Login Page</a>
                <?php else: ?>
                    <a href="setup_db.php" class="btn">Try Again</a>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
