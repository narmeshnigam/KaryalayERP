<?php
/**
 * Employee Portal - Submit New Reimbursement
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../reimbursements/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = 'Submit Reimbursement - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-error">Unable to connect to the database. Please try again later.</div>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

if (!reimbursements_table_exists($conn)) {
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="card" style="max-width:720px;margin:0 auto;">';
    echo '<h2 style="margin-top:0;color:#003581;">Reimbursement module not ready</h2>';
    echo '<p>The reimbursements table is missing. Please contact your administrator to run the module setup.</p>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

$user_id = $_SESSION['user_id'];
$emp_stmt = mysqli_prepare($conn, 'SELECT e.* FROM employees e WHERE e.user_id = ?');
mysqli_stmt_bind_param($emp_stmt, 'i', $user_id);
mysqli_stmt_execute($emp_stmt);
$emp_result = mysqli_stmt_get_result($emp_stmt);
$employee = mysqli_fetch_assoc($emp_result);
mysqli_stmt_close($emp_stmt);

if (!$employee) {
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-error">No employee record found for your account. Please contact HR.</div>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

$categories = reimbursements_fetch_categories($conn);
if (empty($categories)) {
  $categories = ['Travel', 'Food', 'Internet', 'Accommodation', 'Supplies', 'Other'];
}
$errors = [];
$success = '';

$expense_date = date('Y-m-d');
$category = $categories[0] ?? 'Travel';
$amount = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date = $_POST['expense_date'] ?? '';
    $category = $_POST['category'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (empty($expense_date)) {
        $errors[] = 'Expense date is required.';
    }

    if (!in_array($category, $categories, true)) {
        $errors[] = 'Please choose a valid category.';
    }

    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }

    if (empty($description)) {
        $errors[] = 'Description is required.';
    }

    $proof_path = '';
    $file_error = $_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($file_error === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Proof document is required.';
    } else {
        $stored_path = reimbursements_store_proof_file($_FILES['proof'], $errors);
        if ($stored_path !== null) {
            $proof_path = $stored_path;
        }
    }

    if (empty($errors)) {
        $insert_sql = 'INSERT INTO reimbursements (employee_id, date_submitted, expense_date, category, amount, description, status, proof_file) VALUES (?, ?, ?, ?, ?, ?, "Pending", ?)';
        $stmt = mysqli_prepare($conn, $insert_sql);
        $today = date('Y-m-d');
        $amount_value = round((float)$amount, 2);
        mysqli_stmt_bind_param($stmt, 'isssdss', $employee['id'], $today, $expense_date, $category, $amount_value, $description, $proof_path);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Expense claim submitted successfully!';
            $expense_date = date('Y-m-d');
            $category = $categories[0] ?? 'Travel';
            $amount = '';
            $description = '';
        } else {
            $errors[] = 'Unable to save the claim. Please try again.';
            reimbursements_delete_proof_file($proof_path);
        }
        mysqli_stmt_close($stmt);
    } else {
        reimbursements_delete_proof_file($proof_path);
    }
}

closeConnection($conn);
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>🧾 Submit Reimbursement</h1>
          <p>Fill in the details below to create a new claim.</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to My Claims</a>
        </div>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <div>
          <?php foreach ($errors as $error): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <div class="card" style="max-width:760px;">
      <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
          <div class="form-group" style="margin:0;">
            <label for="expense_date">Expense Date</label>
            <input type="date" id="expense_date" name="expense_date" class="form-control" value="<?php echo htmlspecialchars($expense_date); ?>" required>
          </div>
          <div class="form-group" style="margin:0;">
            <label for="category">Category</label>
            <select id="category" name="category" class="form-control" required>
              <?php foreach ($categories as $option): ?>
                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($option === $category) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label for="amount">Amount (₹)</label>
            <input type="number" id="amount" name="amount" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($amount); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" class="form-control" rows="4" placeholder="Provide details about the expense" required><?php echo htmlspecialchars($description); ?></textarea>
        </div>

        <div class="form-group">
          <label for="proof">Upload Proof (PDF, JPG, PNG, max 2 MB)</label>
          <input type="file" id="proof" name="proof" class="form-control" accept="application/pdf,image/jpeg,image/png" required>
        </div>

        <button type="submit" class="btn">Submit Claim</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
