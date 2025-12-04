<?php
require_once __DIR__ . '/common.php';

authz_require_permission($conn, 'crm_tasks', 'edit_all');

if (!crm_tables_exist($conn)) {
    require_once __DIR__ . '/../onboarding.php';
    exit;
}

$current_employee_id = crm_current_employee_id($conn, $CURRENT_USER_ID);
if (!$current_employee_id && !$IS_SUPER_ADMIN) {
    die('Unable to identify your employee record.');
}

// Get task ID
$task_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($task_id <= 0) {
    flash_add('error', 'Invalid task ID', 'crm');
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: index.php');
    exit;
}

// Fetch existing task
$select_cols = crm_tasks_select_columns($conn);
$sql = "SELECT $select_cols FROM crm_tasks c WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    closeConnection($conn);
    die('Failed to prepare statement');
}
mysqli_stmt_bind_param($stmt, 'i', $task_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$task = $res ? mysqli_fetch_assoc($res) : null;
if ($res) mysqli_free_result($res);
mysqli_stmt_close($stmt);

if (!$task) {
    flash_add('error', 'Task not found', 'crm');
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: index.php');
    exit;
}

// Check permissions - non-super-admins and non-edit_all users cannot edit
$has_assigned_to = crm_tasks_has_column($conn, 'assigned_to');
if ($has_assigned_to && !$IS_SUPER_ADMIN) {
    $tasks_permissions = authz_get_permission_set($conn, 'crm_tasks');
    // If user doesn't have edit_all, they can only edit their own tasks
    if (!$tasks_permissions['can_edit_all']) {
        $assigned_to = isset($task['assigned_to']) ? (int)$task['assigned_to'] : 0;
        if ($assigned_to !== $current_employee_id) {
            flash_add('error', 'You do not have permission to edit this task', 'crm');
            if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                closeConnection($conn);
            }
            header('Location: view.php?id=' . $task_id);
            exit;
        }
    }
}

$employees = crm_fetch_employees($conn);
$leads = [];
$res = mysqli_query($conn, "SELECT id, name, company_name FROM crm_leads WHERE deleted_at IS NULL ORDER BY name");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $leads[] = $r;
    }
    mysqli_free_result($res);
}
$follow_up_types = crm_task_follow_up_types();

