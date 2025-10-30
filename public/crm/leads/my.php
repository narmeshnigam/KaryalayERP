<?php
require_once __DIR__ . '/common.php';

crm_leads_require_login();

// Enforce permission to view own leads
$leads_permissions = authz_get_permission_set($conn, 'crm_leads');
$can_view_own = $leads_permissions['can_view_own'] ?? false;
$can_view_all = $leads_permissions['can_view_all'] ?? false;

if (!$can_view_own && !$can_view_all) {
    flash_add('error', 'You do not have permission to view leads.', 'crm');
    header('Location: ../index.php');
    exit;
}

crm_leads_require_tables($conn);

$current_employee_id = crm_current_employee_id($conn, (int)$CURRENT_USER_ID);

if ($current_employee_id <= 0 && !$IS_SUPER_ADMIN && !$can_view_all) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    flash_add('error', 'Employee mapping not found. Contact administrator.', 'crm');
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $lead_id = (int)($_POST['lead_id'] ?? 0);
    if ($lead_id <= 0) {
        flash_add('error', 'Invalid lead selection.', 'crm');
        header('Location: my.php');
        exit;
    }

    $lead = crm_lead_fetch($conn, $lead_id);
    if (!$lead) {
        flash_add('error', 'Lead not found.', 'crm');
        header('Location: my.php');
        exit;
    }

    $can_manage = crm_role_can_manage($user_role);
    $owns_lead = ((int)($lead['assigned_to'] ?? 0) === $current_employee_id);
    if (!$owns_lead && !$can_manage) {
        flash_add('error', 'You cannot modify this lead.', 'crm');
        header('Location: my.php');
        exit;
    }

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        if (!in_array($new_status, crm_lead_allowed_statuses($lead['status'] ?? ''), true)) {
            flash_add('error', 'Status update not allowed.', 'crm');
            header('Location: my.php');
            exit;
        }

        $is_final = in_array($new_status, ['Converted','Dropped'], true);
        $query = $is_final
            ? 'UPDATE crm_leads SET status = ?, follow_up_date = NULL, follow_up_type = NULL, follow_up_created = 0, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
            : 'UPDATE crm_leads SET status = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL';

        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $lead_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flash_add('success', 'Lead status updated.', 'crm');
        }

        header('Location: my.php');
        exit;
    }

    if ($action === 'update_follow_up') {
        $follow_date = trim($_POST['follow_up_date'] ?? '');
        $follow_type = trim($_POST['follow_up_type'] ?? '');
        $errors = [];

        if ($follow_date !== '') {
            if (!crm_lead_allowed_follow_up($follow_date)) {
                $errors[] = 'Follow-up date cannot be in the past.';
            }
            if ($follow_type === '' || !in_array($follow_type, crm_lead_follow_up_types(), true)) {
                $errors[] = 'Select a valid follow-up type.';
            }
        } else {
            $follow_type = '';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash_add('error', $error, 'crm');
            }
            header('Location: my.php');
            exit;
        }

        $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET follow_up_date = ?, follow_up_type = ?, follow_up_created = 0, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        if ($stmt) {
            $dateParam = ($follow_date !== '') ? $follow_date : null;
            $typeParam = ($follow_type !== '') ? $follow_type : null;
            mysqli_stmt_bind_param($stmt, 'ssi', $dateParam, $typeParam, $lead_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flash_add('success', 'Follow-up updated.', 'crm');
        }

        header('Location: my.php');
        exit;
    }

    flash_add('error', 'Unsupported action.', 'crm');
    header('Location: my.php');
    exit;
}

// Check whether the database has follow_up_date column (older installs may not)
$has_follow_up_col = false;
$colRes = mysqli_query($conn, "SHOW COLUMNS FROM crm_leads LIKE 'follow_up_date'");
if ($colRes) {
  if (mysqli_num_rows($colRes) > 0) {
    $has_follow_up_col = true;
  }
  mysqli_free_result($colRes);
}

