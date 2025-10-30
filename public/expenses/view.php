<?php
/**
 * Expense Tracker - View Expense Details
 */

require_once __DIR__ . '/../../includes/auth_check.php';

authz_require_permission($conn, 'office_expenses', 'view_all');
$expense_permissions = authz_get_permission_set($conn, 'office_expenses');
$can_edit_expense = !empty($expense_permissions['can_edit_all']);

$page_title = 'Expense Details - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = $conn ?? createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

function tableExists($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

if (!tableExists($conn, 'office_expenses')) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="card" style="max-width:720px;margin:0 auto;">';
    echo '<h2 style="margin-top:0;color:#003581;">Expense Tracker module not ready</h2>';
    echo '<p>The <code>office_expenses</code> table is missing. Please run the setup script.</p>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$expense_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($expense_id <= 0) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Invalid expense identifier supplied.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$sql = 'SELECT e.*, emp.employee_code, emp.first_name, emp.last_name, emp.department
        FROM office_expenses e
        LEFT JOIN employees emp ON e.added_by = emp.id
        WHERE e.id = ?
        LIMIT 1';
$statement = mysqli_prepare($conn, $sql);
if (!$statement) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to load the expense record.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}
mysqli_stmt_bind_param($statement, 'i', $expense_id);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
$expense = mysqli_fetch_assoc($result);
mysqli_stmt_close($statement);
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}

if (!$expense) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Expense record not found.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

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

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
