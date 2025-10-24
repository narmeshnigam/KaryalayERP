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

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
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
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-accent">
          ← Previous
        </a>
        <h2 style="margin:0;color:#003581;"><?php echo $month_name . ' ' . $year; ?></h2>
        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-accent">
          Next →
        </a>
      </div>

      <!-- Monthly Statistics -->
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:15px;margin-bottom:25px;">
        <div style="padding:15px;background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo $present_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Present Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo $absent_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Absent Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo $leave_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Leave Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#17a2b8 0%,#138496 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo $half_day_count; ?></div>
          <div style="font-size:13px;opacity:0.9;">Half Days</div>
        </div>

        <div style="padding:15px;background:linear-gradient(135deg,#6f42c1 0%,#5a32a3 100%);color:white;border-radius:8px;">
          <div style="font-size:28px;font-weight:700;margin-bottom:5px;"><?php echo number_format($total_hours, 1); ?></div>
          <div style="font-size:13px;opacity:0.9;">Total Hours</div>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div style="background:#f8f9fa;padding:20px;border-radius:8px;">
        <!-- Day Headers -->
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:10px;">
          <?php
          $days_of_week = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
          foreach ($days_of_week as $day):
          ?>
            <div style="text-align:center;font-weight:700;color:#003581;padding:10px;background:white;border-radius:6px;">
              <?php echo $day; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Calendar Days -->
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;">
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
            
            if ($holiday) {
              $bg_color = '#fff3cd';
              $badge = '<div style="margin-top:5px;padding:3px 6px;background:#856404;color:white;border-radius:10px;font-size:10px;">Holiday</div>';
            } elseif ($is_weekend) {
              $bg_color = '#f8f9fa';
            }
            
            if ($attendance) {
              switch ($attendance['status']) {
                case 'Present':
                  $bg_color = '#d4edda';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#28a745;color:white;border-radius:10px;font-size:10px;">Present</div>';
                  if ($attendance['check_in_time']) {
                    $badge .= '<div style="font-size:10px;color:#495057;margin-top:3px;">' . date('g:i A', strtotime($attendance['check_in_time'])) . '</div>';
                  }
                  break;
                case 'Absent':
                  $bg_color = '#f8d7da';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#dc3545;color:white;border-radius:10px;font-size:10px;">Absent</div>';
                  break;
                case 'Leave':
                  $bg_color = '#fff3cd';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#ffc107;color:#212529;border-radius:10px;font-size:10px;">Leave</div>';
                  if ($attendance['leave_type']) {
                    $badge .= '<div style="font-size:10px;color:#495057;margin-top:3px;">' . htmlspecialchars($attendance['leave_type']) . '</div>';
                  }
                  break;
                case 'Half Day':
                  $bg_color = '#d1ecf1';
                  $badge = '<div style="margin-top:5px;padding:3px 6px;background:#17a2b8;color:white;border-radius:10px;font-size:10px;">Half Day</div>';
                  break;
              }
            }
            
            echo '<div style="aspect-ratio:1;background:' . $bg_color . ';border:' . $border . ';border-radius:6px;padding:8px;display:flex;flex-direction:column;position:relative;">';
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
        <div style="display:flex;flex-wrap:wrap;gap:15px;">
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

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