// Get sources for filter dropdown
$sources = [];
$sourceRes = mysqli_query($conn, "SELECT DISTINCT source FROM crm_leads WHERE source IS NOT NULL AND source <> '' ORDER BY source ASC");
if ($sourceRes) {
  while ($row = mysqli_fetch_assoc($sourceRes)) {
    $sources[] = (string)$row['source'];
  }
  mysqli_free_result($sourceRes);
}
if (!$sources) {
  $sources = crm_lead_sources();
}

// Process filters
$filter_status = $_GET['status'] ?? '';
$filter_source = $_GET['source'] ?? '';
$filter_search = trim($_GET['search'] ?? '');
$follow_from = $_GET['follow_from'] ?? '';
$follow_to = $_GET['follow_to'] ?? '';
$has_filters = ($filter_status !== '' || $filter_source !== '' || $filter_search !== '' || $follow_from !== '' || $follow_to !== '');

$where = ['l.deleted_at IS NULL'];
$params = [];
$types = '';

if ($current_employee_id > 0) {
    $where[] = 'l.assigned_to = ?';
    $types .= 'i';
    $params[] = $current_employee_id;
}

if ($filter_status !== '' && in_array($filter_status, crm_lead_statuses(), true)) {
    $where[] = 'l.status = ?';
    $types .= 's';
    $params[] = $filter_status;
}

if ($filter_source !== '' && in_array($filter_source, $sources, true)) {
    $where[] = 'l.source = ?';
    $types .= 's';
    $params[] = $filter_source;
}

if ($filter_search !== '') {
    $like = '%' . $filter_search . '%';
    $where[] = '(l.name LIKE ? OR l.company_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($follow_from !== '') {
  if ($has_follow_up_col) {
    $where[] = 'l.follow_up_date >= ?';
    $types .= 's';
    $params[] = $follow_from;
  }
}

if ($follow_to !== '') {
  if ($has_follow_up_col) {
    $where[] = 'l.follow_up_date <= ?';
    $types .= 's';
    $params[] = $follow_to;
  }
}

// Build ORDER BY clause based on available columns
$orderBy = $has_follow_up_col 
    ? 'ORDER BY l.follow_up_date IS NULL, l.follow_up_date ASC, l.created_at DESC'
    : 'ORDER BY l.created_at DESC';

$sql = 'SELECT l.*, 
               assignee.employee_code AS assigned_code,
               assignee.first_name AS assigned_first,
               assignee.last_name AS assigned_last,
               creator.first_name AS creator_first,
               creator.last_name AS creator_last,
               creator.employee_code AS creator_code
        FROM crm_leads l
        LEFT JOIN employees assignee ON assignee.id = l.assigned_to
        LEFT JOIN employees creator ON creator.id = l.created_by
        WHERE ' . implode(' AND ', $where) . '
        ' . $orderBy . '
        LIMIT 200';

$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

$leads = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $leads[] = $row;
    }
    mysqli_free_result($result);
    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
}

$page_title = 'My CRM Leads - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// Calculate metrics for assigned leads
$all_my_leads = [];
$metrics_where = ['l.deleted_at IS NULL'];
$metrics_params = [];
$metrics_types = '';
if ($current_employee_id > 0) {
    $metrics_where[] = 'l.assigned_to = ?';
    $metrics_types .= 'i';
    $metrics_params[] = $current_employee_id;
}

$metrics_sql = '';
if ($has_follow_up_col) {
  $metrics_sql = 'SELECT l.status, l.follow_up_date FROM crm_leads l WHERE ' . implode(' AND ', $metrics_where);
} else {
  // Older DB installs may not have follow_up_date; only select status
  $metrics_sql = 'SELECT l.status FROM crm_leads l WHERE ' . implode(' AND ', $metrics_where);
}
$metrics_stmt = mysqli_prepare($conn, $metrics_sql);
if ($metrics_stmt && $metrics_types !== '') {
    mysqli_stmt_bind_param($metrics_stmt, $metrics_types, ...$metrics_params);
}
if ($metrics_stmt) {
    mysqli_stmt_execute($metrics_stmt);
    $metrics_result = mysqli_stmt_get_result($metrics_stmt);
    while ($row = mysqli_fetch_assoc($metrics_result)) {
        $all_my_leads[] = $row;
    }
    mysqli_free_result($metrics_result);
    mysqli_stmt_close($metrics_stmt);
}

