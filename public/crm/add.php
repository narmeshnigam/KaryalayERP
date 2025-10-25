<?php
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_role = $_SESSION['role'] ?? 'employee';
if (!crm_role_can_manage($user_role)) { flash_add('error','You do not have permission.','crm'); header('Location: index.php'); exit; }

$conn = createConnection(true);
if (!$conn) { echo 'DB connection failed'; exit; }

if (!crm_tables_exist($conn)) {
  closeConnection($conn);
  require_once __DIR__ . '/onboarding.php';
  exit;
}

$type = strtolower($_GET['type'] ?? 'task');
$valid_types = ['task','call','meeting','visit','lead'];
if (!in_array($type, $valid_types, true)) { $type = 'task'; }

$employees = crm_fetch_employees($conn);
$errors = [];
$success = false;
$attachment_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = strtolower($_POST['type'] ?? $type);
    if (!in_array($type, $valid_types, true)) { $type = 'task'; }

    // Attachment validate
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['attachment'];
        if ($f['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Upload error.'; }
        else {
            if ($f['size'] > 3 * 1024 * 1024) { $errors[] = 'Attachment must be <= 3MB.'; }
            $mime = @mime_content_type($f['tmp_name']);
            if (!in_array($mime, crm_allowed_mime_types(), true)) { $errors[] = 'Invalid attachment type.'; }
        }
    }

    $created_by = crm_current_employee_id($conn, (int)$_SESSION['user_id']);
    if (!$created_by) { $created_by = (int)($_SESSION['employee_id'] ?? 0); }

    if ($type === 'task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to = (int)($_POST['assigned_to'] ?? 0);
        $due_date = trim($_POST['due_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $status = 'Pending';
        if ($title === '') { $errors[] = 'Title is required.'; }
        if ($assigned_to <= 0 || !crm_employee_exists($conn, $assigned_to)) { $errors[] = 'Assigned employee not found.'; }
        if ($due_date !== '' && strtotime($due_date) < strtotime('today')) { $errors[] = 'Due date cannot be in the past.'; }
        if (empty($errors)) {
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                if (!crm_ensure_upload_directory()) { $errors[] = 'Unable to create attachment directory.'; }
                else {
                    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $name = 'crm_' . $type . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
                    $dest = crm_upload_directory() . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
                        $attachment_path = 'uploads/crm_attachments/' . $name;
                    } else { $errors[] = 'Failed to store attachment.'; }
                }
            }
        }
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO crm_tasks (title, description, assigned_to, status, due_date, created_by, location, attachment) VALUES (?,?,?,?,?,?,?,?)');
            if ($stmt) {
                $due = ($due_date !== '') ? $due_date : null;
                mysqli_stmt_bind_param($stmt, 'ssisssss', $title, $description, $assigned_to, $status, $due, $created_by, $location, $attachment_path);
                if (mysqli_stmt_execute($stmt)) { $success = true; crm_notify_new_task($conn, mysqli_insert_id($conn)); }
                mysqli_stmt_close($stmt);
            }
        }
    }
    elseif ($type === 'call') {
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $call_date = trim($_POST['call_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($title === '' || $call_date === '') { $errors[] = 'Title and call date are required.'; }
        if ($employee_id <= 0 || !crm_employee_exists($conn, $employee_id)) { $errors[] = 'Employee not found.'; }
        if (empty($errors)) {
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                if (!crm_ensure_upload_directory()) { $errors[] = 'Unable to create attachment directory.'; }
                else {
                    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $name = 'crm_' . $type . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
                    $dest = crm_upload_directory() . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) { $attachment_path = 'uploads/crm_attachments/' . $name; } else { $errors[] = 'Failed to store attachment.'; }
                }
            }
        }
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO crm_calls (title, summary, employee_id, call_date, location, attachment, created_by) VALUES (?,?,?,?,?,?,?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssisssi', $title, $summary, $employee_id, $call_date, $location, $attachment_path, $created_by);
                if (mysqli_stmt_execute($stmt)) { $success = true; }
                mysqli_stmt_close($stmt);
            }
        }
    }
    elseif ($type === 'meeting') {
        $title = trim($_POST['title'] ?? '');
        $agenda = trim($_POST['agenda'] ?? '');
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $meeting_date = trim($_POST['meeting_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($title === '' || $meeting_date === '') { $errors[] = 'Title and meeting date are required.'; }
        if ($employee_id <= 0 || !crm_employee_exists($conn, $employee_id)) { $errors[] = 'Employee not found.'; }
        if (empty($errors)) {
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                if (!crm_ensure_upload_directory()) { $errors[] = 'Unable to create attachment directory.'; }
                else {
                    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $name = 'crm_' . $type . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
                    $dest = crm_upload_directory() . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) { $attachment_path = 'uploads/crm_attachments/' . $name; } else { $errors[] = 'Failed to store attachment.'; }
                }
            }
        }
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO crm_meetings (title, agenda, employee_id, meeting_date, location, attachment, created_by) VALUES (?,?,?,?,?,?,?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssisssi', $title, $agenda, $employee_id, $meeting_date, $location, $attachment_path, $created_by);
                if (mysqli_stmt_execute($stmt)) { $success = true; }
                mysqli_stmt_close($stmt);
            }
        }
    }
    elseif ($type === 'visit') {
        $title = trim($_POST['title'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $visit_date = trim($_POST['visit_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($title === '' || $visit_date === '') { $errors[] = 'Title and visit date are required.'; }
        if ($employee_id <= 0 || !crm_employee_exists($conn, $employee_id)) { $errors[] = 'Employee not found.'; }
        if (empty($errors)) {
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                if (!crm_ensure_upload_directory()) { $errors[] = 'Unable to create attachment directory.'; }
                else {
                    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $name = 'crm_' . $type . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
                    $dest = crm_upload_directory() . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) { $attachment_path = 'uploads/crm_attachments/' . $name; } else { $errors[] = 'Failed to store attachment.'; }
                }
            }
        }
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO crm_visits (title, notes, employee_id, visit_date, location, attachment, created_by) VALUES (?,?,?,?,?,?,?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssisssi', $title, $notes, $employee_id, $visit_date, $location, $attachment_path, $created_by);
                if (mysqli_stmt_execute($stmt)) { $success = true; }
                mysqli_stmt_close($stmt);
            }
        }
    }
    elseif ($type === 'lead') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $source = trim($_POST['source'] ?? '');
        $status = 'New';
        $assigned_to = (int)($_POST['assigned_to'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($name === '') { $errors[] = 'Lead name is required.'; }
        if ($assigned_to && !crm_employee_exists($conn, $assigned_to)) { $errors[] = 'Assigned employee not found.'; }
        if (empty($errors)) {
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                if (!crm_ensure_upload_directory()) { $errors[] = 'Unable to create attachment directory.'; }
                else {
                    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $namef = 'crm_' . $type . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
                    $dest = crm_upload_directory() . DIRECTORY_SEPARATOR . $namef;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) { $attachment_path = 'uploads/crm_attachments/' . $namef; } else { $errors[] = 'Failed to store attachment.'; }
                }
            }
        }
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO crm_leads (name, phone, email, source, status, assigned_to, notes, attachment, location, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
            if ($stmt) {
                $assigned = $assigned_to ?: null;
                mysqli_stmt_bind_param($stmt, 'sssssisiss', $name, $phone, $email, $source, $status, $assigned, $notes, $attachment_path, $location, $created_by);
                if (mysqli_stmt_execute($stmt)) { $success = true; }
                mysqli_stmt_close($stmt);
            }
        }
    }

    if ($success) {
        flash_add('success', 'Saved successfully.', 'crm');
        header('Location: index.php');
        exit;
    }
}

