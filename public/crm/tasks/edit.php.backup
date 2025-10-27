<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';

if (!crm_role_can_manage($user_role)) { flash_add('error','You do not have permission to edit tasks','crm'); header('Location: my.php'); exit; }

$task_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($task_id <= 0) { flash_add('error','Invalid task ID','crm'); header('Location: index.php'); exit; }

$conn = createConnection(true); if (!$conn) { die('DB failed'); }
if (!crm_tables_exist($conn)) { closeConnection($conn); require_once __DIR__ . '/../onboarding.php'; exit; }

$current_employee_id = crm_current_employee_id($conn, $user_id);

// Detect columns
$has_lead_id = crm_tasks_has_column($conn,'lead_id');
$has_assigned_to = crm_tasks_has_column($conn,'assigned_to');
$has_created_by = crm_tasks_has_column($conn,'created_by');
$has_location = crm_tasks_has_column($conn,'location');
$has_attachment = crm_tasks_has_column($conn,'attachment');
$has_follow_up_date = crm_tasks_has_column($conn,'follow_up_date');
$has_follow_up_type = crm_tasks_has_column($conn,'follow_up_type');
$has_completion_notes = crm_tasks_has_column($conn,'completion_notes');
$has_completed_at = crm_tasks_has_column($conn,'completed_at');
$has_closed_by = crm_tasks_has_column($conn,'closed_by');
$has_updated_at = crm_tasks_has_column($conn,'updated_at');

