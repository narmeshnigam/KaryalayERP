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
            // Prepare SQL statement to prevent SQL injection
            $sql = "SELECT id, username, password, full_name, role, is_active 
                    FROM users 
                    WHERE username = ? 
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                // Check if user exists
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if user is active
                    if ($user['is_active'] != 1) {
                        $error_message = 'Your account has been deactivated. Please contact administrator.';
                    } 
                    // Verify password
                    elseif (password_verify($password, $user['password'])) {
                        // Password is correct, create session
                        session_regenerate_id(true); // Prevent session fixation
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_time'] = time();
                        
                        // Redirect to dashboard
                        header('Location: index.php');
                        exit;
                    } else {
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
            <img src="<?php echo $logo_url; ?>" alt="<?php echo APP_NAME; ?>" style="height: 100px;">
            <p style="color: #666; font-size: 14px;">Please login to continue</p>
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
            <p style="color: #999; font-size: 13px;">
                Need help? Feel free to connect with us. <br>
                <strong>Email:</strong> hi@karyalay.in | <strong>Call:</strong> +917322005500
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
