<?php
/**
 * Module Dependencies Helper
 * Centralized prerequisite checking for all modules
 */

/**
 * Define mandatory base modules that must always be installed
 * These modules are required for the system to function properly
 */
function get_mandatory_modules(): array {
    return ['employees', 'catalog'];
}

/**
 * Define module dependencies
 * Format: 'module_name' => ['prerequisite_module1', 'prerequisite_module2']
 */
function get_module_dependencies(): array {
    return [
        // Base modules - no dependencies
        'employees' => [],
        'users' => [],
        'clients' => [],
        'catalog' => [],
        'branding' => [],
        
        // HR modules - depend on employees
        'attendance' => ['employees'],
        'salary' => ['employees'],
        'reimbursements' => ['employees'],
        'payroll' => ['employees', 'salary'],
        
        // Document & utility modules
        'documents' => ['employees'],
        'visitors' => ['employees'],
        'notebook' => [],
        'assets' => ['employees'],
        'data-transfer' => [],
        
        // Finance modules - depend on catalog and clients
        'invoices' => ['catalog', 'clients'],
        'quotations' => ['catalog', 'clients'],
        'payments' => ['invoices', 'clients'],
        'expenses' => ['employees'],
        
        // CRM modules
        'crm' => ['employees'],
        'contacts' => [],
        
        // Operations modules
        'projects' => ['clients'],
        'workorders' => ['employees', 'clients'],
        'deliverables' => [],
        'delivery' => ['clients']
    ];
}

/**
 * Get module display names
 */
function get_module_display_names(): array {
    return [
        'employees' => 'Employee Management',
        'users' => 'User Management',
        'clients' => 'Client Management',
        'attendance' => 'Attendance Management',
        'salary' => 'Salary Records',
        'documents' => 'Document Management',
        'reimbursements' => 'Reimbursements',
        'office_expenses' => 'Office Expenses',
        'visitors' => 'Visitor Logs',
        'crm' => 'CRM (Customer Relationship Management)',
        'branding' => 'Branding Settings',
        'projects' => 'Projects Management',
        'workorders' => 'Work Orders Management'
    ];
}

/**
 * Get module setup script paths
 */
function get_module_setup_paths(): array {
    return [
        'employees' => 'scripts/setup_employees_table.php',
        'clients' => 'scripts/setup_clients_tables.php',
        'attendance' => 'scripts/setup_attendance_table.php',
        'salary' => 'scripts/setup_salary_records_table.php',
        'documents' => 'scripts/setup_documents_table.php',
        'reimbursements' => 'scripts/setup_reimbursements_table.php',
        'office_expenses' => 'scripts/setup_office_expenses_table.php',
        'visitors' => 'scripts/setup_visitor_logs_table.php',
        'crm' => 'scripts/setup_crm_tables.php',
        'branding' => 'scripts/setup_branding_table.php',
        'projects' => 'scripts/setup_projects_tables.php',
        'workorders' => 'scripts/setup_workorders_tables.php'
    ];
}

/**
 * Check if a table exists in the database
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
 * Check if module prerequisites are met
 * Returns array with 'met' (bool) and 'missing' (array of missing tables)
 */
function check_module_prerequisites(mysqli $conn, string $module_name): array {
    $dependencies = get_module_dependencies();
    
    if (!isset($dependencies[$module_name])) {
        return ['met' => true, 'missing' => []];
    }
    
    $required_tables = $dependencies[$module_name];
    $missing = [];
    
    foreach ($required_tables as $table) {
        if (!table_exists($conn, $table)) {
            $missing[] = $table;
        }
    }
    
    return [
        'met' => empty($missing),
        'missing' => $missing
    ];
}

/**
 * Get prerequisite check result with detailed message
 */
function get_prerequisite_check_result(mysqli $conn, string $module_name): array {
    $check = check_module_prerequisites($conn, $module_name);
    $display_names = get_module_display_names();
    $setup_paths = get_module_setup_paths();
    
    if ($check['met']) {
        return [
            'allowed' => true,
            'message' => '',
            'missing_modules' => []
        ];
    }
    
    $missing_modules = [];
    foreach ($check['missing'] as $table) {
        // Convert table name to module name
        $module = $table; // Simple mapping for now (employees table = employees module)
        $missing_modules[] = [
            'name' => $module,
            'display_name' => $display_names[$module] ?? ucfirst($module),
            'setup_path' => isset($setup_paths[$module]) ? APP_URL . '/' . $setup_paths[$module] : null
        ];
    }
    
    $module_display = $display_names[$module_name] ?? ucfirst($module_name);
    
    return [
        'allowed' => false,
        'message' => "Cannot access $module_display module. Required modules are not set up yet.",
        'missing_modules' => $missing_modules
    ];
}

/**
 * Display prerequisite error page
 */
function display_prerequisite_error(string $module_name, array $missing_modules): void {
    $display_names = get_module_display_names();
    $module_display = $display_names[$module_name] ?? ucfirst($module_name);
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Module Unavailable - <?php echo APP_NAME; ?></title>
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
            }
            .error-container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 600px;
                width: 100%;
                padding: 40px;
                text-align: center;
            }
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                color: #dc3545;
                font-size: 24px;
                margin-bottom: 12px;
            }
            .subtitle {
                color: #6c757d;
                font-size: 14px;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .missing-modules {
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 30px;
                text-align: left;
            }
            .missing-modules h3 {
                color: #856404;
                font-size: 16px;
                margin-bottom: 12px;
            }
            .module-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px;
                margin-bottom: 8px;
                background: white;
                border-radius: 6px;
                border-left: 4px solid #ffc107;
            }
            .module-name {
                color: #495057;
                font-weight: 600;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: linear-gradient(135deg, #003581 0%, #004aad 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.3s;
                margin: 5px;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 30px rgba(0, 53, 129, 0.3);
            }
            .btn-secondary {
                background: #6c757d;
            }
            .btn-secondary:hover {
                box-shadow: 0 10px 30px rgba(108, 117, 125, 0.3);
            }
            .btn-small {
                padding: 6px 12px;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Module Unavailable</h1>
            <p class="subtitle">
                The <strong><?php echo htmlspecialchars($module_display); ?></strong> module cannot be accessed because required prerequisite modules are not set up yet.
            </p>
            
            <div class="missing-modules">
                <h3>📋 Required Modules:</h3>
                <?php foreach ($missing_modules as $mod): ?>
                    <div class="module-item">
                        <span class="module-name"><?php echo htmlspecialchars($mod['display_name']); ?></span>
                        <?php if ($mod['setup_path']): ?>
                            <a href="<?php echo htmlspecialchars($mod['setup_path']); ?>" class="btn btn-small">Setup Now</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div>
                <a href="<?php echo APP_URL; ?>/public/index.php" class="btn">← Back to Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
