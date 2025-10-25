<?php
require_once __DIR__ . '/helpers.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_role = $_SESSION['role'] ?? 'employee';
if (!crm_role_can_manage($user_role)) { flash_add('error','No permission.','crm'); header('Location: index.php'); exit; }

$conn = createConnection(true);
if (!$conn) { echo 'DB error'; exit; }

if (!crm_tables_exist($conn)) {
  closeConnection($conn);
  require_once __DIR__ . '/onboarding.php';
  exit;
}

$type = strtolower($_GET['type'] ?? 'tasks');
$valid = ['tasks','calls','meetings','visits','leads'];
if (!in_array($type, $valid, true)) $type = 'tasks';

$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$export = isset($_GET['export']);

$where = ['deleted_at IS NULL'];
$params = [];
$types = '';
if ($employee_id) {
    if ($type === 'tasks' || $type === 'leads') { $where[] = ($type==='tasks' ? 'assigned_to = ?' : 'assigned_to = ?'); $types.='i'; $params[]=$employee_id; }
    else { $where[] = 'employee_id = ?'; $types.='i'; $params[] = $employee_id; }
}
if ($from !== '') { $where[] = ($type==='tasks'?'COALESCE(due_date, DATE(created_at)) >= ?':($type==='calls'?'DATE(call_date) >= ?':($type==='meetings'?'DATE(meeting_date) >= ?':($type==='visits'?'DATE(visit_date) >= ?':'DATE(created_at) >= ?')))); $types.='s'; $params[]=$from; }
if ($to !== '') { $where[] = ($type==='tasks'?'COALESCE(due_date, DATE(created_at)) <= ?':($type==='calls'?'DATE(call_date) <= ?':($type==='meetings'?'DATE(meeting_date) <= ?':($type==='visits'?'DATE(visit_date) <= ?':'DATE(created_at) <= ?')))); $types.='s'; $params[]=$to; }

$table = 'crm_' . $type;
$select = '*';
$order = 'created_at DESC';
if ($type==='tasks') { $order = 'COALESCE(due_date, DATE(created_at)) DESC'; }
if ($type==='calls') { $order = 'call_date DESC'; }
if ($type==='meetings') { $order = 'meeting_date DESC'; }
if ($type==='visits') { $order = 'visit_date DESC'; }

$sql = 'SELECT ' . $select . ' FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order;
$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $types !== '') { mysqli_stmt_bind_param($stmt, $types, ...$params); }
if ($stmt) { mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); }
else { $res = mysqli_query($conn, 'SELECT ' . $select . ' FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order); }

$rows = [];
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } if ($stmt) mysqli_stmt_close($stmt); else mysqli_free_result($res); }

if ($export) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="crm_' . $type . '_report.csv"');
    $out = fopen('php://output', 'w');
    if ($rows) { fputcsv($out, array_keys($rows[0])); }
    foreach ($rows as $r) { fputcsv($out, $r); }
    fclose($out);
    exit;
}

$employees = crm_fetch_employees($conn);

$page_title = 'CRM Reports - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header"><h1>📊 CRM Reports</h1></div>
    <div class="card">
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
        <div><label>Type</label><select name="type" class="form-control">
          <?php foreach ($valid as $v): ?><option value="<?php echo $v; ?>" <?php echo ($type===$v?'selected':''); ?>><?php echo ucfirst($v); ?></option><?php endforeach; ?>
        </select></div>
        <div><label>Employee</label><select name="employee_id" class="form-control">
          <option value="">All</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo (int)$emp['id']; ?>" <?php echo ($employee_id===(int)$emp['id']?'selected':''); ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
          <?php endforeach; ?>
        </select></div>
        <div><label>From</label><input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control"></div>
        <div><label>To</label><input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control"></div>
        <div style="grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end;">
          <button class="btn">Filter</button>
          <a class="btn btn-secondary" href="?type=<?php echo urlencode($type); ?>&employee_id=<?php echo (int)$employee_id; ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=1">Export CSV</a>
        </div>
      </form>
    </div>

    <div class="card" style="margin-top:12px;">
      <?php if (!$rows): ?><p>No records.</p><?php else: ?>
        <div style="overflow:auto;">
          <table class="table">
            <thead>
              <tr>
                <?php foreach (array_keys($rows[0]) as $k): ?><th><?php echo htmlspecialchars($k); ?></th><?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <?php foreach ($r as $v): ?><td><?php echo htmlspecialchars((string)$v); ?></td><?php endforeach; ?>
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
<?php closeConnection($conn); ?>
