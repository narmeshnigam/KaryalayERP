<?php
/**
 * Salary Viewer - Edit salary record (admin only).
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'salary_records', 'edit_all');

$conn_check = createConnection(true);
if ($conn_check) {
    $prereq_check = get_prerequisite_check_result($conn_check, 'salary');
    if (!$prereq_check['allowed']) {
        closeConnection($conn_check);
        display_prerequisite_error('salary', $prereq_check['missing_modules']);
    }
    closeConnection($conn_check);
}

$record_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($record_id <= 0) {
    flash_add('error', 'Missing salary record identifier.', 'salary');
    header('Location: admin.php');
    exit;
}

$page_title = 'Edit Salary Record - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = $conn ?? createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

if (!salary_table_exists($conn)) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$employees = salary_fetch_employees($conn);

$select = mysqli_prepare($conn, 'SELECT sr.*, emp.employee_code AS emp_code, emp.first_name AS emp_first, emp.last_name AS emp_last FROM salary_records sr INNER JOIN employees emp ON sr.employee_id = emp.id WHERE sr.id = ? LIMIT 1');
if (!$select) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to load salary record.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

mysqli_stmt_bind_param($select, 'i', $record_id);
mysqli_stmt_execute($select);
$result = mysqli_stmt_get_result($select);
$record = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($select);

if (!$record) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    flash_add('error', 'Salary record not found.', 'salary');
    header('Location: admin.php');
    exit;
}

$is_locked = (int) $record['is_locked'] === 1;
$errors = [];
$form = [
    'employee_id' => (int) $record['employee_id'],
    'month' => $record['month'],
    'base_salary' => (string) $record['base_salary'],
    'allowances' => (string) $record['allowances'],
    'deductions' => (string) $record['deductions'],
    'working_days_total' => (string) ($record['working_days_total'] ?? ''),
    'days_worked' => (string) ($record['days_worked'] ?? ''),
    'leave_days' => (string) ($record['leave_days'] ?? ''),
    'leave_breakdown' => (string) ($record['leave_breakdown'] ?? ''),
    'deduction_breakdown' => (string) ($record['deduction_breakdown'] ?? ''),
    'is_locked' => $is_locked,
    'slip_path' => $record['slip_path'] ?? null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_locked) {
        flash_add('error', 'Unlock the salary record before editing.', 'salary');
        if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
            closeConnection($conn);
        }
        header('Location: admin.php');
        exit;
    }

    $form['employee_id'] = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : $form['employee_id'];
    $form['month'] = isset($_POST['month']) ? trim($_POST['month']) : $form['month'];
    $form['base_salary'] = trim($_POST['base_salary'] ?? $form['base_salary']);
    $form['allowances'] = trim($_POST['allowances'] ?? $form['allowances']);
    $form['deductions'] = trim($_POST['deductions'] ?? $form['deductions']);
    $form['is_locked'] = isset($_POST['is_locked']);
    $form['working_days_total'] = isset($_POST['working_days_total']) ? (int) $_POST['working_days_total'] : $form['working_days_total'];
    $form['days_worked'] = isset($_POST['days_worked']) ? (float) $_POST['days_worked'] : $form['days_worked'];
    $form['leave_days'] = isset($_POST['leave_days']) ? (float) $_POST['leave_days'] : $form['leave_days'];
    $form['leave_breakdown'] = trim($_POST['leave_breakdown'] ?? (string)$form['leave_breakdown']);
    $form['deduction_breakdown'] = trim($_POST['deduction_breakdown'] ?? (string)$form['deduction_breakdown']);

    if ($form['employee_id'] <= 0) {
        $errors[] = 'Please select an employee.';
    }

    if (!preg_match('/^\d{4}-\d{2}$/', $form['month'])) {
        $errors[] = 'Invalid month format. Use YYYY-MM.';
    }

    $base = is_numeric($form['base_salary']) ? (float) $form['base_salary'] : null;
    $allowances = ($form['allowances'] === '' ? 0.0 : (is_numeric($form['allowances']) ? (float) $form['allowances'] : null));
    $deductions = ($form['deductions'] === '' ? 0.0 : (is_numeric($form['deductions']) ? (float) $form['deductions'] : null));

    if ($base === null || $base < 0) {
        $errors[] = 'Base salary must be a non-negative number.';
    }
    if ($allowances === null || $allowances < 0) {
        $errors[] = 'Allowances must be a non-negative number.';
    }
    if ($deductions === null || $deductions < 0) {
        $errors[] = 'Deductions must be a non-negative number.';
    }

    $employee_stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE id = ? LIMIT 1');
    $employee_exists = false;
    if ($employee_stmt) {
        mysqli_stmt_bind_param($employee_stmt, 'i', $form['employee_id']);
        mysqli_stmt_execute($employee_stmt);
        $res = mysqli_stmt_get_result($employee_stmt);
        $employee_exists = (bool) ($res && mysqli_fetch_assoc($res));
        mysqli_stmt_close($employee_stmt);
    }
    if (!$employee_exists) {
        $errors[] = 'Selected employee was not found.';
    }

    $new_slip_path = null;
    $old_slip = $form['slip_path'];
    if (isset($_FILES['slip']) && $_FILES['slip']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['slip'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading salary slip.';
        } else {
            $size_limit = 5 * 1024 * 1024;
            if ($file['size'] > $size_limit) {
                $errors[] = 'Salary slip must be less than 5 MB.';
            }
            $detected = @mime_content_type($file['tmp_name']);
            if ($detected !== 'application/pdf') {
                $errors[] = 'Salary slip must be a PDF file.';
            }
            if (empty($errors)) {
                if (!salary_ensure_upload_directory()) {
                    $errors[] = 'Unable to create salary slips directory. Please check permissions.';
                } else {
                    try {
                        $token = bin2hex(random_bytes(4));
                    } catch (Exception $e) {
                        $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
                    }
                    $filename = 'salary_' . $form['employee_id'] . '_' . str_replace('-', '', $form['month']) . '_' . $token . '.pdf';
                    $destination = salary_upload_directory() . DIRECTORY_SEPARATOR . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $errors[] = 'Failed to store uploaded salary slip.';
                    } else {
                        $new_slip_path = 'uploads/salary_slips/' . $filename;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $net = ($base ?? 0) + ($allowances ?? 0) - ($deductions ?? 0);
        $locked_flag = $form['is_locked'] ? 1 : 0;
        $slip_to_save = $new_slip_path ?? $old_slip;
        $update = mysqli_prepare($conn, 'UPDATE salary_records SET employee_id = ?, month = ?, base_salary = ?, allowances = ?, deductions = ?, net_pay = ?, slip_path = ?, is_locked = ?, working_days_total = ?, days_worked = ?, leave_days = ?, leave_breakdown = ?, deduction_breakdown = ?, unpaid_leave_days = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        if ($update) {
            $wb = (int)($form['working_days_total'] !== '' ? $form['working_days_total'] : 0);
            $dw = (float)($form['days_worked'] !== '' ? $form['days_worked'] : 0);
            $ld = (float)($form['leave_days'] !== '' ? $form['leave_days'] : 0);
            $lb = $form['leave_breakdown'] !== '' ? $form['leave_breakdown'] : null;
            $db = $form['deduction_breakdown'] !== '' ? $form['deduction_breakdown'] : null;
            $uld = (float) (json_decode($db ?: '{}', true)['unpaid_leave_days'] ?? 0);
            mysqli_stmt_bind_param($update, 'isddddsiiddssdi', $form['employee_id'], $form['month'], $base, $allowances, $deductions, $net, $slip_to_save, $locked_flag, $wb, $dw, $ld, $lb, $db, $uld, $record_id);
            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);
                if ($new_slip_path && $old_slip && $new_slip_path !== $old_slip) {
                    $old_file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($old_slip);
                    if (is_file($old_file)) {
                        unlink($old_file);
                    }
                }
                flash_add('success', 'Salary record updated.', 'salary');
                if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                    closeConnection($conn);
                }
                header('Location: view.php?id=' . $record_id);
                exit;
            }
            $error_code = mysqli_errno($conn);
            mysqli_stmt_close($update);
        } else {
            $error_code = mysqli_errno($conn);
        }

        if ($new_slip_path) {
            $new_file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($new_slip_path);
            if (is_file($new_file)) {
                unlink($new_file);
            }
        }

        if (!empty($error_code) && (int) $error_code === 1062) {
            $errors[] = 'A salary record already exists for this employee and month.';
        } else {
            $errors[] = 'Failed to update salary record. Please try again.';
        }
    } else {
        if ($new_slip_path) {
            $new_file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($new_slip_path);
            if (is_file($new_file)) {
                unlink($new_file);
            }
        }
    }
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;">
                <div>
                    <h1>✏️ Edit Salary Record</h1>
                    <p>Adjust salary components for <?php echo htmlspecialchars(($record['emp_code'] ?? '') . ' - ' . trim(($record['emp_first'] ?? '') . ' ' . ($record['emp_last'] ?? ''))); ?>.</p>
                </div>
                <div>
                    <a href="view.php?id=<?php echo $record_id; ?>" class="btn btn-secondary">← Back to details</a>
                </div>
            </div>
        </div>

        <?php if ($is_locked): ?>
            <div class="alert alert-warning">
                This salary record is locked. Unlock it from the Salary Manager before making changes.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0;padding-left:18px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:780px;margin:0 auto;">
            <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;"<?php echo $is_locked ? ' aria-disabled="true"' : ''; ?>>
                <div class="form-group">
                    <label for="employee_id">Employee</label>
                    <select id="employee_id" name="employee_id" class="form-control" <?php echo $is_locked ? 'disabled' : 'required'; ?>>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <?php $selected = ((int) $form['employee_id'] === (int) $emp['id']) ? 'selected' : ''; ?>
                            <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="month">Salary month</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?php echo htmlspecialchars($form['month'], ENT_QUOTES); ?>" <?php echo $is_locked ? 'disabled' : 'required'; ?>>
                </div>
                <div class="form-group">
                    <label for="base_salary">Base salary</label>
                    <input type="number" step="0.01" min="0" id="base_salary" name="base_salary" class="form-control" value="<?php echo htmlspecialchars($form['base_salary'], ENT_QUOTES); ?>" <?php echo $is_locked ? 'disabled' : 'required'; ?>>
                </div>
                <div class="form-group">
                    <label for="allowances">Allowances</label>
                    <input type="number" step="0.01" min="0" id="allowances" name="allowances" class="form-control" value="<?php echo htmlspecialchars($form['allowances'], ENT_QUOTES); ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="deductions">Deductions</label>
                    <input type="number" step="0.01" min="0" id="deductions" name="deductions" class="form-control" value="<?php echo htmlspecialchars($form['deductions'], ENT_QUOTES); ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="working_days_total">Working days</label>
                    <input type="number" step="1" min="0" id="working_days_total" name="working_days_total" class="form-control" value="<?php echo htmlspecialchars((string)($form['working_days_total'] ?? ''), ENT_QUOTES); ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="days_worked">Days worked</label>
                    <input type="number" step="0.5" min="0" id="days_worked" name="days_worked" class="form-control" value="<?php echo htmlspecialchars((string)($form['days_worked'] ?? ''), ENT_QUOTES); ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="leave_days">Leaves</label>
                    <input type="number" step="0.5" min="0" id="leave_days" name="leave_days" class="form-control" value="<?php echo htmlspecialchars((string)($form['leave_days'] ?? ''), ENT_QUOTES); ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label for="leave_breakdown">Leave breakdown JSON</label>
                    <textarea id="leave_breakdown" name="leave_breakdown" class="form-control" rows="3" <?php echo $is_locked ? 'disabled' : ''; ?>><?php echo htmlspecialchars((string)($form['leave_breakdown'] ?? ''), ENT_QUOTES); ?></textarea>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label for="deduction_breakdown">Deduction breakdown JSON</label>
                    <textarea id="deduction_breakdown" name="deduction_breakdown" class="form-control" rows="3" <?php echo $is_locked ? 'disabled' : ''; ?>><?php echo htmlspecialchars((string)($form['deduction_breakdown'] ?? ''), ENT_QUOTES); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="slip">Salary slip (PDF, max 5MB)</label>
                    <input type="file" id="slip" name="slip" accept="application/pdf" class="form-control" <?php echo $is_locked ? 'disabled' : ''; ?>>
                    <?php if ($form['slip_path']): ?>
                        <p style="font-size:12px;color:#6c757d;margin-top:6px;">Current slip: <a href="<?php echo htmlspecialchars(salary_public_path($form['slip_path']), ENT_QUOTES); ?>" target="_blank" rel="noopener">Download existing PDF</a></p>
                    <?php endif; ?>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" id="is_locked" name="is_locked" <?php echo $form['is_locked'] ? 'checked' : ''; ?> <?php echo $is_locked ? 'disabled' : ''; ?>>
                    <label for="is_locked" style="margin:0;">Mark as locked</label>
                </div>
                <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:12px;">
                    <a href="admin.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn" style="background:#003581;color:#fff;" <?php echo $is_locked ? 'disabled' : ''; ?>>Update salary record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
