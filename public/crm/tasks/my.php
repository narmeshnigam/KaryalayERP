<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
$user_role = $_SESSION['role'] ?? 'employee';
$user_id = (int)$_SESSION['user_id'];

$conn = createConnection(true); if (!$conn) { die('DB failed'); }
if (!crm_tables_exist($conn)) { closeConnection($conn); require_once __DIR__ . '/../onboarding.php'; exit; }

$current_employee_id = crm_current_employee_id($conn, $user_id);

$has_lead_id = crm_tasks_has_column($conn,'lead_id');
$has_follow_up_date = crm_tasks_has_column($conn,'follow_up_date');
$has_follow_up_type = crm_tasks_has_column($conn,'follow_up_type');

// Filters
$filter_status = trim($_GET['status'] ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to = trim($_GET['date_to'] ?? '');
$search = trim($_GET['search'] ?? '');

// Metrics for current user
$metrics = ['total'=>0,'today'=>0,'overdue'=>0,'completed'=>0];
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND assigned_to = ?"); if ($stmt){ mysqli_stmt_bind_param($stmt,'i',$current_employee_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); if($res){ $metrics['total']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} mysqli_stmt_close($stmt);} 
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND assigned_to = ? AND due_date = CURDATE()"); if ($stmt){ mysqli_stmt_bind_param($stmt,'i',$current_employee_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); if($res){ $metrics['today']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} mysqli_stmt_close($stmt);} 
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND assigned_to = ? AND status <> 'Completed' AND due_date IS NOT NULL AND due_date < CURDATE()"); if ($stmt){ mysqli_stmt_bind_param($stmt,'i',$current_employee_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); if($res){ $metrics['overdue']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} mysqli_stmt_close($stmt);} 
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND assigned_to = ? AND status = 'Completed'"); if ($stmt){ mysqli_stmt_bind_param($stmt,'i',$current_employee_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); if($res){ $metrics['completed']=(int)mysqli_fetch_assoc($res)['c']; mysqli_free_result($res);} mysqli_stmt_close($stmt);} 

// Build query
$select_cols = crm_tasks_select_columns($conn);
$joins = '';
if ($has_lead_id) { $joins .= ' LEFT JOIN crm_leads l ON c.lead_id = l.id '; }

$where = ['c.deleted_at IS NULL','c.assigned_to = ?'];
$params = [$current_employee_id]; $types = 'i';
if ($filter_status!==''){ $where[]='c.status = ?'; $types.='s'; $params[]=$filter_status; }
if ($filter_date_from!==''){ $where[]='DATE(c.due_date) >= ?'; $types.='s'; $params[]=$filter_date_from; }
if ($filter_date_to!==''){ $where[]='DATE(c.due_date) <= ?'; $types.='s'; $params[]=$filter_date_to; }
if ($search!==''){ $where[]='(c.title LIKE ? OR c.description LIKE ?)'; $types.='ss'; $s='%'.$search.'%'; $params[]=$s; $params[]=$s; }
$where_clause = implode(' AND ',$where);

$lead_select = $has_lead_id ? ', l.name AS lead_name, l.company_name AS lead_company' : '';
$sql = "SELECT $select_cols $lead_select FROM crm_tasks c $joins WHERE $where_clause ORDER BY c.due_date IS NULL, c.due_date ASC, c.id DESC";

$stmt = mysqli_prepare($conn,$sql); $tasks=[]; if ($stmt){ if($types!==''){ mysqli_stmt_bind_param($stmt,$types,...$params);} mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); while($res && ($row=mysqli_fetch_assoc($res))){ $tasks[]=$row; } if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);} 

$leads = [];
if ($has_lead_id) {
  $res = mysqli_query($conn, "SELECT id, name, company_name FROM crm_leads WHERE deleted_at IS NULL ORDER BY name");
  if ($res){ while($r=mysqli_fetch_assoc($res)){ $leads[]=$r; } mysqli_free_result($res);} 
}

$page_title = 'My Tasks - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>🧰 My Tasks</h1>
          <p>Tasks assigned to you</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="../index.php" class="btn btn-secondary">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-accent">🧰 All Tasks</a>
          <a href="add.php" class="btn">➕ New Task</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['total']); ?></div><div>My Total Tasks</div></div>
      <div class="card" style="background:linear-gradient(135deg,#0284c7 0%,#06b6d4 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['today']); ?></div><div>Due Today</div></div>
      <div class="card" style="background:linear-gradient(135deg,#ef4444 0%,#f97316 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['overdue']); ?></div><div>Overdue</div></div>
      <div class="card" style="background:linear-gradient(135deg,#16a34a 0%,#22c55e 100%);color:#fff;text-align:center;"><div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['completed']); ?></div><div>Completed</div></div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">🔍 Filter My Tasks</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
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
        <div><a href="my.php" class="btn btn-accent" style="width:100%;display:block;text-decoration:none;text-align:center;">Clear</a></div>
      </form>
    </div>

    <!-- List -->
    <div class="card">
      <?php if (!$tasks): ?>
        <div class="alert alert-info" style="margin:0;">No tasks found.</div>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <h3 style="margin:0;color:#003581;">📋 My Tasks (<?php echo count($tasks); ?>)</h3>
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
              <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Title</th>
              <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Due</th>
              <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Status</th>
              <?php if ($has_lead_id): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Lead</th><?php endif; ?>
              <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
            </tr></thead>
            <tbody>
              <?php foreach ($tasks as $t): $due = crm_task_get($t,'due_date'); $is_overdue = ($due && strtotime($due) < strtotime('today') && crm_task_get($t,'status')!=='Completed'); ?>
              <tr style="border-bottom:1px solid #e1e8ed;">
                <td style="padding:12px;"><div style="font-weight:600;color:#003581;"><a href="view.php?id=<?php echo (int)$t['id']; ?>" style="color:#003581;text-decoration:none;"><?php echo htmlspecialchars(crm_task_get($t,'title')); ?></a></div></td>
                <td style="padding:12px;font-size:13px;"><?php echo $due ? htmlspecialchars(date('d M Y', strtotime($due))) : '—'; ?><?php if ($is_overdue): ?><div style="color:#dc2626;font-size:11px;margin-top:2px;">Overdue</div><?php endif; ?></td>
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
                <td style="padding:12px;text-align:center;white-space:nowrap;">
                  <a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;text-decoration:none;background:#17a2b8;color:#fff;">View</a>
                  <?php if (crm_role_can_manage($user_role)): ?><a href="edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;text-decoration:none;">Edit</a><?php endif; ?>
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

<?php closeConnection($conn); require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
