<?php
/**
 * Expense Tracker - Expense Log List
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
    header('Location: ../index.php');
    exit;
}

$page_title = 'Expense Tracker - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

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
    echo '<p>The <code>office_expenses</code> table is missing. Run the setup script to continue.</p>';
    echo '<a href="../../scripts/setup_office_expenses_table.php" class="btn" style="margin-top:20px;">🚀 Setup Expense Tracker</a>';
    echo '<a href="../index.php" class="btn btn-accent" style="margin-left:10px;margin-top:20px;">Back to dashboard</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];
    if ($delete_id > 0) {
        $file_sql = 'SELECT receipt_file FROM office_expenses WHERE id = ?';
        $file_stmt = mysqli_prepare($conn, $file_sql);
        mysqli_stmt_bind_param($file_stmt, 'i', $delete_id);
        mysqli_stmt_execute($file_stmt);
        $file_res = mysqli_stmt_get_result($file_stmt);
        $file_row = mysqli_fetch_assoc($file_res);
        mysqli_stmt_close($file_stmt);

        $delete_sql = 'DELETE FROM office_expenses WHERE id = ?';
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, 'i', $delete_id);
        if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
            $message = 'Expense entry deleted successfully.';
            if (!empty($file_row['receipt_file'])) {
                $file_path = __DIR__ . '/../../' . ltrim($file_row['receipt_file'], '/');
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        } else {
            $error = 'Unable to delete expense entry.';
        }
        mysqli_stmt_close($delete_stmt);
    }
}

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$payment_filter = isset($_GET['payment_mode']) ? trim($_GET['payment_mode']) : '';
$search_vendor = isset($_GET['vendor']) ? trim($_GET['vendor']) : '';

$where = ['e.date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($category_filter !== '') {
    $where[] = 'e.category = ?';
    $params[] = $category_filter;
    $types .= 's';
}

if ($payment_filter !== '') {
    $where[] = 'e.payment_mode = ?';
    $params[] = $payment_filter;
    $types .= 's';
}

if ($search_vendor !== '') {
    $where[] = 'e.vendor_name LIKE ?';
    $params[] = '%' . $search_vendor . '%';
    $types .= 's';
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT e.*, emp.employee_code, emp.first_name, emp.last_name
        FROM office_expenses e
        LEFT JOIN employees emp ON e.added_by = emp.id
        WHERE $where_clause
        ORDER BY e.date DESC, e.id DESC";

$stmt = mysqli_prepare($conn, $sql);
$bind_params = [];
$bind_params[] = &$types;
foreach ($params as $key => $value) {
    $bind_params[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$expenses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $expenses[] = $row;
}
mysqli_stmt_close($stmt);

$total_sql = "SELECT 
                SUM(amount) AS total_amount,
                SUM(CASE WHEN DATE_FORMAT(date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') THEN amount ELSE 0 END) AS current_month,
                COUNT(*) AS entries
             FROM office_expenses e
             WHERE $where_clause";
$total_stmt = mysqli_prepare($conn, $total_sql);
$bind_params = [];
$bind_params[] = &$types;
foreach ($params as $key => $value) {
    $bind_params[] = &$params[$key];
}
call_user_func_array([$total_stmt, 'bind_param'], $bind_params);
mysqli_stmt_execute($total_stmt);
$total_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($total_stmt));
mysqli_stmt_close($total_stmt);

$category_list = [];
$cat_sql = 'SELECT DISTINCT category FROM office_expenses ORDER BY category';
$cat_res = mysqli_query($conn, $cat_sql);
if ($cat_res) {
    while ($row = mysqli_fetch_assoc($cat_res)) {
        if (!empty($row['category'])) {
            $category_list[] = $row['category'];
        }
    }
}

$payment_modes = [];
$pm_sql = 'SELECT DISTINCT payment_mode FROM office_expenses ORDER BY payment_mode';
$pm_res = mysqli_query($conn, $pm_sql);
if ($pm_res) {
    while ($row = mysqli_fetch_assoc($pm_res)) {
        if (!empty($row['payment_mode'])) {
            $payment_modes[] = $row['payment_mode'];
        }
    }
}

closeConnection($conn);
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
          <h1>💸 Office Expenses</h1>
          <p>Track internal overheads and operational costs.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="add.php" class="btn" style="background:#28a745;">＋ Add Expense</a>
          <a href="reports.php" class="btn btn-accent">📊 Reports</a>
          <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn">📥 Export</a>
        </div>
      </div>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;">₹ <?php echo number_format((float)($total_stats['total_amount'] ?? 0), 2); ?></div>
        <div>Total (filtered)</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#0056b3 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;">₹ <?php echo number_format((float)($total_stats['current_month'] ?? 0), 2); ?></div>
        <div>This Month</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo (int)($total_stats['entries'] ?? 0); ?></div>
        <div>Entries</div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Filter Expenses</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
        <div class="form-group" style="margin:0;">
          <label>From Date</label>
          <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label>To Date</label>
          <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Category</label>
          <select name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($category_list as $category): ?>
              <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter === $category) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Payment Mode</label>
          <select name="payment_mode" class="form-control">
            <option value="">All Modes</option>
            <?php foreach ($payment_modes as $mode): ?>
              <option value="<?php echo htmlspecialchars($mode); ?>" <?php echo ($payment_filter === $mode) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mode); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Vendor</label>
          <input type="text" name="vendor" class="form-control" placeholder="Search vendor" value="<?php echo htmlspecialchars($search_vendor); ?>">
        </div>
        <div>
          <button type="submit" class="btn" style="width:100%;">Apply Filters</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;color:#003581;">Expenses (<?php echo count($expenses); ?>)</h3>
      </div>

      <?php if (count($expenses) === 0): ?>
        <div class="alert alert-info" style="margin:0;">No expenses found for the selected filters.</div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Date</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Category</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Vendor</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Description</th>
                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Amount (₹)</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Payment Mode</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Added By</th>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($expenses as $expense): ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td style="padding:12px;white-space:nowrap;"><?php echo htmlspecialchars(date('d M Y', strtotime($expense['date']))); ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($expense['category']); ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($expense['vendor_name'] ?? '—'); ?></td>
                  <td style="padding:12px;max-width:260px;"><?php echo htmlspecialchars($expense['description']); ?></td>
                  <td style="padding:12px;text-align:right;font-weight:600;color:#003581;">₹ <?php echo number_format((float)$expense['amount'], 2); ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($expense['payment_mode']); ?></td>
                  <td style="padding:12px;">
                    <?php if (!empty($expense['employee_code'])): ?>
                      <div style="font-weight:600;"><?php echo htmlspecialchars($expense['employee_code']); ?></div>
                      <div style="color:#6c757d;font-size:13px;"><?php echo htmlspecialchars($expense['first_name'] . ' ' . $expense['last_name']); ?></div>
                    <?php else: ?>
                      <span style="color:#6c757d;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;text-align:center;white-space:nowrap;">
                    <a href="view.php?id=<?php echo (int) $expense['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;">View</a>
                    <a href="edit.php?id=<?php echo (int) $expense['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;background:#17a2b8;">Edit</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this expense entry?');">
                      <input type="hidden" name="delete_id" value="<?php echo (int) $expense['id']; ?>">
                      <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:#dc3545;">Delete</button>
                    </form>
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
