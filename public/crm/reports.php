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

// Filters
$employees = crm_fetch_employees($conn);
$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if ($from === '' || $to === '') {
  // Default: last 30 days
  $to = date('Y-m-d');
  $from = date('Y-m-d', strtotime('-30 days'));
}

// Build common filter clause helpers
$date_between = function(string $col) use ($from,$to) { return "DATE($col) BETWEEN ? AND ?"; };
$bind_dates = function($stmt) use ($from,$to) { mysqli_stmt_bind_param($stmt,'ss',$from,$to); };

// 1) KPI metrics
$kpi = [
  'tasks' => 0, 'calls' => 0, 'meetings' => 0, 'visits' => 0, 'leads' => 0,
  'overdue_tasks' => 0, 'completed_tasks' => 0, 'avg_completion_days' => 0.0
];

// Helper to add optional employee filter (assigned_to where available)
$emp_clause = $employee_id > 0 ? ' AND assigned_to = ?' : '';

// Tasks count (by due_date in range)
$sql = 'SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND due_date IS NOT NULL AND ' . $date_between('due_date') . ($employee_id>0?' AND assigned_to = ?':'');
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
  if ($employee_id>0) { mysqli_stmt_bind_param($stmt,'ssi',$from,$to,$employee_id); }
  else { mysqli_stmt_bind_param($stmt,'ss',$from,$to); }
  mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['tasks'] = ($res && ($r=mysqli_fetch_assoc($res)))?(int)$r['c']:0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);
}

// Completed tasks (by completed_at in range) - only if column exists
$completed_at_exists = true;
$check_res = mysqli_query($conn, "SHOW COLUMNS FROM crm_tasks LIKE 'completed_at'");
if (!$check_res || mysqli_num_rows($check_res) === 0) { $completed_at_exists = false; }
if ($check_res) mysqli_free_result($check_res);

if ($completed_at_exists) {
  $sql = 'SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND status = \'Completed\' AND completed_at IS NOT NULL AND ' . $date_between('completed_at') . ($employee_id>0?' AND (assigned_to = ? OR closed_by = ?)':'');
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    if ($employee_id>0) { mysqli_stmt_bind_param($stmt,'ssii',$from,$to,$employee_id,$employee_id); }
    else { mysqli_stmt_bind_param($stmt,'ss',$from,$to); }
    mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['completed_tasks'] = ($res && ($r=mysqli_fetch_assoc($res)))?(int)$r['c']:0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Overdue tasks in range (due_date < today and not completed)
if ($completed_at_exists) {
  $sql = 'SELECT COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND status <> \'Completed\' AND due_date IS NOT NULL AND due_date < CURDATE() AND ' . $date_between('due_date') . ($employee_id>0?' AND assigned_to = ?':'');
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    if ($employee_id>0) { mysqli_stmt_bind_param($stmt,'ssi',$from,$to,$employee_id); }
    else { mysqli_stmt_bind_param($stmt,'ss',$from,$to); }
    mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['overdue_tasks'] = ($res && ($r=mysqli_fetch_assoc($res)))?(int)$r['c']:0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Avg completion time in days (created_at -> completed_at) for completed within range
if ($completed_at_exists) {
  $sql = 'SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at))/24 AS d FROM crm_tasks WHERE deleted_at IS NULL AND status = \'Completed\' AND completed_at IS NOT NULL AND ' . $date_between('completed_at') . ($employee_id>0?' AND (assigned_to = ? OR closed_by = ?)':'');
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    if ($employee_id>0) { mysqli_stmt_bind_param($stmt,'ssii',$from,$to,$employee_id,$employee_id); }
    else { mysqli_stmt_bind_param($stmt,'ss',$from,$to); }
    mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['avg_completion_days'] = ($res && ($r=mysqli_fetch_assoc($res)))? round((float)($r['d'] ?? 0), 2) : 0.0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Calls
$sql = 'SELECT COUNT(*) c FROM crm_calls WHERE deleted_at IS NULL AND ' . $date_between('call_date') . ($employee_id>0?' AND (assigned_to = ? OR created_by = ?)':'');
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) { if ($employee_id>0){ mysqli_stmt_bind_param($stmt,'ssii',$from,$to,$employee_id,$employee_id);} else { mysqli_stmt_bind_param($stmt,'ss',$from,$to);} mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['calls'] = ($res && ($r=mysqli_fetch_assoc($res)))?(int)$r['c']:0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);} 

// Meetings
$sql = 'SELECT COUNT(*) c FROM crm_meetings WHERE deleted_at IS NULL AND ' . $date_between('meeting_date') . ($employee_id>0?' AND (assigned_to = ? OR created_by = ?)':'');
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) { if ($employee_id>0){ mysqli_stmt_bind_param($stmt,'ssii',$from,$to,$employee_id,$employee_id);} else { mysqli_stmt_bind_param($stmt,'ss',$from,$to);} mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['meetings'] = ($res && ($r=mysqli_fetch_assoc($res)))?(int)$r['c']:0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);} 

