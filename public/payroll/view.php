<?php
/**
 * Payroll Module - View Employee Payroll Record
 * Detailed breakdown of individual employee salary
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payroll_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$record_id) {
    $_SESSION['flash_error'] = 'Invalid record ID';
    header('Location: index.php');
    exit;
}

// Get record details
$record = get_payroll_record($conn, $record_id);
if (!$record) {
    $_SESSION['flash_error'] = 'Record not found';
    header('Location: index.php');
    exit;
}

// Get payroll details
$payroll = get_payroll_by_id($conn, $record['payroll_id']);

$page_title = 'Payroll Details: ' . $record['employee_name'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>👤 <?php echo htmlspecialchars($record['employee_name']); ?></h1>
                    <p>Payroll: <?php echo format_month_display($record['month']); ?> | Net Pay: <strong><?php echo format_currency($record['net_pay']); ?></strong></p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="review.php?id=<?php echo $record['payroll_id']; ?>" class="btn btn-secondary">← Back to Payroll</a>
                    <?php if (in_array($payroll['status'], ['Locked', 'Paid'])): ?>
                        <a href="payslip.php?id=<?php echo $record['id']; ?>" class="btn btn-primary" target="_blank">📄 View Payslip</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Employee Info Card -->
        <div class="info-card">
            <h2>👤 Employee Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Employee Code:</span>
                    <span class="info-value"><?php echo htmlspecialchars($record['employee_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($record['employee_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Department:</span>
                    <span class="info-value"><?php echo htmlspecialchars($record['department']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Designation:</span>
                    <span class="info-value"><?php echo htmlspecialchars($record['designation']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Month:</span>
                    <span class="info-value"><?php echo format_month_display($record['month']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value"><?php echo get_status_badge($payroll['status']); ?></span>
                </div>
            </div>
        </div>

        <!-- Attendance Info -->
        <div class="attendance-card">
            <h2>📅 Attendance Details</h2>
            <div class="attendance-stats">
                <div class="attendance-stat">
                    <div class="stat-icon">📊</div>
                    <div class="stat-details">
                        <div class="stat-label">Total Working Days</div>
                        <div class="stat-value"><?php echo $record['total_days']; ?> days</div>
                    </div>
                </div>
                <div class="attendance-stat">
                    <div class="stat-icon">✅</div>
                    <div class="stat-details">
                        <div class="stat-label">Present Days</div>
                        <div class="stat-value"><?php echo $record['attendance_days']; ?> days</div>
                    </div>
                </div>
                <div class="attendance-stat">
                    <div class="stat-icon">📈</div>
                    <div class="stat-details">
                        <div class="stat-label">Attendance %</div>
                        <div class="stat-value"><?php echo $record['total_days'] > 0 ? round(($record['attendance_days'] / $record['total_days']) * 100, 2) : 0; ?>%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Breakdown -->
        <div class="breakdown-section">
            <h2>💰 Salary Breakdown</h2>
            
            <!-- Earnings -->
            <div class="breakdown-card earnings-card">
                <h3>➕ Earnings</h3>
                <table class="breakdown-table">
                    <tr>
                        <td>Base Salary (Attendance Adjusted)</td>
                        <td class="amount"><?php echo format_currency($record['base_salary']); ?></td>
                    </tr>
                    <tr>
                        <td>Allowances</td>
                        <td class="amount"><?php echo format_currency($record['allowances']); ?></td>
                    </tr>
                    <tr>
                        <td>Reimbursements</td>
                        <td class="amount"><?php echo format_currency($record['reimbursements']); ?></td>
                    </tr>
                    <?php if ($record['bonus'] > 0): ?>
                        <tr>
                            <td>Bonus</td>
                            <td class="amount"><?php echo format_currency($record['bonus']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong>Total Earnings</strong></td>
                        <td class="amount"><strong><?php 
                            $total_earnings = $record['base_salary'] + $record['allowances'] + $record['reimbursements'] + $record['bonus'];
                            echo format_currency($total_earnings); 
                        ?></strong></td>
                    </tr>
                </table>
            </div>

            <!-- Deductions -->
            <div class="breakdown-card deductions-card">
                <h3>➖ Deductions</h3>
                <table class="breakdown-table">
                    <tr>
                        <td>Standard Deductions</td>
                        <td class="amount"><?php echo format_currency($record['deductions']); ?></td>
                    </tr>
                    <?php if ($record['penalties'] > 0): ?>
                        <tr>
                            <td>Penalties</td>
                            <td class="amount"><?php echo format_currency($record['penalties']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong>Total Deductions</strong></td>
                        <td class="amount"><strong><?php 
                            $total_deductions = $record['deductions'] + $record['penalties'];
                            echo format_currency($total_deductions); 
                        ?></strong></td>
                    </tr>
                </table>
            </div>

            <!-- Net Pay -->
            <div class="net-pay-card">
                <div class="net-pay-label">NET PAY</div>
                <div class="net-pay-amount"><?php echo format_currency($record['net_pay']); ?></div>
                <div class="net-pay-note">Amount to be credited to employee account</div>
            </div>
        </div>

        <!-- Payment Info -->
        <?php if ($record['payment_ref'] || $record['payment_date']): ?>
            <div class="payment-card">
                <h2>💳 Payment Information</h2>
                <div class="payment-grid">
                    <?php if ($record['payment_ref']): ?>
                        <div class="payment-item">
                            <span class="payment-label">Payment Reference:</span>
                            <span class="payment-value"><?php echo htmlspecialchars($record['payment_ref']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($record['payment_date']): ?>
                        <div class="payment-item">
                            <span class="payment-label">Payment Date:</span>
                            <span class="payment-value"><?php echo date('d M Y', strtotime($record['payment_date'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Remarks -->
        <?php if ($record['remarks']): ?>
            <div class="remarks-card">
                <h2>📝 Remarks</h2>
                <p><?php echo nl2br(htmlspecialchars($record['remarks'])); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.info-card, .attendance-card, .breakdown-section, .payment-card, .remarks-card {
    background: white;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.info-card h2, .attendance-card h2, .breakdown-section h2, .payment-card h2, .remarks-card h2 {
    color: #003581;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e0e0e0;
}

.info-grid, .payment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.info-item, .payment-item {
    display: flex;
    flex-direction: column;
}

.info-label, .payment-label {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.info-value, .payment-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.attendance-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.attendance-stat {
    display: flex;
    gap: 16px;
    align-items: center;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-icon {
    font-size: 32px;
}

.stat-label {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #003581;
}

.breakdown-card {
    margin-bottom: 20px;
    padding: 20px;
    border-radius: 8px;
}

.earnings-card {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    border: 2px solid #4caf50;
}

.deductions-card {
    background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
    border: 2px solid #f44336;
}

.breakdown-card h3 {
    color: #003581;
    margin-bottom: 16px;
}

.breakdown-table {
    width: 100%;
    border-collapse: collapse;
}

.breakdown-table tr {
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.breakdown-table td {
    padding: 12px;
    font-size: 15px;
}

.breakdown-table .amount {
    text-align: right;
    font-weight: 600;
}

.breakdown-table .total-row {
    border-top: 2px solid rgba(0,0,0,0.2);
    border-bottom: 2px solid rgba(0,0,0,0.2);
}

.breakdown-table .total-row td {
    padding: 16px 12px;
    font-size: 16px;
}

.net-pay-card {
    background: linear-gradient(135deg, #003581 0%, #002a66 100%);
    color: white;
    padding: 32px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 16px rgba(0,53,129,0.3);
}

.net-pay-label {
    font-size: 16px;
    font-weight: 600;
    opacity: 0.9;
    margin-bottom: 12px;
    letter-spacing: 2px;
}

.net-pay-amount {
    font-size: 48px;
    font-weight: 700;
    margin-bottom: 12px;
}

.net-pay-note {
    font-size: 14px;
    opacity: 0.8;
}

.remarks-card p {
    font-size: 15px;
    line-height: 1.6;
    color: #444;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
