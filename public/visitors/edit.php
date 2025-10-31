<?php
/**
 * Visitor Log Module - Edit Visitor Entry
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

$closeManagedConnection = static function () use (&$conn): void {
  if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
    closeConnection($conn);
    $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
  }
};

if (!authz_user_can_any($conn, [
  ['table' => 'visitor_logs', 'permission' => 'edit_all'],
  ['table' => 'visitor_logs', 'permission' => 'edit_own'],
])) {
  authz_require_permission($conn, 'visitor_logs', 'edit_all');
}

$visitor_permissions = authz_get_permission_set($conn, 'visitor_logs');
$can_edit_all = !empty($visitor_permissions['can_edit_all']);
$can_edit_own = !empty($visitor_permissions['can_edit_own']);

if (!($conn instanceof mysqli)) {
  echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
  require_once __DIR__ . '/../../includes/footer_sidebar.php';
  exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'visitors');
if (!$prereq_check['allowed']) {
  $closeManagedConnection();
  display_prerequisite_error('visitors', $prereq_check['missing_modules']);
  exit;
}

if (!visitor_logs_table_exists($conn)) {
  $closeManagedConnection();
  require_once __DIR__ . '/onboarding.php';
  exit;
}

$visitor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($visitor_id <= 0) {
  $closeManagedConnection();
  flash_add('error', 'Invalid visitor identifier supplied.', 'visitors');
  header('Location: index.php');
  exit;
}

$current_employee = visitor_logs_current_employee($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;
if (!$can_edit_all) {
  if ($can_edit_own && $current_employee) {
    $restricted_employee_id = (int) $current_employee['id'];
  } else {
    $closeManagedConnection();
    authz_require_permission($conn, 'visitor_logs', 'edit_all');
  }
}

$detail_sql = 'SELECT * FROM visitor_logs WHERE id = ? AND deleted_at IS NULL';
$detail_params = [$visitor_id];
$detail_types = 'i';
if ($restricted_employee_id !== null) {
  $detail_sql .= ' AND added_by = ?';
  $detail_params[] = $restricted_employee_id;
  $detail_types .= 'i';
}
$detail_sql .= ' LIMIT 1';

$detail_stmt = mysqli_prepare($conn, $detail_sql);
if (!$detail_stmt) {
  $closeManagedConnection();
  flash_add('error', 'Unable to load visitor record.', 'visitors');
  header('Location: index.php');
  exit;
}

visitor_logs_stmt_bind($detail_stmt, $detail_types, $detail_params);
mysqli_stmt_execute($detail_stmt);
$detail_res = mysqli_stmt_get_result($detail_stmt);
$visitor = $detail_res ? mysqli_fetch_assoc($detail_res) : null;
mysqli_stmt_close($detail_stmt);

if (!$visitor) {
  $closeManagedConnection();
  flash_add('error', 'Visitor record not found or access denied.', 'visitors');
  header('Location: index.php');
  exit;
}

$employee_options = visitor_logs_fetch_employees($conn);
$employee_ids = array_map('intval', array_column($employee_options, 'id'));

$visitor_name = $visitor['visitor_name'] ?? '';
$phone = $visitor['phone'] ?? '';
$purpose = $visitor['purpose'] ?? '';
$employee_id = (int) ($visitor['employee_id'] ?? 0);
$check_in_time = date('Y-m-d\TH:i', strtotime($visitor['check_in_time']));
$check_in_time_db = $visitor['check_in_time'];
$existing_photo = $visitor['photo'] ?? null;

$errors = [];
$new_upload_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $visitor_name = trim($_POST['visitor_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $purpose = trim($_POST['purpose'] ?? '');
  $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
  $check_in_input = trim($_POST['check_in_time'] ?? '');

  if ($visitor_name === '') {
    $errors[] = 'Visitor name is required.';
  }

  if ($purpose === '') {
    $errors[] = 'Purpose of visit is required.';
  }

  if ($employee_id <= 0 || !in_array($employee_id, $employee_ids, true)) {
    $errors[] = 'Please select a valid employee to meet.';
  }

  if ($check_in_input === '') {
    $errors[] = 'Check-in time is required.';
  } else {
    $timestamp = strtotime($check_in_input);
    if ($timestamp === false) {
      $errors[] = 'Invalid check-in time provided.';
    } else {
      $check_in_time_db = date('Y-m-d H:i:s', $timestamp);
      $check_in_time = date('Y-m-d\TH:i', $timestamp);
    }
  }

  $photo_path = $existing_photo;
  if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Error uploading visitor photo/document.';
    } else {
      $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
      $max_size = 2 * 1024 * 1024;
      $detected_type = @mime_content_type($file['tmp_name']);
      if ($detected_type === false || !in_array($detected_type, $allowed_types, true)) {
        $errors[] = 'Photo/document must be a JPG, PNG, or PDF file.';
      }
      if ($file['size'] > $max_size) {
        $errors[] = 'Uploaded file must be 2 MB or smaller.';
      }
      if (empty($errors)) {
        if (!visitor_logs_ensure_upload_directory()) {
          $errors[] = 'Unable to create upload directory for visitor documents.';
        } else {
          $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          try {
            $token = bin2hex(random_bytes(4));
          } catch (Exception $e) {
            $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
          }
          $filename = 'visitor_' . time() . '_' . $token . '.' . $extension;
          $destination_path = visitor_logs_upload_directory_path() . DIRECTORY_SEPARATOR . $filename;
          if (!move_uploaded_file($file['tmp_name'], $destination_path)) {
            $errors[] = 'Failed to store the uploaded file.';
          } else {
            $photo_path = 'uploads/visitor_logs/' . $filename;
            $new_upload_path = $photo_path;
          }
        }
      }
    }
  }

  if (empty($errors)) {
    $update_sql = 'UPDATE visitor_logs SET visitor_name = ?, phone = ?, purpose = ?, check_in_time = ?, employee_id = ?, photo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL';
    $update_params = [
      $visitor_name,
      $phone !== '' ? $phone : null,
      $purpose,
      $check_in_time_db,
      $employee_id,
      $photo_path,
      $visitor_id,
    ];
    $update_types = 'ssssisi';
    if ($restricted_employee_id !== null) {
      $update_sql .= ' AND added_by = ?';
      $update_params[] = $restricted_employee_id;
      $update_types .= 'i';
    }

    $update_stmt = mysqli_prepare($conn, $update_sql);
    if ($update_stmt) {
      visitor_logs_stmt_bind($update_stmt, $update_types, $update_params);
      if (mysqli_stmt_execute($update_stmt) && mysqli_stmt_affected_rows($update_stmt) >= 0) {
        mysqli_stmt_close($update_stmt);
        if ($new_upload_path && $existing_photo && $existing_photo !== $new_upload_path) {
          visitor_logs_delete_file($existing_photo);
        }
        $closeManagedConnection();
        flash_add('success', 'Visitor entry updated successfully.', 'visitors');
        header('Location: view.php?id=' . $visitor_id);
        exit;
      }
      $errors[] = 'Failed to update visitor record. Please try again.';
      mysqli_stmt_close($update_stmt);
    } else {
      $errors[] = 'Unable to prepare database statement.';
    }
  }

  if (!empty($errors) && $new_upload_path) {
    visitor_logs_delete_file($new_upload_path);
    $new_upload_path = null;
  }
}

$page_title = 'Edit Visitor - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
  <div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h1>✏️ Edit Visitor Entry</h1>
      <p>Update visitor details and check-in time.</p>
    </div>
    <div>
      <a href="view.php?id=<?php echo (int) $visitor_id; ?>" class="btn btn-secondary">← Back to details</a>
    </div>
    </div>
  </div>

  <?php echo flash_render(); ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
    <ul style="margin:0;padding-left:18px;">
      <?php foreach ($errors as $error): ?>
      <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
      <?php endforeach; ?>
    </ul>
    </div>
  <?php endif; ?>

  <div class="card" style="max-width:820px;margin:0 auto;">
    <form method="POST" enctype="multipart/form-data" style="display:grid;gap:18px;">
    <div class="form-group" style="margin:0;">
      <label for="visitor_name">Visitor Name<span style="color:#dc3545;">*</span></label>
      <input type="text" id="visitor_name" name="visitor_name" class="form-control" value="<?php echo htmlspecialchars($visitor_name, ENT_QUOTES); ?>" required>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
      <div class="form-group" style="margin:0;">
      <label for="phone">Phone</label>
      <input type="text" id="phone" name="phone" class="form-control" placeholder="Optional" value="<?php echo htmlspecialchars($phone, ENT_QUOTES); ?>">
      </div>
      <div class="form-group" style="margin:0;">
      <label for="check_in_time">Check-in Time<span style="color:#dc3545;">*</span></label>
      <input type="datetime-local" id="check_in_time" name="check_in_time" class="form-control" value="<?php echo htmlspecialchars($check_in_time, ENT_QUOTES); ?>" required>
      </div>
    </div>

    <div class="form-group" style="margin:0;">
      <label for="purpose">Purpose of Visit<span style="color:#dc3545;">*</span></label>
      <input type="text" id="purpose" name="purpose" class="form-control" value="<?php echo htmlspecialchars($purpose, ENT_QUOTES); ?>" required>
    </div>

    <div class="form-group" style="margin:0;">
      <label for="employee_id">Meeting With<span style="color:#dc3545;">*</span></label>
      <select id="employee_id" name="employee_id" class="form-control" required>
      <option value="">Select employee</option>
      <?php foreach ($employee_options as $option): ?>
        <?php $selected = ($employee_id === (int) $option['id']) ? 'selected' : ''; ?>
        <option value="<?php echo (int) $option['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($option['employee_code'] ?? '') . ' - ' . trim(($option['first_name'] ?? '') . ' ' . ($option['last_name'] ?? ''))); ?></option>
      <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin:0;">
      <label for="photo">Visitor Photo / ID Proof <span style="color:#6c757d;font-size:12px;">(Optional, JPG/PNG/PDF up to 2 MB)</span></label>
      <?php if (!empty($existing_photo)): ?>
      <div style="margin-bottom:8px;">
        <a href="<?php echo APP_URL . '/' . ltrim($existing_photo, '/'); ?>" target="_blank" class="btn" style="padding:6px 12px;background:#17a2b8;color:#fff;">View current attachment</a>
      </div>
      <?php endif; ?>
      <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,application/pdf">
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;">
      <a href="view.php?id=<?php echo (int) $visitor_id; ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn" style="background:#003581;color:#fff;">Save Changes</button>
    </div>
    </form>
  </div>
  </div>
</div>

<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
