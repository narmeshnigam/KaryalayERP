<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
$user_id = (int)$_SESSION['user_id'];

$Visit_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($Visit_id <= 0) {
    flash_add('error', 'Invalid Visit ID', 'crm');
    header('Location: index.php');
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    die('Database connection failed');
}

if (!crm_tables_exist($conn)) {
    closeConnection($conn);
    require_once __DIR__ . '/../onboarding.php';
    exit;
}

$current_employee_id = crm_current_employee_id($conn, $user_id);

// Fetch Visit
$Visit = crm_visit_fetch($conn, $Visit_id);

if (!$Visit) {
    flash_add('error', 'Visit not found', 'crm');
    closeConnection($conn);
    header('Location: index.php');
    exit;
}

// Check access rights - employees can only view their own Visits
$has_assigned_to = crm_visits_has_column($conn, 'assigned_to');
if ($has_assigned_to && !crm_role_can_manage($user_role) && (int)($Visit['assigned_to'] ?? 0) !== $current_employee_id) {
    flash_add('error', 'You do not have permission to view this Visit', 'crm');
    closeConnection($conn);
    header('Location: my.php');
    exit;
}

$has_lead_id = crm_visits_has_column($conn, 'lead_id');
$has_outcome = crm_visits_has_column($conn, 'outcome');
$has_created_by = crm_visits_has_column($conn, 'created_by');
$has_follow_up_date = crm_visits_has_column($conn, 'follow_up_date');
$has_follow_up_type = crm_visits_has_column($conn, 'follow_up_type');

function safeValue($value, $fallback = '—') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return htmlspecialchars((string)$value);
}

$page_title = 'Visit Details - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

