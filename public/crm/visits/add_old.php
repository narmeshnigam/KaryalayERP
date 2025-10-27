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

$current_employee_id = crm_current_employee_id($conn, $user_id);
if (!$current_employee_id && !crm_role_can_manage($user_role)) {
    closeConnection($conn);
    die('Unable to identify your employee record.');
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads_for_visits($conn);
$follow_up_types = crm_visit_follow_up_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $visit_date = trim($_POST['visit_date'] ?? '');
    $visit_type = trim($_POST['visit_type'] ?? 'Logged'); // Logged or Scheduled
    $outcome = trim($_POST['outcome'] ?? '');
    $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : $current_employee_id;
    $location = trim($_POST['location'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $follow_up_type = trim($_POST['follow_up_type'] ?? '');
    
    // Capture geo-coordinates
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    
    // Capture visited coordinates (mandatory for logged visits)
    $visited_latitude = isset($_POST['visited_latitude']) ? floatval($_POST['visited_latitude']) : null;
    $visited_longitude = isset($_POST['visited_longitude']) ? floatval($_POST['visited_longitude']) : null;

    $errors = [];

    // Validation
    if ($lead_id === null) {
        $errors[] = 'Related lead is required.';
    }
    if ($title === '') {
        $errors[] = 'Visit title is required.';
    }
    if ($visit_date === '') {
        $errors[] = 'Visit date and time are required.';
    } else {
        $visit_dt = DateTime::createFromFormat('Y-m-d\TH:i', $visit_date);
        if (!$visit_dt) {
            $errors[] = 'Invalid visit date format.';
        } else {
            // For logged visits, date can be past/present
            // For scheduled visits, date must be in future
            $now = new DateTime();
            if ($visit_type === 'Logged' && $visit_dt > $now) {
                $errors[] = 'Logged visit date cannot be in the future. Use "Scheduled" type for future visits.';
            } elseif ($visit_type === 'Scheduled' && $visit_dt <= $now) {
                $errors[] = 'Scheduled visit date must be in the future. Use "Logged" type for past visits.';
            }
        }
    }
    
    // Purpose and outcome validation based on visit type
    if ($visit_type === 'Logged') {
        // Logged visits require purpose and proof of visit
        if ($purpose === '') {
            $errors[] = 'Visit purpose is required for logged visits.';
        }
        if ($visited_latitude === null || $visited_longitude === null) {
            $errors[] = 'Visited location coordinates are required for logged visits.';
        }
    } else {
        // Scheduled visits - purpose is optional
        if ($purpose === '') {
            $purpose = 'Scheduled visit - ' . $title; // Auto-generate basic purpose
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

    // Handle visit proof image upload
    $visit_proof_filename = null;
    if ($visit_type === 'Logged' && !empty($_FILES['visit_proof_image']['name'])) {
        $file = $_FILES['visit_proof_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 3 * 1024 * 1024) {
                $errors[] = 'Visit proof image size must not exceed 3 MB.';
            } elseif (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/jpg'], true)) {
                $errors[] = 'Visit proof image must be JPEG or PNG.';
            } else {
                if (crm_ensure_upload_directory()) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'visit_proof_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $upload_path = crm_upload_directory() . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $visit_proof_filename = $filename;
                    } else {
                        $errors[] = 'Failed to save visit proof image.';
                    }
                } else {
                    $errors[] = 'Upload directory is not writable.';
                }
            }
        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'File upload error: ' . $file['error'];
        }
    }

    // Handle generic attachment upload
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
                    $filename = 'visit_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
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
        $res_cols = mysqli_query($conn, "SHOW COLUMNS FROM crm_visits");
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
            'purpose' => $purpose !== '' ? $purpose : null,
            'notes' => $notes !== '' ? $notes : null,
            'visit_date' => $visit_date,
            'outcome' => $outcome !== '' ? $outcome : null,
            'status' => $visit_type === 'Logged' ? 'Completed' : 'Scheduled',
            'assigned_to' => $assigned_to,
            'created_by' => $current_employee_id,
            'location' => $location !== '' ? $location : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'visited_latitude' => $visit_type === 'Logged' ? $visited_latitude : null,
            'visited_longitude' => $visit_type === 'Logged' ? $visited_longitude : null,
            'visit_proof_image' => $visit_proof_filename,
            'attachment' => $attachment_path,
        ];

        if (!empty($follow_up_date)) {
            $data['follow_up_date'] = $follow_up_date;
            $data['follow_up_type'] = $follow_up_type;
        }

        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];

        foreach ($data as $col => $value) {
            if (in_array($col, $existing_cols)) {
                $columns[] = $col;
                $placeholders[] = '?';
                
                if ($value === null) {
                    $types .= 's';
                    $values[] = null;
                } elseif (is_int($value)) {
                    $types .= 'i';
                    $values[] = $value;
                } elseif (is_float($value)) {
                    $types .= 'd';
                    $values[] = $value;
                } else {
                    $types .= 's';
                    $values[] = $value;
                }
            }
        }

        $sql = "INSERT INTO crm_visits (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                
                // Update lead's last contact time
                if ($lead_id !== null) {
                    crm_update_lead_contact_after_visit($conn, $lead_id);
                }
                
                // Create follow-up activity if scheduled
                if (!empty($follow_up_date) && !empty($follow_up_type)) {
                    crm_create_followup_activity($conn, $follow_up_type, $follow_up_date, $lead_id, $assigned_to, $current_employee_id);
                    crm_update_lead_followup_date($conn, $lead_id, $follow_up_date, $follow_up_type);
                }
                
                mysqli_stmt_close($stmt);
                flash_add('success', 'Visit logged successfully!', 'crm');
                closeConnection($conn);
                header('Location: view.php?id=' . $new_id);
                exit;
            } else {
                $errors[] = 'Failed to save visit: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Failed to prepare statement: ' . mysqli_error($conn);
        }
    }
}

