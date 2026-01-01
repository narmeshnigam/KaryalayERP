<?php
/**
 * Module Installer - Unified Module Installation Interface
 * Step 4 of Setup Wizard: Install multiple modules at once
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../includes/module_discovery.php';
require_once __DIR__ . '/../includes/module_categories.php';
require_once __DIR__ . '/../includes/dependency_resolver.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check - redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/public/login.php');
    exit;
}

// Authorization check - require Super Admin or Admin role
require_once __DIR__ . '/../includes/authz.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    
    authz_refresh_context($conn);
    
    // Check if user has Super Admin or Admin role
    $has_access = authz_has_role('Super Admin') || authz_has_role('Admin');
    
    if (!$has_access) {
        $conn->close();
        header('Location: ' . APP_URL . '/public/unauthorized.php');
        exit;
    }
    
    // Load available modules with installation status
    $all_modules = discover_modules($conn);
    
    // Check if no modules are available (empty module list)
    $no_modules_available = empty($all_modules);
    
    // Check if accessed from settings/dashboard - filter to show only uninstalled modules
    $from_settings = isset($_GET['from']) && $_GET['from'] === 'settings';
    
    if ($from_settings) {
        // Filter to show only uninstalled modules
        $all_modules = array_filter($all_modules, function($module) {
            return !$module['installed'];
        });
    }
    
    // Group modules by category
    $modules_by_category = get_modules_by_category($all_modules);
    
    // Check if all modules are already installed
    $all_installed = false;
    if (!$no_modules_available && !empty($all_modules)) {
        $all_installed = true;
        foreach ($all_modules as $module) {
            if (!$module['installed']) {
                $all_installed = false;
                break;
            }
        }
    }
    
    // Check if filtered list is empty (when accessed from settings)
    $no_uninstalled_modules = $from_settings && empty($all_modules);
    
    // Get dependencies for JavaScript
    $dependencies = load_module_dependencies();
    
    // Get mandatory modules
    $mandatory_modules = get_mandatory_module_list();
    
    $conn->close();
    
} catch (Exception $e) {
    die('Error loading modules: ' . $e->getMessage());
}

$page_title = 'Module Installer - ' . APP_NAME;
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
            padding: 20px;
            overflow-x: hidden;
        }
        .logo-top {
            position: fixed;
            top: 24px;
            left: 28px;
            z-index: 100;
        }
        .logo-top img {
            height: 48px;
            width: auto;
        }
        .installer-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 1200px;
            width: 100%;
            margin: 60px auto 40px;
            padding: 40px;
        }
        h1 {
            color: #003581;
            font-size: 28px;
            margin-bottom: 8px;
            text-align: center;
        }
        .subtitle {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
        }
        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 30px;
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
        .all-installed-message {
            text-align: center;
            padding: 60px 20px;
        }
        .all-installed-message .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .all-installed-message h2 {
            color: #28a745;
            font-size: 24px;
            margin-bottom: 12px;
        }
        .all-installed-message p {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .category-section {
            margin-bottom: 40px;
        }
        .category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e1e8ed;
        }
        .category-icon {
            font-size: 24px;
        }
        .category-title {
            color: #003581;
            font-size: 20px;
            font-weight: 600;
        }
        .category-description {
            color: #6c757d;
            font-size: 13px;
            margin-left: auto;
        }
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .module-card {
            background: #f8f9fa;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
        }
        .module-card:hover {
            border-color: #003581;
            box-shadow: 0 4px 12px rgba(0, 53, 129, 0.1);
        }
        .module-card.installed {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        .module-card.selected {
            background: #e3f2fd;
            border-color: #003581;
        }
        .module-card.mandatory {
            background: #fff3e0;
            border-color: #ff9800;
            position: relative;
        }
        .module-card.mandatory::before {
            content: '⭐';
            position: absolute;
            top: -8px;
            left: -8px;
            background: #ff9800;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .module-card.mandatory.selected {
            background: #fff3e0;
            border-color: #ff9800;
        }
        .module-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        .module-icon {
            font-size: 32px;
            flex-shrink: 0;
        }
        .module-info {
            flex: 1;
        }
        .module-name {
            color: #1b2a57;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .module-description {
            color: #6c757d;
            font-size: 13px;
            line-height: 1.4;
        }
        .module-checkbox {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .module-checkbox:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
        .module-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }
        .status-installed {
            background: #4caf50;
            color: white;
        }
        .status-mandatory {
            background: #ff9800;
            color: white;
        }
        .module-actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
        }
        .btn-info {
            padding: 6px 12px;
            background: #e1e8ed;
            color: #003581;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-info:hover {
            background: #003581;
            color: white;
        }
        .action-bar {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px 0;
            border-top: 2px solid #e1e8ed;
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .selection-info {
            color: #6c757d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-select-all, .btn-deselect-all {
            padding: 6px 12px;
            background: #e1e8ed;
            color: #003581;
            border: 1px solid #c1c9d0;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-select-all:hover, .btn-deselect-all:hover {
            background: #003581;
            color: white;
            border-color: #003581;
        }
        .selection-count {
            color: #003581;
            font-weight: 600;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
        }
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            color: white;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 53, 129, 0.3);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal-header {
            padding: 30px 30px 20px;
            border-bottom: 2px solid #e1e8ed;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .modal-icon {
            font-size: 48px;
            flex-shrink: 0;
        }
        .modal-title-section {
            flex: 1;
        }
        .modal-title {
            color: #003581;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .modal-category {
            color: #6c757d;
            font-size: 13px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        .modal-close:hover {
            background: #f8f9fa;
            color: #003581;
        }
        .modal-body {
            padding: 30px;
        }
        .modal-section {
            margin-bottom: 24px;
        }
        .modal-section:last-child {
            margin-bottom: 0;
        }
        .modal-section-title {
            color: #1b2a57;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-section-content {
            color: #495057;
            font-size: 14px;
            line-height: 1.6;
        }
        .dependency-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .dependency-item {
            padding: 10px 12px;
            background: #f8f9fa;
            border-left: 3px solid #003581;
            margin-bottom: 8px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dependency-item.installed {
            background: #e8f5e9;
            border-left-color: #4caf50;
        }
        .dependency-icon {
            font-size: 18px;
        }
        .dependency-name {
            font-weight: 600;
            color: #1b2a57;
        }
        .dependency-status {
            margin-left: auto;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            background: #e1e8ed;
            color: #495057;
        }
        .dependency-status.installed {
            background: #4caf50;
            color: white;
        }
        .table-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
        }
        .table-item {
            padding: 8px 12px;
            background: #e3f2fd;
            border-radius: 6px;
            font-size: 13px;
            color: #003581;
            font-family: 'Courier New', monospace;
        }
        .info-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #856404;
        }
        /* Loading animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        @keyframes slideInFromLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes progressBarFill {
            from {
                width: 0%;
            }
        }
        
        /* Apply animations */
        .installer-container {
            animation: fadeIn 0.5s ease-out;
        }
        
        .category-section {
            animation: fadeIn 0.6s ease-out;
        }
        
        .module-card {
            animation: fadeIn 0.4s ease-out;
            animation-fill-mode: both;
        }
        
        /* Stagger animation for module cards */
        .module-card:nth-child(1) { animation-delay: 0.05s; }
        .module-card:nth-child(2) { animation-delay: 0.1s; }
        .module-card:nth-child(3) { animation-delay: 0.15s; }
        .module-card:nth-child(4) { animation-delay: 0.2s; }
        .module-card:nth-child(5) { animation-delay: 0.25s; }
        .module-card:nth-child(6) { animation-delay: 0.3s; }
        
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Progress bar animation */
        #progressBar {
            animation: progressBarFill 0.5s ease-out;
        }
        
        /* Smooth transitions */
        .module-card,
        .btn,
        .btn-info,
        .module-checkbox,
        .modal-close,
        .dependency-item {
            transition: all 0.3s ease;
        }
        
        /* Hover effects with smooth transitions */
        .module-card:hover {
            transform: translateY(-2px);
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }
        
        /* Focus states for accessibility */
        .module-checkbox:focus {
            outline: 3px solid rgba(0, 53, 129, 0.3);
            outline-offset: 2px;
        }
        
        .btn:focus,
        .btn-info:focus {
            outline: 3px solid rgba(0, 53, 129, 0.3);
            outline-offset: 2px;
        }
        
        /* Responsive design improvements */
        @media (max-width: 1024px) {
            .modules-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .logo-top {
                top: 16px;
                left: 16px;
            }
            .logo-top img {
                height: 40px;
            }
            .installer-container {
                padding: 20px;
                margin: 50px 10px 20px;
                border-radius: 12px;
            }
            h1 {
                font-size: 24px;
            }
            .subtitle {
                font-size: 13px;
            }
            .modules-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .action-bar {
                flex-direction: column;
                gap: 12px;
                position: relative;
                padding: 16px 0;
            }
            .selection-info {
                justify-content: center;
            }
            .action-buttons {
                width: 100%;
                flex-direction: column;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
            .btn-select-all, .btn-deselect-all {
                flex: 1;
            }
            .modal-content {
                max-height: 95vh;
                border-radius: 12px;
                margin: 10px;
            }
            .modal-header {
                padding: 20px;
                flex-wrap: wrap;
            }
            .modal-icon {
                font-size: 36px;
            }
            .modal-title {
                font-size: 20px;
            }
            .modal-body {
                padding: 20px;
            }
            .table-list {
                grid-template-columns: 1fr;
            }
            .category-header {
                flex-wrap: wrap;
            }
            .category-description {
                margin-left: 0;
                width: 100%;
                margin-top: 4px;
            }
        }
        
        @media (max-width: 480px) {
            .installer-container {
                padding: 16px;
                margin: 40px 5px 20px;
            }
            .module-card {
                padding: 16px;
            }
            .module-header {
                gap: 10px;
            }
            .module-icon {
                font-size: 28px;
            }
            .module-name {
                font-size: 15px;
            }
            .module-description {
                font-size: 12px;
            }
            .progress-steps {
                gap: 6px;
            }
            .step {
                width: 8px;
                height: 8px;
            }
        }
        
        /* Screen reader only content */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
        
        /* Skip to main content link */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: #003581;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            z-index: 1000;
            border-radius: 0 0 4px 0;
        }
        
        .skip-link:focus {
            top: 0;
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .module-card {
                border-width: 3px;
            }
            .btn {
                border: 2px solid currentColor;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* Print styles */
        @media print {
            .logo-top,
            .action-bar,
            .module-checkbox,
            .btn-info,
            .modal-overlay {
                display: none !important;
            }
            body {
                background: white;
            }
            .installer-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <a href="#page-title" class="skip-link">Skip to main content</a>
    <div class="logo-top">
        <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>">
    </div>
    
    <div class="installer-container" role="main" aria-labelledby="page-title">
        <h1 id="page-title">Module Installer</h1>
        <p class="subtitle">Select and install the modules you need for your business</p>
        
        <div class="progress-steps" role="progressbar" aria-label="Setup progress" aria-valuenow="4" aria-valuemin="1" aria-valuemax="4" aria-valuetext="Step 4 of 4: Module Installation">
            <div class="step active" aria-label="Step 1: Database Configuration - Complete"></div>
            <div class="step active" aria-label="Step 2: Create Tables - Complete"></div>
            <div class="step active" aria-label="Step 3: Admin Account - Complete"></div>
            <div class="step active" aria-label="Step 4: Module Installation - Current"></div>
        </div>
        
        <?php if ($no_modules_available): ?>
            <!-- Empty module list state -->
            <div class="all-installed-message" role="status" aria-live="polite">
                <div class="icon" aria-hidden="true">📦</div>
                <h2>No Modules Available</h2>
                <p>No modules were found in the system. Please check your installation or contact support.</p>
                <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-primary">Go to Dashboard →</a>
            </div>
        <?php elseif ($no_uninstalled_modules): ?>
            <!-- All modules installed when accessed from settings -->
            <div class="all-installed-message" role="status" aria-live="polite">
                <div class="icon" aria-hidden="true">✅</div>
                <h2>All Modules Installed!</h2>
                <p>You have successfully installed all available modules. There are no additional modules to install.</p>
                <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-primary">Go to Dashboard →</a>
            </div>
        <?php elseif ($all_installed): ?>
            <!-- All modules installed state -->
            <div class="all-installed-message" role="status" aria-live="polite">
                <div class="icon" aria-hidden="true">✅</div>
                <h2>All Modules Installed!</h2>
                <p>You have successfully installed all available modules. You can now start using the system.</p>
                <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-primary">Go to Dashboard →</a>
            </div>
        <?php else: ?>
            <!-- Module selection interface -->
            <div class="alert alert-info" role="status" aria-live="polite">
                ℹ️ Select the modules you want to install. Dependencies will be automatically selected.
                <br><strong>Note:</strong> Employee Management and Product Catalog will always be installed as required base modules.
            </div>
            
            <div id="warningAlert" class="alert alert-warning" role="alert" aria-live="assertive" style="display: none;">
                ⚠️ <span id="warningMessage"></span>
            </div>
            
            <!-- Screen reader announcements -->
            <div id="srAnnouncements" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
            
            <?php foreach ($modules_by_category as $category => $modules): ?>
                <?php if (empty($modules)) continue; ?>
                
                <section class="category-section" aria-labelledby="category-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $category))); ?>">
                    <?php 
                    $category_info = get_category_info();
                    $cat_data = $category_info[$category] ?? ['icon' => '📦', 'description' => ''];
                    ?>
                    <div class="category-header">
                        <span class="category-icon" aria-hidden="true"><?php echo $cat_data['icon']; ?></span>
                        <h2 class="category-title" id="category-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $category))); ?>"><?php echo htmlspecialchars($category); ?></h2>
                        <span class="category-description"><?php echo htmlspecialchars($cat_data['description']); ?></span>
                    </div>
                    
                    <div class="modules-grid" role="group" aria-label="<?php echo htmlspecialchars($category); ?> modules">
                        <?php foreach ($modules as $module_name => $module): 
                            $is_mandatory = in_array($module_name, $mandatory_modules);
                            $card_classes = 'module-card';
                            if ($module['installed']) $card_classes .= ' installed';
                            if ($is_mandatory && !$module['installed']) $card_classes .= ' mandatory';
                        ?>
                            <div class="<?php echo $card_classes; ?>" 
                                 data-module="<?php echo htmlspecialchars($module_name); ?>"
                                 data-mandatory="<?php echo $is_mandatory ? 'true' : 'false'; ?>"
                                 role="article"
                                 aria-labelledby="module-name-<?php echo htmlspecialchars($module_name); ?>"
                                 aria-describedby="module-desc-<?php echo htmlspecialchars($module_name); ?>">
                                <div class="module-header">
                                    <span class="module-icon" aria-hidden="true"><?php echo $module['icon']; ?></span>
                                    <div class="module-info">
                                        <div class="module-name" id="module-name-<?php echo htmlspecialchars($module_name); ?>"><?php echo htmlspecialchars($module['display_name']); ?></div>
                                        <div class="module-description" id="module-desc-<?php echo htmlspecialchars($module_name); ?>"><?php echo htmlspecialchars($module['description']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($module['installed']): ?>
                                    <span class="module-status status-installed" role="status" aria-label="Module installed">✓ Installed</span>
                                    <input type="checkbox" 
                                           class="module-checkbox" 
                                           disabled 
                                           checked
                                           aria-label="<?php echo htmlspecialchars($module['display_name']); ?> - Already installed"
                                           aria-checked="true">
                                <?php else: ?>
                                    <?php if ($is_mandatory): ?>
                                        <span class="module-status status-mandatory" role="status" aria-label="Required module">⭐ Required</span>
                                    <?php endif; ?>
                                    <input type="checkbox" 
                                           class="module-checkbox" 
                                           name="modules[]" 
                                           value="<?php echo htmlspecialchars($module_name); ?>"
                                           data-module="<?php echo htmlspecialchars($module_name); ?>"
                                           <?php echo $is_mandatory ? 'data-mandatory="true"' : ''; ?>
                                           aria-label="Select <?php echo htmlspecialchars($module['display_name']); ?> for installation"
                                           aria-describedby="module-desc-<?php echo htmlspecialchars($module_name); ?>">
                                <?php endif; ?>
                                
                                <div class="module-actions">
                                    <button class="btn-info" 
                                            onclick="showModuleDetails('<?php echo htmlspecialchars($module_name); ?>')"
                                            aria-label="View details for <?php echo htmlspecialchars($module['display_name']); ?>">
                                        <span aria-hidden="true">ℹ️</span> Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            
            <div class="action-bar" role="region" aria-label="Installation actions">
                <div class="selection-info" role="status" aria-live="polite" aria-atomic="true">
                    <span class="selection-count" id="selectionCount">0</span> module(s) selected
                    <button type="button" class="btn-select-all" onclick="selectAllModules()" aria-label="Select all available modules">
                        Select All
                    </button>
                    <button type="button" class="btn-deselect-all" onclick="deselectAllModules()" aria-label="Deselect all modules except required">
                        Deselect All
                    </button>
                </div>
                <div class="action-buttons">
                    <a href="<?php echo APP_URL; ?>/setup/skip_module_installer.php" 
                       class="btn btn-secondary"
                       aria-label="Skip module installation and go to dashboard">
                        Skip for Now
                    </a>
                    <button id="installBtn" 
                            class="btn btn-primary" 
                            disabled 
                            onclick="startInstallation()"
                            aria-label="Install selected modules"
                            aria-disabled="true">
                        Install Selected Modules →
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Module Details Modal -->
    <div id="moduleModal" 
         class="modal-overlay" 
         onclick="closeModalOnOverlay(event)"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modalTitle"
         aria-describedby="modalDescription">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <span class="modal-icon" id="modalIcon" aria-hidden="true"></span>
                <div class="modal-title-section">
                    <h2 class="modal-title" id="modalTitle"></h2>
                    <div class="modal-category" id="modalCategory"></div>
                </div>
                <button class="modal-close" 
                        onclick="closeModal()"
                        aria-label="Close module details dialog">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <div class="modal-section-title">📝 Description</div>
                    <div class="modal-section-content" id="modalDescription"></div>
                </div>
                
                <div class="modal-section" id="dependenciesSection">
                    <div class="modal-section-title">🔗 Dependencies</div>
                    <div class="modal-section-content">
                        <div id="modalDependencies"></div>
                    </div>
                </div>
                
                <div class="modal-section" id="dependentsSection">
                    <div class="modal-section-title">⬅️ Required By</div>
                    <div class="modal-section-content">
                        <div id="modalDependents"></div>
                    </div>
                </div>
                
                <div class="modal-section">
                    <div class="modal-section-title">🗄️ Database Tables</div>
                    <div class="modal-section-content">
                        <div class="table-list" id="modalTables"></div>
                    </div>
                </div>
                
                <div class="modal-section" id="installStatusSection">
                    <div class="modal-section-title">📊 Installation Status</div>
                    <div class="modal-section-content" id="modalInstallStatus"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Module data from PHP - wrapped in try-catch to catch JSON errors
        let allModules = {};
        let moduleDependencies = {};
        let installedModules = [];
        let mandatoryModules = [];
        
        try {
            allModules = <?php echo json_encode($all_modules, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
            moduleDependencies = <?php echo json_encode($dependencies, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
            installedModules = <?php echo json_encode(array_keys(array_filter($all_modules, fn($m) => $m['installed'])), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
            mandatoryModules = <?php echo json_encode($mandatory_modules, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
            console.log('Module data loaded successfully');
        } catch (e) {
            console.error('Error parsing module data:', e);
        }
        
        // Track selected modules
        let selectedModules = new Set();
        
        // Debug logging
        console.log('Module Installer Initialized');
        console.log('All Modules:', allModules);
        console.log('Mandatory Modules:', mandatoryModules);
        
        // Define global functions first (before DOMContentLoaded)
        // Attach to window to ensure global scope
        window.selectAllModules = function() {
            console.log('selectAllModules called');
            const checkboxes = document.querySelectorAll('.module-checkbox:not(:disabled)');
            console.log('Found checkboxes to select:', checkboxes.length);
            
            checkboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    checkbox.checked = true;
                    const moduleName = checkbox.dataset.module;
                    selectedModules.add(moduleName);
                    checkbox.closest('.module-card').classList.add('selected');
                    checkbox.setAttribute('aria-checked', 'true');
                    console.log('Selected:', moduleName);
                }
            });
            updateSelectionCount();
        };
        
        window.deselectAllModules = function() {
            console.log('deselectAllModules called');
            const checkboxes = document.querySelectorAll('.module-checkbox:not(:disabled)');
            
            checkboxes.forEach(checkbox => {
                const moduleName = checkbox.dataset.module;
                const isMandatory = mandatoryModules.includes(moduleName);
                
                // Keep mandatory modules selected
                if (!isMandatory && checkbox.checked) {
                    // Check if other selected modules depend on this one
                    if (!hasSelectedDependents(moduleName)) {
                        checkbox.checked = false;
                        selectedModules.delete(moduleName);
                        checkbox.closest('.module-card').classList.remove('selected');
                        checkbox.setAttribute('aria-checked', 'false');
                        console.log('Deselected:', moduleName);
                    }
                }
            });
            updateSelectionCount();
        };
        
        window.updateSelectionCount = function() {
            const count = selectedModules.size;
            const countEl = document.getElementById('selectionCount');
            const installBtn = document.getElementById('installBtn');
            
            console.log('updateSelectionCount called, count:', count);
            
            if (countEl) {
                countEl.textContent = count;
            }
            
            if (installBtn) {
                const shouldDisable = count === 0;
                installBtn.disabled = shouldDisable;
                installBtn.setAttribute('aria-disabled', shouldDisable ? 'true' : 'false');
                console.log('Install button disabled:', shouldDisable);
            }
        };
        
        window.hasSelectedDependents = function(moduleName) {
            for (const selected of selectedModules) {
                if (selected === moduleName) continue;
                
                const deps = moduleDependencies[selected] || [];
                if (deps.includes(moduleName)) {
                    return true;
                }
            }
            return false;
        };
        
        window.autoSelectDependencies = function(moduleName) {
            const dependencies = moduleDependencies[moduleName] || [];
            const addedDeps = [];
            
            dependencies.forEach(dep => {
                // Skip if already installed
                if (installedModules.includes(dep)) {
                    return;
                }
                
                // Find and check the dependency checkbox
                const depCheckbox = document.querySelector(`input[data-module="${dep}"]`);
                if (depCheckbox && !depCheckbox.checked) {
                    depCheckbox.checked = true;
                    selectedModules.add(dep);
                    depCheckbox.closest('.module-card').classList.add('selected');
                    depCheckbox.setAttribute('aria-checked', 'true');
                    
                    const depModule = allModules[dep];
                    if (depModule) {
                        addedDeps.push(depModule.display_name);
                    }
                    
                    // Recursively select dependencies of this dependency
                    const nestedDeps = autoSelectDependencies(dep);
                    addedDeps.push(...nestedDeps);
                }
            });
            
            return addedDeps;
        };
        
        window.showWarning = function(message) {
            const alert = document.getElementById('warningAlert');
            const messageEl = document.getElementById('warningMessage');
            if (messageEl) {
                messageEl.textContent = message;
            }
            if (alert) {
                alert.style.display = 'block';
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 5000);
            }
        };
        
        window.announceToScreenReader = function(message) {
            const announcer = document.getElementById('srAnnouncements');
            if (announcer) {
                announcer.textContent = message;
                setTimeout(() => {
                    announcer.textContent = '';
                }, 1000);
            }
        };
        
        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            // Get all checkboxes
            const checkboxes = document.querySelectorAll('.module-checkbox:not(:disabled)');
            console.log('Found checkboxes:', checkboxes.length);
            
            // Pre-select mandatory modules that are not yet installed
            mandatoryModules.forEach(moduleName => {
                if (!installedModules.includes(moduleName)) {
                    const checkbox = document.querySelector(`input[data-module="${moduleName}"]`);
                    if (checkbox && !checkbox.disabled) {
                        checkbox.checked = true;
                        checkbox.closest('.module-card').classList.add('selected');
                        selectedModules.add(moduleName);
                        console.log('Pre-selected mandatory module:', moduleName);
                    }
                }
            });
            
            // Add event listeners to all checkboxes
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    const moduleName = this.dataset.module;
                    const isChecked = this.checked;
                    console.log('Checkbox clicked:', moduleName, 'Checked:', isChecked);
                    
                    const module = allModules[moduleName];
                    const displayName = module ? module.display_name : moduleName;
                    const isMandatory = mandatoryModules.includes(moduleName);
                    
                    if (isChecked) {
                        // Add module to selection
                        selectedModules.add(moduleName);
                        console.log('Added to selection:', moduleName);
                        
                        // Auto-select dependencies
                        const depsAdded = autoSelectDependencies(moduleName);
                        
                        // Update card styling
                        this.closest('.module-card').classList.add('selected');
                        this.setAttribute('aria-checked', 'true');
                    } else {
                        // Prevent deselecting mandatory modules
                        if (isMandatory) {
                            this.checked = true;
                            showWarning(`${displayName} is a required module and cannot be deselected.`);
                            return;
                        }
                        
                        // Check if other selected modules depend on this one
                        if (hasSelectedDependents(moduleName)) {
                            this.checked = true;
                            showWarning(`Cannot deselect ${displayName}. Other selected modules depend on it.`);
                            return;
                        }
                        
                        // Remove module from selection
                        selectedModules.delete(moduleName);
                        console.log('Removed from selection:', moduleName);
                        this.closest('.module-card').classList.remove('selected');
                        this.setAttribute('aria-checked', 'false');
                    }
                    
                    updateSelectionCount();
                    console.log('Current selection count:', selectedModules.size);
                });
            });
            
            // Add keyboard navigation for module cards
            document.querySelectorAll('.module-card').forEach(card => {
                card.setAttribute('tabindex', '0');
                card.addEventListener('keydown', handleCardKeydown);
            });
            
            // Add keyboard navigation for modal
            document.addEventListener('keydown', handleGlobalKeydown);
            
            // Update selection count
            updateSelectionCount();
            
            console.log('Initial selected modules:', Array.from(selectedModules));
            
            // Announce page load to screen readers
            announceToScreenReader('Module installer loaded. ' + Object.keys(allModules).length + ' modules available.');
        });
        
        // Keyboard navigation for module cards
        function handleCardKeydown(event) {
            const card = event.currentTarget;
            const checkbox = card.querySelector('.module-checkbox:not(:disabled)');
            
            // Space or Enter to toggle checkbox
            if ((event.key === ' ' || event.key === 'Enter') && checkbox) {
                event.preventDefault();
                checkbox.checked = !checkbox.checked;
                handleModuleSelection(checkbox);
            }
            
            // 'i' key to show details
            if (event.key === 'i' || event.key === 'I') {
                const moduleName = card.dataset.module;
                if (moduleName) {
                    showModuleDetails(moduleName);
                }
            }
        }
        
        // Global keyboard shortcuts
        function handleGlobalKeydown(event) {
            // Escape to close modal
            if (event.key === 'Escape') {
                const modal = document.getElementById('moduleModal');
                if (modal && modal.classList.contains('active')) {
                    closeModal();
                }
            }
        }
        
        function handleModuleSelection(checkbox) {
            const moduleName = checkbox.dataset.module;
            const isChecked = checkbox.checked;
            const module = allModules[moduleName];
            const displayName = module ? module.display_name : moduleName;
            const isMandatory = mandatoryModules.includes(moduleName);
            
            console.log('handleModuleSelection called for:', moduleName, 'checked:', isChecked);
            
            if (isChecked) {
                // Add module to selection
                selectedModules.add(moduleName);
                console.log('Added to selection:', moduleName);
                
                // Auto-select dependencies
                autoSelectDependencies(moduleName);
                
                // Update card styling
                checkbox.closest('.module-card').classList.add('selected');
                checkbox.setAttribute('aria-checked', 'true');
            } else {
                // Prevent deselecting mandatory modules
                if (isMandatory) {
                    checkbox.checked = true;
                    showWarning(`${displayName} is a required module and cannot be deselected.`);
                    return;
                }
                
                // Check if other selected modules depend on this one
                if (hasSelectedDependents(moduleName)) {
                    checkbox.checked = true;
                    showWarning(`Cannot deselect ${displayName}. Other selected modules depend on it.`);
                    return;
                }
                
                // Remove module from selection
                selectedModules.delete(moduleName);
                console.log('Removed from selection:', moduleName);
                checkbox.closest('.module-card').classList.remove('selected');
                checkbox.setAttribute('aria-checked', 'false');
            }
            
            updateSelectionCount();
            console.log('Current selection count:', selectedModules.size);
        }
        
        function showModuleDetails(moduleName) {
            const module = allModules[moduleName];
            
            if (!module) {
                console.error('Module not found:', moduleName);
                return;
            }
            
            // Store the element that triggered the modal for focus management
            window.lastFocusedElement = document.activeElement;
            
            // Populate modal with module data
            document.getElementById('modalIcon').textContent = module.icon;
            document.getElementById('modalTitle').textContent = module.display_name;
            document.getElementById('modalCategory').textContent = module.category + ' Module';
            document.getElementById('modalDescription').textContent = module.description;
            
            // Announce modal opening to screen readers
            announceToScreenReader('Module details dialog opened for ' + module.display_name);
            
            // Show dependencies
            const deps = moduleDependencies[moduleName] || [];
            const dependenciesSection = document.getElementById('dependenciesSection');
            const dependenciesContainer = document.getElementById('modalDependencies');
            
            if (deps.length > 0) {
                dependenciesSection.style.display = 'block';
                let depsHTML = '<ul class="dependency-list">';
                deps.forEach(dep => {
                    const depModule = allModules[dep];
                    const isInstalled = installedModules.includes(dep);
                    const installedClass = isInstalled ? 'installed' : '';
                    const statusText = isInstalled ? 'Installed' : 'Not Installed';
                    const statusClass = isInstalled ? 'installed' : '';
                    
                    depsHTML += `
                        <li class="dependency-item ${installedClass}">
                            <span class="dependency-icon">${depModule ? depModule.icon : '📦'}</span>
                            <span class="dependency-name">${depModule ? depModule.display_name : dep}</span>
                            <span class="dependency-status ${statusClass}">${statusText}</span>
                        </li>
                    `;
                });
                depsHTML += '</ul>';
                dependenciesContainer.innerHTML = depsHTML;
            } else {
                dependenciesSection.style.display = 'none';
            }
            
            // Show modules that depend on this one
            const dependents = [];
            for (const [mod, modDeps] of Object.entries(moduleDependencies)) {
                if (modDeps.includes(moduleName)) {
                    dependents.push(mod);
                }
            }
            
            const dependentsSection = document.getElementById('dependentsSection');
            const dependentsContainer = document.getElementById('modalDependents');
            
            if (dependents.length > 0) {
                dependentsSection.style.display = 'block';
                let depsHTML = '<ul class="dependency-list">';
                dependents.forEach(dep => {
                    const depModule = allModules[dep];
                    const isInstalled = installedModules.includes(dep);
                    const installedClass = isInstalled ? 'installed' : '';
                    const statusText = isInstalled ? 'Installed' : 'Not Installed';
                    const statusClass = isInstalled ? 'installed' : '';
                    
                    depsHTML += `
                        <li class="dependency-item ${installedClass}">
                            <span class="dependency-icon">${depModule ? depModule.icon : '📦'}</span>
                            <span class="dependency-name">${depModule ? depModule.display_name : dep}</span>
                            <span class="dependency-status ${statusClass}">${statusText}</span>
                        </li>
                    `;
                });
                depsHTML += '</ul>';
                dependentsContainer.innerHTML = depsHTML;
            } else {
                dependentsSection.style.display = 'none';
            }
            
            // Show tables
            const tablesContainer = document.getElementById('modalTables');
            let tablesHTML = '';
            module.tables.forEach(table => {
                tablesHTML += `<div class="table-item">${table}</div>`;
            });
            tablesContainer.innerHTML = tablesHTML;
            
            // Show installation status
            const installStatusContainer = document.getElementById('modalInstallStatus');
            if (module.installed) {
                installStatusContainer.innerHTML = `
                    <div class="info-box" style="background: #e8f5e9; border-color: #4caf50; color: #2e7d32;">
                        ✅ This module is already installed and ready to use.
                    </div>
                `;
            } else {
                if (deps.length > 0) {
                    const allDepsInstalled = deps.every(dep => installedModules.includes(dep));
                    if (allDepsInstalled) {
                        installStatusContainer.innerHTML = `
                            <div class="info-box" style="background: #e3f2fd; border-color: #2196f3; color: #0d47a1;">
                                ℹ️ This module is ready to install. All dependencies are satisfied.
                            </div>
                        `;
                    } else {
                        installStatusContainer.innerHTML = `
                            <div class="info-box">
                                ⚠️ This module requires dependencies to be installed first. They will be automatically selected when you choose this module.
                            </div>
                        `;
                    }
                } else {
                    installStatusContainer.innerHTML = `
                        <div class="info-box" style="background: #e3f2fd; border-color: #2196f3; color: #0d47a1;">
                            ℹ️ This module has no dependencies and is ready to install.
                        </div>
                    `;
                }
            }
            
            // Show modal
            const modal = document.getElementById('moduleModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Focus the close button for keyboard accessibility
            setTimeout(() => {
                const closeBtn = modal.querySelector('.modal-close');
                if (closeBtn) {
                    closeBtn.focus();
                }
            }, 100);
            
            // Trap focus within modal
            trapFocusInModal(modal);
        }
        
        function closeModal() {
            const modal = document.getElementById('moduleModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
            
            // Announce modal closing to screen readers
            announceToScreenReader('Module details dialog closed.');
            
            // Return focus to the element that opened the modal
            if (window.lastFocusedElement) {
                window.lastFocusedElement.focus();
                window.lastFocusedElement = null;
            }
        }
        
        // Trap focus within modal for accessibility
        function trapFocusInModal(modal) {
            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];
            
            modal.addEventListener('keydown', function(e) {
                if (e.key !== 'Tab') return;
                
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            });
        }
        
        function closeModalOnOverlay(event) {
            if (event.target.id === 'moduleModal') {
                closeModal();
            }
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        function startInstallation() {
            if (selectedModules.size === 0) {
                showWarning('Please select at least one module to install.');
                return;
            }
            
            // Show progress modal
            showProgressModal();
            
            // Get CSRF token
            const csrfToken = getCsrfToken();
            
            // Prepare installation request
            const modulesToInstall = Array.from(selectedModules);
            
            // Send installation request
            fetch('<?php echo APP_URL; ?>/setup/ajax_install_modules.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    modules: modulesToInstall,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Installation request failed');
                    });
                }
                return response.json();
            })
            .then(data => {
                // Installation completed
                handleInstallationComplete(data);
            })
            .catch(error => {
                // Installation error
                handleInstallationError(error);
            });
            
            // Start polling for progress updates
            startProgressPolling();
        }
        
        function getCsrfToken() {
            // Try to get from meta tag first
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content');
            }
            
            // Generate a simple token (in production, this should come from server)
            if (!window.csrfToken) {
                window.csrfToken = '<?php echo ensure_csrf_token(); ?>';
            }
            return window.csrfToken;
        }
        
        let progressPollInterval = null;
        
        function startProgressPolling() {
            // Poll every 500ms
            progressPollInterval = setInterval(pollInstallationProgress, 500);
        }
        
        function stopProgressPolling() {
            if (progressPollInterval) {
                clearInterval(progressPollInterval);
                progressPollInterval = null;
            }
        }
        
        function pollInstallationProgress() {
            fetch('<?php echo APP_URL; ?>/setup/ajax_installation_status.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateProgressDisplay(data);
                    
                    // Stop polling if installation is complete
                    if (!data.in_progress) {
                        stopProgressPolling();
                    }
                }
            })
            .catch(error => {
                console.error('Progress polling error:', error);
            });
        }
        
        function showProgressModal() {
            const modalHTML = `
                <div id="progressModal" class="modal-overlay active" role="dialog" aria-modal="true" aria-labelledby="progressModalTitle">
                    <div class="modal-content" style="max-width: 600px;">
                        <div class="modal-header" style="border-bottom: none; padding-bottom: 10px;">
                            <div class="modal-title-section" style="width: 100%;">
                                <h2 class="modal-title" id="progressModalTitle" style="text-align: center;">Installing Modules</h2>
                            </div>
                        </div>
                        <div class="modal-body">
                            <div style="margin-bottom: 20px;">
                                <div id="progressBarContainer" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Installation progress" style="background: #e1e8ed; border-radius: 10px; height: 20px; overflow: hidden;">
                                    <div id="progressBar" style="background: linear-gradient(135deg, #003581 0%, #004aad 100%); height: 100%; width: 0%; transition: width 0.3s;"></div>
                                </div>
                                <div id="progressText" role="status" aria-live="polite" style="text-align: center; margin-top: 10px; color: #6c757d; font-size: 14px;">
                                    Preparing installation...
                                </div>
                            </div>
                            
                            <div id="currentModuleSection" style="display: none; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-weight: 600; color: #003581; margin-bottom: 5px;">Currently Installing:</div>
                                <div id="currentModuleName" style="color: #495057;"></div>
                            </div>
                            
                            <div id="completedModulesSection" style="display: none;">
                                <div style="font-weight: 600; color: #003581; margin-bottom: 10px;">Completed Modules:</div>
                                <div id="completedModulesList" style="max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            
                            <div id="resultsSection" role="region" aria-label="Installation results" style="display: none;">
                                <div id="resultsContent" role="status" aria-live="polite"></div>
                                <div id="resultsActions" style="text-align: center; margin-top: 20px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                                    <button id="retryFailedBtn" onclick="retryFailedModules()" class="btn btn-secondary" style="display: none;" aria-label="Retry installation of failed modules">
                                        <span aria-hidden="true">🔄</span> Retry Failed Modules
                                    </button>
                                    <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-primary" aria-label="Go to dashboard">Go to Dashboard →</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            document.body.style.overflow = 'hidden';
        }
        
        function updateProgressDisplay(data) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const currentModuleSection = document.getElementById('currentModuleSection');
            const currentModuleName = document.getElementById('currentModuleName');
            const completedModulesSection = document.getElementById('completedModulesSection');
            const completedModulesList = document.getElementById('completedModulesList');
            
            if (!progressBar || !progressText) return;
            
            // Update progress bar
            progressBar.style.width = data.percentage + '%';
            progressBar.setAttribute('aria-valuenow', data.percentage);
            progressText.textContent = `${data.completed_count} of ${data.total} modules installed (${data.percentage}%)`;
            
            // Update current module
            if (data.current_module && data.in_progress) {
                currentModuleSection.style.display = 'block';
                const module = allModules[data.current_module];
                const displayName = module ? module.display_name : data.current_module;
                currentModuleName.textContent = displayName;
                
                // Announce progress to screen readers
                announceToScreenReader('Installing ' + displayName + '. ' + data.percentage + '% complete.');
            } else {
                currentModuleSection.style.display = 'none';
            }
            
            // Update completed modules list
            if (data.completed && data.completed.length > 0) {
                completedModulesSection.style.display = 'block';
                let completedHTML = '';
                data.completed.forEach(result => {
                    const module = allModules[result.module];
                    const icon = result.success ? '✅' : '❌';
                    const statusClass = result.success ? 'success' : 'error';
                    completedHTML += `
                        <div style="padding: 8px; margin-bottom: 5px; background: ${result.success ? '#e8f5e9' : '#ffebee'}; border-radius: 6px; display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;">${icon}</span>
                            <span style="flex: 1; color: #1b2a57; font-weight: 500;">${module ? module.display_name : result.module}</span>
                        </div>
                    `;
                });
                completedModulesList.innerHTML = completedHTML;
            }
        }
        
        // Store failed modules for retry functionality
        let failedModules = [];
        
        function handleInstallationComplete(data) {
            stopProgressPolling();
            
            const resultsSection = document.getElementById('resultsSection');
            const resultsContent = document.getElementById('resultsContent');
            const currentModuleSection = document.getElementById('currentModuleSection');
            const progressText = document.getElementById('progressText');
            
            if (!resultsSection || !resultsContent) return;
            
            // Hide current module section
            if (currentModuleSection) {
                currentModuleSection.style.display = 'none';
            }
            
            // Update progress text
            if (progressText) {
                progressText.textContent = 'Installation complete!';
            }
            
            // Count successes and failures
            const results = data.results || [];
            const successful = results.filter(r => r.success).length;
            const failed = results.filter(r => !r.success).length;
            
            // Store failed modules for retry
            failedModules = results.filter(r => !r.success).map(r => r.module);
            
            // Announce completion to screen readers
            if (failed === 0) {
                announceToScreenReader('Installation complete! All ' + successful + ' modules installed successfully.');
            } else {
                announceToScreenReader('Installation complete with errors. ' + successful + ' modules succeeded, ' + failed + ' modules failed.');
            }
            
            let resultsHTML = '';
            
            if (failed === 0) {
                // All successful
                resultsHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 64px; margin-bottom: 15px;">✅</div>
                        <h2 style="color: #28a745; font-size: 24px; margin-bottom: 10px;">Installation Successful!</h2>
                        <p style="color: #6c757d; font-size: 14px;">All ${successful} module(s) have been installed successfully.</p>
                    </div>
                `;
            } else {
                // Mixed results
                resultsHTML = `
                    <div style="padding: 20px;">
                        <h3 style="color: #003581; margin-bottom: 15px;">Installation Summary</h3>
                        <div style="margin-bottom: 15px;">
                            <span style="color: #28a745; font-weight: 600;">✓ ${successful} successful</span>
                            <span style="margin: 0 10px;">|</span>
                            <span style="color: #dc3545; font-weight: 600;">✗ ${failed} failed</span>
                        </div>
                `;
                
                // Show successful modules if any
                if (successful > 0) {
                    resultsHTML += '<div style="margin-top: 15px; margin-bottom: 20px;"><strong style="color: #28a745;">✓ Successfully Installed:</strong></div>';
                    results.forEach(result => {
                        if (result.success) {
                            const module = allModules[result.module];
                            resultsHTML += `
                                <div style="padding: 10px; margin-top: 8px; background: #e8f5e9; border-left: 3px solid #4caf50; border-radius: 4px;">
                                    <div style="font-weight: 600; color: #1b2a57;">${module ? module.display_name : result.module}</div>
                                    <div style="font-size: 13px; color: #2e7d32; margin-top: 4px;">${result.message || 'Installation successful'}</div>
                                </div>
                            `;
                        }
                    });
                }
                
                // Show failed modules
                if (failed > 0) {
                    resultsHTML += '<div style="margin-top: 20px;"><strong style="color: #dc3545;">✗ Failed Modules:</strong></div>';
                    results.forEach(result => {
                        if (!result.success) {
                            const module = allModules[result.module];
                            resultsHTML += `
                                <div style="padding: 10px; margin-top: 8px; background: #ffebee; border-left: 3px solid #dc3545; border-radius: 4px;">
                                    <div style="font-weight: 600; color: #1b2a57;">${module ? module.display_name : result.module}</div>
                                    <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">${result.message || 'Installation failed'}</div>
                                </div>
                            `;
                        }
                    });
                }
                
                resultsHTML += '</div>';
            }
            
            resultsContent.innerHTML = resultsHTML;
            resultsSection.style.display = 'block';
            
            // Update action buttons
            updateResultsActionButtons(failed > 0);
        }
        
        function handleInstallationError(error) {
            stopProgressPolling();
            
            const resultsSection = document.getElementById('resultsSection');
            const resultsContent = document.getElementById('resultsContent');
            const currentModuleSection = document.getElementById('currentModuleSection');
            
            if (!resultsSection || !resultsContent) return;
            
            // Hide current module section
            if (currentModuleSection) {
                currentModuleSection.style.display = 'none';
            }
            
            resultsContent.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 64px; margin-bottom: 15px;">❌</div>
                    <h2 style="color: #dc3545; font-size: 24px; margin-bottom: 10px;">Installation Failed</h2>
                    <p style="color: #6c757d; font-size: 14px;">${error.message || 'An error occurred during installation.'}</p>
                </div>
            `;
            resultsSection.style.display = 'block';
        }
        
        function updateResultsActionButtons(hasFailed) {
            const retryBtn = document.getElementById('retryFailedBtn');
            
            if (retryBtn) {
                if (hasFailed && failedModules.length > 0) {
                    retryBtn.style.display = 'inline-block';
                } else {
                    retryBtn.style.display = 'none';
                }
            }
        }
        
        function retryFailedModules() {
            if (failedModules.length === 0) {
                showWarning('No failed modules to retry.');
                return;
            }
            
            // Reset the progress modal
            const resultsSection = document.getElementById('resultsSection');
            const currentModuleSection = document.getElementById('currentModuleSection');
            const completedModulesSection = document.getElementById('completedModulesSection');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            if (resultsSection) resultsSection.style.display = 'none';
            if (currentModuleSection) currentModuleSection.style.display = 'none';
            if (completedModulesSection) completedModulesSection.style.display = 'none';
            if (progressBar) progressBar.style.width = '0%';
            if (progressText) progressText.textContent = 'Retrying failed modules...';
            
            // Get CSRF token
            const csrfToken = getCsrfToken();
            
            // Send retry request
            fetch('<?php echo APP_URL; ?>/setup/ajax_install_modules.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    modules: failedModules,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Retry request failed');
                    });
                }
                return response.json();
            })
            .then(data => {
                // Retry completed
                handleInstallationComplete(data);
            })
            .catch(error => {
                // Retry error
                handleInstallationError(error);
            });
            
            // Start polling for progress updates
            startProgressPolling();
        }
        
        function closeProgressModal() {
            const modal = document.getElementById('progressModal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = '';
                
                // Reload page to update installation status
                location.reload();
            }
        }
    </script>
</body>
</html>
