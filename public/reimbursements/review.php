<?php
/**
 * Admin - Review Reimbursement Claim
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
  ['table' => 'reimbursements', 'permission' => 'edit_all'],
  ['table' => 'reimbursements', 'permission' => 'edit_assigned'],
  ['table' => 'reimbursements', 'permission' => 'edit_own'],
])) {
  authz_require_permission($conn, 'reimbursements', 'edit_all');
}

$reimbursement_permissions = authz_get_permission_set($conn, 'reimbursements');
$can_edit_all = !empty($reimbursement_permissions['can_edit_all']);
$can_edit_own = !empty($reimbursement_permissions['can_edit_own']);
$can_edit_assigned = !empty($reimbursement_permissions['can_edit_assigned']);

$claim_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($claim_id <= 0) {
  flash_add('error', 'Invalid claim reference.', 'reimbursements');
  $closeManagedConnection();
  header('Location: index.php');
  exit;
}

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

$page_title = 'Review Reimbursement - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$current_employee_id = reimbursements_current_employee_id($conn, (int) $CURRENT_USER_ID);

$claim = reimbursements_fetch_claim($conn, $claim_id);

if (!$claim) {
  flash_add('error', 'Claim not found or may have been deleted.', 'reimbursements');
  $closeManagedConnection();
  header('Location: index.php');
  exit;
}

$is_owner = $current_employee_id && (int) $claim['employee_id'] === (int) $current_employee_id;
$has_access = $IS_SUPER_ADMIN
  || $can_edit_all
  || ($can_edit_own && $is_owner)
  || ($can_edit_assigned && $is_owner);

if (!$has_access) {
  $closeManagedConnection();
  flash_add('error', 'You do not have permission to modify this claim.', 'reimbursements');
  header('Location: index.php');
  exit;
}

$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $decision = $_POST['decision'] ?? '';
  $remarks = trim($_POST['remarks'] ?? '');
  $allowed = ['Approved', 'Rejected', 'Pending'];

  if (!in_array($decision, $allowed, true)) {
    $form_error = 'Invalid action selected.';
  } else {
    $update_sql = 'UPDATE reimbursements SET status = ?, admin_remarks = ?, action_date = NOW() WHERE id = ?';
    $stmt = mysqli_prepare($conn, $update_sql);
    if ($stmt) {
  $params = [$decision, $remarks, $claim_id];
  reimbursements_stmt_bind($stmt, 'ssi', $params);
      if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        flash_add('success', 'Claim updated successfully.', 'reimbursements');
        $closeManagedConnection();
        header('Location: review.php?id=' . $claim_id);
        exit;
      }
      $form_error = 'Failed to update claim. Please try again.';
      mysqli_stmt_close($stmt);
    } else {
      $form_error = 'Unable to prepare statement.';
    }
  }
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

    <?php echo flash_render(); ?>

    <?php if (!empty($form_error)): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($form_error); ?></div>
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

<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
