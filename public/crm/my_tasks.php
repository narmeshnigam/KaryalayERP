<?php
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$conn = createConnection(true);
if (!$conn) { echo 'DB connection failed'; exit; }

if (!crm_tables_exist($conn)) {
  closeConnection($conn);
  require_once __DIR__ . '/onboarding.php';
  exit;
}

$current_employee_id = crm_current_employee_id($conn, (int)($_SESSION['user_id'] ?? 0));
if (!$current_employee_id) {
    $current_employee_id = (int)($_SESSION['employee_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'], $_POST['status'])) {
    $task_id = (int)$_POST['task_id'];
    $status = $_POST['status'];
    if (in_array($status, ['Pending','In Progress','Completed'], true)) {
        $stmt = mysqli_prepare($conn, 'UPDATE crm_tasks SET status = ? WHERE id = ? AND assigned_to = ? AND deleted_at IS NULL');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sii', $status, $task_id, $current_employee_id);
            if (mysqli_stmt_execute($stmt) && $status === 'Completed') {
                crm_notify_task_completed($conn, $task_id);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$tasks = [];
$stmt = mysqli_prepare($conn, 'SELECT id, title, description, status, due_date FROM crm_tasks WHERE assigned_to = ? AND deleted_at IS NULL ORDER BY COALESCE(due_date, DATE(created_at)) ASC, id DESC');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $current_employee_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) { $tasks[] = $r; }
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

$page_title = 'My CRM Tasks - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header"><h1>🧰 My Tasks</h1></div>
    <div class="card">
      <?php if (!$tasks): ?>
        <p>No tasks assigned.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Title</th><th>Status</th><th>Due</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($tasks as $t): ?>
              <tr>
                <td><?php echo htmlspecialchars($t['title'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($t['status'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($t['due_date'] ?? ''); ?></td>
                <td>
                  <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
                    <input type="hidden" name="task_id" value="<?php echo (int)$t['id']; ?>">
                    <select name="status" class="form-control">
                      <?php foreach (['Pending','In Progress','Completed'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($t['status']===$s?'selected':''); ?>><?php echo $s; ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn">Update</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
