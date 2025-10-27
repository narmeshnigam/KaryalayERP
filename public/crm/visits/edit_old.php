<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';

if (!crm_role_can_manage($user_role)) {
    flash_add('error', 'You do not have permission to edit Visits', 'crm');
    header('Location: my.php');
    exit;
}

$Visit_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($Visit_id <= 0) {
    flash_add('error', 'Invalid Visit ID', 'crm');
    header('Location: index.php');
    exit;
}

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

// Fetch Visit
$Visit = crm_visit_fetch($conn, $Visit_id);

if (!$Visit) {
    flash_add('error', 'Visit not found', 'crm');
    closeConnection($conn);
    header('Location: index.php');
    exit;
}

// Detect available columns
$has_lead_id = crm_visits_has_column($conn, 'lead_id');
$has_outcome = crm_visits_has_column($conn, 'outcome');
$has_assigned_to = crm_visits_has_column($conn, 'assigned_to');
$has_follow_up_date = crm_visits_has_column($conn, 'follow_up_date');
$has_follow_up_type = crm_visits_has_column($conn, 'follow_up_type');
$has_location = crm_visits_has_column($conn, 'location');
$has_attachment = crm_visits_has_column($conn, 'attachment');
$has_updated_at = crm_visits_has_column($conn, 'updated_at');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $Visit_date = isset($_POST['Visit_date']) ? trim($_POST['Visit_date']) : '';
    $outcome = isset($_POST['outcome']) ? trim($_POST['outcome']) : '';
    $lead_id = isset($_POST['lead_id']) && is_numeric($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
    $assigned_to = isset($_POST['assigned_to']) && is_numeric($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : 0;
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
    }
    
    if ($follow_up_date && !crm_visit_validate_followup_date($follow_up_date)) {
        $errors[] = 'Follow-up date must be today or in the future';
    }
    
    if ($follow_up_type && !in_array($follow_up_type, crm_visit_follow_up_types())) {
        $errors[] = 'Invalid follow-up type';
    }

    // Handle file upload
    $attachment_filename = $Visit['attachment'] ?? '';
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
            // Delete old file
            if ($attachment_filename && file_exists($upload_dir . $attachment_filename)) {
                unlink($upload_dir . $attachment_filename);
            }
            
            $attachment_filename = 'Visit_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $attachment_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload file';
                $attachment_filename = $Visit['attachment'] ?? '';
            }
        }
    }

    if (empty($errors)) {
  // Build dynamic UPDATE
  // Use canonical DB column names (lowercase) when updating
  $updates = ['title = ?', 'notes = ?', 'visit_date = ?'];
        $types = 'sss';
        $values = [$title, $notes, $Visit_date];

        if ($has_lead_id) {
            $updates[] = 'lead_id = ?';
            $types .= 'i';
            $values[] = $lead_id > 0 ? $lead_id : null;
        }

        if ($has_outcome) {
            $updates[] = 'outcome = ?';
            $types .= 's';
            $values[] = $outcome;
        }

        if ($has_assigned_to) {
            $updates[] = 'assigned_to = ?';
            $types .= 'i';
            $values[] = $assigned_to > 0 ? $assigned_to : null;
        }

        if ($has_follow_up_date) {
            $updates[] = 'follow_up_date = ?';
            $types .= 's';
            $values[] = $follow_up_date ?: null;
        }

        if ($has_follow_up_type) {
            $updates[] = 'follow_up_type = ?';
            $types .= 's';
            $values[] = $follow_up_type ?: null;
        }

        if ($has_location) {
            $updates[] = 'location = ?';
            $types .= 's';
            $values[] = $location;
        }

        if ($has_attachment) {
            $updates[] = 'attachment = ?';
            $types .= 's';
            $values[] = $attachment_filename;
        }

        if ($has_updated_at) {
            $updates[] = 'updated_at = NOW()';
        }

        $sql = "UPDATE crm_visits SET " . implode(', ', $updates) . " WHERE id = ? AND deleted_at IS NULL";
        $types .= 'i';
        $values[] = $Visit_id;

        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                // Update lead's last contact time
                if ($has_lead_id && $lead_id > 0) {
                    crm_update_lead_contact_after_Visit($conn, $lead_id);
                }
                
                mysqli_stmt_close($stmt);
                flash_add('success', 'Visit updated successfully!', 'crm');
                closeConnection($conn);
                header('Location: view.php?id=' . $Visit_id);
                exit;
            } else {
                $errors[] = 'Failed to update Visit: ' . mysqli_error($conn);
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

$page_title = 'Edit Visit - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// Convert visit_date (DB column) to datetime-local format for the form
$Visit_datetime_local = '';
$raw_visit_date = crm_visit_get($Visit, 'visit_date', '');
if ($raw_visit_date) {
  try {
    $dt = new DateTime($raw_visit_date);
    $Visit_datetime_local = $dt->format('Y-m-d\TH:i');
  } catch (Exception $e) {
    $Visit_datetime_local = '';
  }
}
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>✏️ Edit Visit</h1>
          <p>Update Visit details</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="view.php?id=<?php echo $Visit_id; ?>" class="btn btn-secondary">Cancel</a>
          <a href="index.php" class="btn btn-accent">← All Visits</a>
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
                     value="<?php echo htmlspecialchars($_POST['title'] ?? $Visit['title'] ?? ''); ?>"
                     placeholder="e.g., Product Demo with Client">
            </div>

            <div class="form-group">
              <label class="form-label">Visit Date & Time <span style="color:red;">*</span></label>
              <input type="datetime-local" name="Visit_date" class="form-control" required
                     value="<?php echo htmlspecialchars($_POST['Visit_date'] ?? $Visit_datetime_local); ?>">
            </div>

            <?php if ($has_lead_id): ?>
            <div class="form-group">
              <label class="form-label">Related Lead (Optional)</label>
              <select name="lead_id" class="form-control">
                <option value="">-- Select Lead --</option>
                <?php foreach ($leads as $lead): ?>
                  <option value="<?php echo $lead['id']; ?>" 
                          <?php echo (($_POST['lead_id'] ?? $Visit['lead_id'] ?? 0) == $lead['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lead['name'] . (isset($lead['company_name']) && $lead['company_name'] ? ' (' . $lead['company_name'] . ')' : '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($has_assigned_to): ?>
            <div class="form-group">
              <label class="form-label">Assign To</label>
              <select name="assigned_to" class="form-control">
                <option value="">-- Unassigned --</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo $emp['id']; ?>" 
                          <?php echo (($_POST['assigned_to'] ?? $Visit['assigned_to'] ?? 0) == $emp['id']) ? 'selected' : ''; ?>>
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
                     value="<?php echo htmlspecialchars($_POST['location'] ?? $Visit['location'] ?? ''); ?>"
                     placeholder="e.g., Conference Room A or Zoom link">
            </div>
            <?php endif; ?>
          </div>

          <!-- Right Column -->
          <div>
            <div class="form-group">
              <label class="form-label">notes <span style="color:red;">*</span></label>
              <textarea name="notes" class="form-control" rows="5" required
                        placeholder="Visit discussion points and objectives..."><?php echo htmlspecialchars($_POST['notes'] ?? $Visit['notes'] ?? ''); ?></textarea>
            </div>

            <?php if ($has_outcome): ?>
            <div class="form-group">
              <label class="form-label">Outcome / Notes (Optional)</label>
              <textarea name="outcome" class="form-control" rows="4"
                        placeholder="Visit summary and decisions..."><?php echo htmlspecialchars($_POST['outcome'] ?? $Visit['outcome'] ?? ''); ?></textarea>
            </div>
            <?php endif; ?>

            <?php if ($has_follow_up_date): ?>
            <div class="form-group">
              <label class="form-label">Follow-Up Date (Optional)</label>
              <input type="date" name="follow_up_date" class="form-control"
                     value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? $Visit['follow_up_date'] ?? ''); ?>"
                     min="<?php echo date('Y-m-d'); ?>">
            </div>
            <?php endif; ?>

            <?php if ($has_follow_up_type): ?>
            <div class="form-group">
              <label class="form-label">Follow-Up Type</label>
              <select name="follow_up_type" class="form-control">
                <option value="">-- None --</option>
                <?php foreach (crm_visit_follow_up_types() as $type): ?>
                  <option value="<?php echo $type; ?>" 
                          <?php echo (($_POST['follow_up_type'] ?? $Visit['follow_up_type'] ?? '') === $type) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($type); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($has_attachment): ?>
            <div class="form-group">
              <label class="form-label">Attachment (Optional)</label>
              <?php if (!empty($Visit['attachment'])): ?>
                <div style="margin-bottom:8px;padding:8px;background:#f8f9fa;border-radius:6px;font-size:13px;">
                  Current: <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars($Visit['attachment']); ?>" target="_blank" style="color:#003581;">
                    <?php echo htmlspecialchars($Visit['attachment']); ?>
                  </a>
                </div>
              <?php endif; ?>
              <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
              <small style="color:#6c757d;font-size:12px;">Max 3MB. Allowed: PDF, JPG, PNG, DOC, DOCX. Leave empty to keep current file.</small>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid #dee2e6;display:flex;gap:12px;justify-content:flex-end;">
          <a href="view.php?id=<?php echo $Visit_id; ?>" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn">💾 Update Visit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