// Visits
$sql = 'SELECT COUNT(*) c FROM crm_visits WHERE deleted_at IS NULL AND ' . $date_between('visit_date') . ($employee_id>0?' AND (assigned_to = ? OR created_by = ?)':'');
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) { if ($employee_id>0){ mysqli_stmt_bind_param($stmt,'ssii',$from,$to,$employee_id,$employee_id);} else { mysqli_stmt_bind_param($stmt,'ss',$from,$to);} mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['visits'] = ($res && ($r=mysqli_fetch_assoc($res)))?(int)$r['c']:0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);} 

// Leads created
$sql = 'SELECT COUNT(*) c FROM crm_leads WHERE deleted_at IS NULL AND ' . $date_between('created_at') . ($employee_id>0?' AND assigned_to = ?':'');
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) { if ($employee_id>0){ mysqli_stmt_bind_param($stmt,'ssi',$from,$to,$employee_id);} else { mysqli_stmt_bind_param($stmt,'ss',$from,$to);} mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); $kpi['leads'] = ($res && ($r=mysqli_fetch_assoc($res)))?(int)$r['c']:0; if($res)mysqli_free_result($res); mysqli_stmt_close($stmt);} 

// 2) Team performance (leaderboards)
$emp_map = crm_fetch_employee_map($conn); // id=>label

function crm_report_group(mysqli $conn, string $sql, array $params, string $types): array {
  $out = [];
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    if ($types !== '') { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) { $out[] = $r; }
    if ($res) mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
  return $out;
}

$lb_tasks = crm_report_group($conn,
  'SELECT assigned_to AS emp, COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND due_date IS NOT NULL AND ' . $date_between('due_date') . ' GROUP BY assigned_to ORDER BY c DESC LIMIT 10',
  [$from,$to], 'ss');

$lb_completed = $completed_at_exists ? crm_report_group($conn,
  'SELECT COALESCE(closed_by, assigned_to) AS emp, COUNT(*) c FROM crm_tasks WHERE deleted_at IS NULL AND status=\'Completed\' AND completed_at IS NOT NULL AND ' . $date_between('completed_at') . ' GROUP BY COALESCE(closed_by, assigned_to) ORDER BY c DESC LIMIT 10',
  [$from,$to], 'ss') : [];

$lb_calls = crm_report_group($conn,
  'SELECT COALESCE(assigned_to, created_by) AS emp, COUNT(*) c FROM crm_calls WHERE deleted_at IS NULL AND ' . $date_between('call_date') . ' GROUP BY COALESCE(assigned_to, created_by) ORDER BY c DESC LIMIT 10',
  [$from,$to], 'ss');

$lb_meetings = crm_report_group($conn,
  'SELECT COALESCE(assigned_to, created_by) AS emp, COUNT(*) c FROM crm_meetings WHERE deleted_at IS NULL AND ' . $date_between('meeting_date') . ' GROUP BY COALESCE(assigned_to, created_by) ORDER BY c DESC LIMIT 10',
  [$from,$to], 'ss');

$lb_visits = crm_report_group($conn,
  'SELECT COALESCE(assigned_to, created_by) AS emp, COUNT(*) c FROM crm_visits WHERE deleted_at IS NULL AND ' . $date_between('visit_date') . ' GROUP BY COALESCE(assigned_to, created_by) ORDER BY c DESC LIMIT 10',
  [$from,$to], 'ss');

// 3) Outcomes & funnel
$calls_outcomes = crm_report_group($conn,
  'SELECT COALESCE(outcome,\'—\') outcome, COUNT(*) c FROM crm_calls WHERE deleted_at IS NULL AND ' . $date_between('call_date') . ' GROUP BY COALESCE(outcome,\'—\') ORDER BY c DESC',
  [$from,$to], 'ss');