$visit_date_raw = crm_visit_get($Visit, 'visit_date', null);
$Visit_time = $visit_date_raw ? strtotime($visit_date_raw) : false;
$is_upcoming = ($Visit_time !== false) ? ($Visit_time >= time()) : false;
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>🗓️ Visit Details</h1>
          <p>View Visit information and notes</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php if (crm_role_can_manage($user_role)): ?>
            <a href="edit.php?id=<?php echo $Visit_id; ?>" class="btn">✏️ Edit Visit</a>
          <?php endif; ?>
          <a href="<?php echo crm_role_can_manage($user_role) ? 'index.php' : 'my.php'; ?>" class="btn btn-accent">← Back to List</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Visit Header Card -->
    <div class="card" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
      <div style="width:84px;height:84px;border-radius:50%;background:<?php echo $is_upcoming ? '#28a745' : '#6c757d'; ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:32px;">
        🗓️
      </div>
      <div style="flex:1;min-width:280px;">
        <div style="font-size:20px;color:#003581;font-weight:700;">
          <?php echo safeValue(crm_visit_get($Visit, 'title'), 'Untitled Visit'); ?>
        </div>
        <div style="color:#6c757d;font-size:13px;margin-top:4px;">
          Scheduled: <?php echo $Visit_time ? htmlspecialchars(date('d M Y, h:i A', $Visit_time)) : '—'; ?>
        </div>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
          <span style="background:<?php echo $is_upcoming ? '#d4edda' : '#e2e3e5'; ?>;color:<?php echo $is_upcoming ? '#155724' : '#41464b'; ?>;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
            <?php echo $is_upcoming ? '✓ Upcoming' : '✓ Completed'; ?>
          </span>
          <?php if ($has_follow_up_date && crm_visit_get($Visit, 'follow_up_date')): ?>
            <span style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
              Follow-up: <?php $fraw = crm_visit_get($Visit, 'follow_up_date', ''); echo $fraw ? htmlspecialchars(date('d M Y', strtotime($fraw))) : '—'; ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Details Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;">
      <!-- Visit Information -->
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📞 Visit Information</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Visit Date & Time:</strong> <?php echo $Visit_time ? htmlspecialchars(date('d M Y, h:i A', $Visit_time)) : '—'; ?></div>
          <div><strong>Status:</strong> <?php echo $is_upcoming ? '<span style="color:#28a745;">Upcoming</span>' : '<span style="color:#6c757d;">Completed</span>'; ?></div>
          <?php if (crm_visit_get($Visit, 'location')): ?>
            <div><strong>Location:</strong> <?php echo safeValue(crm_visit_get($Visit, 'location')); ?></div>
          <?php endif; ?>
          <div><strong>Created At:</strong> <?php $cr = crm_visit_get($Visit, 'created_at', ''); echo $cr ? htmlspecialchars(date('d M Y, h:i A', strtotime($cr))) : '—'; ?></div>
        </div>
      </div>

      <!-- Assignment Details -->
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">👥 Assignment</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <?php if ($has_assigned_to): ?>
            <div>
              <strong>Assigned To:</strong> 
              <?php echo safeValue(crm_visit_employee_label(
                crm_visit_get($Visit, 'assigned_code'),
                crm_visit_get($Visit, 'assigned_first'),
                crm_visit_get($Visit, 'assigned_last')
              )); ?>
            </div>
          <?php endif; ?>
          <?php if ($has_created_by): ?>
            <div>
              <strong>Created By:</strong> 
              <?php echo safeValue(crm_visit_employee_label(
                crm_visit_get($Visit, 'created_code'),
                crm_visit_get($Visit, 'created_first'),
                crm_visit_get($Visit, 'created_last')
              )); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Follow-Up Details -->
      <?php if ($has_follow_up_date && crm_visit_get($Visit, 'follow_up_date')): ?>
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📅 Follow-Up</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Follow-Up Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime(crm_visit_get($Visit, 'follow_up_date')))); ?></div>
          <?php if ($has_follow_up_type && crm_visit_get($Visit, 'follow_up_type')): ?>
            <div><strong>Follow-Up Type:</strong> <?php echo safeValue(crm_visit_get($Visit, 'follow_up_type')); ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- notes -->
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📋 notes</h3>
      <div style="font-size:14px;color:#495057;line-height:1.6;white-space:pre-wrap;">
        <?php echo nl2br(safeValue(crm_visit_get($Visit, 'notes'), 'No notes provided.')); ?>
      </div>
    </div>

    <!-- Outcome -->
    <?php if ($has_outcome && crm_visit_get($Visit, 'outcome')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📝 Visit Outcome</h3>
      <div style="font-size:14px;color:#495057;line-height:1.6;white-space:pre-wrap;">
        <?php echo nl2br(safeValue(crm_visit_get($Visit, 'outcome'))); ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Related Lead -->
    <?php if ($has_lead_id && crm_visit_get($Visit, 'lead_id')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">👤 Related Lead</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;font-size:14px;">
        <div>
          <strong>Lead Name:</strong><br>
          <a href="../leads/view.php?id=<?php echo (int)$Visit['lead_id']; ?>" style="color:#003581;text-decoration:none;font-weight:600;">
            <?php echo safeValue(crm_visit_get($Visit, 'lead_name'), 'Unknown Lead'); ?>
          </a>
        </div>
        <?php if (crm_visit_get($Visit, 'lead_company')): ?>
          <div><strong>Company:</strong><br><?php echo safeValue(crm_visit_get($Visit, 'lead_company')); ?></div>
        <?php endif; ?>
        <?php if (crm_visit_get($Visit, 'lead_phone')): ?>
          <div>
            <strong>Phone:</strong><br>
            <a href="tel:<?php echo htmlspecialchars(crm_visit_get($Visit, 'lead_phone')); ?>" style="color:#003581;">
              <?php echo safeValue(crm_visit_get($Visit, 'lead_phone')); ?>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Attachment -->
    <?php if (crm_visit_get($Visit, 'attachment')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📎 Attachment</h3>
      <div style="font-size:14px;">
        <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars(crm_visit_get($Visit, 'attachment')); ?>" 
           target="_blank" 
           style="background:#e3f2fd;color:#003581;padding:8px 16px;border-radius:12px;text-decoration:none;display:inline-block;">
          📄 Download Attachment
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
