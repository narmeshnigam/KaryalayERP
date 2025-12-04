<?php
require_once __DIR__ . '/common.php';

// Enforce permission to view calls
authz_require_permission($conn, 'crm_calls', 'view_all');

$calls_permissions = authz_get_permission_set($conn, 'crm_calls');

// Detect available columns
$has_follow_up_date = crm_calls_has_column($conn, 'follow_up_date');
$has_follow_up_type = crm_calls_has_column($conn, 'follow_up_type');

// Filters
$filter_outcome = trim($_GET['outcome'] ?? '');
$filter_employee = isset($_GET['employee']) && $_GET['employee'] !== '' ? (int)$_GET['employee'] : null;
$filter_lead = isset($_GET['lead']) && $_GET['lead'] !== '' ? (int)$_GET['lead'] : null;
$filter_search = trim($_GET['search'] ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to = trim($_GET['date_to'] ?? '');

// Fetch metrics
$metrics = [
    'total' => 0,
    'today' => 0,
    'this_week' => 0,
    'pending_followup' => 0
];

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM crm_calls WHERE deleted_at IS NULL");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    $metrics['total'] = (int)($row['total'] ?? 0);
    mysqli_free_result($res);
}

$res = mysqli_query($conn, "SELECT COUNT(*) AS today FROM crm_calls WHERE deleted_at IS NULL AND DATE(call_date) = CURDATE()");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    $metrics['today'] = (int)($row['today'] ?? 0);
    mysqli_free_result($res);
}

$res = mysqli_query($conn, "SELECT COUNT(*) AS this_week FROM crm_calls WHERE deleted_at IS NULL AND YEARWEEK(call_date, 1) = YEARWEEK(CURDATE(), 1)");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    $metrics['this_week'] = (int)($row['this_week'] ?? 0);
    mysqli_free_result($res);
}

if ($has_follow_up_date) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS pending FROM crm_calls WHERE deleted_at IS NULL AND follow_up_date IS NOT NULL AND follow_up_date >= CURDATE()");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $metrics['pending_followup'] = (int)($row['pending'] ?? 0);
        mysqli_free_result($res);
    }
}

// Build query
$select_cols = crm_calls_select_columns($conn);
$where = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

// Determine if lead_id exists so we can safely LEFT JOIN and filter by lead
$has_lead_id = crm_calls_has_column($conn, 'lead_id');

// Check which optional columns exist
$has_assigned_to = crm_calls_has_column($conn, 'assigned_to');

if ($filter_outcome !== '') {
    $where[] = "c.outcome = ?";
    $params[] = $filter_outcome;
    $types .= 's';
}
if ($filter_employee !== null && $has_assigned_to) {
    $where[] = "c.assigned_to = ?";
    $params[] = $filter_employee;
    $types .= 'i';
} elseif ($filter_employee !== null) {
    // If assigned_to doesn't exist, clear the filter since we can't use it
    $filter_employee = null;
}
if ($filter_lead !== null && $has_lead_id) {
    $where[] = "c.lead_id = ?";
    $params[] = $filter_lead;
    $types .= 'i';
}
if ($filter_search !== '') {
    $where[] = "(c.title LIKE ? OR c.summary LIKE ?)";
    $search_param = '%' . $filter_search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}
if ($filter_date_from !== '') {
    $where[] = "DATE(c.call_date) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}
if ($filter_date_to !== '') {
    $where[] = "DATE(c.call_date) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

$where_clause = implode(' AND ', $where);
$order_by = "c.call_date DESC";

// Build JOINs and SELECTs only for columns that exist
$joins = "";
if ($has_assigned_to) {
    $joins .= "LEFT JOIN employees e ON c.assigned_to = e.id ";
}
if ($has_lead_id) {
    $joins .= "LEFT JOIN crm_leads l ON c.lead_id = l.id ";
}

$lead_select = '';
if ($has_lead_id) {
    $lead_select = ", l.name AS lead_name, l.company_name AS lead_company";
}

$emp_select = '';
if ($has_assigned_to) {
    $emp_select = ", e.first_name AS emp_first, e.last_name AS emp_last";
}

$sql = "SELECT $select_cols $lead_select $emp_select
        FROM crm_calls c
        $joins
        WHERE $where_clause
        ORDER BY $order_by";

$stmt = mysqli_prepare($conn, $sql);
$calls = [];
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $calls[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads($conn);
$outcomes = crm_call_outcomes();

$page_title = 'All Calls - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>



<style>
.calls-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.calls-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}

@media (max-width:768px){
.calls-header-flex{flex-direction:column;align-items:stretch;}
.calls-header-buttons{width:100%;flex-direction:column;gap:10px;}
.calls-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.calls-header-flex h1{font-size:1.5rem;}
}

/* Stats Grid Responsive */
.calls-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;}

@media (max-width:768px){
.calls-stats-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
}

@media (max-width:480px){
.calls-stats-grid{grid-template-columns:1fr;gap:12px;}
.calls-stats-grid .card{padding:16px;text-align:center;}
.calls-stats-grid .card>div:first-child{font-size:28px;}
}

/* Filter Form Responsive */
.calls-filter-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;}

