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
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

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
              ORDER BY created_at DESC 
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

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>👥 Employee Management</h1>
                    <p>Manage employee information, records, and profiles</p>
                </div>
                <div>
                    <?php if ($employees_table_exists && $can_create_employee): ?>
                        <a href="add_employee.php" class="btn" style="display: inline-flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">➕</span> Add New Employee
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!$employees_table_exists): ?>
            <!-- Setup Required -->
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <strong>⚠️ Setup Required</strong><br>
                Employee module database tables need to be created first.
            </div>
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 80px; margin-bottom: 20px;">📋</div>
                <h2 style="color: #003581; margin-bottom: 15px;">Employee Module Not Set Up</h2>
                <p style="color: #6c757d; margin-bottom: 30px; font-size: 16px;">
                    Create the required database tables to start managing employees
                </p>
                <a href="../../scripts/setup_employees_table.php" class="btn" style="padding: 15px 40px; font-size: 16px;">
                    🚀 Setup Employee Module
                </a>
            </div>
        <?php else: ?>
            <!-- Success Message -->
            <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <strong>✅ Success!</strong><br>
                    Employee <?php echo isset($_GET['employee_code']) ? htmlspecialchars($_GET['employee_code']) : ''; ?> has been added successfully!
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
                <?php
                $conn2 = createConnection(true);
                $total_employees = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(*) as count FROM employees WHERE status='Active'"))['count'];
                $total_departments = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(DISTINCT department) as count FROM employees"))['count'];
                $on_leave = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(*) as count FROM employees WHERE status='On Leave'"))['count'];
                $new_this_month = mysqli_fetch_assoc(mysqli_query($conn2, "SELECT COUNT(*) as count FROM employees WHERE MONTH(date_of_joining) = MONTH(CURRENT_DATE) AND YEAR(date_of_joining) = YEAR(CURRENT_DATE)"))['count'];
                closeConnection($conn2);
                ?>
                
                <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $total_employees; ?></div>
                    <div style="font-size: 14px; opacity: 0.9;">Active Employees</div>
                </div>
                
                <div class="card" style="text-align: center; background: linear-gradient(135deg, #faa718 0%, #ffc04d 100%); color: white;">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $total_departments; ?></div>
                    <div style="font-size: 14px; opacity: 0.9;">Departments</div>
                </div>
                
                <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white;">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $on_leave; ?></div>
                    <div style="font-size: 14px; opacity: 0.9;">On Leave Today</div>
                </div>
                
                <div class="card" style="text-align: center; background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%); color: white;">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $new_this_month; ?></div>
                    <div style="font-size: 14px; opacity: 0.9;">New This Month</div>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="card" style="margin-bottom: 25px;">
                <form method="GET" action="index.php" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>🔍 Search Employees</label>
                        <input type="text" name="search" class="form-control" placeholder="Employee Code, Name, Email, Mobile..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
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
                    
                    <div class="form-group" style="margin-bottom: 0;">
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
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn" style="white-space: nowrap;">Search</button>
                        <a href="index.php" class="btn btn-accent" style="white-space: nowrap; text-decoration: none; display: inline-block; text-align: center;">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Employee List -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #003581;">
                        📋 Employee List 
                        <span style="font-size: 14px; color: #6c757d; font-weight: normal;">(<?php echo $total_records; ?> records)</span>
                    </h3>
                    <div style="display: flex; gap: 10px;">
                        <?php if ($can_export_employee): ?>
                            <button onclick="exportToExcel()" class="btn btn-accent" style="padding: 8px 16px; font-size: 13px;">
                                📊 Export to Excel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($employees) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Photo</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Employee Code</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Email</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Mobile</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Department</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Designation</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Joining Date</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Status</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 12px;">
                                            <?php if ($emp['photo_path'] && file_exists(__DIR__ . '/../../' . $emp['photo_path'])): ?>
                                                <img src="<?php echo '../../' . htmlspecialchars($emp['photo_path']); ?>" alt="Photo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #003581; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px;">
                                                    <?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px; font-weight: 600; color: #003581;">
                                            <?php echo htmlspecialchars($emp['employee_code']); ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']); ?>
                                        </td>
                                        <td style="padding: 12px; color: #6c757d; font-size: 13px;">
                                            <?php echo htmlspecialchars($emp['official_email']); ?>
                                        </td>
                                        <td style="padding: 12px; color: #6c757d; font-size: 13px;">
                                            <?php echo htmlspecialchars($emp['mobile_number']); ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: #e3f2fd; color: #003581; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                                <?php echo htmlspecialchars($emp['department']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; font-size: 13px;">
                                            <?php echo htmlspecialchars($emp['designation']); ?>
                                        </td>
                                        <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                            <?php echo date('d-M-Y', strtotime($emp['date_of_joining'])); ?>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <?php
                                            $status_colors = [
                                                'Active' => 'background: #d4edda; color: #155724;',
                                                'Inactive' => 'background: #f8d7da; color: #721c24;',
                                                'On Leave' => 'background: #fff3cd; color: #856404;',
                                                'Terminated' => 'background: #f5c6cb; color: #721c24;',
                                                'Resigned' => 'background: #d6d8db; color: #383d41;'
                                            ];
                                            $status_style = $status_colors[$emp['status']] ?? 'background: #e2e3e5; color: #383d41;';
                                            ?>
                                            <span style="<?php echo $status_style; ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                                <?php echo htmlspecialchars($emp['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <a href="view_employee.php?id=<?php echo $emp['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                                    👁️ View
                                                </a>
                                                <?php if ($can_edit_employee): ?>
                                                    <a href="edit_employee.php?id=<?php echo $emp['id']; ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
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
                        <div style="margin-top: 25px; display: flex; justify-content: center; align-items: center; gap: 10px;">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn" style="padding: 8px 16px; text-decoration: none;">
                                    « Previous
                                </a>
                            <?php endif; ?>
                            
                            <span style="color: #6c757d; font-size: 14px;">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn" style="padding: 8px 16px; text-decoration: none;">
                                    Next »
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                        <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                        <h3 style="color: #003581; margin-bottom: 10px;">No Employees Found</h3>
                        <p>No employees match your search criteria. Try adjusting your filters.</p>
                        <a href="index.php" class="btn btn-accent" style="margin-top: 20px; text-decoration: none;">Clear Filters</a>
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
