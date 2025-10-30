<?php
require_once __DIR__ . '/common.php';

authz_require_permission($conn, 'crm_visits', 'view_own');

if (!crm_tables_exist($conn)) {
    require_once __DIR__ . '/../onboarding.php';
    exit;
}

$current_employee_id = crm_current_employee_id($conn, $CURRENT_USER_ID);

// Get permissions
$visits_permissions = authz_get_permission_set($conn, 'crm_visits');

// Detect available columns
$has_assigned_to = crm_visits_has_column($conn, 'assigned_to');
$has_lead_id = crm_visits_has_column($conn, 'lead_id');
$has_outcome = crm_visits_has_column($conn, 'outcome');
$has_follow_up_date = crm_visits_has_column($conn, 'follow_up_date');

// If assigned_to doesn't exist, show warning and fallback to all visits
if (!$has_assigned_to) {
    flash_add('warning', 'The assigned_to column is not available in your schema. Showing all visits as a fallback.', 'crm');
}

// Filter handling
$filter_lead = isset($_GET['lead']) && is_numeric($_GET['lead']) ? (int)$_GET['lead'] : 0;
$filter_date_from = isset($_GET['date_from']) && $_GET['date_from'] ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) && $_GET['date_to'] ? $_GET['date_to'] : '';
$search = isset($_GET['search']) && $_GET['search'] ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$select_cols = crm_visits_select_columns($conn);

$joins = "";
if ($has_lead_id) {
    $joins .= "LEFT JOIN crm_leads l ON c.lead_id = l.id ";
}

$where = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

// Filter by assigned employee if column exists
if ($has_assigned_to && $current_employee_id > 0) {
    $where[] = "c.assigned_to = ?";
    $types .= 'i';
    $params[] = $current_employee_id;
}

// Apply filters
if ($has_lead_id && $filter_lead > 0) {
    $where[] = "c.lead_id = ?";
    $types .= 'i';
    $params[] = $filter_lead;
}

if ($filter_date_from) {
    $where[] = "DATE(c.visit_date) >= ?";
    $types .= 's';
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $where[] = "DATE(c.visit_date) <= ?";
    $types .= 's';
    $params[] = $filter_date_to;
}

if ($search) {
    $where[] = "(c.title LIKE ? OR c.notes LIKE ?)";
    $types .= 'ss';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM crm_visits c WHERE $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($count_stmt && $types) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
if ($count_stmt) {
    mysqli_stmt_execute($count_stmt);
    $count_res = mysqli_stmt_get_result($count_stmt);
    $total_count = $count_res ? mysqli_fetch_assoc($count_res)['total'] : 0;
    if ($count_res) mysqli_free_result($count_res);
    mysqli_stmt_close($count_stmt);
} else {
    $total_count = 0;
}

$total_pages = $total_count > 0 ? ceil($total_count / $per_page) : 1;

// Fetch visits
$lead_select = $has_lead_id ? ", l.name AS lead_name, l.company_name AS lead_company" : "";

$sql = "SELECT $select_cols $lead_select
        FROM crm_visits c
        $joins
        WHERE $where_clause
        ORDER BY c.visit_date DESC
        LIMIT ? OFFSET ?";

$types .= 'ii';
$params[] = $per_page;
$params[] = $offset;

$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $types) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

$visits = [];
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $visits[] = $row;
    }
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

// Fetch filter options - leads
$leads = [];
if ($has_lead_id) {
    $leads = crm_fetch_active_leads_for_visits($conn);
}

// Metrics
$metrics_where = "deleted_at IS NULL";
$metrics_params = [];
$metrics_types = '';

if ($has_assigned_to && $current_employee_id > 0) {
    $metrics_where .= " AND assigned_to = ?";
    $metrics_types = 'i';
    $metrics_params[] = $current_employee_id;
}

$total_visits = 0;
$upcoming_visits = 0;
$completed_visits = 0;

$metrics_sql = "SELECT COUNT(*) as total,
                SUM(CASE WHEN visit_date >= NOW() THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN visit_date < NOW() THEN 1 ELSE 0 END) as completed
                FROM crm_visits WHERE $metrics_where";

$metrics_stmt = mysqli_prepare($conn, $metrics_sql);
if ($metrics_stmt && $metrics_types) {
    mysqli_stmt_bind_param($metrics_stmt, $metrics_types, ...$metrics_params);
}
if ($metrics_stmt) {
    mysqli_stmt_execute($metrics_stmt);
    $metrics_res = mysqli_stmt_get_result($metrics_stmt);
    if ($metrics_res) {
        $metrics = mysqli_fetch_assoc($metrics_res);
        $total_visits = $metrics['total'] ?? 0;
        $upcoming_visits = $metrics['upcoming'] ?? 0;
        $completed_visits = $metrics['completed'] ?? 0;
        mysqli_free_result($metrics_res);
    }
    mysqli_stmt_close($metrics_stmt);
}