$metrics = [
    'total' => count($all_my_leads),
    'pending' => 0,
    'in_progress' => 0,
    'converted' => 0,
    'upcoming' => 0,
    'overdue' => 0,
];

$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

foreach ($all_my_leads as $lead) {
    $status = $lead['status'] ?? '';
    if ($status === 'New') $metrics['pending']++;
    if (in_array($status, ['Contacted', 'Qualified'], true)) $metrics['in_progress']++;
    if ($status === 'Converted') $metrics['converted']++;
    
    if ($has_follow_up_col && !empty($lead['follow_up_date'])) {
        $follow_date = $lead['follow_up_date'];
        if ($follow_date < $today && in_array($status, ['New', 'Contacted'], true)) {
            $metrics['overdue']++;
        } elseif ($follow_date >= $today && $follow_date <= $next_week && in_array($status, ['New', 'Contacted'], true)) {
            $metrics['upcoming']++;
        }
    }
}

$statuses = crm_lead_statuses();
?>
<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
          <h1>📌 My Leads</h1>
          <p>Review assigned leads, progress them, and plan follow-ups</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="../index.php" class="btn btn-secondary">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-accent">📇 All Leads</a>
          <a href="add.php" class="btn">➕ Add Lead</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Statistics Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;text-align:center;padding:20px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $metrics['total']; ?></div>
        <div style="font-size:14px;opacity:0.9;">Total Assigned</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:#fff;text-align:center;padding:20px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $metrics['pending']; ?></div>
        <div style="font-size:14px;opacity:0.9;">New/Pending</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);color:#fff;text-align:center;padding:20px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $metrics['in_progress']; ?></div>
        <div style="font-size:14px;opacity:0.9;">In Progress</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);color:#fff;text-align:center;padding:20px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $metrics['converted']; ?></div>
        <div style="font-size:14px;opacity:0.9;">Converted</div>
      </div>
      <?php if ($has_follow_up_col): ?>
      <div class="card" style="background:linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);color:#fff;text-align:center;padding:20px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $metrics['upcoming']; ?></div>
        <div style="font-size:14px;opacity:0.9;">Upcoming Follow-Ups</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg, #f97316 0%, #dc2626 100%);color:#fff;text-align:center;padding:20px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo $metrics['overdue']; ?></div>
        <div style="font-size:14px;opacity:0.9;">Overdue Follow-Ups</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Filters Card -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">🔍 Search & Filter Leads</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
        <div class="form-group" style="margin:0;">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
              <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filter_status === $status ? 'selected' : ''); ?>><?php echo htmlspecialchars($status); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Source</label>
          <select name="source" class="form-control">
            <option value="">Any source</option>
            <?php foreach ($sources as $source): ?>
              <option value="<?php echo htmlspecialchars($source); ?>" <?php echo ($filter_source === $source ? 'selected' : ''); ?>><?php echo htmlspecialchars($source); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Search</label>
          <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Name, phone, email, company">
        </div>
        <?php if ($has_follow_up_col): ?>
        <div class="form-group" style="margin:0;">
          <label>Follow-up from</label>
          <input type="date" name="follow_from" class="form-control" value="<?php echo htmlspecialchars($follow_from); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Follow-up to</label>
          <input type="date" name="follow_to" class="form-control" value="<?php echo htmlspecialchars($follow_to); ?>">
        </div>
        <?php endif; ?>
        <div>
          <button class="btn" type="submit" style="width:100%;background:#003581;color:#fff;">Search</button>
        </div>
        <?php if ($has_filters): ?>
        <div>
          <a href="my.php" class="btn btn-accent" style="width:100%;display:block;text-align:center;text-decoration:none;">Clear</a>
        </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Leads Table -->
    <div class="card">
      <h3 style="margin-top:0;color:#003581;">
        📋 Lead Directory (<?php echo count($leads); ?>)
      </h3>
      <?php if (!$leads): ?>
        <div style="padding:40px;text-align:center;color:#6b7280;">
          <div style="font-size:48px;margin-bottom:16px;">📭</div>
          <p style="font-size:18px;margin:0;">
            <?php if ($has_filters): ?>
              No leads found matching your filters.
            <?php else: ?>
              No leads assigned yet.
            <?php endif; ?>
          </p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="table" style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Name</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Company</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Contact</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Status</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Assigned To</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Source</th>
                <?php if ($has_follow_up_col): ?>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Follow-Up</th>
                <?php endif; ?>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Last Contact</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leads as $lead): ?>
                <?php
                  $assignedName = trim(($lead['assigned_first'] ?? '') . ' ' . ($lead['assigned_last'] ?? ''));
                  if (!$assignedName) {
                    $assignedName = $lead['assigned_code'] ?? '—';
                  }
                ?>
                <tr style="border-bottom:1px solid #dee2e6;">
                  <td style="padding:12px;">
                    <strong><a href="view.php?id=<?php echo (int)$lead['id']; ?>" style="color:#003581;text-decoration:none;font-weight:600;"><?php echo htmlspecialchars($lead['name'] ?? 'Unnamed'); ?></a></strong>
                  </td>
                  <td style="padding:12px;">
                    <?php if (!empty($lead['company_name'] ?? null)): ?>
                      <span style="font-size:13px;"><?php echo htmlspecialchars($lead['company_name']); ?></span>
                    <?php else: ?>
                      <span style="color:#9ca3af;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;">
                    <?php if (!empty($lead['phone'] ?? null)): ?>
                      <div style="font-size:13px;">📞 <?php echo htmlspecialchars($lead['phone']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($lead['email'] ?? null)): ?>
                      <div style="font-size:13px;">✉️ <?php echo htmlspecialchars($lead['email']); ?></div>
                    <?php endif; ?>
                    <?php if (empty($lead['phone'] ?? null) && empty($lead['email'] ?? null)): ?>
                      <span style="color:#9ca3af;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;">
                    <span class="badge <?php echo crm_lead_status_badge_class($lead['status'] ?? ''); ?>" style="font-size:12px;padding:4px 10px;">
                      <?php echo htmlspecialchars($lead['status'] ?? 'Unknown'); ?>
                    </span>
                  </td>
                  <td style="padding:12px;">
                    <span style="font-size:13px;"><?php echo htmlspecialchars($assignedName); ?></span>
                  </td>
                  <td style="padding:12px;">
                    <span style="font-size:13px;"><?php echo htmlspecialchars($lead['source'] ?? '—'); ?></span>
                  </td>
                  <?php if ($has_follow_up_col): ?>
                  <td style="padding:12px;">
                    <?php 
                      $followUpDate = $lead['follow_up_date'] ?? null;
                      $followUpType = $lead['follow_up_type'] ?? null;
                      if ($followUpDate): 
                        $isOverdue = (strtotime($followUpDate) < strtotime('today'));
                        $dateColor = $isOverdue ? '#dc3545' : '#28a745';
                    ?>
                      <div style="font-size:13px;color:<?php echo $dateColor; ?>;font-weight:600;">
                        📅 <?php echo date('d M Y', strtotime($followUpDate)); ?>
                      </div>
                      <?php if ($followUpType): ?>
                        <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                          <?php echo htmlspecialchars($followUpType); ?>
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span style="color:#9ca3af;">—</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  <td style="padding:12px;">
                    <div style="font-size:13px;color:#6b7280;">
                      <?php 
                        $lastContact = $lead['last_contacted_at'] ?? null;
                        if ($lastContact) {
                          echo date('d M Y', strtotime($lastContact));
                        } else {
                          echo '—';
                        }
                      ?>
                    </div>
                  </td>
                  <td style="padding:12px;text-align:center;">
                    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                      <a class="btn btn-secondary" href="view.php?id=<?php echo (int)$lead['id']; ?>" style="padding:6px 12px;font-size:13px;text-decoration:none;">View</a>
                      <?php if (crm_role_can_manage($user_role)): ?>
                        <a class="btn" href="edit.php?id=<?php echo (int)$lead['id']; ?>" style="padding:6px 12px;font-size:13px;background:#f59e0b;color:#fff;text-decoration:none;">Edit</a>
                      <?php endif; ?>
                    </div>
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

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn); 
}
?>
