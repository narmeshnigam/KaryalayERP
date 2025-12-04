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
$page_title = 'CRM - Quality Dashboard - ' . APP_NAME;
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
$trend = crm_activities_trend($conn, $filters);
$perf = crm_employee_performance($conn, $filters);
$matrix = crm_followup_matrix($conn, $filters);
$recent = crm_recent_interactions($conn, $filters, 20);
?>

<style>
  :root{
    --brand-blue: #0b5ed7;
    --brand-blue-2: #0747b2;
    --brand-amber: #f59e0b;
    --brand-green: #198754;
    --brand-teal: #0ea5a4;
    --brand-red: #dc3545;
    --card-shadow: 0 8px 22px rgba(11,35,74,0.08);
  }

  /* Tighten header/subnav spacing */
  .page-header { margin-bottom: 12px; }
  /* Improve subnav visibility */
  .crm-subnav { background:#f7f9fc; border:1px solid #e6edf5; border-radius:8px; padding:8px 12px; margin-bottom:16px; box-shadow: inset 0 -1px 0 rgba(0,0,0,0.03); position:sticky; top:0; z-index:5; display:flex; align-items:center; gap:12px; }
  .crm-subnav .sub-links { display:flex; gap:10px; flex-wrap:wrap; }
  .crm-subnav a { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:6px; color:#0d2d66; text-decoration:none; font-weight:600; font-size:14px; transition: background 0.2s, color 0.2s; }
  .crm-subnav a:hover { background:#edf2ff; color:var(--brand-blue); }

  .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; margin-bottom:24px; }
  .kpi-tile { padding:22px; border-radius:12px; color:var(--tile-text, #fff); text-align:center; box-shadow:var(--card-shadow); min-height:120px; display:flex; flex-direction:column; justify-content:center; align-items:center; }
  .kpi-tile.blue { background:linear-gradient(135deg, var(--brand-blue) 0%, var(--brand-blue-2) 100%); }
  .kpi-tile.green { background:linear-gradient(135deg, var(--brand-green) 0%, #16a34a 100%); }
  .kpi-tile.amber { background:linear-gradient(135deg, var(--brand-amber) 0%, #f97316 100%); }
  .kpi-tile.red { background:linear-gradient(135deg, var(--brand-red) 0%, #e6504b 100%); }
  .kpi-tile.teal { background:linear-gradient(135deg, var(--brand-teal) 0%, #0891b2 100%); }

  .kpi-label { color:rgba(255,255,255,0.95); font-size:13px; margin-bottom:8px; font-weight:600; letter-spacing:0.2px; }
  .kpi-value { font-size:34px; font-weight:800; color:#fff; margin-bottom:6px; text-shadow:0 2px 6px rgba(0,0,0,0.12); }
  .kpi-sub { font-size:13px; color:rgba(255,255,255,0.92); opacity:0.95; }

  .kpi-delta { font-size:12px; font-weight:700; display:inline-block; padding:6px 9px; border-radius:999px; background:rgba(0,0,0,0.12); color:#fff; }
  .kpi-delta.up { color:#e6ffea; background:rgba(20,120,45,0.18); }
  .kpi-delta.down { color:#ffeef0; background:rgba(220,53,69,0.12); }

  .ring { width:64px; height:64px; border-radius:50%; background: conic-gradient(rgba(255,255,255,0.95) calc(var(--p)*1%), rgba(255,255,255,0.12) 0); display:grid; place-items:center; position:relative; flex-shrink:0; box-shadow: inset 0 -3px 8px rgba(0,0,0,0.06); border:3px solid rgba(255,255,255,0.06); }
  .ring::before { content:''; position:absolute; inset:10px; background:rgba(0,0,0,0.06); border-radius:50%; }
  .ring > span { position:relative; font-size:12px; font-weight:800; color:white; z-index:2; }

  .grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(450px,1fr)); gap:24px; margin-bottom:24px; }
  .card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; }
  .card-header h3 { margin:0 0 4px 0; color:#0b3a75; font-size:18px; font-weight:700; }
  .card-header p { margin:0; color:#6c757d; font-size:13px; }

  .progress-wrap { background:#f3f4f6; border-radius:9999px; overflow:hidden; height:10px; }
  .progress-bar { height:10px; background:var(--brand-blue); border-radius:9999px; }
  .muted { color:#6c757d; }
  .badge { padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; }
  .badge-success { background:#d1f4e0; color:#1e7e34; }
  .badge-warning { background:#fff3cd; color:#856404; }
  .badge-danger { background:#f8d7da; color:#721c24; }
  table.table { width:100%; border-collapse:collapse; }
  table.table th { padding:12px 16px; text-align:left; font-size:13px; font-weight:600; color:#6c757d; border-bottom:2px solid #e1e8ed; background:#f8f9fa; }
  table.table td { padding:12px 16px; font-size:13px; color:#1b2a57; border-bottom:1px solid #f0f0f0; }
  table.table tr:hover { background:#f8f9fa; }
  .chart-container { padding:24px; }
  /* optional: small tweak for subnav filter toggle alignment */
  .filters-toggle { margin-left:auto; }

  /* Layout Alignment & Spacing */
  .crm-section-full {
    margin-bottom: 24px;
  }

  .crm-section-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
  }

  .crm-section-full .card,
  .crm-section-grid .card {
    display: flex;
    flex-direction: column;
  }

  .crm-table-section {
    margin-top: 0;
    margin-bottom: 0;
  }

  @media (max-width: 1024px) { 
    .grid-2 { grid-template-columns: 1fr; }
    .crm-section-grid { grid-template-columns: 1fr; }
  }


  /* Mobile Responsiveness for CRM Dashboard */
  .crm-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
  }
  
  .crm-header-title h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
  }
  
  .crm-header-title p {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
  }
  
  .crm-header-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .crm-subnav-responsive {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .crm-filter-form-responsive {
    display: grid;
    gap: 20px;
  }

  .crm-filter-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    align-items: end;
  }

  .crm-filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .crm-kpi-grid-responsive {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
  }

  .crm-matrix-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
  }

  .crm-table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .crm-table-responsive table {
    min-width: 100%;
  }

  /* Tablet (600px - 899px) */
  @media (max-width: 899px) {
    .crm-header-flex {
      flex-direction: column;
      align-items: flex-start;
    }

    .crm-header-buttons {
      width: 100%;
      justify-content: flex-start;
    }

    .crm-header-buttons .btn {
      flex: 1;
      min-width: 140px;
      text-align: center;
    }

    .crm-subnav-responsive {
      flex-direction: column;
      align-items: stretch;
      gap: 8px;
    }

    .crm-subnav .sub-links {
      flex-direction: column;
      gap: 0;
    }

    .crm-subnav a {
      border-radius: 0;
      padding: 10px 14px;
      width: 100%;
      justify-content: flex-start;
    }

    .crm-filter-fields {
      grid-template-columns: 1fr;
    }

    .crm-kpi-grid-responsive {
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .kpi-tile {
      padding: 16px;
      min-height: 100px;
    }

    .kpi-label {
      font-size: 12px;
    }

    .kpi-value {
      font-size: 24px;
    }

    .kpi-sub {
      font-size: 12px;
    }

    .ring {
      width: 52px;
      height: 52px;
    }

    .ring > span {
      font-size: 11px;
    }

    table.table th,
    table.table td {
      padding: 10px 12px;
      font-size: 12px;
    }
  }

  /* Mobile (max 600px) */
  @media (max-width: 599px) {
    .crm-header-title h1 {
      font-size: 22px;
    }

    .crm-header-title p {
      font-size: 13px;
    }

    .crm-header-buttons {
      width: 100%;
    }

    .crm-header-buttons .btn {
      flex: 1;
      font-size: 13px;
      padding: 10px 8px;
    }

    .crm-subnav {
      padding: 6px 10px;
      gap: 8px;
      flex-direction: column;
    }

    .crm-subnav .sub-links {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      width: 100%;
    }

    .crm-subnav a {
      border-radius: 4px;
      padding: 8px 10px;
      font-size: 13px;
    }

    .filters-toggle {
      width: 100%;
      margin-left: 0;
    }

    .crm-filter-form-responsive {
      gap: 16px;
    }

    .crm-filter-fields {
      grid-template-columns: 1fr;
      gap: 12px;
    }

    .crm-filter-buttons {
      gap: 8px;
    }

    .crm-filter-buttons .btn {
      flex: 1;
      font-size: 13px;
    }

    .crm-kpi-grid-responsive {
      grid-template-columns: 1fr;
      gap: 10px;
    }

    .kpi-tile {
      padding: 14px;
      min-height: 90px;
    }

    .kpi-label {
      font-size: 11px;
      margin-bottom: 6px;
    }

    .kpi-value {
      font-size: 20px;
      margin-bottom: 4px;
    }

    .kpi-sub {
      font-size: 11px;
    }

    .kpi-delta {
      font-size: 11px;
      padding: 4px 8px;
      margin-top: 2px;
    }

    .ring {
      width: 48px;
      height: 48px;
      flex-shrink: 0;
    }

    .ring > span {
      font-size: 10px;
    }

    .grid-2 {
      grid-template-columns: 1fr;
    }

    .card-header h3 {
      font-size: 16px;
    }

    .card-header p {
      font-size: 12px;
    }

    table.table th,
    table.table td {
      padding: 8px 10px;
      font-size: 11px;
    }

    table.table th {
      background: #f0f0f0;
    }

    .card {
      padding: 16px !important;
    }

    .crm-matrix-grid {
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .progress-wrap {
      height: 8px;
    }

    .badge {
      padding: 3px 8px;
      font-size: 11px;
    }

    #chartTrend,
    #chartFunnel,
    #chartPerf {
      max-height: 180px !important;
    }
  }
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="crm-header-flex">
        <div class="crm-header-title">
          <h1>📊 CRM Dashboard</h1>
          <p>KPIs, trends, activity quality, and follow-up compliance.</p>
        </div>
        <div class="crm-header-buttons">
          <a href="./reports.php" class="btn">📈 Reports</a>
          <a href="./calendar.php" class="btn btn-secondary">📆 Calendar</a>
        </div>
      </div>
    </div>

    <nav class="crm-subnav crm-subnav-responsive" aria-label="CRM sections">
      <div class="sub-links">
        <a href="./leads/index.php">Leads</a>
        <a href="./calls/index.php">Calls</a>
        <a href="./meetings/index.php">Meetings</a>
        <a href="./visits/index.php">Visits</a>
        <a href="./tasks/index.php">Tasks</a>
      </div>
      <button type="button" id="toggleFilters" class="btn btn-secondary btn-sm filters-toggle" aria-expanded="false">Filters</button>
    </nav>

    <form method="get" class="card crm-filter-form-responsive" id="filterPanel" style="margin-bottom:24px;padding:24px; display:none;">
      <div class="crm-filter-fields">
        <div class="form-group" style="margin:0;">
          <label for="range">Quick Range</label>
          <?php $range = isset($_GET['range']) ? strtolower(trim($_GET['range'])) : 'custom'; ?>
          <select name="range" id="range" class="form-control">
            <option value="custom" <?php echo ($range==='custom')?'selected':''; ?>>Custom</option>
            <option value="today" <?php echo ($range==='today')?'selected':''; ?>>Today</option>
            <option value="yesterday" <?php echo ($range==='yesterday')?'selected':''; ?>>Yesterday</option>
            <option value="last7" <?php echo ($range==='last7')?'selected':''; ?>>Last 7 Days</option>
            <option value="thisweek" <?php echo ($range==='thisweek')?'selected':''; ?>>This Week</option>
            <option value="thismonth" <?php echo ($range==='thismonth')?'selected':''; ?>>This Month</option>
            <option value="lastmonth" <?php echo ($range==='lastmonth')?'selected':''; ?>>Last Month</option>
            <option value="next7" <?php echo ($range==='next7')?'selected':''; ?>>Next 7 Days</option>
            <option value="next30" <?php echo ($range==='next30')?'selected':''; ?>>Next 30 Days</option>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="start">Start Date</label>
          <input type="date" name="start" id="start" class="form-control" value="<?php echo htmlspecialchars($filters['start']); ?>" />
        </div>
        <div class="form-group" style="margin:0;">
          <label for="end">End Date</label>
          <input type="date" name="end" id="end" class="form-control" value="<?php echo htmlspecialchars($filters['end']); ?>" />
        </div>
        <div class="form-group" style="margin:0;">
          <label for="employee_id">Employee</label>
          <select name="employee_id" id="employee_id" class="form-control">
            <option value="0">All Employees</option>
            <?php foreach ($opts['employees'] as $emp): $val=(int)$emp['id']; ?>
              <option value="<?php echo $val; ?>" <?php echo ($filters['employee_id']===$val)?'selected':''; ?>><?php 
                echo htmlspecialchars(($emp['employee_code']?($emp['employee_code'].' - '):'').$emp['first_name'].' '.$emp['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="lead_source">Lead Source</label>
          <select name="lead_source" id="lead_source" class="form-control">
            <option value="">All Sources</option>
            <?php foreach ($opts['sources'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($filters['lead_source']===$s)?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:10px;align-items:end;">
          <button type="submit" class="btn btn-primary" style="flex:1;">Apply Filters</button>
          <a href="dashboard.php" class="btn btn-secondary" style="flex:1;text-align:center;text-decoration:none;">Reset</a>
        </div>
      </div>
    </form>

    <!-- KPI CARDS -->
    <div class="crm-kpi-grid-responsive">
      <div class="kpi-tile blue" style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <div class="kpi-label">Total Leads</div>
        <div class="kpi-value"><?php echo (int)$kpis['total_leads']; ?></div>
        <?php $delta = (int)$kpis['total_leads'] - (int)$kpisPrev['total_leads']; $isUp = $delta>=0; ?>
        <div class="kpi-delta <?php echo $isUp?'up':'down'; ?>"><?php echo $isUp?'▲':'▼'; ?> <?php echo ($delta>=0?'+':'').$delta; ?> vs prev period</div>
      </div>
      
      <div class="kpi-tile green" style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <div class="kpi-label">Active Leads</div>
        <?php $activePct = $kpis['total_leads']? round(($kpis['active_leads']/$kpis['total_leads'])*100,1):0; $activePrevPct = $kpisPrev['total_leads']? round(($kpisPrev['active_leads']/$kpisPrev['total_leads'])*100,1):0; $d = round($activePct-$activePrevPct,1); ?>
        <div style="display:flex;align-items:center;gap:14px;justify-content:center;width:100%;margin-bottom:8px;">
          <div class="ring" style="--p: <?php echo max(0,min(100,$activePct)); ?>;">
            <span><?php echo $activePct; ?>%</span>
          </div>
          <div>
            <div class="kpi-value green" style="margin:0;line-height:1;"><?php echo (int)$kpis['active_leads']; ?></div>
            <div class="kpi-sub">New + Contacted</div>
            <div class="kpi-delta <?php echo ($d>=0)?'up':'down'; ?>" style="margin-top:4px;"><?php echo ($d>=0)?'▲':'▼'; ?> <?php echo ($d>=0?'+':'').$d; ?>%</div>
          </div>
        </div>
      </div>
      
      <div class="kpi-tile amber" style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <div class="kpi-label">Conversion Rate</div>
        <div class="kpi-value amber"><?php echo $kpis['conversion_rate']; ?>%</div>
        <?php $d = round($kpis['conversion_rate'] - $kpisPrev['conversion_rate'],1); ?>
        <div class="kpi-delta <?php echo ($d>=0)?'up':'down'; ?>"><?php echo ($d>=0)?'▲':'▼'; ?> <?php echo ($d>=0?'+':'').$d; ?>% vs prev period</div>
      </div>
      
      <div class="kpi-tile blue" style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <div class="kpi-label">Follow-up Compliance</div>
        <div style="display:flex;align-items:center;gap:14px;justify-content:center;width:100%;margin-bottom:8px;">
          <div class="ring" style="--p: <?php echo max(0,min(100,$kpis['followup_compliance'])); ?>;">
            <span><?php echo $kpis['followup_compliance']; ?>%</span>
          </div>
          <div>
            <?php $d = round($kpis['followup_compliance'] - $kpisPrev['followup_compliance'],1); ?>
            <div class="kpi-sub">On-time completion</div>
            <div class="kpi-delta <?php echo ($d>=0)?'up':'down'; ?>" style="margin-top:4px;"><?php echo ($d>=0)?'▲':'▼'; ?> <?php echo ($d>=0?'+':'').$d; ?>%</div>
          </div>
        </div>
      </div>
      
      <div class="kpi-tile teal" style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <div class="kpi-label">Avg Response Time</div>
        <?php $d = round($kpis['avg_response_days'] - $kpisPrev['avg_response_days'],1); $improve = $d<=0; ?>
        <div class="kpi-value">⏱ <?php echo $kpis['avg_response_days']; ?> days</div>
        <div class="kpi-delta <?php echo $improve?'up':'down'; ?>"><?php echo $improve?'▼':'▲'; ?> <?php echo ($d>=0?'+':'').$d; ?> d vs prev period</div>
      </div>
      
      <div class="kpi-tile red" style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <div class="kpi-label">Pending Tasks</div>
        <div class="kpi-value"><?php echo (int)$kpis['pending_tasks']; ?></div>
        <?php $deltaPend = (int)$kpis['pending_tasks'] - (int)$kpisPrev['pending_tasks']; $deltaOver = (int)$kpis['overdue_tasks'] - (int)$kpisPrev['overdue_tasks']; ?>
        <div class="kpi-sub">Overdue: <?php echo (int)$kpis['overdue_tasks']; ?> <span style="font-weight:600;">(<?php echo ($deltaOver>=0?'+':'').$deltaOver; ?>)</span></div>
      </div>
    </div>

    <!-- Activity Volume - Full Width -->
    <div class="card chart-container crm-section-full">
      <div class="card-header">
        <div>
          <h3>Activity Volume – Selected Period</h3>
          <p>Daily trend of calls, meetings, visits and tasks based on filters</p>
        </div>
      </div>
      <canvas id="chartTrend" style="max-height:220px;"></canvas>
    </div>

    <!-- Lead Flow (Funnel) - Full Width -->
    <div class="card chart-container crm-section-full">
      <div class="card-header">
        <div>
          <h3>Lead Flow (Funnel)</h3>
          <p>Current lead statuses in selected period</p>
        </div>
      </div>
      <canvas id="chartFunnel" style="max-height:280px;"></canvas>
    </div>

    <!-- Employee Performance - Full Width -->
    <div class="card chart-container crm-section-full">
      <div class="card-header">
        <div>
          <h3>Employee Performance</h3>
          <p>Leads, conversion & follow-up comparison</p>
        </div>
      </div>
      <canvas id="chartPerf" style="max-height:280px;"></canvas>
    </div>

    <!-- Activity Quality (By Employee) - Full Width -->
    <div class="card crm-section-full" style="padding:32px;">
      <div class="card-header">
        <div>
          <h3>Activity Quality (By Employee)</h3>
          <p>Performance metrics per employee</p>
        </div>
      </div>
      <div class="crm-table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Leads</th><th>Calls</th><th>Meetings</th><th>Visits</th><th>Tasks</th>
              <th>Conv %</th><th>Follow %</th><th>Avg Resp (d)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($perf['rows'])): ?>
              <tr><td colspan="9" class="muted">No data for selected filters.</td></tr>
            <?php else: foreach ($perf['rows'] as $row): ?>
              <tr>
                <td style="font-weight:600;"><?php echo htmlspecialchars($row['employee']); ?></td>
                <td><?php echo (int)$row['leads']; ?></td>
                <td><?php echo (int)$row['calls']; ?></td>
                <td><?php echo (int)$row['meetings']; ?></td>
                <td><?php echo (int)$row['visits']; ?></td>
                <td><?php echo (int)$row['tasks_completed']; ?></td>
                <td>
                  <?php $cp = (float)$row['conv_pct']; $ccls = $cp>85?'badge-success':($cp>=60?'badge-warning':'badge-danger'); ?>
                  <span class="badge <?php echo $ccls; ?>"><?php echo number_format($cp, 1); ?>%</span>
                </td>
                <td>
                  <?php $fp = (float)$row['followup_pct']; $cls = $fp>85?'badge-success':($fp>=60?'badge-warning':'badge-danger'); ?>
                  <span class="badge <?php echo $cls; ?>"><?php echo number_format($fp, 1); ?>%</span>
                </td>
                <td><?php echo number_format((float)$row['avg_response_days'], 1); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Follow-up Compliance Matrix - Full Width -->
    <div class="card crm-section-full" style="padding:32px;">
      <div class="card-header">
        <div>
          <h3>Follow-up Compliance Matrix</h3>
          <p>On-time completion & scheduling metrics</p>
        </div>
      </div>
      <div class="crm-matrix-grid">
        <div>
          <div class="kpi-label">Total Follow-ups</div>
          <div class="kpi-value" style="font-size:24px;"><?php echo (int)$matrix['total_scheduled']; ?></div>
          <div class="kpi-sub">All modules</div>
        </div>
        <div>
          <div class="kpi-label">On-Time</div>
          <div class="kpi-value green" style="font-size:24px;"><?php echo $matrix['completed_on_time_pct']; ?>%</div>
          <div class="progress-wrap" style="margin-top:4px;"><div class="progress-bar" style="width:<?php echo (float)$matrix['completed_on_time_pct']; ?>%;background:#28a745;"></div></div>
        </div>
        <div>
          <div class="kpi-label">Delayed</div>
          <div class="kpi-value red" style="font-size:24px;"><?php echo $matrix['delayed_pct']; ?>%</div>
          <div class="progress-wrap" style="margin-top:4px;"><div class="progress-bar" style="width:<?php echo (float)$matrix['delayed_pct']; ?>%;background:#dc3545;"></div></div>
        </div>
        <div>
          <div class="kpi-label">Auto-generated</div>
          <div class="kpi-value" style="font-size:24px;"><?php echo $matrix['auto_from_leads_pct']; ?>%</div>
          <div class="kpi-sub">From leads</div>
        </div>
        <div>
          <div class="kpi-label">Avg Gap</div>
          <div class="kpi-value" style="font-size:24px;"><?php echo $matrix['avg_followup_gap_days']; ?> d</div>
          <div class="kpi-sub">Follow-up spacing</div>
        </div>
      </div>
    </div>

    <!-- Recent Interactions - Full Width -->
    <div class="card crm-section-full crm-table-section" style="padding:32px;">
      <div class="card-header">
        <div>
          <h3>Recent Interactions</h3>
          <p>Latest activity across all CRM modules</p>
        </div>
      </div>
      <div class="crm-table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th><th>Employee</th><th>Lead</th><th>Type</th><th>Outcome/Status</th><th>Next Follow-up</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent)): ?>
              <tr><td colspan="6" class="muted">No recent activities in selected period.</td></tr>
            <?php else: foreach ($recent as $r): ?>
              <tr>
                <td style="white-space:nowrap;"><?php echo date('d M Y H:i', strtotime($r['date'])); ?></td>
                <td><?php echo htmlspecialchars($r['employee']); ?></td>
                <td><?php echo htmlspecialchars($r['lead'] ?? '—'); ?></td>
                <td><span class="badge" style="background:#f0f0f0;color:#1b2a57;"><?php echo htmlspecialchars($r['type']); ?></span></td>
                <td><?php echo htmlspecialchars($r['outcome']); ?></td>
                <td><?php echo $r['next_followup'] ? date('d M Y', strtotime($r['next_followup'])) : '—'; ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const brandBlue = '#003581';
  const brandAmber = '#faa718';
  const green = '#28a745';
  const teal = '#14b8a6';
  const purple = '#8b5cf6';
  const red = '#dc3545';

  // Funnel Chart
  (function(){
    const ctx = document.getElementById('chartFunnel');
    if (!ctx) return;
    const labels = <?php echo json_encode($funnel['labels']); ?>;
    const data = <?php echo json_encode($funnel['data']); ?>;
    new Chart(ctx, {
      type: 'bar',
      data: { 
        labels, 
        datasets: [{ 
          label: 'Leads', 
          data, 
          backgroundColor: [brandBlue, brandAmber, green, red],
          borderWidth: 0,
          hoverOffset: 8
        }] 
      },
      options: { 
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: { 
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            cornerRadius: 6,
            titleFont: { size: 13, weight: 'bold' },
            bodyFont: { size: 13 }
          }
        }, 
        scales: { 
          x: { beginAtZero: true, ticks: { precision: 0, font: { size: 12 } }, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
          y: { ticks: { font: { size: 12 } }, grid: { display: false } }
        } 
      }
    });
  })();

  // Activity Trend
  (function(){
    const ctx = document.getElementById('chartTrend');
    if (!ctx) return;
    const labels = <?php echo json_encode($trend['labels']); ?>;
    const s = <?php echo json_encode($trend['series']); ?>;
    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Calls', data: s.Calls || [], borderColor: brandAmber, backgroundColor: 'rgba(250, 167, 24, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: brandAmber, pointBorderColor: '#fff', pointBorderWidth: 2 },
          { label: 'Meetings', data: s.Meetings || [], borderColor: green, backgroundColor: 'rgba(40, 167, 69, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: green, pointBorderColor: '#fff', pointBorderWidth: 2 },
          { label: 'Visits', data: s.Visits || [], borderColor: teal, backgroundColor: 'rgba(20, 184, 166, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: teal, pointBorderColor: '#fff', pointBorderWidth: 2 },
          { label: 'Tasks', data: s.Tasks || [], borderColor: brandBlue, backgroundColor: 'rgba(0, 53, 129, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: brandBlue, pointBorderColor: '#fff', pointBorderWidth: 2 }
        ]
      },
      options: { 
        responsive: true,
        maintainAspectRatio: true,
        plugins: { 
          legend: { position: 'bottom', labels: { font: { size: 13 }, padding: 15, usePointStyle: true } },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            cornerRadius: 6,
            titleFont: { size: 14, weight: 'bold' },
            bodyFont: { size: 13 }
          }
        }, 
        scales: { 
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 12 } }, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
          x: { ticks: { font: { size: 12 } }, grid: { display: false } }
        },
        interaction: { intersect: false, mode: 'index' }
      }
    });
  })();

  // Employee Performance
  (function(){
    const ctx = document.getElementById('chartPerf');
    if (!ctx) return;
    const labels = <?php echo json_encode($perf['chart']['labels']); ?>;
    const leads = <?php echo json_encode($perf['chart']['leads']); ?>;
    const conv = <?php echo json_encode($perf['chart']['conv_pct']); ?>;
    const foll = <?php echo json_encode($perf['chart']['followup_pct']); ?>;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Leads', data: leads, backgroundColor: brandBlue, borderWidth: 0 },
          { label: 'Conversion %', data: conv, backgroundColor: green, borderWidth: 0 },
          { label: 'Follow-up %', data: foll, backgroundColor: brandAmber, borderWidth: 0 }
        ]
      },
      options: { 
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: { 
          legend: { position: 'bottom', labels: { font: { size: 13 }, padding: 15, usePointStyle: true } },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            cornerRadius: 6,
            titleFont: { size: 13, weight: 'bold' },
            bodyFont: { size: 13 }
          }
        }, 
        scales: { 
          x: { beginAtZero: true, ticks: { font: { size: 12 } }, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
          y: { ticks: { font: { size: 12 } }, grid: { display: false } }
        } 
      }
    });
  })();
</script>

<script>
  // Quick Range -> auto-fill date inputs
  (function(){
    const sel = document.getElementById('range');
    const start = document.getElementById('start');
    const end = document.getElementById('end');
    if (!sel || !start || !end) return;

    const fmt = (d)=>{
      const pad = (n)=> String(n).padStart(2,'0');
      return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
    };

    const mondayOf = (d)=>{
      const day = d.getDay(); // 0=Sun..6=Sat
      const diff = (day === 0 ? -6 : 1 - day); // move to Monday
      const nd = new Date(d);
      nd.setDate(d.getDate() + diff);
      nd.setHours(0,0,0,0);
      return nd;
    };

    const lastDayOfMonth = (d)=> new Date(d.getFullYear(), d.getMonth()+1, 0);

    const setRange = (key)=>{
      const today = new Date(); today.setHours(0,0,0,0);
      let s = new Date(today), e = new Date(today);
      switch(key){
        case 'today':
          break;
        case 'yesterday':
          s.setDate(s.getDate()-1); e.setDate(e.getDate()-1); break;
        case 'last7':
          s.setDate(s.getDate()-6); break; // incl today
        case 'thisweek':
          s = mondayOf(today); e = new Date(s); e.setDate(s.getDate()+6); break;
        case 'thismonth':
          s = new Date(today.getFullYear(), today.getMonth(), 1);
          e = lastDayOfMonth(today); break;
        case 'lastmonth':
          const firstThis = new Date(today.getFullYear(), today.getMonth(), 1);
          s = new Date(today.getFullYear(), today.getMonth()-1, 1);
          e = new Date(firstThis-1); break;
        case 'next7':
          e.setDate(e.getDate()+6); break;
        case 'next30':
          e.setDate(e.getDate()+29); break;
        default:
          return; // custom – do not alter dates
      }
      start.value = fmt(s); end.value = fmt(e);
    };

    sel.addEventListener('change', (e)=> setRange(e.target.value));
  })();

  // Auto-refresh every 5 minutes (300000 ms)
  setTimeout(function(){ window.location.reload(); }, 300000);

  // Filters toggle
  (function(){
    const btn = document.getElementById('toggleFilters');
    const panel = document.getElementById('filterPanel');
    if (!btn || !panel) return;
    const setState = (expanded)=>{
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      panel.style.display = expanded ? '' : 'none';
    };
    let expanded = false; // default collapsed
    setState(expanded);
    btn.addEventListener('click', ()=>{ expanded = !expanded; setState(expanded); });
  })();
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
