<?php
/**
 * Projects Module - Manage Members
 * Production-ready add/update-role/remove/restore for project members. Permissions ignored per instruction.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

if (!projects_tables_exist($conn)) {
    header('Location: /KaryalayERP/scripts/setup_projects_tables.php');
    exit;
}

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}

$project = get_project_by_id($conn, $project_id);
if (!$project) {
    $_SESSION['flash_message'] = 'Project not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'Contributor';
        if ($user_id <= 0) {
            $errors[] = 'Please select a user.';
        } else {
            if (add_project_member($conn, $project_id, $user_id, $role)) {
                $_SESSION['flash_message'] = 'Member added.';
                $_SESSION['flash_type'] = 'success';
                header('Location: members.php?project_id=' . $project_id);
                exit;
            } else {
                $errors[] = 'Failed to add member.';
            }
        }
    } elseif ($action === 'update_role') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'Contributor';
        if (update_project_member_role($conn, $project_id, $user_id, $role, $_SESSION['user_id'])) {
            $_SESSION['flash_message'] = 'Member role updated.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to update role.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: members.php?project_id=' . $project_id);
        exit;
    } elseif ($action === 'remove') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if (remove_project_member($conn, $project_id, $user_id, $_SESSION['user_id'])) {
            $_SESSION['flash_message'] = 'Member removed.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to remove member.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: members.php?project_id=' . $project_id);
        exit;
    } elseif ($action === 'restore') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        // Reuse add to restore
        if (add_project_member($conn, $project_id, $user_id, $_POST['role'] ?? 'Contributor')) {
            $_SESSION['flash_message'] = 'Member restored.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to restore member.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: members.php?project_id=' . $project_id);
        exit;
    }
}

$members = get_project_members($conn, $project_id);
$available_users = get_available_users_for_project($conn, $project_id);
$removed_members = get_removed_project_members($conn, $project_id);

$page_title = 'Members - ' . $project['title'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
          <h1 style="margin:0 0 8px 0;">👥 Project Members</h1>
          <div style="color:#6c757d;">Manage team for <strong><?= htmlspecialchars($project['title']) ?></strong> <span style="color:#6c757d;font-family:monospace;">#<?= htmlspecialchars($project['project_code']) ?></span></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="tasks.php?project_id=<?= $project_id ?>" class="btn btn-secondary">✅ Manage Tasks</a>
          <a href="phases.php?project_id=<?= $project_id ?>" class="btn btn-secondary">📋 Manage Phases</a>
          <a href="view.php?id=<?= $project_id ?>&tab=members" class="btn btn-secondary">← Back to Project</a>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

    <?php if (!empty($errors)): ?>
      <div style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:16px;border-radius:6px;margin-bottom:16px;">
        <strong>Fix the following:</strong>
        <ul style="margin:8px 0 0 20px;">
          <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Add Member -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">➕ Add Member</h3>
      <form method="post" style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end;">
        <input type="hidden" name="action" value="add">
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">User *</label>
          <select name="user_id" class="form-control" required>
            <option value="">Select user...</option>
            <?php foreach ($available_users as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Role</label>
          <select name="role" class="form-control">
            <?php foreach (['Owner','Contributor','Viewer'] as $r): ?>
              <option value="<?= $r ?>"><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button class="btn btn-primary">Add</button>
        </div>
      </form>
      <?php if (empty($available_users)): ?>
        <div style="color:#6c757d;font-size:13px;margin-top:8px;">All users are already members.</div>
      <?php endif; ?>
    </div>

    <!-- Members List -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">Active Members</h3>
      <?php if ($members): ?>
        <table class="table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <tr>
                <td style="font-weight:600;color:#1b2a57;"><?= htmlspecialchars($m['username']) ?></td>
                <td><?= htmlspecialchars($m['email']) ?></td>
                <td>
                  <form method="post" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                    <select name="role" class="form-control">
                      <?php foreach (['Owner','Contributor','Viewer'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($m['role']===$r)?'selected':'' ?>><?= $r ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm">Save</button>
                  </form>
                </td>
                <td><?= date('M j, Y', strtotime($m['joined_at'])) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Remove this member?');" style="display:inline-block;">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                    <button class="btn btn-danger btn-sm">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:#6c757d;">
          <div style="font-size:40px;">👥</div>
          <div style="margin-top:8px;">No active members.</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Removed Members -->
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">Removed Members</h3>
      <?php if ($removed_members): ?>
        <table class="table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role (at removal)</th>
              <th>Removed At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($removed_members as $m): ?>
              <tr>
                <td style="font-weight:600;color:#1b2a57;"><?= htmlspecialchars($m['username']) ?></td>
                <td><?= htmlspecialchars($m['email']) ?></td>
                <td><?= htmlspecialchars($m['role']) ?></td>
                <td><?= date('M j, Y g:i A', strtotime($m['removed_at'])) ?></td>
                <td>
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                    <input type="hidden" name="role" value="<?= htmlspecialchars($m['role']) ?>">
                    <button class="btn btn-success btn-sm">Restore</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="color:#6c757d;">No removed members.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); }
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
