<?php
/**
 * Visitor Log Module - Daily Summary Sheet
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

$closeManagedConnection = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
    }
};

if (!authz_user_can_any($conn, [
    ['table' => 'visitor_logs', 'permission' => 'view_all'],
    ['table' => 'visitor_logs', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'visitor_logs', 'view_all');
}

$visitor_permissions = authz_get_permission_set($conn, 'visitor_logs');
$can_view_all = !empty($visitor_permissions['can_view_all']);
$can_view_own = !empty($visitor_permissions['can_view_own']);

if (!($conn instanceof mysqli)) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'visitors');
if (!$prereq_check['allowed']) {
    $closeManagedConnection();
    display_prerequisite_error('visitors', $prereq_check['missing_modules']);
    exit;
}

if (!visitor_logs_table_exists($conn)) {
    $closeManagedConnection();
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$summary_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
if ($summary_date === '') {
    $summary_date = date('Y-m-d');
}

$current_employee = visitor_logs_current_employee($conn, (int) $CURRENT_USER_ID);
$restricted_employee_id = null;
if (!$can_view_all) {
    if ($can_view_own && $current_employee) {
        $restricted_employee_id = (int) $current_employee['id'];
    } else {
        $closeManagedConnection();
        authz_require_permission($conn, 'visitor_logs', 'view_all');
    }
}

$sql = "SELECT vl.visitor_name, vl.phone, vl.purpose, vl.check_in_time, vl.check_out_time,
               emp.employee_code AS visiting_code, emp.first_name AS visiting_first, emp.last_name AS visiting_last,
               added.employee_code AS added_code, added.first_name AS added_first, added.last_name AS added_last
        FROM visitor_logs vl
        LEFT JOIN employees emp ON vl.employee_id = emp.id
        LEFT JOIN employees added ON vl.added_by = added.id
        WHERE DATE(vl.check_in_time) = ? AND vl.deleted_at IS NULL";
$params = [$summary_date];
$types = 's';

if ($restricted_employee_id !== null) {
  $sql .= ' AND vl.added_by = ?';
    $params[] = $restricted_employee_id;
    $types .= 'i';
}

$sql .= ' ORDER BY vl.check_in_time ASC';

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $closeManagedConnection();
    flash_add('error', 'Unable to load visitor summary.', 'visitors');
    header('Location: index.php');
    exit;
}

visitor_logs_stmt_bind($stmt, $types, $params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($result && ($row = mysqli_fetch_assoc($result))) {
    $rows[] = $row;
}
mysqli_stmt_close($stmt);

$total_visitors = count($rows);
$checked_out = 0;
foreach ($rows as $row) {
    if (!empty($row['check_out_time'])) {
        $checked_out++;
    }
}
$pending = $total_visitors - $checked_out;

$page_title = 'Visitor Summary - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>Daily Visitor Summary</h1>
          <p>Date: <?php echo htmlspecialchars(date('d M Y', strtotime($summary_date)), ENT_QUOTES); ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <form method="GET" style="display:flex;gap:10px;align-items:center;margin:0;">
            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($summary_date, ENT_QUOTES); ?>" style="width:170px;">
            <button type="submit" class="btn summary-action" style="min-width:120px;display:inline-flex;justify-content:center;align-items:center;padding:10px 18px;">Refresh</button>
          </form>
          <button type="button" class="btn summary-action btn-secondary" onclick="window.print();" style="min-width:120px;display:inline-flex;justify-content:center;align-items:center;padding:10px 18px;">🖨 Print</button>
          <a href="index.php" class="btn summary-action btn-secondary" style="min-width:120px;display:inline-flex;justify-content:center;align-items:center;padding:10px 18px;">← Back</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="padding:20px;border-left:4px solid #003581;">
        <div style="color:#6c757d;font-size:13px;margin-bottom:4px;">Total Visitors</div>
        <div style="font-size:28px;font-weight:700;color:#003581;"><?php echo $total_visitors; ?></div>
      </div>
      <div class="card" style="padding:20px;border-left:4px solid #28a745;">
        <div style="color:#6c757d;font-size:13px;margin-bottom:4px;">Checked-out</div>
        <div style="font-size:28px;font-weight:700;color:#28a745;"><?php echo $checked_out; ?></div>
      </div>
      <div class="card" style="padding:20px;border-left:4px solid #dc3545;">
        <div style="color:#6c757d;font-size:13px;margin-bottom:4px;">Pending Checkout</div>
        <div style="font-size:28px;font-weight:700;color:#dc3545;"><?php echo $pending; ?></div>
      </div>
    </div>

    <div class="card">
      <?php if (empty($rows)): ?>
        <div class="alert alert-info" style="margin:0;">No visitor entries found for the selected date.</div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Visitor</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Purpose</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Check-in</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Check-out</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Meeting With</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Logged By</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td style="padding:12px;">
                    <div style="font-weight:600;color:#1b2a57;"><?php echo htmlspecialchars($row['visitor_name'], ENT_QUOTES); ?></div>
                    <?php if (!empty($row['phone'])): ?>
                      <div style="color:#6c757d;font-size:12px;">📞 <?php echo htmlspecialchars($row['phone'], ENT_QUOTES); ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;color:#6c757d;max-width:220px;">
                    <?php echo htmlspecialchars($row['purpose'], ENT_QUOTES); ?>
                  </td>
                  <td style="padding:12px;white-space:nowrap;">
                    <?php echo date('h:i A', strtotime($row['check_in_time'])); ?>
                  </td>
                  <td style="padding:12px;white-space:nowrap;">
                    <?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '—'; ?>
                  </td>
                  <td style="padding:12px;">
                    <?php
                      $meeting_with = trim(($row['visiting_code'] ?? '') . ' ' . ($row['visiting_first'] ?? '') . ' ' . ($row['visiting_last'] ?? ''));
                      echo $meeting_with !== '' ? htmlspecialchars($meeting_with, ENT_QUOTES) : '—';
                    ?>
                  </td>
                  <td style="padding:12px;">
                    <?php
                      $logged_by = trim(($row['added_code'] ?? '') . ' ' . ($row['added_first'] ?? '') . ' ' . ($row['added_last'] ?? ''));
                      echo $logged_by !== '' ? htmlspecialchars($logged_by, ENT_QUOTES) : '—';
                    ?>
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
