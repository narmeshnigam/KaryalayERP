<?php
/**
 * Visitor Log Module - Listing & Management
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

// Check visitors module prerequisites
$prereq_check = get_prerequisite_check_result($conn, 'visitors');
if (!$prereq_check['allowed']) {
  $closeManagedConnection();
  display_prerequisite_error('visitors', $prereq_check['missing_modules']);
  exit;
}

if (!authz_user_can_any($conn, [
  ['table' => 'visitor_logs', 'permission' => 'view_all'],
  ['table' => 'visitor_logs', 'permission' => 'view_own'],
])) {
  authz_require_permission($conn, 'visitor_logs', 'view_all');
}

$visitor_permissions = authz_get_permission_set($conn, 'visitor_logs');
$can_view_all = !empty($visitor_permissions['can_view_all']);
$can_view_own = !empty($visitor_permissions['can_view_own']);
$can_edit_all = !empty($visitor_permissions['can_edit_all']);
$can_edit_own = !empty($visitor_permissions['can_edit_own']);

if (!($conn instanceof mysqli)) {
  echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
  require_once __DIR__ . '/../../includes/footer_sidebar.php';
  exit;
}

$page_title = 'Visitor Log - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (!visitor_logs_table_exists($conn)) {
  $closeManagedConnection();
  require_once __DIR__ . '/onboarding.php';
  exit;
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

$employees = visitor_logs_fetch_employees($conn);

$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : date('Y-m-d');
$employee_filter = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$visitor_filter = isset($_GET['visitor']) ? trim($_GET['visitor']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Summary statistics (matching other module dashboards)
$stats_sql = "SELECT COUNT(*) AS total, COUNT(check_out_time) AS checked_out FROM visitor_logs WHERE DATE(check_in_time) BETWEEN ? AND ? AND deleted_at IS NULL";
$stats_params = [$from_date, $to_date];
$stats_types = 'ss';
if ($restricted_employee_id !== null) {
    $stats_sql .= ' AND added_by = ?';
    $stats_params[] = $restricted_employee_id;
    $stats_types .= 'i';
}
$stats_stmt = mysqli_prepare($conn, $stats_sql);
$total_visitors = 0;
$checked_out_count = 0;
if ($stats_stmt) {
  visitor_logs_stmt_bind($stats_stmt, $stats_types, $stats_params);
  mysqli_stmt_execute($stats_stmt);
  $stats_res = mysqli_stmt_get_result($stats_stmt);
  if ($stats_row = mysqli_fetch_assoc($stats_res)) {
    $total_visitors = (int) $stats_row['total'];
    $checked_out_count = (int) $stats_row['checked_out'];
  }
  mysqli_stmt_close($stats_stmt);
}
$pending_count = $total_visitors - $checked_out_count;

$can_edit_visitor = $IS_SUPER_ADMIN || $can_edit_all || ($can_edit_own && $current_employee);
$original_query = $_SERVER['QUERY_STRING'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $redirect_target = 'index.php';
  if ($original_query !== '') {
    $redirect_target .= '?' . $original_query;
  }

  if (isset($_POST['checkout_id']) && $can_edit_visitor) {
    $checkout_id = (int) $_POST['checkout_id'];
    if ($checkout_id > 0) {
      $stmt = mysqli_prepare($conn, 'SELECT check_in_time, check_out_time, added_by FROM visitor_logs WHERE id = ? AND deleted_at IS NULL LIMIT 1');
      if ($stmt) {
        visitor_logs_stmt_bind($stmt, 'i', [$checkout_id]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $log = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($log) {
          $is_owner = $current_employee && (int) ($log['added_by'] ?? 0) === (int) ($current_employee['id'] ?? 0);
          if (!$IS_SUPER_ADMIN && !$can_edit_all && (!$can_edit_own || !$is_owner)) {
            flash_add('error', 'You do not have permission to update this visitor entry.', 'visitors');
          } elseif (!empty($log['check_out_time'])) {
            flash_add('error', 'Visitor already checked out.', 'visitors');
          } else {
            $now = date('Y-m-d H:i:s');
            if (strtotime($now) < strtotime($log['check_in_time'])) {
              flash_add('error', 'Checkout time cannot be earlier than check-in time.', 'visitors');
            } else {
              $update = mysqli_prepare($conn, 'UPDATE visitor_logs SET check_out_time = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
              if ($update) {
                visitor_logs_stmt_bind($update, 'si', [$now, $checkout_id]);
                if (mysqli_stmt_execute($update)) {
                  flash_add('success', 'Visitor checked out successfully.', 'visitors');
                } else {
                  flash_add('error', 'Unable to update checkout time.', 'visitors');
                }
                mysqli_stmt_close($update);
              }
            }
          }
        } else {
          flash_add('error', 'Visitor record not found.', 'visitors');
        }
      }
    }
    $closeManagedConnection();
    header('Location: ' . $redirect_target);
    exit;
  }

  if (isset($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];
    $can_archive = $IS_SUPER_ADMIN || $can_edit_all || ($can_edit_own && $current_employee);
    if ($delete_id > 0 && $can_archive) {
      $check_stmt = mysqli_prepare($conn, 'SELECT added_by FROM visitor_logs WHERE id = ? AND deleted_at IS NULL LIMIT 1');
      if ($check_stmt) {
        visitor_logs_stmt_bind($check_stmt, 'i', [$delete_id]);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $record = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);

        $is_owner = $current_employee && (int) ($record['added_by'] ?? 0) === (int) ($current_employee['id'] ?? 0);
        if ($record && ($IS_SUPER_ADMIN || $can_edit_all || ($can_edit_own && $is_owner))) {
          $delete_stmt = mysqli_prepare($conn, 'UPDATE visitor_logs SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL');
          if ($delete_stmt) {
            visitor_logs_stmt_bind($delete_stmt, 'i', [$delete_id]);
            if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
              flash_add('success', 'Visitor entry archived successfully.', 'visitors');
            } else {
              flash_add('error', 'Unable to archive visitor entry.', 'visitors');
            }
            mysqli_stmt_close($delete_stmt);
          }
        } else {
          flash_add('error', 'You do not have permission to archive this visitor entry.', 'visitors');
        }
      }
    }
    $closeManagedConnection();
    header('Location: ' . $redirect_target);
    exit;
  }
}

$where = ['vl.deleted_at IS NULL', 'DATE(vl.check_in_time) BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($employee_filter > 0) {
    $where[] = 'vl.employee_id = ?';
    $params[] = $employee_filter;
    $types .= 'i';
}

if ($visitor_filter !== '') {
    $where[] = 'vl.visitor_name LIKE ?';
    $params[] = '%' . $visitor_filter . '%';
    $types .= 's';
}

if ($status_filter === 'checked_in') {
  $where[] = 'vl.check_out_time IS NULL';
} elseif ($status_filter === 'checked_out') {
  $where[] = 'vl.check_out_time IS NOT NULL';
}

if ($restricted_employee_id !== null) {
  $where[] = 'vl.added_by = ?';
  $params[] = $restricted_employee_id;
  $types .= 'i';
}

$where_clause = implode(' AND ', $where);
$sql = "SELECT vl.id, vl.visitor_name, vl.phone, vl.purpose, vl.check_in_time, vl.check_out_time, vl.photo, vl.added_by,
               emp.employee_code AS visiting_code, emp.first_name AS visiting_first, emp.last_name AS visiting_last,
               added.employee_code AS added_code, added.first_name AS added_first, added.last_name AS added_last
        FROM visitor_logs vl
        LEFT JOIN employees emp ON vl.employee_id = emp.id
        LEFT JOIN employees added ON vl.added_by = added.id
        WHERE $where_clause
        ORDER BY vl.check_in_time DESC";

$stmt = mysqli_prepare($conn, $sql);
$visitor_rows = [];
if ($stmt) {
  visitor_logs_stmt_bind($stmt, $types, $params);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  while ($row = mysqli_fetch_assoc($result)) {
    $visitor_rows[] = $row;
  }
  mysqli_stmt_close($stmt);
}

$closeManagedConnection();

function formatEmployeeName($code, $first, $last)
{
    $name = trim(($first ?? '') . ' ' . ($last ?? ''));
    if ($code) {
        return htmlspecialchars($code, ENT_QUOTES) . ' · ' . htmlspecialchars($name !== '' ? $name : 'Employee', ENT_QUOTES);
    }
    return htmlspecialchars($name !== '' ? $name : 'Employee', ENT_QUOTES);
}
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>🛂 Visitor Log</h1>
          <p>Track check-ins, check-outs, and visitor purpose for compliance.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php if ($visitor_permissions['can_create']): ?>
            <a href="add.php" class="btn" style="background:#28a745;">＋ New Visitor</a>
          <?php endif; ?>
          <a href="summary.php?date=<?php echo urlencode($to_date); ?>" class="btn btn-secondary">🖨 Daily Summary</a>
          <?php if ($visitor_permissions['can_export']): ?>
            <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn">📥 Export CSV</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
        <!-- Summary cards (total / checked-out / pending) -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
          <div class="card" style="background:linear-gradient(135deg,#003581 0%,#0056b3 100%);color:#fff;text-align:center;padding:20px;">
            <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $total_visitors; ?></div>
            <div>Total Visitors</div>
          </div>
          <div class="card" style="background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:#fff;text-align:center;padding:20px;">
            <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $checked_out_count; ?></div>
            <div>Checked-out</div>
          </div>
          <div class="card" style="background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);color:#fff;text-align:center;padding:20px;">
            <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $pending_count; ?></div>
            <div>Pending Checkout</div>
          </div>
        </div>

    <?php echo flash_render(); ?>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Filter Logs</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
        <div class="form-group" style="margin:0;">
          <label for="from_date">From Date</label>
          <input type="date" id="from_date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="to_date">To Date</label>
          <input type="date" id="to_date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="employee_id">Employee</label>
          <select id="employee_id" name="employee_id" class="form-control">
            <option value="0">All employees</option>
            <?php foreach ($employees as $emp): ?>
              <?php $selected = ($employee_filter === (int) $emp['id']) ? 'selected' : ''; ?>
              <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="visitor">Visitor Name</label>
          <input type="text" id="visitor" name="visitor" class="form-control" placeholder="Search visitor" value="<?php echo htmlspecialchars($visitor_filter); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="status">Status</label>
          <select id="status" name="status" class="form-control">
            <option value="">All statuses</option>
            <option value="checked_in" <?php echo $status_filter === 'checked_in' ? 'selected' : ''; ?>>Checked-in</option>
            <option value="checked_out" <?php echo $status_filter === 'checked_out' ? 'selected' : ''; ?>>Checked-out</option>
          </select>
        </div>
        <div>
          <button type="submit" class="btn" style="width:100%;">Apply Filters</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;color:#003581;">Visitor Entries (<?php echo count($visitor_rows); ?>)</h3>
      </div>

      <?php if (empty($visitor_rows)): ?>
        <div class="alert alert-info" style="margin:0;">No visitor logs found for the selected filters.</div>
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
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Status</th>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($visitor_rows as $row): ?>
                <?php
                  $status_label = empty($row['check_out_time']) ? '<span style="padding:4px 10px;border-radius:12px;background:#ffeeba;color:#856404;font-size:12px;">Checked-in</span>' : '<span style="padding:4px 10px;border-radius:12px;background:#d4edda;color:#155724;font-size:12px;">Checked-out</span>';
                  $checkout_time = $row['check_out_time'] ? date('d M Y, h:i A', strtotime($row['check_out_time'])) : '—';
                  $duration = '—';
                  if ($row['check_out_time']) {
                      $duration_seconds = strtotime($row['check_out_time']) - strtotime($row['check_in_time']);
                      if ($duration_seconds > 0) {
                          $hours = floor($duration_seconds / 3600);
                          $minutes = floor(($duration_seconds % 3600) / 60);
                          $duration = ($hours > 0 ? $hours . 'h ' : '') . $minutes . 'm';
                      }
                  }
                  $is_owner = $current_employee && (int) ($row['added_by'] ?? 0) === (int) ($current_employee['id'] ?? 0);
                  $can_manage_row = $IS_SUPER_ADMIN || $can_edit_all || ($can_edit_own && $is_owner);
                ?>
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
                    <?php echo date('d M Y, h:i A', strtotime($row['check_in_time'])); ?>
                  </td>
                  <td style="padding:12px;white-space:nowrap;">
                    <?php echo $checkout_time; ?>
                    <?php if ($duration !== '—'): ?>
                      <div style="color:#6c757d;font-size:12px;">Stay: <?php echo htmlspecialchars($duration, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;">
                    <?php echo formatEmployeeName($row['visiting_code'] ?? '', $row['visiting_first'] ?? '', $row['visiting_last'] ?? ''); ?>
                  </td>
                  <td style="padding:12px;">
                    <?php echo formatEmployeeName($row['added_code'] ?? '', $row['added_first'] ?? '', $row['added_last'] ?? ''); ?>
                  </td>
                  <td style="padding:12px;text-align:center;"><?php echo $status_label; ?></td>
                  <td style="padding:12px;text-align:center;white-space:nowrap;">
                    <?php if ($can_manage_row): ?>
                      <a href="edit.php?id=<?php echo (int) $row['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;background:#003581;color:#fff;margin-right:6px;">Edit</a>
                    <?php endif; ?>
                    <a href="view.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;">View</a>
                    <?php if (empty($row['check_out_time']) && $can_manage_row): ?>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this visitor as checked out?');">
                        <input type="hidden" name="checkout_id" value="<?php echo (int) $row['id']; ?>">
                        <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:#17a2b8;">Checkout</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($can_manage_row): ?>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this visitor log?');">
                        <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                        <button type="submit" class="btn" style="padding:6px 14px;font-size:13px;background:#dc3545;">Archive</button>
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

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
