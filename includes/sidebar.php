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

// Define navigation menu items
$user_role = $_SESSION['role'] ?? 'user';
$reimbursements_link = ($user_role === 'user')
    ? APP_URL . '/public/employee_portal/reimbursements/index.php'
    : APP_URL . '/public/reimbursements/index.php';

$nav_items = [
    [
        'icon' => 'dashboard.png',
        'label' => 'Dashboard',
        'link' => 'index.php',
        'active' => ($current_page == 'index.php')
    ],
    [
        'icon' => 'employees.png',
        'label' => 'Employees',
        'link' => 'employee/index.php',
        'active' => (strpos($current_path, '/employee/') !== false) || in_array($current_page, ['employees.php','add_employee.php','view_employee.php','edit_employee.php'])
    ],
    [
        'icon' => 'attendance.png',
        'label' => 'Attendance',
        'link' => 'attendance/index.php',
        'active' => (strpos($current_path, '/attendance/') !== false) || in_array($current_page, ['mark_attendance.php'])
    ],
    [
        'icon' => 'expenses.png',
        'label' => 'Reimbursements',
        'link' => $reimbursements_link,
        'active' => (strpos($current_path, '/reimbursements/') !== false)
    ],
    [
        'icon' => 'crm.png',
        'label' => 'CRM',
        'link' => 'crm/index.php',
        'active' => (strpos($current_path, '/crm/') !== false)
    ],
    // CRM Dashboard (Managers/Admins only) – insert dynamically below if role permits
    [
        'icon' => 'expenses.png',
        'label' => 'Expenses',
        'link' => 'expenses/index.php',
        'active' => (strpos($current_path, '/reimbursements/') !== false)
    ],
    [
        'icon' => 'salary.png',
        'label' => 'Salary Viewer',
        'link' => 'salary/index.php',
        'active' => (strpos($current_path, '/salary/') !== false) || in_array($current_page, ['salary.php'], true)
    ],
    [
        'icon' => 'documents.png',
        'label' => 'Documents',
        'link' => 'documents/index.php',
        'active' => ($current_page == 'documents.php')
    ],
    [
        'icon' => 'visitors.png',
        'label' => 'Visitor Log',
        'link' => 'visitors/index.php',
        'active' => (strpos($current_path, '/visitors/') !== false)
    ],
    [
        'icon' => 'analytics.png',
        'label' => 'Analytics',
        'link' => 'analytics.php',
        'active' => ($current_page == 'analytics.php')
    ],
    [
        'icon' => 'settings.png',
        'label' => 'Settings',
        'link' => 'settings.php',
        'active' => ($current_page == 'settings.php')
    ],
    [
        'icon' => 'roles.png',
        'label' => 'Roles & Permissions',
        'link' => 'roles.php',
        'active' => ($current_page == 'roles.php')
    ],
    [
        'icon' => 'branding.png',
        'label' => 'Branding',
        'link' => 'branding.php',
        'active' => ($current_page == 'branding.php')
    ],
    [
        'icon' => 'notifications.png',
        'label' => 'Notifications',
        'link' => 'notifications.php',
        'active' => ($current_page == 'notifications.php')
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
                <?php if ($has_logo_icon): ?>
                    <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>" style="width: 120px;">
                <?php else: ?>
                    <div style="width: 32px; height: 32px; background: #faa718; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003581;">K</div>
                <?php endif; ?>
            </div>
            
            <!-- Collapsed Square Icon (shown when sidebar is collapsed) -->
            <div class="logo-collapsed" style="display: none;">
                <?php if ($has_square_icon): ?>
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
            // Inject CRM Dashboard link (manager/admin) just after CRM item
            $injected = false;
            foreach ($nav_items as $item): 
                $isCrm = ($item['label'] === 'CRM');
            ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo $item['link']; ?>" 
                       class="sidebar-nav-link <?php echo $item['active'] ? 'active' : ''; ?>"
                       data-tooltip="<?php echo $item['label']; ?>">
                        <?php 
                        $icon_path = getIconPath($item['icon']);
                        if ($icon_path): 
                        ?>
                            <img src="<?php echo $icon_path; ?>" alt="<?php echo $item['label']; ?>" class="nav-icon">
                        <?php else: ?>
                            <!-- SVG Fallback Icons -->
                            <span class="nav-icon-fallback">
                                <?php
                                // Simple icon fallback based on label
                                $icon_map = [
                                    'Dashboard' => '🏠',
                                    'Employees' => '👥',
                                    'Attendance' => '📅',
                                    'CRM' => '📞',
                                    'Expenses' => '💰',
                                    'Salary Viewer' => '💵',
                                    'Reimbursements' => '💳',
                                    'Documents' => '📂',
                                    'Visitor Log' => '📋',
                                    'Analytics' => '📊',
                                    'Settings' => '⚙️',
                                    'Roles & Permissions' => '🔐',
                                    'Branding' => '🎨',
                                    'Notifications' => '💬'
                                ];
                                echo $icon_map[$item['label']] ?? '•';
                                ?>
                            </span>
                        <?php endif; ?>
                        <span class="nav-text"><?php echo $item['label']; ?></span>
                    </a>
                </li>
                <?php if ($isCrm && in_array(strtolower($user_role), ['admin','manager'], true) && !$injected): ?>
                    <li class="sidebar-nav-item">
                        <a href="crm/dashboard.php" class="sidebar-nav-link <?php echo (strpos($current_path, '/crm/dashboard.php') !== false) ? 'active' : ''; ?>" data-tooltip="CRM Dashboard">
                            <span class="nav-icon-fallback">📊</span>
                            <span class="nav-text">CRM Dashboard</span>
                        </a>
                    </li>
                    <?php $injected = true; endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link" data-tooltip="Logout">
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
