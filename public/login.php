<?php
/**
 * Login Page
 * 
 * Displays login form and handles authentication.
 * Users must provide valid username and password.
 * Passwords are verified using password_verify() against hashed passwords.
 */

// Start session
session_start();

// Include configuration and database connection
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../includes/authz.php';
require_once __DIR__ . '/users/helpers.php';

// Check if setup is complete, redirect to setup wizard if not
if (!isSetupComplete()) {
    header('Location: ' . APP_URL . '/setup/index.php');
    exit;
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Initialize variables
$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Check if connection exists
        if ($conn) {
            // Detect column availability for backwards compatibility
            $columnChecks = [
                'password_hash' => false,
                'password' => false,
                'status' => false,
                'is_active' => false,
            ];

            foreach (array_keys($columnChecks) as $columnName) {
                $colResult = $conn->query("SHOW COLUMNS FROM users LIKE '" . $conn->real_escape_string($columnName) . "'");
                if ($colResult && $colResult->num_rows > 0) {
                    $columnChecks[$columnName] = true;
                }
                if ($colResult) {
                    $colResult->free();
                }
            }

            $selectFields = ['u.id', 'u.username', 'u.full_name'];
            if ($columnChecks['password_hash']) {
                $selectFields[] = 'u.password_hash';
            }
            if ($columnChecks['password']) {
                $selectFields[] = 'u.password AS legacy_password';
            }
            if ($columnChecks['status']) {
                $selectFields[] = 'u.status';
            }
            if ($columnChecks['is_active']) {
                $selectFields[] = 'u.is_active';
            }

            $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM users u WHERE u.username = ? LIMIT 1';
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    $passwordHash = null;
                    if ($columnChecks['password_hash'] && !empty($user['password_hash'])) {
                        $passwordHash = $user['password_hash'];
                    } elseif ($columnChecks['password'] && !empty($user['legacy_password'])) {
                        $passwordHash = $user['legacy_password'];
                    }

                    $accountActive = true;
                    if ($columnChecks['status'] && isset($user['status'])) {
                        $accountActive = ($user['status'] === 'Active');
                    } elseif ($columnChecks['is_active'] && isset($user['is_active'])) {
                        $accountActive = ((int) $user['is_active'] === 1);
                    }

                    if (!$accountActive) {
                        $error_message = 'Your account has been deactivated. Please contact administrator.';
                    } elseif ($passwordHash && password_verify($password, $passwordHash)) {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = (int) $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['login_time'] = time();

                        // Record login activity and update last_login
                        update_last_login($conn, (int)$user['id']);
                        log_user_activity($conn, [
                            'user_id' => (int)$user['id'],
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                            'device' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'login_time' => date('Y-m-d H:i:s'),
                            'status' => 'Success',
                            'failure_reason' => null
                        ]);

                        if (authz_roles_tables_exist($conn)) {
                            // Ensure at least one role assignment exists, falling back to Employee if needed.
                            authz_ensure_user_role_assignment($conn, (int) $user['id']);

                            $roleNames = [];
                            $roleStmt = $conn->prepare('SELECT r.name FROM user_roles ur INNER JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?');
                            if ($roleStmt) {
                                $userIdForRole = (int) $user['id'];
                                $roleStmt->bind_param('i', $userIdForRole);
                                $roleStmt->execute();
                                $roleResult = $roleStmt->get_result();
                                while ($roleRow = $roleResult->fetch_assoc()) {
                                    $roleNames[] = $roleRow['name'];
                                }
                                $roleStmt->close();
                            }
                            $_SESSION['role_names'] = $roleNames;
                        } else {
                            $_SESSION['role_names'] = [];
                        }

                        authz_refresh_context($conn);

                        header('Location: index.php');
                        exit;
                    } else {
                        // If user exists, log failed attempt
                        log_user_activity($conn, [
                            'user_id' => (int)$user['id'],
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                            'device' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'login_time' => date('Y-m-d H:i:s'),
                            'status' => 'Failed',
                            'failure_reason' => 'Invalid password'
                        ]);

                        $error_message = 'Invalid username or password.';
                    }
                } else {
                    $error_message = 'Invalid username or password.';
                }

                $stmt->close();
            } else {
                $error_message = 'Database error. Please try again later.';
            }
        } else {
            $error_message = 'Database connection error. Please contact administrator.';
        }
    }
}

// Set page title
$page_title = 'Login - ' . APP_NAME;

// Include header
include __DIR__ . '/../includes/header.php';
?>

<div class="main-content">
    <div class="container">
        <!-- Logo/Title -->
        <div class="text-center mb-20">
            <?php
                // Try to load login_page_logo from branding settings
                $branding_logo = '';
                if ($conn && isset($conn)) {
                    // Check if branding_settings table exists
                    $table_check = @mysqli_query($conn, "SHOW TABLES LIKE 'branding_settings'");
                    if ($table_check && mysqli_num_rows($table_check) > 0) {
                        $res = @mysqli_query($conn, "SELECT login_page_logo FROM branding_settings LIMIT 1");
                        if ($res && mysqli_num_rows($res) > 0) {
                            $row = mysqli_fetch_assoc($res);
                            if (!empty($row['login_page_logo'])) {
                                $branding_logo = APP_URL . '/' . $row['login_page_logo'];
                            }
                            mysqli_free_result($res);
                        }
                        if ($table_check) @mysqli_free_result($table_check);
                    }
                }
                
                // Use branding logo if available, otherwise fall back to default
                $logo_url = !empty($branding_logo) ? $branding_logo : APP_URL . '/assets/logo/logo_white_bg.png';
            ?>
            <img src="<?php echo $logo_url; ?>" alt="<?php echo APP_NAME; ?>" class="login-logo">
            <p class="login-subtitle">Please login to continue</p>
        </div>
        
        <!-- Error/Success Messages -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="Enter your username"
                    value="<?php echo htmlspecialchars($username ?? ''); ?>"
                    required
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Enter your password"
                    required
                >
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <!-- Additional Info -->
        <div class="text-center mt-20">
            <p class="login-help-text">
                Need help? Feel free to connect with us. <br>
                <strong>Email:</strong> hi@karyalay.in | <strong>Call:</strong> +919608138365
            </p>
        </div>
    </div>
</div>

<?php

// Close database connection
if (isset($conn)) {
    closeConnection($conn);
}
?>
