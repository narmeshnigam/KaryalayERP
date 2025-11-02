<?php
/**
 * Payroll Module - Onboarding/Setup Page
 * Displayed when payroll tables don't exist
 */

$page_title = 'Payroll Setup Required - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="setup-container">
            <div class="setup-icon">🧾</div>
            <h1>Payroll Module Setup Required</h1>
            <p class="setup-description">
                The Payroll Module database tables have not been created yet. 
                This module will help you automate monthly salary calculations, generate payslips, 
                and manage payroll processing efficiently.
            </p>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Automated Processing</h3>
                    <p>Automatically fetch attendance and reimbursements for accurate salary calculation</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3>Salary Components</h3>
                    <p>Manage allowances, deductions, bonuses, and penalties with flexible configuration</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📄</div>
                    <h3>Payslip Generation</h3>
                    <p>Generate professional payslips with company branding for all employees</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Reports & Analytics</h3>
                    <p>Comprehensive payroll reports, department-wise analysis, and CSV exports</p>
                </div>
            </div>

            <div class="setup-actions">
                <a href="<?php echo APP_URL; ?>/scripts/setup_payroll_tables.php" class="btn btn-primary btn-lg">
                    ▶️ Run Setup Now
                </a>
                <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-secondary btn-lg">
                    ← Back to Dashboard
                </a>
            </div>

            <div class="setup-info">
                <h3>What will be created:</h3>
                <ul>
                    <li>✅ Payroll Master - Manage monthly payroll batches</li>
                    <li>✅ Payroll Records - Individual employee salary records</li>
                    <li>✅ Allowances & Deductions - Salary component configuration</li>
                    <li>✅ Activity Log - Complete audit trail of payroll actions</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.setup-container {
    max-width: 900px;
    margin: 60px auto;
    text-align: center;
    padding: 40px;
}

.setup-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.setup-container h1 {
    color: #003581;
    font-size: 32px;
    margin-bottom: 20px;
}

.setup-description {
    font-size: 18px;
    color: #666;
    line-height: 1.6;
    margin-bottom: 40px;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 40px 0;
}

.feature-card {
    background: white;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.feature-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.feature-icon {
    font-size: 36px;
    margin-bottom: 12px;
}

.feature-card h3 {
    color: #003581;
    font-size: 16px;
    margin-bottom: 8px;
}

.feature-card p {
    font-size: 14px;
    color: #666;
    line-height: 1.4;
}

.setup-actions {
    margin: 40px 0;
}

.btn-lg {
    padding: 16px 32px;
    font-size: 16px;
    margin: 0 10px;
    display: inline-block;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
}

.btn-primary {
    background: #003581;
    color: white;
}

.btn-primary:hover {
    background: #002a66;
    transform: scale(1.05);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.setup-info {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 8px;
    margin-top: 40px;
    text-align: left;
}

.setup-info h3 {
    color: #003581;
    margin-bottom: 16px;
}

.setup-info ul {
    list-style: none;
    padding: 0;
}

.setup-info li {
    padding: 8px 0;
    font-size: 15px;
    color: #444;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
