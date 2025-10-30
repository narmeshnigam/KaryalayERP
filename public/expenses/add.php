<?php
/**
 * Expense Tracker - Add Expense Entry
 */

require_once __DIR__ . '/../../includes/auth_check.php';

authz_require_permission($conn, 'office_expenses', 'create');

$page_title = 'Add Expense - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = $conn ?? createConnection(true);

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
    echo '<div class="card" style="max-width:760px;margin:0 auto;">';
    echo '<h2 style="margin-top:0;color:#003581;">Expense Tracker module not ready</h2>';
    echo '<p>The <code>office_expenses</code> table is missing. Run the setup script to continue.</p>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$user_id = (int) $CURRENT_USER_ID;
$emp_stmt = mysqli_prepare($conn, 'SELECT * FROM employees WHERE user_id = ? LIMIT 1');
if (!$emp_stmt) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to load employee details.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}
mysqli_stmt_bind_param($emp_stmt, 'i', $user_id);
mysqli_stmt_execute($emp_stmt);
$emp_result = mysqli_stmt_get_result($emp_stmt);
$employee = mysqli_fetch_assoc($emp_result);
mysqli_stmt_close($emp_stmt);

if (!$employee) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">No employee record linked to your account. Please contact HR.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$category_options = [];
$category_query = 'SELECT DISTINCT category FROM office_expenses ORDER BY category';
if ($category_res = mysqli_query($conn, $category_query)) {
    while ($row = mysqli_fetch_assoc($category_res)) {
        if (!empty($row['category'])) {
            $category_options[] = $row['category'];
        }
    }
}
if (empty($category_options)) {
    $category_options = ['Utilities', 'Office Supplies', 'Staff Welfare', 'Travel & Conveyance', 'Maintenance', 'Marketing', 'IT & Software', 'Miscellaneous'];
}

$payment_modes = [];
$payment_query = 'SELECT DISTINCT payment_mode FROM office_expenses ORDER BY payment_mode';
if ($payment_res = mysqli_query($conn, $payment_query)) {
    while ($row = mysqli_fetch_assoc($payment_res)) {
        if (!empty($row['payment_mode'])) {
            $payment_modes[] = $row['payment_mode'];
        }
    }
}
if (empty($payment_modes)) {
    $payment_modes = ['Cash', 'Bank Transfer', 'UPI', 'Credit Card', 'Debit Card', 'Cheque'];
}

$errors = [];
$success = '';

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

    if (!in_array($category, $category_options, true)) {
        $errors[] = 'Please select a valid category.';
    }

    if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    if (!in_array($payment_mode, $payment_modes, true)) {
        $errors[] = 'Please choose a valid payment mode.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['receipt'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading receipt file.';
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $max_size = 2 * 1024 * 1024;
            $file_type = mime_content_type($file['tmp_name']);
            if ($file_type === false || !in_array($file_type, $allowed_types, true)) {
                $errors[] = 'Receipt must be a PDF, JPG, or PNG file.';
            }
            if ($file['size'] > $max_size) {
                $errors[] = 'Receipt file must be under 2 MB.';
            }
            if (empty($errors)) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                try {
                    $token = bin2hex(random_bytes(4));
                } catch (Exception $e) {
                    $token = substr(md5(uniqid((string) microtime(true), true)), 0, 8);
                }
                $filename = 'expense_' . time() . '_' . $token . '.' . $ext;
                $dest_dir = __DIR__ . '/../../uploads/office_expenses';
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                    $errors[] = 'Failed to store the uploaded receipt.';
                } else {
                    $receipt_path = 'uploads/office_expenses/' . $filename;
                }
            }
        }
    }

    if (empty($errors)) {
        $insert_sql = 'INSERT INTO office_expenses (date, category, vendor_name, description, amount, payment_mode, receipt_file, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $insert_sql);
        if ($stmt) {
            $amount_value = round((float) $amount, 2);
            $amount_param = sprintf('%.2f', $amount_value);
            $receipt_param = $receipt_path ?: null;
            mysqli_stmt_bind_param($stmt, 'sssssssi', $date, $category, $vendor_name, $description, $amount_param, $payment_mode, $receipt_param, $employee['id']);
                if (mysqli_stmt_execute($stmt)) {
                $success = 'Expense recorded successfully.';
                $date = date('Y-m-d');
                $category = $category_options[0] ?? '';
                $vendor_name = '';
                $amount = '';
                $payment_mode = $payment_modes[0] ?? '';
                $description = '';
            } else {
                $errors[] = 'Unable to save the expense. Please try again.';
                if ($receipt_path) {
                    $stored_path = __DIR__ . '/../../' . ltrim($receipt_path, '/');
                    if (is_file($stored_path)) {
                        @unlink($stored_path);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Failed to prepare database statement.';
            if ($receipt_path) {
                $stored_path = __DIR__ . '/../../' . ltrim($receipt_path, '/');
                if (is_file($stored_path)) {
                    @unlink($stored_path);
                }
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
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🧾 Record Office Expense</h1>
                    <p>Log operational spending and internal overheads.</p>
                </div>
                <div>
                    <a class="btn btn-accent" href="index.php">← Back to Expenses</a>
                </div>
            </div>
        </div>

        <?php if ($success) : ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
        <?php endif; ?>

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
            <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;">
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
                    <small style="color:#6c757d;display:block;margin-top:6px;">Uploads are optional. Existing receipts remain unchanged unless a new file is uploaded.</small>
                </div>
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
