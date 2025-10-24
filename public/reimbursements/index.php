<?php
/**
 * Admin - Reimbursements Overview
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

$page_title = 'Reimbursements - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-error">Unable to connect to the database.</div>';
    echo '</div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

function tableExists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

if (!tableExists($conn, 'reimbursements')) {
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="card" style="max-width:760px;margin:0 auto;">';
    echo '<h2 style="margin-top:0;color:#003581;">Reimbursement module not set up</h2>';
    echo '<p>Run the setup script to create the reimbursements table.</p>';
    echo '<a href="../../scripts/setup_reimbursements_table.php" class="btn" style="margin-top:20px;">🚀 Setup Reimbursements Module</a>';
    echo '<a href="../dashboard.php" class="btn btn-accent" style="margin-left:10px;margin-top:20px;">Back to dashboard</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$employee_filter = isset($_GET['employee']) ? (int) $_GET['employee'] : 0;
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$where = ['r.expense_date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($status_filter !== 'All') {
    $where[] = 'r.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($employee_filter > 0) {
    $where[] = 'r.employee_id = ?';
    $params[] = $employee_filter;
    $types .= 'i';
}

if ($category_filter !== '') {
    $where[] = 'r.category = ?';
    $params[] = $category_filter;
    $types .= 's';
}

$where_clause = implode(' AND ', $where);
$sql = "SELECT r.*, e.employee_code, e.first_name, e.last_name, e.department FROM reimbursements r INNER JOIN employees e ON r.employee_id = e.id WHERE $where_clause ORDER BY r.date_submitted DESC, r.id DESC";
$stmt = mysqli_prepare($conn, $sql);

$bind_params = [];
$bind_params[] = &$types;
foreach ($params as $key => $value) {
  $bind_params[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$claims = [];
while ($row = mysqli_fetch_assoc($result)) {
    $claims[] = $row;
}
mysqli_stmt_close($stmt);

$employees = [];
$emp_sql = "SELECT id, employee_code, first_name, last_name FROM employees ORDER BY first_name, last_name";
$emp_res = mysqli_query($conn, $emp_sql);
if ($emp_res) {
    while ($row = mysqli_fetch_assoc($emp_res)) {
        $employees[] = $row;
    }
}

$categories = [];
$cat_sql = "SELECT DISTINCT category FROM reimbursements ORDER BY category";
$cat_res = mysqli_query($conn, $cat_sql);
if ($cat_res) {
    while ($row = mysqli_fetch_assoc($cat_res)) {
        if (!empty($row['category'])) {
            $categories[] = $row['category'];
        }
    }
}

$stats_sql = "SELECT status, COUNT(*) as total FROM reimbursements WHERE expense_date BETWEEN ? AND ? GROUP BY status";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, 'ss', $from_date, $to_date);
mysqli_stmt_execute($stats_stmt);
$stats_res = mysqli_stmt_get_result($stats_stmt);
$stats = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
while ($row = mysqli_fetch_assoc($stats_res)) {
    $stats[$row['status']] = (int) $row['total'];
}
mysqli_stmt_close($stats_stmt);

closeConnection($conn);
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
          <h1>🧾 Reimbursement Claims</h1>
          <p>Review, approve, or reject employee reimbursement requests.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn">📥 Export CSV</a>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $stats['Pending']; ?></div>
        <div>Pending</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $stats['Approved']; ?></div>
        <div>Approved</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $stats['Rejected']; ?></div>
        <div>Rejected</div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Filter Claims</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
        <div class="form-group" style="margin:0;">
          <label>Status</label>
          <select name="status" class="form-control">
            <?php foreach (['All','Pending','Approved','Rejected'] as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($status_filter === $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Employee</label>
          <select name="employee" class="form-control">
            <option value="0">All Employees</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo (int) $emp['id']; ?>" <?php echo ($employee_filter === (int) $emp['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Category</label>
          <select name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter === $category) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>From Date</label>
          <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label>To Date</label>
          <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <div>
          <button type="submit" class="btn" style="width:100%;">Apply</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;color:#003581;">Claims (<?php echo count($claims); ?>)</h3>
      </div>

      <?php if (count($claims) === 0): ?>
        <div class="alert alert-info" style="margin:0;">No claims found with the selected filters.</div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">ID</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Employee</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Department</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Expense Date</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Category</th>
                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Amount (₹)</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Status</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Submitted</th>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Proof</th>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($claims as $claim): ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td style="padding:12px;">#<?php echo (int) $claim['id']; ?></td>
                  <td style="padding:12px;">
                    <div style="font-weight:600;"><?php echo htmlspecialchars($claim['employee_code']); ?></div>
                    <div style="color:#6c757d;font-size:13px;"><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></div>
                  </td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($claim['department'] ?? '—'); ?></td>
                  <td style="padding:12px;white-space:nowrap;"><?php echo htmlspecialchars(date('d M Y', strtotime($claim['expense_date']))); ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($claim['category']); ?></td>
                  <td style="padding:12px;text-align:right;font-weight:600;color:#003581;">₹ <?php echo number_format((float)$claim['amount'], 2); ?></td>
                  <td style="padding:12px;">
                    <?php
                    $status_colors = [
                        'Pending' => 'background:#fff3cd;color:#856404;',
                        'Approved' => 'background:#d4edda;color:#155724;',
                        'Rejected' => 'background:#f8d7da;color:#721c24;'
                    ];
                    $badge_style = $status_colors[$claim['status']] ?? 'background:#e2e3e5;color:#41464b;';
                    ?>
                    <span style="padding:4px 10px;border-radius:12px;font-size:13px;font-weight:600;display:inline-block;<?php echo $badge_style; ?>">
                      <?php echo htmlspecialchars($claim['status']); ?>
                    </span>
                  </td>
                  <td style="padding:12px;white-space:nowrap;"><?php echo htmlspecialchars(date('d M Y', strtotime($claim['date_submitted']))); ?></td>
                  <td style="padding:12px;text-align:center;">
                    <?php if (!empty($claim['proof_file'])): ?>
                      <a href="<?php echo APP_URL . '/' . ltrim($claim['proof_file'], '/'); ?>" target="_blank" class="btn" style="padding:6px 14px;font-size:13px;background:#17a2b8;">View</a>
                    <?php else: ?>
                      <span style="color:#6c757d;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;text-align:center;white-space:nowrap;">
                    <a href="review.php?id=<?php echo (int) $claim['id']; ?>" class="btn btn-accent" style="padding:6px 16px;font-size:13px;">Review</a>
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
