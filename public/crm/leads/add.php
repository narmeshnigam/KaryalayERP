<?php
require_once __DIR__ . '/common.php';

crm_leads_require_login();

// Enforce permission to create leads
authz_require_permission($conn, 'crm_leads', 'create');

crm_leads_require_tables($conn);

$employees = crm_fetch_employees($conn);
$employee_map = crm_fetch_employee_map($conn);
$statuses = crm_lead_statuses();
$follow_types = crm_lead_follow_up_types();
$source_options = crm_lead_sources();

$current_employee_id = crm_current_employee_id($conn, (int)$CURRENT_USER_ID);
if (!$current_employee_id) {
  $current_employee_id = 0;
}

$form = [
  'name' => '',
  'company_name' => '',
  'phone' => '',
  'email' => '',
  'source' => '',
  'assigned_to' => $current_employee_id ?: '',
  'notes' => '',
  'interests' => '',
  'follow_up_date' => '',
  'follow_up_type' => '',
  'location' => ''
];

$errors = [];
$attachment_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach (array_keys($form) as $key) {
    $form[$key] = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
  }

  $form['assigned_to'] = (int)$form['assigned_to'];

  if ($form['name'] === '') {
    $errors[] = 'Lead name is required.';
  }
  if ($form['source'] === '') {
    $errors[] = 'Source is required.';
  }
  if ($form['assigned_to'] <= 0 || !crm_employee_exists($conn, $form['assigned_to'])) {
    $errors[] = 'Assigned employee must be selected.';
  }

  if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
  }

  if ($form['follow_up_date'] !== '' && !crm_lead_allowed_follow_up($form['follow_up_date'])) {
    $errors[] = 'Follow-up date cannot be in the past.';
  }

  if ($form['follow_up_date'] !== '' && ($form['follow_up_type'] === '' || !in_array($form['follow_up_type'], $follow_types, true))) {
    $errors[] = 'Select a valid follow-up type when scheduling a follow-up.';
  }

  if ($form['follow_up_date'] === '') {
    $form['follow_up_type'] = '';
  }

  $conflicts = crm_lead_contact_conflicts($conn, $form['phone'], $form['email']);
  if (($conflicts['phone'] ?? false) === true) {
    $errors[] = 'Phone number already exists for another lead.';
  }
  if (($conflicts['email'] ?? false) === true) {
    $errors[] = 'Email already exists for another lead.';
  }

  if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Attachment upload failed.';
    } else {
      if ($file['size'] > 3 * 1024 * 1024) {
        $errors[] = 'Attachment must not exceed 3MB.';
      }
      $mime = @mime_content_type($file['tmp_name']);
      if ($mime && !in_array($mime, crm_allowed_mime_types(), true)) {
        $errors[] = 'Unsupported attachment type.';
      }
    }
  }

  if (!$errors && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    if (!crm_ensure_upload_directory()) {
      $errors[] = 'Unable to prepare attachment directory.';
    } else {
      $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
      $name = 'crm_lead_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
      $dest = crm_upload_directory() . DIRECTORY_SEPARATOR . $name;
      if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
        $attachment_path = 'uploads/crm_attachments/' . $name;
      } else {
        $errors[] = 'Failed to store attachment.';
      }
    }
  }

  if (!$errors) {
    $created_by = $current_employee_id ?: (int)($_SESSION['employee_id'] ?? 0);
    if ($created_by <= 0) {
      $created_by = $form['assigned_to'];
    }
    $status = 'New';
    $follow_up_date = $form['follow_up_date'] !== '' ? $form['follow_up_date'] : null;
    $follow_up_type = $form['follow_up_type'] !== '' ? $form['follow_up_type'] : null;

    // Build INSERT dynamically based on columns present in the database
    $existingCols = [];
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM crm_leads");
    if ($colRes) {
      while ($c = mysqli_fetch_assoc($colRes)) {
        $existingCols[$c['Field']] = true;
      }
      mysqli_free_result($colRes);
    }

    $desired = [
      'name' => $form['name'],
      'company_name' => $form['company_name'],
      'phone' => $form['phone'],
      'email' => $form['email'],
      'source' => $form['source'],
      'status' => $status,
      'notes' => $form['notes'],
      'interests' => $form['interests'],
      'follow_up_date' => $follow_up_date,
      'follow_up_type' => $follow_up_type,
      'follow_up_created' => 0,
      'last_contacted_at' => null,
      'assigned_to' => $form['assigned_to'],
      'attachment' => $attachment_path,
      'location' => $form['location'],
      'created_by' => $created_by
    ];

    $cols = [];
    $placeholders = [];
    $types = '';
    $params = [];
    foreach ($desired as $col => $val) {
      if (!isset($existingCols[$col])) {
        continue;
      }
      $cols[] = $col;
      if ($val === null) {
        // insert SQL NULL directly
        $placeholders[] = 'NULL';
        continue;
      }
      $placeholders[] = '?';
      // assign types: ints for specific columns
      if (in_array($col, ['assigned_to', 'follow_up_created', 'created_by'], true)) {
        $types .= 'i';
        $params[] = (int)$val;
      } else {
        $types .= 's';
        $params[] = (string)$val;
      }
    }

    if (count($cols) === 0) {
      $errors[] = 'No writable columns available in crm_leads table.';
    } else {
      $sql = 'INSERT INTO crm_leads (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
      $stmt = mysqli_prepare($conn, $sql);
      if ($stmt) {
        if ($types !== '') {
          // bind params using references
          $bind_names = [];
          $bind_names[] = $types;
          foreach ($params as $k => $v) {
            // need variables by reference
            $bind_names[] = & $params[$k];
          }
          call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind_names));
        }
        if (mysqli_stmt_execute($stmt)) {
          $new_id = mysqli_insert_id($conn);
          mysqli_stmt_close($stmt);
          if (!empty($form['assigned_to'])) {
            crm_notify_lead_assigned($conn, $new_id, (int)$form['assigned_to']);
          }
          flash_add('success', 'Lead created successfully.', 'crm');
          header('Location: view.php?id=' . $new_id);
          exit;
        }
        $errors[] = 'Failed to save lead. ' . mysqli_error($conn);
        mysqli_stmt_close($stmt);
      } else {
        $errors[] = 'Could not prepare statement: ' . mysqli_error($conn);
      }
    }
  }
}

