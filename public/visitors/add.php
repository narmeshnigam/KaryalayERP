<?php
/**
 * Visitor Log Module - Add Visitor Entry
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

authz_require_permission($conn, 'visitor_logs', 'create');

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

$current_employee = visitor_logs_current_employee($conn, (int) $CURRENT_USER_ID);
if (!$current_employee) {
    $closeManagedConnection();
    flash_add('error', 'No employee record linked to your account. Please contact HR.', 'visitors');
    header('Location: index.php');
    exit;
}

$employee_options = visitor_logs_fetch_employees($conn);
$employee_ids = array_map('intval', array_column($employee_options, 'id'));

$errors = [];
$visitor_name = '';
$phone = '';
$purpose = '';
$employee_id = $employee_options[0]['id'] ?? 0;
$check_in_time = date('Y-m-d\TH:i');
$check_in_time_db = date('Y-m-d H:i:s');
$stored_file_path = null;

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

    $photo_path = null;
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
                        $stored_file_path = $photo_path;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $insert_sql = 'INSERT INTO visitor_logs (visitor_name, phone, purpose, check_in_time, employee_id, photo, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        if ($insert_stmt) {
            $params = [
                $visitor_name,
                $phone !== '' ? $phone : null,
                $purpose,
                $check_in_time_db,
                $employee_id,
                $photo_path,
                (int) $current_employee['id'],
            ];
            visitor_logs_stmt_bind($insert_stmt, 'ssssisi', $params);
            if (mysqli_stmt_execute($insert_stmt)) {
                mysqli_stmt_close($insert_stmt);
                $closeManagedConnection();
                flash_add('success', 'Visitor entry logged successfully.', 'visitors');
                header('Location: index.php');
                exit;
            }
            $errors[] = 'Failed to save visitor log. Please try again.';
            mysqli_stmt_close($insert_stmt);
        } else {
            $errors[] = 'Unable to prepare database statement.';
        }
    }

    if (!empty($errors) && $stored_file_path) {
        visitor_logs_delete_file($stored_file_path);
        $stored_file_path = null;
    }
}

$page_title = 'Add Visitor - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>🛂 Add Visitor Entry</h1>
          <p>Capture visitor details, purpose, and check-in time.</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-secondary">← Back to Visitor Log</a>
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
          <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,application/pdf">
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;">
          <a href="index.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn" style="background:#003581;color:#fff;">Save Visitor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
