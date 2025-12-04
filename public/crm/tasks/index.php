<?php
require_once __DIR__ . '/common.php';

authz_require_permission($conn, 'crm_tasks', 'view_all');

if (!crm_tables_exist($conn)) { require_once __DIR__ . '/../onboarding.php'; exit; }

// Get permissions
$tasks_permissions = authz_get_permission_set($conn, 'crm_tasks');

// Detect columns
$has_lead_id = crm_tasks_has_column($conn,'lead_id');
$has_assigned_to = crm_tasks_has_column($conn,'assigned_to');
$has_follow_up_date = crm_tasks_has_column($conn,'follow_up_date');
$has_follow_up_type = crm_tasks_has_column($conn,'follow_up_type');
$has_closed_by = crm_tasks_has_column($conn,'closed_by');

// Filters
$filter_employee = isset($_GET['employee']) && is_numeric($_GET['employee']) ? (int)$_GET['employee'] : 0;
$filter_lead = isset($_GET['lead']) && is_numeric($_GET['lead']) ? (int)$_GET['lead'] : 0;
$filter_status = trim($_GET['status'] ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to = trim($_GET['date_to'] ?? '');
$search = trim($_GET['search'] ?? '');

// Metrics
$metrics = ['total'=>0,'today'=>0,'overdue'=>0,'completed'=>0];
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL"); if ($res){$metrics['total']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} 
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND due_date = CURDATE()"); if ($res){$metrics['today']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} 
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND status <> 'Completed' AND due_date IS NOT NULL AND due_date < CURDATE()"); if ($res){$metrics['overdue']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} 
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND status = 'Completed'"); if ($res){$metrics['completed']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} 

// Build query
$select_cols = crm_tasks_select_columns($conn);
$joins = '';
if ($has_lead_id) { $joins .= ' LEFT JOIN crm_leads l ON c.lead_id = l.id '; }
if ($has_assigned_to) { $joins .= ' LEFT JOIN employees e ON c.assigned_to = e.id '; }

$where = ['c.deleted_at IS NULL'];
$params = []; $types = '';
if ($has_assigned_to && $filter_employee>0){ $where[]='c.assigned_to = ?'; $types.='i'; $params[]=$filter_employee; }
if ($has_lead_id && $filter_lead>0){ $where[]='c.lead_id = ?'; $types.='i'; $params[]=$filter_lead; }
if ($filter_status!==''){ $where[]='c.status = ?'; $types.='s'; $params[]=$filter_status; }
if ($filter_date_from!==''){ $where[]='DATE(c.due_date) >= ?'; $types.='s'; $params[]=$filter_date_from; }
if ($filter_date_to!==''){ $where[]='DATE(c.due_date) <= ?'; $types.='s'; $params[]=$filter_date_to; }
if ($search!==''){ $where[]='(c.title LIKE ? OR c.description LIKE ?)'; $types.='ss'; $s='%'.$search.'%'; $params[]=$s; $params[]=$s; }
$where_clause = implode(' AND ',$where);

$lead_select = $has_lead_id ? ', l.name AS lead_name, l.company_name AS lead_company' : '';
$emp_select = $has_assigned_to ? ', e.first_name AS assigned_first, e.last_name AS assigned_last, e.employee_code AS assigned_code' : '';
$sql = "SELECT $select_cols $lead_select $emp_select FROM crm_tasks c $joins WHERE $where_clause ORDER BY c.due_date IS NULL, c.due_date ASC, c.id DESC";

$stmt = mysqli_prepare($conn,$sql); $tasks=[]; if ($stmt){ if($types!==''){ mysqli_stmt_bind_param($stmt,$types,...$params);} mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); while($res && ($row=mysqli_fetch_assoc($res))){ $tasks[]=$row; } if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);} 

$employees = crm_fetch_employees($conn);
$leads = [];
if ($has_lead_id) {
  // fetch leads similar to other modules
  $leads = [];
  $res = mysqli_query($conn, "SELECT id, name, company_name FROM crm_leads WHERE deleted_at IS NULL ORDER BY name");
  if ($res){ while($r=mysqli_fetch_assoc($res)){ $leads[]=$r; } mysqli_free_result($res);} 
}

