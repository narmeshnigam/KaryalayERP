<?php
/**
 * Visitor Log Module - Detail View
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
$can_edit_all = !empty($visitor_permissions['can_edit_all']);
$can_edit_own = !empty($visitor_permissions['can_edit_own']);

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

$visitor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($visitor_id <= 0) {
    $closeManagedConnection();
    flash_add('error', 'Invalid visitor identifier supplied.', 'visitors');
    header('Location: index.php');
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

$detail_sql = "SELECT vl.*, vl.added_by, emp.employee_code AS visiting_code, emp.first_name AS visiting_first, emp.last_name AS visiting_last,
                      added.employee_code AS added_code, added.first_name AS added_first, added.last_name AS added_last
               FROM visitor_logs vl
               LEFT JOIN employees emp ON vl.employee_id = emp.id
               LEFT JOIN employees added ON vl.added_by = added.id
               WHERE vl.id = ? AND vl.deleted_at IS NULL";
$detail_params = [$visitor_id];
$detail_types = 'i';

if ($restricted_employee_id !== null) {
    $detail_sql .= ' AND vl.added_by = ?';
    $detail_params[] = $restricted_employee_id;
    $detail_types .= 'i';
}

$detail_sql .= ' LIMIT 1';

$detail_stmt = mysqli_prepare($conn, $detail_sql);
if (!$detail_stmt) {
    $closeManagedConnection();
    flash_add('error', 'Unable to load visitor record.', 'visitors');
    header('Location: index.php');
    exit;
}

visitor_logs_stmt_bind($detail_stmt, $detail_types, $detail_params);
mysqli_stmt_execute($detail_stmt);
$detail_res = mysqli_stmt_get_result($detail_stmt);
$visitor = $detail_res ? mysqli_fetch_assoc($detail_res) : null;
mysqli_stmt_close($detail_stmt);

if (!$visitor) {
    $closeManagedConnection();
    flash_add('error', 'Visitor record not found or access denied.', 'visitors');
    header('Location: index.php');
    exit;
}

$is_owner = $current_employee && (int) ($visitor['added_by'] ?? 0) === (int) ($current_employee['id'] ?? 0);
$can_manage_entry = $IS_SUPER_ADMIN || $can_edit_all || ($can_edit_own && $is_owner);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['checkout_id']) && $can_manage_entry) {
        if (!empty($visitor['check_out_time'])) {
            flash_add('error', 'Visitor already checked out.', 'visitors');
        } else {
            $now = date('Y-m-d H:i:s');
            if (strtotime($now) < strtotime($visitor['check_in_time'])) {
                flash_add('error', 'Checkout time cannot be earlier than check-in time.', 'visitors');
            } else {
                $update_sql = 'UPDATE visitor_logs SET check_out_time = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL';
                $update_params = [$now, $visitor_id];
                $update_types = 'si';
                if (!$IS_SUPER_ADMIN && !$can_edit_all) {
                    $update_sql .= ' AND added_by = ?';
                    $update_params[] = (int) ($current_employee['id'] ?? 0);
                    $update_types .= 'i';
                }
                $update_stmt = mysqli_prepare($conn, $update_sql);
                if ($update_stmt) {
                    visitor_logs_stmt_bind($update_stmt, $update_types, $update_params);
                    if (mysqli_stmt_execute($update_stmt) && mysqli_stmt_affected_rows($update_stmt) > 0) {
                        flash_add('success', 'Visitor checked out successfully.', 'visitors');
                    } else {
                        flash_add('error', 'Unable to update checkout time.', 'visitors');
                    }
                    mysqli_stmt_close($update_stmt);
                }
            }
        }
        $closeManagedConnection();
        header('Location: view.php?id=' . $visitor_id);
        exit;
    }

    if (isset($_POST['delete_id']) && $can_manage_entry) {
        $delete_sql = 'UPDATE visitor_logs SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL';
        $delete_params = [$visitor_id];
        $delete_types = 'i';
        if (!$IS_SUPER_ADMIN && !$can_edit_all) {
            $delete_sql .= ' AND added_by = ?';
            $delete_params[] = (int) ($current_employee['id'] ?? 0);
            $delete_types .= 'i';
        }
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        if ($delete_stmt) {
            visitor_logs_stmt_bind($delete_stmt, $delete_types, $delete_params);
            if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
                flash_add('success', 'Visitor entry archived.', 'visitors');
            } else {
                flash_add('error', 'Unable to archive visitor entry.', 'visitors');
            }
            mysqli_stmt_close($delete_stmt);
        }
        $closeManagedConnection();
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['checkout_id']) || isset($_POST['delete_id'])) {
        flash_add('error', 'You do not have permission to modify this visitor entry.', 'visitors');
        $closeManagedConnection();
        header('Location: view.php?id=' . $visitor_id);
        exit;
    }
}

$page_title = 'Visitor Details - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$photo_url = null;
$photo_ext = '';
if (!empty($visitor['photo'])) {
    $photo_url = APP_URL . '/' . ltrim($visitor['photo'], '/');
    $photo_ext = strtolower(pathinfo($visitor['photo'], PATHINFO_EXTENSION));
}

$checkout_allowed = $can_manage_entry && empty($visitor['check_out_time']);
$can_archive_entry = $can_manage_entry;

$duration = '—';
if (!empty($visitor['check_in_time']) && !empty($visitor['check_out_time'])) {
    $seconds = strtotime($visitor['check_out_time']) - strtotime($visitor['check_in_time']);
    if ($seconds > 0) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $duration = ($hours > 0 ? $hours . 'h ' : '') . $minutes . 'm';
    }
}

$formatEmployee = static function (?string $code, ?string $first, ?string $last): string {
    $name = trim(($first ?? '') . ' ' . ($last ?? ''));
    $label = $name !== '' ? $name : 'Employee';
    if (!empty($code)) {
        return htmlspecialchars($code, ENT_QUOTES) . ' · ' . htmlspecialchars($label, ENT_QUOTES);
    }
    return htmlspecialchars($label, ENT_QUOTES);
};
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>Visitor Details</h1>
          <p>Review visit history, captured documents, and timestamps.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="index.php" class="btn btn-secondary">← Back to Visitor Log</a>
          <?php if ($can_manage_entry): ?>
            <a href="edit.php?id=<?php echo (int) $visitor['id']; ?>" class="btn" style="background:#003581;color:#fff;">✏️ Edit</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <div class="card" style="display:grid;gap:18px;max-width:900px;">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
        <div>
          <div style="color:#6c757d;font-size:13px;">Visitor Name</div>
          <div style="font-size:20px;font-weight:600;color:#003581;"><?php echo htmlspecialchars($visitor['visitor_name'], ENT_QUOTES); ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Phone</div>
          <div><?php echo !empty($visitor['phone']) ? htmlspecialchars($visitor['phone'], ENT_QUOTES) : '—'; ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Purpose</div>
          <div><?php echo htmlspecialchars($visitor['purpose'], ENT_QUOTES); ?></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
        <div>
          <div style="color:#6c757d;font-size:13px;">Check-in Time</div>
          <div><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($visitor['check_in_time'])), ENT_QUOTES); ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Check-out Time</div>
          <div>
            <?php if (!empty($visitor['check_out_time'])): ?>
              <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($visitor['check_out_time'])), ENT_QUOTES); ?>
            <?php else: ?>
              <span style="color:#dc3545;">Not checked out</span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Duration</div>
          <div><?php echo htmlspecialchars($duration, ENT_QUOTES); ?></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
        <div>
          <div style="color:#6c757d;font-size:13px;">Meeting With</div>
          <div><?php echo $formatEmployee($visitor['visiting_code'] ?? '', $visitor['visiting_first'] ?? '', $visitor['visiting_last'] ?? ''); ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Logged By</div>
          <div><?php echo $formatEmployee($visitor['added_code'] ?? '', $visitor['added_first'] ?? '', $visitor['added_last'] ?? ''); ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Created At</div>
          <div><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($visitor['created_at'])), ENT_QUOTES); ?></div>
        </div>
      </div>

      <?php if ($photo_url): ?>
        <div style="padding:16px;border:1px dashed #c3d0e8;border-radius:8px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <strong>Visitor Attachment</strong>
            <a href="<?php echo htmlspecialchars($photo_url, ENT_QUOTES); ?>" target="_blank" class="btn" style="padding:6px 14px;font-size:13px;background:#faa718;color:#fff;">Open in new tab</a>
          </div>
          <?php if (in_array($photo_ext, ['jpg', 'jpeg', 'png'], true)): ?>
            <img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES); ?>" alt="Visitor attachment" style="max-width:100%;border-radius:6px;">
          <?php else: ?>
            <p style="color:#6c757d;font-size:13px;">Attachment stored as <?php echo strtoupper($photo_ext); ?> document.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;">
        <?php if ($checkout_allowed): ?>
          <form method="POST" onsubmit="return confirm('Mark this visitor as checked out?');" style="display:inline;">
            <input type="hidden" name="checkout_id" value="<?php echo (int) $visitor['id']; ?>">
            <button type="submit" class="btn" style="padding:10px 22px;background:#17a2b8;">Mark Checkout</button>
          </form>
        <?php endif; ?>
        <?php if ($can_archive_entry): ?>
          <form method="POST" onsubmit="return confirm('Archive this visitor entry?');" style="display:inline;">
            <input type="hidden" name="delete_id" value="<?php echo (int) $visitor['id']; ?>">
            <button type="submit" class="btn" style="padding:10px 22px;background:#dc3545;">Archive Entry</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
