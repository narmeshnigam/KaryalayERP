<?php
require_once __DIR__ . '/common.php';

crm_leads_require_login();
$user_role = $_SESSION['role'] ?? 'employee';
if (!crm_role_can_manage($user_role)) {
    flash_add('error', 'Only managers or admins can edit leads.', 'crm');
    header('Location: index.php');
    exit;
}

$lead_id = (int)($_GET['id'] ?? 0);
if ($lead_id <= 0) {
    flash_add('error', 'Invalid lead request.', 'crm');
    header('Location: index.php');
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Database connection error.</div></div></div>';
    exit;
}

crm_leads_require_tables($conn);

$lead = crm_lead_fetch($conn, $lead_id);
if (!$lead) {
    closeConnection($conn);
    flash_add('error', 'Lead not found.', 'crm');
    header('Location: index.php');
    exit;
}

$employees = crm_fetch_employees($conn);
$employee_map = crm_fetch_employee_map($conn);
$statuses = crm_lead_statuses();
$follow_types = crm_lead_follow_up_types();
$source_options = crm_lead_sources();

$form = [
    'name' => $lead['name'] ?? '',
    'company_name' => $lead['company_name'] ?? '',
    'phone' => $lead['phone'] ?? '',
    'email' => $lead['email'] ?? '',
    'source' => $lead['source'] ?? '',
    'status' => $lead['status'] ?? 'New',
    'assigned_to' => (int)($lead['assigned_to'] ?? 0),
    'notes' => $lead['notes'] ?? '',
    'interests' => $lead['interests'] ?? '',
    'follow_up_date' => $lead['follow_up_date'] ?? '',
    'follow_up_type' => $lead['follow_up_type'] ?? '',
    'location' => $lead['location'] ?? ''
];

$errors = [];
$attachment_path = $lead['attachment'] ?? null;
$existing_attachment = $attachment_path;

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
        $errors[] = 'Select a follow-up type for the chosen date.';
    }

    if ($form['follow_up_date'] === '') {
        $form['follow_up_type'] = '';
    }

    if (!in_array($form['status'], $statuses, true)) {
        $errors[] = 'Invalid status selected.';
    } elseif (!in_array($form['status'], crm_lead_allowed_statuses($lead['status'] ?? ''), true)) {
        $errors[] = 'Status change not permitted based on current progression.';
    }

    $conflicts = crm_lead_contact_conflicts($conn, $form['phone'], $form['email'], $lead_id);
    if (($conflicts['phone'] ?? false) === true) {
        $errors[] = 'Phone number already exists for another lead.';
    }
    if (($conflicts['email'] ?? false) === true) {
        $errors[] = 'Email already exists for another lead.';
    }

    $remove_attachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1';

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
        $remove_attachment = false;
        if ($existing_attachment && $existing_attachment !== $attachment_path) {
          $old = realpath(__DIR__ . '/../../../' . $existing_attachment);
          if ($old && file_exists($old)) {
            @unlink($old);
          }
        }
            } else {
                $errors[] = 'Failed to store attachment.';
            }
        }
    }

    if ($remove_attachment) {
        if ($attachment_path && $attachment_path === $existing_attachment) {
            $full = realpath(__DIR__ . '/../../../' . $attachment_path);
            if ($full && file_exists($full)) {
                @unlink($full);
            }
        }
        $attachment_path = null;
    }

    if (!$errors) {
        $follow_up_date = $form['follow_up_date'] !== '' ? $form['follow_up_date'] : null;
        $follow_up_type = $form['follow_up_type'] !== '' ? $form['follow_up_type'] : null;
        $follow_up_created = (int)($lead['follow_up_created'] ?? 0);
        if ($follow_up_date !== ($lead['follow_up_date'] ?? null) || $follow_up_type !== ($lead['follow_up_type'] ?? null)) {
            $follow_up_created = 0;
        }

        $update_data = [
            'name' => $form['name'],
            'company_name' => $form['company_name'],
            'phone' => $form['phone'],
            'email' => $form['email'],
            'source' => $form['source'],
            'status' => $form['status'],
            'notes' => $form['notes'],
            'interests' => $form['interests'],
            'follow_up_date' => $follow_up_date,
            'follow_up_type' => $follow_up_type,
            'follow_up_created' => $follow_up_created,
            'assigned_to' => $form['assigned_to'],
            'attachment' => $attachment_path,
            'location' => $form['location']
        ];

        crm_lead_reset_follow_up_on_final_status($update_data);
        $follow_up_created = $update_data['follow_up_created'];

        $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET name = ?, company_name = ?, phone = ?, email = ?, source = ?, status = ?, notes = ?, interests = ?, follow_up_date = ?, follow_up_type = ?, follow_up_created = ?, assigned_to = ?, attachment = ?, location = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        if ($stmt) {
      mysqli_stmt_bind_param(
        $stmt,
        'ssssssssssiissi',
        $update_data['name'],
        $update_data['company_name'],
        $update_data['phone'],
        $update_data['email'],
        $update_data['source'],
        $update_data['status'],
        $update_data['notes'],
        $update_data['interests'],
        $update_data['follow_up_date'],
        $update_data['follow_up_type'],
        $follow_up_created,
        $update_data['assigned_to'],
        $update_data['attachment'],
        $update_data['location'],
        $lead_id
      );
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                flash_add('success', 'Lead updated successfully.', 'crm');
                header('Location: view.php?id=' . $lead_id);
                exit;
            }
            $errors[] = 'Failed to update lead. ' . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Could not prepare update statement.';
        }
    }
}

