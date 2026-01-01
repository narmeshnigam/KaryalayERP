<?php
/**
 * Sidebar Navigation Component
 * 
 * Collapsible sidebar navigation for logged-in users
 * Features:
 * - Collapsible sidebar
 * - Icon-based navigation
 * - Sticky positioning (no scroll shake)
 * - Active page highlighting
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['PHP_SELF'] ?? '';

// Ensure authz functions are available
if (!function_exists('authz_context')) {
    require_once __DIR__ . '/authz.php';
}

// Get current employee ID for profile link
$current_employee_id = null;
$conn_sidebar = @createConnection(true);
if ($conn_sidebar) {
    // Check if employees table exists
    $table_check = @mysqli_query($conn_sidebar, "SHOW TABLES LIKE 'employees'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $stmt = @mysqli_prepare($conn_sidebar, 'SELECT id FROM employees WHERE user_id = ? LIMIT 1');
        if ($stmt) {
            $uid = (int)$_SESSION['user_id'];
            @mysqli_stmt_bind_param($stmt, 'i', $uid);
            @mysqli_stmt_execute($stmt);
            $result = @mysqli_stmt_get_result($stmt);
            if ($result && $row = @mysqli_fetch_assoc($result)) {
                $current_employee_id = (int)$row['id'];
            }
            @mysqli_stmt_close($stmt);
        }
    }
    if ($table_check) @mysqli_free_result($table_check);
    @closeConnection($conn_sidebar);
}

$sidebar_conn_auth = null;
$sidebar_conn_managed = false;
if (isset($conn) && $conn instanceof mysqli && (($conn->thread_id ?? 0) > 0)) {
    $sidebar_conn_auth = $conn;
} else {
    $sidebar_conn_auth = createConnection(true);
    $sidebar_conn_managed = true;
}

// Get auth context - use global if set by auth_check.php, otherwise create new
if (isset($AUTHZ_CONTEXT) && is_array($AUTHZ_CONTEXT)) {
    $SIDEBAR_AUTH_CONTEXT = $AUTHZ_CONTEXT;
} else {
    $SIDEBAR_AUTH_CONTEXT = authz_context($sidebar_conn_auth);
}

$SIDEBAR_ROLE_NAMES = $_SESSION['role_names'] ?? array_map(static function ($role) {
    return $role['name'] ?? '';
}, $SIDEBAR_AUTH_CONTEXT['roles'] ?? []);
$SIDEBAR_ROLE_DISPLAY = !empty($SIDEBAR_ROLE_NAMES) ? implode(', ', $SIDEBAR_ROLE_NAMES) : 'No Roles Assigned';

if (!function_exists('sidebar_item_has_access')) {
    function sidebar_item_has_access(array $item, mysqli $conn): bool {
        if (isset($item['requires'])) {
            $table = $item['requires']['table'] ?? '';
            if ($table === '') {
                return false;
            }
            $perm = $item['requires']['permission'] ?? 'view_all';
            if (!authz_user_can($conn, $table, $perm)) {
                return false;
            }
        }

        if (isset($item['requires_any']) && is_array($item['requires_any'])) {
            if (!authz_user_can_any($conn, $item['requires_any'])) {
                return false;
            }
        }

        return true;
    }
}

// Define navigation menu items with ABSOLUTE URLs
// Main navigation (ordered as requested)
// Main navigation (ordered as requested)
$nav_items = [
    [
        'icon' => 'dashboard.png',
        'label' => 'Dashboard',
        'link' => APP_URL . '/public/index.php',
        'active' => ($current_page == 'index.php' && strpos($current_path, '/public/index.php') !== false)
    ],
    [
        'icon' => 'search.png',
        'label' => 'Search',
        'link' => APP_URL . '/public/search.php',
        'active' => ($current_page == 'search.php' && strpos($current_path, '/public/search.php') !== false)
    ],
    [
        'icon' => 'employees.png',
        'label' => 'Employees',
        'link' => APP_URL . '/public/employee/index.php',
        'active' => (strpos($current_path, '/employee/') !== false),
        'requires' => ['table' => 'employees', 'permission' => 'view_all']
    ],
    [
        'icon' => 'attendance.png',
        'label' => 'Attendance',
        'link' => APP_URL . '/public/attendance/index.php',
        'active' => (strpos($current_path, '/public/attendance/') !== false),
        'requires' => ['table' => 'attendance', 'permission' => 'view_all']
    ],
    [
        'icon' => 'expenses.png',
        'label' => 'Reimbursements',
        'link' => APP_URL . '/public/reimbursements/index.php',
        'active' => (strpos($current_path, '/public/reimbursements/') !== false && strpos($current_path, '/employee_portal/') === false),
        'requires' => ['table' => 'reimbursements', 'permission' => 'view_all']
    ],
    [
        'icon' => 'CRM.png',
        'label' => 'CRM',
        'link' => APP_URL . '/public/crm/index.php',
        'active' => (strpos($current_path, '/crm/') !== false && strpos($current_path, '/crm/dashboard.php') === false),
        'requires' => ['table' => 'crm_leads', 'permission' => 'view_all']
    ],
    [
        'icon' => 'expenses.png',
        'label' => 'Expenses',
        'link' => APP_URL . '/public/expenses/index.php',
        'active' => (strpos($current_path, '/expenses/') !== false),
        'requires' => ['table' => 'office_expenses', 'permission' => 'view_all']
    ],
    [
        'icon' => 'my_salary.png',
        'label' => 'Salary',
        'link' => APP_URL . '/public/salary/admin.php',
        'active' => (strpos($current_path, '/public/salary/') !== false && strpos($current_path, '/employee_portal/') === false),
        'requires' => ['table' => 'salary_records', 'permission' => 'view_all']
    ],
    [
        'icon' => 'payroll.png',
        'label' => 'Payroll',
        'link' => APP_URL . '/public/payroll/index.php',
        'active' => (strpos($current_path, '/public/payroll/') !== false),
        'requires' => ['table' => 'payroll_master', 'permission' => 'view_all']
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Documents',
        'link' => APP_URL . '/public/documents/index.php',
        'active' => (strpos($current_path, '/documents/') !== false && strpos($current_path, '/employee_portal/') === false),
        'requires' => ['table' => 'documents', 'permission' => 'view_all']
    ],
    [
        'icon' => 'visitor.png',
        'label' => 'Visitor Log',
        'link' => APP_URL . '/public/visitors/index.php',
        'active' => (strpos($current_path, '/visitors/') !== false),
        'requires' => ['table' => 'visitor_logs', 'permission' => 'view_all']
    ],
    [
        'icon' => 'notebook.png',
        'label' => 'Notebook',
        'link' => APP_URL . '/public/notebook/index.php',
        'active' => (strpos($current_path, '/notebook/') !== false),
        'requires' => ['table' => 'notebook_notes', 'permission' => 'view']
    ],
    [
        'icon' => 'contacts.png',
        'label' => 'Contacts',
        'link' => APP_URL . '/public/contacts/index.php',
        'active' => (strpos($current_path, '/contacts/') !== false),
        'requires' => ['table' => 'contacts', 'permission' => 'view']
    ],
    [
        'icon' => 'client.png',
        'label' => 'Clients',
        'link' => APP_URL . '/public/clients/index.php',
        'active' => (strpos($current_path, '/clients/') !== false),
        'requires' => ['table' => 'clients', 'permission' => 'view']
    ],
    [
        'icon' => 'projects.png',
        'label' => 'Projects',
        'link' => APP_URL . '/public/projects/index.php',
        'active' => (strpos($current_path, '/projects/') !== false),
        'requires' => ['table' => 'projects', 'permission' => 'view']
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Work Orders',
        'link' => APP_URL . '/public/workorders/index.php',
        'active' => (strpos($current_path, '/workorders/') !== false),
        'requires' => ['table' => 'work_orders', 'permission' => 'view_all']
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Deliverables',
        'link' => APP_URL . '/public/deliverables/index.php',
        'active' => (strpos($current_path, '/deliverables/') !== false),
        'requires' => ['table' => 'deliverables', 'permission' => 'view']
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Delivery',
        'link' => APP_URL . '/public/delivery/index.php',
        'active' => (strpos($current_path, '/delivery/') !== false),
        'requires' => ['table' => 'deliveries', 'permission' => 'view']
    ],
    [
        'icon' => 'catalog.png',
        'label' => 'Catalog',
        'link' => APP_URL . '/public/catalog/index.php',
        'active' => (strpos($current_path, '/catalog/') !== false),
        'requires' => ['table' => 'items_master', 'permission' => 'view']
    ],
    [
        'icon' => 'quotations.png',
        'label' => 'Quotations',
        'link' => APP_URL . '/public/quotations/index.php',
        'active' => (strpos($current_path, '/quotations/') !== false),
        'requires' => ['table' => 'quotations', 'permission' => 'view_all']
    ],
    [
        'icon' => 'invoice.png',
        'label' => 'Invoices',
        'link' => APP_URL . '/public/invoices/index.php',
        'active' => (strpos($current_path, '/invoices/') !== false),
        'requires' => ['table' => 'invoices', 'permission' => 'view_all']
    ],
    [
        'icon' => 'payments.png',
        'label' => 'Payments',
        'link' => APP_URL . '/public/payments/index.php',
        'active' => (strpos($current_path, '/payments/') !== false),
        'requires' => ['table' => 'payments', 'permission' => 'view_all']
    ],
    [
        'icon' => 'data transfer.png',
        'label' => 'Data Transfer',
        'link' => APP_URL . '/public/data-transfer/index.php',
        'active' => (strpos($current_path, '/data-transfer/') !== false),
        'requires' => ['table' => 'data_transfer_logs', 'permission' => 'view_all']
    ],
    [
        'icon' => 'assets.png',
        'label' => 'Assets',
        'link' => APP_URL . '/public/assets/index.php',
        'active' => (strpos($current_path, '/assets/') !== false),
        'requires' => ['table' => 'assets_master', 'permission' => 'view_all']
    ],
    [
        'icon' => 'branding.png',
        'label' => 'Branding',
        'link' => APP_URL . '/public/branding/view.php',
        'active' => (strpos($current_path, '/branding/') !== false),
        'requires' => ['table' => 'branding_settings', 'permission' => 'view_all']
    ],
    [
        'icon' => 'roles_permissions.png',
        'label' => 'Settings',
        'link' => APP_URL . '/public/settings/index.php',
        'active' => (strpos($current_path, '/settings/index.php') !== false),
        'requires_any' => [
            ['table' => 'roles', 'permission' => 'view_all'],
            ['table' => 'permissions', 'permission' => 'view_all'],
            ['table' => 'users', 'permission' => 'view_all'],
            ['table' => 'branding_settings', 'permission' => 'view_all']
        ]
    ],
    [
        'icon' => 'roles_permissions.png',
        'label' => 'Roles & Permissions',
        'link' => APP_URL . '/public/settings/roles/index.php',
        'active' => (strpos($current_path, '/settings/roles/') !== false || strpos($current_path, '/settings/permissions/') !== false),
        'requires_any' => [
            ['table' => 'roles', 'permission' => 'view_all'],
            ['table' => 'permissions', 'permission' => 'view_all']
        ]
    ],
    [
        'icon' => 'user_management.png',
        'label' => 'Users Management',
        'link' => APP_URL . '/public/users/index.php',
        'active' => (strpos($current_path, '/public/users/') !== false && strpos($current_path, '/my-account.php') === false),
        'requires' => ['table' => 'users', 'permission' => 'view_all']
    ]
];

// Employee-specific menu items (always shown at bottom)
$employee_items = [];
$employee_items[] = [
    'icon' => 'my_attendance.png',
    'label' => 'My Attendance',
    'link' => APP_URL . '/public/employee_portal/attendance/index.php',
    'active' => (strpos($current_path, '/employee_portal/attendance/') !== false),
    'requires_any' => [
        ['table' => 'attendance', 'permission' => 'view_own'],
        ['table' => 'attendance', 'permission' => 'view_all']
    ]
];
$employee_items[] = [
    'icon' => 'my_reimbursements.png',
    'label' => 'My Reimbursements',
    'link' => APP_URL . '/public/employee_portal/reimbursements/index.php',
    'active' => (strpos($current_path, '/employee_portal/reimbursements/') !== false),
    'requires_any' => [
        ['table' => 'reimbursements', 'permission' => 'view_own'],
        ['table' => 'reimbursements', 'permission' => 'view_all']
    ]
];
$employee_items[] = [
    'icon' => 'my_salary.png',
    'label' => 'My Salary',
    'link' => APP_URL . '/public/employee_portal/salary/index.php',
    'active' => (strpos($current_path, '/employee_portal/salary/') !== false),
    'requires_any' => [
        ['table' => 'salary_records', 'permission' => 'view_own'],
        ['table' => 'salary_records', 'permission' => 'view_all']
    ]
];
if ($current_employee_id) {
    $employee_items[] = [
            'icon' => 'my_profile.png',
        'label' => 'My Profile',
        'link' => APP_URL . '/public/employee/view_employee.php?id=' . $current_employee_id,
        'active' => ($current_page == 'view_employee.php' && isset($_GET['id']) && (int)$_GET['id'] === $current_employee_id),
        'requires_any' => [
            ['table' => 'employees', 'permission' => 'view_own'],
            ['table' => 'employees', 'permission' => 'view_all']
        ]
    ];
}
$employee_items[] = [
    'icon' => 'my_account.png',
    'label' => 'My Account',
    'link' => APP_URL . '/public/users/my-account.php',
    'active' => (strpos($current_path, '/users/my-account.php') !== false),
    'requires_any' => [
        ['table' => 'users', 'permission' => 'view_own'],
        ['table' => 'users', 'permission' => 'view_all']
    ]
];

// Check if icon file exists, otherwise use SVG fallback
function getIconPath($icon_name) {
    $icon_path = __DIR__ . '/../assets/icons/' . $icon_name;
    if (file_exists($icon_path)) {
        $app_url = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        return $app_url . '/assets/icons/' . rawurlencode($icon_name);
    }
    return null;
}

// Check if logo icon exists
$logo_icon_path = __DIR__ . '/../assets/logo/logo_white_text_transparent.png';
$has_logo_icon = file_exists($logo_icon_path);

// Check if square icon exists
$square_icon_path = __DIR__ . '/../assets/logo/icon_white_text_transparent.png';
$has_square_icon = file_exists($square_icon_path);

// Get branding logos from database
$branding_full_logo = null;
$branding_square_logo = null;
$conn_branding = @createConnection(true);
if ($conn_branding) {
    // Check if branding_settings table exists
    $table_check = @mysqli_query($conn_branding, "SHOW TABLES LIKE 'branding_settings'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $res = @mysqli_query($conn_branding, "SELECT sidebar_header_full_logo, sidebar_square_logo FROM branding_settings LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            if (!empty($row['sidebar_header_full_logo'])) {
                $branding_full_logo = '../../' . $row['sidebar_header_full_logo'];
            }
            if (!empty($row['sidebar_square_logo'])) {
                $branding_square_logo = '../../' . $row['sidebar_square_logo'];
            }
            mysqli_free_result($res);
        }
        if ($table_check) @mysqli_free_result($table_check);
    }
    @closeConnection($conn_branding);
}
?>

<style>
    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 260px;
        height: 100vh;
        background-color: #003581;
        color: white;
        transition: all 0.3s ease;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar.collapsed {
        width: 90px;
    }

    /* Mobile Header (hidden by default, shown when sidebar is collapsed on mobile) */
    .mobile-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background-color: #003581;
        color: white;
        display: none;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 999;
        transition: all 0.3s ease;
    }

    .mobile-header.active {
        display: flex;
    }

    .mobile-header .hamburger-btn {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mobile-header .hamburger-btn:hover {
        color: #faa718;
    }

    .mobile-header .header-logo {
        display: flex;
        align-items: center;
    }

    .mobile-header .header-logo img {
        height: 40px;
        object-fit: contain;
    }

    /* Main content padding when mobile header is active */
    .main-wrapper.mobile-header-active {
        padding-top: 60px;
    }

    /* Sidebar Header */
    .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 20px;
        background: #002a66;
        min-height: 60px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-toggle {
        background: none;
        border: none;
        color: white;
        font-size: 22px;
        cursor: pointer;
        padding: 5px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
    }

    .sidebar-toggle:hover {
        color: #faa718;
        transform: scale(1.1);
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        margin-left: 10px;
        overflow: hidden;
    }

    .sidebar-logo img {
        width: 32px;
        height: 32px;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .logo-expanded {
        display: block;
    }

    .logo-collapsed {
        display: none;
    }

    .sidebar.collapsed .logo-expanded {
        display: none;
    }

    .sidebar.collapsed .logo-collapsed {
        display: block !important;
    }

    .sidebar-logo-text {
        font-size: 20px;
        font-weight: 700;
        white-space: nowrap;
        transition: opacity 0.3s ease;
    }

    .sidebar.collapsed .sidebar-logo-text {
        opacity: 0;
        width: 0;
    }

    .sidebar.collapsed .sidebar-header {
        justify-content: center;
        padding: 15px 10px;
    }

    .sidebar.collapsed .sidebar-toggle {
        display: none;
    }

    .sidebar.collapsed .sidebar-logo {
        margin-left: 0;
        justify-content: center;
        cursor: pointer;
    }

    .sidebar-logo.clickable {
        cursor: pointer;
    }

    .sidebar-logo.clickable:hover img {
        transform: scale(1.1);
    }

    .square-icon {
        width: 40px;
        height: 40px;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    /* Sidebar Navigation */
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 10px 0;
    }

    /* Custom Scrollbar for Sidebar */
    .sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .sidebar-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-nav-item {
        margin: 0;
    }

    .sidebar-nav-link {
        display: flex;
        align-items: center;
        padding: 16px 20px;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        border-radius: 0;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
        background: transparent;
    }

    .sidebar-nav-link:hover {
        background-color: #faa718;
        color: #003581;
        padding-left: 25px;
    }

    .sidebar-nav-link.active {
        background-color: #faa718;
        color: #003581;
        font-weight: 600;
    }

    .sidebar.collapsed .sidebar-nav-link {
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 12px 5px;
        gap: 4px;
    }

    .sidebar.collapsed .sidebar-nav-link:hover {
        padding-left: 5px;
    }

    .nav-icon {
        width: 24px;
        height: 24px;
        object-fit: contain;
        flex-shrink: 0;
        filter: none;
        transition: filter 0.2s ease;
    }

    .sidebar-nav-link.active .nav-icon,
    .sidebar-nav-link:hover:not(.active) .nav-icon {
        filter: brightness(0) invert(1);
    }

    .logout-link .nav-icon {
        filter: brightness(0) invert(1);
    }

    .nav-icon-fallback {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .nav-text {
        margin-left: 15px;
        white-space: nowrap;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    }

    .sidebar.collapsed .nav-text {
        margin-left: 0;
        margin-top: 2px;
        font-size: 9px;
        font-weight: 600;
        text-align: center;
        line-height: 1.2;
        max-width: 80px;
        white-space: normal;
        word-wrap: break-word;
    }

    /* Logout Section */
    .sidebar-footer {
        padding: 15px 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .logout-link {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.3s ease;
        background: #dc3545;
        margin: 0 5px;
    }

    .logout-link:hover {
        background: #c82333;
        transform: translateX(5px);
    }

    .sidebar.collapsed .logout-link {
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 12px 5px;
        gap: 4px;
    }

    .sidebar.collapsed .logout-link:hover {
        transform: translateX(0);
    }

    /* Main Content Adjustment */
    .main-wrapper {
        margin-left: 260px;
        transition: margin-left 0.3s ease;
        min-height: 100vh;
        background: #f4f6f9;
    }

    body.sidebar-collapsed .main-wrapper {
        margin-left: 90px;
    }

    /* Legacy support for sidebar class */
    .sidebar.collapsed ~ .main-wrapper {
        margin-left: 90px;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        /* On mobile, sidebar is full-screen when expanded and slides out when collapsed */
        .sidebar {
            transform: translateX(0);
            transition: transform 0.3s ease, left 0.3s ease, width 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1500;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
            /* keep width defined but hidden when collapsed */
            width: 100vw;
        }

        .main-wrapper {
            margin-left: 0 !important;
        }

        body.sidebar-collapsed .main-wrapper {
            margin-left: 0 !important;
        }

        .sidebar.collapsed ~ .main-wrapper {
            margin-left: 0 !important;
        }

        /* Show mobile header when sidebar is collapsed */
        body.sidebar-collapsed .mobile-header {
            display: flex !important;
        }

        /* Add padding to main content when mobile header is visible */
        body.sidebar-collapsed .main-wrapper {
            padding-top: 60px;
        }

        /* When sidebar is expanded on mobile, prevent background scroll */
        .sidebar:not(.collapsed) ~ .main-wrapper,
        body .sidebar:not(.collapsed) ~ .main-wrapper {
            pointer-events: none;
        }
    }

    /* User Info in Sidebar */
    .sidebar-user {
        padding: 15px;
        background: rgba(0, 0, 0, 0.2);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }

    .sidebar.collapsed .sidebar-user {
        padding: 10px;
        text-align: center;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #faa718;
        color: #003581;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        margin: 0 auto;
    }

    .user-details {
        margin-top: 10px;
        text-align: center;
        transition: opacity 0.3s ease;
    }

    .sidebar.collapsed .user-details {
        opacity: 0;
        height: 0;
        overflow: hidden;
        margin: 0;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 3px;
    }

    .user-role {
        font-size: 11px;
        color: #faa718;
        text-transform: uppercase;
    }

    /* Tooltip for collapsed state - disabled since text is now visible */
    .sidebar.collapsed .sidebar-nav-link,
    .sidebar.collapsed .logout-link {
        position: relative;
    }

    .sidebar.collapsed .sidebar-nav-link::after,
    .sidebar.collapsed .logout-link::after {
        display: none;
    }
</style>

?>

<!-- Mobile Header (shown when sidebar is collapsed on mobile) -->
<div class="mobile-header" id="mobileHeader">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle Menu">
        ☰
    </button>
    <div class="header-logo">
        <?php if ($branding_full_logo): ?>
            <img src="<?php echo $branding_full_logo; ?>" alt="<?php echo APP_NAME; ?>">
        <?php elseif ($has_logo_icon): ?>
            <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>">
        <?php else: ?>
            <div style="width: 32px; height: 32px; background: #faa718; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003581;">K</div>
        <?php endif; ?>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
            ☰
        </button>
        <div class="sidebar-logo" id="sidebarLogo" onclick="handleLogoClick()">
            <!-- Expanded Logo (shown when sidebar is expanded) -->
            <div class="logo-expanded">
                <?php if ($branding_full_logo): ?>
                    <img src="<?php echo $branding_full_logo; ?>" alt="<?php echo APP_NAME; ?>" style="width: 120px;">
                <?php elseif ($has_logo_icon): ?>
                    <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>" style="width: 120px;">
                <?php else: ?>
                    <div style="width: 32px; height: 32px; background: #faa718; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003581;">K</div>
                <?php endif; ?>
            </div>
            
            <!-- Collapsed Square Icon (shown when sidebar is collapsed) -->
            <div class="logo-collapsed" style="display: none;">
                <?php if ($branding_square_logo): ?>
                    <img src="<?php echo $branding_square_logo; ?>" alt="Menu" class="square-icon">
                <?php elseif ($has_square_icon): ?>
                    <img src="<?php echo APP_URL; ?>/assets/logo/icon_white_text_transparent.png" alt="Menu" class="square-icon">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; background: #faa718; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003581; font-size: 20px;">K</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul>
            <?php 
            // Main navigation items
            foreach ($nav_items as $item): 
                if (!sidebar_item_has_access($item, $sidebar_conn_auth)) {
                    continue;
                }
            ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars($item['link'], ENT_QUOTES); ?>" 
                       class="sidebar-nav-link <?php echo $item['active'] ? 'active' : ''; ?>"
                       data-tooltip="<?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>">
                        <?php 
                        $icon_path = getIconPath($item['icon']);
                        if ($icon_path): 
                        ?>
                            <img src="<?php echo $icon_path; ?>" alt="<?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>" class="nav-icon">
                        <?php else: ?>
                            <!-- SVG Fallback Icons -->
                            <span class="nav-icon-fallback">
                                <?php
                                // Simple icon fallback based on label
                                $icon_map = [
                                    'Dashboard' => '🏠',
                                    'Search' => '🔍',
                                    'Employees' => '👥',
                                    'Attendance' => '📅',
                                    'Reimbursements' => '📤',
                                    'CRM' => '📈',
                                    'Expenses' => '💰',
                                    'Salary' => '💵',
                                    'Documents' => '📂',
                                    'Visitor Log' => '📋',
                                    'Branding' => '🎨'
                                ];
                                echo $icon_map[$item['label']] ?? '•';
                                ?>
                            </span>
                        <?php endif; ?>
                        <span class="nav-text"><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            
            <?php if (!empty($employee_items)): ?>
                <li style="margin: 10px 15px; border-top: 1px solid rgba(255,255,255,0.2);"></li>
                <?php foreach ($employee_items as $item): ?>
                    <?php if (!sidebar_item_has_access($item, $sidebar_conn_auth)) { continue; } ?>
                    <li class="sidebar-nav-item">
                        <a href="<?php echo htmlspecialchars($item['link'], ENT_QUOTES); ?>" 
                           class="sidebar-nav-link <?php echo $item['active'] ? 'active' : ''; ?>"
                           data-tooltip="<?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>">
                            <?php 
                            $icon_path = getIconPath($item['icon']);
                            if ($icon_path): 
                            ?>
                                <img src="<?php echo $icon_path; ?>" alt="<?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>" class="nav-icon">
                            <?php else: ?>
                                <span class="nav-icon-fallback">
                                    <?php
                                    $icon_map = [
                                        'My Profile' => '👤',
                                        'My Attendance' => '📅',
                                        'My Reimbursements' => '💳',
                                        'My Salary' => '💵'
                                    ];
                                    echo $icon_map[$item['label']] ?? '•';
                                    ?>
                                </span>
                            <?php endif; ?>
                            <span class="nav-text"><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="<?php echo APP_URL; ?>/public/logout.php" class="logout-link" data-tooltip="Logout">
            <?php 
            $logout_icon = getIconPath('logout.png');
            if ($logout_icon): 
            ?>
                <img src="<?php echo $logout_icon; ?>" alt="Logout" class="nav-icon">
            <?php else: ?>
                <span class="nav-icon-fallback">🚪</span>
            <?php endif; ?>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</div>

<script>
    // Toggle Sidebar Function
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mobileHeader = document.getElementById('mobileHeader');
        const isMobile = window.innerWidth <= 768;
        
        sidebar.classList.toggle('collapsed');

        // Toggle body class for main content adjustment
        document.body.classList.toggle('sidebar-collapsed');

        // Handle mobile header visibility and background scrolling
        if (isMobile) {
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (isCollapsed) {
                mobileHeader.classList.add('active');
                // allow scrolling when collapsed
                document.body.style.overflow = '';
            } else {
                mobileHeader.classList.remove('active');
                // prevent background scroll when sidebar is overlaying
                document.body.style.overflow = 'hidden';
            }
            // Save state to localStorage as string
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? 'true' : 'false');
        } else {
            // Desktop save state as before (boolean-like string)
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
        }
    }

    // Handle Logo Click - expand sidebar when collapsed
    function handleLogoClick() {
        const sidebar = document.getElementById('sidebar');
        const mobileHeader = document.getElementById('mobileHeader');
        const isCollapsed = sidebar.classList.contains('collapsed');
        const isMobile = window.innerWidth <= 768;
        
        // Only expand if collapsed
        if (isCollapsed) {
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
            
            // Hide mobile header when expanding on mobile
            if (isMobile) {
                mobileHeader.classList.remove('active');
                // prevent background scroll while sidebar overlays
                document.body.style.overflow = 'hidden';
            }

            localStorage.setItem('sidebarCollapsed', 'false');
        }
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mobileHeader = document.getElementById('mobileHeader');
        const isMobile = window.innerWidth <= 768;
        let savedState = localStorage.getItem('sidebarCollapsed');

        // On mobile, always start collapsed (keep sidebar hidden on each page load)
        if (isMobile) {
            savedState = 'true';
            localStorage.setItem('sidebarCollapsed', 'true');
        }

        if (savedState === 'true') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');

            // Show mobile header on mobile if collapsed
            if (isMobile) {
                mobileHeader.classList.add('active');
                // allow scrolling of body when sidebar is collapsed
                document.body.style.overflow = '';
            }
        } else {
            // if expanded on load, prevent background scroll on mobile
            if (isMobile) {
                document.body.style.overflow = 'hidden';
                mobileHeader.classList.remove('active');
            }
        }
    });

    // Handle window resize - adjust mobile header visibility
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const mobileHeader = document.getElementById('mobileHeader');
        const isMobile = window.innerWidth <= 768;
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        if (isMobile && isCollapsed) {
            mobileHeader.classList.add('active');
        } else if (!isMobile) {
            mobileHeader.classList.remove('active');
            // restore overflow for desktop
            document.body.style.overflow = '';
        }
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const mobileHeader = document.getElementById('mobileHeader');
        const isMobile = window.innerWidth <= 768;
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        // Only on mobile and when sidebar is expanded
        if (isMobile && !isCollapsed) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnHamburger = event.target.closest('.hamburger-btn');
            
            if (!isClickInsideSidebar && !isClickOnHamburger) {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
                mobileHeader.classList.add('active');
                localStorage.setItem('sidebarCollapsed', true);
            }
        }
    });
</script>

<?php
if (!empty($sidebar_conn_managed) && $sidebar_conn_auth instanceof mysqli) {
    @closeConnection($sidebar_conn_auth);
}
?>