$meetings_outcomes = crm_report_group($conn,
  'SELECT CASE WHEN outcome IS NULL OR outcome = \'\' THEN \'—\' ELSE \'Recorded\' END outcome, COUNT(*) c FROM crm_meetings WHERE deleted_at IS NULL AND ' . $date_between('meeting_date') . ' GROUP BY outcome ORDER BY c DESC',
  [$from,$to], 'ss');

$visits_outcomes = crm_report_group($conn,
  'SELECT CASE WHEN outcome IS NULL OR outcome = \'\' THEN \'—\' ELSE \'Recorded\' END outcome, COUNT(*) c FROM crm_visits WHERE deleted_at IS NULL AND ' . $date_between('visit_date') . ' GROUP BY outcome ORDER BY c DESC',
  [$from,$to], 'ss');

$lead_funnel = crm_report_group($conn,
  'SELECT status, COUNT(*) c FROM crm_leads WHERE deleted_at IS NULL AND ' . $date_between('created_at') . ' GROUP BY status ORDER BY c DESC',
  [$from,$to], 'ss');

$converted = 0; $total_leads = 0; foreach ($lead_funnel as $f) { $total_leads += (int)$f['c']; if (($f['status'] ?? '') === 'Converted') { $converted = (int)$f['c']; } }
$conversion_rate = $total_leads > 0 ? round(($converted/$total_leads)*100, 1) : 0.0;

// 4) Detailed records export (retain original functionality)
$type = strtolower($_GET['type'] ?? 'tasks');
$valid = ['tasks','calls','meetings','visits','leads']; if (!in_array($type,$valid,true)) $type='tasks';
$export = isset($_GET['export']);

$where = ['deleted_at IS NULL']; $params = []; $types = '';
if ($employee_id) {
  if (in_array($type,['tasks','leads'],true)) { $where[] = 'assigned_to = ?'; $types.='i'; $params[]=$employee_id; }
  else { $where[] = '(assigned_to = ? OR created_by = ?)'; $types.='ii'; $params[]=$employee_id; $params[]=$employee_id; }
}
// date columns per type
$_col = $type==='tasks'?'COALESCE(due_date, DATE(created_at))':($type==='calls'?'DATE(call_date)':($type==='meetings'?'DATE(meeting_date)':($type==='visits'?'DATE(visit_date)':'DATE(created_at)')));
if ($from !== '') { $where[] = $_col . ' >= ?'; $types.='s'; $params[]=$from; }
if ($to !== '') { $where[] = $_col . ' <= ?'; $types.='s'; $params[]=$to; }
$table = 'crm_' . $type; $select='*'; $order = $type==='tasks'?'COALESCE(due_date, DATE(created_at)) DESC':($type==='calls'?'call_date DESC':($type==='meetings'?'meeting_date DESC':($type==='visits'?'visit_date DESC':'created_at DESC')));
$sql = 'SELECT ' . $select . ' FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order;
$stmt = mysqli_prepare($conn,$sql); if ($stmt && $types!==''){ mysqli_stmt_bind_param($stmt,$types,...$params);} if($stmt){ mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);} else { $res = mysqli_query($conn,$sql);} 
$rows = []; if ($res) { while($r=mysqli_fetch_assoc($res)){ $rows[]=$r; } if($stmt) mysqli_stmt_close($stmt); else mysqli_free_result($res);} 

