<?php
require_once __DIR__ . '/helpers.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$conn = createConnection(true);
if (!$conn) { echo 'DB error'; exit; }

if (!crm_tables_exist($conn)) {
  closeConnection($conn);
  require_once __DIR__ . '/onboarding.php';
  exit;
}

$events = [];
// Tasks -> due_date
$res = mysqli_query($conn, "SELECT id, title, COALESCE(due_date, DATE(created_at)) AS d, 'Task' AS t FROM crm_tasks WHERE deleted_at IS NULL ORDER BY d DESC LIMIT 200");
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $events[] = $r; } mysqli_free_result($res); }
// Calls -> call_date
$res = mysqli_query($conn, "SELECT id, title, call_date AS d, 'Call' AS t FROM crm_calls WHERE deleted_at IS NULL ORDER BY d DESC LIMIT 200");
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $events[] = $r; } mysqli_free_result($res); }
// Meetings -> meeting_date
$res = mysqli_query($conn, "SELECT id, title, meeting_date AS d, 'Meeting' AS t FROM crm_meetings WHERE deleted_at IS NULL ORDER BY d DESC LIMIT 200");
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $events[] = $r; } mysqli_free_result($res); }
// Visits -> visit_date
$res = mysqli_query($conn, "SELECT id, title, visit_date AS d, 'Visit' AS t FROM crm_visits WHERE deleted_at IS NULL ORDER BY d DESC LIMIT 200");
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $events[] = $r; } mysqli_free_result($res); }

usort($events, function($a,$b){ return strcmp($a['d'], $b['d']); });

$page_title = 'CRM Calendar - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header"><h1>🗓️ CRM Calendar</h1></div>
    <div class="card">
      <?php if (!$events): ?>
        <p>No events to show.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Date</th><th>Type</th><th>Title</th></tr></thead>
          <tbody>
            <?php foreach ($events as $e): ?>
              <tr>
                <td><?php echo htmlspecialchars($e['d']); ?></td>
                <td><?php echo htmlspecialchars($e['t']); ?></td>
                <td><?php echo htmlspecialchars($e['title']); ?></td>
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
