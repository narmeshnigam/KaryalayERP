<?php
/**
 * Attendance List & Reports
 * View and manage attendance records with filtering
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Attendance Records - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Filter parameters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$employee_filter = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

$conn = createConnection(true);

// Safety: Ensure required tables exist before querying
function tableExists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '" . $table . "'");
    if ($res) {
        $exists = mysqli_num_rows($res) > 0;
        mysqli_free_result($res);
        return $exists;
    }
    return false;
}

$hasEmployees = tableExists($conn, 'employees');
$hasAttendance = tableExists($conn, 'attendance');

if (!$hasEmployees || !$hasAttendance) {
    // Close connection early as we won't run further queries
    closeConnection($conn);
    ?>
    <div class="main-wrapper">
      <div class="main-content">
        <div class="page-header">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <h1>📅 Attendance Module Setup Required</h1>
              <p>We couldn't find the required database tables to show attendance records.</p>
            </div>
            <div>
              <a href="../index.php" class="btn btn-accent">← Back to Dashboard</a>
            </div>
          </div>
        </div>

        <div class="card" style="max-width:820px;margin:0 auto;">
          <div class="alert alert-info">
            <strong>ℹ️ What's missing?</strong><br>
            <?php if (!$hasEmployees): ?>
              • Employees table is not set up. Please complete the Employee Module setup first.<br>
            <?php endif; ?>
            <?php if (!$hasAttendance): ?>
              • Attendance table is not set up. Run the Attendance Module setup to proceed.<br>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:8px;">
            <?php if (!$hasEmployees): ?>
              <a href="../../scripts/setup_employees_table.php" class="btn" style="padding:12px 24px;">🚀 Setup Employee Module</a>
            <?php endif; ?>
            <?php if ($hasEmployees && !$hasAttendance): ?>
              <a href="../../scripts/setup_attendance_table.php" class="btn" style="padding:12px 24px;">🚀 Setup Attendance Module</a>
            <?php endif; ?>
            <?php if (!$hasEmployees && !$hasAttendance): ?>
              <a href="../../scripts/setup_employees_table.php" class="btn" style="padding:12px 24px;">1) Setup Employee Module</a>
              <a href="../../scripts/setup_attendance_table.php" class="btn" style="padding:12px 24px;">2) Setup Attendance Module</a>
            <?php endif; ?>
          </div>

          <div style="margin-top:20px;padding:16px;background:#f8f9fa;border-radius:8px;color:#6c757d;">
            After setup, return to this page to view attendance records and reports.
          </div>
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

// Build query with filters
$where_conditions = ["a.attendance_date BETWEEN '$from_date' AND '$to_date'"];

if ($employee_filter > 0) {
    $where_conditions[] = "e.id = $employee_filter";
}

if (!empty($status_filter)) {
    $status_filter = mysqli_real_escape_string($conn, $status_filter);
    $where_conditions[] = "a.status = '$status_filter'";
}

if (!empty($department_filter)) {
    $department_filter = mysqli_real_escape_string($conn, $department_filter);
    $where_conditions[] = "e.department = '$department_filter'";
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch attendance records
$sql = "SELECT a.*, 
        e.employee_code, e.first_name, e.middle_name, e.last_name, 
        e.department, e.designation,
        CONCAT(e.first_name, ' ', IFNULL(e.middle_name, ''), ' ', e.last_name) as employee_name
        FROM attendance a
        INNER JOIN employees e ON a.employee_id = e.id
        WHERE $where_clause
        ORDER BY a.attendance_date DESC, e.employee_code";

$result = mysqli_query($conn, $sql);
$attendance_records = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance_records[] = $row;
    }
}

// Fetch all employees for filter dropdown
$emp_sql = "SELECT id, employee_code, first_name, middle_name, last_name FROM employees WHERE status = 'Active' ORDER BY employee_code";
$emp_result = mysqli_query($conn, $emp_sql);
$employees = [];
if ($emp_result) {
    while ($row = mysqli_fetch_assoc($emp_result)) {
        $employees[] = $row;
    }
}

// Fetch departments for filter
$dept_sql = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department";
$dept_result = mysqli_query($conn, $dept_sql);
$departments = [];
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row['department'];
    }
}

// Get summary statistics
$stats_sql = "SELECT 
              COUNT(*) as total_records,
              SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
              SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
              SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
              SUM(CASE WHEN a.status = 'Half Day' THEN 1 ELSE 0 END) as half_day_count,
              AVG(a.total_hours) as avg_hours,
              SUM(a.late_by_minutes) as total_late_minutes
              FROM attendance a
              INNER JOIN employees e ON a.employee_id = e.id
              WHERE $where_clause";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

closeConnection($conn);
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>📊 Attendance Records</h1>
          <p>View and manage employee attendance</p>
        </div>
        <div>
          <a href="approve_leave.php" class="btn" style="background:#28a745;">✈️ Leave Requests</a>
          <a href="mark_attendance.php" class="btn btn-accent" style="margin-left:8px;">📋 Mark Attendance</a>
          <a href="export_attendance.php?<?php echo http_build_query($_GET); ?>" class="btn" style="margin-left:8px;">📥 Export</a>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:25px;">
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo $stats['total_records'] ?? 0; ?></div>
        <div style="font-size:14px;opacity:0.95;">Total Records</div>
      </div>
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#28a745 0%,#34ce57 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo $stats['present_count'] ?? 0; ?></div>
        <div style="font-size:14px;opacity:0.95;">Present</div>
      </div>
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#dc3545 0%,#e85563 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo $stats['absent_count'] ?? 0; ?></div>
        <div style="font-size:14px;opacity:0.95;">Absent</div>
      </div>
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#faa718 0%,#ffc04d 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo $stats['leave_count'] ?? 0; ?></div>
        <div style="font-size:14px;opacity:0.95;">On Leave</div>
      </div>
      <div class="card" style="text-align:center;background:linear-gradient(135deg,#17a2b8 0%,#20c9e0 100%);color:white;">
        <div style="font-size:36px;font-weight:700;margin-bottom:8px;"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?>h</div>
        <div style="font-size:14px;opacity:0.95;">Avg. Hours</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:25px;">
      <h3 style="margin-bottom:20px;color:#003581;font-size:20px;">🔍 Filter Attendance</h3>
      <form method="GET" style="display:grid;grid-template-columns:1fr 1fr 1.5fr 1fr 1fr auto;gap:15px;align-items:end;">
        <div class="form-group" style="margin-bottom:0;">
          <label>📅 From Date</label>
          <input type="date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>📅 To Date</label>
          <input type="date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>👤 Employee</label>
          <select name="employee" class="form-control">
            <option value="">All Employees</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo $emp['id']; ?>" <?php echo ($employee_filter == $emp['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>🏢 Department</label>
          <select name="department" class="form-control">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department_filter === $dept) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($dept); ?>
              </option>
            <?php endforeach; ?>
          </select>
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
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn" style="padding:10px 20px;white-space:nowrap;">Apply Filters</button>
          <a href="index.php" class="btn btn-accent" style="padding:10px 20px;white-space:nowrap;text-decoration:none;display:inline-block;text-align:center;">Reset</a>
        </div>
      </form>
    </div>

    <!-- Attendance Table -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="margin:0;color:#003581;">
          📋 Attendance Records 
          <span style="font-size:14px;color:#6c757d;font-weight:normal;">(<?php echo count($attendance_records); ?> records)</span>
        </h3>
        <a href="export_attendance.php?<?php echo http_build_query($_GET); ?>" class="btn btn-accent" style="padding:8px 16px;font-size:13px;">
          📊 Export to Excel
        </a>
      </div>
      
      <?php if (count($attendance_records) === 0): ?>
        <div class="alert alert-warning" style="margin-top:16px;">No attendance records found for the selected filters.</div>
      <?php else: ?>
        <div style="overflow-x:auto;margin-top:16px;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Date</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Employee Code</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Employee Name</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Department</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Status</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Check-In</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Check-Out</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Total Hours</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Late (min)</th>
                <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">WFH</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Leave Type</th>
                <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendance_records as $record): ?>
                <tr style="border-bottom:1px solid #dee2e6;">
                  <td style="padding:12px;font-weight:500;"><?php echo date('d M Y', strtotime($record['attendance_date'])); ?></td>
                  <td style="padding:12px;font-weight:600;color:#003581;"><?php echo htmlspecialchars($record['employee_code']); ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($record['employee_name']); ?></td>
                  <td style="padding:12px;"><?php echo htmlspecialchars($record['department'] ?? '—'); ?></td>
                  <td style="padding:12px;text-align:center;">
                    <span style="padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;
                      <?php
                        switch($record['status']) {
                          case 'Present':
                            echo 'background:#d4edda;color:#155724;';
                            break;
                          case 'Absent':
                            echo 'background:#f8d7da;color:#721c24;';
                            break;
                          case 'Half Day':
                            echo 'background:#fff3cd;color:#856404;';
                            break;
                          case 'Leave':
                            echo 'background:#cce5ff;color:#004085;';
                            break;
                          case 'Holiday':
                            echo 'background:#d1ecf1;color:#0c5460;';
                            break;
                          case 'Week Off':
                            echo 'background:#e2e3e5;color:#383d41;';
                            break;
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
                  <td style="padding:12px;text-align:center;font-weight:600;color:#17a2b8;"><?php echo $record['total_hours'] ? number_format($record['total_hours'], 2) . 'h' : '—'; ?></td>
                  <td style="padding:12px;text-align:center;color:<?php echo ($record['late_by_minutes'] > 0) ? '#dc3545' : '#6c757d'; ?>;">
                    <?php echo $record['late_by_minutes'] > 0 ? $record['late_by_minutes'] : '—'; ?>
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

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