if ($export) {
  header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="crm_' . $type . '_report.csv"'); $out=fopen('php://output','w'); if($rows){ fputcsv($out,array_keys($rows[0])); } foreach($rows as $r){ fputcsv($out,$r);} fclose($out); exit; }

$page_title = 'CRM Reports - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
          <h1>📊 CRM Reports</h1>
          <p>Team activity and performance metrics for actionable oversight</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="./index.php" class="btn btn-accent">← CRM Dashboard</a>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card">
      <form method="GET" id="filterForm">
        <!-- Quick Date Range Selector -->
        <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;">
          <label style="display:block;margin-bottom:8px;font-weight:600;color:#374151;">Quick Date Range</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('today')">Today</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('yesterday')">Yesterday</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('week')">This Week</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('last-week')">Last Week</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('month')">This Month</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('last-month')">Last Month</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('30-days')">Last 30 Days</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('90-days')">Last 90 Days</button>
          </div>
        </div>

        <!-- Custom Filters -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:end;">
          <div>
            <label style="display:block;margin-bottom:4px;font-weight:500;color:#374151;">Employee</label>
            <select name="employee_id" class="form-control">
              <option value="">All Employees</option>
              <?php foreach ($employees as $emp): $label = trim(($emp['employee_code'] ?? '') . ' ' . ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                <option value="<?php echo (int)$emp['id']; ?>" <?php echo ($employee_id===(int)$emp['id']?'selected':''); ?>><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;margin-bottom:4px;font-weight:500;color:#374151;">From Date</label>
            <input type="date" name="from" id="fromDate" value="<?php echo htmlspecialchars($from); ?>" class="form-control">
          </div>
          <div>
            <label style="display:block;margin-bottom:4px;font-weight:500;color:#374151;">To Date</label>
            <input type="date" name="to" id="toDate" value="<?php echo htmlspecialchars($to); ?>" class="form-control">
          </div>
          <div style="display:flex;gap:8px;">
            <button type="submit" class="btn" style="flex:1;">Apply Filters</button>
            <a href="?" class="btn btn-secondary">Reset</a>
          </div>
        </div>
      </form>
    </div>

    <script>
    function setDateRange(range) {
      const today = new Date();
      const fromInput = document.getElementById('fromDate');
      const toInput = document.getElementById('toDate');
      let fromDate, toDate;

      switch(range) {
        case 'today':
          fromDate = toDate = today;
          break;
        case 'yesterday':
          fromDate = toDate = new Date(today.setDate(today.getDate() - 1));
          break;
        case 'week':
          const dayOfWeek = today.getDay();
          const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
          fromDate = new Date(today.setDate(diff));
          toDate = new Date();
          break;
        case 'last-week':
          const lastWeekEnd = new Date(today.setDate(today.getDate() - today.getDay()));
          const lastWeekStart = new Date(lastWeekEnd);
          lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
          fromDate = lastWeekStart;
          toDate = lastWeekEnd;
          break;
        case 'month':
          fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
          toDate = new Date();
          break;
        case 'last-month':
          fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
          toDate = new Date(today.getFullYear(), today.getMonth(), 0);
          break;
        case '30-days':
          toDate = new Date();
          fromDate = new Date(today.setDate(today.getDate() - 30));
          break;
        case '90-days':
          toDate = new Date();
          fromDate = new Date(today.setDate(today.getDate() - 90));
          break;
      }

      fromInput.value = formatDate(fromDate);
      toInput.value = formatDate(toDate);
    }

    function formatDate(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }
    </script>

    <!-- KPI Row -->
    <div class="card" style="margin-top:16px;">
      <h3 style="margin:0 0 16px;color:#003581;font-size:18px;">📈 Key Performance Indicators</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;">
        <div style="padding:16px;border:1px solid #d1fae5;border-radius:8px;background:linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);">
          <div style="font-size:13px;color:#065f46;font-weight:500;margin-bottom:4px;">Meetings</div>
          <div style="font-size:28px;color:#065f46;font-weight:700;"><?php echo (int)$kpi['meetings']; ?></div>
        </div>
        <div style="padding:16px;border:1px solid #cffafe;border-radius:8px;background:linear-gradient(135deg, #ecfeff 0%, #cffafe 100%);">
          <div style="font-size:13px;color:#0f766e;font-weight:500;margin-bottom:4px;">Visits</div>
          <div style="font-size:28px;color:#0f766e;font-weight:700;"><?php echo (int)$kpi['visits']; ?></div>
        </div>
        <div style="padding:16px;border:1px solid #fed7aa;border-radius:8px;background:linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%);">
          <div style="font-size:13px;color:#c2410c;font-weight:500;margin-bottom:4px;">Calls</div>
          <div style="font-size:28px;color:#c2410c;font-weight:700;"><?php echo (int)$kpi['calls']; ?></div>
        </div>
        <div style="padding:16px;border:1px solid #bfdbfe;border-radius:8px;background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);">
          <div style="font-size:13px;color:#1e40af;font-weight:500;margin-bottom:4px;">Tasks (Due)</div>
          <div style="font-size:28px;color:#1e40af;font-weight:700;"><?php echo (int)$kpi['tasks']; ?></div>
        </div>
        <div style="padding:16px;border:1px solid #ddd6fe;border-radius:8px;background:linear-gradient(135deg, #f5f3ff 0%, #ddd6fe 100%);">
          <div style="font-size:13px;color:#5b21b6;font-weight:500;margin-bottom:4px;">Leads Created</div>
          <div style="font-size:28px;color:#5b21b6;font-weight:700;"><?php echo (int)$kpi['leads']; ?></div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="font-size:13px;color:#6b7280;font-weight:500;margin-bottom:4px;">Tasks Completed</div>
          <div style="font-size:26px;color:#059669;font-weight:700;"><?php echo (int)$kpi['completed_tasks']; ?></div>
        </div>
        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="font-size:13px;color:#6b7280;font-weight:500;margin-bottom:4px;">Overdue Tasks</div>
          <div style="font-size:26px;color:#dc2626;font-weight:700;"><?php echo (int)$kpi['overdue_tasks']; ?></div>
        </div>
        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="font-size:13px;color:#6b7280;font-weight:500;margin-bottom:4px;">Avg. Completion Time</div>
          <div style="font-size:26px;color:#111827;font-weight:700;"><?php echo number_format((float)$kpi['avg_completion_days'], 1); ?> <span style="font-size:14px;color:#6b7280;font-weight:400;">days</span></div>
        </div>
        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="font-size:13px;color:#6b7280;font-weight:500;margin-bottom:4px;">Lead Conversion Rate</div>
          <div style="font-size:26px;color:#7c3aed;font-weight:700;"><?php echo $conversion_rate; ?>%</div>
        </div>
      </div>
    </div>

    <!-- Leaderboards -->
    <div class="card" style="margin-top:16px;">
      <h3 style="margin:0 0 16px;color:#003581;font-size:18px;">🏅 Team Leaderboards</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
        <?php
          $sections = [
            ['title'=>'Tasks Assigned (due)','rows'=>$lb_tasks,'color'=>'#1e40af'],
            ['title'=>'Tasks Completed','rows'=>$lb_completed,'color'=>'#059669'],
            ['title'=>'Calls Logged','rows'=>$lb_calls,'color'=>'#c2410c'],
            ['title'=>'Meetings Held','rows'=>$lb_meetings,'color'=>'#065f46'],
            ['title'=>'Visits Logged','rows'=>$lb_visits,'color'=>'#0f766e'],
          ];
          foreach ($sections as $sec): ?>
          <div style="border:1px solid #e5e7eb;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="padding:12px 14px;background:linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);border-bottom:2px solid <?php echo $sec['color']; ?>;font-weight:600;color:#111827;font-size:14px;"><?php echo $sec['title']; ?></div>
            <div style="max-height:280px;overflow-y:auto;">
              <?php if (!$sec['rows']): ?>
                <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">No data available</div>
              <?php else: ?>
                <?php $rank=1; foreach ($sec['rows'] as $r): $name = $emp_map[(int)($r['emp'] ?? 0)] ?? 'Unassigned'; ?>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #f3f4f6;transition:background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                    <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                      <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:<?php echo $rank<=3 ? $sec['color'] : '#e5e7eb'; ?>;color:<?php echo $rank<=3 ? '#fff' : '#6b7280'; ?>;font-size:11px;font-weight:700;flex-shrink:0;"><?php echo $rank++; ?></span>
                      <span style="color:#374151;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($name); ?></span>
                    </div>
                    <div style="font-weight:700;color:<?php echo $sec['color']; ?>;font-size:16px;flex-shrink:0;"><?php echo (int)$r['c']; ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Outcomes & Funnel -->
    <div class="card" style="margin-top:16px;">
      <h3 style="margin:0 0 16px;color:#003581;font-size:18px;">� Outcomes & Funnel Analysis</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
        <div style="border:1px solid #e5e7eb;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
          <div style="padding:12px 14px;background:linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%);border-bottom:2px solid #c2410c;font-weight:600;color:#9a3412;font-size:14px;">📞 Call Outcomes</div>
          <div style="max-height:280px;overflow-y:auto;">
            <?php if (!$calls_outcomes): ?>
              <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">No data available</div>
            <?php else: ?>
              <?php foreach ($calls_outcomes as $o): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #f3f4f6;">
                  <span style="color:#374151;font-size:13px;"><?php echo htmlspecialchars($o['outcome']); ?></span>
                  <span style="font-weight:700;color:#c2410c;font-size:16px;"><?php echo (int)$o['c']; ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
          <div style="padding:12px 14px;background:linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);border-bottom:2px solid #065f46;font-weight:600;color:#064e3b;font-size:14px;">🤝 Meeting Outcomes</div>
          <div style="max-height:280px;overflow-y:auto;">
            <?php if (!$meetings_outcomes): ?>
              <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">No data available</div>
            <?php else: ?>
              <?php foreach ($meetings_outcomes as $o): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #f3f4f6;">
                  <span style="color:#374151;font-size:13px;"><?php echo htmlspecialchars($o['outcome']); ?></span>
                  <span style="font-weight:700;color:#065f46;font-size:16px;"><?php echo (int)$o['c']; ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
          <div style="padding:12px 14px;background:linear-gradient(135deg, #ecfeff 0%, #cffafe 100%);border-bottom:2px solid #0f766e;font-weight:600;color:#134e4a;font-size:14px;">🚶 Visit Outcomes</div>
          <div style="max-height:280px;overflow-y:auto;">
            <?php if (!$visits_outcomes): ?>
              <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">No data available</div>
            <?php else: ?>
              <?php foreach ($visits_outcomes as $o): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #f3f4f6;">
                  <span style="color:#374151;font-size:13px;"><?php echo htmlspecialchars($o['outcome']); ?></span>
                  <span style="font-weight:700;color:#0f766e;font-size:16px;"><?php echo (int)$o['c']; ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
          <div style="padding:12px 14px;background:linear-gradient(135deg, #f5f3ff 0%, #ddd6fe 100%);border-bottom:2px solid #5b21b6;font-weight:600;color:#4c1d95;font-size:14px;">🎯 Lead Funnel</div>
          <div style="max-height:280px;overflow-y:auto;">
            <?php if (!$lead_funnel): ?>
              <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">No data available</div>
            <?php else: ?>
              <?php foreach ($lead_funnel as $f): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #f3f4f6;">
                  <span style="color:#374151;font-size:13px;"><?php echo htmlspecialchars($f['status']); ?></span>
                  <span style="font-weight:700;color:#5b21b6;font-size:16px;"><?php echo (int)$f['c']; ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Detailed Records (with CSV) -->
    <div class="card" style="margin-top:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;">
        <h3 style="margin:0;color:#003581;font-size:18px;white-space:nowrap;">📄 Detailed Records</h3>
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex:1;justify-content:flex-end;">
          <input type="hidden" name="employee_id" value="<?php echo (int)$employee_id; ?>" />
          <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>" />
          <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>" />
          <label style="font-weight:500;color:#374151;white-space:nowrap;">Record Type:</label>
          <select name="type" class="form-control" style="min-width:140px;">
            <?php foreach ($valid as $v): ?><option value="<?php echo $v; ?>" <?php echo ($type===$v?'selected':''); ?>><?php echo ucfirst($v); ?></option><?php endforeach; ?>
          </select>
          <button type="submit" class="btn" style="white-space:nowrap;">🔄 Refresh</button>
          <a class="btn btn-accent" href="?type=<?php echo urlencode($type); ?>&employee_id=<?php echo (int)$employee_id; ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=1" style="white-space:nowrap;">📥 Export CSV</a>
        </form>
      </div>
      <?php if (!$rows): ?>
        <div style="padding:40px;text-align:center;background:#f9fafb;border-radius:8px;border:1px dashed #d1d5db;">
          <p style="color:#6b7280;font-size:14px;margin:0;">No records found for the selected filters.</p>
        </div>
      <?php else: ?>
        <div style="background:#f9fafb;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:13px;color:#6b7280;">
          Showing <strong style="color:#111827;"><?php echo count($rows); ?></strong> record(s) for <strong style="color:#111827;"><?php echo ucfirst($type); ?></strong>
        </div>
        <div style="overflow:auto;border:1px solid #e5e7eb;border-radius:8px;">
          <table class="table" style="margin:0;">
            <thead style="background:#f3f4f6;">
              <tr>
                <?php foreach (array_keys($rows[0]) as $k): ?>
                  <th style="padding:12px;font-size:12px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.5px;white-space:nowrap;"><?php echo htmlspecialchars($k); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $idx => $r): ?>
                <tr style="border-bottom:1px solid #e5e7eb;<?php echo $idx % 2 === 0 ? 'background:#ffffff;' : 'background:#f9fafb;'; ?>">
                  <?php foreach ($r as $v): ?>
                    <td style="padding:10px 12px;font-size:13px;color:#374151;white-space:nowrap;"><?php echo htmlspecialchars((string)$v); ?></td>
                  <?php endforeach; ?>
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