$page_title = 'New Visit - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<style>
.visit-type-switch {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}
.visit-type-btn {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}
.visit-type-btn.active {
    background: #003581;
    color: white;
    border-color: #003581;
}
.visit-type-btn:hover {
    border-color: #003581;
}
.conditional-section {
    display: none;
}
.conditional-section.active {
    display: block;
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>✈️ New Visit</h1>
          <p>Log or schedule a customer/lead visit</p>
        </div>
        <div>
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-accent">← Back to List</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <?php if (!empty($errors)): ?>
      <div class="card" style="background:#fff5f5;border-left:4px solid #dc3545;">
        <strong style="color:#dc3545;">Please fix the following errors:</strong>
        <ul style="margin:8px 0 0 20px;color:#721c24;">
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" enctype="multipart/form-data" id="visitForm">
        <!-- Visit Type Selection -->
        <div style="margin-bottom:24px;">
          <label style="display:block;margin-bottom:12px;font-weight:600;color:#003581;">Visit Type <span style="color:#dc3545;">*</span></label>
          <div class="visit-type-switch">
            <button type="button" class="visit-type-btn active" data-type="Logged" onclick="setVisitType('Logged')">
              📝 Log Past Visit
            </button>
            <button type="button" class="visit-type-btn" data-type="Scheduled" onclick="setVisitType('Scheduled')">
              📅 Schedule Future Visit
            </button>
          </div>
          <input type="hidden" id="visitTypeInput" name="visit_type" value="Logged">
        </div>

        <!-- Visit Information -->
        <div class="card" style="margin-bottom:20px;border-bottom:2px solid #003581;padding-bottom:16px;">
          <h3 style="margin:0 0 16px;color:#003581;">✈️ Visit Information</h3>
          
          <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:20px;margin-bottom:20px;">
            <div>
              <label class="form-label">Related Lead <span style="color:#dc3545;">*</span></label>
              <select name="lead_id" id="leadSelect" class="form-control" required style="height:40px;border:1px solid #cbd5e1;">
                <option value="">-- Select Lead --</option>
                <?php foreach ($leads as $lead): ?>
                  <option value="<?php echo $lead['id']; ?>" <?php echo isset($_POST['lead_id']) && $_POST['lead_id'] == $lead['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lead['name'] . (isset($lead['company_name']) && $lead['company_name'] ? ' (' . $lead['company_name'] . ')' : '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Visit Title <span style="color:#dc3545;">*</span></label>
              <input type="text" name="title" class="form-control" required
                     value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                     placeholder="e.g., Client Site Visit">
            </div>

            <div>
              <label class="form-label">Visit Date & Time <span style="color:#dc3545;">*</span></label>
              <input type="datetime-local" name="visit_date" id="visitDate" class="form-control" required
                     value="<?php echo isset($_POST['visit_date']) ? htmlspecialchars($_POST['visit_date']) : ''; ?>">
            </div>

            <div>
              <label class="form-label">Status</label>
              <select name="status" class="form-control" style="height:40px;border:1px solid #cbd5e1;" disabled>
                <option id="statusOption">Scheduled</option>
              </select>
            </div>

            <div>
              <label class="form-label">Assigned To <span style="color:#dc3545;">*</span></label>
              <select name="assigned_to" id="assignedTo" class="form-control" required style="height:40px;border:1px solid #cbd5e1;">
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo $emp['id']; ?>" <?php echo (isset($_POST['assigned_to']) ? $_POST['assigned_to'] == $emp['id'] : $current_employee_id == $emp['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(trim($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name'])); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Location</label>
              <input type="text" name="location" class="form-control"
                     value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                     placeholder="e.g., Office Address">
            </div>
          </div>

          <!-- Geo-coordinates capture -->
          <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:20px;">
            <div>
              <label class="form-label">Planned Latitude</label>
              <input type="number" step="0.000001" name="latitude" id="latitude" class="form-control"
                     value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : ''; ?>"
                     placeholder="Auto-captured" readonly style="background:#f8f9fa;">
            </div>
            <div>
              <label class="form-label">Planned Longitude</label>
              <input type="number" step="0.000001" name="longitude" id="longitude" class="form-control"
                     value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : ''; ?>"
                     placeholder="Auto-captured" readonly style="background:#f8f9fa;">
            </div>
          </div>
          <button type="button" onclick="captureLocation()" style="margin-top:12px;background:#e3f2fd;color:#003581;padding:8px 16px;border:1px solid #003581;border-radius:6px;cursor:pointer;font-weight:600;">
            📍 Capture Current Location
          </button>
        </div>

        <!-- Visit Details -->
        <div class="card" style="margin-bottom:20px;border-bottom:2px solid #003581;padding-bottom:16px;">
          <h3 style="margin:0 0 16px;color:#003581;">📝 Visit Details</h3>
          
          <div>
            <label class="form-label">Visit Purpose <span style="color:#dc3545;" id="purposeRequired">*</span></label>
            <textarea name="purpose" id="purpose" class="form-control" rows="4"
                      placeholder="Describe the purpose and agenda of the visit..."><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
          </div>

          <div style="margin-top:16px;">
            <label class="form-label">Visit Notes</label>
            <textarea name="notes" class="form-control" rows="4"
                      placeholder="Additional notes or discussion points..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
          </div>

          <div style="margin-top:16px;">
            <label class="form-label">Outcome / Results</label>
            <textarea name="outcome" class="form-control" rows="4"
                      placeholder="Visit outcomes and next steps..."><?php echo isset($_POST['outcome']) ? htmlspecialchars($_POST['outcome']) : ''; ?></textarea>
          </div>
        </div>

        <!-- Visit Proof (for Logged visits only) -->
        <div class="card conditional-section active" id="proofSection" style="margin-bottom:20px;border-bottom:2px solid #003581;padding-bottom:16px;">
          <h3 style="margin:0 0 16px;color:#003581;">🏞️ Visit Proof</h3>
          
          <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:20px;margin-bottom:20px;">
            <div>
              <label class="form-label">Visited Location - Latitude <span style="color:#dc3545;" id="visitedLatRequired">*</span></label>
              <input type="number" step="0.000001" name="visited_latitude" id="visitedLatitude" class="form-control" required
                     value="<?php echo isset($_POST['visited_latitude']) ? htmlspecialchars($_POST['visited_latitude']) : ''; ?>"
                     placeholder="Auto-captured" readonly style="background:#f8f9fa;">
            </div>
            <div>
              <label class="form-label">Visited Location - Longitude <span style="color:#dc3545;" id="visitedLonRequired">*</span></label>
              <input type="number" step="0.000001" name="visited_longitude" id="visitedLongitude" class="form-control" required
                     value="<?php echo isset($_POST['visited_longitude']) ? htmlspecialchars($_POST['visited_longitude']) : ''; ?>"
                     placeholder="Auto-captured" readonly style="background:#f8f9fa;">
            </div>
          </div>
          <button type="button" onclick="captureVisitedLocation()" style="background:#d4edda;color:#155724;padding:10px 20px;border:1px solid #155724;border-radius:6px;cursor:pointer;font-weight:600;">
            📍 Capture Visited Location
          </button>

          <div style="margin-top:20px;">
            <label class="form-label">Visit Proof Image (Photo) <span style="color:#dc3545;" id="proofRequired">*</span></label>
            <input type="file" name="visit_proof_image" id="visitProofImage" class="form-control" accept="image/jpeg,image/png,image/jpg" required>
            <small style="color:#6c757d;font-size:12px;">Max 3MB. Allowed: JPEG, PNG</small>
          </div>
        </div>

        <!-- Follow-Up & Additional -->
        <div class="card" style="margin-bottom:20px;border-bottom:2px solid #003581;padding-bottom:16px;">
          <h3 style="margin:0 0 16px;color:#003581;">📅 Follow-Up & Additional</h3>
          
          <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:20px;">
            <div>
              <label class="form-label">Follow-Up Date</label>
              <input type="date" name="follow_up_date" class="form-control"
                     value="<?php echo isset($_POST['follow_up_date']) ? htmlspecialchars($_POST['follow_up_date']) : ''; ?>"
                     min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div>
              <label class="form-label">Follow-Up Type</label>
              <select name="follow_up_type" class="form-control" style="height:40px;border:1px solid #cbd5e1;">
                <option value="">-- Select Type --</option>
                <?php foreach ($follow_up_types as $type): ?>
                  <option value="<?php echo $type; ?>" <?php echo (isset($_POST['follow_up_type']) && $_POST['follow_up_type'] === $type) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($type); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="margin-top:16px;">
            <label class="form-label">Attachment (Optional)</label>
            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <small style="color:#6c757d;font-size:12px;">Max 3MB. Allowed: PDF, JPG, PNG</small>
          </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;">
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-secondary" style="padding:15px 60px;font-size:16px;">Cancel</a>
          <button type="submit" class="btn" style="padding:15px 60px;font-size:16px;">✈️ Save Visit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let currentVisitType = 'Logged';

function setVisitType(type) {
  currentVisitType = type;
  document.getElementById('visitTypeInput').value = type;
  
  // Update button states
  document.querySelectorAll('.visit-type-btn').forEach(btn => {
    if (btn.getAttribute('data-type') === type) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
  
  // Update date constraints
  const dateInput = document.getElementById('visitDate');
  if (type === 'Logged') {
    dateInput.removeAttribute('min');
    dateInput.max = new Date().toISOString().slice(0, 16);
    document.getElementById('statusOption').textContent = 'Completed';
  } else {
    dateInput.min = new Date().toISOString().slice(0, 16);
    dateInput.removeAttribute('max');
    document.getElementById('statusOption').textContent = 'Scheduled';
  }
  
  // Update proof section visibility
  const proofSection = document.getElementById('proofSection');
  const purposeRequired = document.getElementById('purposeRequired');
  const purpose = document.getElementById('purpose');
  
  if (type === 'Logged') {
    proofSection.classList.add('active');
    document.getElementById('visitedLatRequired').textContent = '*';
    document.getElementById('visitedLonRequired').textContent = '*';
    document.getElementById('proofRequired').textContent = '*';
    document.getElementById('visitProofImage').required = true;
    document.getElementById('visitedLatitude').required = true;
    document.getElementById('visitedLongitude').required = true;
    purposeRequired.textContent = '*';
    purpose.required = true;
  } else {
    proofSection.classList.remove('active');
    document.getElementById('visitedLatRequired').textContent = '';
    document.getElementById('visitedLonRequired').textContent = '';
    document.getElementById('proofRequired').textContent = '';
    document.getElementById('visitProofImage').required = false;
    document.getElementById('visitedLatitude').required = false;
    document.getElementById('visitedLongitude').required = false;
    purposeRequired.textContent = '';
    purpose.required = false;
  }
}

function captureLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      function(position) {
        document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
        document.getElementById('longitude').value = position.coords.longitude.toFixed(6);
        alert('Location captured successfully!');
      },
      function(error) {
        alert('Unable to get location: ' + error.message);
      },
      { timeout: 10000 }
    );
  } else {
    alert('Geolocation is not supported by your browser.');
  }
}

function captureVisitedLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      function(position) {
        document.getElementById('visitedLatitude').value = position.coords.latitude.toFixed(6);
        document.getElementById('visitedLongitude').value = position.coords.longitude.toFixed(6);
        alert('Visited location captured successfully!');
      },
      function(error) {
        alert('Unable to get location: ' + error.message);
      },
      { timeout: 10000 }
    );
  } else {
    alert('Geolocation is not supported by your browser.');
  }
}

// Initialize on page load
window.addEventListener('load', function() {
  setVisitType('Logged');
});
</script>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
