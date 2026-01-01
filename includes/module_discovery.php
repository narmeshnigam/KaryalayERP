<?php
/**
 * Module Discovery Service
 * Scans the system to identify available modules and their metadata
 */

/**
 * Module metadata registry
 * Defines all available modules with their properties
 */
function get_module_registry(): array {
    return [
        'employees' => [
            'name' => 'employees',
            'display_name' => 'Employee Management',
            'description' => 'Manage employee records, departments, and designations',
            'icon' => '👥',
            'category' => 'Core',
            'setup_script' => 'scripts/setup_employees_table.php',
            'tables' => ['employees', 'departments', 'designations']
        ],
        'users' => [
            'name' => 'users',
            'display_name' => 'User Management',
            'description' => 'Manage system users and authentication',
            'icon' => '👤',
            'category' => 'Core',
            'setup_script' => 'scripts/setup_db.php',
            'tables' => ['users']
        ],
        'clients' => [
            'name' => 'clients',
            'display_name' => 'Client Management',
            'description' => 'Manage client information, addresses, and contacts',
            'icon' => '🏢',
            'category' => 'Core',
            'setup_script' => 'scripts/setup_clients_tables.php',
            'tables' => ['clients', 'client_addresses', 'client_contacts_map', 'client_documents', 'client_custom_fields']
        ],
        'contacts' => [
            'name' => 'contacts',
            'display_name' => 'Contact Management',
            'description' => 'Manage business contacts and contact groups',
            'icon' => '📇',
            'category' => 'CRM',
            'setup_script' => 'scripts/setup_contacts_tables.php',
            'tables' => ['contacts', 'contact_groups', 'contact_group_members']
        ],
        'attendance' => [
            'name' => 'attendance',
            'display_name' => 'Attendance Management',
            'description' => 'Track employee attendance and leave requests',
            'icon' => '📅',
            'category' => 'HR',
            'setup_script' => 'scripts/setup_attendance_table.php',
            'tables' => ['attendance', 'leave_requests']
        ],
        'salary' => [
            'name' => 'salary',
            'display_name' => 'Salary Records',
            'description' => 'Manage employee salary information',
            'icon' => '💰',
            'category' => 'HR',
            'setup_script' => 'scripts/setup_salary_records_table.php',
            'tables' => ['salary_records']
        ],
        'payroll' => [
            'name' => 'payroll',
            'display_name' => 'Payroll Management',
            'description' => 'Process payroll and generate salary slips',
            'icon' => '💵',
            'category' => 'HR',
            'setup_script' => 'scripts/setup_payroll_tables.php',
            'tables' => ['payroll_runs', 'payroll_items']
        ],
        'reimbursements' => [
            'name' => 'reimbursements',
            'display_name' => 'Reimbursements',
            'description' => 'Manage employee reimbursement requests',
            'icon' => '🧾',
            'category' => 'HR',
            'setup_script' => 'scripts/setup_reimbursements_table.php',
            'tables' => ['reimbursements']
        ],
        'documents' => [
            'name' => 'documents',
            'display_name' => 'Document Management',
            'description' => 'Store and manage company documents',
            'icon' => '📄',
            'category' => 'Other',
            'setup_script' => 'scripts/setup_documents_table.php',
            'tables' => ['documents']
        ],
        'visitors' => [
            'name' => 'visitors',
            'display_name' => 'Visitor Logs',
            'description' => 'Track visitor check-ins and check-outs',
            'icon' => '🚪',
            'category' => 'Other',
            'setup_script' => 'scripts/setup_visitor_logs_table.php',
            'tables' => ['visitor_logs']
        ],
        'crm' => [
            'name' => 'crm',
            'display_name' => 'CRM',
            'description' => 'Customer Relationship Management - leads, tasks, calls, meetings',
            'icon' => '📊',
            'category' => 'CRM',
            'setup_script' => 'scripts/setup_crm_tables.php',
            'tables' => ['crm_leads', 'crm_tasks', 'crm_calls', 'crm_meetings', 'crm_visits']
        ],
        'branding' => [
            'name' => 'branding',
            'display_name' => 'Branding Settings',
            'description' => 'Customize company branding and logos',
            'icon' => '🎨',
            'category' => 'Other',
            'setup_script' => 'scripts/setup_branding_table.php',
            'tables' => ['branding']
        ],
        'projects' => [
            'name' => 'projects',
            'display_name' => 'Projects Management',
            'description' => 'Manage projects, tasks, and team members',
            'icon' => '📋',
            'category' => 'Operations',
            'setup_script' => 'scripts/setup_projects_tables.php',
            'tables' => ['projects', 'project_members', 'project_tasks', 'project_phases']
        ],
        'workorders' => [
            'name' => 'workorders',
            'display_name' => 'Work Orders',
            'description' => 'Create and track work orders',
            'icon' => '🔧',
            'category' => 'Operations',
            'setup_script' => 'scripts/setup_workorders_tables.php',
            'tables' => ['work_orders', 'work_order_files', 'work_order_activity_log']
        ],
        'invoices' => [
            'name' => 'invoices',
            'display_name' => 'Invoices',
            'description' => 'Create and manage invoices',
            'icon' => '🧾',
            'category' => 'Finance',
            'setup_script' => 'scripts/setup_invoices_tables.php',
            'tables' => ['invoices', 'invoice_items']
        ],
        'quotations' => [
            'name' => 'quotations',
            'display_name' => 'Quotations',
            'description' => 'Create and manage quotations',
            'icon' => '📝',
            'category' => 'Finance',
            'setup_script' => 'scripts/setup_quotations_tables.php',
            'tables' => ['quotations', 'quotation_items']
        ],
        'payments' => [
            'name' => 'payments',
            'display_name' => 'Payments',
            'description' => 'Track payments and allocations',
            'icon' => '💳',
            'category' => 'Finance',
            'setup_script' => 'scripts/setup_payments_tables.php',
            'tables' => ['payments', 'payment_allocations']
        ],
        'catalog' => [
            'name' => 'catalog',
            'display_name' => 'Product Catalog',
            'description' => 'Manage products and inventory',
            'icon' => '📦',
            'category' => 'Finance',
            'setup_script' => 'scripts/setup_catalog_tables.php',
            'tables' => ['items_master', 'item_categories', 'stock_transactions']
        ],
        'expenses' => [
            'name' => 'expenses',
            'display_name' => 'Office Expenses',
            'description' => 'Track office expenses and bills',
            'icon' => '💸',
            'category' => 'Finance',
            'setup_script' => 'scripts/setup_office_expenses_table.php',
            'tables' => ['office_expenses']
        ],
        'deliverables' => [
            'name' => 'deliverables',
            'display_name' => 'Deliverables',
            'description' => 'Manage project deliverables',
            'icon' => '📤',
            'category' => 'Operations',
            'setup_script' => 'scripts/setup_deliverables_tables.php',
            'tables' => ['deliverables', 'deliverable_revisions']
        ],
        'delivery' => [
            'name' => 'delivery',
            'display_name' => 'Delivery Management',
            'description' => 'Track deliveries and proof of delivery',
            'icon' => '🚚',
            'category' => 'Operations',
            'setup_script' => 'scripts/setup_delivery_tables.php',
            'tables' => ['deliveries', 'delivery_items']
        ],
        'notebook' => [
            'name' => 'notebook',
            'display_name' => 'Notebook',
            'description' => 'Personal and shared notes',
            'icon' => '📓',
            'category' => 'Other',
            'setup_script' => 'scripts/setup_notebook_tables.php',
            'tables' => ['notebook_notes', 'notebook_attachments', 'notebook_shares', 'notebook_versions']
        ],
        'assets' => [
            'name' => 'assets',
            'display_name' => 'Asset Management',
            'description' => 'Track company assets and assignments',
            'icon' => '🖥️',
            'category' => 'Other',
            'setup_script' => 'scripts/setup_assets_tables.php',
            'tables' => ['assets', 'asset_assignments']
        ],
        'data-transfer' => [
            'name' => 'data-transfer',
            'display_name' => 'Data Transfer',
            'description' => 'Import and export data',
            'icon' => '🔄',
            'category' => 'Other',
            'setup_script' => 'scripts/setup_data_transfer_tables.php',
            'tables' => ['import_logs', 'export_logs']
        ],
        'roles' => [
            'name' => 'roles',
            'display_name' => 'Roles & Permissions',
            'description' => 'Manage user roles and permissions',
            'icon' => '🔐',
            'category' => 'Core',
            'setup_script' => 'scripts/setup_roles_permissions_tables.php',
            'tables' => ['roles', 'permissions', 'role_permissions', 'user_roles']
        ],
        'activity-log' => [
            'name' => 'activity-log',
            'display_name' => 'User Activity Log',
            'description' => 'Track user activities and audit trail',
            'icon' => '📜',
            'category' => 'Core',
            'setup_script' => 'scripts/setup_user_activity_log.php',
            'tables' => ['user_activity_log']
        ]
    ];
}

