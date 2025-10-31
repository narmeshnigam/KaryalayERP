<?php
/**
 * Expense Tracker - Expense Log List
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
  ['table' => 'office_expenses', 'permission' => 'view_assigned'],
])) {
  authz_require_permission($conn, 'office_expenses', 'view_all');
}

$expense_permissions = authz_get_permission_set($conn, 'office_expenses');
$can_view_all = !empty($expense_permissions['can_view_all']);
$can_view_own = !empty($expense_permissions['can_view_own']);
$can_view_assigned = !empty($expense_permissions['can_view_assigned']);
$can_create_expense = !empty($expense_permissions['can_create']);
$can_edit_all = !empty($expense_permissions['can_edit_all']);
$can_edit_own = !empty($expense_permissions['can_edit_own']);
$can_delete_all = !empty($expense_permissions['can_delete_all']);
$can_delete_own = !empty($expense_permissions['can_delete_own']);
$can_export_expense = !empty($expense_permissions['can_export']);

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

$page_title = 'Expense Tracker - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$current_employee_id = office_expenses_current_employee_id($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;

if (!$IS_SUPER_ADMIN && !$can_view_all) {
  if ($can_view_own && $current_employee_id) {
    $restricted_employee_id = $current_employee_id;
  } elseif ($can_view_assigned) {
    authz_require_permission($conn, 'office_expenses', 'view_all');
  } else {
    authz_require_permission($conn, 'office_expenses', 'view_all');
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $delete_id = (int) ($_POST['delete_id'] ?? 0);
  if ($delete_id <= 0) {
    flash_add('error', 'Invalid expense identifier supplied.', 'office_expenses');
  } else {
    $detail_stmt = mysqli_prepare($conn, 'SELECT id, receipt_file, added_by FROM office_expenses WHERE id = ? LIMIT 1');
    if ($detail_stmt) {
      mysqli_stmt_bind_param($detail_stmt, 'i', $delete_id);
      mysqli_stmt_execute($detail_stmt);
      $detail_res = mysqli_stmt_get_result($detail_stmt);
      $expense_row = $detail_res ? mysqli_fetch_assoc($detail_res) : null;
      mysqli_stmt_close($detail_stmt);

      if ($expense_row) {
        $is_owner = $current_employee_id && ((int) $expense_row['added_by'] === (int) $current_employee_id);
        $can_delete = $IS_SUPER_ADMIN || $can_delete_all || ($can_delete_own && $is_owner);

        if (!$can_delete) {
          flash_add('error', 'You do not have permission to delete this expense entry.', 'office_expenses');
        } else {
          $delete_stmt = mysqli_prepare($conn, 'DELETE FROM office_expenses WHERE id = ? LIMIT 1');
          if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, 'i', $delete_id);
            if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
              if (!empty($expense_row['receipt_file'])) {
                $file_path = __DIR__ . '/../../' . ltrim($expense_row['receipt_file'], '/');
                if (is_file($file_path)) {
                  @unlink($file_path);
                }
              }
              flash_add('success', 'Expense entry deleted successfully.', 'office_expenses');
            } else {
              flash_add('error', 'Unable to delete the selected expense entry.', 'office_expenses');
            }
            mysqli_stmt_close($delete_stmt);
          } else {
            flash_add('error', 'Failed to prepare delete statement.', 'office_expenses');
          }
        }
      } else {
        flash_add('error', 'Expense record not found.', 'office_expenses');
      }
    } else {
      flash_add('error', 'Unable to load expense details for deletion.', 'office_expenses');
    }
  }

  $redirect = 'index.php';
  $query = $_SERVER['QUERY_STRING'] ?? '';
  if ($query !== '') {
    $redirect .= '?' . $query;
  }
  $closeManagedConnection();
  header('Location: ' . $redirect);
  exit;
}

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$payment_filter = isset($_GET['payment_mode']) ? trim($_GET['payment_mode']) : '';
$search_vendor = isset($_GET['vendor']) ? trim($_GET['vendor']) : '';

$where = ['e.date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($restricted_employee_id !== null) {
  $where[] = 'e.added_by = ?';
  $params[] = $restricted_employee_id;
  $types .= 'i';
}

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
$expenses = [];
if ($stmt) {
  office_expenses_stmt_bind($stmt, $types, $params);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  while ($result && ($row = mysqli_fetch_assoc($result))) {
    $expenses[] = $row;
  }
  mysqli_stmt_close($stmt);
}

$total_sql = "SELECT 
        SUM(amount) AS total_amount,
        SUM(CASE WHEN DATE_FORMAT(date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') THEN amount ELSE 0 END) AS current_month,
        COUNT(*) AS entries
       FROM office_expenses e
       WHERE $where_clause";
$total_stmt = mysqli_prepare($conn, $total_sql);
$total_stats = ['total_amount' => 0, 'current_month' => 0, 'entries' => 0];
if ($total_stmt) {
  $total_params = $params;
  office_expenses_stmt_bind($total_stmt, $types, $total_params);
  mysqli_stmt_execute($total_stmt);
  $stats_result = mysqli_stmt_get_result($total_stmt);
  if ($stats_result) {
    $row = mysqli_fetch_assoc($stats_result);
    if ($row) {
      $total_stats = $row;
    }
  }
  mysqli_stmt_close($total_stmt);
}

$category_list = office_expenses_fetch_categories($conn);
if (empty($category_list)) {
  $category_list = office_expenses_default_categories();
}

$payment_modes = office_expenses_fetch_payment_modes($conn);
if (empty($payment_modes)) {
  $payment_modes = office_expenses_default_payment_modes();
}
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
          <?php if ($can_create_expense): ?>
            <a href="add.php" class="btn" style="background:#28a745;">＋ Add Expense</a>
          <?php endif; ?>
          <a href="reports.php" class="btn btn-accent">📊 Reports</a>
          <?php if ($can_export_expense): ?>
            <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn">📥 Export</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

      <?php echo flash_render(); ?>

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
                <?php
                  $is_owner = $current_employee_id && (int) ($expense['added_by'] ?? 0) === (int) $current_employee_id;
                  $can_edit_expense = $IS_SUPER_ADMIN || $can_edit_all || ($can_edit_own && $is_owner);
                  $can_delete_expense = $IS_SUPER_ADMIN || $can_delete_all || ($can_delete_own && $is_owner);
                ?>
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
                    <?php if ($can_edit_expense): ?>
                      <a href="edit.php?id=<?php echo (int) $expense['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;background:#17a2b8;">Edit</a>
                    <?php endif; ?>
                    <?php if ($can_delete_expense): ?>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this expense entry?');">
                        <input type="hidden" name="delete_id" value="<?php echo (int) $expense['id']; ?>">
                        <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:#dc3545;">Delete</button>
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

    <?php $closeManagedConnection(); ?>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
