<?php
require_once __DIR__ . '/common.php';

// Enforce permission to create calls
authz_require_permission($conn, 'crm_calls', 'create');

$current_employee_id = crm_current_employee_id($conn, (int)$CURRENT_USER_ID);
if (!$current_employee_id && !$IS_SUPER_ADMIN) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    die('Unable to identify your employee record.');
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads($conn);
$outcomes = crm_call_outcomes();
$follow_up_types = crm_lead_follow_up_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $call_date = trim($_POST['call_date'] ?? '');
    $call_type = trim($_POST['call_type'] ?? 'Logged'); // Logged or Scheduled
    $duration = trim($_POST['duration'] ?? '');
    $outcome = trim($_POST['outcome'] ?? '');
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
        $errors[] = 'Call title is required.';
    }
    if ($call_date === '') {
        $errors[] = 'Call date and time are required.';
    } else {
        $call_dt = DateTime::createFromFormat('Y-m-d\TH:i', $call_date);
        if (!$call_dt) {
            $errors[] = 'Invalid call date format.';
        } else {
            // For logged calls, date cannot be in future
            // For scheduled calls, date must be in future
            $now = new DateTime();
            if ($call_type === 'Logged' && $call_dt > $now) {
                $errors[] = 'Logged call date cannot be in the future. Use "Scheduled" type for future calls.';
            } elseif ($call_type === 'Scheduled' && $call_dt <= $now) {
                $errors[] = 'Scheduled call date must be in the future. Use "Logged" type for past calls.';
            }
        }
    }
    
    // Outcome and summary validation based on call type
    if ($call_type === 'Logged') {
        if ($summary === '') {
            $errors[] = 'Call summary is required for logged calls.';
        }
        if ($outcome === '') {
            $errors[] = 'Call outcome is required for logged calls.';
        }
    } else {
        // Scheduled calls - outcome and detailed summary are optional
        if ($summary === '') {
            $summary = 'Scheduled call - ' . $title; // Auto-generate basic summary
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
    if (!crm_employee_exists($conn, $assigned_to)) {
        $errors[] = 'Assigned employee does not exist.';
    }

    // Handle file upload
    $attachment_path = null;
    if (!empty($_FILES['attachment']['name'])) {
        $file = $_FILES['attachment'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 3 * 1024 * 1024) {
                $errors[] = 'Attachment file size must not exceed 3 MB.';
            } elseif (!in_array($file['type'], crm_allowed_mime_types(), true)) {
                $errors[] = 'Attachment must be PDF, JPEG, or PNG.';
            } else {
                if (crm_ensure_upload_directory()) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'call_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $upload_path = crm_upload_directory() . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $attachment_path = $filename;
                    } else {
                        $errors[] = 'Failed to save attachment.';
                    }
                } else {
                    $errors[] = 'Upload directory is not writable.';
                }
            }
        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'File upload error: ' . $file['error'];
        }
    }

    if (empty($errors)) {
        // Build dynamic INSERT based on available columns
        $res_cols = mysqli_query($conn, "SHOW COLUMNS FROM crm_calls");
        $existing_cols = [];
        if ($res_cols) {
            while ($r = mysqli_fetch_assoc($res_cols)) {
                $existing_cols[] = $r['Field'];
            }
            mysqli_free_result($res_cols);
        }

        $data = [
            'lead_id' => $lead_id,
            'title' => $title,
            'summary' => $summary,
            'call_date' => $call_date,
            'duration' => $duration !== '' ? $duration : null,
            'outcome' => $outcome !== '' ? $outcome : null,
            'created_by' => $current_employee_id,
            'assigned_to' => $assigned_to,
            'location' => $location !== '' ? $location : null,
            'attachment' => $attachment_path,
        ];
        
        // Add notes if column exists
        if (in_array('notes', $existing_cols, true)) {
            $data['notes'] = $notes !== '' ? $notes : null;
        }
        
        // Add call_type if column exists
        if (in_array('call_type', $existing_cols, true)) {
            $data['call_type'] = $call_type;
        }
        
        // Add geo-coordinates if columns exist
        if (in_array('latitude', $existing_cols, true)) {
            $data['latitude'] = $latitude;
        }
        if (in_array('longitude', $existing_cols, true)) {
            $data['longitude'] = $longitude;
        }

        if (in_array('follow_up_date', $existing_cols, true) && $follow_up_date !== '') {
            $data['follow_up_date'] = $follow_up_date;
        }
        if (in_array('follow_up_type', $existing_cols, true) && $follow_up_type !== '') {
            $data['follow_up_type'] = $follow_up_type;
        }

        $cols = [];
        $vals = [];
        $types = '';
        $params = [];
        foreach ($data as $col => $val) {
            if (in_array($col, $existing_cols, true)) {
                $cols[] = $col;
                $vals[] = '?';
                if (is_int($val) || ($val !== null && is_numeric($val) && strpos($col, 'latitude') === false && strpos($col, 'longitude') === false)) {
                    $types .= 'i';
                } elseif ($val !== null && (strpos($col, 'latitude') !== false || strpos($col, 'longitude') !== false)) {
                    $types .= 'd'; // double for coordinates
                } else {
                    $types .= 's';
                }
                $params[] = $val;
            }
        }

        $sql = "INSERT INTO crm_calls (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Update lead's last_contacted_at (only for logged calls)
                if ($lead_id && $call_type === 'Logged') {
                    crm_update_lead_contact_time($conn, $lead_id);
                }
                
                // If follow-up is scheduled, create the follow-up activity and update lead's follow_up_date
                if ($follow_up_date !== '' && $lead_id) {
                    // Create follow-up activity (Call, Meeting, Visit, or Task)
                    crm_create_followup_activity($conn, $lead_id, $assigned_to, $follow_up_date, $follow_up_type, $title);
                    
                    // Update lead's follow_up_date
                    crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
                }

                flash_add('success', ($call_type === 'Scheduled' ? 'Call scheduled' : 'Call logged') . ' successfully!', 'crm');
                if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                    closeConnection($conn);
                }
                header('Location: view.php?id=' . $new_id);
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

