<?php
/**
 * Payroll Module - Payroll List
 * View all payroll batches with filters
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payroll_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'year' => $_GET['year'] ?? ''
];

// Get payrolls
$payrolls = get_all_payrolls($conn, $filters);

// Get available years
$years_result = $conn->query("SELECT DISTINCT YEAR(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d')) as year FROM payroll_master ORDER BY year DESC");
$years = [];
while ($row = $years_result->fetch_assoc()) {
    $years[] = $row['year'];
}

$page_title = 'Payroll List - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>📋 Payroll History</h1>
                    <p>View and manage all payroll batches</p>
                </div>
                <div>
                    <a href="create.php" class="btn btn-primary">➕ Generate New Payroll</a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="" class="filters-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Draft" <?php echo $filters['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="Reviewed" <?php echo $filters['status'] === 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="Locked" <?php echo $filters['status'] === 'Locked' ? 'selected' : ''; ?>>Locked</option>
                        <option value="Paid" <?php echo $filters['status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year" class="form-control">
                        <option value="">All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $filters['year'] == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="list.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Payroll Table -->
        <div class="table-container">
            <?php if (!empty($payrolls)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Status</th>
                            <th>Employees</th>
                            <th>Total Amount</th>
                            <th>Created By</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payrolls as $payroll): ?>
                            <tr>
                                <td><strong><?php echo format_month_display($payroll['month']); ?></strong></td>
                                <td><?php echo get_status_badge($payroll['status']); ?></td>
                                <td><?php echo $payroll['total_employees']; ?></td>
                                <td><strong><?php echo format_currency($payroll['total_amount']); ?></strong></td>
                                <td><?php echo htmlspecialchars($payroll['created_by_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($payroll['created_at'])); ?></td>
                                <td>
                                    <a href="review.php?id=<?php echo $payroll['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3>No Payroll Batches Found</h3>
                    <p>No payroll records match your filter criteria.</p>
                    <?php if (!empty($filters['status']) || !empty($filters['year'])): ?>
                        <a href="list.php" class="btn btn-secondary">Clear Filters</a>
                    <?php else: ?>
                        <a href="create.php" class="btn btn-primary">Generate First Payroll</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.filters-card {
    background: white;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 20px;
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
    color: #333;
    font-size: 14px;
}

.filter-actions {
    display: flex;
    gap: 8px;
}

.table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.data-table th {
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #003581;
    font-size: 14px;
}

.data-table td {
    padding: 16px;
    border-bottom: 1px solid #e0e0e0;
    font-size: 14px;
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.empty-state h3 {
    color: #003581;
    margin-bottom: 12px;
}

.empty-state p {
    color: #666;
    margin-bottom: 24px;
}

.btn-sm {
    padding: 6px 16px;
    font-size: 13px;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
