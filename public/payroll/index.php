<?php
/**
 * Payroll Module - Dashboard
 * Overview of payroll with KPIs and recent batches
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payroll_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get dashboard statistics
$stats = get_payroll_dashboard_stats($conn);

$page_title = 'Payroll Dashboard - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>🧾 Payroll Dashboard</h1>
                    <p>Manage monthly salary processing and payslip generation</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="create.php" class="btn btn-primary">➕ Generate Payroll</a>
                    <a href="list.php" class="btn btn-secondary">📋 View All</a>
                    <a href="reports.php" class="btn btn-accent">📊 Reports</a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['flash_success']); 
                unset($_SESSION['flash_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['flash_error']); 
                unset($_SESSION['flash_error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo number_format($stats['total_employees']); ?></div>
                <div class="stat-label">Active Employees</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo format_currency($stats['average_salary']); ?></div>
                <div class="stat-label">Average Salary</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon">💳</div>
                <div class="stat-value"><?php echo format_currency($stats['pending_payouts']); ?></div>
                <div class="stat-label">Pending Payouts</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo count($stats['recent_payrolls']); ?></div>
                <div class="stat-label">Recent Batches</div>
            </div>
        </div>

        <!-- Current Month Status -->
        <?php if ($stats['current_payroll']): ?>
            <div class="current-payroll-card">
                <h2>📅 Current Month: <?php echo format_month_display($stats['current_payroll']['month']); ?></h2>
                <div class="current-payroll-details">
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value"><?php echo get_status_badge($stats['current_payroll']['status']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Employees:</span>
                        <span class="detail-value"><?php echo $stats['current_payroll']['total_employees']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Amount:</span>
                        <span class="detail-value"><?php echo format_currency($stats['current_payroll']['total_amount']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Created By:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($stats['current_payroll']['created_by_name']); ?></span>
                    </div>
                </div>
                <div class="current-payroll-actions">
                    <?php if ($stats['current_payroll']['status'] === 'Draft'): ?>
                        <a href="review.php?id=<?php echo $stats['current_payroll']['id']; ?>" class="btn btn-primary">✏️ Review & Edit</a>
                    <?php elseif ($stats['current_payroll']['status'] === 'Reviewed'): ?>
                        <a href="review.php?id=<?php echo $stats['current_payroll']['id']; ?>" class="btn btn-warning">🔒 Lock Payroll</a>
                    <?php elseif ($stats['current_payroll']['status'] === 'Locked'): ?>
                        <a href="review.php?id=<?php echo $stats['current_payroll']['id']; ?>" class="btn btn-success">💳 Mark as Paid</a>
                    <?php else: ?>
                        <a href="review.php?id=<?php echo $stats['current_payroll']['id']; ?>" class="btn btn-secondary">👁️ View Details</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-payroll-card">
                <div class="no-payroll-icon">📭</div>
                <h3>No Payroll for Current Month</h3>
                <p>Generate payroll for <?php echo date('F Y'); ?> to process salaries</p>
                <a href="create.php" class="btn btn-primary btn-lg">➕ Generate Payroll Now</a>
            </div>
        <?php endif; ?>

        <!-- Recent Payrolls -->
        <div class="recent-payrolls-section">
            <h2>📊 Recent Payroll Batches</h2>
            <?php if (!empty($stats['recent_payrolls'])): ?>
                <div class="payroll-grid">
                    <?php foreach ($stats['recent_payrolls'] as $payroll): ?>
                        <div class="payroll-card">
                            <div class="payroll-card-header">
                                <h3><?php echo format_month_display($payroll['month']); ?></h3>
                                <?php echo get_status_badge($payroll['status']); ?>
                            </div>
                            <div class="payroll-card-body">
                                <div class="payroll-stat">
                                    <span class="payroll-stat-label">Employees:</span>
                                    <span class="payroll-stat-value"><?php echo $payroll['total_employees']; ?></span>
                                </div>
                                <div class="payroll-stat">
                                    <span class="payroll-stat-label">Total Amount:</span>
                                    <span class="payroll-stat-value"><?php echo format_currency($payroll['total_amount']); ?></span>
                                </div>
                                <div class="payroll-stat">
                                    <span class="payroll-stat-label">Created:</span>
                                    <span class="payroll-stat-value"><?php echo date('d M Y', strtotime($payroll['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="payroll-card-footer">
                                <a href="review.php?id=<?php echo $payroll['id']; ?>" class="btn btn-sm btn-secondary">View Details →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No payroll batches found. Generate your first payroll to get started!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <div class="quick-links-section">
            <h2>⚡ Quick Actions</h2>
            <div class="quick-links-grid">
                <a href="create.php" class="quick-link-card">
                    <div class="quick-link-icon">➕</div>
                    <h3>Generate Payroll</h3>
                    <p>Create new monthly payroll batch</p>
                </a>
                <a href="list.php" class="quick-link-card">
                    <div class="quick-link-icon">📋</div>
                    <h3>View All Payrolls</h3>
                    <p>Browse payroll history</p>
                </a>
                <a href="reports.php" class="quick-link-card">
                    <div class="quick-link-icon">📊</div>
                    <h3>Reports</h3>
                    <p>Payroll analytics and exports</p>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card.green {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-card.orange {
    background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
}

.stat-card.blue {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card.purple {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon {
    font-size: 36px;
    margin-bottom: 12px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
}

.stat-label {
    opacity: 0.9;
    font-size: 14px;
}

.current-payroll-card {
    background: white;
    padding: 28px;
    border-radius: 8px;
    margin-bottom: 30px;
    border: 2px solid #003581;
}

.current-payroll-card h2 {
    color: #003581;
    margin-bottom: 20px;
}

.current-payroll-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.detail-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.current-payroll-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.no-payroll-card {
    background: white;
    padding: 60px 28px;
    border-radius: 8px;
    margin-bottom: 30px;
    text-align: center;
    border: 2px dashed #ccc;
}

.no-payroll-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.no-payroll-card h3 {
    color: #003581;
    margin-bottom: 12px;
}

.no-payroll-card p {
    color: #666;
    margin-bottom: 24px;
}

.recent-payrolls-section {
    margin-bottom: 30px;
}

.recent-payrolls-section h2 {
    color: #003581;
    margin-bottom: 20px;
}

.payroll-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.payroll-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.payroll-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.payroll-card-header {
    background: #f8f9fa;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e0e0e0;
}

.payroll-card-header h3 {
    margin: 0;
    color: #003581;
    font-size: 18px;
}

.payroll-card-body {
    padding: 16px;
}

.payroll-stat {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.payroll-stat:last-child {
    border-bottom: none;
}

.payroll-stat-label {
    font-size: 14px;
    color: #666;
}

.payroll-stat-value {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.payroll-card-footer {
    padding: 16px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-draft {
    background: #ffc107;
    color: #000;
}

.badge-reviewed {
    background: #17a2b8;
    color: white;
}

.badge-locked {
    background: #dc3545;
    color: white;
}

.badge-paid {
    background: #28a745;
    color: white;
}

.quick-links-section h2 {
    color: #003581;
    margin-bottom: 20px;
}

.quick-links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.quick-link-card {
    background: white;
    padding: 24px;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.quick-link-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.quick-link-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.quick-link-card h3 {
    color: #003581;
    margin-bottom: 8px;
    font-size: 18px;
}

.quick-link-card p {
    color: #666;
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
}

.btn-sm {
    padding: 6px 16px;
    font-size: 13px;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