$page_title = 'All Tasks - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<style>
.tasks-header-flex {display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.tasks-header-buttons {display:flex;gap:8px;flex-wrap:wrap;}
@media (max-width:768px){
.tasks-header-flex{flex-direction:column;align-items:stretch;}
.tasks-header-flex > div:first-child h1{font-size:24px;}
.tasks-header-flex > div:first-child p{font-size:14px;}
.tasks-header-buttons{width:100%;flex-direction:column;gap:10px;}
.tasks-header-buttons .btn{width:100%;text-align:center;}
}
@media (max-width:480px){
.tasks-header-flex > div:first-child h1{font-size:22px;}
.tasks-header-flex > div:first-child p{font-size:13px;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="tasks-header-flex">
        <div>
          <h1>🧰 All Tasks</h1>
          <p>View and manage all CRM tasks</p>
        </div>
        <div class="tasks-header-buttons">
          <a href="../index.php" class="btn btn-secondary">← CRM Dashboard</a>
          <a href="my.php" class="btn btn-accent">🧰 My Tasks</a>
          <?php if ($tasks_permissions['can_create'] || $IS_SUPER_ADMIN): ?>
          <a href="add.php" class="btn">➕ New Task</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['total']); ?></div><div>Total Tasks</div></div>
      <div class="card" style="background:linear-gradient(135deg,#0284c7 0%,#06b6d4 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['today']); ?></div><div>Due Today</div></div>
      <div class="card" style="background:linear-gradient(135deg,#ef4444 0%,#f97316 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['overdue']); ?></div><div>Overdue</div></div>
      <div class="card" style="background:linear-gradient(135deg,#16a34a 0%,#22c55e 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['completed']); ?></div><div>Completed</div></div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">🔍 Filter Tasks</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
        <?php if ($has_assigned_to): ?>
        <div class="form-group" style="margin:0;">
          <label>Employee</label>
          <select name="employee" class="form-control">
            <option value="">All Employees</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo (int)$emp['id']; ?>" <?php echo $filter_employee===(int)$emp['id']?'selected':''; ?>><?php echo htmlspecialchars(trim(($emp['employee_code']??'').' - '.($emp['first_name']??'').' '.($emp['last_name']??''))); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <?php if ($has_lead_id): ?>
        <div class="form-group" style="margin:0;">
          <label>Lead</label>
          <select name="lead" class="form-control">
            <option value="">All Leads</option>
            <?php foreach ($leads as $lead): ?>
              <option value="<?php echo (int)$lead['id']; ?>" <?php echo $filter_lead===(int)$lead['id']?'selected':''; ?>><?php echo htmlspecialchars($lead['name'] . (isset($lead['company_name']) && $lead['company_name'] ? ' ('.$lead['company_name'].')' : '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin:0;">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="">All</option>
            <?php foreach (crm_task_statuses() as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $filter_status===$s?'selected':''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label>Due From</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>

        <div class="form-group" style="margin:0;">
          <label>Due To</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
        </div>

        <div class="form-group" style="margin:0;">
          <label>Search</label>
          <input type="text" name="search" class="form-control" placeholder="Title or description..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div><button type="submit" class="btn" style="width:100%;">Apply Filters</button></div>
        <div><a href="index.php" class="btn btn-accent" style="width:100%;display:block;text-decoration:none;text-align:center;">Clear</a></div>
      </form>
    </div>

    <!-- Task List -->
    <div class="card">
      <?php if (!$tasks): ?>
        <div class="alert alert-info" style="margin:0;">No tasks found with the selected filters.</div>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <h3 style="margin:0;color:#003581;">📋 Task Directory (<?php echo count($tasks); ?>)</h3>
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
              <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Title</th>
              <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Due</th>
              <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Status</th>
              <?php if ($has_lead_id): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Lead</th><?php endif; ?>
              <?php if ($has_assigned_to): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Assigned To</th><?php endif; ?>
              <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
            </tr></thead>
            <tbody>
              <?php foreach ($tasks as $t): $due = crm_task_get($t,'due_date'); $is_overdue = ($due && strtotime($due) < strtotime('today') && crm_task_get($t,'status')!=='Completed'); ?>
              <tr style="border-bottom:1px solid #e1e8ed;">
                <td style="padding:12px;">
                  <div style="font-weight:600;color:#003581;"><a href="view.php?id=<?php echo (int)$t['id']; ?>" style="color:#003581;text-decoration:none;"><?php echo htmlspecialchars(crm_task_get($t,'title')); ?></a></div>
                </td>
                <td style="padding:12px;font-size:13px;">
                  <?php echo $due ? htmlspecialchars(date('d M Y', strtotime($due))) : '—'; ?>
                  <?php if ($is_overdue): ?><div style="color:#dc2626;font-size:11px;margin-top:2px;">Overdue</div><?php endif; ?>
                </td>
                <td style="padding:12px;font-size:13px;"><?php echo htmlspecialchars(crm_task_get($t,'status','Pending')); ?></td>
                <?php if ($has_lead_id): ?>
                <td style="padding:12px;">
                  <?php if (crm_task_get($t,'lead_id')): ?>
                    <a href="../leads/view.php?id=<?php echo (int)$t['lead_id']; ?>" style="color:#003581;text-decoration:none;">
                      <?php echo htmlspecialchars(crm_task_get($t,'lead_name','Lead #'.(int)$t['lead_id'])); ?>
                    </a>
                  <?php else: ?><span style="color:#6c757d;">—</span><?php endif; ?>
                </td>
                <?php endif; ?>
                <?php if ($has_assigned_to): ?>
                <td style="padding:12px;">
                  <span style="background:#e3f2fd;color:#003581;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:500;display:inline-block;">
                    <?php echo htmlspecialchars(crm_task_employee_label(crm_task_get($t,'assigned_code',''), crm_task_get($t,'assigned_first',''), crm_task_get($t,'assigned_last',''))); ?>
                  </span>
                </td>
                <?php endif; ?>
                <td style="padding:12px;text-align:center;white-space:nowrap;">
                  <a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;text-decoration:none;background:#17a2b8;color:#fff;">View</a>
                  <?php if ($tasks_permissions['can_edit_all'] || $IS_SUPER_ADMIN): ?>
                  <a href="edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;text-decoration:none;">Edit</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); } require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
