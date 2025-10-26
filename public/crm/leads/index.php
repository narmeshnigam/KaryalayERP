<?php
require_once __DIR__ . '/common.php';

crm_leads_require_login();
$user_role = $_SESSION['role'] ?? 'employee';
if (!crm_role_can_manage($user_role)) {
    flash_add('error', 'You do not have access to all leads.', 'crm');
    header('Location: ../index.php');
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to database.</div></div></div>';
    exit;
}

crm_leads_require_tables($conn);

$employees = crm_fetch_employee_map($conn);
$statuses = crm_lead_statuses();
$followUpTypes = crm_lead_follow_up_types();
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

// Check whether the database has follow_up_date column (older installs may not)
$has_follow_up_col = false;
$colRes = mysqli_query($conn, "SHOW COLUMNS FROM crm_leads LIKE 'follow_up_date'");
if ($colRes) {
  if (mysqli_num_rows($colRes) > 0) {
    $has_follow_up_col = true;
  }
  mysqli_free_result($colRes);
}

$metrics = [
    'total' => 0,
    'upcoming' => 0,
    'overdue' => 0,
    'converted' => 0,
];

if ($has_follow_up_col) {
  $metricQueries = [
    'total' => "SELECT COUNT(*) AS c FROM crm_leads WHERE deleted_at IS NULL",
    'upcoming' => "SELECT COUNT(*) AS c FROM crm_leads WHERE deleted_at IS NULL AND follow_up_date IS NOT NULL AND follow_up_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status IN ('New','Contacted')",
    'overdue' => "SELECT COUNT(*) AS c FROM crm_leads WHERE deleted_at IS NULL AND follow_up_date IS NOT NULL AND follow_up_date < CURDATE() AND status IN ('New','Contacted')",
    'converted' => "SELECT COUNT(*) AS c FROM crm_leads WHERE deleted_at IS NULL AND status = 'Converted'",
  ];
} else {
  // Fallback metric queries for installations without the new columns
  $metricQueries = [
    'total' => "SELECT COUNT(*) AS c FROM crm_leads WHERE deleted_at IS NULL",
    'upcoming' => "SELECT 0 AS c",
    'overdue' => "SELECT 0 AS c",
    'converted' => "SELECT COUNT(*) AS c FROM crm_leads WHERE deleted_at IS NULL AND status = 'Converted'",
  ];
}

foreach ($metricQueries as $key => $sqlMetric) {
  $metricRes = mysqli_query($conn, $sqlMetric);
  if ($metricRes) {
    $metricRow = mysqli_fetch_assoc($metricRes);
    if ($metricRow && isset($metricRow['c'])) {
      $metrics[$key] = (int)$metricRow['c'];
    }
    mysqli_free_result($metricRes);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $lead_id = (int)($_POST['lead_id'] ?? 0);
  $query_string = $_SERVER['QUERY_STRING'] ?? '';
  $redirect_url = 'index.php' . ($query_string ? ('?' . $query_string) : '');
    if ($lead_id <= 0) {
        flash_add('error', 'Invalid lead request.', 'crm');
        header('Location: index.php');
        exit;
    }

    $lead = crm_lead_fetch($conn, $lead_id);
    if (!$lead) {
        flash_add('error', 'Lead not found.', 'crm');
        header('Location: index.php');
        exit;
    }

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        if (!in_array($new_status, crm_lead_allowed_statuses($lead['status'] ?? ''), true)) {
            flash_add('error', 'Invalid status transition.', 'crm');
            header('Location: index.php');
            exit;
        }

        $is_final = in_array($new_status, ['Converted','Dropped'], true);
    if ($is_final) {
            $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET status = ?, follow_up_date = NULL, follow_up_type = NULL, follow_up_created = 0, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $new_status, $lead_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                flash_add('success', 'Lead status updated.', 'crm');
            }
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET status = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $new_status, $lead_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                flash_add('success', 'Lead status updated.', 'crm');
            }
        }

    header('Location: ' . $redirect_url);
        exit;
    }

    if ($action === 'reassign') {
        $assigned_to = (int)($_POST['assigned_to'] ?? 0);
        if ($assigned_to <= 0 || !crm_employee_exists($conn, $assigned_to)) {
            flash_add('error', 'Select a valid employee.', 'crm');
            header('Location: index.php');
            exit;
        }

        $stmt = mysqli_prepare($conn, 'UPDATE crm_leads SET assigned_to = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $assigned_to, $lead_id);
            if (mysqli_stmt_execute($stmt)) {
                crm_notify_lead_assigned($conn, $lead_id, $assigned_to);
                flash_add('success', 'Lead reassigned successfully.', 'crm');
            }
            mysqli_stmt_close($stmt);
        }

    header('Location: ' . $redirect_url);
        exit;
    }

    flash_add('error', 'Unsupported action.', 'crm');
    header('Location: index.php');
    exit;
}

