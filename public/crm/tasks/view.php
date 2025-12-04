<?php
require_once __DIR__ . '/common.php';

$task_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($task_id <= 0) { flash_add('error','Invalid task ID','crm'); header('Location: index.php'); exit; }

if (!crm_tables_exist($conn)) { require_once __DIR__ . '/../onboarding.php'; exit; }

$current_employee_id = crm_current_employee_id($conn, $CURRENT_USER_ID);

// Get permissions
$tasks_permissions = authz_get_permission_set($conn, 'crm_tasks');

$has_lead_id = crm_tasks_has_column($conn,'lead_id');
$has_assigned_to = crm_tasks_has_column($conn,'assigned_to');
$has_created_by = crm_tasks_has_column($conn,'created_by');
$has_follow_up_date = crm_tasks_has_column($conn,'follow_up_date');
$has_follow_up_type = crm_tasks_has_column($conn,'follow_up_type');
$has_completion_notes = crm_tasks_has_column($conn,'completion_notes');
$has_completed_at = crm_tasks_has_column($conn,'completed_at');
$has_closed_by = crm_tasks_has_column($conn,'closed_by');

$select_cols = crm_tasks_select_columns($conn);
$joins = '';
$emp_select = '';
if ($has_assigned_to) { $joins .= ' LEFT JOIN employees e1 ON c.assigned_to = e1.id'; $emp_select .= ', e1.first_name AS assigned_first, e1.last_name AS assigned_last'; }
if ($has_created_by) { $joins .= ' LEFT JOIN employees e2 ON c.created_by = e2.id'; $emp_select .= ', e2.first_name AS created_first, e2.last_name AS created_last'; }
$lead_select = '';
if ($has_lead_id) { $joins = ' LEFT JOIN crm_leads l ON c.lead_id = l.id ' . $joins; $lead_select = ', l.name AS lead_name, l.company_name AS lead_company'; }

$sql = "SELECT $select_cols $lead_select $emp_select FROM crm_tasks c $joins WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1";
$stmt = mysqli_prepare($conn,$sql); if (!$stmt){ if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); } die('Failed to prepare'); }
mysqli_stmt_bind_param($stmt,'i',$task_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $task = $res?mysqli_fetch_assoc($res):null; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);
if (!$task) { flash_add('error','Task not found','crm'); if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); } header('Location: index.php'); exit; }

if ($has_assigned_to && !$tasks_permissions['can_view_all'] && !$IS_SUPER_ADMIN && (int)($task['assigned_to'] ?? 0) !== (int)$current_employee_id) {
  flash_add('error','You do not have permission to view this task','crm'); if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); } header('Location: my.php'); exit;
}

$page_title = 'Task Details - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

function v($val,$fallback='—'){ return ($val===null||$val==='')?$fallback:htmlspecialchars((string)$val); }
$due = $task['due_date'] ?? null;
$overdue = ($due && strtotime($due) < strtotime('today') && ($task['status'] ?? '') !== 'Completed');
?>

<style>
.task-view-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.task-view-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}
.task-view-profile-card{display:flex;gap:20px;align-items:center;flex-wrap:wrap;}
.task-view-profile-info{flex:1;min-width:280px;}
.task-view-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;}

@media (max-width:768px){
.task-view-header-flex{flex-direction:column;align-items:stretch;}
.task-view-header-buttons{width:100%;flex-direction:column;gap:10px;}
.task-view-header-buttons .btn{width:100%;text-align:center;}
.task-view-profile-card{flex-direction:column;text-align:center;}
.task-view-profile-info{min-width:100%;}
.task-view-info-grid{grid-template-columns:1fr;gap:15px;}
}