$employees = crm_fetch_employees($conn);
$leads = [];
$res = mysqli_query($conn, "SELECT id, name, company_name FROM crm_leads WHERE deleted_at IS NULL ORDER BY name");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $leads[] = $r;
    }
    mysqli_free_result($res);
}
$follow_up_types = crm_task_follow_up_types();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $task_date = trim($_POST['task_date'] ?? '');
    $task_type = trim($_POST['task_type'] ?? 'Scheduled');
    $outcome = trim($_POST['outcome'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
    $due_date = trim($_POST['due_date'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $location = trim($_POST['location'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $follow_up_type = trim($_POST['follow_up_type'] ?? '');
    $completion_notes = trim($_POST['completion_notes'] ?? '');
    
    // Capture geo-coordinates
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;
    
    // Capture task completion coordinates
    $task_latitude = isset($_POST['task_latitude']) && $_POST['task_latitude'] !== '' ? floatval($_POST['task_latitude']) : (crm_task_get($task, 'task_latitude') ?: null);
    $task_longitude = isset($_POST['task_longitude']) && $_POST['task_longitude'] !== '' ? floatval($_POST['task_longitude']) : (crm_task_get($task, 'task_longitude') ?: null);

    // Validation
    if ($lead_id === null) {
        $errors[] = 'Related lead is required.';
    }
    if ($title === '') {
        $errors[] = 'Task title is required.';
    }
    if ($assigned_to <= 0) {
        $errors[] = 'Assigned employee is required.';
    } else {
        if (!crm_employee_exists($conn, $assigned_to)) {
            $errors[] = 'Assigned employee does not exist.';
        }
    }
    if ($task_date === '') {
        $errors[] = 'Task date and time are required.';
    } else {
        $task_dt = DateTime::createFromFormat('Y-m-d\TH:i', $task_date);
        if (!$task_dt) {
            $errors[] = 'Invalid task date format.';
        } else {
            $now = new DateTime();
            if ($task_type === 'Logged' && $task_dt > $now) {
                $errors[] = 'Logged task date cannot be in the future.';
            } elseif ($task_type === 'Scheduled' && $task_dt <= $now) {
                $errors[] = 'Scheduled task date must be in the future.';
            }
        }
    }
    
    // Type-based validation
    if ($task_type === 'Logged') {
        if ($description === '') {
            $errors[] = 'Task description is required for logged tasks.';
        }
        if ($outcome === '') {
            $errors[] = 'Task outcome is required for logged tasks.';
        }
        if ($completion_notes === '') {
            $errors[] = 'Completion notes are required for logged tasks.';
        }
        if ($task_latitude === null || $task_longitude === null) {
            $errors[] = 'Task location coordinates are required for logged tasks.';
        }
        $status = 'Completed';
    }
    
    // Status-based validation
    if ($status === 'Completed') {
        if ($outcome === '') {
            $errors[] = 'Task outcome is required when status is Completed.';
        }
        if ($completion_notes === '') {
            $errors[] = 'Completion notes are required when status is Completed.';
        }
        if ($task_latitude === null || $task_longitude === null) {
            $errors[] = 'Task location coordinates are required for completed tasks.';
        }
    }
    
    // Follow-up validation
    if ($follow_up_date !== '' && $follow_up_type === '') {
        $errors[] = 'Follow-up type is required when follow-up date is selected.';
    }
    
    if ($lead_id !== null) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM crm_leads WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $lead_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (!mysqli_fetch_assoc($res)) {
            $errors[] = 'Selected lead does not exist.';
        }
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);
    }

    // Handle task proof image upload (mandatory for logged/completed tasks)
    $task_proof_filename = crm_task_get($task, 'task_proof_image', '');
    if ($task_type === 'Logged' || $status === 'Completed') {
        if (isset($_FILES['task_proof_image']) && $_FILES['task_proof_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../../uploads/crm_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['task_proof_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                $errors[] = 'Task proof must be JPG or PNG image';
            } elseif ($_FILES['task_proof_image']['size'] > 3 * 1024 * 1024) {
                $errors[] = 'Task proof image size must be less than 3MB';
            } else {
                // Delete old proof image if exists
                if ($task_proof_filename && file_exists($upload_dir . $task_proof_filename)) {
                    @unlink($upload_dir . $task_proof_filename);
                }
                
                $task_proof_filename = 'task_proof_' . time() . '_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $task_proof_filename;
                
                if (!move_uploaded_file($_FILES['task_proof_image']['tmp_name'], $upload_path)) {
                    $errors[] = 'Failed to upload task proof image';
                    $task_proof_filename = crm_task_get($task, 'task_proof_image', '');
                }
            }
        } elseif (!$task_proof_filename) {
            // No new file and no existing file
            $errors[] = 'Task proof image is required for logged/completed tasks';
        }
    }

    // Handle generic attachment upload (optional)
    $attachment_filename = crm_task_get($task, 'attachment', '');
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/crm_attachments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Attachment must be PDF, JPG, PNG, DOC, or DOCX';
        } elseif ($_FILES['attachment']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Attachment size must be less than 3MB';
        } else {
            // Delete old attachment if exists
            if ($attachment_filename && file_exists($upload_dir . $attachment_filename)) {
                @unlink($upload_dir . $attachment_filename);
            }
            
            $attachment_filename = 'task_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $attachment_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload attachment';
                $attachment_filename = crm_task_get($task, 'attachment', '');
            }
        }
    }

    if (empty($errors)) {
        // Get existing columns
        $existing_cols = [];
        $cols_res = mysqli_query($conn, "SHOW COLUMNS FROM crm_tasks");
        if ($cols_res) {
            while ($c = mysqli_fetch_assoc($cols_res)) {
                $existing_cols[] = $c['Field'];
            }
            mysqli_free_result($cols_res);
        }

        // Build dynamic data array
        $updates = [];
        $types = '';
        $values = [];

        $updates[] = 'title = ?';
        $types .= 's';
        $values[] = $title;

        $updates[] = 'task_date = ?';
        $types .= 's';
        $values[] = $task_date;

        $updates[] = 'task_type = ?';
        $types .= 's';
        $values[] = $task_type;

        $updates[] = 'status = ?';
        $types .= 's';
        $values[] = $status;

        if (in_array('description', $existing_cols)) {
            $updates[] = 'description = ?';
            $types .= 's';
            $values[] = $description !== '' ? $description : null;
        }

        if (in_array('notes', $existing_cols)) {
            $updates[] = 'notes = ?';
            $types .= 's';
            $values[] = $notes !== '' ? $notes : null;
        }

        if (in_array('lead_id', $existing_cols)) {
            $updates[] = 'lead_id = ?';
            $types .= 'i';
            $values[] = $lead_id;
        }

        if (in_array('outcome', $existing_cols)) {
            $updates[] = 'outcome = ?';
            $types .= 's';
            $values[] = $outcome !== '' ? $outcome : null;
        }

        if (in_array('assigned_to', $existing_cols)) {
            $updates[] = 'assigned_to = ?';
            $types .= 'i';
            $values[] = $assigned_to;
        }

        if (in_array('due_date', $existing_cols)) {
            $updates[] = 'due_date = ?';
            $types .= 's';
            $values[] = $due_date !== '' ? $due_date : null;
        }

        if (in_array('priority', $existing_cols)) {
            $updates[] = 'priority = ?';
            $types .= 's';
            $values[] = $priority;
        }

        if (in_array('location', $existing_cols)) {
            $updates[] = 'location = ?';
            $types .= 's';
            $values[] = $location !== '' ? $location : null;
        }

        if (in_array('latitude', $existing_cols)) {
            $updates[] = 'latitude = ?';
            $types .= 'd';
            $values[] = $latitude;
        }

        if (in_array('longitude', $existing_cols)) {
            $updates[] = 'longitude = ?';
            $types .= 'd';
            $values[] = $longitude;
        }

        if (in_array('task_latitude', $existing_cols)) {
            $updates[] = 'task_latitude = ?';
            $types .= 'd';
            $values[] = $task_latitude;
        }

        if (in_array('task_longitude', $existing_cols)) {
            $updates[] = 'task_longitude = ?';
            $types .= 'd';
            $values[] = $task_longitude;
        }

        if (in_array('completion_notes', $existing_cols)) {
            $updates[] = 'completion_notes = ?';
            $types .= 's';
            $values[] = $completion_notes !== '' ? $completion_notes : null;
        }

        if (in_array('task_proof_image', $existing_cols)) {
            $updates[] = 'task_proof_image = ?';
            $types .= 's';
            $values[] = $task_proof_filename;
        }

        if (in_array('attachment', $existing_cols)) {
            $updates[] = 'attachment = ?';
            $types .= 's';
            $values[] = $attachment_filename;
        }

        if (in_array('follow_up_date', $existing_cols)) {
            $updates[] = 'follow_up_date = ?';
            $types .= 's';
            $values[] = $follow_up_date !== '' ? $follow_up_date : null;
        }

        if (in_array('follow_up_type', $existing_cols)) {
            $updates[] = 'follow_up_type = ?';
            $types .= 's';
            $values[] = $follow_up_type !== '' ? $follow_up_type : null;
        }

        // Handle completion timestamp
        if ($status === 'Completed') {
            if (in_array('completed_at', $existing_cols)) {
                $updates[] = 'completed_at = NOW()';
            }
            if (in_array('closed_by', $existing_cols)) {
                $updates[] = 'closed_by = ?';
                $types .= 'i';
                $values[] = $current_employee_id;
            }
        } else {
            if (in_array('completed_at', $existing_cols)) {
                $updates[] = 'completed_at = NULL';
            }
            if (in_array('closed_by', $existing_cols)) {
                $updates[] = 'closed_by = NULL';
            }
        }

        if (in_array('updated_at', $existing_cols)) {
            $updates[] = 'updated_at = NOW()';
        }

        // Build UPDATE query
        $sql = "UPDATE crm_tasks SET " . implode(', ', $updates) . " WHERE id = ? AND deleted_at IS NULL";
        $types .= 'i';
        $values[] = $task_id;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                // Update lead contact time for logged tasks
                if ($task_type === 'Logged' && $lead_id && function_exists('crm_task_touch_lead')) {
                    crm_task_touch_lead($conn, $lead_id);
                }

                // Handle follow-up
                if ($follow_up_date !== '' && $lead_id && $follow_up_type !== '') {
                    if (function_exists('crm_update_lead_followup_date')) {
                        crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
                    }
                }

                flash_add('success', 'Task updated successfully!', 'crm');
                if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                    closeConnection($conn);
                }
                header('Location: view.php?id=' . $task_id);
                exit;
            } else {
                $errors[] = 'Failed to update task: ' . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$page_title = 'Edit Task - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<!-- Select2 CSS for searchable dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
/* Select2 Custom Styling */
.select2-container .select2-selection--single {
    height: 40px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px;
    color: #1b2a57;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px;
}
.select2-dropdown {
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}
.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
    background-color: #0b5ed7;
}
</style>

