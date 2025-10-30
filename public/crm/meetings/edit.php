<?php
require_once __DIR__ . '/common.php';

// Enforce permission to edit meetings
authz_require_permission($conn, 'crm_meetings', 'edit_all');

$meeting_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($meeting_id <= 0) {
    flash_add('error', 'Invalid meeting ID', 'crm');
    header('Location: index.php');
    exit;
}

// Fetch existing meeting
$select_cols = crm_meetings_select_columns($conn);
$sql = "SELECT $select_cols FROM crm_meetings c WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    die('Failed to prepare query');
}

mysqli_stmt_bind_param($stmt, 'i', $meeting_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$meeting = $res ? mysqli_fetch_assoc($res) : null;
if ($res) mysqli_free_result($res);
mysqli_stmt_close($stmt);

if (!$meeting) {
    flash_add('error', 'Meeting not found', 'crm');
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: index.php');
    exit;
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads_for_meetings($conn);
$meeting_statuses = ['Scheduled', 'Completed', 'Cancelled', 'Rescheduled'];
$follow_up_types = crm_meeting_follow_up_types();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_type = trim($_POST['meeting_type'] ?? 'Logged');
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $meeting_date = trim($_POST['meeting_date'] ?? '');
    $outcome = trim($_POST['outcome'] ?? '');
    $status = trim($_POST['status'] ?? 'Scheduled');
    $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
    $location = trim($_POST['location'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $follow_up_type = trim($_POST['follow_up_type'] ?? '');
    
    // Capture geo-coordinates
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    // Store old follow_up_date to check if it changed
    $old_follow_up_date = crm_meeting_get($meeting, 'follow_up_date');

    // Validation based on meeting_type
    if ($lead_id === null) {
        $errors[] = 'Related lead is required.';
    }
    if ($title === '') {
        $errors[] = 'Meeting title is required.';
    }
    
    // Conditional validation based on meeting_type
    if ($meeting_type === 'Logged') {
        // Logged meetings: description and outcome are required, date must be past/present
        if ($description === '') {
            $errors[] = 'Meeting description is required for logged meetings.';
        }
        if ($outcome === '') {
            $errors[] = 'Meeting outcome is required for logged meetings.';
        }
        if ($meeting_date !== '') {
            $meeting_dt = DateTime::createFromFormat('Y-m-d\TH:i', $meeting_date);
            if ($meeting_dt && $meeting_dt > new DateTime()) {
                $errors[] = 'Logged meeting date cannot be in the future.';
            }
        }
        $status = 'Completed'; // Auto-set for logged meetings
    } else {
        // Scheduled meetings: description and outcome are optional, date should be future
        if ($meeting_date !== '') {
            $meeting_dt = DateTime::createFromFormat('Y-m-d\TH:i', $meeting_date);
            if ($meeting_dt && $meeting_dt <= new DateTime()) {
                $errors[] = 'Scheduled meeting date must be in the future.';
            }
        }
        if ($description === '') {
            $description = 'Scheduled meeting - ' . $title; // Auto-generate
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

    // Handle file upload
    $attachment_filename = crm_meeting_get($meeting, 'attachment');
    if (crm_meetings_has_column($conn, 'attachment') && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/crm_attachments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG';
        } elseif ($_FILES['attachment']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'File size must be less than 3MB';
        } else {
            // Delete old file
            if ($attachment_filename && file_exists($upload_dir . $attachment_filename)) {
                @unlink($upload_dir . $attachment_filename);
            }
            
            $attachment_filename = 'meeting_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $attachment_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload file';
                $attachment_filename = crm_meeting_get($meeting, 'attachment');
            }
        }
    }

    if (empty($errors)) {
        // Get existing columns
        $existing_cols = [];
        $cols_res = mysqli_query($conn, "SHOW COLUMNS FROM crm_meetings");
        if ($cols_res) {
            while ($c = mysqli_fetch_assoc($cols_res)) {
                $existing_cols[] = $c['Field'];
            }
            mysqli_free_result($cols_res);
        }

        // Build dynamic data array
        $data = [
            'title' => $title,
            'meeting_date' => $meeting_date
        ];

        if (in_array('description', $existing_cols, true)) {
            $data['description'] = $description !== '' ? $description : null;
        }

        if (in_array('notes', $existing_cols, true)) {
            $data['notes'] = $notes !== '' ? $notes : null;
        }

        if (in_array('lead_id', $existing_cols, true)) {
            $data['lead_id'] = $lead_id;
        }

        if (in_array('outcome', $existing_cols, true)) {
            $data['outcome'] = $outcome !== '' ? $outcome : null;
        }

        if (in_array('status', $existing_cols, true)) {
            $data['status'] = $status;
        }

        if (in_array('assigned_to', $existing_cols, true)) {
            $data['assigned_to'] = $assigned_to;
        }

        if (in_array('location', $existing_cols, true)) {
            $data['location'] = $location !== '' ? $location : null;
        }

        if (in_array('latitude', $existing_cols, true)) {
            $data['latitude'] = $latitude;
        }

        if (in_array('longitude', $existing_cols, true)) {
            $data['longitude'] = $longitude;
        }

        if (in_array('follow_up_date', $existing_cols, true)) {
            $data['follow_up_date'] = $follow_up_date !== '' ? $follow_up_date : null;
        }

        if (in_array('follow_up_type', $existing_cols, true)) {
            $data['follow_up_type'] = $follow_up_type !== '' ? $follow_up_type : null;
        }

        if (in_array('attachment', $existing_cols, true)) {
            $data['attachment'] = $attachment_filename !== '' ? $attachment_filename : null;
        }

        $updates = [];
        $types = '';
        $values = [];
        
        foreach ($data as $col => $val) {
            $updates[] = "$col = ?";
            if (is_int($val)) {
                $types .= 'i';
            } elseif (is_float($val)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $val;
        }

        $sql = "UPDATE crm_meetings SET " . implode(', ', $updates) . " WHERE id = ? AND deleted_at IS NULL";
        $types .= 'i';
        $values[] = $meeting_id;

        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                
                // Update lead's last contact time (only for logged meetings)
                if ($lead_id && $meeting_type === 'Logged') {
                    crm_update_lead_contact_after_meeting($conn, $lead_id);
                }
                
                // If follow-up date changed and is set, create/update follow-up activity
                if ($follow_up_date !== '' && $follow_up_date !== $old_follow_up_date && $lead_id) {
                    // Create follow-up activity
                    crm_create_followup_activity($conn, $lead_id, $assigned_to, $follow_up_date, $follow_up_type, $title);
                    
                    // Update lead's follow_up_date
                    crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
                }
                
                flash_add('success', 'Meeting updated successfully!', 'crm');
                if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                    closeConnection($conn);
                }
                header('Location: view.php?id=' . $meeting_id);
                exit;
            } else {
                $errors[] = 'Database error: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        } else {
            $errors[] = 'Failed to prepare statement: ' . mysqli_error($conn);
        }
    }
}

$page_title = 'Edit Meeting - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// Convert meeting_date to datetime-local format
$meeting_datetime_local = '';
if (crm_meeting_get($meeting, 'meeting_date')) {
    $dt = new DateTime(crm_meeting_get($meeting, 'meeting_date'));
    $meeting_datetime_local = $dt->format('Y-m-d\TH:i');
}

// Determine meeting type based on current data
$current_meeting_type = 'Logged';
if (crm_meeting_get($meeting, 'status') === 'Scheduled' || crm_meeting_get($meeting, 'status') === 'Rescheduled') {
    $meeting_dt = new DateTime(crm_meeting_get($meeting, 'meeting_date'));
    if ($meeting_dt > new DateTime()) {
        $current_meeting_type = 'Scheduled';
    }
}
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
    <!-- Page Header -->
    <div class="page-header">
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div>
          <h1>✏️ Edit Meeting</h1>
          <p>Update meeting details</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="../index.php" class="btn btn-accent">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-secondary">← All Meetings</a>
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

    <form method="post" enctype="multipart/form-data">
        <!-- Meeting Information -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                🤝 Meeting Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="form-group">
                    <label for="meeting_type">Meeting Type <span style="color: #dc3545;">*</span></label>
                    <select id="meeting_type" name="meeting_type" class="form-control" required>
                        <?php $current_type = isset($_POST['meeting_type']) ? $_POST['meeting_type'] : $current_meeting_type; ?>
                        <option value="Logged" <?php echo $current_type === 'Logged' ? 'selected' : ''; ?>>
                            📝 Logged (Past Meeting)
                        </option>
                        <option value="Scheduled" <?php echo $current_type === 'Scheduled' ? 'selected' : ''; ?>>
                            📅 Scheduled (Future Meeting)
                        </option>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Select whether this is a logged past meeting or scheduled future meeting</small>
                </div>
                
                <div class="form-group">
                    <label for="lead_id">Related Lead <span style="color: #dc3545;">*</span></label>
                    <select id="lead_id" name="lead_id" class="form-control select2-lead" required>
                        <option value="">-- Select Lead --</option>
                        <?php foreach ($leads as $l): ?>
                            <?php
                                $l_id = (int)$l['id'];
                                $selected = (isset($_POST['lead_id']) ? (int)$_POST['lead_id'] === $l_id : (int)crm_meeting_get($meeting, 'lead_id') === $l_id);
                            ?>
                            <option value="<?php echo $l_id; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php 
                                    $lbl = htmlspecialchars($l['name']);
                                    if (!empty($l['company_name'])) {
                                        $lbl .= ' (' . htmlspecialchars($l['company_name']) . ')';
                                    }
                                    echo $lbl;
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Search and select a lead</small>
                </div>

                <div class="form-group">
                    <label for="title">Meeting Title <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? crm_meeting_get($meeting, 'title')); ?>" 
                           placeholder="e.g., Product Demo and Q&A" required>
                </div>

                <div class="form-group">
                    <label for="meeting_date">Meeting Date & Time <span style="color: #dc3545;">*</span></label>
                    <input type="datetime-local" id="meeting_date" name="meeting_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['meeting_date'] ?? $meeting_datetime_local); ?>" required>
                    <small id="meeting_date_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="status">Status <span style="color: #dc3545;">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <?php foreach ($meeting_statuses as $s): ?>
                            <?php
                                $selected = (isset($_POST['status']) ? $_POST['status'] === $s : crm_meeting_get($meeting, 'status') === $s);
                            ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="status_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="outcome">Meeting Outcome <span id="outcome_required" style="color: #dc3545;">*</span></label>
                    <input type="text" id="outcome" name="outcome" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['outcome'] ?? crm_meeting_get($meeting, 'outcome')); ?>"
                           placeholder="e.g., Agreed to proceed with trial">
                    <small id="outcome_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="assigned_to">Assigned To <span style="color: #dc3545;">*</span></label>
                    <select id="assigned_to" name="assigned_to" class="form-control select2-employee" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                                $emp_id = (int)$emp['id'];
                                $emp_label = htmlspecialchars(trim($emp['first_name'] . ' ' . $emp['last_name']));
                                $selected = (isset($_POST['assigned_to']) ? (int)$_POST['assigned_to'] === $emp_id : (int)crm_meeting_get($meeting, 'assigned_to') === $emp_id);
                            ?>
                            <option value="<?php echo $emp_id; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo $emp_label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Search and select employee</small>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="description">Meeting Description <span id="description_required" style="color: #dc3545;">*</span></label>
                    <textarea id="description" name="description" class="form-control" rows="4" 
                              placeholder="Meeting agenda and key discussion points..." required><?php echo htmlspecialchars($_POST['description'] ?? crm_meeting_get($meeting, 'description')); ?></textarea>
                    <small id="description_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="location">Location / Meeting Link</label>
                    <input type="text" id="location" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['location'] ?? crm_meeting_get($meeting, 'location')); ?>"
                           placeholder="e.g., Conference Room A or Zoom link">
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? crm_meeting_get($meeting, 'latitude')); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? crm_meeting_get($meeting, 'longitude')); ?>">
                </div>
            </div>
        </div>

        <!-- Follow-Up & Additional Details -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                📅 Follow-Up & Additional Details
            </h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="form-group">
                    <label for="follow_up_date">Follow-Up Date</label>
                    <input type="date" id="follow_up_date" name="follow_up_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? crm_meeting_get($meeting, 'follow_up_date')); ?>">
                    <small style="color: #6c757d; font-size: 12px;">Optional - schedule a follow-up activity</small>
                </div>

                <div class="form-group">
                    <label for="follow_up_type">Follow-Up Type <span id="followup_required" style="color: #dc3545;display:none;">*</span></label>
                    <select id="follow_up_type" name="follow_up_type" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach ($follow_up_types as $ft): ?>
                            <?php
                                $selected = (isset($_POST['follow_up_type']) ? $_POST['follow_up_type'] === $ft : crm_meeting_get($meeting, 'follow_up_type') === $ft);
                            ?>
                            <option value="<?php echo htmlspecialchars($ft); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ft); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Required if follow-up date is selected</small>
                </div>

                <div class="form-group">
                    <label for="attachment">Attachment (PDF, JPG, PNG - Max 3MB)</label>
                    <?php if (crm_meeting_get($meeting, 'attachment')): ?>
                        <div class="attachment-current">
                            <strong>Current:</strong> 
                            <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars(crm_meeting_get($meeting, 'attachment')); ?>" 
                               target="_blank" style="color:#003581;text-decoration:none;">
                                <?php echo htmlspecialchars(crm_meeting_get($meeting, 'attachment')); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small style="color:#6b7280;">Upload a new file to replace the existing attachment. Accepted: PDF, JPG, PNG. Max 3MB.</small>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="notes">Internal Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                              placeholder="Internal notes for your reference (not visible to leads)..."><?php echo htmlspecialchars($_POST['notes'] ?? crm_meeting_get($meeting, 'notes')); ?></textarea>
                    <small style="color: #6c757d; font-size: 12px;">Optional - Add any internal notes or reminders for your team</small>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div style="text-align: center; padding: 20px 0;">
            <button type="submit" class="btn" id="submit_btn" style="padding: 15px 60px; font-size: 16px;">
                ✅ Update Meeting
            </button>
            <a href="view.php?id=<?php echo $meeting_id; ?>" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
                ❌ Cancel
            </a>
        </div>
    </form>
  </div>
