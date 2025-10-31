<?php
/**
 * Salary Viewer - Admin/Accountant manager console.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

$closeManagedConnection = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
    }
};

authz_require_permission($conn, 'salary_records', 'view_all');
$salary_permissions = authz_get_permission_set($conn, 'salary_records');
$can_create_salary = !empty($salary_permissions['can_create']);
$can_edit_salary = !empty($salary_permissions['can_edit_all']);
$can_delete_salary = !empty($salary_permissions['can_delete_all']);

if (!($conn instanceof mysqli)) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'salary');
if (!$prereq_check['allowed']) {
    $closeManagedConnection();
    display_prerequisite_error('salary', $prereq_check['missing_modules']);
    exit;
}

if (!salary_table_exists($conn)) {
    $closeManagedConnection();
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$page_title = 'Salary Manager - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$query_string = $_SERVER['QUERY_STRING'] ?? '';
$redirect_base = 'admin.php' . ($query_string !== '' ? '?' . $query_string : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $record_id = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
    if ($record_id <= 0) {
        flash_add('error', 'Invalid record identifier.', 'salary');
        header('Location: ' . $redirect_base);
        exit;
    }

    if (in_array($action, ['lock', 'unlock', 'delete'], true) && !$can_edit_salary && !$can_delete_salary) {
        flash_add('error', 'You do not have permission to modify salary records.', 'salary');
        header('Location: ' . $redirect_base);
        exit;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, employee_id, is_locked FROM salary_records WHERE id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $record_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $record = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    } else {
        $record = null;
    }

    if (!$record) {
        flash_add('error', 'Salary record not found.', 'salary');
        header('Location: ' . $redirect_base);
        exit;
    }

    if ($action === 'lock' || $action === 'unlock') {
        if (!$can_edit_salary) {
            flash_add('error', 'You do not have permission to change lock state.', 'salary');
            header('Location: ' . $redirect_base);
            exit;
        }
        $desired = $action === 'lock' ? 1 : 0;
        if ((int) $record['is_locked'] === $desired) {
            flash_add('info', 'Record is already in the requested state.', 'salary');
            header('Location: ' . $redirect_base);
            exit;
        }
        $update = mysqli_prepare($conn, 'UPDATE salary_records SET is_locked = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        if ($update) {
            mysqli_stmt_bind_param($update, 'ii', $desired, $record_id);
            if (mysqli_stmt_execute($update)) {
                $message = $desired ? 'Salary record locked.' : 'Salary record unlocked.';
                flash_add('success', $message, 'salary');
            } else {
                flash_add('error', 'Unable to update record state.', 'salary');
            }
            mysqli_stmt_close($update);
        }
        header('Location: ' . $redirect_base);
        exit;
    }

    if ($action === 'delete') {
        if (!$can_delete_salary) {
            flash_add('error', 'You do not have permission to delete salary records.', 'salary');
            header('Location: ' . $redirect_base);
            exit;
        }
        if ((int) $record['is_locked'] === 1) {
            flash_add('error', 'Locked salary records cannot be deleted. Unlock first.', 'salary');
            header('Location: ' . $redirect_base);
            exit;
        }
        $delete = mysqli_prepare($conn, 'DELETE FROM salary_records WHERE id = ?');
        if ($delete) {
            mysqli_stmt_bind_param($delete, 'i', $record_id);
            if (mysqli_stmt_execute($delete)) {
                flash_add('success', 'Salary record deleted.', 'salary');
            } else {
                flash_add('error', 'Unable to delete salary record.', 'salary');
            }
            mysqli_stmt_close($delete);
        }
        header('Location: ' . $redirect_base);
        exit;
    }

    flash_add('warning', 'Unsupported action.', 'salary');
    header('Location: ' . $redirect_base);
    exit;
}

$employees = salary_fetch_employees($conn);

$defaults = salary_month_range_default();
$from_month = isset($_GET['from_month']) ? trim($_GET['from_month']) : $defaults[0];
$to_month = isset($_GET['to_month']) ? trim($_GET['to_month']) : $defaults[1];
$filter_employee = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$filter_lock = isset($_GET['lock_status']) ? trim($_GET['lock_status']) : '';
$filter_uploaded_by = isset($_GET['uploaded_by']) ? (int) $_GET['uploaded_by'] : 0;

$validate_month = static function (string $value, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : $fallback;
};

$from_month = $validate_month($from_month, $defaults[0]);
$to_month = $validate_month($to_month, $defaults[1]);

$where = ['sr.month BETWEEN ? AND ?'];
$params = [$from_month, $to_month];
$types = 'ss';

if ($filter_employee > 0) {
    $where[] = 'sr.employee_id = ?';
    $params[] = $filter_employee;
    $types .= 'i';
}

if ($filter_uploaded_by > 0) {
    $where[] = 'sr.uploaded_by = ?';
    $params[] = $filter_uploaded_by;
    $types .= 'i';
}

if ($filter_lock === 'locked') {
    $where[] = 'sr.is_locked = 1';
} elseif ($filter_lock === 'unlocked') {
    $where[] = 'sr.is_locked = 0';
}

$sql = "SELECT sr.id, sr.month, sr.base_salary, sr.allowances, sr.deductions, sr.net_pay, sr.slip_path, sr.is_locked, sr.created_at,
               emp.employee_code AS emp_code, emp.first_name AS emp_first, emp.last_name AS emp_last,
               uploader.employee_code AS uploader_code, uploader.first_name AS uploader_first, uploader.last_name AS uploader_last
        FROM salary_records sr
        INNER JOIN employees emp ON sr.employee_id = emp.id
        LEFT JOIN employees uploader ON sr.uploaded_by = uploader.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY sr.month DESC, emp.first_name ASC";

$stmt = mysqli_prepare($conn, $sql);
$records = [];
if ($stmt) {
    salary_stmt_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$summary_sql = "SELECT COUNT(*) AS record_count,
                       SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) AS locked_count,
                       SUM(CASE WHEN is_locked = 0 THEN 1 ELSE 0 END) AS unlocked_count,
                       SUM(base_salary) AS total_base,
                       SUM(allowances) AS total_allowances,
                       SUM(deductions) AS total_deductions,
                       SUM(net_pay) AS total_net
                FROM salary_records sr
                WHERE " . implode(' AND ', $where);
$summary_stmt = mysqli_prepare($conn, $summary_sql);
$summary = ['record_count' => 0, 'locked_count' => 0, 'unlocked_count' => 0, 'total_base' => 0, 'total_allowances' => 0, 'total_deductions' => 0, 'total_net' => 0];
if ($summary_stmt) {
    salary_stmt_bind($summary_stmt, $types, $params);
    mysqli_stmt_execute($summary_stmt);
    $res = mysqli_stmt_get_result($summary_stmt);
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $summary['record_count'] = (int) ($row['record_count'] ?? 0);
            $summary['locked_count'] = (int) ($row['locked_count'] ?? 0);
            $summary['unlocked_count'] = (int) ($row['unlocked_count'] ?? 0);
            $summary['total_base'] = (float) ($row['total_base'] ?? 0);
            $summary['total_allowances'] = (float) ($row['total_allowances'] ?? 0);
            $summary['total_deductions'] = (float) ($row['total_deductions'] ?? 0);
            $summary['total_net'] = (float) ($row['total_net'] ?? 0);
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($summary_stmt);
}

$monthly_totals = [];
$monthly_sql = 'SELECT sr.month, SUM(sr.net_pay) AS net_total FROM salary_records sr WHERE ' . implode(' AND ', $where) . ' GROUP BY sr.month ORDER BY sr.month DESC LIMIT 12';
$monthly_stmt = mysqli_prepare($conn, $monthly_sql);
if ($monthly_stmt) {
    salary_stmt_bind($monthly_stmt, $types, $params);
    mysqli_stmt_execute($monthly_stmt);
    $res = mysqli_stmt_get_result($monthly_stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $monthly_totals[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($monthly_stmt);
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    $closeManagedConnection();
}
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;">
                <div>
                    <h1>💼 Salary Manager</h1>
                    <p>Upload salary slips, lock payroll, and manage corrections.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if ($can_create_salary): ?>
                        <a href="upload.php" class="btn" style="background:#003581;color:#fff;">＋ Upload Salary</a>
                    <?php endif; ?>
                    <?php if ($can_edit_salary): ?>
                        <a href="../../scripts/setup_salary_records_table.php" class="btn btn-secondary">⚙ Module Setup</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
            <div class="card" style="background:linear-gradient(135deg,#003581 0%,#0056b3 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo $summary['record_count']; ?>
                </div>
                <div>Total records (filtered)</div>
            </div>
            <div class="card" style="background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo salary_format_currency($summary['total_net']); ?>
                </div>
                <div>Total net pay</div>
            </div>
            <div class="card" style="background:linear-gradient(135deg,#17a2b8 0%,#138496 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo $summary['locked_count']; ?>
                </div>
                <div>Locked entries</div>
            </div>
            <div class="card" style="background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo $summary['unlocked_count']; ?>
                </div>
                <div>Draft entries</div>
            </div>
        </div>

        <?php echo flash_render(); ?>

        <?php if (!empty($monthly_totals)): ?>
            <div class="card" style="margin-bottom:24px;">
                <h3 style="margin-top:0;color:#003581;">Monthly payroll (last <?php echo count($monthly_totals); ?> months)</h3>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                                <th style="padding:10px;text-align:left;color:#003581;font-weight:600;">Month</th>
                                <th style="padding:10px;text-align:right;color:#003581;font-weight:600;">Total net pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_totals as $month_row): ?>
                                <tr style="border-bottom:1px solid #e1e8ed;">
                                    <td style="padding:10px;white-space:nowrap;"><?php echo salary_format_month_label($month_row['month']); ?></td>
                                    <td style="padding:10px;text-align:right;font-weight:600;color:#155724;"><?php echo salary_format_currency($month_row['net_total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin-top:0;color:#003581;">Filter salary records</h3>
            <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label for="from_month">From month</label>
                    <input type="month" id="from_month" name="from_month" class="form-control" value="<?php echo htmlspecialchars($from_month, ENT_QUOTES); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="to_month">To month</label>
                    <input type="month" id="to_month" name="to_month" class="form-control" value="<?php echo htmlspecialchars($to_month, ENT_QUOTES); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="employee_id">Employee</label>
                    <select id="employee_id" name="employee_id" class="form-control">
                        <option value="0">All employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <?php $selected = $filter_employee === (int) $emp['id'] ? 'selected' : ''; ?>
                            <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="lock_status">Lock status</label>
                    <select id="lock_status" name="lock_status" class="form-control">
                        <option value="">All</option>
                        <option value="locked" <?php echo $filter_lock === 'locked' ? 'selected' : ''; ?>>Locked</option>
                        <option value="unlocked" <?php echo $filter_lock === 'unlocked' ? 'selected' : ''; ?>>Unlocked</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="uploaded_by">Uploaded by</label>
                    <select id="uploaded_by" name="uploaded_by" class="form-control">
                        <option value="0">Anyone</option>
                        <?php foreach ($employees as $emp): ?>
                            <?php $selected = $filter_uploaded_by === (int) $emp['id'] ? 'selected' : ''; ?>
                            <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn" style="width:100%;">Apply filters</button>
                </div>
                <div>
                    <a href="admin.php" class="btn btn-secondary" style="width:100%;text-align:center;">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
                <h3 style="margin:0;color:#003581;">Salary records (<?php echo count($records); ?>)</h3>
            </div>

            <?php if (empty($records)): ?>
                <div class="alert alert-info" style="margin:0;">No salary data matches the current filters.</div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Employee</th>
                                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Month</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Base</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Allowances</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Deductions</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Net Pay</th>
                                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Uploaded by</th>
                                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Status</th>
                                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <?php
                                    $locked = (int) $record['is_locked'] === 1;
                                    $status_label = $locked
                                        ? '<span style="padding:4px 10px;border-radius:12px;background:#d4edda;color:#155724;font-size:12px;">Locked</span>'
                                        : '<span style="padding:4px 10px;border-radius:12px;background:#ffeeba;color:#856404;font-size:12px;">Draft</span>';
                                    $download_link = !empty($record['slip_path']) ? 'view.php?id=' . (int) $record['id'] . '&download=1' : null;
                                ?>
                                <tr style="border-bottom:1px solid #e1e8ed;">
                                    <td style="padding:12px;">
                                        <?php echo salary_format_employee($record['emp_code'] ?? null, $record['emp_first'] ?? null, $record['emp_last'] ?? null); ?>
                                    </td>
                                    <td style="padding:12px;white-space:nowrap;font-weight:600;color:#1b2a57;">
                                        <?php echo salary_format_month_label($record['month']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;">
                                        <?php echo salary_format_currency($record['base_salary']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;">
                                        <?php echo salary_format_currency($record['allowances']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;">
                                        <?php echo salary_format_currency($record['deductions']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;font-weight:600;color:#155724;">
                                        <?php echo salary_format_currency($record['net_pay']); ?>
                                    </td>
                                    <td style="padding:12px;">
                                        <?php echo salary_format_employee($record['uploader_code'] ?? null, $record['uploader_first'] ?? null, $record['uploader_last'] ?? null); ?>
                                    </td>
                                    <td style="padding:12px;text-align:center;">
                                        <?php echo $status_label; ?>
                                    </td>
                                    <td style="padding:12px;text-align:center;white-space:nowrap;">
                                        <a href="view.php?id=<?php echo (int) $record['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;">View</a>
                                        <a href="<?php echo $download_link ? htmlspecialchars($download_link, ENT_QUOTES) : '#'; ?>" class="btn" style="padding:6px 14px;font-size:13px;background:#17a2b8;color:#fff;<?php echo $download_link ? '' : 'opacity:0.5;pointer-events:none;'; ?>">PDF</a>
                                        <?php if ($can_edit_salary): ?>
                                            <a href="edit.php?id=<?php echo (int) $record['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;background:#003581;color:#fff;">Edit</a>
                                        <?php endif; ?>
                                        <?php if ($can_edit_salary): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Change lock state for this record?');">
                                                <input type="hidden" name="record_id" value="<?php echo (int) $record['id']; ?>">
                                                <input type="hidden" name="action" value="<?php echo $locked ? 'unlock' : 'lock'; ?>">
                                                <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:<?php echo $locked ? '#6c757d' : '#28a745'; ?>;color:#fff;">
                                                    <?php echo $locked ? 'Unlock' : 'Lock'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($can_delete_salary): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this salary record?');">
                                                <input type="hidden" name="record_id" value="<?php echo (int) $record['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:#dc3545;color:#fff;">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
