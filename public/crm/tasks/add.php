<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';

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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $due_date = trim($_POST['due_date'] ?? '');
  $status = trim($_POST['status'] ?? 'Pending');
  $lead_id = isset($_POST['lead_id']) && is_numeric($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
  $assigned_to = isset($_POST['assigned_to']) && is_numeric($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : ($current_employee_id ?? 0);
  $follow_up_date = trim($_POST['follow_up_date'] ?? '');
  $follow_up_type = trim($_POST['follow_up_type'] ?? '');
  $location = trim($_POST['location'] ?? '');

  if ($title === '') { $errors[] = 'Task title is required'; }
  if ($status && !in_array($status, crm_task_statuses(), true)) { $errors[] = 'Invalid status'; }
  if ($due_date !== '' && !crm_task_validate_due_date($due_date)) { $errors[] = 'Due date must be today or in the future'; }
  if ($has_follow_up_type && $follow_up_type !== '' && !in_array($follow_up_type, crm_task_follow_up_types(), true)) { $errors[] = 'Invalid follow-up type'; }

  // File upload
  $attachment_filename = '';
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
      $attachment_filename = 'Task_' . time() . '_' . uniqid() . '.' . $file_ext;
      if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment_filename)) {
        $errors[] = 'Failed to upload attachment';
        $attachment_filename = '';
      }
    }
  }

  if (!$errors) {
    $columns = ['title','description','status'];
    $placeholders = ['?','?','?'];
    $types = 'sss';
    $values = [$title,$description,$status];

    if ($due_date !== '' && $due_date !== null && $has_follow_up_date /* dummy to reference */) { /* no-op */ }
    // due_date column exists in base schema
    $columns[] = 'due_date'; $placeholders[]='?'; $types.='s'; $values[] = $due_date !== '' ? $due_date : null;

    if ($has_lead_id) { $columns[]='lead_id'; $placeholders[]='?'; $types.='i'; $values[] = $lead_id > 0 ? $lead_id : null; }
    if ($has_assigned_to) { $columns[]='assigned_to'; $placeholders[]='?'; $types.='i'; $values[] = $assigned_to > 0 ? $assigned_to : null; }
    if ($has_created_by) { $columns[]='created_by'; $placeholders[]='?'; $types.='i'; $values[] = ($current_employee_id ?? null); }
    if ($has_follow_up_date) { $columns[]='follow_up_date'; $placeholders[]='?'; $types.='s'; $values[] = $follow_up_date !== '' ? $follow_up_date : null; }
    if ($has_follow_up_type) { $columns[]='follow_up_type'; $placeholders[]='?'; $types.='s'; $values[] = $follow_up_type !== '' ? $follow_up_type : null; }
    if ($has_location) { $columns[]='location'; $placeholders[]='?'; $types.='s'; $values[] = $location !== '' ? $location : null; }
    if ($has_attachment && $attachment_filename) { $columns[]='attachment'; $placeholders[]='?'; $types.='s'; $values[] = $attachment_filename; }

    $sql = 'INSERT INTO crm_tasks (' . implode(', ',$columns) . ') VALUES (' . implode(', ',$placeholders) . ')';
    $stmt = mysqli_prepare($conn,$sql);
    if ($stmt) {
      mysqli_stmt_bind_param($stmt,$types,...$values);
      if (mysqli_stmt_execute($stmt)) {
        $new_id = mysqli_insert_id($conn);
        if ($has_lead_id && $lead_id>0) { crm_task_touch_lead($conn, $lead_id); }
        if (function_exists('crm_notify_new_task')) { crm_notify_new_task($conn, $new_id); }
        flash_add('success','Task created successfully','crm');
        closeConnection($conn);
        header('Location: view.php?id=' . $new_id);
        exit;
      } else {
        $errors[] = 'Failed to create task: ' . mysqli_error($conn);
      }
      mysqli_stmt_close($stmt);
    } else {
      $errors[] = 'Failed to prepare statement';
    }
  }
}

// Employees and leads for selects
$employees = crm_fetch_employees($conn);
$leads = [];
if ($has_lead_id) { $res = mysqli_query($conn, "SELECT id, name, company_name FROM crm_leads WHERE deleted_at IS NULL ORDER BY name"); if ($res){ while($r=mysqli_fetch_assoc($res)){ $leads[]=$r; } mysqli_free_result($res);} }

$page_title = 'Add Task - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>➕ New Task</h1>
          <p>Create a new CRM task</p>
        </div>
        <div>
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-accent">← Back to List</a>
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
            <div class="form-group">
              <label class="form-label">Title <span style="color:red;">*</span></label>
              <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="e.g., Follow up with lead">
            </div>
            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-control">
                <?php foreach (crm_task_statuses() as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo (($s === ($_POST['status'] ?? 'Pending')) ? 'selected' : ''); ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Due Date</label>
              <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <?php if ($has_lead_id): ?>
            <div class="form-group">
              <label class="form-label">Related Lead (optional)</label>
              <select name="lead_id" class="form-control">
                <option value="">-- Select Lead --</option>
                <?php $prefilled_lead = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : (isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0); ?>
                <?php foreach ($leads as $lead): ?>
                  <option value="<?php echo (int)$lead['id']; ?>" <?php echo $prefilled_lead === (int)$lead['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(($lead['name'] ?? 'Lead #'.$lead['id']) . (($lead['company_name'] ?? '') ? ' ('.$lead['company_name'].')' : '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($has_assigned_to && crm_role_can_manage($user_role)): ?>
            <div class="form-group">
              <label class="form-label">Assign To</label>
              <select name="assigned_to" class="form-control">
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo (int)$emp['id']; ?>" <?php echo ((int)($_POST['assigned_to'] ?? ($current_employee_id ?? 0)) === (int)$emp['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(trim(($emp['employee_code']??'').' - '.($emp['first_name']??'').' '.($emp['last_name']??''))); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($has_location): ?>
            <div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" placeholder="Onsite / Zoom / Address"></div>
            <?php endif; ?>
            <?php if ($has_attachment): ?>
            <div class="form-group"><label class="form-label">Attachment</label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"><small style="color:#6c757d;">Max 3MB</small></div>
            <?php endif; ?>
          </div>
          <div>
            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea name="description" rows="8" class="form-control" placeholder="Details or notes..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            <?php if ($has_follow_up_date): ?>
            <div class="form-group"><label class="form-label">Follow-up Date</label><input type="date" name="follow_up_date" class="form-control" value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>"></div>
            <?php endif; ?>
            <?php if ($has_follow_up_type): ?>
            <div class="form-group"><label class="form-label">Follow-up Type</label>
              <select name="follow_up_type" class="form-control">
                <option value="">-- None --</option>
                <?php foreach (crm_task_follow_up_types() as $t): ?>
                  <option value="<?php echo $t; ?>" <?php echo (($t === ($_POST['follow_up_type'] ?? '')) ? 'selected' : ''); ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div style="margin-top:24px;padding-top:20px;border-top:1px solid #dee2e6;display:flex;gap:12px;justify-content:flex-end;">
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn">💾 Create Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php closeConnection($conn); require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
