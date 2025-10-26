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
$selected_day = isset($_GET['day']) ? $_GET['day'] : '';

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

// Colors and icons per module
$type_meta = [
  'Task' => ['bg' => '#e3f2fd', 'fg' => '#1e40af', 'icon' => '✓', 'module' => 'tasks', 'date_col' => 'due_date'],
  'Call' => ['bg' => '#fff7ed', 'fg' => '#c2410c', 'icon' => '📞', 'module' => 'calls', 'date_col' => 'call_date'],
  'Meeting' => ['bg' => '#ecfdf5', 'fg' => '#065f46', 'icon' => '📋', 'module' => 'meetings', 'date_col' => 'meeting_date'],
  'Visit' => ['bg' => '#ecfeff', 'fg' => '#0f766e', 'icon' => '🚗', 'module' => 'visits', 'date_col' => 'visit_date'],
  'Follow-up' => ['bg' => '#f5f3ff', 'fg' => '#5b21b6', 'icon' => '🔔', 'module' => 'leads', 'date_col' => 'follow_up_date'],
];

// Fetch within month
$from = $first_day . ' 00:00:00';
$to   = $last_day . ' 23:59:59';

// Tasks
if ($show_tasks) {
  $sql = "SELECT id, title, status, due_date FROM crm_tasks WHERE deleted_at IS NULL AND due_date BETWEEN ? AND ? ORDER BY due_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) { mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['due_date'], [
        'type' => 'Task',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Untitled Task'),
        'status' => (string)($r['status'] ?? ''),
        'link' => './tasks/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Calls
if ($show_calls) {
  $sql = "SELECT id, title, call_date FROM crm_calls WHERE deleted_at IS NULL AND call_date BETWEEN ? AND ? ORDER BY call_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) { mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['call_date'], [
        'type' => 'Call',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Call'),
        'status' => '',
        'link' => './calls/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Meetings
if ($show_meetings) {
  $sql = "SELECT id, title, meeting_date FROM crm_meetings WHERE deleted_at IS NULL AND meeting_date BETWEEN ? AND ? ORDER BY meeting_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) { mysqli_stmt_bind_param($stmt, 'ss', $from, $to); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['meeting_date'], [
        'type' => 'Meeting',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Meeting'),
        'status' => '',
        'link' => './meetings/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Visits
if ($show_visits) {
  $sql = "SELECT id, title, visit_date FROM crm_visits WHERE deleted_at IS NULL AND visit_date BETWEEN ? AND ? ORDER BY visit_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) { mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['visit_date'], [
        'type' => 'Visit',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Visit'),
        'status' => '',
        'link' => './visits/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Lead follow-ups
if ($show_followups) {
  $sql = "SELECT id, name AS title, follow_up_date FROM crm_leads WHERE deleted_at IS NULL AND follow_up_date IS NOT NULL AND follow_up_date BETWEEN ? AND ? ORDER BY follow_up_date";
  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) { mysqli_stmt_bind_param($stmt, 'ss', $first_day, $last_day); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
      $push_event((string)$r['follow_up_date'], [
        'type' => 'Follow-up',
        'id' => (int)$r['id'],
        'title' => (string)($r['title'] ?? 'Lead Follow-up'),
        'status' => '',
        'link' => './leads/view.php?id=' . (int)$r['id']
      ]);
    }
    if ($res) mysqli_free_result($res); mysqli_stmt_close($stmt);
  }
}

// Prepare month navigation
$prev_month = $month - 1; $prev_year = $year; if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year; if ($next_month > 12) { $next_month = 1; $next_year++; }
$month_name = date('F', strtotime($first_day));

$page_title = 'CRM Calendar - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <!-- Header -->
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
          <h1>🗓️ CRM Calendar</h1>
          <p>Compact monthly view of all activities with quick links</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a class="btn btn-accent" href="./index.php">← CRM Dashboard</a>
        </div>
      </div>
    </div>

    <div class="card">
      <!-- Navigation & Filters -->
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <div style="display:flex;gap:8px;align-items:center;">
          <a class="btn" href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">← Prev</a>
          <div style="font-weight:700;color:#003581;font-size:18px;min-width:180px;text-align:center;"><?php echo htmlspecialchars($month_name . ' ' . $year); ?></div>
          <a class="btn" href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">Next →</a>
          <a class="btn btn-secondary" href="?month=<?php echo (int)date('n'); ?>&year=<?php echo (int)date('Y'); ?>">Today</a>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
          <?php
            // Simple color chips legend
            $legend = [
              ['Task','#e3f2fd','#1e40af'],
              ['Call','#fff7ed','#c2410c'],
              ['Meeting','#ecfdf5','#065f46'],
              ['Visit','#ecfeff','#0f766e'],
              ['Follow-up','#f5f3ff','#5b21b6'],
            ];
            foreach ($legend as $l) {
              $bg = ($l[0] === 'Follow-up') ? '#f5f3ff' : '#f9fafb';
              echo '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:16px;background:'.$bg.';">'
                .'<span style="width:10px;height:10px;border-radius:50%;background:'.$l[1].';border:1px solid #e5e7eb;"></span>'
                .'<span style="font-size:12px;color:#374151;">'.$l[0].'</span>'
                .'</span>';
            }
          ?>
        </div>
      </div>

      <!-- Day headers -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:8px;">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
          <div style="text-align:center;font-weight:700;color:#003581;padding:8px;background:#fff;border-radius:6px;border:1px solid #e5e7eb;"><?php echo $dow; ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Month grid -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;">
        <?php
          $first_ts = strtotime($first_day);
          $start_w = (int)date('w', $first_ts);
          $days_in_month = (int)date('t', $first_ts);

          for ($i=0; $i<$start_w; $i++) echo '<div style="aspect-ratio:1;background:#f3f4f6;border-radius:6px;border:1px solid #e5e7eb;"></div>';

          for ($d = 1; $d <= $days_in_month; $d++) {
            $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $is_today = ($date_str === date('Y-m-d'));
            $list = $events_by_date[$date_str] ?? [];

            // sort per-day events: Follow-up first, then Meetings, Calls, Visits, Tasks
            usort($list, function($a,$b){
              $order = ['Follow-up'=>0,'Meeting'=>1,'Call'=>2,'Visit'=>3,'Task'=>4];
              return ($order[$a['type']] ?? 9) <=> ($order[$b['type']] ?? 9);
            });

            echo '<div style="aspect-ratio:1;background:#ffffff;border:' . ($is_today?'2px solid #003581':'1px solid #e5e7eb') . ';border-radius:8px;padding:8px;display:flex;flex-direction:column;min-height:140px;">';
            echo '<div style="display:flex;justify-content:space-between;align-items:center;">'
                .'<div style="font-weight:700;color:#111827;">'.$d.'</div>'
                .'<a href="?month='.(int)$month.'&year='.(int)$year.'&day='.$date_str.'#agenda" style="font-size:11px;color:#003581;text-decoration:none;">Agenda</a>'
                .'</div>';

            // chips
            $max = 4; $shown = 0; 
            foreach ($list as $ev) {
              if ($shown >= $max) break; $shown++;
              $meta = $type_meta[$ev['type']] ?? ['bg'=>'#f3f4f6','fg'=>'#374151','icon'=>'•'];
              echo '<a href="'.htmlspecialchars($ev['link']).'" style="display:flex;gap:6px;align-items:center;margin-top:6px;padding:6px 8px;background:'.$meta['bg'].';color:'.$meta['fg'].';border-radius:6px;text-decoration:none;border:1px solid #e5e7eb;">'
                 .'<span style="font-size:13px;">'.$meta['icon'].'</span>'
                 .'<span style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'.htmlspecialchars($ev['title']).'</span>'
                 .'</a>';
            }
            $remain = max(0, count($list) - $shown);
            if ($remain > 0) {
              echo '<a href="?month='.(int)$month.'&year='.(int)$year.'&day='.$date_str.'#agenda" style="margin-top:6px;font-size:12px;color:#6b7280;text-decoration:none;">+'.$remain.' more</a>';
            }
            echo '</div>';
          }

          $total_cells = $start_w + $days_in_month;
          $remaining = 7 - ($total_cells % 7);
          if ($remaining < 7) {
            for ($i=0;$i<$remaining;$i++) echo '<div style="aspect-ratio:1;background:#f3f4f6;border-radius:6px;border:1px solid #e5e7eb;"></div>';
          }
        ?>
      </div>

      <?php if ($selected_day && isset($events_by_date[$selected_day])): ?>
        <div id="agenda" style="margin-top:20px;padding-top:12px;border-top:1px solid #e5e7eb;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h3 style="margin:0;color:#003581;">Agenda • <?php echo htmlspecialchars(date('D, d M Y', strtotime($selected_day))); ?></h3>
            <a class="btn btn-secondary" href="?month=<?php echo (int)$month; ?>&year=<?php echo (int)$year; ?>">Close</a>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($events_by_date[$selected_day] as $ev): $meta = $type_meta[$ev['type']] ?? ['bg'=>'#f3f4f6','fg'=>'#374151','icon'=>'•']; ?>
              <a href="<?php echo htmlspecialchars($ev['link']); ?>" style="display:flex;gap:10px;align-items:center;padding:10px;border:1px solid #e5e7eb;border-radius:8px;background:#ffffff;text-decoration:none;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:<?php echo $meta['bg']; ?>;color:<?php echo $meta['fg']; ?>;font-size:14px;"><?php echo $meta['icon']; ?></span>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;color:#003581;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">[<?php echo $ev['type']; ?>] <?php echo htmlspecialchars($ev['title']); ?></div>
                  <?php if (!empty($ev['status'])): ?><div style="margin-top:2px;font-size:11px;color:#6b7280;">Status: <?php echo htmlspecialchars($ev['status']); ?></div><?php endif; ?>
                </div>
                <span style="font-size:12px;color:#6b7280;">View →</span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
<?php closeConnection($conn); ?>
