<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';

$conn = createConnection(true);
if (!$conn) {
    die('Database connection failed');
}

if (!crm_tables_exist($conn)) {
    closeConnection($conn);
    require_once __DIR__ . '/../onboarding.php';
    exit;
}

$current_employee_id = crm_current_employee_id($conn, $user_id);

// Detect available columns
$has_lead_id = crm_visits_has_column($conn, 'lead_id');
$has_outcome = crm_visits_has_column($conn, 'outcome');
$has_assigned_to = crm_visits_has_column($conn, 'assigned_to');
$has_follow_up_date = crm_visits_has_column($conn, 'follow_up_date');
$has_follow_up_type = crm_visits_has_column($conn, 'follow_up_type');
$has_created_by = crm_visits_has_column($conn, 'created_by');
$has_location = crm_visits_has_column($conn, 'location');
$has_attachment = crm_visits_has_column($conn, 'attachment');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $Visit_date = isset($_POST['Visit_date']) ? trim($_POST['Visit_date']) : '';
    $outcome = isset($_POST['outcome']) ? trim($_POST['outcome']) : '';
    $lead_id = isset($_POST['lead_id']) && is_numeric($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
    $assigned_to = isset($_POST['assigned_to']) && is_numeric($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $current_employee_id;
    $follow_up_date = isset($_POST['follow_up_date']) ? trim($_POST['follow_up_date']) : '';
    $follow_up_type = isset($_POST['follow_up_type']) ? trim($_POST['follow_up_type']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';

    if (!$title) {
        $errors[] = 'Visit title is required';
    }
    if (!$notes) {
        $errors[] = 'Visit notes is required';
    }
    if (!$Visit_date) {
        $errors[] = 'Visit date and time are required';
    } elseif (!crm_visit_validate_date($Visit_date)) {
        $errors[] = 'Visit date must be today or in the future';
    }
    
    if ($follow_up_date && !crm_visit_validate_followup_date($follow_up_date)) {
        $errors[] = 'Follow-up date must be today or in the future';
    }
    
    if ($follow_up_type && !in_array($follow_up_type, crm_visit_follow_up_types())) {
        $errors[] = 'Invalid follow-up type';
    }

    // Handle file upload
    $attachment_filename = '';
    if ($has_attachment && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/crm_attachments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX';
        } elseif ($_FILES['attachment']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'File size must be less than 3MB';
        } else {
            $attachment_filename = 'Visit_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $attachment_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload file';
                $attachment_filename = '';
            }
        }
    }

    if (empty($errors)) {
        // Build dynamic INSERT
        $columns = ['title', 'notes', 'Visit_date'];
        $placeholders = ['?', '?', '?'];
        $types = 'sss';
        $values = [$title, $notes, $Visit_date];

        if ($has_lead_id && $lead_id > 0) {
            $columns[] = 'lead_id';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $lead_id;
        }

        if ($has_outcome && $outcome) {
            $columns[] = 'outcome';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $outcome;
        }

        if ($has_assigned_to && $assigned_to > 0) {
            $columns[] = 'assigned_to';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $assigned_to;
        }

        if ($has_created_by && $current_employee_id > 0) {
            $columns[] = 'created_by';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $current_employee_id;
        }

        if ($has_follow_up_date && $follow_up_date) {
            $columns[] = 'follow_up_date';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $follow_up_date;
        }

        if ($has_follow_up_type && $follow_up_type) {
            $columns[] = 'follow_up_type';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $follow_up_type;
        }

        if ($has_location && $location) {
            $columns[] = 'location';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $location;
        }

        if ($has_attachment && $attachment_filename) {
            $columns[] = 'attachment';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $attachment_filename;
        }

        $sql = "INSERT INTO crm_visits (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                
                // Update lead's last contact time
                if ($has_lead_id && $lead_id > 0) {
                    crm_update_lead_contact_after_Visit($conn, $lead_id);
                }
                
                mysqli_stmt_close($stmt);
                flash_add('success', 'Visit scheduled successfully!', 'crm');
                closeConnection($conn);
                header('Location: view.php?id=' . $new_id);
                exit;
            } else {
                $errors[] = 'Failed to schedule Visit: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Failed to prepare statement';
        }
    }
}

