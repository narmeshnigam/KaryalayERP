<?php
/**
 * Admin - Reimbursements Overview
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

$closeManagedConnection = static function () use (&$conn): void {
  if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
    closeConnection($conn);
    $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
  }
};

if (!authz_user_can_any($conn, [
  ['table' => 'reimbursements', 'permission' => 'view_all'],
  ['table' => 'reimbursements', 'permission' => 'view_assigned'],
  ['table' => 'reimbursements', 'permission' => 'view_own'],
])) {
  authz_require_permission($conn, 'reimbursements', 'view_all');
}

$reimbursement_permissions = authz_get_permission_set($conn, 'reimbursements');
$can_view_all = !empty($reimbursement_permissions['can_view_all']);
$can_view_own = !empty($reimbursement_permissions['can_view_own']);
$can_view_assigned = !empty($reimbursement_permissions['can_view_assigned']);
$can_review_all = !empty($reimbursement_permissions['can_edit_all']);
$can_review_own = !empty($reimbursement_permissions['can_edit_own']);
$can_review_assigned = !empty($reimbursement_permissions['can_edit_assigned']);
$can_export = !empty($reimbursement_permissions['can_export']);

if (!($conn instanceof mysqli)) {
  echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
  require_once __DIR__ . '/../../includes/footer_sidebar.php';
  exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'reimbursements');
if (!$prereq_check['allowed']) {
  $closeManagedConnection();
  display_prerequisite_error('reimbursements', $prereq_check['missing_modules']);
  exit;
}

if (!reimbursements_table_exists($conn)) {
  $closeManagedConnection();
  require_once __DIR__ . '/onboarding.php';
  exit;
}

$page_title = 'Reimbursements - ' . APP_NAME;

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$current_employee_id = reimbursements_current_employee_id($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;

if (!$can_view_all) {
  if ($can_view_own && $current_employee_id) {
    $restricted_employee_id = $current_employee_id;
  } elseif ($can_view_assigned) {
    // No assignment model yet; fall back to requiring elevated permission.
    authz_require_permission($conn, 'reimbursements', 'view_all');
  } else {
    authz_require_permission($conn, 'reimbursements', 'view_all');
  }
}

$allowed_statuses = reimbursements_allowed_statuses();
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
if (!in_array($status_filter, $allowed_statuses, true)) {
  $status_filter = 'All';
}

$employee_filter = isset($_GET['employee']) ? (int) $_GET['employee'] : 0;
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

if ($restricted_employee_id !== null) {
  $employee_filter = $restricted_employee_id;
}

$where = ['r.expense_date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($restricted_employee_id !== null) {
  $where[] = 'r.employee_id = ?';
  $params[] = $restricted_employee_id;
  $types .= 'i';
}

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
$claims = [];
if ($stmt) {
  if ($types !== '') {
    reimbursements_stmt_bind($stmt, $types, $params);
  }
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  while ($row = mysqli_fetch_assoc($result)) {
    $claims[] = $row;
  }
  mysqli_stmt_close($stmt);
}

$employees = reimbursements_fetch_employees($conn);
if ($restricted_employee_id !== null) {
  $employees = array_values(array_filter($employees, static function (array $row) use ($restricted_employee_id) {
    return (int) ($row['id'] ?? 0) === $restricted_employee_id;
  }));
}

$categories = reimbursements_fetch_categories($conn);

$stats_sql = "SELECT status, COUNT(*) as total FROM reimbursements WHERE expense_date BETWEEN ? AND ? GROUP BY status";
$stats_params = [$from_date, $to_date];
$stats_types = 'ss';

if ($restricted_employee_id !== null) {
  $stats_sql = "SELECT status, COUNT(*) as total FROM reimbursements WHERE expense_date BETWEEN ? AND ? AND employee_id = ? GROUP BY status";
  $stats_params[] = $restricted_employee_id;
  $stats_types .= 'i';
}

$stats_stmt = mysqli_prepare($conn, $stats_sql);
$stats = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
if ($stats_stmt) {
  reimbursements_stmt_bind($stats_stmt, $stats_types, $stats_params);
  mysqli_stmt_execute($stats_stmt);
  $stats_res = mysqli_stmt_get_result($stats_stmt);
  while ($row = mysqli_fetch_assoc($stats_res)) {
    $stats[$row['status']] = (int) $row['total'];
  }
  mysqli_stmt_close($stats_stmt);
}
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
          <?php if ($can_export): ?>
            <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn">📥 Export CSV</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

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
            <?php foreach ($allowed_statuses as $option): ?>
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
                <?php
                  $is_owner = $current_employee_id && (int) ($claim['employee_id'] ?? 0) === (int) $current_employee_id;
                  $can_review_claim = $IS_SUPER_ADMIN
                    || $can_review_all
                    || ($can_review_own && $is_owner)
                    || ($can_review_assigned && $is_owner);
                ?>
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
                    <?php if ($can_review_claim): ?>
                      <a href="review.php?id=<?php echo (int) $claim['id']; ?>" class="btn btn-accent" style="padding:6px 16px;font-size:13px;">Review</a>
                    <?php else: ?>
                      <span style="color:#6c757d;font-size:13px;">No access</span>
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

<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