@media (max-width:480px){
.task-view-header-flex h1{font-size:1.5rem;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="task-view-header-flex">
        <div>
          <h1>🧰 Task Details</h1>
          <p>Full task information and timeline</p>
        </div>
        <div class="task-view-header-buttons">
          <?php if ($tasks_permissions['can_edit_all'] || $IS_SUPER_ADMIN): ?><a href="edit.php?id=<?php echo $task_id; ?>" class="btn">✏️ Edit Task</a><?php endif; ?>
          <a href="<?php echo ($tasks_permissions['can_view_all'] || $IS_SUPER_ADMIN) ? 'index.php' : 'my.php'; ?>" class="btn btn-accent">← Back to List</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <div class="card task-view-profile-card">
      <div style="width:84px;height:84px;border-radius:50%;background:#003581;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:32px;">🧰</div>
      <div class="task-view-profile-info">
        <div style="font-size:20px;color:#003581;font-weight:700;"><?php echo v($task['title'] ?? 'Untitled Task'); ?></div>
        <div style="color:#6c757d;font-size:13px;margin-top:4px;">Due: <?php echo $due ? htmlspecialchars(date('d M Y', strtotime($due))) : '—'; ?></div>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
          <span style="background:#e2e3e5;color:#111827;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Status: <?php echo v($task['status'] ?? 'Pending'); ?></span>
          <?php if ($overdue): ?><span style="background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Overdue</span><?php endif; ?>
          <?php if ($has_follow_up_date && ($task['follow_up_date'] ?? null)): ?><span style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Follow-up: <?php echo htmlspecialchars(date('d M Y', strtotime($task['follow_up_date']))); ?></span><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="task-view-info-grid">
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📝 Task Information</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Title:</strong> <?php echo v($task['title']); ?></div>
          <div><strong>Status:</strong> <?php echo v($task['status']); ?></div>
          <div><strong>Due Date:</strong> <?php echo $due? htmlspecialchars(date('d M Y', strtotime($due))) : '—'; ?></div>
          <?php if ($has_follow_up_type && ($task['follow_up_type'] ?? null)): ?><div><strong>Follow-up Type:</strong> <?php echo v($task['follow_up_type']); ?></div><?php endif; ?>
          <?php if (($task['location'] ?? '') !== ''): ?><div><strong>Location:</strong> <?php echo v($task['location']); ?></div><?php endif; ?>
        </div>
      </div>

      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">👥 Assignment</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <?php if ($has_assigned_to): ?><div><strong>Assigned To:</strong> <?php echo v(trim(($task['assigned_first'] ?? '').' '.($task['assigned_last'] ?? ''))); ?></div><?php endif; ?>
          <?php if ($has_created_by): ?><div><strong>Created By:</strong> <?php echo v(trim(($task['created_first'] ?? '').' '.($task['created_last'] ?? ''))); ?></div><?php endif; ?>
          <div><strong>Created At:</strong> <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($task['created_at']))); ?></div>
          <?php if ($has_completed_at && ($task['completed_at'] ?? null)): ?><div><strong>Completed At:</strong> <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($task['completed_at']))); ?></div><?php endif; ?>
        </div>
      </div>

      <?php if ($has_lead_id && ($task['lead_id'] ?? 0)): ?>
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">👤 Related Lead</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Lead:</strong> <a href="../leads/view.php?id=<?php echo (int)$task['lead_id']; ?>" style="color:#003581;text-decoration:none;font-weight:600;"><?php echo v($task['lead_name'] ?? ('Lead #'.(int)$task['lead_id'])); ?></a></div>
          <?php if (($task['lead_company'] ?? '') !== ''): ?><div><strong>Company:</strong> <?php echo v($task['lead_company']); ?></div><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🗒️ Description</h3>
      <div style="font-size:14px;color:#495057;line-height:1.6;white-space:pre-wrap;"><?php echo nl2br(v($task['description'],'No description.')); ?></div>
    </div>

    <?php if ($has_completion_notes && ($task['completion_notes'] ?? '') !== ''): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">✅ Completion Notes</h3>
      <div style="font-size:14px;color:#495057;line-height:1.6;white-space:pre-wrap;"><?php echo nl2br(v($task['completion_notes'])); ?></div>
    </div>
    <?php endif; ?>

    <?php if (($task['attachment'] ?? '') !== ''): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📎 Attachment</h3>
      <div style="font-size:14px;"><a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars($task['attachment']); ?>" target="_blank" style="background:#e3f2fd;color:#003581;padding:8px 16px;border-radius:12px;text-decoration:none;display:inline-block;">📄 Download Attachment</a></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); } require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
