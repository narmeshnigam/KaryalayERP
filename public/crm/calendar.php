<?php
require_once __DIR__ . '/helpers.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$conn = createConnection(true);
if (!$conn) { echo 'DB error'; exit; }

if (!crm_tables_exist($conn)) {
  closeConnection($conn);
  require_once __DIR__ . '/onboarding.php';
  exit;
}

// Month/year and optional day selection
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
$year  = isset($_GET['year']) ? max(2020, min(2100, (int)$_GET['year'])) : (int)date('Y');

$first_day = sprintf('%04d-%02d-01', $year, $month);
$last_day  = date('Y-m-t', strtotime($first_day));

// Filters (all enabled by default)
$show_tasks = isset($_GET['tasks']) ? (bool)$_GET['tasks'] : true;
$show_calls = isset($_GET['calls']) ? (bool)$_GET['calls'] : true;
$show_meetings = isset($_GET['meetings']) ? (bool)$_GET['meetings'] : true;
$show_visits = isset($_GET['visits']) ? (bool)$_GET['visits'] : true;
$show_followups = isset($_GET['followups']) ? (bool)$_GET['followups'] : true;

// Unified event collection
$events_by_date = [];

// Helper to push event
$push_event = function(string $date, array $e) use (&$events_by_date) {
  if (!$date) return;
  $key = substr($date, 0, 10);
  if (!isset($events_by_date[$key])) $events_by_date[$key] = [];
  $events_by_date[$key][] = $e;
};

// Tasks
if ($show_tasks) {
  $sql = "SELECT id, title, status, due_date FROM crm_tasks WHERE deleted_at IS NULL AND due_date BETWEEN ? AND ? ORDER BY due_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['due_date'], [
        'type' => 'Task',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Untitled Task'),
        'status' => (string)($r['status'] ?? ''),
        'link' => './tasks/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
  }
}

// Calls
if ($show_calls) {
  $sql = "SELECT id, title, call_date FROM crm_calls WHERE deleted_at IS NULL AND call_date BETWEEN ? AND ? ORDER BY call_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['call_date'], [
        'type' => 'Call',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Call'),
        'status' => '',
        'link' => './calls/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
  }
}

// Meetings
if ($show_meetings) {
  $sql = "SELECT id, title, meeting_date FROM crm_meetings WHERE deleted_at IS NULL AND meeting_date BETWEEN ? AND ? ORDER BY meeting_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['meeting_date'], [
        'type' => 'Meeting',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Meeting'),
        'status' => '',
        'link' => './meetings/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
  }
}

// Visits
if ($show_visits) {
  $sql = "SELECT id, title, visit_date FROM crm_visits WHERE deleted_at IS NULL AND visit_date BETWEEN ? AND ? ORDER BY visit_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['visit_date'], [
        'type' => 'Visit',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Visit'),
        'status' => '',
        'link' => './visits/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res);
    mysqli_stmt_close($stmt);
  }
}

// Lead follow-ups
if ($show_followups) {
  $sql = "SELECT id, name AS title, follow_up_date FROM crm_leads WHERE deleted_at IS NULL AND follow_up_date IS NOT NULL AND follow_up_date BETWEEN ? AND ? ORDER BY follow_up_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['follow_up_date'], [
        'type' => 'Follow-up',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Lead Follow-up'),
        'status' => '',
        'link' => './leads/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) {
      mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
  }
}

// Meta for activity types (shared with UI and legend)
$type_meta = [
  'Task' => ['label' => 'Task', 'icon' => '📋', 'bg' => '#e3f2fd', 'fg' => '#003581'],
  'Call' => ['label' => 'Call', 'icon' => '📞', 'bg' => '#fff7ed', 'fg' => '#c2410c'],
  'Meeting' => ['label' => 'Meeting', 'icon' => '👥', 'bg' => '#ecfdf5', 'fg' => '#047857'],
  'Visit' => ['label' => 'Visit', 'icon' => '🚗', 'bg' => '#ecfeff', 'fg' => '#0e7490'],
  'Follow-up' => ['label' => 'Follow-up', 'icon' => '🔁', 'bg' => '#f5f3ff', 'fg' => '#6d28d9'],
];
$type_meta_default = ['label' => 'CRM', 'icon' => '•', 'bg' => '#f3f4f6', 'fg' => '#374151'];

// Calendar helpers
$day_labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$start_of_month = strtotime($first_day);
$days_in_month = (int)date('t', $start_of_month);
$first_weekday = (int)date('w', $start_of_month); // 0 (Sun) - 6 (Sat)
$today_key = date('Y-m-d');

// Prepare month navigation
$prev_month = $month - 1; $prev_year = $year; if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year; if ($next_month > 12) { $next_month = 1; $next_year++; }
$month_name = date('F', strtotime($first_day));

