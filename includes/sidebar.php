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
if (isset($conn) && $conn instanceof mysqli) {
    $sidebar_conn_auth = $conn;
} else {
    $sidebar_conn_auth = createConnection(true);
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
        'icon' => 'crm.png',
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
        'icon' => 'salary.png',
        'label' => 'Salary',
        'link' => APP_URL . '/public/salary/admin.php',
        'active' => (strpos($current_path, '/public/salary/') !== false && strpos($current_path, '/employee_portal/') === false),
        'requires' => ['table' => 'salary_records', 'permission' => 'view_all']
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Documents',
        'link' => APP_URL . '/public/documents/index.php',
        'active' => (strpos($current_path, '/documents/') !== false && strpos($current_path, '/employee_portal/') === false),
        'requires' => ['table' => 'documents', 'permission' => 'view_all']
    ],
    [
        'icon' => 'visitors.png',
        'label' => 'Visitor Log',
        'link' => APP_URL . '/public/visitors/index.php',
        'active' => (strpos($current_path, '/visitors/') !== false),
        'requires' => ['table' => 'visitor_logs', 'permission' => 'view_all']
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Notebook',
        'link' => APP_URL . '/public/notebook/index.php',
        'active' => (strpos($current_path, '/notebook/') !== false),
        'requires' => ['table' => 'notebook_notes', 'permission' => 'view']
    ],
    [
        'icon' => 'employees.png',
        'label' => 'Contacts',
        'link' => APP_URL . '/public/contacts/index.php',
        'active' => (strpos($current_path, '/contacts/') !== false),
        'requires' => ['table' => 'contacts', 'permission' => 'view']
    ],
    [
        'icon' => 'employees.png',
        'label' => 'Clients',
        'link' => APP_URL . '/public/clients/index.php',
        'active' => (strpos($current_path, '/clients/') !== false),
        'requires' => ['table' => 'clients', 'permission' => 'view']
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Projects',
        'link' => APP_URL . '/public/projects/index.php',
        'active' => (strpos($current_path, '/projects/') !== false),
        'requires' => ['table' => 'projects', 'permission' => 'view']
    ],
    [
        'icon' => 'branding.png',
        'label' => 'Branding',
        'link' => APP_URL . '/public/branding/view.php',
        'active' => (strpos($current_path, '/branding/') !== false),
        'requires' => ['table' => 'branding_settings', 'permission' => 'view_all']
    ],
    [
        'icon' => 'settings.png',
        'label' => 'Roles & Permissions',
        'link' => APP_URL . '/public/settings/roles/index.php',
        'active' => (strpos($current_path, '/settings/roles/') !== false || strpos($current_path, '/settings/permissions/') !== false),
        'requires_any' => [
            ['table' => 'roles', 'permission' => 'view_all'],
            ['table' => 'permissions', 'permission' => 'view_all']
        ]
    ],
    [
        'icon' => 'employees.png',
        'label' => 'Users Management',
        'link' => APP_URL . '/public/users/index.php',
        'active' => (strpos($current_path, '/public/users/') !== false && strpos($current_path, '/my-account.php') === false),
        'requires' => ['table' => 'users', 'permission' => 'view_all']
    ]
];

// Employee-specific menu items (always shown at bottom)
$employee_items = [];
$employee_items[] = [
    'icon' => 'attendance.png',
    'label' => 'My Attendance',
    'link' => APP_URL . '/public/employee_portal/attendance/index.php',
    'active' => (strpos($current_path, '/employee_portal/attendance/') !== false),
    'requires_any' => [
        ['table' => 'attendance', 'permission' => 'view_own'],
        ['table' => 'attendance', 'permission' => 'view_all']
    ]
];
$employee_items[] = [
    'icon' => 'expenses.png',
    'label' => 'My Reimbursements',
    'link' => APP_URL . '/public/employee_portal/reimbursements/index.php',
    'active' => (strpos($current_path, '/employee_portal/reimbursements/') !== false),
    'requires_any' => [
        ['table' => 'reimbursements', 'permission' => 'view_own'],
        ['table' => 'reimbursements', 'permission' => 'view_all']
    ]
];
$employee_items[] = [
    'icon' => 'salary.png',
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
        'icon' => 'employees.png',
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
    'icon' => 'settings.png',
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
        return '../assets/icons/' . $icon_name;
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
        transition: width 0.3s ease;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar.collapsed {
        width: 70px;
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
        justify-content: center;
        padding: 16px 10px;
    }

    .sidebar.collapsed .sidebar-nav-link:hover {
        padding-left: 10px;
    }

    .nav-icon {
        width: 24px;
        height: 24px;
        object-fit: contain;
        flex-shrink: 0;
        filter: brightness(0) invert(1);
    }

    .sidebar-nav-link.active .nav-icon,
    .sidebar-nav-link:hover .nav-icon {
        filter: none;
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
        transition: opacity 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    }

    .sidebar.collapsed .nav-text {
        opacity: 0;
        width: 0;
        margin-left: 0;
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
        justify-content: center;
        padding: 12px 10px;
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
        margin-left: 70px;
    }

    /* Legacy support for sidebar class */
    .sidebar.collapsed ~ .main-wrapper {
        margin-left: 70px;
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

    /* Tooltip for collapsed state */
    .sidebar.collapsed .sidebar-nav-link {
        position: relative;
    }

    .sidebar.collapsed .sidebar-nav-link::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 70px;
        top: 50%;
        transform: translateY(-50%);
        background: #003581;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        z-index: 1000;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .sidebar.collapsed .sidebar-nav-link:hover::after {
        opacity: 1;
    }
</style>

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
        sidebar.classList.toggle('collapsed');
        
        // Toggle body class for main content adjustment
        document.body.classList.toggle('sidebar-collapsed');
        
        // Save state to localStorage
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }

    // Handle Logo Click - expand sidebar when collapsed
    function handleLogoClick() {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        // Only expand if collapsed
        if (isCollapsed) {
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', false);
        }
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const savedState = localStorage.getItem('sidebarCollapsed');
        
        if (savedState === 'true') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
    });
</script>

<?php
if ((!isset($conn) || !($conn instanceof mysqli)) && isset($sidebar_conn_auth) && $sidebar_conn_auth instanceof mysqli) {
    @closeConnection($sidebar_conn_auth);
}
?>
