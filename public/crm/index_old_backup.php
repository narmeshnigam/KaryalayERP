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

$filters = crm_parse_filters($conn);
$page_title = 'CRM Dashboard - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$opts = crm_filter_options($conn);
$kpis = crm_kpis($conn, $filters);
// Previous period = same length immediately before
$periodStart = new DateTime($filters['start']);
$periodEnd = new DateTime($filters['end']);
$days = max(1, (int)$periodStart->diff($periodEnd)->days + 1);
$prevEnd = (clone $periodStart)->modify('-1 day');
$prevStart = (clone $prevEnd)->modify('-'.($days-1).' days');
$prevFilters = $filters; $prevFilters['start'] = $prevStart->format('Y-m-d'); $prevFilters['end'] = $prevEnd->format('Y-m-d');
$kpisPrev = crm_kpis($conn, $prevFilters);
$funnel = crm_lead_funnel($conn, $filters);
$trend = crm_activities_last_days($conn, 7);
$perf = crm_employee_performance($conn, $filters);
$matrix = crm_followup_matrix($conn, $filters);
$recent = crm_recent_interactions($conn, $filters, 20);
?>

<style>
  .crm-nav { display:flex; gap:0; background:#f9fafb; border-bottom:2px solid #e5e7eb; margin:-20px -20px 20px; padding:0 20px; }
  .crm-nav a { padding:12px 20px; color:#6b7280; text-decoration:none; font-weight:500; border-bottom:2px solid transparent; margin-bottom:-2px; transition: all 0.2s; }
  .crm-nav a:hover { color:#003581; background:#fff; }
  .crm-nav a.active { color:#003581; border-bottom-color:#003581; }
  .filters { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
  .filters .form-group { margin-bottom:0; }
  .grid-2 { display:grid; grid-template-columns: 1.2fr 1fr; gap:16px; }
  .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
  .kpi-card { display:flex; flex-direction:column; gap:8px; }
  .kpi-title { font-size:12px; letter-spacing:.4px; text-transform:uppercase; color:#6b7280; font-weight:700; }
  .kpi-value { font-size:28px; font-weight:800; color:#003581; }
  .kpi-sub { font-size:12px; color:#6b7280; }
  .ring { width:64px; height:64px; border-radius:50%; background: conic-gradient(#003581 calc(var(--p)*1%), #e5e7eb 0); display:grid; place-items:center; position:relative; }
  .ring::before { content:''; position:absolute; inset:8px; background:#fff; border-radius:50%; }
  .ring > span { position:relative; font-size:12px; font-weight:700; color:#1f2937; }
  .progress-wrap { background:#f3f4f6; border-radius:9999px; overflow:hidden; height:10px; }
  .progress-bar { height:10px; background:#003581; }
  .muted { color:#6b7280; }
  table.table th, table.table td { font-size: 13px; }
  @media (max-width: 1024px) { .grid-2 { grid-template-columns: 1fr; } .crm-nav { overflow-x:auto; }  }
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <h1>📊 CRM Dashboard</h1>
      <p class="muted" style="margin:6px 0 0;">Track leads, activities, and performance metrics.</p>
    </div>

    <!-- Navigation Bar -->
    <nav class="crm-nav">
      <a href="./leads/index.php">Leads</a>
      <a href="./calls/index.php">Calls</a>
      <a href="./meetings/index.php">Meetings</a>
      <a href="./visits/index.php">Visits</a>
      <a href="./tasks/index.php">Tasks</a>
    </nav>

    <!-- Filters -->
    <form method="get" class="card" style="margin-bottom:16px;">
      <div class="filters">
        <div class="form-group">
          <label>Start Date</label>
          <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($filters['start']); ?>" />
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($filters['end']); ?>" />
        </div>
        <div class="form-group">
          <label>Employee</label>
          <select name="employee_id" class="form-control">
            <option value="0">All</option>
            <?php foreach ($opts['employees'] as $emp): $val=(int)$emp['id']; ?>
              <option value="<?php echo $val; ?>" <?php echo ($filters['employee_id']===$val)?'selected':''; ?>><?php 
                echo htmlspecialchars(($emp['employee_code']?($emp['employee_code'].' - '):'').$emp['first_name'].' '.$emp['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Lead Source</label>
          <select name="lead_source" class="form-control">
            <option value="">All</option>
            <?php foreach ($opts['sources'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($filters['lead_source']===$s)?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>&nbsp;</label>
          <button type="submit" class="btn">Apply Filters</button>
        </div>
      </div>
    </form>

    <!-- KPI CARDS -->
    <div class="kpi-grid">
      <div class="card kpi-card">
        <div class="kpi-title">Total Leads</div>
        <div class="kpi-value"><?php echo (int)$kpis['total_leads']; ?></div>
        <?php $delta = (int)$kpis['total_leads'] - (int)$kpisPrev['total_leads']; $deltaCls = $delta>=0?'#16a34a':'#dc2626'; $deltaArrow=$delta>=0?'▲':'▼'; ?>
        <div class="kpi-sub" style="color: <?php echo $deltaCls; ?>;"><?php echo $deltaArrow; ?> <?php echo ($delta>=0?'+':'').$delta; ?> vs prev</div>
      </div>

      <div class="card kpi-card">
        <div class="kpi-title">Active Leads</div>
        <?php $activePct = $kpis['total_leads']? round(($kpis['active_leads']/$kpis['total_leads'])*100,1):0; $activePrevPct = $kpisPrev['total_leads']? round(($kpisPrev['active_leads']/$kpisPrev['total_leads'])*100,1):0; $d = round($activePct-$activePrevPct,1); $cls = $d>=0?'#16a34a':'#dc2626'; $arr = $d>=0?'▲':'▼'; ?>
        <div style="display:flex;align-items:center;gap:12px;">
          <div class="ring" style="--p: <?php echo max(0,min(100,$activePct)); ?>;">
            <span><?php echo $activePct; ?>%</span>
          </div>
          <div>
            <div class="kpi-value" style="margin:0; line-height:1;"><?php echo (int)$kpis['active_leads']; ?></div>
            <div class="kpi-sub muted">New + Contacted <span style="color: <?php echo $cls; ?>; margin-left:6px;"><?php echo $arr.' '.(($d>=0?'+':'').$d).' pp'; ?></span></div>
          </div>
        </div>
      </div>

      <div class="card kpi-card">
        <div class="kpi-title">Conversion Rate</div>
        <div class="kpi-value"><?php echo $kpis['conversion_rate']; ?>%</div>
        <?php $d = round($kpis['conversion_rate'] - $kpisPrev['conversion_rate'],1); $cls = $d>=0?'#16a34a':'#dc2626'; $arr = $d>=0?'▲':'▼'; ?>
        <div class="kpi-sub" style="color: <?php echo $cls; ?>;"><?php echo $arr.' '.(($d>=0?'+':'').$d); ?> pp vs prev</div>
      </div>

      <div class="card kpi-card">
        <div class="kpi-title">Open Tasks</div>
        <div class="kpi-value"><?php echo (int)$summary['tasks_open']; ?></div>
        <div class="kpi-sub muted">Due Today: <?php echo (int)$summary['tasks_due_today']; ?></div>
      </div>

      <div class="card kpi-card">
        <div class="kpi-title">Overdue Tasks</div>
        <div class="kpi-value" style="color:#dc2626;">
          <?php echo (int)$summary['tasks_overdue']; ?>
        </div>
        <div class="kpi-sub muted">Resolve ASAP</div>
      </div>

      <div class="card kpi-card">
        <div class="kpi-title">Today Calls</div>
        <div class="kpi-value"><?php echo (int)$summary['calls_today']; ?></div>
        <div class="kpi-sub">
          <a class="btn btn-secondary" href="./calls/index.php">Open</a>
        </div>
      </div>

      <div class="card kpi-card">
        <div class="kpi-title">Today Meetings</div>
        <div class="kpi-value"><?php echo (int)$summary['meetings_today']; ?></div>
        <div class="kpi-sub">
          <a class="btn btn-secondary" href="./meetings/<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>">Open</a>
        </div>
      </div>

      <div class="card kpi-card">
        <div class="kpi-title">Today Visits</div>
        <div class="kpi-value"><?php echo (int)$summary['visits_today']; ?></div>
        <div class="kpi-sub">
          <a class="btn btn-secondary" href="./visits/<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>">Open</a>
        </div>
      </div>
    </div>

    <div style="height:12px;"></div>

    <div class="section-grid">
      <div>
        <div class="card">
          <div class="card-title">Deadlines Focus</div>
          <div class="lists-grid">
            <div>
              <div class="badge badge-danger" style="margin-bottom:10px;">Overdue</div>
              <ul class="mini-list">
                <?php if (empty($deadlines['overdue'])): ?>
                  <li class="muted">No overdue items. 🎉</li>
                <?php else: foreach ($deadlines['overdue'] as $it): ?>
                  <li>
                    <div>
                      <span class="title"><?php echo htmlspecialchars($it['title']); ?></span>
                      <span class="meta">• <?php echo htmlspecialchars($it['type']); ?></span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                      <span class="meta"><?php echo date('d M', strtotime($it['due'])); ?></span>
                      <a class="btn btn-secondary" href="<?php echo $it['link']; ?>">Open</a>
                    </div>
                  </li>
                <?php endforeach; endif; ?>
              </ul>
            </div>

            <div>
              <div class="badge badge-warning" style="margin-bottom:10px;">Due Today</div>
              <ul class="mini-list">
                <?php if (empty($deadlines['today'])): ?>
                  <li class="muted">Nothing due today.</li>
                <?php else: foreach ($deadlines['today'] as $it): ?>
                  <li>
                    <div>
                      <span class="title"><?php echo htmlspecialchars($it['title']); ?></span>
                      <span class="meta">• <?php echo htmlspecialchars($it['type']); ?></span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                      <span class="meta"><?php echo date('H:i', strtotime($it['due'])); ?></span>
                      <a class="btn btn-secondary" href="<?php echo $it['link']; ?>">Open</a>
                    </div>
                  </li>
                <?php endforeach; endif; ?>
              </ul>
            </div>

            <div>
              <div class="badge badge-info" style="margin-bottom:10px;">This Week</div>
              <ul class="mini-list">
                <?php if (empty($deadlines['week'])): ?>
                  <li class="muted">You're clear for the week.</li>
                <?php else: foreach ($deadlines['week'] as $it): ?>
                  <li>
                    <div>
                      <span class="title"><?php echo htmlspecialchars($it['title']); ?></span>
                      <span class="meta">• <?php echo htmlspecialchars($it['type']); ?></span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                      <span class="meta"><?php echo date('D d M', strtotime($it['due'])); ?></span>
                      <a class="btn btn-secondary" href="<?php echo $it['link']; ?>">Open</a>
                    </div>
                  </li>
                <?php endforeach; endif; ?>
              </ul>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Quick Actions</div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn" href="./leads/add.php">+ Add Lead</a>
            <a class="btn" href="./tasks/add.php">+ Add Task</a>
            <a class="btn" href="./meetings/add.php">+ Schedule Meeting</a>
            <a class="btn" href="./calls/add.php">+ Log Call</a>
            <a class="btn" href="./visits/add.php">+ Log Visit</a>
          </div>
        </div>
      </div>

      <div>
        <div class="card">
          <div class="card-title">Leads by Status</div>
          <canvas id="chartLeadsByStatus" height="220"></canvas>
        </div>
        <div class="card">
          <div class="card-title">Top Owners (30d)</div>
          <canvas id="chartTopOwners" height="240"></canvas>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Activity - Last 7 Days</div>
      <canvas id="chartActivities7" height="90"></canvas>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const brandBlue = '#003581';
  const brandAmber = '#faa718';
  const gray = '#6b7280';

  // Leads by Status Doughnut
  (function(){
    const ctx = document.getElementById('chartLeadsByStatus');
    if (!ctx) return;
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($leadsChart['labels']); ?>,
        datasets: [{
          data: <?php echo json_encode($leadsChart['data']); ?>,
          backgroundColor: [brandBlue, brandAmber, '#16a34a', '#dc2626'],
          borderWidth: 0
        }]
      },
      options: {
        plugins: { legend: { position:'bottom' } },
        cutout: '60%'
      }
    });
  })();

  // Top Owners Stacked Bar
  (function(){
    const ctx = document.getElementById('chartTopOwners');
    if (!ctx) return;
    const labels = <?php echo json_encode($owners30['labels']); ?>;
    const series = <?php echo json_encode($owners30['series']); ?>;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label:'Calls', data: series.Calls || [], backgroundColor: brandBlue },
          { label:'Meetings', data: series.Meetings || [], backgroundColor: brandAmber },
          { label:'Visits', data: series.Visits || [], backgroundColor: '#10b981' },
          { label:'Tasks', data: series.Tasks || [], backgroundColor: '#6366f1' },
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { x: { stacked:true }, y: { stacked:true, beginAtZero:true, ticks: { precision:0 } } }
      }
    });
  })();

  // Activities Last 7 Days - Multi-line
  (function(){
    const ctx = document.getElementById('chartActivities7');
    if (!ctx) return;
    const labels = <?php echo json_encode($activities7['labels']); ?>;
    const s = <?php echo json_encode($activities7['series']); ?>;
    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label:'Calls', data: s.Calls || [], borderColor: brandBlue, backgroundColor: brandBlue, tension: .3 },
          { label:'Meetings', data: s.Meetings || [], borderColor: brandAmber, backgroundColor: brandAmber, tension: .3 },
          { label:'Visits', data: s.Visits || [], borderColor: '#10b981', backgroundColor: '#10b981', tension: .3 },
          { label:'Tasks', data: s.Tasks || [], borderColor: '#6366f1', backgroundColor: '#6366f1', tension: .3 },
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision:0 } } }
      }
    });
  })();
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