<div class="main-wrapper">
  <div class="main-content">
<style>
.task-edit-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.task-edit-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}
.task-edit-form-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
.task-edit-form-grid-1{display:grid;grid-template-columns:1fr;gap:20px;}

@media (max-width:1024px){
.task-edit-form-grid-3{grid-template-columns:repeat(2,1fr);}
}

@media (max-width:768px){
.task-edit-header-flex{flex-direction:column;align-items:stretch;}
.task-edit-header-buttons{width:100%;flex-direction:column;gap:10px;}
.task-edit-header-buttons .btn{width:100%;text-align:center;}
.task-edit-form-grid-3{grid-template-columns:1fr;}
}

@media (max-width:480px){
.task-edit-header-flex h1{font-size:1.5rem;}
.task-edit-form-grid-3{gap:15px;}
}
</style>

    <div class="page-header">
      <div class="task-edit-header-flex">
        <div>
          <h1>✏️ Edit Task</h1>
          <p>Update task details and mark completion status</p>
        </div>
        <div class="task-edit-header-buttons">
          <a href="view.php?id=<?php echo $task_id; ?>" class="btn btn-secondary">← Back to Task</a>
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-accent">← All Tasks</a>
        </div>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <strong>❌ Error:</strong><br>
        <?php foreach ($errors as $err): ?>
          • <?php echo htmlspecialchars($err); ?><br>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php echo flash_render(); ?>

    <form method="POST" enctype="multipart/form-data" id="taskForm">
      <!-- Task Information -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          🧰 Task Information
        </h3>
        <div class="task-edit-form-grid-3">
          <div class="form-group">
            <label for="task_type">Task Type <span style="color: #dc3545;">*</span></label>
            <select id="task_type" name="task_type" class="form-control" required>
              <option value="Logged" <?php echo crm_task_get($task, 'task_type', 'Scheduled') === 'Logged' ? 'selected' : ''; ?>>
                📝 Logged (Completed Task)
              </option>
              <option value="Scheduled" <?php echo crm_task_get($task, 'task_type', 'Scheduled') === 'Scheduled' ? 'selected' : ''; ?>>
                📅 Scheduled (Future Task)
              </option>
            </select>
            <small style="color: #6c757d; font-size: 12px;">Select whether this is a logged past task or scheduled future one</small>
          </div>

          <div class="form-group">
            <label for="lead_id">Related Lead <span style="color: #dc3545;">*</span></label>
            <select id="lead_id" name="lead_id" class="form-control select2-lead" required>
              <option value="">-- Select Lead --</option>
              <?php foreach ($leads as $l): ?>
                <option value="<?php echo (int)$l['id']; ?>"
                    <?php echo (int)crm_task_get($task, 'lead_id', 0) === (int)$l['id'] ? 'selected' : ''; ?>>
                  <?php 
                    echo htmlspecialchars($l['name'] ?? 'Lead #'.$l['id']);
                    if (!empty($l['company_name'])) {
                        echo ' - ' . htmlspecialchars($l['company_name']);
                    }
                  ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small style="color: #6c757d; font-size: 12px;">Search and select a lead</small>
          </div>

          <div class="form-group">
            <label for="assigned_to">Assigned To <span style="color: #dc3545;">*</span></label>
            <select id="assigned_to" name="assigned_to" class="form-control select2-employee" required>
              <option value="">-- Select Employee --</option>
              <?php foreach ($employees as $emp): ?>
                <?php
                  $emp_id = (int)$emp['id'];
                  $selected = (int)crm_task_get($task, 'assigned_to', 0) === $emp_id;
                  $emp_display = trim(($emp['employee_code'] ?? '') . ' - ' . ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
                ?>
                <option value="<?php echo $emp_id; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($emp_display); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small style="color: #6c757d; font-size: 12px;">Search and select employee</small>
          </div>

          <div class="form-group">
            <label for="title">Task Title <span style="color: #dc3545;">*</span></label>
            <input type="text" id="title" name="title" class="form-control" 
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'title', '')); ?>" 
                   placeholder="e.g., Follow-up call, Document submission" required>
            <small style="color: #6c757d; font-size: 12px;">Brief description of the task</small>
          </div>

          <div class="form-group">
            <label for="task_date">Task Date & Time <span style="color: #dc3545;">*</span></label>
            <input type="datetime-local" id="task_date" name="task_date" class="form-control"
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'task_date', '')); ?>" required>
            <small id="task_date_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group">
            <label for="status">Status <span style="color: #dc3545;">*</span></label>
            <select id="status" name="status" class="form-control" required>
              <option value="Pending" <?php echo crm_task_get($task, 'status', 'Pending') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="In Progress" <?php echo crm_task_get($task, 'status', 'Pending') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
              <option value="Completed" <?php echo crm_task_get($task, 'status', 'Pending') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
              <option value="Cancelled" <?php echo crm_task_get($task, 'status', 'Pending') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <small id="status_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group">
            <label for="priority">Priority <span style="color: #dc3545;">*</span></label>
            <select id="priority" name="priority" class="form-control" required>
              <option value="Low" <?php echo crm_task_get($task, 'priority', 'Medium') === 'Low' ? 'selected' : ''; ?>>🟢 Low</option>
              <option value="Medium" <?php echo crm_task_get($task, 'priority', 'Medium') === 'Medium' ? 'selected' : ''; ?>>🟡 Medium</option>
              <option value="High" <?php echo crm_task_get($task, 'priority', 'Medium') === 'High' ? 'selected' : ''; ?>>🔴 High</option>
            </select>
            <small style="color: #6c757d; font-size: 12px;">Task priority level</small>
          </div>

          <div class="form-group">
            <label for="due_date">Due Date</label>
            <input type="date" id="due_date" name="due_date" class="form-control"
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'due_date', '')); ?>">
            <small style="color: #6c757d; font-size: 12px;">Optional - Deadline for task completion</small>
          </div>

          <div class="form-group">
            <label for="outcome">Task Outcome <span id="outcome_required" style="color: #dc3545;">*</span></label>
            <input type="text" id="outcome" name="outcome" class="form-control"
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'outcome', '')); ?>"
                   placeholder="e.g., Client agreed to meeting">
            <small id="outcome_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="description">Task Description <span id="description_required" style="color: #dc3545;">*</span></label>
            <textarea id="description" name="description" class="form-control" rows="4" 
                      placeholder="Task details, activities, and key discussion points..."><?php echo htmlspecialchars(crm_task_get($task, 'description', '')); ?></textarea>
            <small id="description_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" class="form-control"
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'location', '')); ?>"
                   placeholder="e.g., Office, Client site, Online">
            <small style="color: #6c757d; font-size: 12px;">Physical or virtual location</small>
            <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars(crm_task_get($task, 'latitude', '')); ?>">
            <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars(crm_task_get($task, 'longitude', '')); ?>">
          </div>
        </div>
      </div>

      <!-- Task Proof (for Logged/Completed Tasks) -->
      <div id="task_proof_section" class="card" style="margin-bottom: 25px; display: none;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📸 Task Proof
        </h3>
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
          <strong>⚠️ Required for Logged/Completed Tasks:</strong>
          <p style="margin: 5px 0 0 0; color: #856404;">
            When marking a task as completed, you must provide task proof image and the system will capture your current location.
          </p>
        </div>
        
        <?php if (crm_task_get($task, 'task_proof_image')): ?>
        <div style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 4px; border-left: 4px solid #28a745;">
          <strong style="color: #155724;">📎 Existing Task Proof:</strong>
          <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars(crm_task_get($task, 'task_proof_image')); ?>" 
             target="_blank" style="color: #003581; margin-left: 10px;">View Current Proof Image</a>
          <small style="display: block; margin-top: 5px; color: #6c757d;">Upload a new image to replace the existing one</small>
        </div>
        <?php endif; ?>
        
        <div class="task-edit-form-grid-3">
          <div class="form-group">
            <label for="task_proof_image">Task Proof Image <span id="proof_required" style="color: #dc3545;">*</span></label>
            <input type="file" id="task_proof_image" name="task_proof_image" class="form-control" accept="image/jpeg,image/png,image/jpg">
            <small style="color: #6c757d; font-size: 12px;">JPG or PNG only. Max 3MB. <?php echo crm_task_get($task, 'task_proof_image') ? 'Leave blank to keep existing.' : ''; ?></small>
          </div>

          <div class="form-group">
            <label for="task_latitude">Task Latitude <span style="color: #dc3545;">*</span></label>
            <input type="text" id="task_latitude" name="task_latitude" class="form-control" 
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'task_latitude', '')); ?>"
                   readonly placeholder="Auto-captured">
            <small style="color: #6c757d; font-size: 12px;">Automatically captured from your device</small>
          </div>

          <div class="form-group">
            <label for="task_longitude">Task Longitude <span style="color: #dc3545;">*</span></label>
            <input type="text" id="task_longitude" name="task_longitude" class="form-control"
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'task_longitude', '')); ?>"
                   readonly placeholder="Auto-captured">
            <small style="color: #6c757d; font-size: 12px;">Automatically captured from your device</small>
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="completion_notes">Completion Notes <span style="color: #dc3545;">*</span></label>
            <textarea id="completion_notes" name="completion_notes" class="form-control" rows="3" placeholder="Detailed notes about task completion, challenges faced, final outcome..."><?php echo htmlspecialchars(crm_task_get($task, 'completion_notes', '')); ?></textarea>
            <small style="color: #6c757d; font-size: 12px;">Required - Provide complete details about how the task was completed</small>
          </div>
        </div>
      </div>

      <!-- Follow-Up & Additional Details -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📅 Follow-Up & Additional Details
        </h3>
        <div class="task-edit-form-grid-3">
          <div class="form-group">
            <label for="follow_up_date">Follow-Up Date</label>
            <input type="date" id="follow_up_date" name="follow_up_date" class="form-control" 
                   value="<?php echo htmlspecialchars(crm_task_get($task, 'follow_up_date', '')); ?>">
            <small style="color: #6c757d; font-size: 12px;">Optional - Schedule next interaction</small>
          </div>

          <div class="form-group">
            <label for="follow_up_type">Follow-Up Type <span class="followup-required" style="color: #dc3545; display: none;">*</span></label>
            <select id="follow_up_type" name="follow_up_type" class="form-control">
              <option value="">-- Select Type --</option>
              <?php foreach ($follow_up_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>" 
                    <?php echo crm_task_get($task, 'follow_up_type', '') === $type ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($type); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small style="color: #6c757d; font-size: 12px;">Required if follow-up date is set</small>
          </div>

          <div class="form-group">
            <label for="attachment">Attachment (Optional)</label>
            <?php if (crm_task_get($task, 'attachment')): ?>
              <div style="margin-bottom: 8px; padding: 8px; background-color: #f8f9fa; border-radius: 4px;">
                <strong style="font-size: 12px;">Current:</strong>
                <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars(crm_task_get($task, 'attachment')); ?>" 
                   target="_blank" style="color: #003581; font-size: 12px;">Download</a>
              </div>
            <?php endif; ?>
            <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            <small style="color: #6c757d; font-size: 12px;">PDF, JPG, PNG, DOC, DOCX (max 3MB). <?php echo crm_task_get($task, 'attachment') ? 'Leave blank to keep existing.' : ''; ?></small>
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="notes">Internal Notes (Team Only) 🔒</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Private notes for internal team use only..."><?php echo htmlspecialchars(crm_task_get($task, 'notes', '')); ?></textarea>
            <small style="color: #6c757d; font-size: 12px;">These notes are visible only to your team, not to leads</small>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div style="text-align: center; margin-top: 24px;">
        <button type="submit" class="btn" style="padding: 15px 60px; font-size: 16px;">💾 Update Task</button>
      </div>
    </form>
  </div>
</div>

<!-- jQuery and Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  // Initialize Select2 with search functionality
  $('.select2-lead').select2({
    placeholder: '-- Select Lead --',
    allowClear: false,
    width: '100%'
  });
  
  $('.select2-employee').select2({
    placeholder: '-- Select Employee --',
    allowClear: false,
    width: '100%'
  });

  // Capture geolocation on page load (but don't overwrite existing task coordinates)
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      $('#latitude').val(position.coords.latitude.toFixed(6));
      $('#longitude').val(position.coords.longitude.toFixed(6));
      
      // Only set task coordinates if they don't exist yet
      if (!$('#task_latitude').val()) {
        $('#task_latitude').val(position.coords.latitude.toFixed(6));
      }
      if (!$('#task_longitude').val()) {
        $('#task_longitude').val(position.coords.longitude.toFixed(6));
      }
    }, function(error) {
      console.warn('Geolocation error:', error.message);
    }, {
      timeout: 5000,
      enableHighAccuracy: true,
      maximumAge: 60000
    });
  }

  // Update form based on task type
  function updateFormBasedOnType() {
    const taskType = $('#task_type').val();
    const status = $('#status').val();
    const isLogged = taskType === 'Logged';
    const isCompleted = status === 'Completed';
    const now = new Date();
    const nowString = now.toISOString().slice(0, 16);
    
    if (isLogged) {
      // Logged Task: Past activity
      $('#task_date').attr('max', nowString).removeAttr('min');
      $('#task_date_hint').text('Must be a past or current date/time');
      
      $('#status').val('Completed');
      $('#status_hint').text('Auto-set to Completed for logged tasks');
      
      $('#outcome_required').show();
      $('#outcome').prop('required', true);
      $('#outcome_hint').text('Required - What was the result of this task?');
      
      $('#description_required').show();
      $('#description').prop('required', true);
      $('#description_hint').text('Required - Detailed summary of what was done');
      
      $('#task_proof_section').show();
      $('#completion_notes').prop('required', true);
      
      $('button[type="submit"]').html('💾 Update Task');
    } else {
      // Scheduled Task: Future activity
      $('#task_date').attr('min', nowString).removeAttr('max');
      $('#task_date_hint').text('Must be a future date/time');
      
      if (status !== 'Completed') {
        $('#status').val('Pending');
      }
      $('#status_hint').text(status === 'Completed' ? 'Task marked as completed' : 'Current task status');
      
      if (!isCompleted) {
        $('#outcome_required').hide();
        $('#outcome').prop('required', false);
        $('#outcome_hint').text('Optional - Can be filled later after the task');
        
        $('#description_required').hide();
        $('#description').prop('required', false);
        $('#description_hint').text('Optional - Brief notes about planned activities');
        
        $('#task_proof_section').hide();
        $('#completion_notes').prop('required', false);
      }
      
      $('button[type="submit"]').html('💾 Update Task');
    }
    
    // Always show proof section if status is completed
    if (isCompleted || isLogged) {
      $('#task_proof_section').show();
      $('#outcome_required').show();
      $('#outcome').prop('required', true);
      $('#completion_notes').prop('required', true);
    }
  }

  // Task type change handler
  $('#task_type').on('change', updateFormBasedOnType);

  // Status change handler - show/hide proof requirement
  $('#status').on('change', function() {
    const status = $(this).val();
    const taskType = $('#task_type').val();
    
    if (status === 'Completed') {
      $('#outcome_required').show();
      $('#outcome').prop('required', true);
      $('#outcome_hint').text('Required - What was the result?');
      
      $('#task_proof_section').show();
      $('#completion_notes').prop('required', true);
    } else {
      // Only hide if not a logged task
      if (taskType !== 'Logged') {
        $('#outcome_required').hide();
        $('#outcome').prop('required', false);
        $('#outcome_hint').text('Optional');
        
        $('#task_proof_section').hide();
        $('#completion_notes').prop('required', false);
      }
    }
  });

  // Follow-up date change handler
  $('#follow_up_date').on('change', function() {
    if ($(this).val()) {
      $('.followup-required').show();
      $('#follow_up_type').prop('required', true);
      if ($('#follow_up_type').val() === '') {
        $('#follow_up_type').focus();
      }
    } else {
      $('.followup-required').hide();
      $('#follow_up_type').prop('required', false);
    }
  });
  
  // Follow-up type change handler
  $('#follow_up_type').on('change', function() {
    if ($(this).val() !== '' && $('#follow_up_date').val() === '') {
      $('#follow_up_date').focus();
    }
  });
  
  // Initialize follow-up validation on page load
  if ($('#follow_up_date').val() !== '') {
    $('.followup-required').show();
    $('#follow_up_type').prop('required', true);
  }

  // Initialize on page load
  updateFormBasedOnType();
  
  // Form submission handler
  $('#taskForm').on('submit', function(e) {
    const taskType = $('#task_type').val();
    const taskDate = $('#task_date').val();
    const description = $('#description').val().trim();
    const outcome = $('#outcome').val().trim();
    const status = $('#status').val();
    const leadId = $('#lead_id').val();
    
    // Validate lead is selected
    if (!leadId) {
      e.preventDefault();
      alert('⚠️ Related lead is required. Please select a lead.');
      $('#lead_id').focus();
      return false;
    }
    
    // Validate based on task type
    if (taskType === 'Logged') {
      if (!taskDate) {
        e.preventDefault();
        alert('⚠️ Task date/time is required for logged tasks.');
        $('#task_date').focus();
        return false;
      }
      if (!description) {
        e.preventDefault();
        alert('⚠️ Description is required for logged tasks.');
        $('#description').focus();
        return false;
      }
      if (!outcome) {
        e.preventDefault();
        alert('⚠️ Outcome is required for logged tasks. What was the result?');
        $('#outcome').focus();
        return false;
      }
      
      if (!$('#completion_notes').val().trim()) {
        e.preventDefault();
        alert('⚠️ Completion notes are required for logged tasks. Please provide detailed notes.');
        $('#completion_notes').focus();
        return false;
      }
    }
    
    // Validate completed status requires outcome, completion notes, and proof
    if (status === 'Completed') {
      if (!outcome) {
        e.preventDefault();
        alert('⚠️ Outcome is required when task status is Completed.');
        $('#outcome').focus();
        return false;
      }
      
      if (!$('#completion_notes').val().trim()) {
        e.preventDefault();
        alert('⚠️ Completion notes are required when task status is Completed.');
        $('#completion_notes').focus();
        return false;
      }
    }
    
    // For logged/completed tasks, ensure we have geolocation
    if (taskType === 'Logged' || status === 'Completed') {
      const lat = $('#task_latitude').val();
      const lon = $('#task_longitude').val();
      
      // If no coordinates, try to capture one last time
      if (!lat || !lon) {
        e.preventDefault();
        
        if (navigator.geolocation) {
          const form = this;
          
          navigator.geolocation.getCurrentPosition(
            function(position) {
              $('#latitude').val(position.coords.latitude.toFixed(6));
              $('#longitude').val(position.coords.longitude.toFixed(6));
              $('#task_latitude').val(position.coords.latitude.toFixed(6));
              $('#task_longitude').val(position.coords.longitude.toFixed(6));
              
              // Submit form
              form.submit();
            },
            function(error) {
              alert('⚠️ Location Error: ' + error.message + '\n\nFor logged/completed tasks, location capture is required. Please enable location services and try again.');
              console.error('Geolocation error:', error);
            },
            {
              timeout: 10000,
              enableHighAccuracy: true,
              maximumAge: 0
            }
          );
          
          return false;
        } else {
          alert('⚠️ Geolocation is not supported by your browser.\n\nFor logged/completed tasks, location information is required.');
          return false;
        }
      }
    }
    
    // Validate follow-up fields
    const followUpDate = $('#follow_up_date').val();
    const followUpType = $('#follow_up_type').val();
    
    if ((followUpDate && !followUpType) || (!followUpDate && followUpType)) {
      e.preventDefault();
      alert('⚠️ Follow-up requires both Date and Type to be set together.');
      if (!followUpDate) $('#follow_up_date').focus();
      else $('#follow_up_type').focus();
      return false;
    }
    
    return true;
  });
});
</script>

<?php
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>