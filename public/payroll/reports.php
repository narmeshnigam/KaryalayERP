<?php
/**
 * Payroll Module - Reports & Analytics
 * Comprehensive payroll analytics with export functionality
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payroll_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get filter parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : null;
$department = isset($_GET['department']) ? $_GET['department'] : null;

// Get available years
$years_query = "SELECT DISTINCT YEAR(month) as year FROM payroll_master ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_query);
$available_years = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $available_years[] = $row['year'];
}

// Get departments
$departments_query = "SELECT DISTINCT department FROM employees WHERE status = 'Active' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    if (!empty($row['department'])) {
        $departments[] = $row['department'];
    }
}

// Build query for monthly summary

$summary_query = "SELECT 
    DATE_FORMAT(pm.month, '%Y-%m') as month,
    DATE_FORMAT(pm.month, '%M %Y') as month_display,
    COUNT(DISTINCT pm.id) as payroll_count,
    SUM(pm.total_employees) as total_employees,
    SUM(pm.total_amount) as total_amount,
    COUNT(CASE WHEN pm.status = 'Paid' THEN 1 END) as paid_count
FROM payroll_master pm
WHERE YEAR(pm.month) = ?";

$params = [$year];
$types = 'i';

if ($department) {
    $summary_query .= " AND pm.id IN (
        SELECT DISTINCT payroll_id FROM payroll_records pr
        INNER JOIN employees e ON pr.employee_id = e.id
        WHERE e.department = ?
    )";
    $params[] = $department;
    $types .= 's';
}

$summary_query .= " GROUP BY DATE_FORMAT(pm.month, '%Y-%m') ORDER BY pm.month DESC";

$stmt = mysqli_prepare($conn, $summary_query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$summary_result = mysqli_stmt_get_result($stmt);
$monthly_summary = [];
while ($row = mysqli_fetch_assoc($summary_result)) {
    $monthly_summary[] = $row;
}

// Department-wise summary
$dept_query = "SELECT 
    e.department,
    COUNT(DISTINCT pr.id) as record_count,
    COUNT(DISTINCT pr.employee_id) as employee_count,
    SUM(pr.base_salary) as total_base,
    SUM(pr.allowances) as total_allowances,
    SUM(pr.reimbursements) as total_reimbursements,
    SUM(pr.bonus) as total_bonus,
    SUM(pr.deductions) as total_deductions,
    SUM(pr.penalties) as total_penalties,
    SUM(pr.net_pay) as total_net_pay,
    AVG(pr.net_pay) as avg_net_pay
FROM payroll_records pr
INNER JOIN employees e ON pr.employee_id = e.id
INNER JOIN payroll_master pm ON pr.payroll_id = pm.id
WHERE YEAR(pm.month) = ?";

$dept_params = [$year];
$dept_types = 'i';

if ($month) {
    $dept_query .= " AND DATE_FORMAT(pm.month, '%Y-%m') = ?";
    $dept_params[] = $month;
    $dept_types .= 's';
}

if ($department) {
    $dept_query .= " AND e.department = ?";
    $dept_params[] = $department;
    $dept_types .= 's';
}

$dept_query .= " GROUP BY e.department ORDER BY total_net_pay DESC";

$dept_stmt = mysqli_prepare($conn, $dept_query);
mysqli_stmt_bind_param($dept_stmt, $dept_types, ...$dept_params);
mysqli_stmt_execute($dept_stmt);
$dept_result = mysqli_stmt_get_result($dept_stmt);
$dept_summary = [];
while ($row = mysqli_fetch_assoc($dept_result)) {
    $dept_summary[] = $row;
}

// Year totals
$year_totals = [
    'total_payrolls' => array_sum(array_column($monthly_summary, 'payroll_count')),
    'total_employees' => 0,
    'total_paid' => array_sum(array_column($monthly_summary, 'total_amount')),
    'avg_monthly' => 0
];

if (count($monthly_summary) > 0) {
    $year_totals['avg_monthly'] = $year_totals['total_paid'] / count($monthly_summary);
}

// Get unique employee count for the year
$unique_emp_query = "SELECT COUNT(DISTINCT pr.employee_id) as unique_count
FROM payroll_records pr
INNER JOIN payroll_master pm ON pr.payroll_id = pm.id
WHERE YEAR(pm.month) = ?";
$unique_emp_stmt = mysqli_prepare($conn, $unique_emp_query);
mysqli_stmt_bind_param($unique_emp_stmt, 'i', $year);
mysqli_stmt_execute($unique_emp_stmt);
$unique_emp_result = mysqli_stmt_get_result($unique_emp_stmt);
$unique_emp = mysqli_fetch_assoc($unique_emp_result);
$year_totals['total_employees'] = $unique_emp['unique_count'];

$page_title = 'Payroll Reports & Analytics - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>📊 Payroll Reports & Analytics</h1>
                    <p>Comprehensive payroll insights and data export</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="" class="filters-form">
                <div class="filter-group">
                    <label>Year:</label>
                    <select name="year" class="form-control">
                        <?php foreach ($available_years as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo $yr == $year ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Month (Optional):</label>
                    <select name="month" class="form-control">
                        <option value="">All Months</option>
                        <?php foreach ($monthly_summary as $ms): ?>
                            <option value="<?php echo $ms['month']; ?>" <?php echo $month == $ms['month'] ? 'selected' : ''; ?>>
                                <?php echo $ms['month_display']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Department (Optional):</label>
                    <select name="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="reports.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Year Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon">📋</div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo $year_totals['total_payrolls']; ?></div>
                    <div class="summary-label">Total Payrolls</div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon">👥</div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo $year_totals['total_employees']; ?></div>
                    <div class="summary-label">Unique Employees</div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon">💰</div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo format_currency($year_totals['total_paid']); ?></div>
                    <div class="summary-label">Total Paid (<?php echo $year; ?>)</div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon">📊</div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo format_currency($year_totals['avg_monthly']); ?></div>
                    <div class="summary-label">Avg Monthly Payout</div>
                </div>
            </div>
        </div>

        <!-- Monthly Summary Table -->
        <div class="report-section">
            <div class="section-header">
                <h2>📅 Monthly Summary - <?php echo $year; ?></h2>
                <?php if (count($monthly_summary) > 0): ?>
                    <button onclick="exportTableToCSV('monthly_summary', 'Monthly_Payroll_<?php echo $year; ?>')" class="btn btn-sm btn-success">📥 Export CSV</button>
                <?php endif; ?>
            </div>
            
            <?php if (count($monthly_summary) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table" id="monthly_summary">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Payroll Batches</th>
                                <th>Total Employees</th>
                                <th>Total Amount</th>
                                <th>Paid Batches</th>
                                <th>Avg per Employee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_summary as $row): ?>
                                <tr>
                                    <td><?php echo $row['month_display']; ?></td>
                                    <td><?php echo $row['payroll_count']; ?></td>
                                    <td><?php echo $row['total_employees']; ?></td>
                                    <td><?php echo format_currency($row['total_amount']); ?></td>
                                    <td><?php echo $row['paid_count']; ?></td>
                                    <td><?php echo $row['total_employees'] > 0 ? format_currency($row['total_amount'] / $row['total_employees']) : '₹0.00'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>TOTAL</th>
                                <th><?php echo array_sum(array_column($monthly_summary, 'payroll_count')); ?></th>
                                <th>-</th>
                                <th><?php echo format_currency(array_sum(array_column($monthly_summary, 'total_amount'))); ?></th>
                                <th><?php echo array_sum(array_column($monthly_summary, 'paid_count')); ?></th>
                                <th>-</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">No payroll data found for <?php echo $year; ?></p>
            <?php endif; ?>
        </div>

        <!-- Department-wise Summary -->
        <div class="report-section">
            <div class="section-header">
                <h2>🏢 Department-wise Salary Expense</h2>
                <?php if (count($dept_summary) > 0): ?>
                    <button onclick="exportTableToCSV('dept_summary', 'Department_Payroll_<?php echo $year; ?>')" class="btn btn-sm btn-success">📥 Export CSV</button>
                <?php endif; ?>
            </div>
            
            <?php if (count($dept_summary) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table" id="dept_summary">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Employees</th>
                                <th>Base Salary</th>
                                <th>Allowances</th>
                                <th>Reimbursements</th>
                                <th>Bonus</th>
                                <th>Deductions</th>
                                <th>Penalties</th>
                                <th>Net Pay</th>
                                <th>Avg Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_summary as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['department']); ?></strong></td>
                                    <td><?php echo $row['employee_count']; ?></td>
                                    <td><?php echo format_currency($row['total_base']); ?></td>
                                    <td><?php echo format_currency($row['total_allowances']); ?></td>
                                    <td><?php echo format_currency($row['total_reimbursements']); ?></td>
                                    <td><?php echo format_currency($row['total_bonus']); ?></td>
                                    <td><?php echo format_currency($row['total_deductions']); ?></td>
                                    <td><?php echo format_currency($row['total_penalties']); ?></td>
                                    <td><strong><?php echo format_currency($row['total_net_pay']); ?></strong></td>
                                    <td><?php echo format_currency($row['avg_net_pay']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>TOTAL</th>
                                <th>-</th>
                                <th><?php echo format_currency(array_sum(array_column($dept_summary, 'total_base'))); ?></th>
                                <th><?php echo format_currency(array_sum(array_column($dept_summary, 'total_allowances'))); ?></th>
                                <th><?php echo format_currency(array_sum(array_column($dept_summary, 'total_reimbursements'))); ?></th>
                                <th><?php echo format_currency(array_sum(array_column($dept_summary, 'total_bonus'))); ?></th>
                                <th><?php echo format_currency(array_sum(array_column($dept_summary, 'total_deductions'))); ?></th>
                                <th><?php echo format_currency(array_sum(array_column($dept_summary, 'total_penalties'))); ?></th>
                                <th><strong><?php echo format_currency(array_sum(array_column($dept_summary, 'total_net_pay'))); ?></strong></th>
                                <th>-</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">No department data found</p>
            <?php endif; ?>
        </div>

        <!-- Chart Visualization -->
        <?php if (count($monthly_summary) > 0): ?>
            <div class="report-section">
                <h2>📈 Monthly Trend</h2>
                <canvas id="monthlyChart" style="max-height: 400px;"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.filters-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filters-form {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 13px;
    color: #555;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.summary-card {
    background: white;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    gap: 16px;
    align-items: center;
}

.summary-icon {
    font-size: 40px;
}

.summary-value {
    font-size: 28px;
    font-weight: 700;
    color: #003581;
}

.summary-label {
    font-size: 13px;
    color: #666;
    margin-top: 4px;
}

.report-section {
    background: white;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e0e0e0;
}

.section-header h2 {
    color: #003581;
    margin: 0;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f5f5f5;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #003581;
    font-size: 13px;
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
}

.data-table tfoot th {
    background: #003581;
    color: white;
    padding: 14px 12px;
    border: none;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #999;
    font-style: italic;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Export table to CSV
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    let csv = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Get body rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            let text = td.textContent.trim().replace(/,/g, '');
            row.push(text);
        });
        csv.push(row.join(','));
    });
    
    // Get footer if exists
    const tfoot = table.querySelector('tfoot');
    if (tfoot) {
        const footRow = [];
        tfoot.querySelectorAll('th').forEach(th => {
            let text = th.textContent.trim().replace(/,/g, '');
            footRow.push(text);
        });
        csv.push(footRow.join(','));
    }
    
    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '_' + new Date().getTime() + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Chart
<?php if (count($monthly_summary) > 0): ?>
    const chartData = {
        labels: <?php echo json_encode(array_column($monthly_summary, 'month_display')); ?>,
        datasets: [{
            label: 'Total Payout',
            data: <?php echo json_encode(array_column($monthly_summary, 'total_amount')); ?>,
            backgroundColor: 'rgba(0, 53, 129, 0.2)',
            borderColor: 'rgba(0, 53, 129, 1)',
            borderWidth: 2,
            tension: 0.4
        }]
    };
    
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₹' + context.parsed.y.toLocaleString('en-IN');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString('en-IN');
                        }
                    }
                }
            }
        }
    });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
