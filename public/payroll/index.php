<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'employees.view');

if (!payroll_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$stats = get_payroll_statistics($conn);
$active_tab = $_GET['tab'] ?? 'all';
$filter_type = ($active_tab === 'salary') ? 'Salary' : (($active_tab === 'reimbursement') ? 'Reimbursement' : ($_GET['type'] ?? ''));
$filter_month = $_GET['month'] ?? '';
$filter_status = $_GET['status'] ?? '';

$payrolls = get_all_payrolls($conn, $filter_type, $filter_month, $filter_status);

$page_title = 'Payroll Management - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.pr-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
.pr-header-btns{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.pr-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:30px}
.pr-card{padding:24px;border-radius:12px;color:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);transition:transform 0.2s}
.pr-card:hover{transform:translateY(-2px);box-shadow:0 6px 12px rgba(0,0,0,0.15)}
.pr-card:nth-child(1){background:linear-gradient(135deg,#667eea 0%,#764ba2 100%)}
.pr-card:nth-child(2){background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%)}
.pr-card:nth-child(3){background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%)}
.pr-card:nth-child(4){background:linear-gradient(135deg,#43e97b 0%,#38f9d7 100%)}
.pr-num{font-size:32px;font-weight:700;margin:10px 0}
.pr-lbl{font-size:14px;opacity:0.9}
.pr-tabs{display:flex;gap:10px;margin-bottom:30px;border-bottom:2px solid #e0e0e0;overflow-x:auto}
.pr-tab{padding:12px 24px;cursor:pointer;border:none;background:none;font-size:16px;font-weight:600;color:#666;border-bottom:3px solid transparent;white-space:nowrap;transition:all 0.2s}
.pr-tab:hover{color:#003581;background:#f8f9fa}
.pr-tab.active{color:#003581;border-bottom-color:#003581}
.pr-filter{display:grid;grid-template-columns:repeat(3,1fr) auto;gap:15px;background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05)}
.pr-tbl-cont{background:white;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);overflow-x:auto}
.pr-tbl{width:100%;border-collapse:collapse}
.pr-tbl th{background:#f8f9fa;padding:16px;text-align:left;font-weight:600;color:#003581;border-bottom:2px solid #dee2e6;white-space:nowrap}
.pr-tbl td{padding:14px 16px;border-bottom:1px solid #dee2e6;vertical-align:middle}
.pr-tbl tr:hover{background:#f8f9fa}
.pr-tbl tbody tr:last-child td{border-bottom:none}
.pr-actions{display:flex;gap:5px;flex-wrap:wrap}
.pr-empty{text-align:center;padding:60px 20px;color:#666}
.pr-empty-icon{font-size:48px;margin-bottom:15px;opacity:0.5}
@media (max-width:1024px){.pr-stats{grid-template-columns:repeat(2,1fr)}.pr-filter{grid-template-columns:1fr 1fr}}
@media (max-width:768px){.pr-header-flex{flex-direction:column;align-items:stretch}.pr-header-flex>div{width:100%}.pr-header-btns{flex-direction:column;width:100%}.pr-header-btns .btn{width:100%;text-align:center}.pr-stats{grid-template-columns:1fr}.pr-filter{grid-template-columns:1fr}.pr-filter button{width:100%}.pr-tbl{font-size:13px}.pr-tbl th,.pr-tbl td{padding:10px}.pr-actions{flex-direction:column}.pr-actions .btn{width:100%;text-align:center}}
</style>
<div class="main-wrapper">
<div class="main-content">
<div class="page-header">
<div class="pr-header-flex">
<div>
<h1>💰 Payroll Management</h1>
<p>Manage salary and reimbursement payrolls with draft/lock workflow</p>
</div>
<div class="pr-header-btns">
<a href="generate.php" class="btn" style="display:inline-flex;align-items:center;gap:8px">
<span style="font-size:18px">➕</span> Generate Payroll
</a>
<a href="export.php" class="btn btn-accent" style="display:inline-flex;align-items:center;gap:8px">
<span style="font-size:16px">📊</span> Export Reports
</a>
</div>
</div>
</div>
<div class="pr-stats">
<div class="pr-card">
<div class="pr-lbl">Total Payrolls (Month)</div>
<div class="pr-num"><?php echo $stats['total_payrolls']; ?></div>
</div>
<div class="pr-card">
<div class="pr-lbl">Total Salary Outflow</div>
<div class="pr-num"><?php echo format_currency($stats['salary_outflow']); ?></div>
</div>
<div class="pr-card">
<div class="pr-lbl">Pending Reimbursements</div>
<div class="pr-num"><?php echo format_currency($stats['reimbursement_pending']); ?></div>
</div>
<div class="pr-card">
<div class="pr-lbl">Locked This Month</div>
<div class="pr-num"><?php echo $stats['locked_this_month']; ?></div>
</div>
</div>
<div class="pr-tabs">
<button class="pr-tab <?php echo $active_tab==='all'?'active':''; ?>" onclick="location.href='?tab=all'">All Payrolls</button>
<button class="pr-tab <?php echo $active_tab==='salary'?'active':''; ?>" onclick="location.href='?tab=salary'">Salary</button>
<button class="pr-tab <?php echo $active_tab==='reimbursement'?'active':''; ?>" onclick="location.href='?tab=reimbursement'">Reimbursement</button>
</div>
<form method="GET" class="pr-filter">
<input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
<div>
<label style="display:block;margin-bottom:5px;font-weight:500;color:#495057">Type</label>
<select name="type" class="form-control">
<option value="">All Types</option>
<option value="Salary" <?php echo $filter_type==='Salary'?'selected':''; ?>>Salary</option>
<option value="Reimbursement" <?php echo $filter_type==='Reimbursement'?'selected':''; ?>>Reimbursement</option>
</select>
</div>
<div>
<label style="display:block;margin-bottom:5px;font-weight:500;color:#495057">Month</label>
<input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($filter_month); ?>" placeholder="Select month">
</div>
<div>
<label style="display:block;margin-bottom:5px;font-weight:500;color:#495057">Status</label>
<select name="status" class="form-control">
<option value="">All Status</option>
<option value="Draft" <?php echo $filter_status==='Draft'?'selected':''; ?>>Draft</option>
<option value="Locked" <?php echo $filter_status==='Locked'?'selected':''; ?>>Locked</option>
<option value="Paid" <?php echo $filter_status==='Paid'?'selected':''; ?>>Paid</option>
</select>
</div>
<div style="display:flex;align-items:flex-end">
<button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px">
<span style="font-size:16px">🔍</span> Search
</button>
</div>
</form>
<div class="pr-tbl-cont">
<table class="pr-tbl">
<thead>
<tr>
<th>ID</th><th>Type</th><th>Month</th><th>Employees</th><th>Amount</th><th>Status</th><th>Created</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($payrolls)): ?>
<tr><td colspan="8" class="pr-empty">
<div class="pr-empty-icon">📋</div>
<strong style="display:block;margin-bottom:8px;font-size:16px">No payrolls found</strong>
<span style="color:#999">Click "Generate Payroll" to create your first payroll batch</span>
</td></tr>
<?php else: ?>
<?php foreach ($payrolls as $pr): ?>
<tr>
<td><strong style="color:#003581">#<?php echo $pr['id']; ?></strong></td>
<td><span class="badge badge-<?php echo strtolower($pr['payroll_type']); ?>"><?php echo $pr['payroll_type']; ?></span></td>
<td><?php echo get_month_name($pr['month_year']); ?></td>
<td><strong><?php echo $pr['item_count']??0; ?></strong> <small style="color:#999">employees</small></td>
<td><strong style="color:#003581"><?php echo format_currency($pr['total_amount']); ?></strong></td>
<td><span class="badge badge-<?php echo strtolower($pr['status']); ?>"><?php echo $pr['status']; ?></span></td>
<td><small style="color:#666"><?php echo date('d M Y',strtotime($pr['created_at'])); ?></small></td>
<td>
<div class="pr-actions">
<a href="view.php?id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-info" title="View Details">👁️ View</a>
<?php if ($pr['status']==='Draft' && authz_user_can($conn,'employees.delete')): ?>
<a href="delete.php?id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this draft payroll?')" title="Delete Draft">🗑️ Delete</a>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<?php if (!empty($payrolls)): ?>
<div style="background:white;padding:15px 20px;border-top:1px solid #dee2e6;border-radius:0 0 8px 8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
<div style="color:#666;font-size:14px">
Showing <strong><?php echo count($payrolls); ?></strong> payroll<?php echo count($payrolls)>1?'s':''; ?>
<?php if ($filter_type || $filter_month || $filter_status): ?>
<span style="color:#999">• Filtered</span>
<?php endif; ?>
</div>
<div style="display:flex;gap:8px">
<?php if ($filter_type || $filter_month || $filter_status): ?>
<a href="?tab=<?php echo htmlspecialchars($active_tab); ?>" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
<?php endif; ?>
<a href="export.php" class="btn btn-sm btn-accent">📊 Export All</a>
</div>
</div>
<?php endif; ?>

</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>