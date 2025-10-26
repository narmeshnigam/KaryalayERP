<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
$user_id = (int)$_SESSION['user_id'];

$conn = createConnection(true);
if (!$conn) {
    die('Database connection failed');
}

$current_employee_id = crm_current_employee_id($conn, $user_id);
if (!$current_employee_id) {
    closeConnection($conn);
    die('Unable to identify your employee record.');
}

// Detect available columns
$has_follow_up_date = crm_calls_has_column($conn, 'follow_up_date');
$has_follow_up_type = crm_calls_has_column($conn, 'follow_up_type');
$has_assigned_to = crm_calls_has_column($conn, 'assigned_to');
$has_lead_id_col = crm_calls_has_column($conn, 'lead_id');

// If assigned_to is missing, warn but continue — we'll fall back to showing all calls
if (!$has_assigned_to) {
  // Don't redirect; show warning and continue so the page can render in older schemas
  flash_add('warning', 'Your database schema does not have the assigned_to column; "My Calls" will show all calls.', 'crm');
}

// Filters
$filter_outcome = trim($_GET['outcome'] ?? '');
$filter_lead = isset($_GET['lead']) && $_GET['lead'] !== '' ? (int)$_GET['lead'] : null;
$filter_search = trim($_GET['search'] ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to = trim($_GET['date_to'] ?? '');

// Fetch metrics for current employee
$metrics = [
    'total' => 0,
    'today' => 0,
    'this_week' => 0,
    'pending_followup' => 0
];

if ($has_assigned_to) {
  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM crm_calls WHERE deleted_at IS NULL AND assigned_to = ?");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $current_employee_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
      $row = mysqli_fetch_assoc($res);
      $metrics['total'] = (int)($row['total'] ?? 0);
      mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
  }

  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS today FROM crm_calls WHERE deleted_at IS NULL AND assigned_to = ? AND DATE(call_date) = CURDATE()");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $current_employee_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
      $row = mysqli_fetch_assoc($res);
      $metrics['today'] = (int)($row['today'] ?? 0);
      mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
  }

  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS this_week FROM crm_calls WHERE deleted_at IS NULL AND assigned_to = ? AND YEARWEEK(call_date, 1) = YEARWEEK(CURDATE(), 1)");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $current_employee_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
      $row = mysqli_fetch_assoc($res);
      $metrics['this_week'] = (int)($row['this_week'] ?? 0);
      mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
  }

  if ($has_follow_up_date) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS pending FROM crm_calls WHERE deleted_at IS NULL AND assigned_to = ? AND follow_up_date IS NOT NULL AND follow_up_date >= CURDATE()");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'i', $current_employee_id);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      if ($res) {
        $row = mysqli_fetch_assoc($res);
        $metrics['pending_followup'] = (int)($row['pending'] ?? 0);
        mysqli_free_result($res);
      }
      mysqli_stmt_close($stmt);
    }
  }
} else {
  // Fallback to global metrics when assigned_to isn't present
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
}

// Build query
$select_cols = crm_calls_select_columns($conn);
$where = ["c.deleted_at IS NULL"];
$params = [];
$types = '';
if ($has_assigned_to) {
  $where[] = "c.assigned_to = ?";
  $params[] = $current_employee_id;
  $types .= 'i';
}

if ($filter_outcome !== '') {
    $where[] = "c.outcome = ?";
    $params[] = $filter_outcome;
    $types .= 's';
}
if ($filter_lead !== null && $has_lead_id_col) {
    $where[] = "c.lead_id = ?";
    $params[] = $filter_lead;
    $types .= 'i';
} elseif ($filter_lead !== null) {
    $filter_lead = null; // Clear filter if column doesn't exist
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

$select_cols = crm_calls_select_columns($conn);
$joins = "";
if ($has_lead_id_col) {
    $joins .= "LEFT JOIN crm_leads l ON c.lead_id = l.id ";
}

$lead_select = '';
if ($has_lead_id_col) {
    $lead_select = ", l.name AS lead_name, l.company_name AS lead_company";
}

$sql = "SELECT $select_cols $lead_select
        FROM crm_calls c
        $joins
        WHERE $where_clause
        ORDER BY $order_by";

$stmt = mysqli_prepare($conn, $sql);
$calls = [];
if ($stmt) {
  // Bind params only when types are present (we may have no params when assigned_to is missing)
  if ($types !== '') {
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

$leads = crm_fetch_active_leads($conn);
$outcomes = crm_call_outcomes();

$page_title = 'My Calls - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>



<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>☎️ My Calls</h1>
          <p>Calls assigned to you</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php if (crm_role_can_manage($user_role)): ?>
            <a href="index.php" class="btn btn-accent">← All Calls</a>
          <?php endif; ?>
          <a href="add.php" class="btn">➕ Log New Call</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Statistics Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:6px;"><?php echo number_format($metrics['total']); ?></div>
        <div>My Total Calls</div>
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
      <h3 style="margin-top:0;color:#003581;">🔍 Search & Filter My Calls</h3>
      <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;">
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

        <?php if ($has_lead_id_col): ?>
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
          <a href="my.php" class="btn btn-accent" style="width:100%;text-decoration:none;display:block;text-align:center;">Clear</a>
        </div>
      </form>
    </div>

    <!-- Calls Table -->
    <div class="card">
      <h3 style="margin:0 0 16px;color:#003581;">📋 My Call Records (<?php echo count($calls); ?>)</h3>
      <?php if (empty($calls)): ?>
        <div style="text-align:center;padding:40px;color:#6c757d;">
          <p style="font-size:18px;margin:0 0 8px;">No calls assigned to you yet</p>
          <p style="margin:0;"><a href="add.php" style="color:#003581;text-decoration:none;font-weight:600;">Log your first call</a> to get started.</p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Call Date</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Title</th>
                <?php if ($has_lead_id_col): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Lead</th><?php endif; ?>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Outcome</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Duration</th>
                <?php if ($has_follow_up_date): ?><th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Follow-Up</th><?php endif; ?>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($calls as $call): ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td style="padding:12px;font-size:13px;"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($call['call_date']))); ?></td>
                  <td style="padding:12px;"><strong><?php echo htmlspecialchars($call['title']); ?></strong></td>
                  <?php if ($has_lead_id_col): ?>
                    <td style="padding:12px;">
                      <?php if (!empty($call['lead_id'])): ?>
                        <a href="../leads/view.php?id=<?php echo (int)$call['lead_id']; ?>" style="color:#003581;text-decoration:none;font-weight:500;">
                          <?php echo htmlspecialchars($call['lead_name'] ?? 'Lead #' . $call['lead_id']); ?>
                        </a>
                      <?php else: ?>
                        <span style="color:#9ca3af;">—</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td style="padding:12px;">
                    <span class="badge <?php echo crm_call_outcome_badge($call['outcome'] ?? null); ?>">
                      <?php echo htmlspecialchars($call['outcome'] ?? 'N/A'); ?>
                    </span>
                  </td>
                  <td style="padding:12px;font-size:13px;"><?php echo crm_format_duration($call['duration'] ?? null); ?></td>
                  <?php if ($has_follow_up_date): ?>
                    <td style="padding:12px;">
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
                  <td style="padding:12px;text-align:center;white-space:nowrap;">
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
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