$page_title = 'CRM Calendar - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.cal-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.cal-nav-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 15px;
}
.cal-nav-flex a {
    flex: 1;
}
.cal-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
}
.cal-legend-flex {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}
.desktop-text {
    display: inline;
}
.mobile-text {
    display: none !important;
}
@media (max-width: 600px) {
    .cal-header-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .cal-header-flex > div {
        width: 100%;
    }
    .cal-header-flex .btn {
        width: 100%;
        text-align: center;
        display: block;
    }
    .cal-nav-flex {
        flex-direction: column;
        align-items: stretch;
        margin-bottom: 15px;
    }
    .cal-nav-flex h2 {
        order: -1;
        text-align: center;
        margin-bottom: 12px !important;
    }
    .cal-nav-flex a {
        width: 100%;
        flex: none;
    }
    .cal-calendar-grid {
        gap: 3px;
        padding: 8px !important;
    }
    .cal-calendar-grid > div {
        aspect-ratio: 1;
        padding: 2px !important;
        font-size: 10px;
    }
    .cal-calendar-grid > div.day-header {
        padding: 6px !important;
        font-size: 10px !important;
    }
    .cal-calendar-grid > div.day-cell {
        cursor: pointer;
        transition: transform 0.1s, box-shadow 0.1s;
    }
    .cal-calendar-grid > div.day-cell:active {
        transform: scale(0.95);
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
    }
    .cal-calendar-grid div div:first-child {
        font-size: 11px;
        font-weight: 700;
    }
    .cal-calendar-grid > div > div[style*="margin-top"] {
        padding: 1px 2px !important;
        font-size: 7px !important;
        margin-top: 1px !important;
        line-height: 1;
    }
    .cal-legend-flex {
        gap: 12px;
        font-size: 12px;
    }
    .cal-legend-flex > div {
        flex: 1 1 calc(50% - 6px);
    }
    .desktop-text {
        display: none;
    }
    .mobile-text {
        display: inline !important;
    }
}
</style>
<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="cal-header-flex">
        <div>
          <h1>🗓️ CRM Calendar</h1>
          <p>Monthly view of all CRM activities</p>
        </div>
        <div>
          <a href="./index.php" class="btn btn-accent">← Back to Dashboard</a>
        </div>
      </div>
    </div>

    <!-- Month Navigation -->
    <div class="card">
      <div class="cal-nav-flex">
        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-accent">
          ← Previous
        </a>
        <h2 style="margin:0;color:#003581;"><?php echo htmlspecialchars($month_name . ' ' . $year); ?></h2>
        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-accent">
          Next →
        </a>
      </div>

      <!-- Calendar Grid -->
      <div style="background:#f8f9fa;padding:20px;border-radius:8px;">
        <div class="cal-calendar-grid">
          <?php foreach ($day_labels as $label): ?>
            <div class="day-header" style="text-align:center;font-weight:700;color:#003581;padding:10px;background:white;border-radius:6px;">
              <?php echo htmlspecialchars($label, ENT_QUOTES); ?>
            </div>
          <?php endforeach; ?>

          <?php
            // Empty cells for days before the first day
            for ($i = 0; $i < $first_weekday; $i++) {
              echo '<div style="aspect-ratio:1;background:#e9ecef;border-radius:6px;"></div>';
            }

            // Render each day
            for ($day = 1; $day <= $days_in_month; $day++) {
              $cell_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
              $cell_events = $events_by_date[$cell_date] ?? [];
              $is_today = ($cell_date === $today_key);
              
              $border = $is_today ? '2px solid #003581' : '1px solid #dee2e6';
              
              echo '<div class="day-cell" onclick="showDayDetail(\'' . htmlspecialchars($cell_date, ENT_QUOTES) . '\')" style="aspect-ratio:1;background:white;border:' . $border . ';border-radius:6px;padding:8px;display:flex;flex-direction:column;position:relative;overflow:hidden;cursor:pointer;">';
              echo '<div style="font-weight:700;color:#212529;">' . $day . '</div>';
              
              if (!empty($cell_events)) {
                $visible_events = array_slice($cell_events, 0, 3);
                foreach ($visible_events as $event) {
                  $meta = $type_meta[$event['type']] ?? $type_meta_default;
                  $chip_bg = htmlspecialchars($meta['bg'], ENT_QUOTES);
                  $chip_fg = htmlspecialchars($meta['fg'], ENT_QUOTES);
                  $chip_icon = htmlspecialchars($meta['icon'], ENT_QUOTES);
                  $chip_title = htmlspecialchars($event['title'] ?? 'CRM Item', ENT_QUOTES);
                  
                  echo '<div style="margin-top:5px;padding:3px 6px;background:' . $chip_bg . ';color:' . $chip_fg . ';border-radius:10px;font-size:10px;">';
                  echo '<span class="desktop-text">' . $chip_icon . ' ' . $chip_title . '</span>';
                  echo '<span class="mobile-text" style="display:none;">' . $chip_icon . '</span>';
                  echo '</div>';
                }
                
                if (count($cell_events) > 3) {
                  $remaining = count($cell_events) - 3;
                  echo '<div style="font-size:10px;color:#495057;margin-top:3px;" class="desktop-text">+' . $remaining . ' more</div>';
                }
              }
              
              echo '</div>';
            }
            
            // Empty cells to complete the grid
            $total_cells = $first_weekday + $days_in_month;
            $remaining_cells = 7 - ($total_cells % 7);
            if ($remaining_cells < 7) {
              for ($i = 0; $i < $remaining_cells; $i++) {
                echo '<div style="aspect-ratio:1;background:#e9ecef;border-radius:6px;"></div>';
              }
            }
          ?>
        </div>
      </div>

      <!-- Legend -->
      <div style="margin-top:20px;padding:15px;background:#f8f9fa;border-radius:8px;">
        <div style="font-weight:600;color:#003581;margin-bottom:10px;">📋 Legend:</div>
        <div class="cal-legend-flex">
          <?php foreach ($type_meta as $meta): ?>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:20px;height:20px;background:<?php echo htmlspecialchars($meta['bg'], ENT_QUOTES); ?>;border:1px solid #dee2e6;border-radius:4px;"></div>
              <span style="font-size:14px;"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES); ?></span>
            </div>
          <?php endforeach; ?>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:20px;height:20px;background:white;border:2px solid #003581;border-radius:4px;"></div>
            <span style="font-size:14px;">Today</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Day Detail Modal -->
