<?php
/**
 * Salary Viewer - Detailed salary record view.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

$close_managed = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $conn = null;
    }
};

$record_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$salary_permissions = authz_get_permission_set($conn, 'salary_records');
$can_view_all = !empty($salary_permissions['can_view_all']);
$can_view_own = !empty($salary_permissions['can_view_own']);
$can_edit_salary = !empty($salary_permissions['can_edit_all']);
$can_delete_salary = !empty($salary_permissions['can_delete_all']);

$redirect_back = $can_view_all ? 'admin.php' : '../employee_portal/salary/index.php';

if ($record_id <= 0) {
    flash_add('error', 'Missing salary record identifier.', 'salary');
    header('Location: ' . $redirect_back);
    exit;
}

$conn_check = createConnection(true);
if ($conn_check) {
    $prereq_check = get_prerequisite_check_result($conn_check, 'salary');
    if (!$prereq_check['allowed']) {
        closeConnection($conn_check);
        display_prerequisite_error('salary', $prereq_check['missing_modules']);
    }
    closeConnection($conn_check);
}

$page_title = 'Salary Details - ' . APP_NAME;

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

$current_employee_id = salary_current_employee_id($conn, (int) $CURRENT_USER_ID);

$select_sql = "SELECT sr.*, 
             emp.employee_code AS emp_code, emp.first_name AS emp_first, emp.last_name AS emp_last,
             uploader.employee_code AS uploader_code, uploader.first_name AS uploader_first, uploader.last_name AS uploader_last
         FROM salary_records sr
         INNER JOIN employees emp ON sr.employee_id = emp.id
         LEFT JOIN employees uploader ON sr.uploaded_by = uploader.id
         WHERE sr.id = ?
         LIMIT 1";
$select = mysqli_prepare($conn, $select_sql);
if (!$select) {
    $close_managed();
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
    $close_managed();
    flash_add('error', 'Salary record not found.', 'salary');
    header('Location: ' . $redirect_back);
    exit;
}

$owns_record = $current_employee_id && ((int) $record['employee_id'] === (int) $current_employee_id);

if (!$can_view_all) {
    if (!$can_view_own || !$owns_record) {
        $close_managed();
        flash_add('error', 'You are not allowed to view this salary record.', 'salary');
        header('Location: ' . $redirect_back);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'lock' || $action === 'unlock') {
        if (!$can_edit_salary) {
            flash_add('error', 'You do not have permission to change lock state.', 'salary');
            $close_managed();
            header('Location: view.php?id=' . $record_id);
            exit;
        }
        $desired = $action === 'lock' ? 1 : 0;
        if ((int) $record['is_locked'] === $desired) {
            flash_add('info', 'Record is already in the requested state.', 'salary');
        } else {
            $update = mysqli_prepare($conn, 'UPDATE salary_records SET is_locked = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            if ($update) {
                mysqli_stmt_bind_param($update, 'ii', $desired, $record_id);
                if (mysqli_stmt_execute($update)) {
                    flash_add('success', $desired ? 'Salary record locked.' : 'Salary record unlocked.', 'salary');
                    $record['is_locked'] = $desired;
                } else {
                    flash_add('error', 'Unable to update record state.', 'salary');
                }
                mysqli_stmt_close($update);
            }
        }
        $close_managed();
        header('Location: view.php?id=' . $record_id);
        exit;
    }

    if ($action === 'delete') {
        if (!$can_delete_salary) {
            flash_add('error', 'You do not have permission to delete salary records.', 'salary');
            $close_managed();
            header('Location: view.php?id=' . $record_id);
            exit;
        }
        if ((int) $record['is_locked'] === 1) {
            flash_add('error', 'Locked salary records cannot be deleted.', 'salary');
            $close_managed();
            header('Location: view.php?id=' . $record_id);
            exit;
        }
        $delete = mysqli_prepare($conn, 'DELETE FROM salary_records WHERE id = ?');
        if ($delete) {
            mysqli_stmt_bind_param($delete, 'i', $record_id);
            if (mysqli_stmt_execute($delete)) {
                if (!empty($record['slip_path'])) {
                    $file = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($record['slip_path']);
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                flash_add('success', 'Salary record deleted.', 'salary');
                $close_managed();
                header('Location: admin.php');
                exit;
            }
            mysqli_stmt_close($delete);
        }
        flash_add('error', 'Unable to delete salary record.', 'salary');
        $close_managed();
        header('Location: view.php?id=' . $record_id);
        exit;
    }

    flash_add('warning', 'Unsupported action.', 'salary');
    $close_managed();
    header('Location: view.php?id=' . $record_id);
    exit;
}

// Note: we intentionally include header/sidebar only after handling download (if requested)
// to avoid sending any HTML output before PDF headers are emitted.

// Try to populate emp_email safely (some deployments don't have employees.email column)
$record['emp_email'] = salary_get_employee_email($conn, (int) ($record['employee_id'] ?? 0));

// If download was requested, stream the PDF and exit before sending page HTML
if (isset($_GET['download'])) {
    if (empty($record['slip_path'])) {
        flash_add('error', 'Salary slip not available for download.', 'salary');
        $close_managed();
        header('Location: view.php?id=' . $record_id);
        exit;
    }
    $file_path = salary_upload_directory() . DIRECTORY_SEPARATOR . basename($record['slip_path']);
    if (!is_file($file_path)) {
        flash_add('error', 'Salary slip file is missing.', 'salary');
        $close_managed();
        header('Location: view.php?id=' . $record_id);
        exit;
    }

    // Notify (logging) before streaming
    salary_notify_slip_downloaded($conn, $record_id, (int) $record['employee_id']);
    $file_name = 'salary-slip-' . str_replace('-', '', $record['month']) . '.pdf';

    // Close DB connection before streaming
    $close_managed();

    // Ensure no prior output is sent; PHP's output buffering may be active via bootstrap.
    // Send proper headers for PDF and stream the file.
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    // flush any output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($file_path);
    exit;
}

$close_managed();

// Now it's safe to include the header and sidebar which emit HTML
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;">
                <div>
                    <h1>💳 Salary Details</h1>
                    <p>Summary of earnings for <?php echo salary_format_month_label($record['month']); ?>.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="<?php echo $redirect_back; ?>" class="btn btn-secondary">← Back</a>
                    <?php if (!empty($record['slip_path'])): ?>
                        <a href="view.php?id=<?php echo $record_id; ?>&download=1" class="btn" style="background:#17a2b8;color:#fff;">⬇ Download Slip</a>
                    <?php endif; ?>
                    <?php if ($can_edit_salary): ?>
                        <a href="edit.php?id=<?php echo $record_id; ?>" class="btn" style="background:#003581;color:#fff;">✏️ Edit</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php echo flash_render(); ?>

        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin-top:0;color:#003581;">Employee information</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
                <div>
                    <div style="color:#6c757d;font-size:12px;">Employee</div>
                    <div style="font-weight:600;color:#1b2a57;">
                        <?php echo salary_format_employee($record['emp_code'] ?? null, $record['emp_first'] ?? null, $record['emp_last'] ?? null); ?>
                    </div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Email</div>
                    <div><?php echo htmlspecialchars($record['emp_email'] ?? '—', ENT_QUOTES); ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Salary month</div>
                    <div><?php echo salary_format_month_label($record['month']); ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Uploaded by</div>
                    <div><?php echo salary_format_employee($record['uploader_code'] ?? null, $record['uploader_first'] ?? null, $record['uploader_last'] ?? null); ?></div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:24px;">
            <div class="card" style="background:#f8f9fa;">
                <h3 style="margin-top:0;color:#003581;">Base salary</h3>
                <div style="font-size:26px;font-weight:700;color:#1b2a57;"><?php echo salary_format_currency($record['base_salary']); ?></div>
            </div>
            <div class="card" style="background:#f1f8ff;">
                <h3 style="margin-top:0;color:#003581;">Allowances</h3>
                <div style="font-size:26px;font-weight:700;color:#0c5460;"><?php echo salary_format_currency($record['allowances']); ?></div>
            </div>
            <div class="card" style="background:#fff5f5;">
                <h3 style="margin-top:0;color:#d63384;">Deductions</h3>
                <div style="font-size:26px;font-weight:700;color:#dc3545;"><?php echo salary_format_currency($record['deductions']); ?></div>
            </div>
            <div class="card" style="background:#e3fcef;">
                <h3 style="margin-top:0;color:#155724;">Net pay</h3>
                <div style="font-size:26px;font-weight:700;color:#155724;"><?php echo salary_format_currency($record['net_pay']); ?></div>
            </div>
        </div>

        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin-top:0;color:#003581;">Status & timestamps</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
                <div>
                    <div style="color:#6c757d;font-size:12px;">Lock status</div>
                    <?php if ((int) $record['is_locked'] === 1): ?>
                        <span style="display:inline-block;padding:6px 12px;border-radius:12px;background:#d4edda;color:#155724;font-weight:600;font-size:12px;">Locked</span>
                    <?php else: ?>
                        <span style="display:inline-block;padding:6px 12px;border-radius:12px;background:#ffeeba;color:#856404;font-weight:600;font-size:12px;">Draft</span>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Created at</div>
                    <div><?php echo $record['created_at'] ? date('d M Y, h:i A', strtotime($record['created_at'])) : '—'; ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Last updated</div>
                    <div><?php echo $record['updated_at'] ? date('d M Y, h:i A', strtotime($record['updated_at'])) : '—'; ?></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin-top:0;color:#003581;">Attendance summary</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
                <div>
                    <div style="color:#6c757d;font-size:12px;">Working days</div>
                    <div><?php echo isset($record['working_days_total']) ? (int)$record['working_days_total'] : '—'; ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Days worked</div>
                    <div><?php echo isset($record['days_worked']) ? (float)$record['days_worked'] : '—'; ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Leaves</div>
                    <div><?php echo isset($record['leave_days']) ? (float)$record['leave_days'] : '—'; ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:12px;">Unpaid leave days</div>
                    <div><?php echo isset($record['unpaid_leave_days']) ? (float)$record['unpaid_leave_days'] : '—'; ?></div>
                </div>
            </div>
            <?php if (!empty($record['leave_breakdown'])): ?>
                <div style="margin-top:12px;">
                    <div style="color:#6c757d;font-size:12px;">Leave breakdown</div>
                    <pre style="background:#f8f9fa;padding:12px;border-radius:6px;overflow:auto;max-height:200px;"><?php echo htmlspecialchars($record['leave_breakdown'], ENT_QUOTES); ?></pre>
                </div>
            <?php endif; ?>
            <?php if (!empty($record['deduction_breakdown'])): ?>
                <div style="margin-top:12px;">
                    <div style="color:#6c757d;font-size:12px;">Deduction breakdown</div>
                    <pre style="background:#f8f9fa;padding:12px;border-radius:6px;overflow:auto;max-height:200px;"><?php echo htmlspecialchars($record['deduction_breakdown'], ENT_QUOTES); ?></pre>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($can_edit_salary || $can_delete_salary): ?>
            <div class="card" style="border:1px solid #dee2e6;background:#fff8e1;">
                <h3 style="margin-top:0;color:#b35c00;">Admin actions</h3>
                <div style="display:flex;flex-wrap:wrap;gap:12px;">
                    <?php if ($can_edit_salary): ?>
                        <form method="POST" onsubmit="return confirm('Toggle lock status for this record?');">
                            <input type="hidden" name="action" value="<?php echo (int) $record['is_locked'] === 1 ? 'unlock' : 'lock'; ?>">
                            <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:<?php echo (int) $record['is_locked'] === 1 ? '#6c757d' : '#28a745'; ?>;color:#fff;">
                                <?php echo (int) $record['is_locked'] === 1 ? 'Unlock record' : 'Lock record'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($can_delete_salary): ?>
                        <form method="POST" onsubmit="return confirm('Delete this salary record? This cannot be undone.');">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:#dc3545;color:#fff;">Delete record</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
