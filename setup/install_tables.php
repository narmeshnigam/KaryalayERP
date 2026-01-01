<?php
/**
 * Setup Wizard - Install All Tables
 * Step 4: Install all required database tables for the application
 * This page shows after admin account creation and allows installing all module tables
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token if not exists
 */
function ensure_csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: create_admin.php');
    exit;
}

$status = getSetupStatus();

// Allow forcing the page to show with ?force=1 (for re-running setup)
$force_show = isset($_GET['force']) && $_GET['force'] === '1';

// If setup is already complete and not forcing, redirect to dashboard
if ($status['setup_complete'] && !$force_show) {
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// If forcing, reset the module installer complete flags
if ($force_show) {
    // Clear session flag
    if (isset($_SESSION['module_installer_complete'])) {
        unset($_SESSION['module_installer_complete']);
    }
    
    // Clear database flag if system_settings table exists
    $reset_conn = createConnection(true);
    if ($reset_conn) {
        $reset_conn->query("DELETE FROM system_settings WHERE setting_key = 'module_installer_complete'");
        closeConnection($reset_conn);
    }
    
    // Remove marker file if exists
    $marker_file = __DIR__ . '/../.module_installer_complete';
    if (file_exists($marker_file)) {
        @unlink($marker_file);
    }
    
    // Re-check status after reset
    $status = getSetupStatus();
}

// Define all available modules with their setup functions
$available_modules = [
    'employees' => [
        'name' => 'Employee Management',
        'description' => 'Manage employee records, departments, and designations',
        'icon' => '👥',
        'category' => 'Core',
        'script' => 'scripts/setup_employees_table.php',
        'function' => 'setup_employees_module',
        'tables' => ['employees', 'departments', 'designations'],
        'dependencies' => []
    ],
    'clients' => [
        'name' => 'Client Management',
        'description' => 'Manage client information, addresses, and contacts',
        'icon' => '🏢',
        'category' => 'Core',
        'script' => 'scripts/setup_clients_tables.php',
        'function' => 'setup_clients_module',
        'tables' => ['clients', 'client_addresses', 'client_contacts_map'],
        'dependencies' => []
    ],
    'contacts' => [
        'name' => 'Contact Management',
        'description' => 'Manage business contacts and contact groups',
        'icon' => '📇',
        'category' => 'CRM',
        'script' => 'scripts/setup_contacts_tables.php',
        'function' => 'setup_contacts_module',
        'tables' => ['contacts', 'contact_groups', 'contact_group_members'],
        'dependencies' => []
    ],
    'attendance' => [
        'name' => 'Attendance Management',
        'description' => 'Track employee attendance and leave requests',
        'icon' => '📅',
        'category' => 'HR',
        'script' => 'scripts/setup_attendance_table.php',
        'function' => 'setupAttendanceModule',
        'tables' => ['attendance', 'leave_types', 'holidays'],
        'dependencies' => ['employees']
    ],
    'salary' => [
        'name' => 'Salary Records',
        'description' => 'Manage employee salary information',
        'icon' => '💰',
        'category' => 'HR',
        'script' => 'scripts/setup_salary_records_table.php',
        'function' => 'salary_setup_create',
        'tables' => ['salary_records'],
        'dependencies' => ['employees']
    ],
    'payroll' => [
        'name' => 'Payroll Management',
        'description' => 'Process payroll and generate salary slips',
        'icon' => '💵',
        'category' => 'HR',
        'script' => 'scripts/setup_payroll_tables.php',
        'function' => 'setup_payroll_module',
        'tables' => ['payroll_runs', 'payroll_items'],
        'dependencies' => ['employees', 'salary']
    ],
    'reimbursements' => [
        'name' => 'Reimbursements',
        'description' => 'Manage employee reimbursement requests',
        'icon' => '🧾',
        'category' => 'HR',
        'script' => 'scripts/setup_reimbursements_table.php',
        'function' => 'setupReimbursementModule',
        'tables' => ['reimbursements'],
        'dependencies' => ['employees']
    ],
    'documents' => [
        'name' => 'Document Management',
        'description' => 'Store and manage company documents',
        'icon' => '📄',
        'category' => 'Other',
        'script' => 'scripts/setup_documents_table.php',
        'function' => 'setup_document_vault',
        'tables' => ['documents'],
        'dependencies' => ['employees']
    ],
    'visitors' => [
        'name' => 'Visitor Logs',
        'description' => 'Track visitor check-ins and check-outs',
        'icon' => '🚪',
        'category' => 'Other',
        'script' => 'scripts/setup_visitor_logs_table.php',
        'function' => 'setupVisitorLogModule',
        'tables' => ['visitor_logs'],
        'dependencies' => ['employees']
    ],
    'crm' => [
        'name' => 'CRM',
        'description' => 'Customer Relationship Management - leads, tasks, calls, meetings',
        'icon' => '📊',
        'category' => 'CRM',
        'script' => 'scripts/setup_crm_tables.php',
        'function' => 'crm_setup_create',
        'tables' => ['crm_leads', 'crm_tasks', 'crm_calls', 'crm_meetings', 'crm_visits'],
        'dependencies' => ['employees']
    ],
    'branding' => [
        'name' => 'Branding Settings',
        'description' => 'Customize company branding and logos',
        'icon' => '🎨',
        'category' => 'Other',
        'script' => 'scripts/setup_branding_table.php',
        'function' => 'branding_setup_create',
        'tables' => ['branding_settings'],
        'dependencies' => []
    ],
    'projects' => [
        'name' => 'Projects Management',
        'description' => 'Manage projects, tasks, and team members',
        'icon' => '📋',
        'category' => 'Operations',
        'script' => 'scripts/setup_projects_tables.php',
        'function' => 'setup_projects_module',
        'tables' => ['projects', 'project_members', 'project_tasks', 'project_phases'],
        'dependencies' => ['clients']
    ],
    'workorders' => [
        'name' => 'Work Orders',
        'description' => 'Create and track work orders',
        'icon' => '🔧',
        'category' => 'Operations',
        'script' => 'scripts/setup_workorders_tables.php',
        'function' => 'setup_workorders_module',
        'tables' => ['work_orders', 'work_order_files', 'work_order_activity_log'],
        'dependencies' => ['employees', 'clients']
    ],
    'invoices' => [
        'name' => 'Invoices',
        'description' => 'Create and manage invoices',
        'icon' => '🧾',
        'category' => 'Finance',
        'script' => 'scripts/setup_invoices_tables.php',
        'function' => 'invoices_setup_create',
        'tables' => ['invoices', 'invoice_items'],
        'dependencies' => ['clients']
    ],
    'quotations' => [
        'name' => 'Quotations',
        'description' => 'Create and manage quotations',
        'icon' => '📝',
        'category' => 'Finance',
        'script' => 'scripts/setup_quotations_tables.php',
        'function' => 'quotations_setup_create',
        'tables' => ['quotations', 'quotation_items'],
        'dependencies' => ['clients']
    ],
    'payments' => [
        'name' => 'Payments',
        'description' => 'Track payments and allocations',
        'icon' => '💳',
        'category' => 'Finance',
        'script' => 'scripts/setup_payments_tables.php',
        'function' => 'payments_setup_create',
        'tables' => ['payments', 'payment_allocations'],
        'dependencies' => ['clients', 'invoices']
    ],
    'catalog' => [
        'name' => 'Product Catalog',
        'description' => 'Manage products and inventory',
        'icon' => '📦',
        'category' => 'Finance',
        'script' => 'scripts/setup_catalog_tables.php',
        'function' => 'setup_catalog_tables',
        'tables' => ['items_master', 'item_categories', 'stock_transactions'],
        'dependencies' => []
    ],
    'expenses' => [
        'name' => 'Office Expenses',
        'description' => 'Track office expenses and bills',
        'icon' => '💸',
        'category' => 'Finance',
        'script' => 'scripts/setup_office_expenses_table.php',
        'function' => 'setupExpenseTracker',
        'tables' => ['office_expenses'],
        'dependencies' => ['employees']
    ],
    'deliverables' => [
        'name' => 'Deliverables',
        'description' => 'Manage project deliverables',
        'icon' => '📤',
        'category' => 'Operations',
        'script' => 'scripts/setup_deliverables_tables.php',
        'function' => 'setup_deliverables_module',
        'tables' => ['deliverables', 'deliverable_revisions'],
        'dependencies' => ['projects']
    ],
    'delivery' => [
        'name' => 'Delivery Management',
        'description' => 'Track deliveries and proof of delivery',
        'icon' => '🚚',
        'category' => 'Operations',
        'script' => 'scripts/setup_delivery_tables.php',
        'function' => 'setup_delivery_module',
        'tables' => ['deliveries', 'delivery_items'],
        'dependencies' => ['clients']
    ],
    'notebook' => [
        'name' => 'Notebook',
        'description' => 'Personal and shared notes',
        'icon' => '📓',
        'category' => 'Other',
        'script' => 'scripts/setup_notebook_tables.php',
        'function' => 'setup_notebook_module',
        'tables' => ['notes', 'note_versions', 'note_shares'],
        'dependencies' => []
    ],
    'assets' => [
        'name' => 'Asset Management',
        'description' => 'Track company assets and assignments',
        'icon' => '🖥️',
        'category' => 'Other',
        'script' => 'scripts/setup_assets_tables.php',
        'function' => 'setup_assets_module',
        'tables' => ['assets', 'asset_assignments'],
        'dependencies' => ['employees']
    ],
    'data-transfer' => [
        'name' => 'Data Transfer',
        'description' => 'Import and export data',
        'icon' => '🔄',
        'category' => 'Other',
        'script' => 'scripts/setup_data_transfer_tables.php',
        'function' => 'setup_data_transfer_module',
        'tables' => ['import_logs', 'export_logs'],
        'dependencies' => []
    ]
];

// Check which modules are already installed
function check_table_exists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function check_module_installed($conn, $tables) {
    foreach ($tables as $table) {
        if (!check_table_exists($conn, $table)) {
            return false;
        }
    }
    return true;
}

// Get database connection
$conn = createConnection(true);
$installation_status = [];

if ($conn) {
    foreach ($available_modules as $key => $module) {
        $installation_status[$key] = check_module_installed($conn, $module['tables']);
    }
}

// Group modules by category
$categories = [
    'Core' => [],
    'HR' => [],
    'Finance' => [],
    'Operations' => [],
    'CRM' => [],
    'Other' => []
];

// Define required modules that should be auto-selected and locked (only if not installed)
$required_modules = ['employees', 'clients', 'catalog'];

// Filter to only include required modules that are NOT installed
$required_modules_to_install = array_filter($required_modules, function($key) use ($installation_status) {
    return !($installation_status[$key] ?? false);
});

foreach ($available_modules as $key => $module) {
    $module['key'] = $key;
    $module['installed'] = $installation_status[$key] ?? false;
    // Only mark as required if it's in the required list AND not installed
    $module['required'] = in_array($key, $required_modules) && !$module['installed'];
    $categories[$module['category']][] = $module;
}

$page_title = 'Install Database Tables - Setup';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="csrf-token" content="<?php echo ensure_csrf_token(); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            min-height: 100vh;
            padding: 20px;
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
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 1000px;
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
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert-info {
            background: #e7f3ff;
            color: #0c5460;
            border: 1px solid #b3d9ff;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        .category-section {
            margin-bottom: 32px;
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
            font-size: 18px;
            font-weight: 600;
        }
        .category-select-all {
            margin-left: auto;
            font-size: 13px;
            color: #003581;
            cursor: pointer;
            text-decoration: underline;
        }
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .module-card {
            background: #f8f9fa;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 16px;
            transition: all 0.3s;
            position: relative;
            cursor: pointer;
        }
        .module-card:hover {
            border-color: #003581;
            box-shadow: 0 4px 12px rgba(0, 53, 129, 0.1);
        }
        .module-card.installed {
            background: #e8f5e9;
            border-color: #4caf50;
            cursor: default;
        }
        .module-card.selected {
            background: #e3f2fd;
            border-color: #003581;
        }
        .module-card.selected[data-required="true"],
        .module-card.required {
            background: #fff8e1;
            border-color: #ffa000;
            cursor: not-allowed;
        }
        .module-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .module-icon {
            font-size: 28px;
            flex-shrink: 0;
        }
        .module-info {
            flex: 1;
        }
        .module-name {
            color: #1b2a57;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .module-description {
            color: #6c757d;
            font-size: 12px;
            line-height: 1.4;
        }
        .module-checkbox {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .module-checkbox:disabled {
            cursor: not-allowed;
        }
        .module-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }
        .status-installed {
            background: #4caf50;
            color: white;
        }
        .status-pending {
            background: #ffa000;
            color: white;
        }
        .status-required {
            background: #ffa000;
            color: white;
        }
        .action-bar {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px 0;
            border-top: 2px solid #e1e8ed;
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .selection-info {
            color: #6c757d;
            font-size: 14px;
        }
        .selection-count {
            color: #003581;
            font-weight: 600;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            transform: none;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        /* Modal styles */
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
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid #e1e8ed;
            text-align: center;
        }
        .modal-title {
            color: #003581;
            font-size: 22px;
            font-weight: 600;
        }
        .modal-body {
            padding: 24px;
        }
        .progress-container {
            margin-bottom: 20px;
        }
        .progress-bar-bg {
            background: #e1e8ed;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            border-radius: 10px;
        }
        .progress-text {
            text-align: center;
            margin-top: 10px;
            color: #6c757d;
            font-size: 14px;
        }
        .current-module {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            display: none;
        }
        .current-module.active {
            display: block;
        }
        .current-module-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .current-module-name {
            font-size: 16px;
            font-weight: 600;
            color: #003581;
        }
        .installation-log {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
        }
        .log-entry {
            padding: 6px 0;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .log-success {
            color: #28a745;
        }
        .log-error {
            color: #dc3545;
        }
        .log-pending {
            color: #6c757d;
        }
        .results-section {
            display: none;
            text-align: center;
            padding: 20px 0;
        }
        .results-section.active {
            display: block;
        }
        .results-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        .results-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .results-message {
            color: #6c757d;
            margin-bottom: 20px;
        }
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
        @media (max-width: 768px) {
            .setup-container {
                padding: 20px;
                margin: 50px 10px 20px;
            }
            .modules-grid {
                grid-template-columns: 1fr;
            }
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="logo-top">
        <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>">
    </div>
    
    <div class="setup-container">
        <h1>🗄️ Install Database Tables</h1>
        <p class="subtitle">Step 4 of 4 - Select and install the modules you need</p>
        
        <div class="progress-steps">
            <div class="step active"></div>
            <div class="step active"></div>
            <div class="step active"></div>
            <div class="step active"></div>
        </div>
        
        <?php
        // Check if all modules are already installed
        $all_installed = !empty($installation_status) && !in_array(false, $installation_status, true);
        $uninstalled_count = count(array_filter($installation_status, function($v) { return !$v; }));
        
        if ($all_installed):
        ?>
        <div class="alert alert-success" style="text-align: center; padding: 40px;">
            <div style="font-size: 64px; margin-bottom: 16px;">✅</div>
            <h2 style="color: #155724; margin-bottom: 12px;">All Modules Already Installed!</h2>
            <p style="margin-bottom: 24px;">All available modules have been set up. Your system is ready to use.</p>
            <button type="button" class="btn btn-success" style="padding: 14px 40px; font-size: 16px;" onclick="skipAndComplete()">
                Go to Dashboard →
            </button>
        </div>
        <?php else: ?>
        
        <div class="alert alert-info">
            <strong>👋 Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin'); ?>!</strong><br>
            Your administrator account has been created. Now let's set up the database tables for the modules you want to use.
            Select the modules below and click "Install Selected Modules" to proceed.
            <?php if ($uninstalled_count > 0): ?>
            <br><small style="opacity: 0.8;"><?php echo $uninstalled_count; ?> module(s) available for installation.</small>
            <?php endif; ?>
            <br><br>
            <strong>Note:</strong> You can skip this step and install modules later from the admin panel. Click "Mark Complete & Go to Dashboard" to proceed.
        </div>

        <?php
        $category_icons = [
            'Core' => '⚙️',
            'HR' => '👔',
            'Finance' => '💰',
            'Operations' => '🔧',
            'CRM' => '📊',
            'Other' => '📦'
        ];
        
        foreach ($categories as $category => $modules):
            if (empty($modules)) continue;
        ?>
        <div class="category-section">
            <div class="category-header">
                <span class="category-icon"><?php echo $category_icons[$category] ?? '📦'; ?></span>
                <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                <span class="category-select-all" onclick="selectCategory('<?php echo htmlspecialchars($category); ?>')">Select All</span>
            </div>
            
            <div class="modules-grid">
                <?php foreach ($modules as $module): ?>
                <div class="module-card <?php echo $module['installed'] ? 'installed' : ''; ?> <?php echo ($module['required'] ?? false) && !$module['installed'] ? 'required' : ''; ?>" 
                     data-module="<?php echo htmlspecialchars($module['key']); ?>"
                     data-category="<?php echo htmlspecialchars($category); ?>"
                     data-dependencies="<?php echo htmlspecialchars(json_encode($module['dependencies'])); ?>"
                     <?php if (($module['required'] ?? false) && !$module['installed']): ?>data-required="true"<?php endif; ?>
                     onclick="toggleModule(this)">
                    <div class="module-header">
                        <span class="module-icon"><?php echo $module['icon']; ?></span>
                        <div class="module-info">
                            <div class="module-name"><?php echo htmlspecialchars($module['name']); ?></div>
                            <div class="module-description"><?php echo htmlspecialchars($module['description']); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($module['installed']): ?>
                        <span class="module-status status-installed">✓ Installed</span>
                        <input type="checkbox" class="module-checkbox" disabled checked>
                    <?php elseif ($module['required'] ?? false): ?>
                        <span class="module-status status-pending">Required</span>
                        <input type="checkbox" class="module-checkbox module-required" 
                               name="modules[]" 
                               value="<?php echo htmlspecialchars($module['key']); ?>"
                               checked disabled
                               data-required="true">
                    <?php else: ?>
                        <input type="checkbox" class="module-checkbox" 
                               name="modules[]" 
                               value="<?php echo htmlspecialchars($module['key']); ?>"
                               onclick="event.stopPropagation()">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="action-bar">
            <div class="selection-info">
                <span class="selection-count" id="selectionCount">0</span> module(s) selected for installation
            </div>
            <div class="action-buttons">
                <button type="button" class="btn btn-secondary" onclick="selectAllModules()">
                    Select All Uninstalled
                </button>
                <button type="button" id="installBtn" class="btn btn-primary" disabled onclick="startInstallation()">
                    Install Selected Modules →
                </button>
                <button type="button" class="btn btn-success" onclick="skipAndComplete()">
                    Mark Complete & Go to Dashboard →
                </button>
            </div>
        </div>
        <?php endif; // End of else block for all_installed check ?>
    </div>
    
    <!-- Installation Progress Modal -->
    <div id="installModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Installing Modules</h2>
            </div>
            <div class="modal-body">
                <div id="progressSection">
                    <div class="progress-container">
                        <div class="progress-bar-bg">
                            <div class="progress-bar" id="progressBar"></div>
                        </div>
                        <div class="progress-text" id="progressText">Preparing installation...</div>
                    </div>
                    
                    <div class="current-module" id="currentModule">
                        <div class="current-module-label">Currently Installing:</div>
                        <div class="current-module-name" id="currentModuleName"></div>
                    </div>
                    
                    <div class="installation-log" id="installationLog"></div>
                </div>
                
                <div class="results-section" id="resultsSection">
                    <div class="results-icon" id="resultsIcon">✅</div>
                    <div class="results-title" id="resultsTitle">Installation Complete!</div>
                    <div class="results-message" id="resultsMessage">All selected modules have been installed successfully.</div>
                    <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-primary">
                        Go to Dashboard →
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const moduleData = <?php echo json_encode($available_modules); ?>;
        const installedModules = <?php echo json_encode(array_keys(array_filter($installation_status))); ?>;
        const requiredModules = <?php echo json_encode(array_values($required_modules_to_install)); ?>;
        let selectedModules = new Set();
        
        function toggleModule(card) {
            const checkbox = card.querySelector('.module-checkbox');
            if (checkbox.disabled) return;
            
            checkbox.checked = !checkbox.checked;
            updateModuleSelection(card, checkbox.checked);
        }
        
        function updateModuleSelection(card, isSelected) {
            const moduleName = card.dataset.module;
            const dependencies = JSON.parse(card.dataset.dependencies || '[]');
            
            if (isSelected) {
                selectedModules.add(moduleName);
                card.classList.add('selected');
                
                // Auto-select dependencies
                dependencies.forEach(dep => {
                    if (!installedModules.includes(dep) && !selectedModules.has(dep)) {
                        const depCard = document.querySelector(`[data-module="${dep}"]`);
                        if (depCard) {
                            const depCheckbox = depCard.querySelector('.module-checkbox');
                            if (depCheckbox && !depCheckbox.disabled) {
                                depCheckbox.checked = true;
                                depCard.classList.add('selected');
                                selectedModules.add(dep);
                            }
                        }
                    }
                });
            } else {
                // Check if any selected module depends on this one
                let canDeselect = true;
                selectedModules.forEach(selected => {
                    if (selected !== moduleName) {
                        const deps = moduleData[selected]?.dependencies || [];
                        if (deps.includes(moduleName)) {
                            canDeselect = false;
                        }
                    }
                });
                
                if (canDeselect) {
                    selectedModules.delete(moduleName);
                    card.classList.remove('selected');
                } else {
                    // Re-check the checkbox
                    const checkbox = card.querySelector('.module-checkbox');
                    checkbox.checked = true;
                    alert(`Cannot deselect ${moduleData[moduleName]?.name || moduleName}. Other selected modules depend on it.`);
                }
            }
            
            updateSelectionCount();
        }
        
        function selectCategory(category) {
            document.querySelectorAll(`[data-category="${category}"]`).forEach(card => {
                const checkbox = card.querySelector('.module-checkbox');
                if (!checkbox.disabled && !checkbox.checked) {
                    checkbox.checked = true;
                    updateModuleSelection(card, true);
                }
            });
        }
        
        function selectAllModules() {
            document.querySelectorAll('.module-card:not(.installed)').forEach(card => {
                const checkbox = card.querySelector('.module-checkbox');
                if (!checkbox.disabled && !checkbox.checked) {
                    checkbox.checked = true;
                    updateModuleSelection(card, true);
                }
            });
        }
        
        function updateSelectionCount() {
            const count = selectedModules.size;
            document.getElementById('selectionCount').textContent = count;
            document.getElementById('installBtn').disabled = count === 0;
        }
        
        async function startInstallation() {
            if (selectedModules.size === 0) {
                alert('Please select at least one module to install.');
                return;
            }
            
            // Show modal
            document.getElementById('installModal').classList.add('active');
            document.getElementById('progressSection').style.display = 'block';
            document.getElementById('resultsSection').classList.remove('active');
            document.getElementById('currentModule').classList.add('active');
            
            const modulesToInstall = Array.from(selectedModules);
            const totalModules = modulesToInstall.length;
            let completedModules = 0;
            const log = document.getElementById('installationLog');
            log.innerHTML = '';
            
            // Sort modules by dependencies (simple topological sort)
            const sortedModules = sortByDependencies(modulesToInstall);
            
            for (const moduleName of sortedModules) {
                const module = moduleData[moduleName];
                
                // Update current module display
                document.getElementById('currentModuleName').textContent = module?.name || moduleName;
                
                // Add pending log entry
                const logEntry = document.createElement('div');
                logEntry.className = 'log-entry log-pending';
                logEntry.id = `log-${moduleName}`;
                logEntry.innerHTML = `<span>⏳</span> <span>Installing ${module?.name || moduleName}...</span>`;
                log.appendChild(logEntry);
                log.scrollTop = log.scrollHeight;
                
                try {
                    const result = await installModule(moduleName);
                    
                    if (result.success) {
                        logEntry.className = 'log-entry log-success';
                        logEntry.innerHTML = `<span>✅</span> <span>${module?.name || moduleName} - Installed successfully</span>`;
                    } else {
                        logEntry.className = 'log-entry log-error';
                        logEntry.innerHTML = `<span>❌</span> <span>${module?.name || moduleName} - ${result.message || 'Failed'}</span>`;
                    }
                } catch (error) {
                    logEntry.className = 'log-entry log-error';
                    logEntry.innerHTML = `<span>❌</span> <span>${module?.name || moduleName} - Error: ${error.message}</span>`;
                }
                
                completedModules++;
                const percentage = Math.round((completedModules / totalModules) * 100);
                document.getElementById('progressBar').style.width = percentage + '%';
                document.getElementById('progressText').textContent = `${completedModules} of ${totalModules} modules installed (${percentage}%)`;
            }
            
            // Mark setup as complete
            await markSetupComplete();
            
            // Show results
            document.getElementById('currentModule').classList.remove('active');
            document.getElementById('resultsSection').classList.add('active');
            document.getElementById('modalTitle').textContent = 'Installation Complete';
        }
        
        function sortByDependencies(modules) {
            const sorted = [];
            const visited = new Set();
            
            function visit(moduleName) {
                if (visited.has(moduleName)) return;
                visited.add(moduleName);
                
                const deps = moduleData[moduleName]?.dependencies || [];
                deps.forEach(dep => {
                    if (modules.includes(dep)) {
                        visit(dep);
                    }
                });
                
                sorted.push(moduleName);
            }
            
            modules.forEach(visit);
            return sorted;
        }
        
        async function installModule(moduleName) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            const response = await fetch('<?php echo APP_URL; ?>/setup/ajax_install_single_module.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    module: moduleName,
                    csrf_token: csrfToken
                })
            });
            
            return await response.json();
        }
        
        async function markSetupComplete() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            try {
                await fetch('<?php echo APP_URL; ?>/setup/ajax_mark_complete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ csrf_token: csrfToken })
                });
            } catch (e) {
                console.error('Failed to mark setup complete:', e);
            }
        }
        
        async function skipAndComplete() {
            // Mark setup as complete before redirecting
            await markSetupComplete();
            // Redirect to dashboard
            window.location.href = '<?php echo APP_URL; ?>/public/index.php';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-select required modules (Core + Catalog)
            requiredModules.forEach(moduleName => {
                if (!installedModules.includes(moduleName)) {
                    const card = document.querySelector(`[data-module="${moduleName}"]`);
                    if (card) {
                        card.classList.add('selected');
                        selectedModules.add(moduleName);
                    }
                }
            });
            
            // Add click handlers to checkboxes
            document.querySelectorAll('.module-checkbox:not(:disabled)').forEach(checkbox => {
                checkbox.addEventListener('change', function(e) {
                    const card = this.closest('.module-card');
                    updateModuleSelection(card, this.checked);
                });
            });
            
            updateSelectionCount();
        });
    </script>
</body>
</html>
<?php
if ($conn) {
    closeConnection($conn);
}
?>
