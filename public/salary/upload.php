<?php
/**
 * Salary Viewer - Upload form for admins/accountants.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
if (!salary_role_can_manage($user_role)) {
    flash_add('error', 'You do not have permission to upload salaries.', 'salary');
    header('Location: admin.php');
    exit;
}

$page_title = 'Upload Salary Record - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

if (!salary_table_exists($conn)) {
    closeConnection($conn);
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$current_employee_id = salary_current_employee_id($conn, (int) $_SESSION['user_id']);
$employees = salary_fetch_employees($conn);

$errors = [];
$form = [
    'employee_id' => '',
    'month' => date('Y-m'),
    'base_salary' => '',
    'allowances' => '',
    'deductions' => '',
    'working_days_total' => '',
    'days_worked' => '',
    'leave_days' => '',
    'leave_breakdown' => '',
    'deduction_breakdown' => '',
    'is_locked' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['employee_id'] = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
    $form['month'] = isset($_POST['month']) ? trim($_POST['month']) : date('Y-m');
    $form['base_salary'] = trim($_POST['base_salary'] ?? '');
    $form['allowances'] = trim($_POST['allowances'] ?? '');
    $form['deductions'] = trim($_POST['deductions'] ?? '');
    $form['is_locked'] = isset($_POST['is_locked']);
    $form['working_days_total'] = isset($_POST['working_days_total']) ? (int) $_POST['working_days_total'] : null;
    $form['days_worked'] = isset($_POST['days_worked']) ? (float) $_POST['days_worked'] : null;
    $form['leave_days'] = isset($_POST['leave_days']) ? (float) $_POST['leave_days'] : null;
    $form['leave_breakdown'] = trim($_POST['leave_breakdown'] ?? '');
    $form['deduction_breakdown'] = trim($_POST['deduction_breakdown'] ?? '');

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

    $employee_stmt = mysqli_prepare($conn, 'SELECT id, department, designation, salary_type, basic_salary, hra, conveyance_allowance, medical_allowance, special_allowance FROM employees WHERE id = ? LIMIT 1');
    $employee_exists = false;
    $snapshot = null;
    if ($employee_stmt) {
        mysqli_stmt_bind_param($employee_stmt, 'i', $form['employee_id']);
        mysqli_stmt_execute($employee_stmt);
        $res = mysqli_stmt_get_result($employee_stmt);
        $snapshot = $res ? mysqli_fetch_assoc($res) : null;
        $employee_exists = (bool) $snapshot;
        mysqli_stmt_close($employee_stmt);
    }
    if (!$employee_exists) {
        $errors[] = 'Selected employee was not found.';
    }

    // Server-side prefill if client didn't supply
    if ($employee_exists) {
        if ($form['base_salary'] === '' && isset($snapshot['basic_salary'])) {
            $form['base_salary'] = (string) $snapshot['basic_salary'];
        }
        if ($form['allowances'] === '') {
            $allow = (float)($snapshot['hra'] ?? 0) + (float)($snapshot['conveyance_allowance'] ?? 0) + (float)($snapshot['medical_allowance'] ?? 0) + (float)($snapshot['special_allowance'] ?? 0);
            $form['allowances'] = (string) $allow;
        }
        // Attendance summary
        $att = salary_compute_monthly_attendance($conn, $form['employee_id'], $form['month']);
        if ($form['working_days_total'] === null) $form['working_days_total'] = (int)($att['working_days_total'] ?? 0);
        if ($form['days_worked'] === null) $form['days_worked'] = (float)($att['days_worked'] ?? 0);
        if ($form['leave_days'] === null) $form['leave_days'] = (float)($att['leave_days'] ?? 0);
        if ($form['leave_breakdown'] === '') $form['leave_breakdown'] = json_encode($att['leave_breakdown'] ?? [], JSON_UNESCAPED_UNICODE);
        if ($form['deduction_breakdown'] === '') {
            $ded = ['unpaid_leave_days' => (float)($att['unpaid_leave_days'] ?? 0)];
            $form['deduction_breakdown'] = json_encode($ded, JSON_UNESCAPED_UNICODE);
        }
    }

    $slip_path = null;
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
                    $extension = '.pdf';
                    try {
                        $token = bin2hex(random_bytes(4));
                    } catch (Exception $e) {
                        $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
                    }
                    $filename = 'salary_' . $form['employee_id'] . '_' . str_replace('-', '', $form['month']) . '_' . $token . $extension;
                    $destination = salary_upload_directory() . DIRECTORY_SEPARATOR . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $errors[] = 'Failed to store uploaded salary slip.';
                    } else {
                        $slip_path = 'uploads/salary_slips/' . $filename;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $net = ($base ?? 0) + ($allowances ?? 0) - ($deductions ?? 0);
        $uploaded_by = $current_employee_id ?: null;
        $error_code = null;

        $insert = mysqli_prepare($conn, 'INSERT INTO salary_records (employee_id, month, base_salary, allowances, deductions, net_pay, slip_path, is_locked, uploaded_by, snapshot_department, snapshot_designation, snapshot_salary_type, working_days_total, days_worked, leave_days, leave_breakdown, deduction_breakdown, unpaid_leave_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($insert) {
            $locked_flag = $form['is_locked'] ? 1 : 0;
            $snap_dept = $snapshot['department'] ?? null;
            $snap_desig = $snapshot['designation'] ?? null;
            $snap_type = $snapshot['salary_type'] ?? null;
            $wb = (int)($form['working_days_total'] ?? 0);
            $dw = (float)($form['days_worked'] ?? 0);
            $ld = (float)($form['leave_days'] ?? 0);
            $lb = $form['leave_breakdown'] ?: null;
            $db = $form['deduction_breakdown'] ?: null;
            $db_arr = [];
            if (is_string($db) && $db !== '') {
                $tmp = json_decode($db, true);
                if (is_array($tmp)) { $db_arr = $tmp; }
            }
            $uld = (float) ($db_arr['unpaid_leave_days'] ?? 0);
            // Types: i(employee_id) s(month) d(base) d(allowances) d(deductions) d(net) s(slip_path) i(is_locked) i(uploaded_by)
            //        s(snapshot_department) s(snapshot_designation) s(snapshot_salary_type) i(working_days_total) d(days_worked) d(leave_days)
            //        s(leave_breakdown) s(deduction_breakdown) d(unpaid_leave_days)
            mysqli_stmt_bind_param($insert, 'isddddsiisssiddssd', $form['employee_id'], $form['month'], $base, $allowances, $deductions, $net, $slip_path, $locked_flag, $uploaded_by, $snap_dept, $snap_desig, $snap_type, $wb, $dw, $ld, $lb, $db, $uld);
            if (mysqli_stmt_execute($insert)) {
                $new_id = mysqli_insert_id($conn);
                salary_notify_salary_uploaded($conn, $new_id);
                mysqli_stmt_close($insert);
                flash_add('success', 'Salary record created successfully.', 'salary');
                closeConnection($conn);
                header('Location: admin.php');
                exit;
            }
            $error_code = mysqli_errno($conn);
            mysqli_stmt_close($insert);
        }

        if ($slip_path !== null) {
            $stored = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($slip_path);
            if (is_file($stored)) {
                unlink($stored);
            }
        }

        if (!empty($error_code) && (int) $error_code === 1062) {
            $errors[] = 'A salary record already exists for this employee and month.';
        } else {
            $errors[] = 'Failed to create salary record. Please try again.';
        }
    } else {
        if ($slip_path !== null) {
            $stored = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($slip_path);
            if (is_file($stored)) {
                unlink($stored);
            }
        }
    }
}

closeConnection($conn);
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;">
                <div>
                    <h1>📤 Upload Salary Record</h1>
                    <p>Record monthly payroll details and optionally attach the payslip PDF.</p>
                </div>
                <div>
                    <a href="admin.php" class="btn btn-secondary">← Back to Salary Manager</a>
                </div>
            </div>
        </div>

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
            <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
                <div class="form-group">
                    <label for="employee_id">Employee</label>
                    <select id="employee_id" name="employee_id" class="form-control" required>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <?php $selected = ((int) $form['employee_id'] === (int) $emp['id']) ? 'selected' : ''; ?>
                            <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="month">Salary month</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?php echo htmlspecialchars($form['month'], ENT_QUOTES); ?>" required>
                </div>
                <div class="form-group">
                    <label for="base_salary">Base salary</label>
                    <input type="number" step="0.01" min="0" id="base_salary" name="base_salary" class="form-control" value="<?php echo htmlspecialchars($form['base_salary'], ENT_QUOTES); ?>" required>
                </div>
                <div class="form-group">
                    <label for="allowances">Allowances</label>
                    <input type="number" step="0.01" min="0" id="allowances" name="allowances" class="form-control" value="<?php echo htmlspecialchars($form['allowances'], ENT_QUOTES); ?>">
                </div>
                <div class="form-group">
                    <label for="deductions">Deductions</label>
                    <input type="number" step="0.01" min="0" id="deductions" name="deductions" class="form-control" value="<?php echo htmlspecialchars($form['deductions'], ENT_QUOTES); ?>">
                </div>
                <div class="form-group">
                    <label for="working_days_total">Working days (auto)</label>
                    <input type="number" step="1" min="0" id="working_days_total" name="working_days_total" class="form-control" value="<?php echo htmlspecialchars((string)($form['working_days_total'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="form-group">
                    <label for="days_worked">Days worked (auto)</label>
                    <input type="number" step="0.5" min="0" id="days_worked" name="days_worked" class="form-control" value="<?php echo htmlspecialchars((string)($form['days_worked'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="form-group">
                    <label for="leave_days">Leaves (auto)</label>
                    <input type="number" step="0.5" min="0" id="leave_days" name="leave_days" class="form-control" value="<?php echo htmlspecialchars((string)($form['leave_days'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label for="leave_breakdown">Leave breakdown JSON (auto)</label>
                    <textarea id="leave_breakdown" name="leave_breakdown" class="form-control" rows="3" placeholder='{"Sick Leave": 1, "Unpaid Leave": 2}'><?php echo htmlspecialchars((string)($form['leave_breakdown'] ?? ''), ENT_QUOTES); ?></textarea>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label for="deduction_breakdown">Deduction breakdown JSON (auto)</label>
                    <textarea id="deduction_breakdown" name="deduction_breakdown" class="form-control" rows="3" placeholder='{"unpaid_leave_days": 2}'><?php echo htmlspecialchars((string)($form['deduction_breakdown'] ?? ''), ENT_QUOTES); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="slip">Salary slip (PDF, max 5MB)</label>
                    <input type="file" id="slip" name="slip" accept="application/pdf" class="form-control">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" id="is_locked" name="is_locked" <?php echo $form['is_locked'] ? 'checked' : ''; ?>>
                    <label for="is_locked" style="margin:0;">Mark as locked</label>
                </div>
                <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:12px;">
                    <a href="admin.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn" style="background:#003581;color:#fff;">Save salary record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>

<script>
// Auto-prefill when employee or month changes
function fetchPrefill() {
  const empId = document.getElementById('employee_id').value;
  const month = document.getElementById('month').value;
  if (!empId || !month) return;
  const url = '../api/salary/prefill.php?employee_id=' + encodeURIComponent(empId) + '&month=' + encodeURIComponent(month);
  fetch(url, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) return;
      const emp = data.employee || {};
      const att = data.attendance || {};
      // Prefill salary numbers if empty
      const base = document.getElementById('base_salary');
      const allow = document.getElementById('allowances');
      if (base && (!base.value || base.value === '')) base.value = (emp.basic_salary ?? '');
      if (allow && (!allow.value || allow.value === '')) allow.value = (emp.suggested_allowances ?? '');
      // Attendance
      const wd = document.getElementById('working_days_total');
      const dw = document.getElementById('days_worked');
      const ld = document.getElementById('leave_days');
      const lb = document.getElementById('leave_breakdown');
      const db = document.getElementById('deduction_breakdown');
      if (wd && !wd.value) wd.value = att.working_days_total ?? '';
      if (dw && !dw.value) dw.value = att.days_worked ?? '';
      if (ld && !ld.value) ld.value = att.leave_days ?? '';
      if (lb && !lb.value) lb.value = JSON.stringify(att.leave_breakdown || {}, null, 2);
      if (db && !db.value) db.value = JSON.stringify({ unpaid_leave_days: att.unpaid_leave_days || 0 }, null, 2);
    })
    .catch(() => {});
}
document.getElementById('employee_id').addEventListener('change', fetchPrefill);
document.getElementById('month').addEventListener('change', fetchPrefill);
// Trigger once on load
document.addEventListener('DOMContentLoaded', fetchPrefill);
</script>