// Fetch task
$select_cols = crm_tasks_select_columns($conn);
$sql = "SELECT $select_cols FROM crm_tasks c WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1";
$stmt = mysqli_prepare($conn,$sql); if (!$stmt){ closeConnection($conn); die('Failed to prepare'); }
mysqli_stmt_bind_param($stmt,'i',$task_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $task = $res?mysqli_fetch_assoc($res):null; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);
if (!$task) { flash_add('error','Task not found','crm'); closeConnection($conn); header('Location: index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $status = trim($_POST['status'] ?? 'Pending');
  $due_date = trim($_POST['due_date'] ?? '');
  $lead_id = isset($_POST['lead_id']) && is_numeric($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
  $assigned_to = isset($_POST['assigned_to']) && is_numeric($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : 0;
  $follow_up_date = trim($_POST['follow_up_date'] ?? '');
  $follow_up_type = trim($_POST['follow_up_type'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $completion_notes = trim($_POST['completion_notes'] ?? '');

  if ($title === '') { $errors[] = 'Task title is required'; }
  if ($status && !in_array($status, crm_task_statuses(), true)) { $errors[] = 'Invalid status'; }
  if ($due_date !== '' && !crm_task_validate_due_date($due_date)) { $errors[] = 'Due date must be today or in the future'; }
  if ($has_follow_up_type && $follow_up_type !== '' && !in_array($follow_up_type, crm_task_follow_up_types(), true)) { $errors[] = 'Invalid follow-up type'; }

  // File upload (keep existing by default)
  $attachment_filename = $task['attachment'] ?? '';
  if ($has_attachment && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../../../uploads/crm_attachments/';
    if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }
    $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['pdf','jpg','jpeg','png','doc','docx'];
    if (!in_array($file_ext,$allowed_exts,true)) {
      $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX';
    } elseif (($_FILES['attachment']['size'] ?? 0) > 3*1024*1024) {
      $errors[] = 'File size must be less than 3MB';
    } else {
      if ($attachment_filename && file_exists($upload_dir . $attachment_filename)) { @unlink($upload_dir . $attachment_filename); }
      $attachment_filename = 'Task_' . time() . '_' . uniqid() . '.' . $file_ext;
      if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment_filename)) {
        $errors[] = 'Failed to upload attachment';
        $attachment_filename = $task['attachment'] ?? '';
      }
    }
  }

  if (!$errors) {
    $updates = ['title = ?','description = ?','status = ?','due_date = ?'];
    $types = 'ssss';
    $values = [$title,$description,$status,($due_date !== '' ? $due_date : null)];

    if ($has_lead_id) { $updates[]='lead_id = ?'; $types.='i'; $values[] = $lead_id>0?$lead_id:null; }
    if ($has_assigned_to) { $updates[]='assigned_to = ?'; $types.='i'; $values[] = $assigned_to>0?$assigned_to:null; }
    if ($has_follow_up_date) { $updates[]='follow_up_date = ?'; $types.='s'; $values[] = $follow_up_date !== '' ? $follow_up_date : null; }
    if ($has_follow_up_type) { $updates[]='follow_up_type = ?'; $types.='s'; $values[] = $follow_up_type !== '' ? $follow_up_type : null; }
    if ($has_location) { $updates[]='location = ?'; $types.='s'; $values[] = $location !== '' ? $location : null; }
    if ($has_attachment) { $updates[]='attachment = ?'; $types.='s'; $values[] = $attachment_filename; }

    // Completion handling
    if ($has_completion_notes) { $updates[]='completion_notes = ?'; $types.='s'; $values[] = $completion_notes !== '' ? $completion_notes : null; }
    if ($status === 'Completed') {
      if ($has_completed_at) { $updates[]='completed_at = NOW()'; }
      if ($has_closed_by) { $updates[]='closed_by = ?'; $types.='i'; $values[] = $current_employee_id ?? null; }
    } else {
      if ($has_completed_at) { $updates[]='completed_at = NULL'; }
      if ($has_closed_by) { $updates[]='closed_by = NULL'; }
    }

    if ($has_updated_at) { $updates[] = 'updated_at = NOW()'; }

    $sql = 'UPDATE crm_tasks SET ' . implode(', ',$updates) . ' WHERE id = ? AND deleted_at IS NULL';
    $types .= 'i'; $values[] = $task_id;
    $stmt = mysqli_prepare($conn,$sql);
    if ($stmt) {
      mysqli_stmt_bind_param($stmt,$types,...$values);
      if (mysqli_stmt_execute($stmt)) {
        if ($has_lead_id && $lead_id>0 && $status === 'Completed') { crm_task_touch_lead($conn, $lead_id); }
        if ($status === 'Completed' && function_exists('crm_notify_task_completed')) { crm_notify_task_completed($conn, $task_id); }
        flash_add('success','Task updated successfully','crm');
        closeConnection($conn);
        header('Location: view.php?id=' . $task_id);
        exit;
      } else {
        $errors[] = 'Failed to update task: ' . mysqli_error($conn);
      }
      mysqli_stmt_close($stmt);
    } else { $errors[] = 'Failed to prepare statement'; }
  }
}

$employees = crm_fetch_employees($conn);
$leads = [];
if ($has_lead_id) { $res = mysqli_query($conn, "SELECT id, name, company_name FROM crm_leads WHERE deleted_at IS NULL ORDER BY name"); if ($res){ while($r=mysqli_fetch_assoc($res)){ $leads[]=$r; } mysqli_free_result($res);} }

$page_title = 'Edit Task - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>✏️ Edit Task</h1>
          <p>Update task details</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="view.php?id=<?php echo $task_id; ?>" class="btn btn-secondary">Cancel</a>
          <a href="index.php" class="btn btn-accent">← All Tasks</a>
        </div>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-error"><strong>Fix these:</strong><ul style="margin:8px 0 0 20px;">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
          <div>
            <div class="form-group"><label class="form-label">Title <span style="color:red;">*</span></label>
              <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($_POST['title'] ?? crm_task_get($task,'title')); ?>">
            </div>
            <div class="form-group"><label class="form-label">Status</label>
              <select name="status" class="form-control">
                <?php foreach (crm_task_statuses() as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo (($s === ($_POST['status'] ?? crm_task_get($task,'status','Pending'))) ? 'selected' : ''); ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Due Date</label>
              <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($_POST['due_date'] ?? crm_task_get($task,'due_date')); ?>" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <?php if ($has_lead_id): ?>
            <div class="form-group"><label class="form-label">Related Lead</label>
              <select name="lead_id" class="form-control">
                <option value="">-- None --</option>
                <?php foreach ($leads as $lead): ?>
                  <option value="<?php echo (int)$lead['id']; ?>" <?php echo (((int)($_POST['lead_id'] ?? crm_task_get($task,'lead_id',0))) === (int)$lead['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(($lead['name'] ?? 'Lead #'.$lead['id']) . (($lead['company_name'] ?? '') ? ' ('.$lead['company_name'].')' : '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($has_assigned_to): ?>
            <div class="form-group"><label class="form-label">Assign To</label>
              <select name="assigned_to" class="form-control">
                <option value="">-- Unassigned --</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo (int)$emp['id']; ?>" <?php echo (((int)($_POST['assigned_to'] ?? crm_task_get($task,'assigned_to',0))) === (int)$emp['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(trim(($emp['employee_code']??'').' - '.($emp['first_name']??'').' '.($emp['last_name']??''))); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($has_location): ?><div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($_POST['location'] ?? crm_task_get($task,'location')); ?>"></div><?php endif; ?>
            <?php if ($has_attachment): ?>
            <div class="form-group"><label class="form-label">Attachment</label>
              <?php if (crm_task_get($task,'attachment')): ?>
                <div style="margin-bottom:8px;padding:8px;background:#f8f9fa;border-radius:6px;font-size:13px;">Current: <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars(crm_task_get($task,'attachment')); ?>" target="_blank" style="color:#003581;">Download</a></div>
              <?php endif; ?>
              <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
              <small style="color:#6c757d;">Max 3MB. Leave blank to keep current file.</small>
            </div>
            <?php endif; ?>
          </div>
          <div>
            <div class="form-group"><label class="form-label">Description</label>
              <textarea name="description" rows="8" class="form-control"><?php echo htmlspecialchars($_POST['description'] ?? crm_task_get($task,'description')); ?></textarea>
            </div>
            <?php if ($has_follow_up_date): ?><div class="form-group"><label class="form-label">Follow-up Date</label><input type="date" name="follow_up_date" class="form-control" value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? crm_task_get($task,'follow_up_date')); ?>" min="<?php echo date('Y-m-d'); ?>"></div><?php endif; ?>
            <?php if ($has_follow_up_type): ?><div class="form-group"><label class="form-label">Follow-up Type</label>
              <select name="follow_up_type" class="form-control"><option value="">-- None --</option><?php foreach (crm_task_follow_up_types() as $t): ?><option value="<?php echo $t; ?>" <?php echo (($t === ($_POST['follow_up_type'] ?? crm_task_get($task,'follow_up_type',''))) ? 'selected' : ''); ?>><?php echo $t; ?></option><?php endforeach; ?></select>
            </div><?php endif; ?>
            <?php if ($has_completion_notes): ?><div class="form-group"><label class="form-label">Completion Notes</label>
              <textarea name="completion_notes" rows="5" class="form-control" placeholder="Notes upon completion..."><?php echo htmlspecialchars($_POST['completion_notes'] ?? crm_task_get($task,'completion_notes')); ?></textarea>
            </div><?php endif; ?>
          </div>
        </div>
        <div style="margin-top:24px;padding-top:20px;border-top:1px solid #dee2e6;display:flex;gap:12px;justify-content:flex-end;">
          <a href="view.php?id=<?php echo $task_id; ?>" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn">💾 Update Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php closeConnection($conn); require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