// Fetch employees for assignment
$employees = [];
if ($has_assigned_to) {
  $emp_sql = "SELECT id, employee_code, first_name, last_name FROM employees";
  $emp_where = [];
  if (function_exists('crm_visits_has_column_in_table')) {
    if (crm_visits_has_column_in_table($conn, 'employees', 'is_active')) {
      $emp_where[] = 'is_active = 1';
    } elseif (crm_visits_has_column_in_table($conn, 'employees', 'active')) {
      $emp_where[] = 'active = 1';
    }
  }
  if ($emp_where) {
    $emp_sql .= ' WHERE ' . implode(' AND ', $emp_where);
  }
  $emp_sql .= ' ORDER BY first_name, last_name';

  $emp_res = mysqli_query($conn, $emp_sql);
  if ($emp_res) {
    while ($row = mysqli_fetch_assoc($emp_res)) {
      $employees[] = $row;
    }
    mysqli_free_result($emp_res);
  }
}

// Fetch leads
$leads = [];
if ($has_lead_id) {
    $leads = crm_fetch_active_leads_for_Visits($conn);
}

$page_title = 'Schedule New Visit - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>➕ Schedule New Visit</h1>
          <p>Create a new Visit entry</p>
        </div>
        <div>
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-accent">← Back to List</a>
        </div>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <strong>Please fix the following errors:</strong>
        <ul style="margin:8px 0 0 20px;">
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
          <!-- Left Column -->
          <div>
            <div class="form-group">
              <label class="form-label">Visit Title <span style="color:red;">*</span></label>
              <input type="text" name="title" class="form-control" required 
                     value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                     placeholder="e.g., Product Demo with Client">
            </div>

            <div class="form-group">
              <label class="form-label">Visit Date & Time <span style="color:red;">*</span></label>
              <input type="datetime-local" name="Visit_date" class="form-control" required
                     value="<?php echo isset($_POST['Visit_date']) ? htmlspecialchars($_POST['Visit_date']) : ''; ?>"
                     min="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>

            <?php if ($has_lead_id): ?>
            <div class="form-group">
              <label class="form-label">Related Lead (Optional)</label>
              <select name="lead_id" class="form-control">
                <option value="">-- Select Lead --</option>
                <?php $prefilled_lead = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : (isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0); ?>
                <?php foreach ($leads as $lead): ?>
                  <option value="<?php echo $lead['id']; ?>" <?php echo $prefilled_lead == $lead['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lead['name'] . (isset($lead['company_name']) && $lead['company_name'] ? ' (' . $lead['company_name'] . ')' : '')); ?>
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
                  <option value="<?php echo $emp['id']; ?>" 
                          <?php echo (isset($_POST['assigned_to']) ? $_POST['assigned_to'] == $emp['id'] : $current_employee_id == $emp['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(trim($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name'])); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($has_location): ?>
            <div class="form-group">
              <label class="form-label">Location / Visit Link</label>
              <input type="text" name="location" class="form-control"
                     value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                     placeholder="e.g., Conference Room A or Zoom link">
            </div>
            <?php endif; ?>
          </div>

          <!-- Right Column -->
          <div>
            <div class="form-group">
              <label class="form-label">notes <span style="color:red;">*</span></label>
              <textarea name="notes" class="form-control" rows="5" required
                        placeholder="Visit discussion points and objectives..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
            </div>

            <?php if ($has_outcome): ?>
            <div class="form-group">
              <label class="form-label">Outcome / Notes (Optional)</label>
              <textarea name="outcome" class="form-control" rows="4"
                        placeholder="Visit summary and decisions (can be added after the Visit)..."><?php echo isset($_POST['outcome']) ? htmlspecialchars($_POST['outcome']) : ''; ?></textarea>
            </div>
            <?php endif; ?>

            <?php if ($has_follow_up_date): ?>
            <div class="form-group">
              <label class="form-label">Follow-Up Date (Optional)</label>
              <input type="date" name="follow_up_date" class="form-control"
                     value="<?php echo isset($_POST['follow_up_date']) ? htmlspecialchars($_POST['follow_up_date']) : ''; ?>"
                     min="<?php echo date('Y-m-d'); ?>">
            </div>
            <?php endif; ?>

            <?php if ($has_follow_up_type): ?>
            <div class="form-group">
              <label class="form-label">Follow-Up Type</label>
              <select name="follow_up_type" class="form-control">
                <option value="">-- None --</option>
                <?php foreach (crm_visit_follow_up_types() as $type): ?>
                  <option value="<?php echo $type; ?>" <?php echo (isset($_POST['follow_up_type']) && $_POST['follow_up_type'] === $type) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($type); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($has_attachment): ?>
            <div class="form-group">
              <label class="form-label">Attachment (Optional)</label>
              <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
              <small style="color:#6c757d;font-size:12px;">Max 3MB. Allowed: PDF, JPG, PNG, DOC, DOCX</small>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid #dee2e6;display:flex;gap:12px;justify-content:flex-end;">
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn">📅 Schedule Visit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
