<?php
/**
 * Projects Module - Manage Phases
 * Production-ready CRUD + reorder for project phases. Permissions ignored per instruction.
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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'status' => $_POST['status'] ?? 'Active',
        ];
        $res = create_phase($conn, $project_id, $data, $_SESSION['user_id']);
        if ($res['ok']) {
            $_SESSION['flash_message'] = 'Phase added successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: phases.php?project_id=' . $project_id);
            exit;
        } else {
            $errors = $res['errors'];
        }
    } elseif ($action === 'update') {
        $phase_id = (int)($_POST['phase_id'] ?? 0);
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'status' => $_POST['status'] ?? 'Active',
        ];
        $res = update_phase($conn, $phase_id, $data, $_SESSION['user_id']);
        if (!empty($res['ok'])) {
            $_SESSION['flash_message'] = 'Phase updated successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: phases.php?project_id=' . $project_id);
            exit;
        } else {
            $errors = $res['errors'] ?? ['Failed to update phase.'];
        }
    } elseif ($action === 'delete') {
        $phase_id = (int)($_POST['phase_id'] ?? 0);
        if (delete_phase($conn, $phase_id, $_SESSION['user_id'])) {
            $_SESSION['flash_message'] = 'Phase deleted.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete phase.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: phases.php?project_id=' . $project_id);
        exit;
    } elseif ($action === 'move_up' || $action === 'move_down') {
        $phase_id = (int)($_POST['phase_id'] ?? 0);
        $dir = $action === 'move_up' ? 'up' : 'down';
        if (move_phase($conn, $project_id, $phase_id, $dir)) {
            $_SESSION['flash_message'] = 'Phase order updated.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Unable to move phase.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: phases.php?project_id=' . $project_id);
        exit;
    }
}

$phases = get_phases_with_stats($conn, $project_id);

$page_title = 'Phases - ' . $project['title'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
          <h1 style="margin:0 0 8px 0;">📋 Project Phases</h1>
          <div style="color:#6c757d;">Manage phases for <strong><?= htmlspecialchars($project['title']) ?></strong> <span style="color:#6c757d;font-family:monospace;">#<?= htmlspecialchars($project['project_code']) ?></span></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="view.php?id=<?= $project_id ?>&tab=phases" class="btn btn-secondary">← Back to Project</a>
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

    <!-- Add Phase -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">➕ Add Phase</h3>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;">
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Title *</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g., Planning">
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Start Date</label>
            <input type="date" name="start_date" class="form-control">
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">End Date</label>
            <input type="date" name="end_date" class="form-control">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-top:12px;">
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Description</label>
            <textarea name="description" rows="2" class="form-control" placeholder="Short description..."></textarea>
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Status</label>
            <select name="status" class="form-control">
              <option value="Pending">� Pending</option>
              <option value="In Progress">� In Progress</option>
              <option value="On Hold">⏸️ On Hold</option>
              <option value="Completed">✅ Completed</option>
            </select>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
          <button class="btn btn-primary">Add Phase</button>
        </div>
      </form>
    </div>

    <!-- Phases List -->
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">Phases</h3>
      <?php if ($phases): ?>
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Phase</th>
              <th>Dates</th>
              <th>Tasks</th>
              <th>Progress</th>
              <th>Status</th>
              <th>Order</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($phases as $p): ?>
              <tr>
                <td><?= (int)$p['sequence_order'] ?></td>
                <td>
                  <div style="font-weight:600;color:#1b2a57;"><?= htmlspecialchars($p['title']) ?></div>
                  <?php if (!empty($p['description'])): ?>
                    <div style="font-size:13px;color:#6c757d;"><?= htmlspecialchars($p['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                  <?= $p['start_date'] ? date('M j, Y', strtotime($p['start_date'])) : '-' ?>
                  → <?= $p['end_date'] ? date('M j, Y', strtotime($p['end_date'])) : '-' ?>
                </td>
                <td>
                  <?= (int)$p['tasks_completed'] ?>/<?= (int)$p['tasks_total'] ?><?php if ((int)$p['tasks_overdue']>0): ?>
                    <span class="badge" style="background:#dc3545;"><?= (int)$p['tasks_overdue'] ?> overdue</span>
                  <?php endif; ?>
                </td>
                <td style="min-width:160px;">
                  <div style="background:#e9ecef;height:16px;border-radius:8px;overflow:hidden;">
                    <div style="height:100%;width:<?= (float)$p['progress'] ?>%;background:linear-gradient(90deg,#003581,#0056b3);"></div>
                  </div>
                  <div style="font-size:12px;color:#6c757d;margin-top:4px;"><?= number_format((float)$p['progress'],0) ?>%</div>
                </td>
                <td>
                  <span class="badge" style="background:<?= $p['status']==='Completed'?'#28a745':($p['status']==='In Progress'?'#003581':'#6c757d') ?>;">
                    <?= htmlspecialchars($p['status']) ?>
                  </span>
                </td>
                <td>
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="move_up">
                    <input type="hidden" name="phase_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-secondary" title="Move Up">▲</button>
                  </form>
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="move_down">
                    <input type="hidden" name="phase_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-secondary" title="Move Down">▼</button>
                  </form>
                </td>
                <td>
                  <!-- Edit inline collapsible -->
                  <details>
                    <summary class="btn btn-sm btn-primary" style="display:inline-block;">Edit</summary>
                    <div style="background:#f8f9fa;border:1px solid #e9ecef;padding:12px;border-radius:6px;margin-top:8px;">
                      <form method="post" style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="phase_id" value="<?= (int)$p['id'] ?>">
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Title *</label>
                          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($p['title']) ?>">
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Start Date</label>
                          <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($p['start_date']) ?>">
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">End Date</label>
                          <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($p['end_date']) ?>">
                        </div>
                        <div style="grid-column:1 / span 2;">
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Description</label>
                          <textarea name="description" rows="2" class="form-control"><?= htmlspecialchars($p['description']) ?></textarea>
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Status</label>
                          <select name="status" class="form-control">
                            <option value="Pending" <?= $p['status']==='Pending'?'selected':'' ?>>� Pending</option>
                            <option value="In Progress" <?= $p['status']==='In Progress'?'selected':'' ?>>� In Progress</option>
                            <option value="On Hold" <?= $p['status']==='On Hold'?'selected':'' ?>>⏸️ On Hold</option>
                            <option value="Completed" <?= $p['status']==='Completed'?'selected':'' ?>>✅ Completed</option>
                          </select>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;grid-column:1 / -1;justify-content:flex-end;">
                          <button class="btn btn-primary">Save</button>
                        </div>
                      </form>
                    </div>
                  </details>
                  <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('Delete this phase? Tasks will remain but unlinked.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="phase_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:#6c757d;">
          <div style="font-size:40px;">📋</div>
          <div style="margin-top:8px;">No phases yet. Add your first phase above.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); }
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
