<?php
require_once __DIR__ . '/common.php';

// Enforce permission to create meetings
authz_require_permission($conn, 'crm_meetings', 'create');

if (!crm_tables_exist($conn)) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    require_once __DIR__ . '/../onboarding.php';
    exit;
}

$current_employee_id = crm_current_employee_id($conn, (int)$CURRENT_USER_ID);
if (!$current_employee_id && !$IS_SUPER_ADMIN) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    die('Unable to identify your employee record.');
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads_for_meetings($conn);
$meeting_statuses = ['Scheduled', 'Completed', 'Cancelled', 'Rescheduled'];
$follow_up_types = crm_meeting_follow_up_types();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $meeting_date = trim($_POST['meeting_date'] ?? '');
    $meeting_type = trim($_POST['meeting_type'] ?? 'Scheduled'); // Logged or Scheduled
    $outcome = trim($_POST['outcome'] ?? '');
    $status = trim($_POST['status'] ?? 'Scheduled');
    $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : $current_employee_id;
    $location = trim($_POST['location'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $follow_up_type = trim($_POST['follow_up_type'] ?? '');
    
    // Capture geo-coordinates
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    // Validation
    if ($lead_id === null) {
        $errors[] = 'Related lead is required.';
    }
    if ($title === '') {
        $errors[] = 'Meeting title is required.';
    }
    if ($meeting_date === '') {
        $errors[] = 'Meeting date and time are required.';
    } else {
        $meeting_dt = DateTime::createFromFormat('Y-m-d\TH:i', $meeting_date);
        if (!$meeting_dt) {
            $errors[] = 'Invalid meeting date format.';
        } else {
            // For logged meetings, date cannot be in future
            // For scheduled meetings, date must be in future
            $now = new DateTime();
            if ($meeting_type === 'Logged' && $meeting_dt > $now) {
                $errors[] = 'Logged meeting date cannot be in the future. Use "Scheduled" type for future meetings.';
            } elseif ($meeting_type === 'Scheduled' && $meeting_dt <= $now) {
                $errors[] = 'Scheduled meeting date must be in the future. Use "Logged" type for past meetings.';
            }
        }
    }
    
    // Description and outcome validation based on meeting type
    if ($meeting_type === 'Logged') {
        if ($description === '') {
            $errors[] = 'Meeting description is required for logged meetings.';
        }
        if ($outcome === '') {
            $errors[] = 'Meeting outcome is required for logged meetings.';
        }
        $status = 'Completed'; // Auto-set status for logged meetings
    } else {
        // Scheduled meetings - outcome is optional
        if ($description === '') {
            $description = 'Scheduled meeting - ' . $title; // Auto-generate basic description
        }
    }
    
    // Follow-up validation: if date is selected, type is required
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
    $attachment_filename = '';
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
            $attachment_filename = 'meeting_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $attachment_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload file';
                $attachment_filename = '';
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
            $data['lead_id'] = $lead_id > 0 ? $lead_id : null;
        }

        if (in_array('outcome', $existing_cols, true)) {
            $data['outcome'] = $outcome !== '' ? $outcome : null;
        }

        if (in_array('status', $existing_cols, true)) {
            $data['status'] = $status;
        }

        if (in_array('assigned_to', $existing_cols, true)) {
            $data['assigned_to'] = $assigned_to > 0 ? $assigned_to : null;
        }

        if (in_array('created_by', $existing_cols, true)) {
            $data['created_by'] = $current_employee_id > 0 ? $current_employee_id : null;
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

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $types = str_repeat('s', count($columns));
        
        // Adjust types for integers and floats
        $type_arr = str_split($types);
        foreach ($columns as $idx => $col) {
            if (in_array($col, ['lead_id', 'assigned_to', 'created_by'])) {
                $type_arr[$idx] = 'i';
            } elseif (in_array($col, ['latitude', 'longitude'])) {
                $type_arr[$idx] = 'd';
            }
        }
        $types = implode('', $type_arr);

        $sql = "INSERT INTO crm_meetings (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            $values = array_values($data);
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                
                // Update lead's last contact time (only for logged meetings)
                if ($lead_id > 0 && $meeting_type === 'Logged') {
                    crm_update_lead_contact_after_meeting($conn, $lead_id);
                }
                
                // If follow-up is scheduled, create the follow-up activity and update lead's follow_up_date
                if ($follow_up_date !== '' && $lead_id > 0) {
                    // Create follow-up activity (Call, Meeting, Visit, or Task)
                    crm_create_followup_activity($conn, $lead_id, $assigned_to, $follow_up_date, $follow_up_type, $title);
                    
                    // Update lead's follow_up_date
                    crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
                }
                
                mysqli_stmt_close($stmt);
                flash_add('success', 'Meeting ' . ($meeting_type === 'Logged' ? 'logged' : 'scheduled') . ' successfully!', 'crm');
                if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                    closeConnection($conn);
                }
                header('Location: view.php?id=' . $new_id);
                exit;
            } else {
                $errors[] = 'Failed to save meeting: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Failed to prepare statement';
        }
    }
}

$page_title = 'Add Meeting - CRM - ' . APP_NAME;
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

<style>
.meeting-add-header-flex{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;}
.meeting-add-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}
.meeting-add-form-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
.meeting-add-form-grid-1{display:grid;grid-template-columns:1fr;gap:20px;}

@media (max-width:1024px){
.meeting-add-form-grid-3{grid-template-columns:repeat(2,1fr);}
}

@media (max-width:768px){
.meeting-add-header-flex{flex-direction:column;align-items:stretch;}
.meeting-add-header-buttons{width:100%;flex-direction:column;gap:10px;}
.meeting-add-header-buttons .btn{width:100%;text-align:center;}
.meeting-add-form-grid-3{grid-template-columns:1fr;}
}