</div>

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Select2 for lead dropdown
    $('.select2-lead').select2({
        placeholder: '-- Select Lead --',
        allowClear: true,
        width: '100%'
    });
    
    // Initialize Select2 for employee dropdown
    $('.select2-employee').select2({
        placeholder: '-- Select Employee --',
        allowClear: true,
        width: '100%'
    });

    // Update form behavior based on meeting_type
    function updateFormBasedOnType() {
        const meetingType = $('#meeting_type').val();
        const outcomeField = $('#outcome');
        const outcomeRequired = $('#outcome_required');
        const outcomeHint = $('#outcome_hint');
        const descriptionField = $('#description');
        const descriptionRequired = $('#description_required');
        const descriptionHint = $('#description_hint');
        const meetingDateHint = $('#meeting_date_hint');
        const meetingDateField = $('#meeting_date');
        const statusField = $('#status');
        const statusHint = $('#status_hint');
        const submitBtn = $('#submit_btn');

        if (meetingType === 'Scheduled') {
            // Scheduled meeting: outcome and description optional, date should be future
            outcomeField.removeAttr('required');
            outcomeRequired.hide();
            outcomeHint.text('Optional for scheduled meetings');
            
            descriptionField.removeAttr('required');
            descriptionRequired.hide();
            descriptionHint.text('Optional - will auto-generate if left empty');
            
            meetingDateHint.text('Must be a future date/time');
            statusHint.text('Status can be Scheduled or Rescheduled');
            
            // Set min to current datetime for scheduled meetings
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            meetingDateField.attr('min', minDateTime);
            meetingDateField.removeAttr('max');
            
            submitBtn.html('✅ Update Meeting');
        } else {
            // Logged meeting: outcome and description are required, date should be past/present
            outcomeField.attr('required', 'required');
            outcomeRequired.show();
            outcomeHint.text('Required for logged meetings');
            
            descriptionField.attr('required', 'required');
            descriptionRequired.show();
            descriptionHint.text('Required - describe what was discussed');
            
            meetingDateHint.text('Must be a past or current date/time');
            statusHint.text('Status will be set to Completed');
            
            // Set max to current datetime for logged meetings
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const maxDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            meetingDateField.attr('max', maxDateTime);
            meetingDateField.removeAttr('min');
            
            submitBtn.html('✅ Update Meeting');
        }
    }

    // Run on page load
    updateFormBasedOnType();

    // Run when meeting_type changes
    $('#meeting_type').on('change', updateFormBasedOnType);
    
    // Follow-up date validation
    $('#follow_up_date').on('change', function() {
        const followUpType = $('#follow_up_type');
        const followUpRequired = $('#followup_required');
        
        if ($(this).val() !== '') {
            followUpType.attr('required', 'required');
            followUpRequired.show();
        } else {
            followUpType.removeAttr('required');
            followUpRequired.hide();
        }
    });

    // Capture geolocation if available
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                $('#latitude').val(position.coords.latitude);
                $('#longitude').val(position.coords.longitude);
            },
            function(error) {
                console.log('Geolocation error:', error.message);
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
    }
});
</script>

<?php
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>