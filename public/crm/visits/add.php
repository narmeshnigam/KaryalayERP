<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
$user_id = (int)$_SESSION['user_id'];

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
if (!$current_employee_id && !crm_role_can_manage($user_role)) {
    closeConnection($conn);
    die('Unable to identify your employee record.');
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads_for_visits($conn);
$follow_up_types = crm_visit_follow_up_types();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $visit_date = trim($_POST['visit_date'] ?? '');
    $visit_type = trim($_POST['visit_type'] ?? 'Scheduled'); // Logged or Scheduled
    $outcome = trim($_POST['outcome'] ?? '');
    $status = trim($_POST['status'] ?? 'Planned');
    $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : $current_employee_id;
    $location = trim($_POST['location'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $follow_up_type = trim($_POST['follow_up_type'] ?? '');
    
    // Capture geo-coordinates (employee's current location when creating)
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;
    
    // Capture visited coordinates (mandatory for logged visits)
    $visited_latitude = isset($_POST['visited_latitude']) && $_POST['visited_latitude'] !== '' ? floatval($_POST['visited_latitude']) : null;
    $visited_longitude = isset($_POST['visited_longitude']) && $_POST['visited_longitude'] !== '' ? floatval($_POST['visited_longitude']) : null;

    // Validation
    if ($lead_id === null) {
        $errors[] = 'Related lead is required.';
    }
    if ($title === '') {
        $errors[] = 'Visit title is required.';
    }
    if ($assigned_to <= 0) {
        $errors[] = 'Assigned employee is required.';
    } else {
        // Verify assigned employee exists
        if (!crm_employee_exists($conn, $assigned_to)) {
            $errors[] = 'Assigned employee does not exist.';
        }
    }
    if ($visit_date === '') {
        $errors[] = 'Visit date and time are required.';
    } else {
        $visit_dt = DateTime::createFromFormat('Y-m-d\TH:i', $visit_date);
        if (!$visit_dt) {
            $errors[] = 'Invalid visit date format.';
        } else {
            // For logged visits, date cannot be in future
            // For scheduled visits, date must be in future
            $now = new DateTime();
            if ($visit_type === 'Logged' && $visit_dt > $now) {
                $errors[] = 'Logged visit date cannot be in the future. Use "Scheduled" type for future visits.';
            } elseif ($visit_type === 'Scheduled' && $visit_dt <= $now) {
                $errors[] = 'Scheduled visit date must be in the future. Use "Logged" type for past visits.';
            }
        }
    }
    
    // Description and outcome validation based on visit type
    if ($visit_type === 'Logged') {
        if ($description === '') {
            $errors[] = 'Visit description is required for logged visits.';
        }
        if ($outcome === '') {
            $errors[] = 'Visit outcome is required for logged visits.';
        }
        // Visited coordinates are mandatory for logged visits
        if ($visited_latitude === null || $visited_longitude === null) {
            $errors[] = 'Visited location coordinates are required for logged visits. Please allow location access.';
        }
        $status = 'Completed'; // Auto-set status for logged visits
    } else {
        // Scheduled visits - description/outcome are optional
        if ($description === '') {
            $description = 'Scheduled visit - ' . $title; // Auto-generate basic description
        }
        $status = 'Planned';
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

    // Handle visit proof image upload (mandatory for logged visits)
    $visit_proof_filename = '';
    if ($visit_type === 'Logged') {
        if (crm_visits_has_column($conn, 'visit_proof_image') && isset($_FILES['visit_proof_image']) && $_FILES['visit_proof_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../../uploads/crm_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['visit_proof_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                $errors[] = 'Visit proof must be JPG or PNG image';
            } elseif ($_FILES['visit_proof_image']['size'] > 3 * 1024 * 1024) {
                $errors[] = 'Visit proof image size must be less than 3MB';
            } else {
                $visit_proof_filename = 'visit_proof_' . time() . '_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $visit_proof_filename;
                
                if (!move_uploaded_file($_FILES['visit_proof_image']['tmp_name'], $upload_path)) {
                    $errors[] = 'Failed to upload visit proof image';
                    $visit_proof_filename = '';
                }
            }
        } else {
            $errors[] = 'Visit proof image is required for logged visits';
        }
    }

    // Handle generic attachment upload (optional)
    $attachment_filename = '';
    if (crm_visits_has_column($conn, 'attachment') && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/crm_attachments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Attachment must be PDF, JPG, or PNG';
        } elseif ($_FILES['attachment']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Attachment size must be less than 3MB';
        } else {
            $attachment_filename = 'visit_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $attachment_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload attachment';
                $attachment_filename = '';
            }
        }
    }

    if (empty($errors)) {
        // Get existing columns
        $existing_cols = [];
        $cols_res = mysqli_query($conn, "SHOW COLUMNS FROM crm_visits");
        if ($cols_res) {
            while ($c = mysqli_fetch_assoc($cols_res)) {
                $existing_cols[] = $c['Field'];
            }
            mysqli_free_result($cols_res);
        }

        // Build dynamic data array
        $data = [
            'title' => $title,
            'visit_date' => $visit_date,
            'created_by' => $current_employee_id
        ];

        // Add optional columns if they exist
        if (in_array('description', $existing_cols)) $data['description'] = $description !== '' ? $description : null;
        if (in_array('purpose', $existing_cols)) $data['purpose'] = $description !== '' ? $description : null; // Map description to purpose for backwards compatibility
        if (in_array('notes', $existing_cols)) $data['notes'] = $notes !== '' ? $notes : null;
        if (in_array('lead_id', $existing_cols)) $data['lead_id'] = $lead_id;
        if (in_array('outcome', $existing_cols)) $data['outcome'] = $outcome !== '' ? $outcome : null;
        if (in_array('status', $existing_cols)) $data['status'] = $status;
        if (in_array('assigned_to', $existing_cols)) $data['assigned_to'] = $assigned_to;
        if (in_array('location', $existing_cols)) $data['location'] = $location !== '' ? $location : null;
        if (in_array('latitude', $existing_cols)) $data['latitude'] = $latitude;
        if (in_array('longitude', $existing_cols)) $data['longitude'] = $longitude;
        if (in_array('visit_proof_image', $existing_cols) && $visit_proof_filename) $data['visit_proof_image'] = $visit_proof_filename;
        if (in_array('attachment', $existing_cols) && $attachment_filename) $data['attachment'] = $attachment_filename;
        
        // Add follow-up fields if provided
        if ($follow_up_date !== '') {
            if (in_array('follow_up_date', $existing_cols)) $data['follow_up_date'] = $follow_up_date;
            if (in_array('follow_up_type', $existing_cols)) $data['follow_up_type'] = $follow_up_type;
        }

        // Build INSERT query
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = "INSERT INTO crm_visits (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        } else {
            // Build type string and bind parameters
            $types = '';
            $values = [];
            foreach ($data as $key => $value) {
                if ($value === null) {
                    $types .= 's';
                    $values[] = null;
                } elseif (in_array($key, ['lead_id', 'assigned_to', 'created_by'])) {
                    $types .= 'i';
                    $values[] = $value;
                } elseif (in_array($key, ['latitude', 'longitude'])) {
                    $types .= 'd';
                    $values[] = $value;
                } else {
                    $types .= 's';
                    $values[] = $value;
                }
            }

            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                $visit_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Update lead contact time for logged visits
                if ($visit_type === 'Logged' && $lead_id && function_exists('crm_update_lead_contact_after_visit')) {
                    crm_update_lead_contact_after_visit($conn, $lead_id);
                }

                // If follow-up is scheduled, create the follow-up activity and update lead's follow_up_date
                if ($follow_up_date !== '' && $lead_id && $follow_up_type !== '') {
                    // Create follow-up activity (Call, Meeting, Visit, or Task)
                    if (function_exists('crm_create_followup_activity')) {
                        crm_create_followup_activity(
                            $conn,
                            (int)$lead_id,
                            $assigned_to,
                            $follow_up_date,
                            $follow_up_type,
                            "Follow-up from visit: $title"
                        );
                    }
                    
                    // Update lead's follow_up_date
                    if (function_exists('crm_update_lead_followup_date')) {
                        crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
                    }
                }

                flash_add('success', ($visit_type === 'Scheduled' ? 'Visit scheduled' : 'Visit logged') . ' successfully!', 'crm');
                closeConnection($conn);
                header('Location: view.php?id=' . $visit_id);
                exit;
            } else {
                $errors[] = 'Failed to create visit: ' . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$page_title = 'Add Visit - CRM - ' . APP_NAME;
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
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>🚗 Add Visit</h1>
          <p>Log a past visit or schedule a future visit</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="../index.php" class="btn btn-accent">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-secondary">← All Visits</a>
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

    <form method="POST" enctype="multipart/form-data" id="visitForm">
      <!-- Visit Information -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          🚗 Visit Information
        </h3>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
          <div class="form-group">
            <label for="visit_type">Visit Type <span style="color: #dc3545;">*</span></label>
            <select id="visit_type" name="visit_type" class="form-control" required>
              <option value="Logged" <?php echo (isset($_POST['visit_type']) && $_POST['visit_type'] === 'Logged') || !isset($_POST['visit_type']) ? 'selected' : ''; ?>>
                � Logged (Past Visit)
              </option>
              <option value="Scheduled" <?php echo (isset($_POST['visit_type']) && $_POST['visit_type'] === 'Scheduled') ? 'selected' : ''; ?>>
                📅 Scheduled (Future Visit)
              </option>
            </select>
            <small style="color: #6c757d; font-size: 12px;">Select whether you're logging a past visit or scheduling a future one</small>
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

          <div class="form-group">
            <label for="title">Visit Title <span style="color: #dc3545;">*</span></label>
            <input type="text" id="title" name="title" class="form-control" 
                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                   placeholder="e.g., Client Site Visit - Product Demo" required>
            <small style="color: #6c757d; font-size: 12px;">Brief description of the visit purpose</small>
          </div>

          <div class="form-group">
            <label for="visit_date">Visit Date & Time <span style="color: #dc3545;">*</span></label>
            <input type="datetime-local" id="visit_date" name="visit_date" class="form-control"
                   value="<?php echo htmlspecialchars($_POST['visit_date'] ?? ''); ?>" required>
            <small id="visit_date_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group">
            <label for="status">Status <span style="color: #dc3545;">*</span></label>
            <select id="status" name="status" class="form-control" required>
              <option value="Planned" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'Planned') ? 'selected' : ''; ?>>Planned</option>
              <option value="Completed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
              <option value="Cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
              <option value="Rescheduled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Rescheduled') ? 'selected' : ''; ?>>Rescheduled</option>
            </select>
            <small id="status_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group">
            <label for="outcome">Visit Outcome <span id="outcome_required" style="color: #dc3545;">*</span></label>
            <input type="text" id="outcome" name="outcome" class="form-control"
                   value="<?php echo htmlspecialchars($_POST['outcome'] ?? ''); ?>"
                   placeholder="e.g., Order placed for 50 units">
            <small id="outcome_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="description">Visit Description <span id="description_required" style="color: #dc3545;">*</span></label>
            <textarea id="description" name="description" class="form-control" rows="4" 
                      placeholder="Visit purpose, activities, and key discussion points..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            <small id="description_hint" style="color: #6c757d; font-size: 12px;"></small>
          </div>

          <div class="form-group">
            <label for="location">Visit Location</label>
            <input type="text" id="location" name="location" class="form-control"
                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                   placeholder="e.g., Client office address">
            <small style="color: #6c757d; font-size: 12px;">Physical location of the visit</small>
            <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
            <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <!-- Visit Proof (for Logged Visits) -->
      <div id="visit_proof_section" class="card" style="margin-bottom: 25px; display: none;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📸 Visit Proof
        </h3>
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
          <strong>⚠️ Required for Logged Visits:</strong>
          <p style="margin: 5px 0 0 0; color: #856404;">
            When marking a visit as completed (Logged), you must provide visit proof image and the system will capture your current location.
          </p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
          <div class="form-group">
            <label for="visit_proof_image">Visit Proof Image <span style="color: #dc3545;">*</span></label>
            <input type="file" id="visit_proof_image" name="visit_proof_image" class="form-control" accept="image/jpeg,image/png,image/jpg">
            <small style="color: #6c757d; font-size: 12px;">JPG or PNG only. Max 3MB.</small>
          </div>

          <div class="form-group">
            <label for="visited_latitude">Visited Latitude <span style="color: #dc3545;">*</span></label>
            <input type="text" id="visited_latitude" name="visited_latitude" class="form-control" readonly placeholder="Auto-captured">
            <small style="color: #6c757d; font-size: 12px;">Automatically captured from your device</small>
          </div>

          <div class="form-group">
            <label for="visited_longitude">Visited Longitude <span style="color: #dc3545;">*</span></label>
            <input type="text" id="visited_longitude" name="visited_longitude" class="form-control" readonly placeholder="Auto-captured">
            <small style="color: #6c757d; font-size: 12px;">Automatically captured from your device</small>
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
            <input type="date" id="follow_up_date" name="follow_up_date" class="form-control" value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? ''); ?>">
            <small style="color: #6c757d; font-size: 12px;">Optional - Schedule next interaction</small>
          </div>

          <div class="form-group">
            <label for="follow_up_type">Follow-Up Type <span class="followup-required" style="color: #dc3545; display: none;">*</span></label>
            <select id="follow_up_type" name="follow_up_type" class="form-control">
              <option value="">-- Select Type --</option>
              <?php foreach ($follow_up_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>" 
                    <?php echo (isset($_POST['follow_up_type']) && $_POST['follow_up_type'] === $type) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($type); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small style="color: #6c757d; font-size: 12px;">Required if follow-up date is set</small>
          </div>

          <div class="form-group">
            <label for="attachment">Attachment (Optional)</label>
            <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <small style="color: #6c757d; font-size: 12px;">PDF, JPG, PNG (max 3MB)</small>
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="notes">Internal Notes (Team Only) 🔒</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Private notes for internal team use only..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            <small style="color: #6c757d; font-size: 12px;">These notes are visible only to your team, not to leads</small>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div style="text-align: center; margin-top: 24px;">
        <button type="submit" class="btn" style="padding: 15px 60px; font-size: 16px;">💾 Save Visit</button>
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
    allowClear: true,
    width: '100%'
  });
  
  $('.select2-employee').select2({
    placeholder: '-- Select Employee --',
    allowClear: false,
    width: '100%'
  });

  // Capture geolocation on page load
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      $('#latitude').val(position.coords.latitude.toFixed(6));
      $('#longitude').val(position.coords.longitude.toFixed(6));
      
      // Also set visited coordinates initially
      if ($('#visited_latitude').length) {
        $('#visited_latitude').val(position.coords.latitude.toFixed(6));
        $('#visited_longitude').val(position.coords.longitude.toFixed(6));
      }
    }, function(error) {
      console.warn('Geolocation error:', error.message);
      // Don't show error to user yet, we'll capture again on submit if needed
    }, {
      timeout: 5000,
      enableHighAccuracy: true,
      maximumAge: 60000
    });
  }

  // Update form based on visit type
  function updateFormBasedOnType() {
    const visitType = $('#visit_type').val();
    const isLogged = visitType === 'Logged';
    const now = new Date();
    const nowString = now.toISOString().slice(0, 16);
    
    if (isLogged) {
      // Logged Visit: Past activity
      $('#visit_date').attr('max', nowString).removeAttr('min');
      $('#visit_date_hint').text('Must be a past or current date/time');
      
      $('#status').val('Completed');
      $('#status_hint').text('Auto-set to Completed for logged visits');
      
      $('#outcome_required').show();
      $('#outcome').prop('required', true);
      $('#outcome_hint').text('Required - What was the result of this visit?');
      
      $('#description_required').show();
      $('#description').prop('required', true);
      $('#description_hint').text('Required - Detailed summary of what happened during the visit');
      
      $('#visit_proof_section').show();
      $('#visit_proof_image').prop('required', true);
      
      $('button[type="submit"]').html('💾 Save Visit');
    } else {
      // Scheduled Visit: Future activity
      $('#visit_date').attr('min', nowString).removeAttr('max');
      $('#visit_date_hint').text('Must be a future date/time');
      
      $('#status').val('Planned');
      $('#status_hint').text('Auto-set to Planned for scheduled visits');
      
      $('#outcome_required').hide();
      $('#outcome').prop('required', false);
      $('#outcome_hint').text('Optional - Can be filled later after the visit');
      
      $('#description_required').hide();
      $('#description').prop('required', false);
      $('#description_hint').text('Optional - Brief notes about planned discussion topics');
      
      $('#visit_proof_section').hide();
      $('#visit_proof_image').prop('required', false);
      
      $('button[type="submit"]').html('📅 Schedule Visit');
    }
  }

  // Visit type change handler
  $('#visit_type').on('change', updateFormBasedOnType);

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
  
  // Form submission handler - ensure geolocation is captured for logged visits
  $('#visitForm').on('submit', function(e) {
    const visitType = $('#visit_type').val();
    
    // For logged visits, ensure we have geolocation
    if (visitType === 'Logged') {
      const lat = $('#visited_latitude').val();
      const lon = $('#visited_longitude').val();
      
      // If no coordinates, try to capture one last time
      if (!lat || !lon) {
        e.preventDefault();
        
        if (navigator.geolocation) {
          const form = this;
          
          navigator.geolocation.getCurrentPosition(
            function(position) {
              $('#latitude').val(position.coords.latitude.toFixed(6));
              $('#longitude').val(position.coords.longitude.toFixed(6));
              $('#visited_latitude').val(position.coords.latitude.toFixed(6));
              $('#visited_longitude').val(position.coords.longitude.toFixed(6));
              
              // Submit form
              form.submit();
            },
            function(error) {
              alert('⚠️ Location Error: ' + error.message + '\n\nFor logged visits, location capture is required. Please enable location services and try again.');
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
          alert('⚠️ Geolocation is not supported by your browser.\n\nFor logged visits, location information is required.');
          return false;
        }
      }
    }
    
    return true;
  });
});
</script>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