@media (max-width:480px){
.meeting-add-header-flex h1{font-size:1.5rem;}
.meeting-add-form-grid-3{gap:15px;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="meeting-add-header-flex">
        <div>
          <h1>🤝 Add Meeting</h1>
          <p>Log a past meeting or schedule a future meeting with a lead</p>
        </div>
        <div class="meeting-add-header-buttons">
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
            <div class="meeting-add-form-grid-3">
                <div class="form-group">
                    <label for="meeting_type">Meeting Type <span style="color: #dc3545;">*</span></label>
                    <select id="meeting_type" name="meeting_type" class="form-control" required>
                        <option value="Logged" <?php echo (isset($_POST['meeting_type']) && $_POST['meeting_type'] === 'Logged') || !isset($_POST['meeting_type']) ? 'selected' : ''; ?>>
                            📝 Logged (Past Meeting)
                        </option>
                        <option value="Scheduled" <?php echo (isset($_POST['meeting_type']) && $_POST['meeting_type'] === 'Scheduled') ? 'selected' : ''; ?>>
                            📅 Scheduled (Future Meeting)
                        </option>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Select whether you're logging a past meeting or scheduling a future one</small>
                </div>
                
                <div class="form-group">
                    <label for="lead_id">Related Lead <span style="color: #dc3545;">*</span></label>
                    <select id="lead_id" name="lead_id" class="form-control select2-lead" required>
                        <option value="">-- Select Lead --</option>
                        <?php $prefilled_lead = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : (isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0); ?>
                        <?php foreach ($leads as $l): ?>
                            <option value="<?php echo (int)$l['id']; ?>"
                                <?php echo $prefilled_lead === (int)$l['id'] ? 'selected' : ''; ?>>
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
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                           placeholder="e.g., Product Demo and Q&A" required>
                </div>

                <div class="form-group">
                    <label for="meeting_date">Meeting Date & Time <span style="color: #dc3545;">*</span></label>
                    <input type="datetime-local" id="meeting_date" name="meeting_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['meeting_date'] ?? ''); ?>" required>
                    <small id="meeting_date_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="status">Status <span style="color: #dc3545;">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <?php foreach ($meeting_statuses as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"
                                <?php echo (isset($_POST['status']) && $_POST['status'] === $s) || (!isset($_POST['status']) && $s === 'Scheduled') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="status_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="outcome">Meeting Outcome <span id="outcome_required" style="color: #dc3545;">*</span></label>
                    <input type="text" id="outcome" name="outcome" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['outcome'] ?? ''); ?>"
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
                                $selected = (isset($_POST['assigned_to']) && (int)$_POST['assigned_to'] === $emp_id) 
                                            || (!isset($_POST['assigned_to']) && $emp_id === $current_employee_id);
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
                              placeholder="Meeting agenda and key discussion points..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <small id="description_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="location">Location / Meeting Link</label>
                    <input type="text" id="location" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                           placeholder="e.g., Conference Room A or Zoom link">
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Follow-Up & Additional Details -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                📅 Follow-Up & Additional Details
            </h3>
            <div class="meeting-add-form-grid-3">
                <div class="form-group">
                    <label for="follow_up_date">Follow-Up Date</label>
                    <input type="date" id="follow_up_date" name="follow_up_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? ''); ?>">
                    <small style="color: #6c757d; font-size: 12px;">Optional - schedule a follow-up activity</small>
                </div>

                <div class="form-group">
                    <label for="follow_up_type">Follow-Up Type <span id="followup_required" style="color: #dc3545;display:none;">*</span></label>
                    <select id="follow_up_type" name="follow_up_type" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach ($follow_up_types as $ft): ?>
                            <option value="<?php echo htmlspecialchars($ft); ?>"
                                <?php echo (isset($_POST['follow_up_type']) && $_POST['follow_up_type'] === $ft) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ft); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Required if follow-up date is selected</small>
                </div>

                <div class="form-group">
                    <label for="attachment">Attachment (PDF, JPG, PNG - Max 3MB)</label>
                    <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small style="color:#6b7280;">Accepted: PDF, JPG, PNG. Max 3MB.</small>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="notes">Internal Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                              placeholder="Internal notes for your reference (not visible to leads)..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    <small style="color: #6c757d; font-size: 12px;">Optional - Add any internal notes or reminders for your team</small>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div style="text-align: center; padding: 20px 0;">
            <button type="submit" class="btn" id="submit_btn" style="padding: 15px 60px; font-size: 16px;">
                ✅ Save Meeting
            </button>
            <a href="index.php" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
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
            // Scheduled meeting: outcome is optional, description optional, date should be future
            outcomeField.removeAttr('required');
            outcomeRequired.hide();
            outcomeHint.text('Optional for scheduled meetings');
            
            descriptionField.removeAttr('required');
            descriptionRequired.hide();
            descriptionHint.text('Optional - will auto-generate if left empty');
            
            meetingDateHint.text('Must be a future date/time');
            statusHint.text('Auto-set to Scheduled');
            statusField.val('Scheduled');
            
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
            
            submitBtn.html('✅ Schedule Meeting');
        } else {
            // Logged meeting: outcome and description are required, date should be past/present
            outcomeField.attr('required', 'required');
            outcomeRequired.show();
            outcomeHint.text('Required for logged meetings');
            
            descriptionField.attr('required', 'required');
            descriptionRequired.show();
            descriptionHint.text('Required - describe what was discussed');
            
            meetingDateHint.text('Must be a past or current date/time');
            statusHint.text('Auto-set to Completed');
            statusField.val('Completed');
            
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
            
            submitBtn.html('✅ Save Meeting');
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