$page_title = 'Add Call - CRM - ' . APP_NAME;
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
    <!-- Page Header -->
    <div class="page-header">
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div>
          <h1>☎️ Add Call</h1>
          <p>Log a past call or schedule a future call with a lead</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="../index.php" class="btn btn-accent">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-secondary">← All Calls</a>
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
        <!-- Call Information -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                📞 Call Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="form-group">
                    <label for="call_type">Call Type <span style="color: #dc3545;">*</span></label>
                    <select id="call_type" name="call_type" class="form-control" required>
                        <option value="Logged" <?php echo (isset($_POST['call_type']) && $_POST['call_type'] === 'Logged') || !isset($_POST['call_type']) ? 'selected' : ''; ?>>
                            📝 Logged (Past Call)
                        </option>
                        <option value="Scheduled" <?php echo (isset($_POST['call_type']) && $_POST['call_type'] === 'Scheduled') ? 'selected' : ''; ?>>
                            📅 Scheduled (Future Call)
                        </option>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Select whether you're logging a past call or scheduling a future one</small>
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
                    <label for="title">Call Title <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                           placeholder="e.g., Follow-up on product demo" required>
                </div>

                <div class="form-group">
                    <label for="call_date">Call Date & Time <span style="color: #dc3545;">*</span></label>
                    <input type="datetime-local" id="call_date" name="call_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['call_date'] ?? ''); ?>" required>
                    <small id="call_date_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="duration">Duration</label>
                    <input type="text" id="duration" name="duration" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>"
                           placeholder="e.g., 15m, 5m30s">
                </div>

                <div class="form-group">
                    <label for="outcome">Call Outcome <span id="outcome_required" style="color: #dc3545;">*</span></label>
                    <select id="outcome" name="outcome" class="form-control" required>
                        <option value="">-- Select Outcome --</option>
                        <?php foreach ($outcomes as $o): ?>
                            <option value="<?php echo htmlspecialchars($o); ?>"
                                <?php echo (isset($_POST['outcome']) && $_POST['outcome'] === $o) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                    <label for="summary">Call Summary <span id="summary_required" style="color: #dc3545;">*</span></label>
                    <textarea id="summary" name="summary" class="form-control" rows="4" 
                              placeholder="Brief notes on discussion points and outcomes..." required><?php echo htmlspecialchars($_POST['summary'] ?? ''); ?></textarea>
                    <small id="summary_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="location">Location / GPS</label>
                    <input type="text" id="location" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                           placeholder="Optional location or coordinates">
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
                ✅ Save Call
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

    // Update form behavior based on call_type
    function updateFormBasedOnType() {
        const callType = $('#call_type').val();
        const outcomeField = $('#outcome');
        const outcomeRequired = $('#outcome_required');
        const outcomeHint = $('#outcome_hint');
        const summaryField = $('#summary');
        const summaryRequired = $('#summary_required');
        const summaryHint = $('#summary_hint');
        const callDateHint = $('#call_date_hint');
        const callDateField = $('#call_date');
        const submitBtn = $('#submit_btn');

        if (callType === 'Scheduled') {
            // Scheduled call: outcome and summary are optional, date should be future
            outcomeField.removeAttr('required');
            outcomeRequired.hide();
            outcomeHint.text('Optional for scheduled calls');
            
            summaryField.removeAttr('required');
            summaryRequired.hide();
            summaryHint.text('Optional - will auto-generate if left empty');
            
            callDateHint.text('Must be a future date/time');
            
            // Set min to current datetime for scheduled calls
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            callDateField.attr('min', minDateTime);
            callDateField.removeAttr('max');
            
            submitBtn.html('✅ Schedule Call');
        } else {
            // Logged call: outcome and summary are required, date should be past/present
            outcomeField.attr('required', 'required');
            outcomeRequired.show();
            outcomeHint.text('Required for logged calls');
            
            summaryField.attr('required', 'required');
            summaryRequired.show();
            summaryHint.text('Required - describe what was discussed');
            
            callDateHint.text('Must be a past or current date/time');
            
            // Set max to current datetime for logged calls
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const maxDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            callDateField.attr('max', maxDateTime);
            callDateField.removeAttr('min');
            
            submitBtn.html('✅ Save Call');
        }
    }

    // Run on page load
    updateFormBasedOnType();

    // Run when call_type changes
    $('#call_type').on('change', updateFormBasedOnType);
    
    // Follow-up date validation
    $('#follow_up_date').on('change', function() {
        const followUpType = $('#follow_up_type');
        const followUpRequired = $('#followup_required');
        
        if ($(this).val() !== '') {
            followUpType.attr('required', 'required');
            followUpRequired.show();
            if (followUpType.val() === '') {
                followUpType.focus();
            }
        } else {
            followUpType.removeAttr('required');
            followUpRequired.hide();
        }
    });
    
    $('#follow_up_type').on('change', function() {
        const followUpDate = $('#follow_up_date');
        if ($(this).val() !== '' && followUpDate.val() === '') {
            followUpDate.focus();
        }
    });
    
    // Initialize follow-up validation on page load
    if ($('#follow_up_date').val() !== '') {
        $('#follow_up_type').attr('required', 'required');
        $('#followup_required').show();
    }
    
    // Geolocation capture on form submit
    $('form').on('submit', function(e) {
        // Check if geolocation is already captured
        if ($('input[name="latitude"]').length > 0) {
            return true; // Already have coordinates, proceed
        }
        
        // Try to get geolocation
        if (navigator.geolocation) {
            e.preventDefault();
            const form = this;
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // Success: add coordinates to form
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'latitude',
                        value: position.coords.latitude
                    }).appendTo(form);
                    
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'longitude',
                        value: position.coords.longitude
                    }).appendTo(form);
                    
                    // Submit the form
                    form.submit();
                },
                function(error) {
                    // Error or denied: proceed without coordinates
                    console.warn('Geolocation error:', error.message);
                    form.submit();
                },
                {
                    timeout: 5000,
                    maximumAge: 60000
                }
            );
        }
    });
});
</script>

<?php
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>

