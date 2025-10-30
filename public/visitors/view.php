<?php
/**
 * Visitor Log Module - Detail View
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/flash.php';

// Enforce permission to view visitor logs
authz_require_permission($conn, 'visitor_logs', 'view_all');

$visitor_permissions = authz_get_permission_set($conn, 'visitor_logs');

$page_title = 'Visitor Details - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

function tableExists($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

if (!tableExists($conn, 'visitor_logs')) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$visitor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($visitor_id <= 0) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Invalid visitor ID provided.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $can_edit_visitor = $IS_SUPER_ADMIN || $visitor_permissions['can_edit_all'];
    if (isset($_POST['checkout_id']) && $can_edit_visitor) {
        $checkout_id = (int) $_POST['checkout_id'];
        if ($checkout_id > 0) {
            $stmt = mysqli_prepare($conn, 'SELECT check_in_time, check_out_time FROM visitor_logs WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $checkout_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $log = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

        if ($log) {
          if (!empty($log['check_out_time'])) {
            flash_add('error', 'Visitor already checked out.', 'visitors');
                    } else {
                        $now = date('Y-m-d H:i:s');
                        if (strtotime($now) < strtotime($log['check_in_time'])) {
              flash_add('error', 'Checkout time cannot be earlier than check-in time.', 'visitors');
                        } else {
                            $update = mysqli_prepare($conn, 'UPDATE visitor_logs SET check_out_time = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                            if ($update) {
                                mysqli_stmt_bind_param($update, 'si', $now, $checkout_id);
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
    header('Location: view.php?id=' . $visitor_id);
    exit;
    }

    if (isset($_POST['delete_id']) && $user_role === 'admin') {
        $delete_id = (int) $_POST['delete_id'];
        if ($delete_id > 0) {
            $delete_stmt = mysqli_prepare($conn, 'UPDATE visitor_logs SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL');
            if ($delete_stmt) {
                mysqli_stmt_bind_param($delete_stmt, 'i', $delete_id);
        if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
          flash_add('success', 'Visitor entry archived.', 'visitors');
                } else {
          flash_add('error', 'Unable to archive visitor entry.', 'visitors');
                }
                mysqli_stmt_close($delete_stmt);
            }
        }
    header('Location: index.php');
    exit;
    }
}

$detail_sql = "SELECT vl.*, emp.employee_code AS visiting_code, emp.first_name AS visiting_first, emp.last_name AS visiting_last,
                      added.employee_code AS added_code, added.first_name AS added_first, added.last_name AS added_last
               FROM visitor_logs vl
               LEFT JOIN employees emp ON vl.employee_id = emp.id
               LEFT JOIN employees added ON vl.added_by = added.id
               WHERE vl.id = ? LIMIT 1";
$detail_stmt = mysqli_prepare($conn, $detail_sql);
mysqli_stmt_bind_param($detail_stmt, 'i', $visitor_id);
mysqli_stmt_execute($detail_stmt);
$detail_res = mysqli_stmt_get_result($detail_stmt);
$visitor = mysqli_fetch_assoc($detail_res);
mysqli_stmt_close($detail_stmt);

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}

if (!$visitor || $visitor['deleted_at'] !== null) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Visitor record not found.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

function displayEmployee($code, $first, $last)
{
    $name = trim(($first ?? '') . ' ' . ($last ?? ''));
    $label = $name !== '' ? $name : 'Employee';
    if (!empty($code)) {
        return htmlspecialchars($code . ' · ' . $label, ENT_QUOTES);
    }
    return htmlspecialchars($label, ENT_QUOTES);
}

$photo_url = null;
if (!empty($visitor['photo'])) {
    $photo_url = APP_URL . '/' . ltrim($visitor['photo'], '/');
    $photo_ext = strtolower(pathinfo($visitor['photo'], PATHINFO_EXTENSION));
}
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>Visitor Details</h1>
          <p>Review visit history, captured documents, and timestamps.</p>
        </div>
        <div style="display:flex;gap:10px;">
          <a href="index.php" class="btn btn-secondary">← Back to Visitor Log</a>
          <?php 
          $can_edit_visitor = $IS_SUPER_ADMIN || $visitor_permissions['can_edit_all'];
          if ($can_edit_visitor): ?>
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
          <div><?php echo $visitor['phone'] ? htmlspecialchars($visitor['phone'], ENT_QUOTES) : '—'; ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Purpose</div>
          <div><?php echo htmlspecialchars($visitor['purpose'], ENT_QUOTES); ?></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
        <div>
          <div style="color:#6c757d;font-size:13px;">Check-in Time</div>
          <div><?php echo date('d M Y, h:i A', strtotime($visitor['check_in_time'])); ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Check-out Time</div>
          <div>
            <?php if (!empty($visitor['check_out_time'])): ?>
              <?php echo date('d M Y, h:i A', strtotime($visitor['check_out_time'])); ?>
            <?php else: ?>
              <span style="color:#dc3545;">Not checked out</span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Duration</div>
          <div>
            <?php
              if (!empty($visitor['check_out_time'])) {
                  $seconds = strtotime($visitor['check_out_time']) - strtotime($visitor['check_in_time']);
                  if ($seconds > 0) {
                      $hours = floor($seconds / 3600);
                      $minutes = floor(($seconds % 3600) / 60);
                      echo ($hours > 0 ? $hours . 'h ' : '') . $minutes . 'm';
                  } else {
                      echo '—';
                  }
              } else {
                  echo '—';
              }
            ?>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
        <div>
          <div style="color:#6c757d;font-size:13px;">Meeting With</div>
          <div><?php echo displayEmployee($visitor['visiting_code'] ?? '', $visitor['visiting_first'] ?? '', $visitor['visiting_last'] ?? ''); ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Logged By</div>
          <div><?php echo displayEmployee($visitor['added_code'] ?? '', $visitor['added_first'] ?? '', $visitor['added_last'] ?? ''); ?></div>
        </div>
        <div>
          <div style="color:#6c757d;font-size:13px;">Created At</div>
          <div><?php echo date('d M Y, h:i A', strtotime($visitor['created_at'])); ?></div>
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
        <?php if (empty($visitor['check_out_time'])): ?>
          <form method="POST" onsubmit="return confirm('Mark this visitor as checked out?');" style="display:inline;">
            <input type="hidden" name="checkout_id" value="<?php echo (int) $visitor['id']; ?>">
            <button type="submit" class="btn" style="padding:10px 22px;background:#17a2b8;">Mark Checkout</button>
          </form>
        <?php endif; ?>
        <?php if ($user_role === 'admin'): ?>
          <form method="POST" onsubmit="return confirm('Archive this visitor entry?');" style="display:inline;">
            <input type="hidden" name="delete_id" value="<?php echo (int) $visitor['id']; ?>">
            <button type="submit" class="btn" style="padding:10px 22px;background:#dc3545;">Archive Entry</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
