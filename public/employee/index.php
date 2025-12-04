<?php
/**
 * Employee Management Module - Main Page (Scoped under /public/employee)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

authz_require_permission($conn, 'employees', 'view_all');
$employee_permissions = authz_get_permission_set($conn, 'employees');
$can_create_employee = !empty($employee_permissions['can_create']);
$can_edit_employee = !empty($employee_permissions['can_edit_all']);
$can_export_employee = !empty($employee_permissions['can_export']);

// Page title
$page_title = "Employee Management - " . APP_NAME;

// Include header with sidebar
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Check if employees table exists
$table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'employees'");
$employees_table_exists = mysqli_num_rows($table_exists) > 0;

// Get filters from request
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Whitelist sortable columns
$sortable_columns = [
    'employee_code' => 'employee_code',
    'name' => 'first_name',
    'email' => 'official_email',
    'mobile' => 'mobile_number',
    'department' => 'department',
    'designation' => 'designation',
    'joining_date' => 'date_of_joining',
    'status' => 'status',
    'created_at' => 'created_at'
];
$sort_column = isset($sortable_columns[$sort_by]) ? $sortable_columns[$sort_by] : 'created_at';

// Build query
$where_clauses = [];
if ($search) {
    $where_clauses[] = "(employee_code LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR official_email LIKE '%$search%' OR mobile_number LIKE '%$search%')";
}
if ($department_filter) {
    $where_clauses[] = "department = '$department_filter'";
}
if ($status_filter) {
    $where_clauses[] = "status = '$status_filter'";
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Get employees
$employees = [];
$total_records = 0;

if ($employees_table_exists) {
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM employees $where_sql";
    $count_result = mysqli_query($conn, $count_query);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
    
    // Get employees
    $query = "SELECT id, employee_code, first_name, middle_name, last_name, official_email, 
              mobile_number, department, designation, date_of_joining, status, photo_path
              FROM employees 
              $where_sql 
              ORDER BY $sort_column $sort_order 
              LIMIT $per_page OFFSET $offset";
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
    
    // Get departments for filter
    $dept_query = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department";
    $dept_result = mysqli_query($conn, $dept_query);
    $departments = [];
    while ($dept = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $dept['department'];
    }
}

$total_pages = ceil($total_records / $per_page);

// Helper function to generate sort URLs
function getSortUrl($column) {
    global $search, $department_filter, $status_filter, $sort_by, $sort_order;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'desc' : 'asc';
    $params = array_filter([
        'search' => $search,
        'department' => $department_filter,
        'status' => $status_filter,
        'sort' => $column,
        'order' => $new_order
    ]);
    return 'index.php?' . http_build_query($params);
}

function getSortIndicator($column) {
    global $sort_by, $sort_order;
    if ($sort_by === $column) {
        return $sort_order === 'ASC' ? ' ▲' : ' ▼';
    }
    return '';
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="emp-header-flex">
                <div>
                    <h1>👥 Employee Management</h1>
                    <p>Manage employee information, records, and profiles</p>
                </div>
                <?php if ($employees_table_exists && $can_create_employee): ?>
                    <div class="emp-header-btn-mobile">
                        <a href="add_employee.php" class="btn btn-inline-icon">
                            <span class="btn-icon">➕</span> Add New Employee
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$employees_table_exists): ?>
            <!-- Setup Required -->
            <div class="alert alert-warning emp-alert-spacing">
                <strong>⚠️ Setup Required</strong><br>
                Employee module database tables need to be created first.
            </div>
            <div class="emp-setup-wrapper">
                <div class="emp-setup-icon">📋</div>
                <h2 class="emp-setup-title">Employee Module Not Set Up</h2>
                <p class="emp-setup-text">
                    Create the required database tables to start managing employees
                </p>
                <a href="<?php echo APP_URL; ?>/scripts/setup_employees_table.php" class="btn emp-setup-btn">
                    🚀 Setup Employee Module
                </a>
            </div>
        <?php else: ?>
            <!-- Success Message -->
            <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                <div class="alert alert-success emp-alert-spacing">
                    <strong>✅ Success!</strong><br>
                    Employee <?php echo isset($_GET['employee_code']) ? htmlspecialchars($_GET['employee_code']) : ''; ?> has been added successfully!
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="emp-stats-grid">
                <?php
                $conn2 = createConnection(true);
                $total_employees = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(*) as count FROM employees WHERE status='Active'"))['count'];
                $total_departments = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(DISTINCT department) as count FROM employees"))['count'];
                $on_leave = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(*) as count FROM employees WHERE status='On Leave'"))['count'];
                $new_this_month = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(*) as count FROM employees WHERE MONTH(date_of_joining) = MONTH(CURRENT_DATE) AND YEAR(date_of_joining) = YEAR(CURRENT_DATE)"))['count'];
                closeConnection($conn2);
                ?>
                
                <div class="card emp-stat-card emp-stat-blue">
                    <div class="emp-stat-value"><?php echo $total_employees; ?></div>
                    <div class="emp-stat-label">Active Employees</div>
                </div>
                
                <div class="card emp-stat-card emp-stat-amber">
                    <div class="emp-stat-value"><?php echo $total_departments; ?></div>
                    <div class="emp-stat-label">Departments</div>
                </div>
                
                <div class="card emp-stat-card emp-stat-green">
                    <div class="emp-stat-value"><?php echo $on_leave; ?></div>
                    <div class="emp-stat-label">On Leave Today</div>
                </div>
                
                <div class="card emp-stat-card emp-stat-purple">
                    <div class="emp-stat-value"><?php echo $new_this_month; ?></div>
                    <div class="emp-stat-label">New This Month</div>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="card emp-search-card">
                <h3 class="emp-filter-heading">🔍 Filter Employees</h3>
                <form method="GET" action="index.php" class="emp-search-form">
                    <div class="form-group form-group-inline">
                        <label>🔍 Search Employees</label>
                        <input type="text" name="search" class="form-control" placeholder="Employee Code, Name, Email, Mobile..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group form-group-inline">
                        <label>🏢 Department</label>
                        <select name="department" class="form-control">
                            <option value="">All Departments</option>
                            <?php if (!empty($departments)) { foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter == $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; } ?>
                        </select>
                    </div>
                    
                    <div class="form-group form-group-inline">
                        <label>📊 Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="On Leave" <?php echo $status_filter == 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                            <option value="Terminated" <?php echo $status_filter == 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                            <option value="Resigned" <?php echo $status_filter == 'Resigned' ? 'selected' : ''; ?>>Resigned</option>
                        </select>
                    </div>
                    
                    <div class="emp-search-actions">
                        <button type="submit" class="btn btn-no-wrap">Search</button>
                        <a href="index.php" class="btn btn-accent btn-no-wrap">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Employee List -->
            <div class="card">
                <div class="emp-card-header">
                    <h3 class="emp-card-title">
                        📋 Employee List 
                        <span class="emp-card-count">(<?php echo $total_records; ?> records)</span>
                    </h3>
                    <div class="emp-card-actions">
                        <?php if ($can_export_employee): ?>
                            <button onclick="exportToExcel()" class="btn btn-accent btn-small">
                                📊 Export to Excel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($employees) > 0): ?>
                    <div class="emp-table-wrapper">
                        <table class="table emp-table">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('employee_code'); ?>">Employee Code<?php echo getSortIndicator('employee_code'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('name'); ?>">Name<?php echo getSortIndicator('name'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('email'); ?>">Email<?php echo getSortIndicator('email'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('mobile'); ?>">Mobile<?php echo getSortIndicator('mobile'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('department'); ?>">Department<?php echo getSortIndicator('department'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('designation'); ?>">Designation<?php echo getSortIndicator('designation'); ?></a></th>
                                    <th class="sortable"><a href="<?php echo getSortUrl('joining_date'); ?>">Joining Date<?php echo getSortIndicator('joining_date'); ?></a></th>
                                    <th class="sortable emp-table-center"><a href="<?php echo getSortUrl('status'); ?>">Status<?php echo getSortIndicator('status'); ?></a></th>
                                    <th class="emp-table-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td class="emp-table-photo">
                                            <?php if ($emp['photo_path'] && file_exists(__DIR__ . '/../../' . $emp['photo_path'])): ?>
                                                <img src="<?php echo '../../' . htmlspecialchars($emp['photo_path']); ?>" alt="Photo">
                                            <?php else: ?>
                                                <div class="emp-photo-placeholder">
                                                    <?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="emp-table-code">
                                            <?php echo htmlspecialchars($emp['employee_code']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']); ?>
                                        </td>
                                        <td class="emp-table-email">
                                            <?php echo htmlspecialchars($emp['official_email']); ?>
                                        </td>
                                        <td class="emp-table-mobile">
                                            <?php echo htmlspecialchars($emp['mobile_number']); ?>
                                        </td>
                                        <td>
                                            <span class="emp-dept-badge">
                                                <?php echo htmlspecialchars($emp['department']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($emp['designation']); ?>
                                        </td>
                                        <td class="emp-table-date">
                                            <?php echo date('d-M-Y', strtotime($emp['date_of_joining'])); ?>
                                        </td>
                                            <td class="emp-table-center">
                                            <?php
                                            $status_classes = [
                                                'Active' => 'status-badge status-active',
                                                'Inactive' => 'status-badge status-inactive',
                                                'On Leave' => 'status-badge status-on-leave',
                                                'Terminated' => 'status-badge status-terminated',
                                                'Resigned' => 'status-badge status-resigned'
                                            ];
                                            $badge_class = $status_classes[$emp['status']] ?? 'status-badge status-default';
                                            ?>
                                            <span class="<?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($emp['status']); ?>
                                            </span>
                                        </td>
                                        <td class="emp-table-actions">
                                            <div class="emp-row-actions">
                                                <a href="view_employee.php?id=<?php echo $emp['id']; ?>" class="btn btn-small">
                                                    👁️ View
                                                </a>
                                                <?php if ($can_edit_employee): ?>
                                                    <a href="edit_employee.php?id=<?php echo $emp['id']; ?>" class="btn btn-accent btn-small">
                                                        ✏️ Edit
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="emp-pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-small">
                                    « Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="emp-pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-small">
                                    Next »
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="emp-empty-state">
                        <div class="emp-empty-icon">📭</div>
                        <h3 class="emp-empty-title">No Employees Found</h3>
                        <p class="emp-empty-text">No employees match your search criteria. Try adjusting your filters.</p>
                        <a href="index.php" class="btn btn-accent emp-empty-btn">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportToExcel() {
    window.location.href = 'export_employees.php?search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