$page_title = 'My Visits - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>🚗 My Visits</h1>
          <p>Manage your assigned field visits</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="../index.php" class="btn btn-secondary">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-accent">🚗 All Visits</a>
          <a href="add.php" class="btn">➕ Log Visit</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Statistics Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($total_visits); ?></div>
        <div>Total Visits</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#16a34a 0%,#22c55e 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($upcoming_visits); ?></div>
        <div>Upcoming Visits</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($completed_visits); ?></div>
        <div>Completed Visits</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">🔍 Search & Filter Visits</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
        <?php if ($has_lead_id): ?>
        <div class="form-group" style="margin:0;">
          <label>Lead</label>
          <select name="lead" class="form-control">
            <option value="">All Leads</option>
            <?php foreach ($leads as $lead): ?>
              <option value="<?php echo $lead['id']; ?>" <?php echo $filter_lead == $lead['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($lead['name'] . (isset($lead['company_name']) && $lead['company_name'] ? ' (' . $lead['company_name'] . ')' : '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin:0;">
          <label>Date From</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>

        <div class="form-group" style="margin:0;">
          <label>Date To</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
        </div>

        <div class="form-group" style="margin:0;">
          <label>Search</label>
          <input type="text" name="search" class="form-control" placeholder="Title or notes..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div>
          <button type="submit" class="btn" style="width:100%;">Search</button>
        </div>
        <div>
          <a href="my.php" class="btn btn-accent" style="width:100%;text-decoration:none;display:block;text-align:center;">Clear</a>
        </div>
      </form>
    </div>

    <!-- Visits List -->
    <div class="card">
      <?php if (empty($visits)): ?>
        <div class="alert alert-info" style="margin:0;">No visits assigned to you yet. Log your first visit!</div>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <h3 style="margin:0;color:#003581;">📋 Your Visits (<?php echo count($visits); ?>)</h3>
        </div>

        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Title</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Visit Date</th>
                <?php if ($has_lead_id): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Lead</th><?php endif; ?>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Notes</th>
                <?php if ($has_outcome): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Outcome</th><?php endif; ?>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($visits as $visit): ?>
                <?php
                  $visit_time = strtotime($visit['visit_date']);
                  $is_upcoming = $visit_time >= time();
                ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td style="padding:12px;">
                    <div style="font-weight:600;color:#003581;"><a href="view.php?id=<?php echo (int)$visit['id']; ?>" style="color:#003581;text-decoration:none;"><?php echo htmlspecialchars(crm_visit_get($visit, 'title')); ?></a></div>
                    <?php if ($is_upcoming): ?>
                      <span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:500;display:inline-block;margin-top:4px;">Upcoming</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;font-size:13px;">
                    <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($visit['visit_date']))); ?>
                  </td>
                  <?php if ($has_lead_id): ?>
                    <td style="padding:12px;">
                      <?php if (isset($visit['lead_name']) && $visit['lead_name']): ?>
                        <div style="font-weight:500;color:#003581;"><a href="../leads/view.php?id=<?php echo (int)$visit['lead_id']; ?>" style="color:#003581;text-decoration:none;">
                          <?php echo htmlspecialchars($visit['lead_name']); ?>
                        </a></div>
                        <?php if (isset($visit['lead_company']) && $visit['lead_company']): ?>
                          <div style="font-size:12px;color:#6c757d;"><?php echo htmlspecialchars($visit['lead_company']); ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span style="color:#6c757d;">—</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td style="padding:12px;font-size:13px;max-width:300px;overflow:hidden;text-overflow:ellipsis;">
                    <?php echo htmlspecialchars(crm_visit_get($visit, 'notes', '—')); ?>
                  </td>
                  <?php if ($has_outcome): ?>
                    <td style="padding:12px;font-size:13px;max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                      <?php echo htmlspecialchars(crm_visit_get($visit, 'outcome', '—')); ?>
                    </td>
                  <?php endif; ?>
                  <td style="padding:12px;text-align:center;white-space:nowrap;">
                    <a href="view.php?id=<?php echo $visit['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;text-decoration:none;background:#17a2b8;color:#fff;">View</a>
                    <a href="edit.php?id=<?php echo $visit['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;text-decoration:none;">Edit</a>
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
