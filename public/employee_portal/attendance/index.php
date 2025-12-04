<?php
/**
 * Employee Portal - My Attendance Dashboard
 * Self-service attendance check-in/check-out and status view
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = "My Attendance - " . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

$conn = createConnection(true);

// Get employee info from user_id
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
    echo '<div class="alert alert-error">No employee record found for your account. Please contact HR.</div>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

$employee_id = $employee['id'];
$today = date('Y-m-d');

// Handle check-in/check-out
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $current_time = date('H:i:s');
        
        // Get geo-location data if provided
        $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        
        if ($action === 'check_in') {
            // Check if already checked in today
            $check_query = "SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'is', $employee_id, $today);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "You have already checked in today!";
            } else {
                // Calculate late minutes (assuming 9:00 AM is standard)
                $standard_in = strtotime('09:00:00');
                $actual_in = strtotime($current_time);
                $late_minutes = max(0, ($actual_in - $standard_in) / 60);
                $late_minutes = (int) round($late_minutes);

                $checkinLatPlaceholder = ($latitude !== null) ? '?' : 'NULL';
                $checkinLonPlaceholder = ($longitude !== null) ? '?' : 'NULL';

                $insert_query = "INSERT INTO attendance 
                                (employee_id, attendance_date, check_in_time, checkin_latitude, checkin_longitude, 
                                 status, late_by_minutes, marked_by, approval_status) 
                                VALUES (?, ?, ?, $checkinLatPlaceholder, $checkinLonPlaceholder, 'Present', ?, ?, 'Approved')";
                $insert_stmt = mysqli_prepare($conn, $insert_query);

                $insert_types = 'iss';
                $insert_params = [$employee_id, $today, $current_time];

                if ($latitude !== null) {
                    $insert_types .= 'd';
                    $insert_params[] = $latitude;
                }

                if ($longitude !== null) {
                    $insert_types .= 'd';
                    $insert_params[] = $longitude;
                }

                $insert_types .= 'ii';
                $insert_params[] = $late_minutes;
                $insert_params[] = $user_id;

                $insert_bind_params = [&$insert_types];
        foreach ($insert_params as $key => $value) {
          $insert_bind_params[] = &$insert_params[$key];
        }
        call_user_func_array([$insert_stmt, 'bind_param'], $insert_bind_params);
        if (isset($value)) {
          unset($value);
        }

                if (mysqli_stmt_execute($insert_stmt)) {
                    $location_msg = ($latitude !== null && $longitude !== null) ? " (Location captured)" : "";
                    $message = "Check-in successful! Time: " . date('h:i A', strtotime($current_time)) . $location_msg;
                } else {
                    $error = "Error during check-in. Please try again.";
                }
                mysqli_stmt_close($insert_stmt);
            }
            mysqli_stmt_close($check_stmt);
            
        } elseif ($action === 'check_out') {
            // Get today's attendance record
            $get_query = "SELECT id, check_in_time FROM attendance WHERE employee_id = ? AND attendance_date = ?";
            $get_stmt = mysqli_prepare($conn, $get_query);
            mysqli_stmt_bind_param($get_stmt, 'is', $employee_id, $today);
            mysqli_stmt_execute($get_stmt);
            $get_result = mysqli_stmt_get_result($get_stmt);
            
            if (mysqli_num_rows($get_result) == 0) {
                $error = "You must check in first!";
            } else {
                $attendance = mysqli_fetch_assoc($get_result);
                
                if ($attendance['check_in_time']) {
                    // Calculate total hours
                    $check_in = strtotime($attendance['check_in_time']);
                    $check_out = strtotime($current_time);
                    $total_hours = ($check_out - $check_in) / 3600;
                    
                    // Calculate early leave (assuming 6:00 PM is standard)
                    $standard_out = strtotime('18:00:00');
                    $early_leave = max(0, ($standard_out - $check_out) / 60);
                    $early_leave = (int) round($early_leave);
                    
                    // Calculate overtime
                    $overtime = max(0, ($check_out - $standard_out) / 60);
                    $overtime = (int) round($overtime);

                    $checkoutLatSet = ($latitude !== null) ? 'checkout_latitude = ?' : 'checkout_latitude = NULL';
                    $checkoutLonSet = ($longitude !== null) ? 'checkout_longitude = ?' : 'checkout_longitude = NULL';

                    $update_query = "UPDATE attendance 
                                    SET check_out_time = ?, $checkoutLatSet, $checkoutLonSet, 
                                        total_hours = ?, early_leave_minutes = ?, overtime_minutes = ?
                                    WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);

                    $update_types = 's';
                    $update_params = [$current_time];

                    if ($latitude !== null) {
                        $update_types .= 'd';
                        $update_params[] = $latitude;
                    }

          if ($longitude !== null) {
            $update_types .= 'd';
            $update_params[] = $longitude;
          }

          $update_types .= 'dii';
          $update_params[] = $total_hours;
          $update_params[] = $early_leave;
          $update_params[] = $overtime;

                    $update_types .= 'i';
                    $update_params[] = $attendance['id'];

                    $update_bind_params = [&$update_types];
          foreach ($update_params as $key => $value) {
            $update_bind_params[] = &$update_params[$key];
          }
          call_user_func_array([$update_stmt, 'bind_param'], $update_bind_params);
          if (isset($value)) {
            unset($value);
          }
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $location_msg = ($latitude !== null && $longitude !== null) ? " (Location captured)" : "";
                        $message = "Check-out successful! Time: " . date('h:i A', strtotime($current_time)) . " | Total Hours: " . number_format($total_hours, 2) . "h" . $location_msg;
                    } else {
                        $error = "Error during check-out. Please try again.";
                    }
                    mysqli_stmt_close($update_stmt);
                }
            }
            mysqli_stmt_close($get_stmt);
        }
    }
}

// Get today's attendance status
$today_query = "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?";
$today_stmt = mysqli_prepare($conn, $today_query);
mysqli_stmt_bind_param($today_stmt, 'is', $employee_id, $today);
mysqli_stmt_execute($today_stmt);
$today_result = mysqli_stmt_get_result($today_stmt);
$today_attendance = mysqli_fetch_assoc($today_result);
mysqli_stmt_close($today_stmt);

// Get this month's stats
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$stats_query = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
                SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as half_days,
                AVG(total_hours) as avg_hours,
                SUM(late_by_minutes) as total_late_minutes,
                SUM(overtime_minutes) as total_overtime_minutes
                FROM attendance 
                WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, 'iss', $employee_id, $month_start, $month_end);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$month_stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

// Get recent attendance (last 7 days)
$recent_query = "SELECT * FROM attendance 
                WHERE employee_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ORDER BY attendance_date DESC";
$recent_stmt = mysqli_prepare($conn, $recent_query);
mysqli_stmt_bind_param($recent_stmt, 'i', $employee_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);
$recent_attendance = [];
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_attendance[] = $row;
}
mysqli_stmt_close($recent_stmt);

closeConnection($conn);
?>

<style>
.emp-att-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.emp-att-header-btns {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.emp-att-today-card {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 30px;
    align-items: center;
}
.emp-att-today-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.emp-att-today-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.emp-att-monthly-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}
.emp-att-table-responsive {
    overflow-x: auto;
}
@media (max-width: 1200px) {
    .emp-att-today-card {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .emp-att-today-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 900px) {
    .emp-att-table-responsive table {
        font-size: 12px;
    }
    .emp-att-table-responsive th,
    .emp-att-table-responsive td {
        padding: 8px !important;
    }
}
@media (max-width: 600px) {
    .emp-att-header-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .emp-att-header-flex > div {
        width: 100%;
    }
    .emp-att-header-btns {
        flex-direction: column;
        width: 100%;
    }
    .emp-att-header-btns .btn {
        width: 100%;
    }
    .emp-att-today-card {
        grid-template-columns: 1fr;
    }
    .emp-att-today-stats {
        grid-template-columns: 1fr;
    }
    .emp-att-today-stats > div:nth-child(4) {
        grid-column: auto;
    }
    .emp-att-today-actions {
        width: 100%;
    }
    .emp-att-today-actions .btn {
        width: 100%;
        padding: 12px 20px !important;
        font-size: 14px !important;
    }
    .emp-att-monthly-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    .emp-att-monthly-grid .card {
        padding: 15px !important;
    }
    .emp-att-table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="emp-att-header-flex">
        <div>
          <h1>⏰ My Attendance</h1>
          <p>Track your attendance and working hours</p>
        </div>
        <div class="emp-att-header-btns">
          <a href="calendar.php" class="btn btn-accent">📅 Calendar View</a>
          <a href="history.php" class="btn">📊 View History</a>
          <a href="request_leave.php" class="btn btn-accent">📝 Request Leave</a>
        </div>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Today's Status Card -->
    <div class="card" style="margin-bottom:25px;background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:white;">
      <div class="emp-att-today-card">
        <div>
          <h2 style="margin:0 0 10px 0;font-size:24px;">📅 Today: <?php echo date('l, d M Y'); ?></h2>
          
          <?php if ($today_attendance): ?>
            <div class="emp-att-today-stats">
              <div>
                <div style="opacity:0.9;font-size:13px;margin-bottom:5px;">Check-In</div>
                <div style="font-size:20px;font-weight:700;">
                  <?php echo $today_attendance['check_in_time'] ? date('h:i A', strtotime($today_attendance['check_in_time'])) : '—'; ?>
                </div>
              </div>
              <div>
                <div style="opacity:0.9;font-size:13px;margin-bottom:5px;">Check-Out</div>
                <div style="font-size:20px;font-weight:700;">
                  <?php echo $today_attendance['check_out_time'] ? date('h:i A', strtotime($today_attendance['check_out_time'])) : '—'; ?>
                </div>
              </div>
              <div>
                <div style="opacity:0.9;font-size:13px;margin-bottom:5px;">Working Hours</div>
                <div style="font-size:20px;font-weight:700;">
                  <?php echo $today_attendance['total_hours'] ? number_format($today_attendance['total_hours'], 2) . 'h' : '—'; ?>
                </div>
              </div>
              <div>
                <div style="opacity:0.9;font-size:13px;margin-bottom:5px;">Status</div>
                <div style="font-size:16px;font-weight:600;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:20px;display:inline-block;">
                  <?php echo htmlspecialchars($today_attendance['status']); ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div style="font-size:18px;opacity:0.9;margin-top:15px;">No attendance marked yet for today</div>
          <?php endif; ?>
        </div>

        <div class="emp-att-today-actions">
          <?php if (!$today_attendance): ?>
            <button type="button" class="btn location-action" data-action="check_in" style="background:#28a745;border:none;padding:15px 30px;font-size:16px;white-space:nowrap;">
              ✓ Check In
            </button>
          <?php elseif ($today_attendance && !$today_attendance['check_out_time']): ?>
            <button type="button" class="btn location-action" data-action="check_out" style="background:#faa718;border:none;padding:15px 30px;font-size:16px;white-space:nowrap;">
              → Check Out
            </button>
          <?php else: ?>
            <button disabled class="btn" style="background:#6c757d;border:none;padding:15px 30px;font-size:16px;cursor:not-allowed;">
              ✓ Completed
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Monthly Statistics -->
    <div class="emp-att-monthly-grid">
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#28a745 0%,#34ce57 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo (int)($month_stats['present_days'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.95;">Days Present</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#dc3545 0%,#e85563 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo (int)($month_stats['absent_days'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.95;">Days Absent</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#faa718 0%,#ffc04d 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo (int)($month_stats['leave_days'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.95;">Days on Leave</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#17a2b8 0%,#20c9e0 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo number_format($month_stats['avg_hours'] ?? 0, 1); ?>h</div>
        <div style="font-size:14px;opacity:0.95;">Avg. Daily Hours</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#6f42c1 0%,#8b5cf6 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo round(($month_stats['total_late_minutes'] ?? 0) / 60, 1); ?>h</div>
        <div style="font-size:14px;opacity:0.95;">Total Late Time</div>
      </div>
    </div>

    <!-- Recent Attendance -->
    <div class="card">
      <h3 style="margin:0 0 20px 0;color:#003581;">📊 Recent Attendance (Last 7 Days)</h3>
      
      <?php if (count($recent_attendance) > 0): ?>
        <div class="emp-att-table-responsive">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Date</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Status</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Check-In</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Check-Out</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Total Hours</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Late</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Overtime</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_attendance as $record): ?>
                <tr style="border-bottom:1px solid #dee2e6;">
                  <td style="padding:12px;font-weight:500;">
                    <?php echo date('d M Y (D)', strtotime($record['attendance_date'])); ?>
                  </td>
                  <td style="padding:12px;text-align:center;">
                    <span style="padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;
                      <?php
                        switch($record['status']) {
                          case 'Present': echo 'background:#d4edda;color:#155724;'; break;
                          case 'Absent': echo 'background:#f8d7da;color:#721c24;'; break;
                          case 'Half Day': echo 'background:#fff3cd;color:#856404;'; break;
                          case 'Leave': echo 'background:#cce5ff;color:#004085;'; break;
                          case 'Holiday': echo 'background:#d1ecf1;color:#0c5460;'; break;
                          case 'Week Off': echo 'background:#e2e3e5;color:#383d41;'; break;
                        }
                      ?>">
                      <?php echo htmlspecialchars($record['status']); ?>
                    </span>
                  </td>
                  <td style="padding:12px;text-align:center;">
                    <?php if ($record['check_in_time']): ?>
                      <?php echo date('h:i A', strtotime($record['check_in_time'])); ?>
                      <?php if ($record['checkin_latitude'] !== null && $record['checkin_longitude'] !== null): ?>
                        <a href="javascript:void(0)" 
                           onclick="showLocationMap(<?php echo $record['checkin_latitude']; ?>, <?php echo $record['checkin_longitude']; ?>, 'Check-In Location')"
                           style="margin-left:5px;color:#17a2b8;text-decoration:none;font-size:14px;"
                           title="View check-in location">
                          📍
                        </a>
                      <?php endif; ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;text-align:center;">
                    <?php if ($record['check_out_time']): ?>
                      <?php echo date('h:i A', strtotime($record['check_out_time'])); ?>
                      <?php if ($record['checkout_latitude'] !== null && $record['checkout_longitude'] !== null): ?>
                        <a href="javascript:void(0)" 
                           onclick="showLocationMap(<?php echo $record['checkout_latitude']; ?>, <?php echo $record['checkout_longitude']; ?>, 'Check-Out Location')"
                           style="margin-left:5px;color:#17a2b8;text-decoration:none;font-size:14px;"
                           title="View check-out location">
                          📍
                        </a>
                      <?php endif; ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;text-align:center;font-weight:600;color:#17a2b8;">
                    <?php echo $record['total_hours'] ? number_format($record['total_hours'], 2) . 'h' : '—'; ?>
                  </td>
                  <td style="padding:12px;text-align:center;color:<?php echo ($record['late_by_minutes'] > 0) ? '#dc3545' : '#6c757d'; ?>;">
                    <?php echo $record['late_by_minutes'] > 0 ? round($record['late_by_minutes']) . 'm' : '—'; ?>
                  </td>
                  <td style="padding:12px;text-align:center;color:<?php echo ($record['overtime_minutes'] > 0) ? '#28a745' : '#6c757d'; ?>;">
                    <?php echo $record['overtime_minutes'] > 0 ? round($record['overtime_minutes']) . 'm' : '—'; ?>
                  </td>
                  <td style="padding:12px;color:#6c757d;font-size:13px;">
                    <?php echo htmlspecialchars($record['remarks'] ?? '—'); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="alert alert-info">No attendance records found for the last 7 days.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Geolocation Modal -->
<div id="locationModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;padding:30px;max-width:500px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <h3 style="margin:0 0 15px 0;color:#003581;">📍 Capturing Location</h3>
    <div id="locationStatus">
      <div style="text-align:center;padding:20px;">
        <div style="display:inline-block;width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #003581;border-radius:50%;animation:spin 1s linear infinite;"></div>
        <p style="margin-top:15px;color:#6c757d;">Please allow location access...</p>
      </div>
    </div>
    <div style="text-align:center;margin-top:20px;">
      <button onclick="closeLocationModal()" class="btn btn-accent">Cancel</button>
    </div>
  </div>
</div>

<style>
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>

<script>
let currentAction = '';

function showLocationModal(action) {
  currentAction = action;
  document.getElementById('locationModal').style.display = 'flex';
  document.getElementById('locationStatus').innerHTML = `
    <div style="text-align:center;padding:20px;">
      <div style="display:inline-block;width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #003581;border-radius:50%;animation:spin 1s linear infinite;"></div>
      <p style="margin-top:15px;color:#6c757d;">Please allow location access...</p>
    </div>
  `;
  
  // Request geolocation
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      function(position) {
        onLocationSuccess(position);
      },
      function(error) {
        onLocationError(error);
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      }
    );
  } else {
    document.getElementById('locationStatus').innerHTML = `
      <div class="alert alert-error" style="margin:0;">
        Your browser doesn't support geolocation. Proceeding without location...
      </div>
    `;
    setTimeout(submitWithoutLocation, 2000);
  }
}

function onLocationSuccess(position) {
  const lat = position.coords.latitude;
  const lon = position.coords.longitude;
  const accuracy = position.coords.accuracy;
  
  document.getElementById('locationStatus').innerHTML = `
    <div class="alert alert-success" style="margin:0;">
      ✓ Location captured successfully!<br>
      <small style="color:#6c757d;">Accuracy: ${Math.round(accuracy)}m</small>
    </div>
  `;
  
  setTimeout(() => submitWithLocation(lat, lon), 1000);
}

function onLocationError(error) {
  let errorMsg = 'Location access denied.';
  switch(error.code) {
    case error.PERMISSION_DENIED:
      errorMsg = 'Location access denied. Proceeding without location...';
      break;
    case error.POSITION_UNAVAILABLE:
      errorMsg = 'Location unavailable. Proceeding without location...';
      break;
    case error.TIMEOUT:
      errorMsg = 'Location request timeout. Proceeding without location...';
      break;
  }
  
  document.getElementById('locationStatus').innerHTML = `
    <div class="alert alert-error" style="margin:0;">
      ${errorMsg}
    </div>
  `;
  
  setTimeout(submitWithoutLocation, 2000);
}

function submitWithLocation(lat, lon) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '';
  
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'action';
  actionInput.value = currentAction;
  form.appendChild(actionInput);
  
  const latInput = document.createElement('input');
  latInput.type = 'hidden';
  latInput.name = 'latitude';
  latInput.value = lat;
  form.appendChild(latInput);
  
  const lonInput = document.createElement('input');
  lonInput.type = 'hidden';
  lonInput.name = 'longitude';
  lonInput.value = lon;
  form.appendChild(lonInput);
  
  document.body.appendChild(form);
  closeLocationModal();
  form.submit();
}

function submitWithoutLocation() {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '';
  
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'action';
  actionInput.value = currentAction;
  form.appendChild(actionInput);
  
  document.body.appendChild(form);
  closeLocationModal();
  form.submit();
}

function closeLocationModal() {
  const modal = document.getElementById('locationModal');
  if (modal) {
    modal.style.display = 'none';
  }
  currentAction = '';
}

// Attach to buttons
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.location-action').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const action = this.getAttribute('data-action');
      if (action) {
        showLocationModal(action);
      }
    });
  });
});

// Location Map Modal Functions
function showLocationMap(lat, lon, title) {
  if (!document.getElementById('locationMapModal')) {
    // Create modal if it doesn't exist
    createLocationMapModal();
  }

  const modal = document.getElementById('locationMapModal');
  const titleEl = document.getElementById('mapLocationTitle');
  const coordsEl = document.getElementById('mapLocationCoords');
  const frameEl = document.getElementById('mapLocationFrame');

  if (!modal || !titleEl || !coordsEl || !frameEl) {
    console.warn('Location modal elements missing');
    return;
  }

  titleEl.textContent = '📍 ' + title;
  coordsEl.textContent = `Coordinates: ${lat.toFixed(6)}, ${lon.toFixed(6)}`;

  // Google Maps embed URL
  const mapUrl = `https://www.google.com/maps?q=${lat},${lon}&z=15&output=embed`;
  frameEl.src = mapUrl;

  modal.style.display = 'flex';
}

function closeLocationMap() {
  const modal = document.getElementById('locationMapModal');
  const frameEl = document.getElementById('mapLocationFrame');
  if (modal) {
    modal.style.display = 'none';
  }
  if (frameEl) {
    frameEl.src = '';
  }
}

function createLocationMapModal() {
  const modalHTML = `
    <div id="locationMapModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:10000;align-items:center;justify-content:center;">
      <div style="background:white;border-radius:12px;padding:0;max-width:900px;width:90%;max-height:90vh;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
        <div style="padding:20px;border-bottom:1px solid #dee2e6;display:flex;justify-content:space-between;align-items:center;background:#003581;color:white;">
          <h3 style="margin:0;" id="mapLocationTitle">📍 Location</h3>
          <button onclick="closeLocationMap()" style="background:none;border:none;color:white;font-size:24px;cursor:pointer;padding:0;line-height:1;">&times;</button>
        </div>
        <div style="height:500px;">
          <iframe id="mapLocationFrame" width="100%" height="100%" frameborder="0" style="border:0;" allowfullscreen></iframe>
        </div>
        <div style="padding:15px;background:#f8f9fa;text-align:center;font-size:13px;color:#6c757d;">
          <span id="mapLocationCoords"></span>
        </div>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLocationMap();
    closeLocationModal();
  }
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
