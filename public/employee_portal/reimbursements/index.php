<?php
/**
 * Employee Portal - My Reimbursements
 * View and filter personal reimbursement claims
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../reimbursements/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = 'My Reimbursements - ' . APP_NAME;
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
    echo '<a href="../../index.php" class="btn" style="margin-top:20px;">← Back to dashboard</a>';
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

$employee_id = (int) $employee['id'];

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$where = ['employee_id = ?'];
$params = [$employee_id, $from_date, $to_date];
$types = 'iss';
$where[] = 'expense_date BETWEEN ? AND ?';

if ($status_filter !== 'All') {
    $where[] = 'status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = implode(' AND ', $where);
$query = "SELECT * FROM reimbursements WHERE $where_clause ORDER BY expense_date DESC, id DESC";
$stmt = mysqli_prepare($conn, $query);

reimbursements_stmt_bind($stmt, $types, $params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$claims = [];
while ($row = mysqli_fetch_assoc($result)) {
    $claims[] = $row;
}
mysqli_stmt_close($stmt);

$stats_sql = "SELECT status, COUNT(*) as total FROM reimbursements WHERE employee_id = ? GROUP BY status";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, 'i', $employee_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
while ($row = mysqli_fetch_assoc($stats_result)) {
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
          <h1>💼 My Reimbursements</h1>
          <p>Submit expense claims and track their status.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="add.php" class="btn" style="background:#28a745;">＋ New Claim</a>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:#fff;text-align:center;">
        <div style="font-size:34px;font-weight:700;margin-bottom:6px;"><?php echo $stats['Pending']; ?></div>
        <div>Pending</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:#fff;text-align:center;">
        <div style="font-size:34px;font-weight:700;margin-bottom:6px;"><?php echo $stats['Approved']; ?></div>
        <div>Approved</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);color:#fff;text-align:center;">
        <div style="font-size:34px;font-weight:700;margin-bottom:6px;"><?php echo $stats['Rejected']; ?></div>
        <div>Rejected</div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Filter Claims</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
        <div class="form-group" style="margin:0;">
          <label>Status</label>
          <select name="status" class="form-control">
            <?php
            $statuses = ['All', 'Pending', 'Approved', 'Rejected'];
            foreach ($statuses as $status_option) {
                $selected = ($status_filter === $status_option) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($status_option) . '" ' . $selected . '>' . htmlspecialchars($status_option) . '</option>';
            }
            ?>
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
        <div class="alert alert-info" style="margin:0;">No claims found for the selected filters.</div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
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
                  <td style="padding:12px;white-space:nowrap;"><?php echo htmlspecialchars(date('d M Y', strtotime($claim['expense_date']))); ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($claim['category']); ?></td>
                  <td style="padding:12px;text-align:right;font-weight:600;color:#003581;"><?php echo number_format((float)$claim['amount'], 2); ?></td>
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
                  <td style="padding:12px;text-align:center;">
                    <a href="view.php?id=<?php echo (int) $claim['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;">Details</a>
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

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
