<?php
/**
 * Setup Wizard - Create Admin User
 * Step 3: Create the first administrator account
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../includes/authz.php';

$status = getSetupStatus();
$error = '';
$success = '';

// If not configured or tables don't exist, redirect back
if (!$status['database_exists'] || !$status['users_table_exists']) {
    header('Location: create_tables.php');
    exit;
}

// If admin already exists, redirect to completion
if ($status['admin_exists']) {
    header('Location: ../public/branding/onboarding.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($username) || empty($full_name) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($username) < 4) {
        $error = 'Username must be at least 4 characters long.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                $error = 'Connection failed: ' . $conn->connect_error;
            } else {
                // Check if username already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = 'Username already exists. Please choose a different username.';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert admin user
                    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
                    $stmt->bind_param("ssss", $username, $hashed_password, $full_name, $email);
                    
                    if ($stmt->execute()) {
                        $success = 'Administrator account created successfully!';
                        $new_user_id = (int)$stmt->insert_id;

                        // Attach the Super Admin role for RBAC alignment.
                        authz_ensure_user_role_assignment($conn, $new_user_id, 'admin');
                        
                        // Auto-login the new admin user
                        session_start();
                        $_SESSION['user_id'] = $new_user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['role'] = 'admin';
                        $_SESSION['login_time'] = time();

                        authz_refresh_context($conn);
                        
                        $conn->close();
                        
                        // Redirect to branding setup
                        sleep(1);
                        header('Location: ../public/branding/onboarding.php');
                        exit;
                    } else {
                        $error = 'Error creating account: ' . $conn->error;
                    }
                }
                
                $stmt->close();
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Create Admin Account - Setup';
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
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 16px;
            text-align: left;
            flex: 1;
        }
        .form-group {
            margin-bottom: 0; /* grid gap controls spacing */
            text-align: left;
        }
        .span-2 {
            grid-column: span 2;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e1e8ed;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #003581;
            box-shadow: 0 0 0 3px rgba(0, 53, 129, 0.1);
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
        .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
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
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 6px;
            background: #e1e8ed;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="logo-top">
        <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>">
    </div>
    
    <div class="setup-container">
        <br>
        <h1>Create Administrator Account</h1>
        <p class="subtitle">Step 3 of 3 - Set up your admin credentials</p>
        
        <div class="progress-steps">
            <div class="step active"></div>
            <div class="step active"></div>
            <div class="step active"></div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <br>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" name="username" id="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    <div class="help-text">At least 4 characters, no spaces</div>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group span-2">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="help-text">Optional, but recommended for password recovery</div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="help-text">At least 6 characters, use a strong password</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
            </div>
            <br>
            <div>
                <button type="submit" class="btn">Create Account & Complete Setup →</button>
            </div>
            
        </form>
        
        <div style="text-align: center;">
            <a href="create_tables.php" class="back-link">← Back to Tables</a>
        </div>
    </div>
    
    <script>
        // Simple password strength indicator
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.length >= 10) strength += 25;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password) && /[^a-zA-Z0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.background = '#dc3545';
            } else if (strength < 75) {
                strengthBar.style.background = '#faa718';
            } else {
                strengthBar.style.background = '#28a745';
            }
        });
    </script>
</body>
</html>