$filter_status = $_GET['status'] ?? '';
$filter_employee = (int)($_GET['assigned_to'] ?? 0);
$filter_source = $_GET['source'] ?? '';
$filter_search = trim($_GET['search'] ?? '');
$follow_from = $_GET['follow_from'] ?? '';
$follow_to = $_GET['follow_to'] ?? '';
$has_filters = ($filter_status !== '' || $filter_employee > 0 || $filter_source !== '' || $filter_search !== '' || $follow_from !== '' || $follow_to !== '');
$filtered_total = 0;

$where = ['l.deleted_at IS NULL'];
$params = [];
$types = '';

if ($filter_status !== '' && in_array($filter_status, $statuses, true)) {
    $where[] = 'l.status = ?';
    $types .= 's';
    $params[] = $filter_status;
}
if ($filter_employee > 0) {
    $where[] = 'l.assigned_to = ?';
    $types .= 'i';
    $params[] = $filter_employee;
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

$countSql = 'SELECT COUNT(*) AS c FROM crm_leads l WHERE ' . implode(' AND ', $where);
$countStmt = mysqli_prepare($conn, $countSql);
if ($countStmt) {
  if ($types !== '') {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
  }
  mysqli_stmt_execute($countStmt);
  $countRes = mysqli_stmt_get_result($countStmt);
  if ($countRes) {
    $countRow = mysqli_fetch_assoc($countRes);
    if ($countRow && isset($countRow['c'])) {
      $filtered_total = (int)$countRow['c'];
    }
    mysqli_free_result($countRes);
  }
  mysqli_stmt_close($countStmt);
} elseif ($types === '') {
  $countRes = mysqli_query($conn, $countSql);
  if ($countRes) {
    $countRow = mysqli_fetch_assoc($countRes);
    if ($countRow && isset($countRow['c'])) {
      $filtered_total = (int)$countRow['c'];
    }
    mysqli_free_result($countRes);
  }
}

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
        ORDER BY l.created_at DESC
        LIMIT 300';

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

$page_title = 'CRM Leads - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
          <h1>📇 All Leads</h1>
          <p>Manage lead assignments, status, and follow-ups</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="../index.php" class="btn btn-secondary">← CRM Dashboard</a>
          <a href="my.php" class="btn btn-accent">📌 My Leads</a>
          <a href="add.php" class="btn">➕ Add Lead</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Statistics Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['total']); ?></div>
        <div>Total Leads</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['upcoming']); ?></div>
        <div>Upcoming Follow-Ups</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#f97316 0%,#dc2626 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['overdue']); ?></div>
        <div>Overdue Follow-Ups</div>
      </div>

      <div class="card" style="background:linear-gradient(135deg,#16a34a 0%,#22c55e 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['converted']); ?></div>
        <div>Converted Leads</div>
      </div>
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
          <label>Assigned To</label>
          <select name="assigned_to" class="form-control">
            <option value="">All employees</option>
            <?php foreach ($employees as $id => $label): ?>
              <option value="<?php echo (int)$id; ?>" <?php echo ($filter_employee === (int)$id ? 'selected' : ''); ?>><?php echo htmlspecialchars($label); ?></option>
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
        <div class="form-group" style="margin:0;">
          <label>Follow-up from</label>
          <input type="date" name="follow_from" class="form-control" value="<?php echo htmlspecialchars($follow_from); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Follow-up to</label>
          <input type="date" name="follow_to" class="form-control" value="<?php echo htmlspecialchars($follow_to); ?>">
        </div>
        <div>
          <button class="btn" type="submit" style="width:100%;">Search</button>
        </div>
        <div>
          <a class="btn btn-accent" href="index.php" style="width:100%;text-decoration:none;display:block;text-align:center;">Clear</a>
        </div>
      </form>
    </div>

    <div class="card">
      <?php if (!$leads): ?>
        <div class="alert alert-info" style="margin:0;">No leads found with the selected filters.</div>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <h3 style="margin:0;color:#003581;">📋 Lead Directory (<?php echo count($leads); ?>)</h3>
        </div>

        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Name</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Company</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Contact</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Status</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Assigned To</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Source</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Follow-Up</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Last Contact</th>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leads as $lead): ?>
                <?php
                  $assignedLabel = crm_lead_employee_label($lead['assigned_code'] ?? '', $lead['assigned_first'] ?? '', $lead['assigned_last'] ?? '');
                  $followDate = isset($lead['follow_up_date']) && $lead['follow_up_date'] ? date('d M Y', strtotime($lead['follow_up_date'])) : '—';
                  $followType = isset($lead['follow_up_type']) && $lead['follow_up_type'] ? $lead['follow_up_type'] : '';
                  $lastContact = isset($lead['last_contacted_at']) && $lead['last_contacted_at'] ? date('d M Y', strtotime($lead['last_contacted_at'])) : '—';
                ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td style="padding:12px;">
                    <div style="font-weight:600;color:#003581;"><a href="view.php?id=<?php echo (int)$lead['id']; ?>" style="color:#003581;text-decoration:none;"><?php echo htmlspecialchars($lead['name'] ?? ''); ?></a></div>
                    <?php if (!empty($lead['interests'])): ?>
                      <div style="font-size:13px;color:#6c757d;margin-top:4px;">💡 <?php echo htmlspecialchars($lead['interests']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($lead['company_name'] ?? '—'); ?></td>
                  <td style="padding:12px;">
                    <?php if (!empty($lead['phone'])): ?>
                      <div style="margin-bottom:4px;font-size:13px;">📞 <?php echo htmlspecialchars($lead['phone']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($lead['email'])): ?>
                      <div style="font-size:13px;">✉️ <?php echo htmlspecialchars($lead['email']); ?></div>
                    <?php endif; ?>
                    <?php if (empty($lead['phone']) && empty($lead['email'])): ?>
                      <span style="color:#6c757d;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;">
                    <?php
                    $status_colors = [
                        'New' => 'background:#e3f2fd;color:#1565c0;',
                        'Contacted' => 'background:#fff3cd;color:#856404;',
                        'Qualified' => 'background:#d1ecf1;color:#0c5460;',
                        'Proposal' => 'background:#cfe2ff;color:#084298;',
                        'Negotiation' => 'background:#f8d7da;color:#721c24;',
                        'Converted' => 'background:#d4edda;color:#155724;',
                        'Dropped' => 'background:#f8d7da;color:#721c24;'
                    ];
                    $badge_style = $status_colors[$lead['status'] ?? ''] ?? 'background:#e2e3e5;color:#41464b;';
                    ?>
                    <span style="padding:4px 10px;border-radius:12px;font-size:13px;font-weight:600;display:inline-block;<?php echo $badge_style; ?>">
                      <?php echo htmlspecialchars($lead['status'] ?? ''); ?>
                    </span>
                  </td>
                  <td style="padding:12px;">
                    <span style="background:#e3f2fd;color:#003581;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:500;display:inline-block;">
                      <?php echo htmlspecialchars($assignedLabel); ?>
                    </span>
                  </td>
                  <td style="padding:12px;font-size:13px;"><?php echo htmlspecialchars($lead['source'] ?? '—'); ?></td>
                  <td style="padding:12px;">
                    <div style="font-size:13px;color:#6c757d;"><?php echo htmlspecialchars($followDate); ?></div>
                    <?php if ($followType): ?>
                      <span style="background:#f0f9ff;color:#0284c7;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:500;display:inline-block;margin-top:4px;"><?php echo htmlspecialchars($followType); ?></span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;font-size:13px;color:#6c757d;"><?php echo htmlspecialchars($lastContact); ?></td>
                  <td style="padding:12px;text-align:center;white-space:nowrap;">
                    <a href="view.php?id=<?php echo (int)$lead['id']; ?>" class="btn" style="padding:6px 14px;font-size:13px;text-decoration:none;background:#17a2b8;color:#fff;">View</a>
                    <a href="edit.php?id=<?php echo (int)$lead['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;text-decoration:none;">Edit</a>
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
<?php closeConnection($conn); ?>
