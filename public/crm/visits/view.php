<?php
require_once __DIR__ . '/common.php';

$visit_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($visit_id <= 0) {
    flash_add('error', 'Invalid visit ID', 'crm');
    header('Location: index.php');
    exit;
}

if (!crm_tables_exist($conn)) {
    require_once __DIR__ . '/../onboarding.php';
    exit;
}

$current_employee_id = crm_current_employee_id($conn, $CURRENT_USER_ID);

// Get permissions
$visits_permissions = authz_get_permission_set($conn, 'crm_visits');

// Detect available columns
$has_lead_id = crm_visits_has_column($conn, 'lead_id');
$has_assigned_to = crm_visits_has_column($conn, 'assigned_to');
$has_created_by = crm_visits_has_column($conn, 'created_by');
$has_outcome = crm_visits_has_column($conn, 'outcome');
$has_description = crm_visits_has_column($conn, 'description');
$has_visit_proof = crm_visits_has_column($conn, 'visit_proof_image');

// Fetch visit with lead and employee details
$select_cols = crm_visits_select_columns($conn);
$joins = "LEFT JOIN employees e1 ON c.assigned_to = e1.id " . ($has_created_by ? "LEFT JOIN employees e2 ON c.created_by = e2.id " : "");
if ($has_lead_id) {
    $joins = "LEFT JOIN crm_leads l ON c.lead_id = l.id " . $joins;
}

$lead_select = '';
if ($has_lead_id) {
    $lead_select = ", l.name AS lead_name, l.company_name AS lead_company, l.phone AS lead_phone, l.email AS lead_email";
}

$emp_select = '';
if ($has_assigned_to) {
    $emp_select .= ", e1.first_name AS assigned_first, e1.last_name AS assigned_last";
}
if ($has_created_by) {
    $emp_select .= ", e2.first_name AS created_first, e2.last_name AS created_last";
}

$sql = "SELECT $select_cols $lead_select $emp_select
        FROM crm_visits c
        $joins
        WHERE c.id = ? AND c.deleted_at IS NULL
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    die('Failed to prepare query');
}