$page_title = 'Add Lead - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div>
          <h1>➕ Add Lead</h1>
          <p>Capture a new lead, assign ownership, and schedule the next follow-up</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="../index.php" class="btn btn-accent">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-secondary">← All Leads</a>
        </div>
      </div>
    </div>

    <?php if (count($errors) > 0): ?>
      <div class="alert alert-error">
        <strong>❌ Error:</strong><br>
        <?php foreach ($errors as $error): ?>
          • <?php echo htmlspecialchars($error); ?><br>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php echo flash_render(); ?>

    <form method="POST" enctype="multipart/form-data">
      <!-- Lead Information -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📋 Lead Information
        </h3>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
          <div class="form-group">
            <label>Name <span style="color: #dc3545;">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($form['name']); ?>">
          </div>
          <div class="form-group">
            <label>Company</label>
            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($form['company_name']); ?>">
          </div>
          <div class="form-group">
            <label>Source <span style="color: #dc3545;">*</span></label>
            <select name="source" class="form-control" required>
              <option value="">Select source</option>
              <?php foreach ($source_options as $source): ?>
                <option value="<?php echo htmlspecialchars($source); ?>" <?php echo ($form['source'] === $source ? 'selected' : ''); ?>><?php echo htmlspecialchars($source); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📞 Contact Information
        </h3>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($form['phone']); ?>">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form['email']); ?>">
          </div>
          <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($form['location']); ?>" placeholder="Address or GPS notes">
          </div>
        </div>
      </div>

      <!-- Assignment & Tracking -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          👤 Assignment & Tracking
        </h3>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
          <div class="form-group">
            <label>Assign To <span style="color: #dc3545;">*</span></label>
            <select name="assigned_to" class="form-control" required>
              <option value="">Select employee</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?php echo (int)$emp['id']; ?>" <?php echo ($form['assigned_to'] === (int)$emp['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($employee_map[(int)$emp['id']] ?? (($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Follow-up Date</label>
            <input type="date" name="follow_up_date" class="form-control" value="<?php echo htmlspecialchars($form['follow_up_date']); ?>">
          </div>
          <div class="form-group">
            <label>Follow-up Type</label>
            <select name="follow_up_type" class="form-control">
              <option value="">Select type</option>
              <?php foreach ($follow_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($form['follow_up_type'] === $type ? 'selected' : ''); ?>><?php echo htmlspecialchars($type); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column: 1 / -1;">
            <label>Interests (comma separated)</label>
            <input type="text" name="interests" class="form-control" value="<?php echo htmlspecialchars($form['interests']); ?>" placeholder="Product A, Service B">
          </div>
        </div>
      </div>

      <!-- Additional Details -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📝 Additional Details
        </h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($form['notes']); ?></textarea>
          </div>
          <div class="form-group">
            <label>Attachment</label>
            <input type="file" name="attachment" class="form-control" accept="application/pdf,image/*">
            <small style="color:#6b7280;">Accepted: PDF, JPG, PNG. Max 3MB.</small>
          </div>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div style="text-align: center; padding: 20px 0;">
        <button type="submit" class="btn" style="padding: 15px 60px; font-size: 16px;">
          ✅ Save Lead
        </button>
        <a href="index.php" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
          ❌ Cancel
        </a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn); 
}
?>
