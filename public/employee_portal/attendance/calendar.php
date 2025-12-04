<?php
/**
 * Employee Portal - Attendance Calendar
 * Monthly calendar view of attendance records
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = "Attendance Calendar - " . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

$conn = createConnection(true);

// Get employee info
$user_id = $_SESSION['user_id'];
$emp_query = "SELECT e.* FROM employees e WHERE e.user_id = ?";
$emp_stmt = mysqli_prepare($conn, $emp_query);
mysqli_stmt_bind_param($emp_stmt, 'i', $user_id);
mysqli_stmt_execute($emp_stmt);
$emp_result = mysqli_stmt_get_result($emp_stmt);
$employee = mysqli_fetch_assoc($emp_result);
mysqli_stmt_close($emp_stmt);

if (!$employee) {
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-error">No employee record found for your account.</div>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

$employee_id = $employee['id'];

// Get month and year from query string or default to current
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month/year
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2100) $year = date('Y');

// Get all attendance records for the month
$first_day = sprintf('%04d-%02d-01', $year, $month);
$last_day = date('Y-m-t', strtotime($first_day));

$attendance_query = "SELECT * FROM attendance 
                     WHERE employee_id = ? 
                     AND attendance_date BETWEEN ? AND ?
                     ORDER BY attendance_date";
$attendance_stmt = mysqli_prepare($conn, $attendance_query);
mysqli_stmt_bind_param($attendance_stmt, 'iss', $employee_id, $first_day, $last_day);
mysqli_stmt_execute($attendance_stmt);
$attendance_result = mysqli_stmt_get_result($attendance_stmt);

$attendance_map = [];
while ($row = mysqli_fetch_assoc($attendance_result)) {
    $attendance_map[$row['attendance_date']] = $row;
}
mysqli_stmt_close($attendance_stmt);

// Calculate statistics
$present_count = 0;
$absent_count = 0;
$leave_count = 0;
$half_day_count = 0;
$total_hours = 0;

foreach ($attendance_map as $record) {
    switch ($record['status']) {
        case 'Present':
            $present_count++;
            if ($record['total_hours']) $total_hours += floatval($record['total_hours']);
            break;
        case 'Absent':
            $absent_count++;
            break;
        case 'Leave':
            $leave_count++;
            break;
        case 'Half Day':
            $half_day_count++;
            if ($record['total_hours']) $total_hours += floatval($record['total_hours']);
            break;
    }
}

// Get holidays for the month
$holidays_query = "SELECT holiday_date, holiday_name FROM holidays 
                  WHERE holiday_date BETWEEN ? AND ?";
$holidays_stmt = mysqli_prepare($conn, $holidays_query);
mysqli_stmt_bind_param($holidays_stmt, 'ss', $first_day, $last_day);
mysqli_stmt_execute($holidays_stmt);
$holidays_result = mysqli_stmt_get_result($holidays_stmt);

$holidays_map = [];
while ($row = mysqli_fetch_assoc($holidays_result)) {
    $holidays_map[$row['holiday_date']] = $row['holiday_name'];
}
mysqli_stmt_close($holidays_stmt);

closeConnection($conn);

// Calculate previous and next months
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
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
.cal-stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
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
@media (max-width: 1200px) {
    .cal-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 900px) {
    .cal-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
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
    .cal-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    .cal-stats-grid > div {
        padding: 12px !important;
    }
    .cal-stats-grid > div > div:first-child {
        font-size: 24px !important;
    }
    .cal-stats-grid > div > div:last-child {
        font-size: 12px !important;
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
          <h1>📅 Attendance Calendar</h1>
          <p>Monthly view of your attendance records</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to Dashboard</a>
        </div>
      </div>
    </div>

    <!-- Month Navigation -->
    <div class="card">
      <div class="cal-nav-flex">
        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-accent">
          ← Previous
        </a>
        <h2 style="margin:0;color:#003581;"><?php echo $month_name . ' ' . $year; ?></h2>
        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-accent">
          Next →
        </a>
      </div>

      <!-- Monthly Statistics -->
      <div class="cal-stats-grid">
        <div style="padding:15px;background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo (int)$present_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Present Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo (int)$absent_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Absent Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo (int)$leave_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Leave Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#17a2b8 0%,#138496 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo (int)$half_day_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Half Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#6f42c1 0%,#5a32a3 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo number_format($total_hours, 1); ?></div>
          <div style="font-size:13px;opacity:0.9;">Total Hours</div>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div style="background:#f8f9fa;padding:20px;border-radius:8px;">
        <!-- Calendar with Headers and Days in single grid -->
        <div class="cal-calendar-grid">
          <?php
          // Render day headers first
          $days_of_week = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
          foreach ($days_of_week as $day):
          ?>
            <div class="day-header" style="text-align:center;font-weight:700;color:#003581;padding:10px;background:white;border-radius:6px;">
              <?php echo $day; ?>
            </div>
          <?php endforeach; ?>
          
          <?php
          // Get the first day of the month
          $first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
          $day_of_week = date('w', $first_day_of_month);
          $days_in_month = date('t', $first_day_of_month);
          
          // Empty cells for days before the first day
          for ($i = 0; $i < $day_of_week; $i++) {
            echo '<div style="aspect-ratio:1;background:#e9ecef;border-radius:6px;"></div>';
          }
          
          // Render each day
          for ($day = 1; $day <= $days_in_month; $day++) {
            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $is_today = ($current_date == date('Y-m-d'));
            $is_weekend = (date('N', strtotime($current_date)) >= 6);
            
            $attendance = isset($attendance_map[$current_date]) ? $attendance_map[$current_date] : null;
            $holiday = isset($holidays_map[$current_date]) ? $holidays_map[$current_date] : null;
            
            // Determine background color
            $bg_color = 'white';
            $text_color = '#212529';
            $badge = '';
            
            if ($is_today) {
              $border = '2px solid #003581';
            } else {
              $border = '1px solid #dee2e6';
            }
            
            if ($attendance) {
              switch ($attendance['status']) {
                case 'Present':
                  $bg_color = '#d4edda';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#28a745;color:white;border-radius:10px;font-size:10px;"><span class="desktop-text">Present</span><span class="mobile-text" style="display:none;">✓</span></div>';
                  if ($attendance['check_in_time']) {
                    $badge .= '<div style="font-size:10px;color:#495057;margin-top:3px;" class="desktop-text">' . date('g:i A', strtotime($attendance['check_in_time'])) . '</div>';
                  }
                  break;
                case 'Absent':
                  $bg_color = '#f8d7da';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#dc3545;color:white;border-radius:10px;font-size:10px;"><span class="desktop-text">Absent</span><span class="mobile-text" style="display:none;">✗</span></div>';
                  break;
                case 'Leave':
                  $bg_color = '#fff3cd';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#ffc107;color:#212529;border-radius:10px;font-size:10px;"><span class="desktop-text">Leave</span><span class="mobile-text" style="display:none;">📝</span></div>';
                  if ($attendance['leave_type']) {
                    $badge .= '<div style="font-size:10px;color:#495057;margin-top:3px;" class="desktop-text">' . htmlspecialchars($attendance['leave_type']) . '</div>';
                  }
                  break;
                case 'Half Day':
                  $bg_color = '#d1ecf1';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#17a2b8;color:white;border-radius:10px;font-size:10px;"><span class="desktop-text">Half Day</span><span class="mobile-text" style="display:none;">⏱</span></div>';
                  break;
              }
            } else {
              if ($holiday) {
                $bg_color = '#fff3cd';
                $badge = '<div style="margin-top:5px;padding:3px 6px;background:#856404;color:white;border-radius:10px;font-size:10px;"><span class="desktop-text">Holiday</span><span class="mobile-text" style="display:none;">🏖</span></div>';
              }
            }
            
            echo '<div class="day-cell" onclick="showDayDetail(\'' . $current_date . '\')" style="aspect-ratio:1;background:' . $bg_color . ';border:' . $border . ';border-radius:6px;padding:8px;display:flex;flex-direction:column;position:relative;overflow:hidden;">';
            echo '<div style="font-weight:700;color:' . $text_color . ';">' . $day . '</div>';
            echo $badge;
            echo '</div>';
          }
          
          // Empty cells to complete the grid
          $total_cells = $day_of_week + $days_in_month;
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
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:20px;height:20px;background:#d4edda;border:1px solid #dee2e6;border-radius:4px;"></div>
            <span style="font-size:14px;">Present</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:20px;height:20px;background:#f8d7da;border:1px solid #dee2e6;border-radius:4px;"></div>
            <span style="font-size:14px;">Absent</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:20px;height:20px;background:#fff3cd;border:1px solid #dee2e6;border-radius:4px;"></div>
            <span style="font-size:14px;">Leave / Holiday</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:20px;height:20px;background:#d1ecf1;border:1px solid #dee2e6;border-radius:4px;"></div>
            <span style="font-size:14px;">Half Day</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:20px;height:20px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;"></div>
            <span style="font-size:14px;">Weekend</span>
          </div>
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
// Store attendance data
const attendanceData = <?php echo json_encode($attendance_map); ?>;
const holidaysData = <?php echo json_encode($holidays_map); ?>;

function showDayDetail(date) {
  const attendance = attendanceData[date];
  const holiday = holidaysData[date];
  const dateObj = new Date(date + 'T00:00:00');
  const formattedDate = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  
  document.getElementById('modalDateTitle').textContent = formattedDate;
  
  let content = '';
  
  if (holiday) {
    content += `
      <div style="background:#fff3cd;border-left:4px solid #856404;padding:15px;border-radius:6px;margin-bottom:15px;">
        <div style="font-weight:600;color:#856404;">🏖️ Holiday</div>
        <div style="color:#495057;margin-top:5px;">${holiday}</div>
      </div>
    `;
  }
  
  if (attendance) {
    let statusColor = '#6c757d';
    let statusBg = '#f8f9fa';
    let statusIcon = '•';
    
    switch(attendance.status) {
      case 'Present':
        statusColor = '#155724';
        statusBg = '#d4edda';
        statusIcon = '✓';
        break;
      case 'Absent':
        statusColor = '#721c24';
        statusBg = '#f8d7da';
        statusIcon = '✗';
        break;
      case 'Leave':
        statusColor = '#856404';
        statusBg = '#fff3cd';
        statusIcon = '📝';
        break;
      case 'Half Day':
        statusColor = '#0c5460';
        statusBg = '#d1ecf1';
        statusIcon = '⏱';
        break;
    }
    
    content += `
      <div style="background:${statusBg};border-left:4px solid ${statusColor};padding:15px;border-radius:6px;margin-bottom:15px;">
        <div style="font-weight:600;color:${statusColor};">${statusIcon} ${attendance.status}</div>
    `;
    
    if (attendance.check_in_time) {
      content += `<div style="margin-top:10px;color:#495057;"><strong>Check-In:</strong> ${formatTime(attendance.check_in_time)}</div>`;
    }
    
    if (attendance.check_out_time) {
      content += `<div style="color:#495057;"><strong>Check-Out:</strong> ${formatTime(attendance.check_out_time)}</div>`;
    }
    
    if (attendance.total_hours) {
      content += `<div style="color:#495057;"><strong>Total Hours:</strong> ${parseFloat(attendance.total_hours).toFixed(2)}h</div>`;
    }
    
    if (attendance.late_by_minutes && attendance.late_by_minutes > 0) {
      content += `<div style="color:#dc3545;"><strong>Late By:</strong> ${Math.round(attendance.late_by_minutes)} minutes</div>`;
    }
    
    if (attendance.early_leave_minutes && attendance.early_leave_minutes > 0) {
      content += `<div style="color:#ffc107;"><strong>Early Leave:</strong> ${Math.round(attendance.early_leave_minutes)} minutes</div>`;
    }
    
    if (attendance.overtime_minutes && attendance.overtime_minutes > 0) {
      content += `<div style="color:#28a745;"><strong>Overtime:</strong> ${Math.round(attendance.overtime_minutes)} minutes</div>`;
    }
    
    if (attendance.leave_type) {
      content += `<div style="color:#495057;"><strong>Leave Type:</strong> ${attendance.leave_type}</div>`;
    }
    
    if (attendance.remarks) {
      content += `<div style="color:#495057;margin-top:10px;"><strong>Remarks:</strong><br>${attendance.remarks}</div>`;
    }
    
    content += '</div>';
  } else {
    content += `
      <div style="background:#f8f9fa;border-left:4px solid #6c757d;padding:15px;border-radius:6px;margin-bottom:15px;">
        <div style="color:#6c757d;text-align:center;">No attendance record found for this date</div>
      </div>
    `;
  }
  
  document.getElementById('modalContent').innerHTML = content;
  document.getElementById('dayDetailModal').style.display = 'flex';
}

function closeDayDetailModal() {
  document.getElementById('dayDetailModal').style.display = 'none';
}

function formatTime(time) {
  if (!time) return '—';
  const [hours, minutes] = time.split(':');
  const hour = parseInt(hours);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const displayHour = hour % 12 || 12;
  return `${displayHour}:${minutes} ${ampm}`;
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

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