@media (max-width:768px){
.calls-filter-form{grid-template-columns:repeat(2,1fr);gap:12px;}
}

@media (max-width:480px){
.calls-filter-form{grid-template-columns:1fr;gap:12px;}
.form-control{font-size:16px;}
.form-group{margin:0;}
}

/* Table Responsive - Card Style on Mobile */
.calls-table-wrapper{overflow-x:auto;}

@media (max-width:600px){
.calls-table-wrapper table{display:block;}
.calls-table-wrapper thead{display:none;}
.calls-table-wrapper tbody{display:block;}
.calls-table-wrapper tr{display:block;margin-bottom:16px;border:1px solid #dee2e6;border-radius:6px;overflow:hidden;}
.calls-table-wrapper td{display:block;padding:12px;border:none;border-bottom:1px solid #e9ecef;text-align:left;}
.calls-table-wrapper td:last-child{border-bottom:none;}
.calls-table-wrapper td::before{content:attr(data-label);font-weight:600;color:#003581;display:block;font-size:12px;margin-bottom:4px;}
.calls-table-wrapper td[style*="text-align: center"]{text-align:left;}
}

@media (max-width:480px){
.calls-table-wrapper tr{margin-bottom:12px;padding:0;}
.calls-table-wrapper td{padding:10px;font-size:13px;}
.calls-table-wrapper td::before{font-size:11px;margin-bottom:2px;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="calls-header-flex">
        <div>
          <h1>☎️ All Calls</h1>
          <p>View and manage all telephonic interactions</p>
        </div>
        <div class="calls-header-buttons">
          <a href="../index.php" class="btn btn-secondary">← CRM Dashboard</a>
          <a href="my.php" class="btn btn-accent">☎️ My Calls</a>
          <a href="add.php" class="btn">➕ Log New Call</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Statistics Cards -->
    <div class="calls-stats-grid">
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['total']); ?></div>
        <div>Total Calls</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#0284c7 0%,#06b6d4 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['today']); ?></div>
        <div>Today's Calls</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#7c3aed 0%,#a855f7 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['this_week']); ?></div>
        <div>This Week</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#ea580c 0%,#f97316 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['pending_followup']); ?></div>
        <div>Pending Follow-Ups</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">🔍 Search & Filter Calls</h3>
      <form method="get" class="calls-filter-form">
        <div class="form-group" style="margin:0;">
          <label>Outcome</label>
          <select name="outcome" class="form-control">
            <option value="">All Outcomes</option>
            <?php foreach ($outcomes as $o): ?>
              <option value="<?php echo htmlspecialchars($o); ?>" <?php echo $filter_outcome === $o ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($o); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($has_assigned_to): ?>
        <div class="form-group" style="margin:0;">
          <label>Assigned To</label>
          <select name="employee" class="form-control">
            <option value="">All Employees</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo (int)$emp['id']; ?>" <?php echo $filter_employee === (int)$emp['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(trim($emp['first_name'] . ' ' . $emp['last_name'])); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <?php if ($has_lead_id): ?>
        <div class="form-group" style="margin:0;">
          <label>Lead</label>
          <select name="lead" class="form-control">
            <option value="">All Leads</option>
            <?php foreach ($leads as $l): ?>
              <option value="<?php echo (int)$l['id']; ?>" <?php echo $filter_lead === (int)$l['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($l['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin:0;">
          <label>Search</label>
          <input type="text" name="search" class="form-control" 
                 value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Title or summary...">
        </div>

        <div class="form-group" style="margin:0;">
          <label>Call Date From</label>
          <input type="date" name="date_from" class="form-control"
                 value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>

        <div class="form-group" style="margin:0;">
          <label>Call Date To</label>
          <input type="date" name="date_to" class="form-control"
                 value="<?php echo htmlspecialchars($filter_date_to); ?>">
        </div>

        <div>
          <button type="submit" class="btn" style="width:100%;">Apply Filters</button>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent" style="width:100%;text-decoration:none;display:block;text-align:center;">Clear</a>
        </div>
      </form>
    </div>

    <!-- Calls Table -->
    <div class="card">
      <h3 style="margin:0 0 16px;color:#003581;">📋 Call Records (<?php echo count($calls); ?>)</h3>
      <?php if (empty($calls)): ?>
        <div style="text-align:center;padding:40px;color:#6c757d;">
          <p style="font-size:18px;margin:0 0 8px;">No calls found</p>
          <p style="margin:0;">Try adjusting your filters or <a href="add.php" style="color:#003581;text-decoration:none;font-weight:600;">log a new call</a>.</p>
        </div>
      <?php else: ?>
        <div class="calls-table-wrapper">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Call Date</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Title</th>
                <?php if ($has_lead_id): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Lead</th><?php endif; ?>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Outcome</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Duration</th>
                <?php if ($has_assigned_to): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Assigned To</th><?php endif; ?>
                <?php if ($has_follow_up_date): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Follow-Up</th><?php endif; ?>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($calls as $call): ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td data-label="Call Date" style="padding:12px;font-size:13px;"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($call['call_date']))); ?></td>
                  <td data-label="Title" style="padding:12px;"><strong><?php echo htmlspecialchars($call['title']); ?></strong></td>
                  <?php if ($has_lead_id): ?>
                    <td data-label="Lead" style="padding:12px;">
                      <?php if (!empty($call['lead_id'])): ?>
                        <a href="../leads/view.php?id=<?php echo (int)$call['lead_id']; ?>" style="color:#003581;text-decoration:none;font-weight:500;">
                          <?php echo htmlspecialchars($call['lead_name'] ?? 'Lead #' . $call['lead_id']); ?>
                        </a>
                      <?php else: ?>
                        <span style="color:#9ca3af;">—</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td data-label="Outcome" style="padding:12px;">
                    <span class="badge <?php echo crm_call_outcome_badge($call['outcome'] ?? null); ?>">
                      <?php echo htmlspecialchars($call['outcome'] ?? 'N/A'); ?>
                    </span>
                  </td>
                  <td data-label="Duration" style="padding:12px;font-size:13px;"><?php echo crm_format_duration($call['duration'] ?? null); ?></td>
                  <?php if ($has_assigned_to): ?>
                    <td data-label="Assigned To" style="padding:12px;">
                      <span style="background:#e3f2fd;color:#003581;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:500;display:inline-block;">
                        <?php echo htmlspecialchars(trim(($call['emp_first'] ?? '') . ' ' . ($call['emp_last'] ?? ''))); ?>
                      </span>
                    </td>
                  <?php endif; ?>
                  <?php if ($has_follow_up_date): ?>
                    <td data-label="Follow-Up" style="padding:12px;">
                      <?php if (!empty($call['follow_up_date'])): ?>
                        <?php echo htmlspecialchars(date('d M Y', strtotime($call['follow_up_date']))); ?>
                        <?php if ($has_follow_up_type && !empty($call['follow_up_type'])): ?>
                          <br><small style="color:#6b7280;font-size:11px;"><?php echo htmlspecialchars($call['follow_up_type']); ?></small>
                        <?php endif; ?>
                      <?php else: ?>
                        <span style="color:#9ca3af;">—</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td data-label="Actions" style="padding:12px;text-align:center;white-space:nowrap;">
                    <a href="view.php?id=<?php echo (int)$call['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;text-decoration:none;background:#17a2b8;color:#fff;">View</a>
                    <a href="edit.php?id=<?php echo (int)$call['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;text-decoration:none;">Edit</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
