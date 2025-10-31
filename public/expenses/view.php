<?php
/**
 * Expense Tracker - View Expense Details
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/helpers.php';

$closeManagedConnection = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
    }
};

if (!authz_user_can_any($conn, [
    ['table' => 'office_expenses', 'permission' => 'view_all'],
    ['table' => 'office_expenses', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'office_expenses', 'view_all');
}

$expense_permissions = authz_get_permission_set($conn, 'office_expenses');
$can_view_all = !empty($expense_permissions['can_view_all']);
$can_view_own = !empty($expense_permissions['can_view_own']);
$can_edit_all = !empty($expense_permissions['can_edit_all']);
$can_edit_own = !empty($expense_permissions['can_edit_own']);

if (!($conn instanceof mysqli)) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'office_expenses');
if (!$prereq_check['allowed']) {
    $closeManagedConnection();
    display_prerequisite_error('office_expenses', $prereq_check['missing_modules']);
    exit;
}

if (!office_expenses_table_exists($conn)) {
    $closeManagedConnection();
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$expense_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($expense_id <= 0) {
    $closeManagedConnection();
    flash_add('error', 'Invalid expense identifier supplied.', 'office_expenses');
    header('Location: index.php');
    exit;
}

$current_employee = office_expenses_current_employee($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;
if (!$can_view_all) {
    if ($can_view_own && $current_employee) {
        $restricted_employee_id = (int) $current_employee['id'];
    } else {
        $closeManagedConnection();
        authz_require_permission($conn, 'office_expenses', 'view_all');
    }
}

$sql = 'SELECT e.*, emp.employee_code, emp.first_name, emp.last_name, emp.department
        FROM office_expenses e
        LEFT JOIN employees emp ON e.added_by = emp.id
        WHERE e.id = ?';
$params = [$expense_id];
$types = 'i';

if ($restricted_employee_id !== null) {
    $sql .= ' AND e.added_by = ?';
    $params[] = $restricted_employee_id;
    $types .= 'i';
}

$sql .= ' LIMIT 1';

$statement = mysqli_prepare($conn, $sql);
if (!$statement) {
    $closeManagedConnection();
    flash_add('error', 'Unable to load the expense record.', 'office_expenses');
    header('Location: index.php');
    exit;
}

office_expenses_stmt_bind($statement, $types, $params);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
$expense = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($statement);

if (!$expense || (!empty($expense['deleted_at']))) {
    $closeManagedConnection();
    flash_add('error', 'Expense record not found or access denied.', 'office_expenses');
    header('Location: index.php');
    exit;
}

$is_owner = $current_employee && (int) ($expense['added_by'] ?? 0) === (int) $current_employee['id'];
$can_edit_expense = $IS_SUPER_ADMIN || $can_edit_all || ($can_edit_own && $is_owner);

$added_by = '—';
if (!empty($expense['employee_code'])) {
    $full_name = trim(($expense['first_name'] ?? '') . ' ' . ($expense['last_name'] ?? ''));
    $full_name = $full_name !== '' ? $full_name : 'Employee';
    $department = !empty($expense['department']) ? ' · ' . htmlspecialchars($expense['department'], ENT_QUOTES) : '';
    $added_by = htmlspecialchars($expense['employee_code'], ENT_QUOTES) . ' - ' . htmlspecialchars($full_name, ENT_QUOTES) . $department;
}

$receipt_url = null;
if (!empty($expense['receipt_file'])) {
    $receipt_url = APP_URL . '/' . ltrim($expense['receipt_file'], '/');
}

$page_title = 'Expense Details - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>Expense #<?php echo (int) $expense['id']; ?></h1>
                    <p>Detailed breakdown for <?php echo htmlspecialchars(date('d M Y', strtotime($expense['date'])), ENT_QUOTES); ?>.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if ($can_edit_expense): ?>
                        <a href="edit.php?id=<?php echo (int) $expense['id']; ?>" class="btn" style="background:#17a2b8;">✏️ Edit</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">← Back to Expenses</a>
                </div>
            </div>
        </div>

    <?php echo flash_render(); ?>

        <div class="card" style="padding:24px;margin-bottom:24px;">
            <h3 style="margin-top:0;color:#003581;">Expense Summary</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
                <div>
                    <div style="color:#6c757d;font-size:13px;">Category</div>
                    <div style="font-weight:600;font-size:18px;"><?php echo htmlspecialchars($expense['category'], ENT_QUOTES); ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:13px;">Vendor / Payee</div>
                    <div style="font-weight:600;font-size:18px;"><?php echo $expense['vendor_name'] !== null && $expense['vendor_name'] !== '' ? htmlspecialchars($expense['vendor_name'], ENT_QUOTES) : '—'; ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:13px;">Amount (₹)</div>
                    <div style="font-weight:700;font-size:22px;color:#003581;">₹ <?php echo number_format((float) $expense['amount'], 2); ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:13px;">Payment Mode</div>
                    <div style="font-weight:600;font-size:18px;"><?php echo htmlspecialchars($expense['payment_mode'], ENT_QUOTES); ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:13px;">Recorded By</div>
                    <div style="font-weight:600;font-size:16px;"><?php echo $added_by; ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:13px;">Created At</div>
                    <div style="font-weight:600;font-size:16px;"><?php echo htmlspecialchars(date('d M Y · h:i A', strtotime($expense['created_at'])), ENT_QUOTES); ?></div>
                </div>
                <div>
                    <div style="color:#6c757d;font-size:13px;">Last Updated</div>
                    <div style="font-weight:600;font-size:16px;"><?php echo htmlspecialchars(date('d M Y · h:i A', strtotime($expense['updated_at'])), ENT_QUOTES); ?></div>
                </div>
            </div>
        </div>

        <div class="card" style="padding:24px;margin-bottom:24px;">
            <h3 style="margin-top:0;color:#003581;">Description</h3>
            <p style="line-height:1.6;margin:0;white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($expense['description'], ENT_QUOTES)); ?></p>
        </div>

        <div class="card" style="padding:24px;">
            <h3 style="margin-top:0;color:#003581;">Receipt</h3>
            <?php if ($receipt_url) : ?>
                <p style="margin-bottom:16px;">Attached receipt is available for download.</p>
                <a href="<?php echo htmlspecialchars($receipt_url, ENT_QUOTES); ?>" target="_blank" class="btn">📄 View Receipt</a>
            <?php else : ?>
                <div class="alert alert-info" style="margin:0;">No receipt uploaded for this expense.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
