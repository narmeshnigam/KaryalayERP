<?php
/**
 * Employee Portal - My Attendance History
 * View personal attendance history with filters
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = "My Attendance History - " . APP_NAME;
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

// Filter parameters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$where_conditions = ["employee_id = $employee_id", "attendance_date BETWEEN '$from_date' AND '$to_date'"];

if (!empty($status_filter)) {
    $status_filter_safe = mysqli_real_escape_string($conn, $status_filter);
    $where_conditions[] = "status = '$status_filter_safe'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get attendance records
$sql = "SELECT * FROM attendance WHERE $where_clause ORDER BY attendance_date DESC";
$result = mysqli_query($conn, $sql);
$attendance_records = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance_records[] = $row;
    }
}

// Get summary statistics for the filtered period
$stats_sql = "SELECT 
              COUNT(*) as total_records,
              SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
              SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
              SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
              SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as half_day_count,
              AVG(total_hours) as avg_hours,
              SUM(total_hours) as total_hours,
              SUM(late_by_minutes) as total_late_minutes,
              SUM(overtime_minutes) as total_overtime_minutes
              FROM attendance WHERE $where_clause";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

closeConnection($conn);
?>

<style>
.hist-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.hist-filter-form {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 15px;
    align-items: end;
}
.hist-filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.hist-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}
.hist-table-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.hist-table-responsive {
    overflow-x: auto;
}
@media (max-width: 1200px) {
    .hist-filter-form {
        grid-template-columns: 1fr 1fr auto;
    }
    .hist-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .hist-table-responsive table {
        font-size: 13px;
    }
    .hist-table-responsive th,
    .hist-table-responsive td {
        padding: 8px !important;
    }
}
@media (max-width: 900px) {
    .hist-filter-form {
        grid-template-columns: 1fr 1fr;
    }
    .hist-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .hist-table-responsive table {
        font-size: 12px;
    }
    .hist-table-responsive th,
    .hist-table-responsive td {
        padding: 6px !important;
    }
}
@media (max-width: 600px) {
    .hist-header-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .hist-header-flex > div {
        width: 100%;
    }
    .hist-header-flex .btn {
        width: 100%;
        text-align: center;
        display: block;
    }
    .hist-filter-form {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .hist-filter-buttons {
        grid-column: 1;
        flex-direction: column;
    }
    .hist-filter-buttons .btn {
        width: 100%;
    }
    .hist-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .hist-stats-grid .card {
        padding: 15px !important;
    }
    .hist-stats-grid > div > div:first-child {
        font-size: 28px !important;
    }
    .hist-stats-grid > div > div:last-child {
        font-size: 12px !important;
    }
    .hist-table-header-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .hist-table-header-flex h3 {
        margin-bottom: 8px !important;
    }
    .hist-table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="hist-header-flex">
        <div>
          <h1>📊 My Attendance History</h1>
          <p>View your complete attendance records</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to Dashboard</a>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:25px;">
      <h3 style="margin-bottom:20px;color:#003581;font-size:20px;">🔍 Filter Records</h3>
      <form method="GET" class="hist-filter-form">
        <div class="form-group" style="margin-bottom:0;">
          <label>📅 From Date</label>
          <input type="date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>📅 To Date</label>
          <input type="date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>📊 Status</label>
          <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="Present" <?php echo ($status_filter === 'Present') ? 'selected' : ''; ?>>Present</option>
            <option value="Absent" <?php echo ($status_filter === 'Absent') ? 'selected' : ''; ?>>Absent</option>
            <option value="Half Day" <?php echo ($status_filter === 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
            <option value="Leave" <?php echo ($status_filter === 'Leave') ? 'selected' : ''; ?>>Leave</option>
            <option value="Holiday" <?php echo ($status_filter === 'Holiday') ? 'selected' : ''; ?>>Holiday</option>
            <option value="Week Off" <?php echo ($status_filter === 'Week Off') ? 'selected' : ''; ?>>Week Off</option>
          </select>
        </div>
        <div class="hist-filter-buttons">
          <button type="submit" class="btn" style="padding:10px 20px;white-space:nowrap;">Apply Filters</button>
          <a href="history.php" class="btn btn-accent" style="padding:10px 20px;white-space:nowrap;text-decoration:none;display:inline-block;text-align:center;">Reset</a>
        </div>
      </form>
    </div>

    <!-- Summary Statistics -->
    <div class="hist-stats-grid">
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo (int)($stats['total_records'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.95;">Total Days</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#28a745 0%,#34ce57 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo (int)($stats['present_count'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.95;">Present</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#dc3545 0%,#e85563 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo (int)($stats['absent_count'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.95;">Absent</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#faa718 0%,#ffc04d 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo (int)($stats['leave_count'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.95;">On Leave</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#17a2b8 0%,#20c9e0 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?>h</div>
        <div style="font-size:14px;opacity:0.95;">Total Hours</div>
      </div>
      
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#6f42c1 0%,#8b5cf6 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?>h</div>
        <div style="font-size:14px;opacity:0.95;">Avg. Hours/Day</div>
      </div>
    </div>

    <!-- Attendance Records Table -->
    <div class="card">
      <div class="hist-table-header-flex">
        <h3 style="margin:0;color:#003581;">
          📋 Attendance Records 
          <span style="font-size:14px;color:#6c757d;font-weight:normal;">(<?php echo count($attendance_records); ?> records)</span>
        </h3>
      </div>
      
      <?php if (count($attendance_records) === 0): ?>
        <div class="alert alert-warning">No attendance records found for the selected period.</div>
      <?php else: ?>
        <div class="hist-table-responsive">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Date</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Day</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Status</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Check-In</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Check-Out</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Total Hours</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Late (min)</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Overtime</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">WFH</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Leave Type</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendance_records as $record): ?>
                <tr style="border-bottom:1px solid #dee2e6;">
                  <td style="padding:12px;font-weight:500;"><?php echo date('d M Y', strtotime($record['attendance_date'])); ?></td>
                  <td style="padding:12px;text-align:center;color:#6c757d;font-size:13px;">
                    <?php echo date('D', strtotime($record['attendance_date'])); ?>
                  </td>
                  <td style="padding:12px;text-align:center;">
                    <span style="padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;
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
                    <?php echo $record['late_by_minutes'] > 0 ? round($record['late_by_minutes']) : '—'; ?>
                  </td>
                  <td style="padding:12px;text-align:center;color:<?php echo ($record['overtime_minutes'] > 0) ? '#28a745' : '#6c757d'; ?>;">
                    <?php echo $record['overtime_minutes'] > 0 ? round($record['overtime_minutes']) . 'm' : '—'; ?>
                  </td>
                  <td style="padding:12px;text-align:center;"><?php echo $record['work_from_home'] ? '<span style="color:#28a745;font-size:16px;">✓</span>' : '—'; ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($record['leave_type'] ?? '—'); ?></td>
                  <td style="padding:12px;color:#6c757d;font-size:13px;"><?php echo htmlspecialchars($record['remarks'] ?? '—'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
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

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
