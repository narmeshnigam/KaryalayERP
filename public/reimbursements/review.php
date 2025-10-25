<?php
/**
 * Admin - Review Reimbursement Claim
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

$claim_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($claim_id <= 0) {
    header('Location: index.php');
    exit;
}

$page_title = 'Review Reimbursement - ' . APP_NAME;
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
    echo '<div class="alert alert-error">Reimbursement module is not set up.</div>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = $_POST['decision'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $allowed = ['Approved', 'Rejected', 'Pending'];

    if (!in_array($decision, $allowed, true)) {
        $error = 'Invalid action selected.';
    } else {
        $update_sql = 'UPDATE reimbursements SET status = ?, admin_remarks = ?, action_date = NOW() WHERE id = ?';
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'ssi', $decision, $remarks, $claim_id);
        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) >= 0) {
            $message = 'Claim updated successfully.';
        } else {
            $error = 'Failed to update claim. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}

$detail_sql = 'SELECT r.*, e.employee_code, e.first_name, e.last_name, e.department, e.designation FROM reimbursements r INNER JOIN employees e ON r.employee_id = e.id WHERE r.id = ? LIMIT 1';
$detail_stmt = mysqli_prepare($conn, $detail_sql);
mysqli_stmt_bind_param($detail_stmt, 'i', $claim_id);
mysqli_stmt_execute($detail_stmt);
$result = mysqli_stmt_get_result($detail_stmt);
$claim = mysqli_fetch_assoc($result);
mysqli_stmt_close($detail_stmt);

closeConnection($conn);

if (!$claim) {
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-error">Claim not found.</div>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
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
  <div class="main-content" style="max-width:900px;">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>Review Claim #<?php echo (int) $claim['id']; ?></h1>
          <p>Inspect receipt and decide the appropriate action.</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to List</a>
        </div>
      </div>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-weight:600;font-size:20px;">₹ <?php echo number_format((float)$claim['amount'], 2); ?></div>
          <div style="color:#6c757d;">Expense date: <?php echo htmlspecialchars(date('d M Y', strtotime($claim['expense_date']))); ?></div>
        </div>
        <div>
          <span style="padding:6px 14px;border-radius:14px;font-weight:600;<?php echo $badge_style; ?>">
            <?php echo htmlspecialchars($claim['status']); ?>
          </span>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Employee Details</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
        <div>
          <div style="font-size:13px;color:#6c757d;">Employee</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($claim['employee_code'] . ' - ' . $claim['first_name'] . ' ' . $claim['last_name']); ?></div>
        </div>
        <div>
          <div style="font-size:13px;color:#6c757d;">Department</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($claim['department'] ?? '—'); ?></div>
        </div>
        <div>
          <div style="font-size:13px;color:#6c757d;">Designation</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($claim['designation'] ?? '—'); ?></div>
        </div>
        <div>
          <div style="font-size:13px;color:#6c757d;">Submitted On</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars(date('d M Y', strtotime($claim['date_submitted']))); ?></div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Expense Description</h3>
      <div style="font-size:13px;color:#6c757d;margin-bottom:6px;">Category</div>
      <div style="font-weight:600;margin-bottom:16px;"><?php echo htmlspecialchars($claim['category']); ?></div>
      <div style="white-space:pre-wrap;line-height:1.6;"><?php echo nl2br(htmlspecialchars($claim['description'])); ?></div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Proof Document</h3>
      <?php if (!empty($claim['proof_file'])): ?>
        <a href="<?php echo APP_URL . '/' . ltrim($claim['proof_file'], '/'); ?>" target="_blank" class="btn" style="padding:10px 22px;">View / Download Proof</a>
      <?php else: ?>
        <div style="color:#6c757d;">No proof uploaded.</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 style="margin-top:0;color:#003581;">Take Action</h3>
      <form method="POST">
        <div class="form-group">
          <label for="remarks">Remarks (optional)</label>
          <textarea id="remarks" name="remarks" class="form-control" rows="4" placeholder="Add notes for the employee..."><?php echo htmlspecialchars($claim['admin_remarks'] ?? ''); ?></textarea>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <button type="submit" name="decision" value="Approved" class="btn" style="background:#28a745;">✓ Approve</button>
          <button type="submit" name="decision" value="Pending" class="btn" style="background:#17a2b8;">⏳ Mark Pending</button>
          <button type="submit" name="decision" value="Rejected" class="btn" style="background:#dc3545;" onclick="return confirm('Reject this claim?');">✗ Reject</button>
        </div>
      </form>
      <div style="margin-top:16px;color:#6c757d;font-size:13px;">
        Last action: <?php echo $claim['action_date'] ? htmlspecialchars(date('d M Y h:i A', strtotime($claim['action_date']))) : '—'; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
