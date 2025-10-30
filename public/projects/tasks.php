<?php
/**
 * Projects Module - Manage Tasks
 * Production-ready CRUD + assign + status for project tasks. Permissions ignored per instruction.
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

// Load reference data
$phases_stmt = $conn->prepare("SELECT id, title FROM project_phases WHERE project_id = ? ORDER BY sequence_order ASC, id ASC");
$phases_stmt->bind_param("i", $project_id);
$phases_stmt->execute();
$phases = $phases_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$phases_stmt->close();

$members = get_project_members($conn, $project_id);

$errors = [];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'phase_id' => !empty($_POST['phase_id']) ? (int)$_POST['phase_id'] : null,
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'priority' => $_POST['priority'] ?? 'Medium',
            'status' => $_POST['status'] ?? 'Pending',
            'assignees' => isset($_POST['assignees']) && is_array($_POST['assignees']) ? array_map('intval', $_POST['assignees']) : []
        ];
        $res = create_task($conn, $project_id, $data, $_SESSION['user_id']);
        if (!empty($res['ok'])) {
            $_SESSION['flash_message'] = 'Task added successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: tasks.php?project_id=' . $project_id);
            exit;
        } else {
            $errors = $res['errors'] ?? ['Failed to add task.'];
        }
    } elseif ($action === 'update') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'phase_id' => !empty($_POST['phase_id']) ? (int)$_POST['phase_id'] : null,
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'priority' => $_POST['priority'] ?? 'Medium',
            'status' => $_POST['status'] ?? 'Pending',
            'assignees' => isset($_POST['assignees']) && is_array($_POST['assignees']) ? array_map('intval', $_POST['assignees']) : null
        ];
        $res = update_task($conn, $task_id, $data, $_SESSION['user_id']);
        if (!empty($res['ok'])) {
            $_SESSION['flash_message'] = 'Task updated successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: tasks.php?project_id=' . $project_id . build_query_suffix());
            exit;
        } else {
            $errors = $res['errors'] ?? ['Failed to update task.'];
        }
    } elseif ($action === 'delete') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        if (delete_task($conn, $task_id, $_SESSION['user_id'])) {
            $_SESSION['flash_message'] = 'Task deleted.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete task.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: tasks.php?project_id=' . $project_id . build_query_suffix());
        exit;
    } elseif ($action === 'mark_status') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? 'Pending';
        $closing_notes = $_POST['closing_notes'] ?? null;
        if (set_task_status($conn, $task_id, $status, $_SESSION['user_id'], $closing_notes)) {
            $_SESSION['flash_message'] = 'Task status updated.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to update task status.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: tasks.php?project_id=' . $project_id . build_query_suffix());
        exit;
    }
}

// Filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'phase_id' => !empty($_GET['phase_id']) ? (int)$_GET['phase_id'] : null,
    'priority' => $_GET['priority'] ?? '',
    'search' => $_GET['search'] ?? ''
];

function build_query_suffix(): string {
    $parts = [];
    foreach (['status','phase_id','priority','search'] as $k) {
        if (!empty($_GET[$k])) { $parts[] = $k . '=' . urlencode((string)$_GET[$k]); }
    }
    return $parts ? ('&' . implode('&', $parts)) : '';
}

$tasks = get_tasks($conn, $project_id, $filters);

$page_title = 'Tasks - ' . $project['title'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
          <h1 style="margin:0 0 8px 0;">✅ Project Tasks</h1>
          <div style="color:#6c757d;">Manage tasks for <strong><?= htmlspecialchars($project['title']) ?></strong> <span style="color:#6c757d;font-family:monospace;">#<?= htmlspecialchars($project['project_code']) ?></span></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="phases.php?project_id=<?= $project_id ?>" class="btn btn-secondary">📋 Manage Phases</a>
          <a href="view.php?id=<?= $project_id ?>&tab=tasks" class="btn btn-secondary">← Back to Project</a>
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

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
      <form method="get" style="display:grid;grid-template-columns:1fr 1fr 1fr 2fr auto;gap:12px;align-items:end;">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Status</label>
          <select name="status" class="form-control">
            <option value="">All</option>
            <?php foreach (['Pending','In Progress','Review','Completed'] as $st): ?>
              <option value="<?= $st ?>" <?= ($filters['status']===$st)?'selected':'' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Phase</label>
          <select name="phase_id" class="form-control">
            <option value="">All</option>
            <?php foreach ($phases as $ph): ?>
              <option value="<?= (int)$ph['id'] ?>" <?= (!empty($filters['phase_id']) && (int)$filters['phase_id']===(int)$ph['id'])?'selected':'' ?>><?= htmlspecialchars($ph['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Priority</label>
          <select name="priority" class="form-control">
            <option value="">All</option>
            <?php foreach (['Low','Medium','High','Critical'] as $pr): ?>
              <option value="<?= $pr ?>" <?= ($filters['priority']===$pr)?'selected':'' ?>><?= $pr ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Search</label>
          <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Title or description">
        </div>
        <div>
          <button class="btn btn-primary">Filter</button>
        </div>
      </form>
    </div>

    <!-- Add Task -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">➕ Add Task</h3>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:12px;">
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Title *</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g., Draft proposal">
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Phase</label>
            <select name="phase_id" class="form-control">
              <option value="">Unassigned</option>
              <?php foreach ($phases as $ph): ?>
                <option value="<?= (int)$ph['id'] ?>"><?= htmlspecialchars($ph['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Due Date</label>
            <input type="date" name="due_date" class="form-control">
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Priority</label>
            <select name="priority" class="form-control">
              <?php foreach (['Low','Medium','High','Critical'] as $pr): ?>
                <option value="<?= $pr ?>"><?= $pr ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;margin-top:12px;">
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Description</label>
            <textarea name="description" rows="2" class="form-control" placeholder="Short details..."></textarea>
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Status</label>
            <select name="status" class="form-control">
              <?php foreach (['Pending','In Progress','Review','Completed'] as $st): ?>
                <option value="<?= $st ?>"><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-weight:600;margin-bottom:6px;">Assignees</label>
            <select name="assignees[]" class="form-control" multiple size="3">
              <?php foreach ($members as $m): ?>
                <option value="<?= (int)$m['user_id'] ?>"><?= htmlspecialchars($m['username']) ?></option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:12px;color:#6c757d;margin-top:4px;">Hold Ctrl/Command to select multiple</div>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
          <button class="btn btn-primary">Add Task</button>
        </div>
      </form>
    </div>

    <!-- Tasks List -->
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">Tasks</h3>
      <?php if ($tasks): ?>
        <table class="table">
          <thead>
            <tr>
              <th>Task</th>
              <th>Phase</th>
              <th>Assignees</th>
              <th>Priority</th>
              <th>Due</th>
              <th>Progress</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasks as $t): ?>
              <?php $assigneeIds = !empty($t['assignee_ids']) ? array_map('intval', explode(',', $t['assignee_ids'])) : []; ?>
              <tr>
                <td>
                  <div style="font-weight:600;color:#1b2a57;"><?= htmlspecialchars($t['title']) ?></div>
                  <?php if (!empty($t['description'])): ?>
                    <div style="font-size:13px;color:#6c757d;"><?= htmlspecialchars($t['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= $t['phase_title'] ? htmlspecialchars($t['phase_title']) : '-' ?></td>
                <td><?= $t['assignees'] ? htmlspecialchars($t['assignees']) : 'Unassigned' ?></td>
                <td>
                  <span class="badge" style="background: <?= $t['priority']==='Critical'?'#dc3545':($t['priority']==='High'?'#faa718':($t['priority']==='Medium'?'#ffc107':'#17a2b8')) ?>;">
                    <?= htmlspecialchars($t['priority']) ?>
                  </span>
                </td>
                <td><?= $t['due_date'] ? date('M j, Y', strtotime($t['due_date'])) : '-' ?></td>
                <td><?= number_format((float)$t['progress'], 0) ?>%</td>
                <td>
                  <span class="badge" style="background:<?= $t['status']==='Completed'?'#28a745':($t['status']==='In Progress'?'#003581':($t['status']==='Review'?'#17a2b8':'#6c757d')) ?>;">
                    <?= htmlspecialchars($t['status']) ?>
                  </span>
                </td>
                <td style="white-space:nowrap;">
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="mark_status">
                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                    <input type="hidden" name="status" value="Completed">
                    <button class="btn btn-sm btn-success" title="Mark Completed">✅</button>
                  </form>
                  <details style="display:inline-block;margin-left:6px;">
                    <summary class="btn btn-sm btn-primary" style="display:inline-block;">Edit</summary>
                    <div style="background:#f8f9fa;border:1px solid #e9ecef;padding:12px;border-radius:6px;margin-top:8px;min-width:560px;">
                      <form method="post" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:12px;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Title *</label>
                          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($t['title']) ?>">
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Phase</label>
                          <select name="phase_id" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($phases as $ph): ?>
                              <option value="<?= (int)$ph['id'] ?>" <?= ((int)$t['phase_id'] === (int)$ph['id'])?'selected':'' ?>><?= htmlspecialchars($ph['title']) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Due Date</label>
                          <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars((string)$t['due_date']) ?>">
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Priority</label>
                          <select name="priority" class="form-control">
                            <?php foreach (['Low','Medium','High','Critical'] as $pr): ?>
                              <option value="<?= $pr ?>" <?= ($t['priority']===$pr)?'selected':'' ?>><?= $pr ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div style="grid-column:1 / span 2;">
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Description</label>
                          <textarea name="description" rows="2" class="form-control"><?= htmlspecialchars((string)$t['description']) ?></textarea>
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Status</label>
                          <select name="status" class="form-control">
                            <?php foreach (['Pending','In Progress','Review','Completed'] as $st): ?>
                              <option value="<?= $st ?>" <?= ($t['status']===$st)?'selected':'' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div>
                          <label style="display:block;font-weight:600;margin-bottom:6px;">Assignees</label>
                          <select name="assignees[]" class="form-control" multiple size="4">
                            <?php foreach ($members as $m): ?>
                              <option value="<?= (int)$m['user_id'] ?>" <?= in_array((int)$m['user_id'], $assigneeIds, true)?'selected':'' ?>><?= htmlspecialchars($m['username']) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;grid-column:1 / -1;justify-content:flex-end;">
                          <button class="btn btn-primary">Save</button>
                          <form method="post" onsubmit="return confirm('Delete this task?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                            <button class="btn btn-danger">Delete</button>
                          </form>
                        </div>
                      </form>
                    </div>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:#6c757d;">
          <div style="font-size:40px;">✅</div>
          <div style="margin-top:8px;">No tasks match the current filters.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); }
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