$page_title = 'Edit Lead - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
          <h1>✏️ Edit Lead</h1>
          <p style="margin:6px 0 0;">Adjust lead details, ownership, and progression status.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="../index.php" class="btn btn-accent">← CRM Dashboard</a>
          <a href="view.php?id=<?php echo (int)$lead_id; ?>" class="btn btn-secondary">← View Lead</a>
          <a href="index.php" class="btn btn-secondary">All Leads</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>
    <?php if ($errors): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <!-- Lead Information -->
      <div class="card" style="margin-bottom:20px;">
        <h3 style="color: #003581; margin-bottom: 12px; border-bottom: 2px solid #003581; padding-bottom: 8px;">📋 Lead Information</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div class="form-group">
            <label>Name <span style="color:#dc3545;">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($form['name']); ?>">
          </div>
          <div class="form-group">
            <label>Company</label>
            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($form['company_name']); ?>">
          </div>
          <div class="form-group">
            <label>Source <span style="color:#dc3545;">*</span></label>
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
      <div class="card" style="margin-bottom:20px;">
        <h3 style="color: #003581; margin-bottom: 12px; border-bottom: 2px solid #003581; padding-bottom: 8px;">📞 Contact Information</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
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
            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($form['location']); ?>">
          </div>
        </div>
      </div>

      <!-- Assignment & Tracking -->
      <div class="card" style="margin-bottom:20px;">
        <h3 style="color: #003581; margin-bottom: 12px; border-bottom: 2px solid #003581; padding-bottom: 8px;">👤 Assignment & Tracking</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div class="form-group">
            <label>Assign To <span style="color:#dc3545;">*</span></label>
            <select name="assigned_to" class="form-control" required>
              <option value="">Select employee</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?php echo (int)$emp['id']; ?>" <?php echo ($form['assigned_to'] === (int)$emp['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($employee_map[(int)$emp['id']] ?? (($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status <span style="color:#dc3545;">*</span></label>
            <select name="status" class="form-control" required>
              <?php foreach (crm_lead_allowed_statuses($lead['status'] ?? '') as $statusOption): ?>
                <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($form['status'] === $statusOption ? 'selected' : ''); ?>><?php echo htmlspecialchars($statusOption); ?></option>
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

          <div class="form-group" style="grid-column:1 / -1;">
            <label>Interests (comma separated)</label>
            <input type="text" name="interests" class="form-control" value="<?php echo htmlspecialchars($form['interests']); ?>" placeholder="Product A, Service B">
          </div>
        </div>
      </div>

      <!-- Additional Details -->
      <div class="card" style="margin-bottom:20px;">
        <h3 style="color: #003581; margin-bottom: 12px; border-bottom: 2px solid #003581; padding-bottom: 8px;">📝 Additional Details</h3>
        <div style="display:grid;grid-template-columns:1fr;gap:16px;">
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($form['notes']); ?></textarea>
          </div>
          <div class="form-group">
            <label>Attachment</label>
            <?php if ($existing_attachment): ?>
              <div style="margin-bottom:8px;">
                <a class="btn btn-secondary" href="<?php echo htmlspecialchars('../' . $existing_attachment); ?>" target="_blank">View current attachment</a>
              </div>
            <?php endif; ?>
            <input type="file" name="attachment" class="form-control" accept="application/pdf,image/*">
            <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
              <label style="display:flex;align-items:center;gap:8px;font-size:14px;color:#111;">
                <input type="checkbox" name="remove_attachment" value="1">
                Remove existing attachment
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div style="text-align:center;padding:20px 0;">
        <button type="submit" class="btn" style="padding:12px 48px;font-size:15px;background:#003581;color:#fff;">✅ Save Changes</button>
        <a href="view.php?id=<?php echo (int)$lead_id; ?>" class="btn btn-accent" style="padding:12px 48px;font-size:15px;margin-left:12px;text-decoration:none;">❌ Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
