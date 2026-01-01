<?php
$page_title = 'Payroll Setup Required - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
<div class="main-content">
<div class="setup-container" style="max-width:900px;margin:60px auto;padding:40px">
<div style="text-align:center;margin-bottom:40px">
<div style="font-size:80px;margin-bottom:20px"></div>
<h1 style="color:#003581;font-size:32px;margin-bottom:20px">Payroll Module Setup Required</h1>
<p style="font-size:18px;color:#666;line-height:1.6;margin-bottom:40px">
The Payroll Module streamlines salary and reimbursement processing with integrated attendance tracking and compliance-ready records.
</p>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin:40px 0">
<div style="background:white;padding:24px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center">
<div style="font-size:36px;margin-bottom:12px"></div>
<h3 style="color:#003581;font-size:16px;margin-bottom:8px">Salary Processing</h3>
<p style="font-size:14px;color:#666">Automated monthly salary calculation with attendance integration</p>
</div>
<div style="background:white;padding:24px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center">
<div style="font-size:36px;margin-bottom:12px"></div>
<h3 style="color:#003581;font-size:16px;margin-bottom:8px">Reimbursement Payouts</h3>
<p style="font-size:14px;color:#666">Process approved reimbursements in organized payroll batches</p>
</div>
<div style="background:white;padding:24px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center">
<div style="font-size:36px;margin-bottom:12px"></div>
<h3 style="color:#003581;font-size:16px;margin-bottom:8px">Draft & Lock</h3>
<p style="font-size:14px;color:#666">Review, edit, and finalize before payment processing</p>
</div>
<div style="background:white;padding:24px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center">
<div style="font-size:36px;margin-bottom:12px"></div>
<h3 style="color:#003581;font-size:16px;margin-bottom:8px">Reports & Audit</h3>
<p style="font-size:14px;color:#666">Export registers, salary slips, and transaction logs</p>
</div>
</div>
<div style="background:#f8f9fa;padding:24px;border-radius:8px;margin:40px 0">
<h3 style="color:#003581;margin-bottom:16px"> What will be created:</h3>
<ul style="list-style:none;padding:0">
<li style="padding:8px 0;font-size:15px;color:#444"> Payroll Master - Unified salary and reimbursement tracking</li>
<li style="padding:8px 0;font-size:15px;color:#444"> Payroll Items - Detailed employee-level records</li>
<li style="padding:8px 0;font-size:15px;color:#444"> Activity Log - Complete audit trail and compliance records</li>
</ul>
</div>
<div style="text-align:center;margin:40px 0">
<a href="<?php echo APP_URL; ?>/scripts/setup_payroll_tables.php" style="display:inline-block;padding:16px 32px;background:#003581;color:white;text-decoration:none;border-radius:8px;font-size:16px;font-weight:600"> Run Setup Now</a>
<a href="<?php echo APP_URL; ?>/public/index.php" style="display:inline-block;padding:16px 32px;background:#6c757d;color:white;text-decoration:none;border-radius:8px;font-size:16px;font-weight:600;margin-left:10px"> Back to Dashboard</a>
</div>
<div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6c757d;text-align:center">
<p style="margin:0"><strong>Tip:</strong> You can also install multiple modules at once using the <a href="<?php echo APP_URL; ?>/setup/module_installer.php?from=settings" style="color:#003581;text-decoration:underline">Unified Module Installer</a></p>
</div>
<div style="background:#fff3cd;padding:20px;border-radius:8px;border-left:4px solid #ffc107;margin-top:40px">
<h4 style="color:#856404;margin:0 0 10px 0"> Prerequisites:</h4>
<p style="color:#856404;margin:0;font-size:14px">
Ensure Employees, Attendance, and Reimbursements modules are set up. Payroll integrates with these systems for automated calculations and data sync.
</p>
</div>
</div>
</div>
</div>
<style>
@media (max-width:768px){
.setup-container{padding:20px!important}
.setup-container h1{font-size:24px!important}
.setup-container>div:nth-child(2){grid-template-columns:1fr!important}
}
</style>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
