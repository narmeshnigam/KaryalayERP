<?php
require_once __DIR__ . '/common.php';

authz_require_permission($conn, 'crm_visits', 'edit_all');

$visit_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($visit_id <= 0) {
    flash_add('error', 'Invalid visit ID', 'crm');
    header('Location: index.php');
    exit;
}

// Fetch existing visit
$select_cols = crm_visits_select_columns($conn);
$sql = "SELECT $select_cols FROM crm_visits c WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    die('Failed to prepare query');
}

mysqli_stmt_bind_param($stmt, 'i', $visit_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$visit = $res ? mysqli_fetch_assoc($res) : null;
if ($res) mysqli_free_result($res);
mysqli_stmt_close($stmt);

if (!$visit) {
    flash_add('error', 'Visit not found', 'crm');
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: index.php');
    exit;
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads_for_visits($conn);
$visit_statuses = ['Planned', 'Completed', 'Cancelled', 'Rescheduled'];
$follow_up_types = crm_visit_follow_up_types();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_type = trim($_POST['visit_type'] ?? 'Logged');
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $visit_date = trim($_POST['visit_date'] ?? '');
    $outcome = trim($_POST['outcome'] ?? '');
    $status = trim($_POST['status'] ?? 'Planned');
    $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
    $location = trim($_POST['location'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $follow_up_type = trim($_POST['follow_up_type'] ?? '');
    
    // Capture geo-coordinates
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    // Store old follow_up_date to check if it changed
    $old_follow_up_date = crm_visit_get($visit, 'follow_up_date');

    // Validation based on visit_type
    if ($lead_id === null) {
        $errors[] = 'Related lead is required.';
    }
    if ($title === '') {
        $errors[] = 'Visit title is required.';
    }
    
    // Conditional validation based on visit_type
    if ($visit_type === 'Logged') {
        // Logged visits: description and outcome are required, date must be past/present
        if ($description === '') {
            $errors[] = 'Visit description is required for logged visits.';
        }
        if ($outcome === '') {
            $errors[] = 'Visit outcome is required for logged visits.';
        }
        if ($visit_date !== '') {
            $visit_dt = DateTime::createFromFormat('Y-m-d\TH:i', $visit_date);
            if ($visit_dt && $visit_dt > new DateTime()) {
                $errors[] = 'Logged visit date cannot be in the future.';
            }
        }
        $status = 'Completed'; // Auto-set for logged visits
    } else {
        // Scheduled visits: description and outcome are optional, date should be future
        if ($visit_date !== '') {
            $visit_dt = DateTime::createFromFormat('Y-m-d\TH:i', $visit_date);
            if ($visit_dt && $visit_dt <= new DateTime()) {
                $errors[] = 'Scheduled visit date must be in the future.';
            }
        }
        if ($description === '') {
            $description = 'Scheduled visit - ' . $title; // Auto-generate
        }
    }
    
    // Follow-up validation
    if ($follow_up_date !== '' && $follow_up_type === '') {
        $errors[] = 'Follow-up type is required when follow-up date is selected.';
    }
    
    // Validate assigned employee exists
    if ($assigned_to && !crm_employee_exists($conn, $assigned_to)) {
        $errors[] = 'Assigned employee does not exist.';
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

    // Handle visit proof image upload (for logged visits)
    $visit_proof_filename = crm_visit_get($visit, 'visit_proof_image');
    if ($visit_type === 'Logged' && crm_visits_has_column($conn, 'visit_proof_image')) {
        if (isset($_FILES['visit_proof_image']) && $_FILES['visit_proof_image']['error'] === UPLOAD_ERR_OK) {
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
                // Delete old file
                if ($visit_proof_filename && file_exists($upload_dir . $visit_proof_filename)) {
                    @unlink($upload_dir . $visit_proof_filename);
                }
                
                $visit_proof_filename = 'visit_proof_' . time() . '_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $visit_proof_filename;
                
                if (!move_uploaded_file($_FILES['visit_proof_image']['tmp_name'], $upload_path)) {
                    $errors[] = 'Failed to upload visit proof image';
                    $visit_proof_filename = crm_visit_get($visit, 'visit_proof_image');
                }
            }
        }
    }

    // Handle file upload
    $attachment_filename = crm_visit_get($visit, 'attachment');
    if (crm_visits_has_column($conn, 'attachment') && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
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
            
            $attachment_filename = 'visit_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $attachment_filename;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload file';
                $attachment_filename = crm_visit_get($visit, 'attachment');
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
            'visit_date' => $visit_date
        ];

        if (in_array('description', $existing_cols, true)) {
            $data['description'] = $description !== '' ? $description : null;
        }

        if (in_array('purpose', $existing_cols, true)) {
            $data['purpose'] = $description !== '' ? $description : null;
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

        if (in_array('visit_proof_image', $existing_cols, true) && $visit_proof_filename) {
            $data['visit_proof_image'] = $visit_proof_filename;
        }

        if (in_array('attachment', $existing_cols, true)) {
            $data['attachment'] = $attachment_filename !== '' ? $attachment_filename : null;
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

        $sql = "UPDATE crm_visits SET " . implode(', ', $updates) . " WHERE id = ? AND deleted_at IS NULL";
        $types .= 'i';
        $values[] = $visit_id;

        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                
                // Update lead's last contact time (only for logged visits)
                if ($lead_id && $visit_type === 'Logged' && function_exists('crm_update_lead_contact_after_visit')) {
                    crm_update_lead_contact_after_visit($conn, $lead_id);
                }
                
                // If follow-up date changed and is set, create/update follow-up activity
                if ($follow_up_date !== '' && $follow_up_date !== $old_follow_up_date && $lead_id && $follow_up_type !== '') {
                    // Create follow-up activity
                    if (function_exists('crm_create_followup_activity')) {
                        crm_create_followup_activity($conn, $lead_id, $assigned_to, $follow_up_date, $follow_up_type, "Follow-up from visit: $title");
                    }
                    
                    // Update lead's follow_up_date
                    if (function_exists('crm_update_lead_followup_date')) {
                        crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
                    }
                }
                
                flash_add('success', ($visit_type === 'Scheduled' ? 'Scheduled visit updated' : 'Visit updated') . ' successfully!', 'crm');
                if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                    closeConnection($conn);
                }
                header('Location: view.php?id=' . $visit_id);
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

$page_title = 'Edit Visit - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// Convert visit_date to datetime-local format
$visit_datetime_local = '';
if (crm_visit_get($visit, 'visit_date')) {
    $dt = new DateTime(crm_visit_get($visit, 'visit_date'));
    $visit_datetime_local = $dt->format('Y-m-d\TH:i');
}

// Determine visit type based on current data
$current_visit_type = 'Logged';
if (crm_visit_get($visit, 'status') === 'Planned' || crm_visit_get($visit, 'status') === 'Rescheduled') {
    $visit_dt = new DateTime(crm_visit_get($visit, 'visit_date'));
    if ($visit_dt > new DateTime()) {
        $current_visit_type = 'Scheduled';
    }
}
?>

<!-- Select2 CSS for searchable dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
.attachment-current {
    margin-bottom: 12px;
    padding: 10px;
    background: #f0f9ff;
    border-radius: 4px;
}
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
          <h1>✏️ Edit Visit</h1>
          <p>Update visit details</p>
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

    <form method="post" enctype="multipart/form-data">
        <!-- Visit Information -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                🚗 Visit Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="form-group">
                    <label for="visit_type">Visit Type <span style="color: #dc3545;">*</span></label>
                    <select id="visit_type" name="visit_type" class="form-control" required>
                        <?php $current_type = isset($_POST['visit_type']) ? $_POST['visit_type'] : $current_visit_type; ?>
                        <option value="Logged" <?php echo $current_type === 'Logged' ? 'selected' : ''; ?>>
                            📝 Logged (Past Visit)
                        </option>
                        <option value="Scheduled" <?php echo $current_type === 'Scheduled' ? 'selected' : ''; ?>>
                            📅 Scheduled (Future Visit)
                        </option>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Select whether this is a logged past visit or scheduled future visit</small>
                </div>
                
                <div class="form-group">
                    <label for="lead_id">Related Lead <span style="color: #dc3545;">*</span></label>
                    <select id="lead_id" name="lead_id" class="form-control select2-lead" required>
                        <option value="">-- Select Lead --</option>
                        <?php foreach ($leads as $l): ?>
                            <?php
                                $l_id = (int)$l['id'];
                                $selected = (isset($_POST['lead_id']) ? (int)$_POST['lead_id'] === $l_id : (int)crm_visit_get($visit, 'lead_id') === $l_id);
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
                    <label for="title">Visit Title <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? crm_visit_get($visit, 'title')); ?>" 
                           placeholder="e.g., Client Site Visit - Product Demo" required>
                </div>

                <div class="form-group">
                    <label for="visit_date">Visit Date & Time <span style="color: #dc3545;">*</span></label>
                    <input type="datetime-local" id="visit_date" name="visit_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['visit_date'] ?? $visit_datetime_local); ?>" required>
                    <small id="visit_date_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="status">Status <span style="color: #dc3545;">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <?php foreach ($visit_statuses as $s): ?>
                            <?php
                                $selected = (isset($_POST['status']) ? $_POST['status'] === $s : crm_visit_get($visit, 'status') === $s);
                            ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="status_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="outcome">Visit Outcome <span id="outcome_required" style="color: #dc3545;">*</span></label>
                    <input type="text" id="outcome" name="outcome" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['outcome'] ?? crm_visit_get($visit, 'outcome')); ?>"
                           placeholder="e.g., Order placed for 50 units">
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
                                $selected = (isset($_POST['assigned_to']) ? (int)$_POST['assigned_to'] === $emp_id : (int)crm_visit_get($visit, 'assigned_to') === $emp_id);
                            ?>
                            <option value="<?php echo $emp_id; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo $emp_label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6c757d; font-size: 12px;">Search and select employee</small>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="description">Visit Description <span id="description_required" style="color: #dc3545;">*</span></label>
                    <textarea id="description" name="description" class="form-control" rows="4" 
                              placeholder="Visit purpose, activities, and key discussion points..." required><?php echo htmlspecialchars($_POST['description'] ?? crm_visit_get($visit, 'description')); ?></textarea>
                    <small id="description_hint" style="color: #6c757d; font-size: 12px;"></small>
                </div>

                <div class="form-group">
                    <label for="location">Visit Location</label>
                    <input type="text" id="location" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['location'] ?? crm_visit_get($visit, 'location')); ?>"
                           placeholder="e.g., Client office address">
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? crm_visit_get($visit, 'latitude')); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? crm_visit_get($visit, 'longitude')); ?>">
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
                    <label for="visit_proof_image">Visit Proof Image <span id="visit_proof_required" style="color: #dc3545;">*</span></label>
                    <?php 
                    $current_proof = crm_visit_get($visit, 'visit_proof_image');
                    if ($current_proof): 
                    ?>
                        <div style="margin-bottom: 10px; padding: 10px; background-color: #e8f4f8; border-radius: 4px;">
                            <small style="color: #003581;">
                                📎 Current: <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars($current_proof); ?>" target="_blank" style="color: #003581;">View Image</a>
                            </small>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="visit_proof_image" name="visit_proof_image" class="form-control" 
                           accept="image/jpeg,image/png,image/jpg">
                    <small style="color: #6c757d; font-size: 12px;">JPG or PNG only. Max 3MB.</small>
                </div>

                <div class="form-group">
                    <label for="visited_latitude">Visited Latitude <span id="visited_lat_required" style="color: #dc3545;">*</span></label>
                    <input type="text" id="visited_latitude" name="visited_latitude" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['visited_latitude'] ?? crm_visit_get($visit, 'latitude')); ?>" 
                           readonly placeholder="Auto-captured">
                    <small style="color: #6c757d; font-size: 12px;">Automatically captured</small>
                </div>

                <div class="form-group">
                    <label for="visited_longitude">Visited Longitude <span id="visited_long_required" style="color: #dc3545;">*</span></label>
                    <input type="text" id="visited_longitude" name="visited_longitude" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['visited_longitude'] ?? crm_visit_get($visit, 'longitude')); ?>" 
                           readonly placeholder="Auto-captured">
                    <small style="color: #6c757d; font-size: 12px;">Automatically captured</small>
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
                           value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? crm_visit_get($visit, 'follow_up_date')); ?>">
                    <small style="color: #6c757d; font-size: 12px;">Optional - schedule a follow-up activity</small>
                </div>

                <div class="form-group">
                    <label for="follow_up_type">Follow-Up Type <span id="followup_required" style="color: #dc3545;display:none;">*</span></label>
                    <select id="follow_up_type" name="follow_up_type" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach ($follow_up_types as $ft): ?>
                            <?php
                                $selected = (isset($_POST['follow_up_type']) ? $_POST['follow_up_type'] === $ft : crm_visit_get($visit, 'follow_up_type') === $ft);
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
                    <?php if (crm_visit_get($visit, 'attachment')): ?>
                        <div class="attachment-current">
                            <strong>Current:</strong> 
                            <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars(crm_visit_get($visit, 'attachment')); ?>" 
                               target="_blank" style="color:#003581;text-decoration:none;">
                                <?php echo htmlspecialchars(crm_visit_get($visit, 'attachment')); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small style="color:#6b7280;">Upload a new file to replace the existing attachment. Accepted: PDF, JPG, PNG. Max 3MB.</small>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="notes">Internal Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                              placeholder="Internal notes for your reference (not visible to leads)..."><?php echo htmlspecialchars($_POST['notes'] ?? crm_visit_get($visit, 'notes')); ?></textarea>
                    <small style="color: #6c757d; font-size: 12px;">Optional - Add any internal notes or reminders for your team</small>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div style="text-align: center; padding: 20px 0;">
            <button type="submit" class="btn" id="submit_btn" style="padding: 15px 60px; font-size: 16px;">
                ✅ Update Visit
            </button>
            <a href="view.php?id=<?php echo $visit_id; ?>" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
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

    // Update form behavior based on visit_type
    function updateFormBasedOnType() {
        const visitType = $('#visit_type').val();
        const outcomeField = $('#outcome');
        const outcomeRequired = $('#outcome_required');
        const outcomeHint = $('#outcome_hint');
        const descriptionField = $('#description');
        const descriptionRequired = $('#description_required');
        const descriptionHint = $('#description_hint');
        const visitDateHint = $('#visit_date_hint');
        const visitDateField = $('#visit_date');
        const statusField = $('#status');
        const statusHint = $('#status_hint');
        const submitBtn = $('#submit_btn');
        const visitProofSection = $('#visit_proof_section');
        const visitProofImage = $('#visit_proof_image');
        const visitProofRequired = $('#visit_proof_required');
        const visitedLatRequired = $('#visited_lat_required');
        const visitedLongRequired = $('#visited_long_required');

        if (visitType === 'Scheduled') {
            // Scheduled visit: outcome and description optional, date should be future
            outcomeField.removeAttr('required');
            outcomeRequired.hide();
            outcomeHint.text('Optional for scheduled visits');
            
            descriptionField.removeAttr('required');
            descriptionRequired.hide();
            descriptionHint.text('Optional - will auto-generate if left empty');
            
            visitDateHint.text('Must be a future date/time');
            statusHint.text('Status can be Planned or Rescheduled');
            
            // Hide visit proof section
            visitProofSection.hide();
            visitProofImage.removeAttr('required');
            visitProofRequired.hide();
            visitedLatRequired.hide();
            visitedLongRequired.hide();
            
            // Set min to current datetime for scheduled visits
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            visitDateField.attr('min', minDateTime);
            visitDateField.removeAttr('max');
            
            submitBtn.html('✅ Update Visit');
        } else {
            // Logged visit: outcome and description are required, date should be past/present
            outcomeField.attr('required', 'required');
            outcomeRequired.show();
            outcomeHint.text('Required for logged visits');
            
            descriptionField.attr('required', 'required');
            descriptionRequired.show();
            descriptionHint.text('Required - describe visit activities');
            
            visitDateHint.text('Must be a past or current date/time');
            statusHint.text('Status will be set to Completed');
            
            // Show visit proof section and make fields required
            visitProofSection.show();
            visitProofImage.attr('required', 'required');
            visitProofRequired.show();
            visitedLatRequired.show();
            visitedLongRequired.show();
            
            // Set max to current datetime for logged visits
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const maxDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            visitDateField.attr('max', maxDateTime);
            visitDateField.removeAttr('min');
            
            submitBtn.html('✅ Update Visit');
        }
    }

    // Run on page load
    updateFormBasedOnType();

    // Run when visit_type changes
    $('#visit_type').on('change', updateFormBasedOnType);
    
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

    // Capture geolocation on page load and continuously
    function captureGeolocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    $('#latitude').val(position.coords.latitude);
                    $('#longitude').val(position.coords.longitude);
                    $('#visited_latitude').val(position.coords.latitude);
                    $('#visited_longitude').val(position.coords.longitude);
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
    }
    
    // Capture on page load
    captureGeolocation();
    
    // Geolocation capture on form submit (final capture)
    $('form').on('submit', function(e) {
        const visitType = $('#visit_type').val();
        
        // For logged visits, ensure we have current geolocation
        if (visitType === 'Logged') {
            // Check if geolocation is already captured
            if ($('#visited_latitude').val() !== '' && $('#visited_longitude').val() !== '') {
                return true; // Already have coordinates, proceed
            }
            
            // Try to get geolocation one last time
            if (navigator.geolocation) {
                e.preventDefault();
                const form = this;
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        // Success: add coordinates
                        $('#latitude').val(position.coords.latitude);
                        $('#longitude').val(position.coords.longitude);
                        $('#visited_latitude').val(position.coords.latitude);
                        $('#visited_longitude').val(position.coords.longitude);
                        
                        // Submit the form
                        form.submit();
                    },
                    function(error) {
                        // Error: show warning but allow submission
                        console.warn('Geolocation error:', error.message);
                        if (confirm('Could not capture location. Continue anyway?')) {
                            form.submit();
                        }
                    },
                    {
                        timeout: 5000,
                        maximumAge: 60000
                    }
                );
            }
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