$page_title = 'Add CRM Item - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header"><h1>Add CRM: <?php echo htmlspecialchars(strtoupper($type)); ?></h1></div>

  <?php echo flash_render(); ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><ul>
      <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
    </ul></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
      <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">

      <?php if ($type==='task'): ?>
        <div><label>Title</label><input name="title" class="form-control" required></div>
        <div style="grid-column:1/-1"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
        <div><label>Assign to</label><select name="assigned_to" class="form-control" required>
          <option value="">Select employee</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
          <?php endforeach; ?>
        </select></div>
        <div><label>Due date</label><input type="date" name="due_date" class="form-control"></div>
        <div style="grid-column:1/-1"><label>Location</label><input name="location" class="form-control"></div>
        <div style="grid-column:1/-1"><label>Attachment</label><input type="file" name="attachment" class="form-control" accept="application/pdf,image/*"></div>
      <?php elseif ($type==='call'): ?>
        <div><label>Title</label><input name="title" class="form-control" required></div>
        <div style="grid-column:1/-1"><label>Summary</label><textarea name="summary" class="form-control"></textarea></div>
        <div><label>Employee</label><select name="employee_id" class="form-control" required>
          <option value="">Select employee</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
          <?php endforeach; ?>
        </select></div>
        <div><label>Call date</label><input type="datetime-local" name="call_date" class="form-control" required></div>
        <div style="grid-column:1/-1"><label>Location</label><input name="location" class="form-control"></div>
        <div style="grid-column:1/-1"><label>Attachment</label><input type="file" name="attachment" class="form-control" accept="application/pdf,image/*"></div>
      <?php elseif ($type==='meeting'): ?>
        <div><label>Title</label><input name="title" class="form-control" required></div>
        <div style="grid-column:1/-1"><label>Agenda</label><textarea name="agenda" class="form-control"></textarea></div>
        <div><label>Employee</label><select name="employee_id" class="form-control" required>
          <option value="">Select employee</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
          <?php endforeach; ?>
        </select></div>
        <div><label>Meeting date</label><input type="datetime-local" name="meeting_date" class="form-control" required></div>
        <div style="grid-column:1/-1"><label>Location</label><input name="location" class="form-control"></div>
        <div style="grid-column:1/-1"><label>Attachment</label><input type="file" name="attachment" class="form-control" accept="application/pdf,image/*"></div>
      <?php elseif ($type==='visit'): ?>
        <div><label>Title</label><input name="title" class="form-control" required></div>
        <div style="grid-column:1/-1"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
        <div><label>Employee</label><select name="employee_id" class="form-control" required>
          <option value="">Select employee</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
          <?php endforeach; ?>
        </select></div>
        <div><label>Visit date</label><input type="datetime-local" name="visit_date" class="form-control" required></div>
        <div style="grid-column:1/-1"><label>Location</label><input name="location" class="form-control"></div>
        <div style="grid-column:1/-1"><label>Attachment</label><input type="file" name="attachment" class="form-control" accept="application/pdf,image/*"></div>
      <?php elseif ($type==='lead'): ?>
        <div><label>Name</label><input name="name" class="form-control" required></div>
        <div><label>Phone</label><input name="phone" class="form-control"></div>
        <div><label>Email</label><input type="email" name="email" class="form-control"></div>
        <div><label>Source</label><input name="source" class="form-control" placeholder="Web, Referral, etc."></div>
        <div style="grid-column:1/-1"><label>Assign to</label><select name="assigned_to" class="form-control">
          <option value="">Unassigned</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
          <?php endforeach; ?>
        </select></div>
        <div style="grid-column:1/-1"><label>Location</label><input name="location" class="form-control"></div>
        <div style="grid-column:1/-1"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
        <div style="grid-column:1/-1"><label>Attachment</label><input type="file" name="attachment" class="form-control" accept="application/pdf,image/*"></div>
      <?php endif; ?>

      <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;">
        <a href="index.php" class="btn btn-secondary">Cancel</a>
        <button class="btn" style="background:#003581;color:#fff;">Save</button>
      </div>
    </form>

  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
