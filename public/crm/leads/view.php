<?php
require_once __DIR__ . '/common.php';

crm_leads_require_login();

// Enforce permission to view leads
$leads_permissions = authz_get_permission_set($conn, 'crm_leads');

$lead_id = (int)($_GET['id'] ?? 0);
if ($lead_id <= 0) {
    flash_add('error', 'Lead not specified.', 'crm');
    header('Location: index.php');
    exit;
}

crm_leads_require_tables($conn);

$lead = crm_lead_fetch($conn, $lead_id);
if (!$lead) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    flash_add('error', 'Lead not found.', 'crm');
    header('Location: index.php');
    exit;
}

$can_manage = $IS_SUPER_ADMIN || $leads_permissions['can_edit_all'];

$assigned_label = crm_lead_employee_label($lead['assigned_code'] ?? '', $lead['assigned_first'] ?? '', $lead['assigned_last'] ?? '');
$creator_label = crm_lead_employee_label($lead['creator_code'] ?? '', $lead['creator_first'] ?? '', $lead['creator_last'] ?? '');
$follow_up_display = isset($lead['follow_up_date']) && $lead['follow_up_date'] ? date('d M Y', strtotime($lead['follow_up_date'])) : 'Not scheduled';
$follow_up_type = isset($lead['follow_up_type']) && $lead['follow_up_type'] ? $lead['follow_up_type'] : '';
$last_contact = isset($lead['last_contacted_at']) && $lead['last_contacted_at'] ? date('d M Y', strtotime($lead['last_contacted_at'])) : 'No activity logged';

function safeValue($value, $fallback = '—') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return htmlspecialchars((string)$value);
}

function safeDate($value, $fallback = '—') {
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return htmlspecialchars($value);
    }
    return date('d M Y', $timestamp);
}

function safeDateTime($value, $fallback = '—') {
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return htmlspecialchars($value);
    }
    return date('d M Y H:i', $timestamp);
}

