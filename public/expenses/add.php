<?php
/**
 * Expense Tracker - Add Expense Entry
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

authz_require_permission($conn, 'office_expenses', 'create');

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

$employee = office_expenses_current_employee($conn, (int) $CURRENT_USER_ID);
if (!$employee) {
    $closeManagedConnection();
    flash_add('error', 'No employee record linked to your account. Please contact HR.', 'office_expenses');
    header('Location: index.php');
    exit;
}

$category_options = office_expenses_fetch_categories($conn);
if (empty($category_options)) {
    $category_options = office_expenses_default_categories();
}

$payment_modes = office_expenses_fetch_payment_modes($conn);
if (empty($payment_modes)) {
    $payment_modes = office_expenses_default_payment_modes();
}

$errors = [];
$new_receipt_path = null;

$date = date('Y-m-d');
$category = $category_options[0] ?? '';
$vendor_name = '';
$amount = '';
$payment_mode = $payment_modes[0] ?? '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $payment_mode = trim($_POST['payment_mode'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($category !== '' && !in_array($category, $category_options, true)) {
        array_unshift($category_options, $category);
    }
    if ($payment_mode !== '' && !in_array($payment_mode, $payment_modes, true)) {
        array_unshift($payment_modes, $payment_mode);
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

    if ($payment_mode === '' || !in_array($payment_mode, $payment_modes, true)) {
        $errors[] = 'Please choose a valid payment mode.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    $new_receipt_path = office_expenses_store_receipt_file($_FILES['receipt'] ?? ['error' => UPLOAD_ERR_NO_FILE], $errors);

    if (empty($errors)) {
        $insert_sql = 'INSERT INTO office_expenses (date, category, vendor_name, description, amount, payment_mode, receipt_file, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $insert_sql);
        if ($stmt) {
            $amount_value = sprintf('%.2f', round((float) $amount, 2));
            mysqli_stmt_bind_param($stmt, 'sssssssi', $date, $category, $vendor_name, $description, $amount_value, $payment_mode, $new_receipt_path, $employee['id']);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $closeManagedConnection();
                flash_add('success', 'Expense recorded successfully.', 'office_expenses');
                header('Location: index.php');
                exit;
            }
            $errors[] = 'Unable to save the expense. Please try again.';
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Failed to prepare database statement.';
        }
    }

    if (!empty($errors)) {
        office_expenses_delete_receipt_file($new_receipt_path);
        $new_receipt_path = null;
    }
}

$page_title = 'Add Expense - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.expense-add-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.expense-add-header-buttons{display:flex;gap:10px;flex-wrap:wrap;}
.expense-add-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;}

@media (max-width:1024px){
.expense-add-form-grid{grid-template-columns:repeat(2,1fr);}
}

@media (max-width:768px){
.expense-add-header-flex{flex-direction:column;align-items:stretch;}
.expense-add-header-buttons{width:100%;flex-direction:column;gap:10px;}
.expense-add-header-buttons .btn{width:100%;text-align:center;}
.expense-add-form-grid{grid-template-columns:1fr;}
}

@media (max-width:480px){
.expense-add-header-flex h1{font-size:1.5rem;}
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div class="expense-add-header-flex">
                <div>
                    <h1>🧾 Record Office Expense</h1>
                    <p>Log operational spending and internal overheads.</p>
                </div>
                <div class="expense-add-header-buttons">
                    <a class="btn btn-accent" href="index.php">← Back to Expenses</a>
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
            <form method="post" enctype="multipart/form-data" class="expense-add-form-grid">
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
                        <?php foreach ($payment_modes as $mode) : ?>
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
                    <small style="color:#6c757d;display:block;margin-top:6px;">Upload a receipt to keep your documentation centralized. Leave empty to skip.</small>
                </div>
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
