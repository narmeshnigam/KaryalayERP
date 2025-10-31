<?php
/**
 * Employee Portal - Reimbursement Details
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../reimbursements/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = 'Reimbursement Details - ' . APP_NAME;
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
    echo '<p>The reimbursements table is missing. Please contact your administrator.</p>';
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

$claim_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($claim_id <= 0) {
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-error">Invalid reimbursement reference.</div>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

$claim = reimbursements_fetch_claim($conn, $claim_id);

closeConnection($conn);

if (!$claim || (int) $claim['employee_id'] !== (int) $employee['id']) {
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-error">Reimbursement not found.</div>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

$status_colors = [
    'Pending' => 'background:#fff3cd;color:#856404;',
    'Approved' => 'background:#d4edda;color:#155724;',
    'Rejected' => 'background:#f8d7da;color:#721c24;'
];
$badge_style = $status_colors[$claim['status']] ?? 'background:#e2e3e5;color:#41464b;';
?>

<div class="main-wrapper">
  <div class="main-content" style="max-width:820px;">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>Claim #<?php echo (int) $claim['id']; ?></h1>
          <p>Status summary and submitted details.</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to My Claims</a>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-weight:600;font-size:18px;">₹ <?php echo number_format((float)$claim['amount'], 2); ?></div>
          <div style="color:#6c757d;font-size:14px;">Expense on <?php echo htmlspecialchars(date('d M Y', strtotime($claim['expense_date']))); ?></div>
        </div>
        <div>
          <span style="padding:6px 14px;border-radius:14px;font-weight:600;<?php echo $badge_style; ?>">
            <?php echo htmlspecialchars($claim['status']); ?>
          </span>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Submission Details</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
        <div>
          <div style="font-size:13px;color:#6c757d;">Submitted On</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars(date('d M Y', strtotime($claim['date_submitted']))); ?></div>
        </div>
        <div>
          <div style="font-size:13px;color:#6c757d;">Category</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($claim['category']); ?></div>
        </div>
        <div>
          <div style="font-size:13px;color:#6c757d;">Employee Code</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($claim['employee_code']); ?></div>
        </div>
      </div>

      <div style="margin-top:18px;">
        <div style="font-size:13px;color:#6c757d;margin-bottom:6px;">Description</div>
        <div style="white-space:pre-wrap;line-height:1.6;"><?php echo nl2br(htmlspecialchars($claim['description'])); ?></div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Admin Notes</h3>
      <?php if (!empty($claim['admin_remarks'])): ?>
        <div style="white-space:pre-wrap;line-height:1.6;"><?php echo nl2br(htmlspecialchars($claim['admin_remarks'])); ?></div>
      <?php else: ?>
        <div style="color:#6c757d;">No remarks added yet.</div>
      <?php endif; ?>
      <div style="margin-top:12px;color:#6c757d;font-size:13px;">
        Last action: <?php echo $claim['action_date'] ? htmlspecialchars(date('d M Y h:i A', strtotime($claim['action_date']))) : '—'; ?>
      </div>
    </div>

    <div class="card">
      <h3 style="margin-top:0;color:#003581;">Proof Document</h3>
      <?php if (!empty($claim['proof_file'])): ?>
        <a href="<?php echo APP_URL . '/' . ltrim($claim['proof_file'], '/'); ?>" target="_blank" class="btn" style="padding:10px 22px;">View / Download</a>
      <?php else: ?>
        <div style="color:#6c757d;">No file uploaded.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
