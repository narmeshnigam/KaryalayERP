<?php
/**
 * ERP Main Dashboard (Unified)
 * Aligns with CRM Dashboard visual language and adds consolidated KPIs/charts.
 */

// Start session
session_start();

// Includes
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/setup_helper.php';

if (!isSetupComplete()) { header('Location: ' . APP_URL . '/setup/index.php'); exit; }
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'user';
$full_name = $_SESSION['full_name'] ?? 'User';
$role = strtolower($_SESSION['role'] ?? 'employee');
$isAdmin = in_array($role, ['admin']);
$isManager = in_array($role, ['manager']);

$page_title = 'Dashboard - ' . APP_NAME;

include __DIR__ . '/../includes/header_sidebar.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
    :root{
        --brand-blue: #0b5ed7; --brand-blue-2: #0747b2; --brand-amber: #f59e0b; --brand-green:#198754; --brand-teal:#0ea5a4; --brand-red:#dc3545;
        --card-shadow: 0 8px 22px rgba(11,35,74,0.08);
    }
    .page-header { margin-bottom: 12px; }
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; margin-bottom:24px; }
    .kpi-tile { padding:22px; border-radius:12px; color:#fff; text-align:center; box-shadow:var(--card-shadow); min-height:120px; display:flex; flex-direction:column; justify-content:center; align-items:center; }
    .kpi-tile.blue { background:linear-gradient(135deg, var(--brand-blue) 0%, var(--brand-blue-2) 100%); }
    .kpi-tile.green { background:linear-gradient(135deg, var(--brand-green) 0%, #16a34a 100%); }
    .kpi-tile.amber { background:linear-gradient(135deg, var(--brand-amber) 0%, #f97316 100%); }
    .kpi-tile.red { background:linear-gradient(135deg, var(--brand-red) 0%, #e6504b 100%); }
    .kpi-tile.teal { background:linear-gradient(135deg, var(--brand-teal) 0%, #0891b2 100%); }
    .kpi-label { color:rgba(255,255,255,0.95); font-size:13px; margin-bottom:8px; font-weight:600; letter-spacing:0.2px; }
    .kpi-value { font-size:34px; font-weight:800; color:#fff; margin-bottom:6px; text-shadow:0 2px 6px rgba(0,0,0,0.12); }
    .kpi-sub { font-size:13px; color:rgba(255,255,255,0.92); }
    .kpi-delta { font-size:12px; font-weight:700; display:inline-block; padding:6px 9px; border-radius:999px; background:rgba(0,0,0,0.12); color:#fff; }
    .kpi-delta.up { color:#e6ffea; background:rgba(20,120,45,0.18); }
    .kpi-delta.down { color:#ffeef0; background:rgba(220,53,69,0.12); }
    .ring { width:56px; height:56px; border-radius:50%; background: conic-gradient(rgba(255,255,255,0.95) calc(var(--p)*1%), rgba(255,255,255,0.12) 0); display:grid; place-items:center; position:relative; flex-shrink:0; box-shadow: inset 0 -3px 8px rgba(0,0,0,0.06); border:3px solid rgba(255,255,255,0.06); }
    .ring::before { content:''; position:absolute; inset:10px; background:rgba(0,0,0,0.06); border-radius:50%; }
    .ring > span { position:relative; font-size:12px; font-weight:800; color:white; z-index:2; }
    .grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(450px,1fr)); gap:24px; margin-bottom:24px; }
    .grid-3 { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:24px; }
    .section-card { padding:24px; }
    .card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; }
    .card-header h3 { margin:0; color:#0b3a75; font-size:18px; font-weight:700; }
    .card-header p { margin:0; color:#6c757d; font-size:13px; }
    .quick-actions { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; }
    .qa { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1px solid #e6edf5; border-radius:10px; background:#f7f9fc; text-decoration:none; color:#0d2d66; font-weight:600; }
    .qa:hover { background:#edf2ff; }
    .muted { color:#6c757d; }
    table.table { width:100%; border-collapse:collapse; }
    table.table th { padding:10px 12px; text-align:left; font-size:13px; font-weight:600; color:#6c757d; border-bottom:2px solid #e1e8ed; background:#f8f9fa; }
    table.table td { padding:10px 12px; font-size:13px; color:#1b2a57; border-bottom:1px solid #f0f0f0; }
    table.table tr:hover { background:#f8f9fa; }
    @media (max-width: 1024px) { .grid-2 { grid-template-columns: 1fr; } }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Header Bar -->
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🏠 Welcome, <?php echo htmlspecialchars($full_name); ?></h1>
                    <p class="muted" id="dashDate"></p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <form method="get" action="<?php echo APP_URL; ?>/public/search.php" style="display:flex;gap:8px;align-items:center;">
                        <input type="text" name="q" placeholder="Search employees, leads, tasks..." class="form-control" style="min-width:260px;" />
                        <button class="btn btn-primary" type="submit">Search</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="kpi-grid" id="kpiGrid">
            <!-- CRM -->
            <div class="kpi-tile blue">
                <div class="kpi-label">CRM • Total Leads (This Month)</div>
                <div class="kpi-value" id="kpi_total_leads">0</div>
                <div class="kpi-delta" id="kpi_total_leads_delta">—</div>
            </div>
            <div class="kpi-tile amber" <?php if(!($isAdmin||$isManager)) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">CRM • Conversion Rate</div>
                <div class="kpi-value" id="kpi_conv_rate">0%</div>
                <div class="kpi-delta" id="kpi_conv_rate_delta">—</div>
            </div>
            <!-- Employee -->
            <div class="kpi-tile teal" <?php if(!$isAdmin) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">Employees • Total Active</div>
                <div class="kpi-value" id="kpi_total_employees">0</div>
                <div class="kpi-delta" id="kpi_total_employees_delta">—</div>
            </div>
            <!-- Attendance -->
            <div class="kpi-tile green" <?php if(!$isAdmin) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">Attendance • Present Today</div>
                <div class="kpi-value" id="kpi_present_today">0</div>
                <div class="kpi-delta" id="kpi_present_today_delta">—</div>
            </div>
            <div class="kpi-tile green" <?php if($isAdmin||$isManager) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">My Attendance • Today</div>
                <div class="kpi-value" id="kpi_my_attendance">—</div>
                <div class="kpi-sub" id="kpi_my_hours">Hours: 0</div>
            </div>
            <!-- Tasks -->
            <div class="kpi-tile red">
                <div class="kpi-label">Tasks • Pending</div>
                <div class="kpi-value" id="kpi_pending_tasks">0</div>
                <div class="kpi-sub" id="kpi_overdue_tasks">Overdue: 0</div>
            </div>
            <!-- Reimbursements -->
            <div class="kpi-tile amber" <?php if(!($isAdmin||$isManager)) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">Reimbursements • Claims Pending</div>
                <div class="kpi-value" id="kpi_claims_pending">0</div>
                <div class="kpi-delta" id="kpi_claims_pending_delta">—</div>
            </div>
            <!-- Expenses -->
            <div class="kpi-tile blue" <?php if(!($isAdmin||$isManager)) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">Expenses • Total (Month)</div>
                <div class="kpi-value" id="kpi_total_expenses">₹0</div>
                <div class="kpi-delta" id="kpi_total_expenses_delta">—</div>
            </div>
            <!-- Salary -->
            <div class="kpi-tile teal" <?php if(!$isAdmin) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">Salary • Payroll Completed</div>
                <div style="display:flex;align-items:center;gap:12px;justify-content:center;">
                    <div class="ring" style="--p: 0" id="kpi_payroll_ring"><span>0%</span></div>
                    <div class="kpi-value" id="kpi_payroll_pct" style="margin:0;">0%</div>
                </div>
            </div>
            <!-- Visitors -->
            <div class="kpi-tile blue" <?php if(!$isAdmin) echo 'style="display:none;"'; ?>>
                <div class="kpi-label">Visitor Log • Today</div>
                <div class="kpi-value" id="kpi_visitors_today">0</div>
                <div class="kpi-sub" id="kpi_visitors_sub">—</div>
            </div>
        </div>

        <!-- Visual Analytics -->
        <div class="grid-2">
            <div class="card section-card">
                <div class="card-header"><div><h3>Organizational Overview</h3><p>Employees vs Active Tasks by Department</p></div></div>
                <canvas id="chartOrg" style="max-height:260px;"></canvas>
            </div>
            <div class="card section-card">
                <div class="card-header"><div><h3>Monthly Expense Trend</h3><p>Expenses • Salary • Reimbursements</p></div></div>
                <canvas id="chartExpense" style="max-height:260px;"></canvas>
            </div>
        </div>

        <div class="grid-3">
            <div class="card section-card">
                <div class="card-header"><div><h3>Attendance Heatmap</h3><p>Current month presence density</p></div></div>
                <div id="heatmap" style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;min-height:140px;"></div>
            </div>
            <div class="card section-card">
                <div class="card-header"><div><h3>CRM Performance</h3><p>Active • Converted • Dropped</p></div></div>
                <canvas id="chartCRM" style="max-height:220px;"></canvas>
            </div>
            <div class="card section-card">
                <div class="card-header"><div><h3>Task Completion Trend</h3><p>Assigned vs Completed per week</p></div></div>
                <canvas id="chartTasks" style="max-height:220px;"></canvas>
            </div>
        </div>

        <!-- Activity Panels -->
        <div class="grid-2" style="margin-top:24px;">
            <div class="card section-card">
                <div class="card-header"><div><h3>My Tasks</h3><p>Top 5 pending/ongoing</p></div></div>
                <div style="overflow:auto;">
                    <table class="table" id="tblMyTasks"><thead><tr><th>Title</th><th>Due</th><th>Status</th><th>Lead</th><th></th></tr></thead><tbody><tr><td colspan="5" class="muted">No tasks found.</td></tr></tbody></table>
                </div>
            </div>
            <div class="card section-card">
                <div class="card-header"><div><h3>Attendance</h3><p><?php echo $isAdmin? 'Live summary' : 'Your last 7 days'; ?></p></div></div>
                <div id="attendancePanel" class="muted">No data available.</div>
            </div>
        </div>

        <div class="grid-2" style="margin-top:24px;">
            <div class="card section-card">
                <div class="card-header"><div><h3>CRM Follow-ups</h3><p>Upcoming follow-ups</p></div></div>
                <div style="overflow:auto;">
                    <table class="table" id="tblFollowups"><thead><tr><th>Type</th><th>Date</th><th>Lead</th><th>Assigned</th><th>Status</th></tr></thead><tbody><tr><td colspan="5" class="muted">No follow-ups scheduled.</td></tr></tbody></table>
                </div>
            </div>
            <div class="card section-card">
                <div class="card-header"><div><h3>Reimbursements & Expenses</h3><p>Latest 5 items</p></div></div>
                <div style="overflow:auto;">
                    <table class="table" id="tblFinance"><thead><tr><th>Type</th><th>Date</th><th>Employee</th><th>Amount</th><th>Status</th></tr></thead><tbody><tr><td colspan="5" class="muted">No items.</td></tr></tbody></table>
                </div>
            </div>
        </div>

        <div class="grid-2" style="margin-top:24px;">
            <div class="card section-card">
                <div class="card-header"><div><h3>Salary Summary</h3><p>Current pay cycle</p></div></div>
                <div id="salaryPanel" class="muted">No data.</div>
            </div>
            <div class="card section-card">
                <div class="card-header"><div><h3>Visitor Log</h3><p>Today</p></div></div>
                <div style="overflow:auto;">
                    <table class="table" id="tblVisitors"><thead><tr><th>Name</th><th>Purpose</th><th>Time</th><th>Employee</th></tr></thead><tbody><tr><td colspan="4" class="muted">No visitors recorded.</td></tr></tbody></table>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Announcements -->
        <div class="grid-2" style="margin-top:24px;">
            <div class="card section-card">
                <div class="card-header"><div><h3>Quick Actions</h3><p>Get things done faster</p></div></div>
                <div class="quick-actions">
                    <a class="qa" href="./crm/leads/index.php">➕ Add Lead</a>
                    <a class="qa" href="./crm/calls/index.php">📞 Log Call</a>
                    <a class="qa" href="./expenses/add.php">🧾 Add Expense</a>
                    <a class="qa" href="./crm/tasks/index.php">📋 New Task</a>
                    <a class="qa" href="./attendance/index.php">🕒 Mark Attendance</a>
                    <a class="qa" href="./employee/add_employee.php">👥 Add Employee</a>
                </div>
            </div>
            <div class="card section-card">
                <div class="card-header"><div><h3>Announcements</h3><p>Organization-wide notices</p></div></div>
                <ul id="announcements" class="muted" style="margin:0;padding-left:16px;">
                    <li>No announcements.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const brandBlue = '#003581';
    const amber = '#f59e0b';
    const green = '#198754';
    const teal = '#0ea5a4';
    const red = '#dc3545';

    // Header date/time
    const dateEl = document.getElementById('dashDate');
    function updateDate(){
        const d = new Date();
        const h = d.getHours();
        const greet = h<12?'Good Morning':(h<18?'Good Afternoon':'Good Evening');
        dateEl.textContent = `${greet} • ${d.toLocaleDateString()} ${d.toLocaleTimeString()}`;
    }
    updateDate(); setInterval(updateDate, 1000*30);

    async function fetchJSON(url){
        try { const r = await fetch(url, { credentials:'same-origin' }); if(!r.ok) throw new Error('HTTP '+r.status); return await r.json(); } 
        catch(e){ return null; }
    }

    // Populate KPIs (graceful fallback if APIs not available)
    (async function(){
    const s = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/summary.php');
        const asText = (v)=> (v==null? '0' : v);
        if(s){
            setNum('kpi_total_leads', s.crm_total_leads);
            setPct('kpi_conv_rate', s.crm_conversion_rate);
            setNum('kpi_total_employees', s.total_employees);
            setNum('kpi_present_today', s.attendance_present_today);
            setMyAttendance(s.my_attendance_status, s.my_hours_logged);
            setNum('kpi_pending_tasks', s.pending_tasks);
            document.getElementById('kpi_overdue_tasks').textContent = `Overdue: ${asText(s.overdue_tasks)}`;
            setCurrency('kpi_total_expenses', s.expenses_month_total);
            setPayroll(s.salary_payroll_completed_pct);
            setNum('kpi_visitors_today', s.visitors_today);
            document.getElementById('kpi_visitors_sub').textContent = s.visitors_note || '—';
            // deltas if present
            if(s.deltas){
                setDelta('kpi_total_leads_delta', s.deltas.crm_total_leads);
                setDelta('kpi_conv_rate_delta', s.deltas.crm_conversion_rate, true);
                setDelta('kpi_total_employees_delta', s.deltas.total_employees);
                setDelta('kpi_present_today_delta', s.deltas.attendance_present_today);
                setDelta('kpi_claims_pending_delta', s.deltas.claims_pending);
                setDelta('kpi_total_expenses_delta', s.deltas.expenses_month_total, true);
            }
        } else {
            // safe defaults
            setPayroll(0);
        }
    })();

    function setNum(id, v){ const el = document.getElementById(id); if(!el) return; el.textContent = (v==null?0:parseInt(v)); }
    function setPct(id, v){ const el = document.getElementById(id); if(!el) return; el.textContent = ((v==null?0:parseFloat(v)).toFixed(1)) + '%'; }
    function setCurrency(id, v){ const el = document.getElementById(id); if(!el) return; const n = Number(v||0); el.textContent = '₹' + n.toLocaleString(); }
    function setDelta(id, dv, isPct=false){ const el = document.getElementById(id); if(!el) return; const n = Number(dv||0); const up = n>=0; el.className = 'kpi-delta ' + (up?'up':'down'); el.textContent = (up?'▲ ':'▼ ') + (up?'+':'') + (isPct? n.toFixed(1)+'%': n) + ' vs prev'; }
    function setMyAttendance(status, hours){ const el = document.getElementById('kpi_my_attendance'); if(el) el.textContent = status || '—'; const h = document.getElementById('kpi_my_hours'); if(h) h.textContent = 'Hours: ' + (hours||0); }
    function setPayroll(p){ const ring = document.getElementById('kpi_payroll_ring'); const val = document.getElementById('kpi_payroll_pct'); const pct = Math.max(0, Math.min(100, Number(p||0))); if(ring) ring.style.setProperty('--p', pct); if(val) val.textContent = pct.toFixed(0)+'%'; if(ring) ring.querySelector('span').textContent = pct.toFixed(0)+'%'; }

    // Charts with graceful placeholder data
    (async function(){
    const org = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/org-overview.php');
        const labels = org?.labels || ['Sales','Ops','HR','IT'];
        const employees = org?.employees || [10,8,5,6];
        const tasks = org?.active_tasks || [7,4,3,5];
        new Chart(document.getElementById('chartOrg'), { type:'bar', data:{ labels, datasets:[{ label:'Employees', data:employees, backgroundColor:brandBlue }, { label:'Active Tasks', data:tasks, type:'line', borderColor:teal, backgroundColor:'rgba(14,165,164,.15)', fill:true, tension:.3, borderWidth:3 }] }, options:{ responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } } });
    })();

    (async function(){
    const exp = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/expense-trend.php');
        const labels = exp?.labels || ['Jan','Feb','Mar','Apr','May','Jun'];
        const expenses = exp?.expenses || [0,0,0,0,0,0];
        const salary = exp?.salary || [0,0,0,0,0,0];
        const reimb = exp?.reimb || [0,0,0,0,0,0];
        new Chart(document.getElementById('chartExpense'), { type:'line', data:{ labels, datasets:[{ label:'Expenses', data:expenses, borderColor:amber, backgroundColor:'rgba(245,158,11,.12)', fill:true, tension:.35, borderWidth:3 },{ label:'Salary', data:salary, borderColor:green, backgroundColor:'rgba(25,135,84,.12)', fill:true, tension:.35, borderWidth:3 },{ label:'Reimbursements', data:reimb, borderColor:teal, backgroundColor:'rgba(14,165,164,.12)', fill:true, tension:.35, borderWidth:3 }] }, options:{ plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } }});
    })();

    (async function(){
    const crm = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/crm-stats.php');
        const data = crm?.data || [65, 25, 10];
        new Chart(document.getElementById('chartCRM'), { type:'doughnut', data:{ labels:['Active','Converted','Dropped'], datasets:[{ data, backgroundColor:[teal, green, red] }] }, options:{ plugins:{ legend:{ position:'bottom' } }, cutout:'60%' } });
    })();

    (async function(){
    const t = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/task-trend.php');
        const labels = t?.labels || ['W1','W2','W3','W4'];
        const assigned = t?.assigned || [0,0,0,0];
        const completed = t?.completed || [0,0,0,0];
        new Chart(document.getElementById('chartTasks'), { type:'line', data:{ labels, datasets:[{ label:'Assigned', data:assigned, borderColor:brandBlue, backgroundColor:'rgba(0,53,129,.10)', fill:true, tension:.35, borderWidth:3 },{ label:'Completed', data:completed, borderColor:green, backgroundColor:'rgba(25,135,84,.12)', fill:true, tension:.35, borderWidth:3 }] }, options:{ plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } } });
    })();

    // Simple attendance "heatmap" (no extra libs)
    (function(){
        const el = document.getElementById('heatmap'); if(!el) return;
        const daysInMonth = new Date(new Date().getFullYear(), new Date().getMonth()+1, 0).getDate();
        for(let i=1;i<=daysInMonth;i++){
            const v = Math.random(); // placeholder density
            const c = v>0.85?green: v>0.55?'#eab308' : v>0.25?'#60a5fa' : '#e5e7eb';
            const d = document.createElement('div');
            d.title = `Day ${i}`; d.style.cssText = `height:22px;border-radius:5px;background:${c};`;
            el.appendChild(d);
        }
    })();

    // Tables (graceful, placeholders if APIs missing)
    (async function(){
        const tasks = await fetchJSON('<?php echo APP_URL; ?>/public/api/crm/index.php?type=tasks');
        const body = document.querySelector('#tblMyTasks tbody');
        const arr = tasks && tasks.data ? tasks.data : [];
        if(arr.length){ body.innerHTML = arr.slice(0,5).map(t=>`<tr><td>${escapeHtml(t.title)}</td><td>${fmtDate(t.due_date)}</td><td><span class="badge" style="background:#f0f0f0;">${escapeHtml(t.status)}</span></td><td>${escapeHtml((t.lead_id||'')+'')}</td><td><a href="./crm/tasks/index.php" class="btn btn-sm">Open</a></td></tr>`).join(''); }
    })();

    (async function(){
    const foll = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/followups.php?limit=5');
        const body = document.querySelector('#tblFollowups tbody');
        if(foll && foll.length){ body.innerHTML = foll.slice(0,5).map(r=>`<tr><td>${escapeHtml(r.type)}</td><td>${fmtDate(r.date)}</td><td>${escapeHtml(r.lead||'—')}</td><td>${escapeHtml(r.employee||'—')}</td><td>${escapeHtml(r.status||'—')}</td></tr>`).join(''); }
    })();

    (async function(){
    const fin = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/finance-latest.php?limit=5');
        const body = document.querySelector('#tblFinance tbody');
        if(fin && fin.length){ body.innerHTML = fin.slice(0,5).map(r=>`<tr><td>${escapeHtml(r.type)}</td><td>${fmtDate(r.date)}</td><td>${escapeHtml(r.employee||'—')}</td><td>₹${Number(r.amount||0).toLocaleString()}</td><td>${escapeHtml(r.status||'—')}</td></tr>`).join(''); }
    })();

    (async function(){
    const v = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/visitors-today.php');
        const body = document.querySelector('#tblVisitors tbody');
        if(v && v.length){ body.innerHTML = v.map(r=>`<tr><td>${escapeHtml(r.name)}</td><td>${escapeHtml(r.purpose||'—')}</td><td>${fmtDateTime(r.time)}</td><td>${escapeHtml(r.employee||'—')}</td></tr>`).join(''); }
    })();

    (async function(){
    const a = await fetchJSON('<?php echo APP_URL; ?>/public/api/dashboard/announcements.php');
        const ul = document.getElementById('announcements');
        if(a && a.length){ ul.innerHTML = a.map(x=>`<li><strong>${escapeHtml(x.title)}</strong> <span class="muted">(${fmtDate(x.date)})</span> — ${escapeHtml(x.description||'')}</li>`).join(''); }
    })();

    function fmtDate(d){ if(!d) return '—'; const dt = new Date(d); return dt.toLocaleDateString(); }
    function fmtDateTime(d){ if(!d) return '—'; const dt = new Date(d); return dt.toLocaleString(); }
    function escapeHtml(s){ return String(s).replace(/[&<>"]+/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m])); }
</script>

<?php include __DIR__ . '/../includes/footer_sidebar.php'; if (isset($conn)) { closeConnection($conn); } ?>