<div id="dayDetailModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:15px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:30px;max-width:500px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="margin:0;color:#003581;" id="modalDateTitle">Date</h3>
      <button onclick="closeDayDetailModal()" style="background:none;border:none;font-size:28px;cursor:pointer;padding:0;color:#6c757d;line-height:1;">&times;</button>
    </div>
    
    <div id="modalContent">
      <!-- Content will be inserted here -->
    </div>
    
    <div style="margin-top:20px;text-align:center;">
      <button onclick="closeDayDetailModal()" class="btn" style="padding:10px 24px;">Close</button>
    </div>
  </div>
</div>

<script>
// Store CRM events data
const crmEventsByDate = <?php echo json_encode($events_by_date, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const crmTypeMeta = <?php echo json_encode($type_meta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function showDayDetail(date) {
  const events = crmEventsByDate[date] || [];
  const dateObj = new Date(date + 'T00:00:00');
  const formattedDate = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  
  document.getElementById('modalDateTitle').textContent = formattedDate;
  
  let content = '';
  
  if (!events.length) {
    content = `
      <div style="background:#f8f9fa;border-left:4px solid #6c757d;padding:15px;border-radius:6px;margin-bottom:15px;">
        <div style="color:#6c757d;text-align:center;">No CRM activities scheduled for this date</div>
      </div>
    `;
  } else {
    events.forEach((ev) => {
      const meta = crmTypeMeta[ev.type] || { bg: '#f3f4f6', fg: '#374151', icon: '•', label: 'CRM' };
      const safeLink = escapeHtml(ev.link || '#');
      const safeTitle = escapeHtml(ev.title || 'CRM Item');
      const safeStatus = ev.status ? escapeHtml(ev.status) : '';
      const safeType = escapeHtml(meta.label || ev.type || 'CRM');
      
      content += `
        <div style="background:${meta.bg};border-left:4px solid ${meta.fg};padding:15px;border-radius:6px;margin-bottom:15px;">
          <div style="font-weight:600;color:${meta.fg};">${meta.icon} ${safeType}</div>
          <div style="margin-top:8px;color:#495057;"><strong>Title:</strong> ${safeTitle}</div>
          ${safeStatus ? `<div style="color:#495057;"><strong>Status:</strong> ${safeStatus}</div>` : ''}
          <div style="margin-top:10px;">
            <a href="${safeLink}" class="btn btn-sm" style="display:inline-block;padding:6px 12px;font-size:13px;">View Details →</a>
          </div>
        </div>
      `;
    });
  }
  
  document.getElementById('modalContent').innerHTML = content;
  document.getElementById('dayDetailModal').style.display = 'flex';
}

function closeDayDetailModal() {
  document.getElementById('dayDetailModal').style.display = 'none';
}

function escapeHtml(value) {
  if (!value && value !== 0) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeDayDetailModal();
  }
});

// Close modal on outside click
document.getElementById('dayDetailModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeDayDetailModal();
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