$page_title = 'View Lead - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
          <h1>👤 Lead Profile</h1>
          <p>Detailed lead information and follow-up tracking</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="../index.php" class="btn btn-accent">← CRM Dashboard</a>
          <a href="index.php" class="btn btn-secondary">← All Leads</a>
          <?php if ($can_manage): ?>
          <a href="edit.php?id=<?php echo (int)$lead_id; ?>" class="btn" style="margin-left:8px;">✏️ Edit</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Profile Header Card -->
    <div class="card" style="display:flex;gap:20px;align-items:center;">
      <div style="width:84px;height:84px;border-radius:50%;background:#003581;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:32px;">
        <?php echo strtoupper(substr($lead['name'] ?? 'L', 0, 1)); ?>
      </div>
      <div style="flex:1;">
        <div style="font-size:20px;color:#003581;font-weight:700;">
          <?php echo safeValue($lead['name'] ?? null, 'Unknown Lead'); ?>
        </div>
        <div style="color:#6c757d;font-size:13px;">
          Source: <?php echo safeValue($lead['source'] ?? null); ?> • Assigned to: <?php echo htmlspecialchars($assigned_label); ?>
        </div>
        <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
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
          <span style="<?php echo $badge_style; ?>padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Status: <?php echo safeValue($lead['status'] ?? null); ?></span>
          <?php if ($follow_up_display !== 'Not scheduled'): ?>
            <span style="background:#f0f9ff;color:#0284c7;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Follow-up: <?php echo htmlspecialchars($follow_up_display); ?></span>
          <?php endif; ?>
          <?php if (!empty($lead['company_name'])): ?>
            <span style="background:#f8f9fa;color:#495057;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Company: <?php echo safeValue($lead['company_name'] ?? null); ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Details Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;">
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📇 Contact Information</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Name:</strong> <?php echo safeValue($lead['name'] ?? null); ?></div>
          <div><strong>Company:</strong> <?php echo safeValue($lead['company_name'] ?? null); ?></div>
          <div><strong>Phone:</strong> <?php echo safeValue($lead['phone'] ?? null); ?></div>
          <div><strong>Email:</strong> <?php echo safeValue($lead['email'] ?? null); ?></div>
          <div><strong>Location:</strong> <?php echo safeValue($lead['location'] ?? null); ?></div>
        </div>
      </div>

      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🔄 Lead Management</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Status:</strong> <?php echo safeValue($lead['status'] ?? null); ?></div>
          <div><strong>Source:</strong> <?php echo safeValue($lead['source'] ?? null); ?></div>
          <div><strong>Assigned To:</strong> <?php echo htmlspecialchars($assigned_label); ?></div>
          <div><strong>Created By:</strong> <?php echo htmlspecialchars($creator_label); ?></div>
          <div><strong>Created On:</strong> <?php echo safeDateTime($lead['created_at']); ?></div>
        </div>
      </div>

      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📅 Follow-Up Details</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Next Follow-Up:</strong> <?php echo htmlspecialchars($follow_up_display); ?></div>
          <?php if ($follow_up_type): ?>
            <div><strong>Follow-Up Type:</strong> <?php echo safeValue($follow_up_type); ?></div>
          <?php endif; ?>
          <div><strong>Last Contact:</strong> <?php echo htmlspecialchars($last_contact); ?></div>
          <?php if (!empty($lead['attachment'])): ?>
            <div>
              <strong>Attachment:</strong><br>
              <a href="<?php echo htmlspecialchars('../../' . $lead['attachment']); ?>" target="_blank" style="background:#e3f2fd;color:#003581;padding:6px 12px;border-radius:12px;font-size:12px;text-decoration:none;display:inline-block;margin-top:4px;">View Document ⤓</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Notes & Interests Section -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;">
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">💡 Interests</h3>
        <div style="font-size:14px;color:#495057;line-height:1.6;">
          <?php 
            $interests = isset($lead['interests']) && $lead['interests'] ? $lead['interests'] : 'Not specified';
            echo nl2br(safeValue($interests, 'Not specified'));
          ?>
        </div>
      </div>

      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📝 Notes</h3>
        <div style="font-size:14px;color:#495057;line-height:1.6;">
          <?php 
            $notes = isset($lead['notes']) && $lead['notes'] ? $lead['notes'] : 'No notes captured yet.';
            echo nl2br(safeValue($notes, 'No notes captured yet.'));
          ?>
        </div>
      </div>
    </div>

    <!-- Linked Activities -->
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🔗 Linked Activities</h3>
      <?php
        // Fetch linked activities for this lead
        $activities = [];
        
        // Fetch calls
        $calls_sql = "SELECT 'Call' as activity_type, id, title, call_date as activity_date, outcome as status FROM crm_calls WHERE lead_id = ? AND deleted_at IS NULL ORDER BY call_date DESC LIMIT 50";
        $stmt = mysqli_prepare($conn, $calls_sql);
        if ($stmt) {
          mysqli_stmt_bind_param($stmt, 'i', $lead_id);
          mysqli_stmt_execute($stmt);
          $res = mysqli_stmt_get_result($stmt);
          while ($res && ($row = mysqli_fetch_assoc($res))) {
            $row['module'] = 'calls';
            $activities[] = $row;
          }
          if ($res) mysqli_free_result($res);
          mysqli_stmt_close($stmt);
        }

        // Fetch visits
        $visits_sql = "SELECT 'Visit' as activity_type, id, title, visit_date as activity_date, outcome as status FROM crm_visits WHERE lead_id = ? AND deleted_at IS NULL ORDER BY visit_date DESC LIMIT 50";
        $stmt = mysqli_prepare($conn, $visits_sql);
        if ($stmt) {
          mysqli_stmt_bind_param($stmt, 'i', $lead_id);
          mysqli_stmt_execute($stmt);
          $res = mysqli_stmt_get_result($stmt);
          while ($res && ($row = mysqli_fetch_assoc($res))) {
            $row['module'] = 'visits';
            $activities[] = $row;
          }
          if ($res) mysqli_free_result($res);
          mysqli_stmt_close($stmt);
        }

        // Fetch meetings
        $meetings_sql = "SELECT 'Meeting' as activity_type, id, title, meeting_date as activity_date, outcome as status FROM crm_meetings WHERE lead_id = ? AND deleted_at IS NULL ORDER BY meeting_date DESC LIMIT 50";
        $stmt = mysqli_prepare($conn, $meetings_sql);
        if ($stmt) {
          mysqli_stmt_bind_param($stmt, 'i', $lead_id);
          mysqli_stmt_execute($stmt);
          $res = mysqli_stmt_get_result($stmt);
          while ($res && ($row = mysqli_fetch_assoc($res))) {
            $row['module'] = 'meetings';
            $activities[] = $row;
          }
          if ($res) mysqli_free_result($res);
          mysqli_stmt_close($stmt);
        }

        // Fetch tasks (only if crm_tasks has a lead_id column)
        $has_tasks_lead = false;
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM crm_tasks LIKE 'lead_id'");
        if ($chk) { $has_tasks_lead = mysqli_num_rows($chk) > 0; mysqli_free_result($chk); }
        if ($has_tasks_lead) {
          $tasks_sql = "SELECT 'Task' as activity_type, id, title, due_date as activity_date, status FROM crm_tasks WHERE lead_id = ? AND deleted_at IS NULL ORDER BY due_date DESC, id DESC LIMIT 50";
          $stmt = mysqli_prepare($conn, $tasks_sql);
          if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $lead_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
              $row['module'] = 'tasks';
              $activities[] = $row;
            }
            if ($res) mysqli_free_result($res);
            mysqli_stmt_close($stmt);
          }
        }

        // Sort by date descending
        usort($activities, function($a, $b) {
          $date_a = strtotime($a['activity_date'] ?? 'now');
          $date_b = strtotime($b['activity_date'] ?? 'now');
          return $date_b <=> $date_a;
        });
      ?>

      <?php if (empty($activities)): ?>
        <div style="text-align:center;padding:30px 20px;color:#6c757d;">
          <div style="font-size:36px;margin-bottom:12px;">🧭</div>
          <p style="font-size:14px;margin:0;">No activities linked to this lead yet.</p>
          <p style="font-size:13px;color:#9ca3af;margin:8px 0 0;">Create a call, meeting, visit, or task and link it to this lead.</p>
        </div>
      <?php else: ?>
        <div style="position:relative;padding-left:22px;display:flex;flex-direction:column;gap:12px;">
          <div style="position:absolute;left:9px;top:0;bottom:0;width:2px;background:#e5e7eb;"></div>
          <?php foreach (array_slice($activities, 0, 20) as $activity): // Show latest 20 activities ?>
            <?php 
              $icon_map = [
                'Call' => '☎️',
                'Visit' => '🚗',
                'Meeting' => '📞',
                'Task' => '✓'
              ];
              $module = $activity['module'] ?? '';
              $colors = [
                'calls' => ['bg' => '#e3f2fd', 'fg' => '#003581'],
                'visits' => ['bg' => '#fde2e2', 'fg' => '#9b1c1c'],
                'meetings' => ['bg' => '#fff3cd', 'fg' => '#856404'],
                'tasks' => ['bg' => '#d4edda', 'fg' => '#155724'],
              ];
              $c = $colors[$module] ?? ['bg' => '#eef2f7', 'fg' => '#374151'];
              $icon = $icon_map[$activity['activity_type']] ?? '🔗';
              $activity_date = !empty($activity['activity_date']) ? date('d M Y', strtotime($activity['activity_date'])) : 'N/A';
              $view_link = '../' . $module . '/view.php?id=' . (int)$activity['id'];
            ?>
            <a href="<?php echo $view_link; ?>" style="position:relative;display:flex;gap:12px;padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #eef2f7;align-items:flex-start;text-decoration:none;color:inherit;">
              <div style="position:absolute;left:-17px;top:16px;width:12px;height:12px;border-radius:50%;background:<?php echo $c['bg']; ?>;border:2px solid #ffffff;box-shadow:0 0 0 2px #e5e7eb;"></div>
              <div style="flex-shrink:0;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;background:<?php echo $c['bg']; ?>;color:<?php echo $c['fg']; ?>;">
                <?php echo $icon; ?>
              </div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap;">
                  <div>
                    <div style="color:#003581;font-weight:600;font-size:14px;"><?php echo htmlspecialchars($activity['title'] ?? 'Untitled'); ?></div>
                    <div style="font-size:12px;color:#6c757d;margin-top:2px;"><?php echo $activity['activity_type']; ?> • <?php echo $activity_date; ?></div>
                  </div>
                  <?php if (!empty($activity['status'])): ?>
                    <span style="background:<?php echo $c['bg']; ?>;color:<?php echo $c['fg']; ?>;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;">
                      <?php echo htmlspecialchars($activity['status']); ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div style="margin-top:6px;font-size:12px;color:#6b7280;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                  <span style="color:#9ca3af;">View details →</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if (count($activities) > 20): ?>
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#6c757d;">
            Showing 20 of <?php echo count($activities); ?> activities. <a href="#" style="color:#003581;text-decoration:none;font-weight:600;">View all →</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Quick Add Actions -->
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;">
        <a href="../calls/add.php?lead_id=<?php echo (int)$lead_id; ?>" class="btn" style="padding:8px 12px;font-size:13px;text-decoration:none;text-align:center;">☎️ Log Call</a>
        <a href="../meetings/add.php?lead_id=<?php echo (int)$lead_id; ?>" class="btn" style="padding:8px 12px;font-size:13px;text-decoration:none;text-align:center;">📞 Schedule Meeting</a>
        <a href="../visits/add.php?lead_id=<?php echo (int)$lead_id; ?>" class="btn" style="padding:8px 12px;font-size:13px;text-decoration:none;text-align:center;">🚗 Log Visit</a>
        <a href="../tasks/add.php?lead_id=<?php echo (int)$lead_id; ?>" class="btn" style="padding:8px 12px;font-size:13px;text-decoration:none;text-align:center;">✓ Create Task</a>
      </div>
    </div>

    <!-- Follow-Up Guidance -->
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📊 Follow-Up Guidance</h3>
      <p style="margin-bottom:12px;font-size:14px;color:#6c757d;">Track upcoming actions to keep this lead warm.</p>
      <ul style="margin:0;padding-left:20px;color:#495057;line-height:1.8;font-size:14px;">
        <li>Next follow-up is <strong><?php echo htmlspecialchars($follow_up_display); ?></strong><?php echo $follow_up_type ? ' via ' . htmlspecialchars(strtolower($follow_up_type)) : ''; ?>.</li>
        <li>When logging new calls, meetings, visits, or tasks, remember to associate them with this lead once activity linking is enabled.</li>
        <li>Converted or dropped leads automatically clear pending follow-ups.</li>
      </ul>
    </div>
    
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn); 
}
?>
