<?php
/**
 * Header Include File
 * 
 * Contains common HTML head, navigation, and header elements
 * Used across all pages for consistent UI
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config for APP_NAME
require_once __DIR__ . '/../config/config.php';

// Get page title from variable or use default
$page_title = isset($page_title) ? $page_title : APP_NAME;
$current_page = basename($_SERVER['PHP_SELF']);

// Load favicon from branding settings if available
$favicon_url = APP_URL . '/assets/logo/icon_blue_text_transparent.png'; // default
$conn_favicon = @createConnection(true);
if ($conn_favicon) {
    // Check if branding_settings table exists
    $table_check = @mysqli_query($conn_favicon, "SHOW TABLES LIKE 'branding_settings'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $res = @mysqli_query($conn_favicon, "SELECT favicon FROM branding_settings LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            if (!empty($row['favicon'])) {
                $favicon_url = APP_URL . '/' . $row['favicon'];
            }
            mysqli_free_result($res);
        }
        if ($table_check) @mysqli_free_result($table_check);
    }
    @closeConnection($conn_favicon);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $favicon_url; ?>">
    <link rel="shortcut icon" href="<?php echo $favicon_url; ?>">
    
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header Styles */
        .header {
            background: #003581;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0;
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo:hover {
            color: #faa718;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 20px;
            align-items: center;
        }
        
        .nav-menu a {
            text-decoration: none;
            color: #ffffff;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #faa718;
        }
        
        .nav-menu a.active {
            background: #faa718;
            color: #003581;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
            font-size: 14px;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white !important;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        /* Main Content Container */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Container Styles */
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .container-large {
            max-width: 900px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
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
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #003581;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            background: #004aad;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 53, 129, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }
        
        /* Text Styles */
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
        
        .mb-20 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Header Navigation for logged-in users -->
    <header class="header">
        <div class="header-container">
            <a href="<?php echo APP_URL; ?>" class="logo">
                <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>" style="height: 40px;">
            </a>
            
            <nav>
                <?php
                    $header_role_names = $_SESSION['role_names'] ?? [];
                    $header_role_display = !empty($header_role_names) ? implode(', ', $header_role_names) : '';
                ?>
                <ul class="nav-menu">
                    <li><a href="../public/index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Dashboard</a></li>
                    <li>
                        <div class="user-info" title="<?php echo $header_role_display ? 'Roles: ' . htmlspecialchars($header_role_display) : 'No roles assigned'; ?>">
                            👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
                            <?php if ($header_role_display): ?>
                                <span style="display:block;font-size:11px;color:#cbd5f5;">Roles: <?php echo htmlspecialchars($header_role_display); ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li><a href="../public/logout.php" class="logout-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <?php endif; ?>