/**
 * Discover all available modules with their current installation status
 * 
 * @param mysqli $conn Database connection
 * @return array Array of module definitions with installation status
 */
function discover_modules(mysqli $conn): array {
    $registry = get_module_registry();
    $modules = [];
    
    foreach ($registry as $module_name => $module_data) {
        $module = $module_data;
        $module['installed'] = check_module_installed($conn, $module_name);
        $modules[$module_name] = $module;
    }
    
    return $modules;
}

/**
 * Get metadata for a specific module
 * 
 * @param string $module_name Module identifier
 * @return array|null Module metadata or null if not found
 */
function get_module_metadata(string $module_name): ?array {
    $registry = get_module_registry();
    return $registry[$module_name] ?? null;
}

/**
 * Check if a module is installed by verifying table existence
 * 
 * @param mysqli $conn Database connection
 * @param string $module_name Module identifier
 * @return bool True if all module tables exist
 */
function check_module_installed(mysqli $conn, string $module_name): bool {
    $metadata = get_module_metadata($module_name);
    
    if (!$metadata || !isset($metadata['tables'])) {
        return false;
    }
    
    // Check if all tables for this module exist
    foreach ($metadata['tables'] as $table) {
        if (!table_exists($conn, $table)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Check if a table exists in the database
 * 
 * @param mysqli $conn Database connection
 * @param string $table_name Table name to check
 * @return bool True if table exists
 */
if (!function_exists('table_exists')) {
    function table_exists(mysqli $conn, string $table_name): bool {
        $table_esc = mysqli_real_escape_string($conn, $table_name);
        $result = @mysqli_query($conn, "SHOW TABLES LIKE '$table_esc'");
        $exists = ($result && mysqli_num_rows($result) > 0);
        if ($result) {
            mysqli_free_result($result);
        }
        return $exists;
    }
}

/**
 * Scan scripts directory for setup scripts
 * Returns list of setup script files found
 * 
 * @return array Array of setup script filenames
 */
function scan_setup_scripts(): array {
    $scripts_dir = __DIR__ . '/../scripts';
    $setup_scripts = [];
    
    if (!is_dir($scripts_dir)) {
        return $setup_scripts;
    }
    
    $files = scandir($scripts_dir);
    
    foreach ($files as $file) {
        // Match setup_*_table.php or setup_*_tables.php pattern
        if (preg_match('/^setup_.*_tables?\.php$/', $file)) {
            $setup_scripts[] = $file;
        }
    }
    
    return $setup_scripts;
}

/**
 * Get all available module names from registry
 * 
 * @return array Array of module names
 */
function get_available_module_names(): array {
    return array_keys(get_module_registry());
}
?>