mysqli_stmt_bind_param($stmt, 'i', $visit_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$visit = $res ? mysqli_fetch_assoc($res) : null;
if ($res) mysqli_free_result($res);
mysqli_stmt_close($stmt);

if (!$visit) {
    flash_add('error', 'Visit not found', 'crm');
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: index.php');
    exit;
}

// Check access rights - employees can only view their own visits
if ($has_assigned_to && !$visits_permissions['can_view_all'] && !$IS_SUPER_ADMIN && (int)($visit['assigned_to'] ?? 0) !== $current_employee_id) {
    flash_add('error', 'You do not have permission to view this visit', 'crm');
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    header('Location: my.php');
    exit;
}

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

$visit_time = strtotime($visit['visit_date']);
$is_upcoming = $visit_time >= time();
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
          <h1>🚗 Visit Details</h1>
          <p>Detailed visit information and follow-up tracking</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php if ($visits_permissions['can_edit_all'] || $IS_SUPER_ADMIN): ?>
            <a href="edit.php?id=<?php echo $visit_id; ?>" class="btn">✏️ Edit Visit</a>
          <?php endif; ?>
          <a href="<?php echo ($visits_permissions['can_view_all'] || $IS_SUPER_ADMIN) ? 'index.php' : 'my.php'; ?>" class="btn btn-accent">← Back to List</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <!-- Visit Header Card -->
    <div class="card" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
      <div style="width:84px;height:84px;border-radius:50%;background:#003581;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:32px;">
        🚗
      </div>
      <div style="flex:1;min-width:280px;">
        <div style="font-size:20px;color:#003581;font-weight:700;">
          <?php echo safeValue(crm_visit_get($visit, 'title'), 'Untitled Visit'); ?>
        </div>
        <div style="color:#6c757d;font-size:13px;margin-top:4px;">
          Visit Date: <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($visit['visit_date']))); ?>
        </div>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
          <?php
            $outcome = crm_visit_get($visit, 'outcome');
            $outcome_colors = [
                'Successful' => 'background:#d4edda;color:#155724;',
                'Order Placed' => 'background:#d4edda;color:#155724;',
                'Pending' => 'background:#fff3cd;color:#856404;',
                'Follow-up Required' => 'background:#fef3c7;color:#92400e;',
                'Cancelled' => 'background:#f8d7da;color:#721c24;',
                'Rescheduled' => 'background:#d1ecf1;color:#0c5460;',
                'No Response' => 'background:#f8d7da;color:#721c24;'
            ];
            $badge_style = $outcome_colors[$outcome] ?? 'background:#e2e3e5;color:#41464b;';
          ?>
          <?php if ($has_outcome && $outcome): ?>
            <span style="<?php echo $badge_style; ?>padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
              <?php echo safeValue($outcome); ?>
            </span>
          <?php endif; ?>
          <?php
            $status = crm_visit_get($visit, 'status');
            $status_colors = [
                'Planned' => 'background:#e3f2fd;color:#0284c7;',
                'Completed' => 'background:#d4edda;color:#155724;',
                'Cancelled' => 'background:#f8d7da;color:#721c24;',
                'Rescheduled' => 'background:#d1ecf1;color:#0c5460;'
            ];
            $status_badge_style = $status_colors[$status] ?? 'background:#e2e3e5;color:#41464b;';
          ?>
          <span style="<?php echo $status_badge_style; ?>padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
            <?php echo safeValue($status, 'N/A'); ?>
          </span>
          <?php if ($has_follow_up_date && crm_visit_get($visit, 'follow_up_date')): ?>
            <span style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
              Follow-up: <?php echo htmlspecialchars(date('d M Y', strtotime(crm_visit_get($visit, 'follow_up_date')))); ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Related Lead -->
    <?php if ($has_lead_id && crm_visit_get($visit, 'lead_id')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">👤 Related Lead</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;font-size:14px;">
        <div>
          <strong>Lead Name:</strong><br>
          <a href="../leads/view.php?id=<?php echo (int)$visit['lead_id']; ?>" style="color:#003581;text-decoration:none;font-weight:600;">
            <?php echo safeValue(crm_visit_get($visit, 'lead_name'), 'Unknown Lead'); ?>
          </a>
        </div>
        <?php if (crm_visit_get($visit, 'lead_company')): ?>
          <div><strong>Company:</strong><br><?php echo safeValue(crm_visit_get($visit, 'lead_company')); ?></div>
        <?php endif; ?>
        <?php if (crm_visit_get($visit, 'lead_phone')): ?>
          <div>
            <strong>Phone:</strong><br>
            <a href="tel:<?php echo htmlspecialchars(crm_visit_get($visit, 'lead_phone')); ?>" style="color:#003581;">
              <?php echo safeValue(crm_visit_get($visit, 'lead_phone')); ?>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Details Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;">
      <!-- Visit Information -->
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🚗 Visit Information</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Visit Date & Time:</strong> <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($visit['visit_date']))); ?></div>
          <div><strong>Status:</strong> <?php echo safeValue(crm_visit_get($visit, 'status')); ?></div>
          <?php if ($has_outcome && crm_visit_get($visit, 'outcome')): ?>
            <div><strong>Outcome:</strong> <?php echo safeValue(crm_visit_get($visit, 'outcome')); ?></div>
          <?php endif; ?>
          <?php if (crm_visit_get($visit, 'location')): ?>
            <div><strong>Location:</strong> <?php echo safeValue(crm_visit_get($visit, 'location')); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Assignment Details -->
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">👥 Assignment</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <?php if ($has_assigned_to): ?>
            <div><strong>Assigned To:</strong> <?php echo safeValue(trim(crm_visit_get($visit, 'assigned_first') . ' ' . crm_visit_get($visit, 'assigned_last'))); ?></div>
          <?php endif; ?>
          <?php if ($has_created_by): ?>
            <div><strong>Created By:</strong> <?php echo safeValue(trim(crm_visit_get($visit, 'created_first') . ' ' . crm_visit_get($visit, 'created_last'))); ?></div>
          <?php endif; ?>
          <div><strong>Created At:</strong> <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime(crm_visit_get($visit, 'created_at')))); ?></div>
        </div>
      </div>

      <!-- Follow-Up Details -->
      <?php if ($has_follow_up_date && crm_visit_get($visit, 'follow_up_date')): ?>
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📅 Follow-Up</h3>
        <div style="display:grid;gap:12px;font-size:14px;">
          <div><strong>Follow-Up Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime(crm_visit_get($visit, 'follow_up_date')))); ?></div>
          <?php if ($has_follow_up_type && crm_visit_get($visit, 'follow_up_type')): ?>
            <div><strong>Follow-Up Type:</strong> <?php echo safeValue(crm_visit_get($visit, 'follow_up_type')); ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Description -->
    <?php if ($has_description && crm_visit_get($visit, 'description')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📝 Description</h3>
      <div style="font-size:14px;color:#495057;line-height:1.6;white-space:pre-wrap;">
        <?php echo nl2br(safeValue(crm_visit_get($visit, 'description'), 'No description provided.')); ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Visit Proof Image -->
    <?php if ($has_visit_proof && crm_visit_get($visit, 'visit_proof_image')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📸 Visit Proof</h3>
      <div style="font-size:14px;">
        <div style="margin-bottom:12px;">
          <strong>Proof Image:</strong>
        </div>
        <a href="<?php echo htmlspecialchars(crm_visit_get($visit, 'visit_proof_image')); ?>" target="_blank">
          <img src="<?php echo htmlspecialchars(crm_visit_get($visit, 'visit_proof_image')); ?>" 
               alt="Visit Proof" 
               style="max-width:100%;max-height:400px;border:1px solid #dee2e6;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Internal Notes -->
    <?php if (crm_visits_has_column($conn, 'notes') && crm_visit_get($visit, 'notes')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">� Internal Notes</h3>
      <div style="font-size:14px;color:#495057;line-height:1.6;white-space:pre-wrap;background:#f8f9fa;padding:12px;border-radius:8px;">
        <?php echo nl2br(htmlspecialchars(crm_visit_get($visit, 'notes'))); ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Attachment -->
    <?php if (crm_visit_get($visit, 'attachment')): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📎 Attachment</h3>
      <div style="font-size:14px;">
        <a href="../../../uploads/crm_attachments/<?php echo htmlspecialchars(crm_visit_get($visit, 'attachment')); ?>" 
           target="_blank" 
           style="background:#e3f2fd;color:#003581;padding:8px 16px;border-radius:12px;text-decoration:none;display:inline-block;">
          📄 Download Attachment
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Geo-Location -->
    <?php
      $has_latitude = crm_visits_has_column($conn, 'latitude');
      $has_longitude = crm_visits_has_column($conn, 'longitude');
      $latitude = crm_visit_get($visit, 'latitude');
      $longitude = crm_visit_get($visit, 'longitude');
    ?>
    <?php if ($has_latitude && $has_longitude && $latitude !== null && $longitude !== null && $latitude !== '' && $longitude !== ''): ?>
    <div class="card" style="margin-top:20px;">
      <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📍 Visited Location</h3>
      <div style="font-size:14px;">
        <strong>Coordinates:</strong> <?php echo number_format((float)$latitude, 6); ?>, <?php echo number_format((float)$longitude, 6); ?>
        <a href="javascript:void(0)" 
           onclick="showLocationMap(<?php echo (float)$latitude; ?>, <?php echo (float)$longitude; ?>, 'Visited Location')"
           style="margin-left:12px;background:#e3f2fd;color:#003581;padding:6px 12px;border-radius:8px;text-decoration:none;display:inline-block;font-size:13px;">
          📍 View on Map
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Location Map Modal -->
<div id="locationMapModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;padding:0;max-width:900px;width:90%;max-height:90vh;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <div style="padding:20px;border-bottom:1px solid #dee2e6;display:flex;justify-content:space-between;align-items:center;background:#003581;color:white;">
      <h3 style="margin:0;" id="locationTitle">📍 Location</h3>
      <button onclick="closeLocationMap()" style="background:none;border:none;color:white;font-size:24px;cursor:pointer;padding:0;line-height:1;">&times;</button>
    </div>
    <div style="height:500px;">
      <iframe id="mapFrame" width="100%" height="100%" frameborder="0" style="border:0;" allowfullscreen></iframe>
    </div>
    <div style="padding:15px;background:#f8f9fa;text-align:center;font-size:13px;color:#6c757d;">
      <span id="locationCoords"></span>
    </div>
  </div>
</div>

<script>
function showLocationMap(lat, lon, title) {
  document.getElementById('locationTitle').textContent = '📍 ' + title;
  document.getElementById('locationCoords').textContent = `Coordinates: ${lat.toFixed(6)}, ${lon.toFixed(6)}`;
  
  // Google Maps embed URL
  const mapUrl = `https://www.google.com/maps?q=${lat},${lon}&z=15&output=embed`;
  document.getElementById('mapFrame').src = mapUrl;
  
  document.getElementById('locationMapModal').style.display = 'flex';
}

function closeLocationMap() {
  document.getElementById('locationMapModal').style.display = 'none';
  document.getElementById('mapFrame').src = '';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLocationMap();
  }
});
</script>

<?php
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>

