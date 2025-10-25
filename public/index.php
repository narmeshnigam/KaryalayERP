<?php
/**
 * Dashboard Page
 * 
 * Protected page - requires user to be logged in.
 * Displays user information and system statistics.
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include configuration and database connection
require_once __DIR__ . '/../config/db_connect.php';

// Get user information from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Calculate session duration
$login_time = $_SESSION['login_time'] ?? time();
$session_duration = time() - $login_time;
$minutes = floor($session_duration / 60);
$seconds = $session_duration % 60;

// Get some statistics from database
$total_users = 0;
$active_users = 0;

if ($conn) {
    // Count total users
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_users = $row['total'];
    }
    
    // Count active users
    $result = $conn->query("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
    if ($result) {
        $row = $result->fetch_assoc();
        $active_users = $row['active'];
    }
}

// Set page title
$page_title = 'Dashboard - ' . APP_NAME;

// Include header with sidebar
include __DIR__ . '/../includes/header_sidebar.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <!-- Welcome Message -->
        <div class="text-center mb-20">
            <h1 style="color: #003581; font-size: 28px; margin-bottom: 10px;">
                Welcome, <?php echo htmlspecialchars($full_name); ?>! 👋
            </h1>
            <p style="color: #666; font-size: 14px;">
                You are logged in as <strong><?php echo htmlspecialchars($role); ?></strong>
            </p>
        </div>
        
        <!-- Success Message -->
        <div class="alert alert-success">
            <span>✓</span>
            <span>You have successfully logged into the system!</span>
        </div>
        
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- User Info Card -->
            <div class="card">
                <div class="card-title">👤 Your Account</div>
                <div style="padding: 10px 0;">
                    <p style="margin-bottom: 10px; color: #555;">
                        <strong>Username:</strong> <?php echo htmlspecialchars($username); ?>
                    </p>
                    <p style="margin-bottom: 10px; color: #555;">
                        <strong>Full Name:</strong> <?php echo htmlspecialchars($full_name); ?>
                    </p>
                    <p style="margin-bottom: 10px; color: #555;">
                        <strong>Role:</strong> 
                        <span style="background: #003581; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                            <?php echo strtoupper(htmlspecialchars($role)); ?>
                        </span>
                    </p>
                    <p style="margin-bottom: 0; color: #555;">
                        <strong>User ID:</strong> #<?php echo $user_id; ?>
                    </p>
                </div>
            </div>
            
            <!-- Session Info Card -->
            <div class="card">
                <div class="card-title">⏱️ Session Info</div>
                <div style="padding: 10px 0;">
                    <p style="margin-bottom: 10px; color: #555;">
                        <strong>Login Time:</strong><br>
                        <?php echo date('d M Y, h:i A', $login_time); ?>
                    </p>
                    <p style="margin-bottom: 0; color: #555;">
                        <strong>Session Duration:</strong><br>
                        <?php echo $minutes; ?> minutes, <?php echo $seconds; ?> seconds
                    </p>
                </div>
            </div>
            
            <!-- System Stats Card -->
            <div class="card">
                <div class="card-title">📊 System Stats</div>
                <div style="padding: 10px 0;">
                    <p style="margin-bottom: 10px; color: #555;">
                        <strong>Total Users:</strong> 
                        <span style="font-size: 24px; color: #003581; font-weight: 700;">
                            <?php echo $total_users; ?>
                        </span>
                    </p>
                    <p style="margin-bottom: 0; color: #555;">
                        <strong>Active Users:</strong> 
                        <span style="font-size: 24px; color: #28a745; font-weight: 700;">
                            <?php echo $active_users; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Features/Actions Section -->
        <div class="card">
            <div class="card-title">🚀 Quick Actions</div>
            <div style="padding: 10px 0;">
                <p style="color: #666; margin-bottom: 15px;">
                    Your Karyalay ERP system is up and running! Here are some things you can do:
                </p>
                <ul style="color: #555; line-height: 2; padding-left: 20px;">
                    <li>✅ Login system is working perfectly</li>
                    <li>✅ Database connection is established</li>
                    <li>✅ Session management is active</li>
                    <li>✅ User authentication is secure</li>
                    <li>⚠️ Remember to change default admin password</li>
                </ul>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="card">
            <div class="card-title">ℹ️ System Information</div>
            <div style="padding: 10px 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <p style="color: #999; font-size: 12px; margin-bottom: 5px;">Application</p>
                        <p style="color: #333; font-weight: 600;"><?php echo APP_NAME; ?></p>
                    </div>
                    <div>
                        <p style="color: #999; font-size: 12px; margin-bottom: 5px;">Database</p>
                        <p style="color: #333; font-weight: 600;"><?php echo DB_NAME; ?></p>
                    </div>
                    <div>
                        <p style="color: #999; font-size: 12px; margin-bottom: 5px;">PHP Version</p>
                        <p style="color: #333; font-weight: 600;"><?php echo phpversion(); ?></p>
                    </div>
                    <div>
                        <p style="color: #999; font-size: 12px; margin-bottom: 5px;">Server Time</p>
                        <p style="color: #333; font-weight: 600;"><?php echo date('h:i A'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include __DIR__ . '/../includes/footer_sidebar.php';

// Close database connection
if (isset($conn)) {
    closeConnection($conn);
}
?>
