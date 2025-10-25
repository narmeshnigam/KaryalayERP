<?php
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    exit;
}

// If CRM tables are not present, show onboarding instead of failing queries
if (!crm_tables_exist($conn)) {
  closeConnection($conn);
  require_once __DIR__ . '/onboarding.php';
  exit;
}

$counts = [
    'tasks' => 0,
    'calls' => 0,
    'meetings' => 0,
    'visits' => 0,
    'leads' => 0,
];

foreach (['tasks','calls','meetings','visits','leads'] as $t) {
    $table = 'crm_' . $t;
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM $table WHERE deleted_at IS NULL");
    if ($res) { $row = mysqli_fetch_assoc($res); $counts[$t] = (int)($row['c'] ?? 0); mysqli_free_result($res);}    
}

$page_title = 'CRM - Dashboard - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
  .crm-subnav { display:flex; gap:12px; flex-wrap:wrap; border-bottom:1px solid #e5e7eb; padding-bottom:8px; }
  .crm-subnav a { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; color:#111827; text-decoration:none; font-weight:500; }
  .crm-subnav a:hover { background:#f3f4f6; }
  .crm-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:16px; }
  .crm-card h3 { margin:0 0 8px; font-size:18px; }
  .crm-card .count { font-size:28px; font-weight:700; color:#003581; }
  .crm-actions { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
  .btn-link { color:#003581; text-decoration:none; font-weight:600; }
  .btn-link:hover { text-decoration:underline; }
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <h1>📇 CRM</h1>
      <p style="margin:6px 0 0;">Quick overview and shortcuts across Tasks, Calls, Meetings, Visits and Leads.</p>
    </div>

    <nav class="crm-subnav" aria-label="CRM sections">
      <a href="./my_tasks.php">Tasks</a>
      <a href="./add.php?type=call">Calls</a>
      <a href="./add.php?type=meeting">Meetings</a>
      <a href="./add.php?type=visit">Visits</a>
      <a href="./add.php?type=lead">Leads</a>
      <a href="./calendar.php">Calendar</a>
      <a href="./reports.php">Reports</a>
    </nav>

    <div style="height:10px;"></div>

    <div class="crm-cards">
      <div class="card crm-card">
        <h3>Tasks</h3>
        <div class="count"><?php echo (int)$counts['tasks']; ?></div>
        <div class="crm-actions">
          <a class="btn" href="./my_tasks.php">View my tasks</a>
          <?php if (crm_role_can_manage($user_role)): ?>
            <a class="btn btn-secondary" href="./reports.php?type=tasks">All tasks</a>
            <a class="btn" href="./add.php?type=task">+ Add Task</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card crm-card">
        <h3>Calls</h3>
        <div class="count"><?php echo (int)$counts['calls']; ?></div>
        <div class="crm-actions">
          <a class="btn btn-secondary" href="./reports.php?type=calls">View calls</a>
          <?php if (crm_role_can_manage($user_role)): ?>
            <a class="btn" href="./add.php?type=call">+ Log Call</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card crm-card">
        <h3>Meetings</h3>
        <div class="count"><?php echo (int)$counts['meetings']; ?></div>
        <div class="crm-actions">
          <a class="btn btn-secondary" href="./reports.php?type=meetings">View meetings</a>
          <?php if (crm_role_can_manage($user_role)): ?>
            <a class="btn" href="./add.php?type=meeting">+ Schedule</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card crm-card">
        <h3>Visits</h3>
        <div class="count"><?php echo (int)$counts['visits']; ?></div>
        <div class="crm-actions">
          <a class="btn btn-secondary" href="./reports.php?type=visits">View visits</a>
          <?php if (crm_role_can_manage($user_role)): ?>
            <a class="btn" href="./add.php?type=visit">+ Log Visit</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card crm-card">
        <h3>Leads</h3>
        <div class="count"><?php echo (int)$counts['leads']; ?></div>
        <div class="crm-actions">
          <a class="btn btn-secondary" href="./reports.php?type=leads">View leads</a>
          <?php if (crm_role_can_manage($user_role)): ?>
            <a class="btn" href="./add.php?type=lead">+ Add Lead</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="margin-top:22px;display:flex;gap:12px;flex-wrap:wrap;">
      <a class="btn" href="./calendar.php">📆 Open Calendar</a>
      <a class="btn btn-secondary" href="./reports.php">📊 Open Reports</a>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
