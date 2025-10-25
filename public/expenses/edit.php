<?php
/**
 * Expense Tracker - Edit Expense Entry
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'user';
if (!in_array($user_role, ['admin', 'manager'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

$page_title = 'Edit Expense - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$expense_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($expense_id <= 0) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Invalid expense identifier supplied.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$conn = createConnection(true);
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
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="card" style="max-width:760px;margin:0 auto;">';
    echo '<h2 style="margin-top:0;color:#003581;">Expense Tracker module not ready</h2>';
    echo '<p>The <code>office_expenses</code> table is missing. Please run the setup script.</p>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$sql = 'SELECT e.*, emp.employee_code, emp.first_name, emp.last_name
        FROM office_expenses e
        LEFT JOIN employees emp ON e.added_by = emp.id
        WHERE e.id = ?
        LIMIT 1';
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to load the expense record.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $expense_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$expense = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$expense) {
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Expense record not found.</div></div></div>';
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
if (!empty($expense['category']) && !in_array($expense['category'], $category_options, true)) {
    array_unshift($category_options, $expense['category']);
}

$payment_options = [];
$payment_query = 'SELECT DISTINCT payment_mode FROM office_expenses ORDER BY payment_mode';
if ($payment_res = mysqli_query($conn, $payment_query)) {
    while ($row = mysqli_fetch_assoc($payment_res)) {
        if (!empty($row['payment_mode'])) {
            $payment_options[] = $row['payment_mode'];
        }
    }
}
if (empty($payment_options)) {
    $payment_options = ['Cash', 'Bank Transfer', 'UPI', 'Credit Card', 'Debit Card', 'Cheque'];
}
if (!empty($expense['payment_mode']) && !in_array($expense['payment_mode'], $payment_options, true)) {
    array_unshift($payment_options, $expense['payment_mode']);
}

$errors = [];
$success = '';

$date = isset($_POST['date']) ? trim($_POST['date']) : ($expense['date'] ?: date('Y-m-d'));
$category = isset($_POST['category']) ? trim($_POST['category']) : ($expense['category'] ?? '');
$vendor_name = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : ($expense['vendor_name'] ?? '');
$amount = isset($_POST['amount']) ? trim($_POST['amount']) : ($expense['amount'] !== null ? sprintf('%.2f', (float) $expense['amount']) : '');
$payment_mode = isset($_POST['payment_mode']) ? trim($_POST['payment_mode']) : ($expense['payment_mode'] ?? '');
$description = isset($_POST['description']) ? trim($_POST['description']) : ($expense['description'] ?? '');
$remove_receipt = isset($_POST['remove_receipt']) && $_POST['remove_receipt'] === '1';
$existing_receipt = $expense['receipt_file'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($date === '') {
        $errors[] = 'Date is required.';
    }

    if ($category === '') {
        $errors[] = 'Category is required.';
    }

    if ($vendor_name === '') {
        $errors[] = 'Vendor / Payee name is required.';
    }

    if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    if ($payment_mode === '') {
        $errors[] = 'Payment mode is required.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    $receipt_to_store = $existing_receipt;
    $new_receipt_path = null;
    $receipt_sql_fragment = '';

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
                    $receipt_to_store = 'uploads/office_expenses/' . $filename;
                    $new_receipt_path = $receipt_to_store;
                    $receipt_sql_fragment = 'receipt_file = ?';
                }
            }
        }
    } elseif ($remove_receipt && !empty($existing_receipt)) {
        $receipt_to_store = null;
        $receipt_sql_fragment = 'receipt_file = NULL';
    }

    if (empty($errors)) {
        $amount_param = sprintf('%.2f', round((float) $amount, 2));
        $fields = ['date = ?', 'category = ?', 'vendor_name = ?', 'description = ?', 'amount = ?', 'payment_mode = ?'];
        $params = [$date, $category, $vendor_name, $description, $amount_param, $payment_mode];
        $types = 'ssssss';

        if ($receipt_sql_fragment === 'receipt_file = ?' && $new_receipt_path !== null) {
            $fields[] = $receipt_sql_fragment;
            $params[] = $new_receipt_path;
            $types .= 's';
        } elseif ($receipt_sql_fragment === 'receipt_file = NULL') {
            $fields[] = $receipt_sql_fragment;
        }

        $params[] = $expense_id;
        $types .= 'i';

        $update_sql = 'UPDATE office_expenses SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $update_stmt = mysqli_prepare($conn, $update_sql);
        if ($update_stmt) {
            $bind_params = [];
            $bind_params[] = &$types;
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            if (call_user_func_array([$update_stmt, 'bind_param'], $bind_params) && mysqli_stmt_execute($update_stmt)) {
                $success = 'Expense details updated successfully.';
                if ($receipt_sql_fragment === 'receipt_file = ?' && $new_receipt_path !== null) {
                    if (!empty($existing_receipt)) {
                        $old_path = __DIR__ . '/../../' . ltrim($existing_receipt, '/');
                        if (is_file($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    $existing_receipt = $new_receipt_path;
                } elseif ($receipt_sql_fragment === 'receipt_file = NULL' && !empty($existing_receipt)) {
                    $old_path = __DIR__ . '/../../' . ltrim($existing_receipt, '/');
                    if (is_file($old_path)) {
                        @unlink($old_path);
                    }
                    $existing_receipt = null;
                }
                $expense['date'] = $date;
                $expense['category'] = $category;
                $expense['vendor_name'] = $vendor_name;
                $expense['description'] = $description;
                $expense['amount'] = $amount_param;
                $expense['payment_mode'] = $payment_mode;
                $expense['receipt_file'] = $existing_receipt;
                $remove_receipt = false;
            } else {
                $errors[] = 'Unable to save changes. Please try again.';
                if ($new_receipt_path !== null) {
                    $stored_path = __DIR__ . '/../../' . ltrim($new_receipt_path, '/');
                    if (is_file($stored_path)) {
                        @unlink($stored_path);
                    }
                }
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $errors[] = 'Failed to prepare database statement.';
            if ($new_receipt_path !== null) {
                $stored_path = __DIR__ . '/../../' . ltrim($new_receipt_path, '/');
                if (is_file($stored_path)) {
                    @unlink($stored_path);
                }
            }
        }
    } else {
        if ($new_receipt_path !== null) {
            $stored_path = __DIR__ . '/../../' . ltrim($new_receipt_path, '/');
            if (is_file($stored_path)) {
                @unlink($stored_path);
            }
        }
    }
}

closeConnection($conn);

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
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>✏️ Update Expense</h1>
                    <p>Edit entry recorded on <?php echo htmlspecialchars(date('d M Y', strtotime($expense['date'])), ENT_QUOTES); ?> by <?php echo $employee_label; ?>.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="btn" style="background:#17a2b8;" href="view.php?id=<?php echo (int) $expense['id']; ?>">👁 View</a>
                    <a class="btn btn-secondary" href="index.php">← Back to Expenses</a>
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
                    <?php if ($receipt_url) : ?>
                        <div style="margin-top:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <a href="<?php echo htmlspecialchars($receipt_url, ENT_QUOTES); ?>" target="_blank" class="btn btn-secondary">📄 View current receipt</a>
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
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
