<?php
/**
 * Expense Tracker - Edit Expense Entry
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

$expense_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($expense_id <= 0) {
    flash_add('error', 'Invalid expense identifier supplied.', 'office_expenses');
    header('Location: index.php');
    exit;
}

if (!authz_user_can_any($conn, [
    ['table' => 'office_expenses', 'permission' => 'edit_all'],
    ['table' => 'office_expenses', 'permission' => 'edit_own'],
])) {
    authz_require_permission($conn, 'office_expenses', 'edit_all');
}

$expense_permissions = authz_get_permission_set($conn, 'office_expenses');
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

$current_employee = office_expenses_current_employee($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;
if (!$can_edit_all) {
    if ($can_edit_own && $current_employee) {
        $restricted_employee_id = (int) $current_employee['id'];
    } else {
        $closeManagedConnection();
        authz_require_permission($conn, 'office_expenses', 'edit_all');
    }
}

$expense = office_expenses_fetch_expense($conn, $expense_id);

if ($restricted_employee_id !== null && $expense && (int) ($expense['added_by'] ?? 0) !== $restricted_employee_id) {
    $expense = null;
}

if (!$expense || (!empty($expense['deleted_at']))) {
    $closeManagedConnection();
    flash_add('error', 'Expense record not found or access denied.', 'office_expenses');
    header('Location: index.php');
    exit;
}

$is_owner = $current_employee && (int) ($expense['added_by'] ?? 0) === (int) $current_employee['id'];
if (!$can_edit_all && !$is_owner) {
    $closeManagedConnection();
    flash_add('error', 'You do not have permission to modify this expense.', 'office_expenses');
    header('Location: index.php');
    exit;
}

$category_options = office_expenses_fetch_categories($conn);
if (empty($category_options)) {
    $category_options = office_expenses_default_categories();
}
if (!empty($expense['category']) && !in_array($expense['category'], $category_options, true)) {
    array_unshift($category_options, $expense['category']);
}

$payment_options = office_expenses_fetch_payment_modes($conn);
if (empty($payment_options)) {
    $payment_options = office_expenses_default_payment_modes();
}
if (!empty($expense['payment_mode']) && !in_array($expense['payment_mode'], $payment_options, true)) {
    array_unshift($payment_options, $expense['payment_mode']);
}

$errors = [];
$new_receipt_path = null;

$date = isset($_POST['date']) ? trim($_POST['date']) : ($expense['date'] ?: date('Y-m-d'));
$category = isset($_POST['category']) ? trim($_POST['category']) : ($expense['category'] ?? '');
$vendor_name = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : ($expense['vendor_name'] ?? '');
$amount = isset($_POST['amount']) ? trim($_POST['amount']) : ($expense['amount'] !== null ? sprintf('%.2f', (float) $expense['amount']) : '');
$payment_mode = isset($_POST['payment_mode']) ? trim($_POST['payment_mode']) : ($expense['payment_mode'] ?? '');
$description = isset($_POST['description']) ? trim($_POST['description']) : ($expense['description'] ?? '');
$remove_receipt = isset($_POST['remove_receipt']) && $_POST['remove_receipt'] === '1';
$existing_receipt = $expense['receipt_file'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($category !== '' && !in_array($category, $category_options, true)) {
        array_unshift($category_options, $category);
    }
    if ($payment_mode !== '' && !in_array($payment_mode, $payment_options, true)) {
        array_unshift($payment_options, $payment_mode);
    }

    if ($date === '') {
        $errors[] = 'Date is required.';
    }

    if ($vendor_name === '') {
        $errors[] = 'Vendor / Payee name is required.';
    }

    if ($category === '' || !in_array($category, $category_options, true)) {
        $errors[] = 'Please select a valid category.';
    }

    if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    if ($payment_mode === '' || !in_array($payment_mode, $payment_options, true)) {
        $errors[] = 'Please choose a valid payment mode.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    $new_receipt_path = office_expenses_store_receipt_file($_FILES['receipt'] ?? ['error' => UPLOAD_ERR_NO_FILE], $errors);
    if ($new_receipt_path !== null) {
        $remove_receipt = false;
    }

    if (empty($errors)) {
        $amount_param = sprintf('%.2f', round((float) $amount, 2));
        $fields = ['date = ?', 'category = ?', 'vendor_name = ?', 'description = ?', 'amount = ?', 'payment_mode = ?'];
        $params = [$date, $category, $vendor_name, $description, $amount_param, $payment_mode];
        $types = 'ssssss';

        if ($new_receipt_path !== null) {
            $fields[] = 'receipt_file = ?';
            $params[] = $new_receipt_path;
            $types .= 's';
        } elseif ($remove_receipt && !empty($existing_receipt)) {
            $fields[] = 'receipt_file = NULL';
        }

        $params[] = $expense_id;
        $types .= 'i';

        $update_sql = 'UPDATE office_expenses SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $update_stmt = mysqli_prepare($conn, $update_sql);
        if ($update_stmt) {
            office_expenses_stmt_bind($update_stmt, $types, $params);
            if (mysqli_stmt_execute($update_stmt) && mysqli_stmt_affected_rows($update_stmt) >= 0) {
                if ($new_receipt_path !== null && !empty($existing_receipt)) {
                    office_expenses_delete_receipt_file($existing_receipt);
                }
                if ($new_receipt_path === null && $remove_receipt && !empty($existing_receipt)) {
                    office_expenses_delete_receipt_file($existing_receipt);
                }
                mysqli_stmt_close($update_stmt);
                $closeManagedConnection();
                flash_add('success', 'Expense details updated successfully.', 'office_expenses');
                header('Location: view.php?id=' . $expense_id);
                exit;
            }
            $errors[] = 'Unable to save changes. Please try again.';
            mysqli_stmt_close($update_stmt);
        } else {
            $errors[] = 'Failed to prepare database statement.';
        }
    }

    if (!empty($errors) && $new_receipt_path !== null) {
        office_expenses_delete_receipt_file($new_receipt_path);
        $new_receipt_path = null;
    }
}

$employee_label = '—';
if (!empty($expense['employee_code'])) {
    $full_name = trim(($expense['first_name'] ?? '') . ' ' . ($expense['last_name'] ?? ''));
    $full_name = $full_name !== '' ? $full_name : 'Employee';
    $employee_label = htmlspecialchars($expense['employee_code'], ENT_QUOTES) . ' - ' . htmlspecialchars($full_name, ENT_QUOTES);
}

$receipt_url = null;
if (!empty($existing_receipt)) {
    $receipt_url = APP_URL . '/' . ltrim($existing_receipt, '/');
}

$page_title = 'Edit Expense - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.expense-edit-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.expense-edit-header-buttons{display:flex;gap:10px;flex-wrap:wrap;}
.expense-edit-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;}

@media (max-width:1024px){
.expense-edit-form-grid{grid-template-columns:repeat(2,1fr);}
}

@media (max-width:768px){
.expense-edit-header-flex{flex-direction:column;align-items:stretch;}
.expense-edit-header-buttons{width:100%;flex-direction:column;gap:10px;}
.expense-edit-header-buttons .btn{width:100%;text-align:center;}
.expense-edit-form-grid{grid-template-columns:1fr;}
}

@media (max-width:480px){
.expense-edit-header-flex h1{font-size:1.5rem;}
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div class="expense-edit-header-flex">
                <div>
                    <h1>✏️ Update Expense</h1>
                    <p>Edit entry recorded on <?php echo htmlspecialchars(date('d M Y', strtotime($expense['date'])), ENT_QUOTES); ?> by <?php echo $employee_label; ?>.</p>
                </div>
                <div class="expense-edit-header-buttons">
                    <a class="btn" style="background:#17a2b8;" href="view.php?id=<?php echo (int) $expense['id']; ?>">👁 View</a>
                    <a class="btn btn-secondary" href="index.php">← Back to Expenses</a>
                </div>
            </div>
        </div>

    <?php echo flash_render(); ?>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card" style="padding:24px;">
            <h3 style="margin-top:0;color:#003581;">Expense Details</h3>
            <form method="post" enctype="multipart/form-data" class="expense-edit-form-grid">
                <div class="form-group" style="margin:0;">
                    <label for="date">Expense Date <span style="color:#dc3545;">*</span></label>
                    <input type="date" class="form-control" name="date" id="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES); ?>" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="category">Category <span style="color:#dc3545;">*</span></label>
                    <select class="form-control" name="category" id="category" required>
                        <?php foreach ($category_options as $option) : ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>" <?php echo ($option === $category) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option, ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="vendor_name">Vendor / Payee <span style="color:#dc3545;">*</span></label>
                    <input type="text" class="form-control" name="vendor_name" id="vendor_name" value="<?php echo htmlspecialchars($vendor_name, ENT_QUOTES); ?>" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="amount">Amount (₹) <span style="color:#dc3545;">*</span></label>
                    <input type="number" class="form-control" step="0.01" min="0" name="amount" id="amount" value="<?php echo htmlspecialchars($amount, ENT_QUOTES); ?>" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="payment_mode">Payment Mode <span style="color:#dc3545;">*</span></label>
                    <select class="form-control" name="payment_mode" id="payment_mode" required>
                        <?php foreach ($payment_options as $mode) : ?>
                            <option value="<?php echo htmlspecialchars($mode, ENT_QUOTES); ?>" <?php echo ($mode === $payment_mode) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mode, ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;margin:0;">
                    <label for="description">Description / Notes <span style="color:#dc3545;">*</span></label>
                    <textarea class="form-control" name="description" id="description" rows="4" required><?php echo htmlspecialchars($description, ENT_QUOTES); ?></textarea>
                </div>
                <div class="form-group" style="grid-column:1/-1;margin:0;">
                    <label for="receipt">Receipt (PDF/JPG/PNG, max 2&nbsp;MB)</label>
                    <input type="file" class="form-control" name="receipt" id="receipt" accept=".pdf,.jpg,.jpeg,.png">
                    <small style="color:#6c757d;display:block;margin-top:6px;">Upload a file to replace the current receipt. Leave empty to keep it unchanged.</small>
                    <?php if (!empty($existing_receipt)) : ?>
                        <div style="margin-top:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <a href="<?php echo htmlspecialchars($receipt_url ?? '', ENT_QUOTES); ?>" target="_blank" class="btn btn-secondary">📄 View current receipt</a>
                            <label style="display:flex;align-items:center;gap:6px;color:#dc3545;font-size:14px;">
                                <input type="checkbox" name="remove_receipt" value="1" <?php echo $remove_receipt ? 'checked' : ''; ?>>
                                Remove existing receipt
                            </label>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info" style="margin-top:10px;">No receipt uploaded for this expense.</div>
                    <?php endif; ?>
                </div>
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:10px;">
                    <a href="view.php?id=<?php echo (int) $expense['id']; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
