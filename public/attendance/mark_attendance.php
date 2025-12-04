<?php
/**
 * Mark Attendance
 * Interface for marking daily attendance
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$page_title = "Mark Attendance - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<style>
.mark-att-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.mark-att-date-filter {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 15px;
    align-items: end;
}
.mark-att-title-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}
.mark-att-title-btns {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.mark-att-table-responsive {
    overflow-x: auto;
}
@media (max-width: 1200px) {
    .mark-att-table-responsive table {
        font-size: 13px;
    }
    .mark-att-table-responsive th,
    .mark-att-table-responsive td {
        padding: 8px !important;
    }
}
@media (max-width: 900px) {
    .mark-att-table-responsive table {
        font-size: 12px;
    }
    .mark-att-table-responsive th,
    .mark-att-table-responsive td {
        padding: 6px !important;
    }
    .mark-att-table-responsive input,
    .mark-att-table-responsive select {
        font-size: 12px;
        padding: 4px 8px;
    }
}
@media (max-width: 600px) {
    .mark-att-header-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .mark-att-header-flex > div {
        width: 100%;
    }
    .mark-att-header-flex .btn {
        width: 100%;
        text-align: center;
        display: block;
    }
    .mark-att-date-filter {
        grid-template-columns: 1fr;
    }
    .mark-att-date-filter .btn {
        width: 100%;
    }
    .mark-att-title-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .mark-att-title-flex h3 {
        font-size: 16px;
        margin-bottom: 12px !important;
    }
    .mark-att-title-btns {
        flex-direction: row;
        width: 100%;
        gap: 8px;
    }
    .mark-att-title-btns .btn {
        flex: 1;
        padding: 8px 12px !important;
        font-size: 13px;
    }
    .mark-att-table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0 -15px;
    }
    .mark-att-table-responsive table {
        font-size: 12px;
        min-width: 900px;
    }
    .mark-att-table-responsive thead tr {
        background: #f8f9fa !important;
    }
    .mark-att-table-responsive th {
        padding: 8px 6px !important;
        font-weight: 600;
        color: #003581;
        white-space: nowrap;
        border-bottom: 2px solid #dee2e6;
        font-size: 11px;
    }
    .mark-att-table-responsive td {
        padding: 6px !important;
        border-bottom: 1px solid #dee2e6;
        height: auto;
    }
    .mark-att-table-responsive input[type="text"],
    .mark-att-table-responsive input[type="time"],
    .mark-att-table-responsive select {
        font-size: 12px;
        padding: 4px 6px !important;
        width: 100%;
        height: 30px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .mark-att-table-responsive input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
}
</style>

<?php

$message = '';
$error = '';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_data'])) {
    $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
    $attendance_data = $_POST['attendance_data'];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($attendance_data as $employee_id => $data) {
        $employee_id = (int)$employee_id;
        $status = mysqli_real_escape_string($conn, $data['status']);
        $check_in = !empty($data['check_in']) ? "'" . mysqli_real_escape_string($conn, $data['check_in']) . "'" : "NULL";
        $check_out = !empty($data['check_out']) ? "'" . mysqli_real_escape_string($conn, $data['check_out']) . "'" : "NULL";
        $remarks = mysqli_real_escape_string($conn, $data['remarks'] ?? '');
        $work_from_home = isset($data['work_from_home']) ? 1 : 0;
        $leave_type = !empty($data['leave_type']) ? "'" . mysqli_real_escape_string($conn, $data['leave_type']) . "'" : "NULL";
        
        // Calculate total hours
        $total_hours = "NULL";
        if (!empty($data['check_in']) && !empty($data['check_out'])) {
            $check_in_time = strtotime($data['check_in']);
            $check_out_time = strtotime($data['check_out']);
            if ($check_out_time > $check_in_time) {
                $hours = ($check_out_time - $check_in_time) / 3600;
                $total_hours = number_format($hours, 2);
            }
        }
        
        // Calculate late/early leave (assuming standard time is 9:00 AM to 6:00 PM)
        $late_minutes = 0;
        $early_leave_minutes = 0;
        
        if (!empty($data['check_in']) && $status === 'Present') {
            $check_in_time = strtotime($data['check_in']);
            $standard_in = strtotime('09:00:00');
            if ($check_in_time > $standard_in) {
                $late_minutes = ($check_in_time - $standard_in) / 60;
            }
        }
        
        if (!empty($data['check_out']) && $status === 'Present') {
            $check_out_time = strtotime($data['check_out']);
            $standard_out = strtotime('18:00:00');
            if ($check_out_time < $standard_out) {
                $early_leave_minutes = ($standard_out - $check_out_time) / 60;
            }
        }
        
        $sql = "INSERT INTO attendance 
                (employee_id, attendance_date, check_in_time, check_out_time, status, 
                total_hours, late_by_minutes, early_leave_minutes, remarks, 
                work_from_home, leave_type, marked_by, approval_status)
                VALUES 
                ($employee_id, '$attendance_date', $check_in, $check_out, '$status', 
                $total_hours, $late_minutes, $early_leave_minutes, '$remarks', 
                $work_from_home, $leave_type, {$CURRENT_USER_ID}, 'Approved')
                ON DUPLICATE KEY UPDATE 
                check_in_time = VALUES(check_in_time),
                check_out_time = VALUES(check_out_time),
                status = VALUES(status),
                total_hours = VALUES(total_hours),
                late_by_minutes = VALUES(late_by_minutes),
                early_leave_minutes = VALUES(early_leave_minutes),
                remarks = VALUES(remarks),
                work_from_home = VALUES(work_from_home),
                leave_type = VALUES(leave_type),
                marked_by = VALUES(marked_by),
                approval_status = VALUES(approval_status)
                -- Note: geo-coordinates (checkin_latitude, checkin_longitude, checkout_latitude, checkout_longitude) 
                -- are intentionally NOT updated to preserve employee self-check-in location data";
        
        if (mysqli_query($conn, $sql)) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    if ($success_count > 0) {
        $message = "Attendance marked successfully for $success_count employee(s)!";
    }
    if ($error_count > 0) {
        $error = "Failed to mark attendance for $error_count employee(s).";
    }
}

// Fetch employees with their existing attendance for the selected date
$sql = "SELECT e.id, e.employee_code, e.first_name, e.middle_name, e.last_name, 
        e.designation, e.department, e.status as emp_status,
        a.check_in_time, a.check_out_time, a.status as attendance_status,
        a.remarks, a.work_from_home, a.leave_type
        FROM employees e
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = '$selected_date'
        WHERE e.status = 'Active'
        ORDER BY e.employee_code";

$result = mysqli_query($conn, $sql);
$employees = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
}

// Fetch active leave types from database
$leave_types_sql = "SELECT leave_type_name FROM leave_types WHERE status = 'Active' ORDER BY leave_type_name";
$leave_types_result = mysqli_query($conn, $leave_types_sql);
$leave_types = [];
if ($leave_types_result) {
    while ($row = mysqli_fetch_assoc($leave_types_result)) {
        $leave_types[] = $row['leave_type_name'];
    }
}

// Fallback to default leave types if table doesn't exist or is empty
if (empty($leave_types)) {
    $leave_types = ['Sick Leave', 'Casual Leave', 'Earned Leave', 'Maternity Leave', 'Paternity Leave', 'Unpaid Leave'];
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
  closeConnection($conn);
}
?>

  <div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="mark-att-header-flex">
        <div>
          <h1>📋 Mark Attendance</h1>
          <p>Record daily attendance for employees</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">View All Attendance</a>
        </div>
      </div>
    </div>    <?php if ($message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:25px;">
      <form method="GET" class="mark-att-date-filter">
        <div class="form-group" style="margin-bottom:0;">
          <label style="font-weight:600;color:#003581;">📅 Attendance Date</label>
          <input type="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" class="form-control" required>
        </div>
        <button type="submit" class="btn" style="padding:10px 24px;white-space:nowrap;">Load Attendance</button>
      </form>
    </div>

    <form method="POST">
      <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
      
      <div class="card">
        <div class="mark-att-title-flex">
          <h3 style="margin:0;color:#003581;">
            📋 Attendance for <?php echo date('d M Y', strtotime($selected_date)); ?> 
            <span style="font-weight:normal;color:#6c757d;font-size:14px;">(<?php echo date('l', strtotime($selected_date)); ?>)</span>
          </h3>
          <div class="mark-att-title-btns">
            <button type="button" class="btn btn-accent" onclick="markAllPresent()" style="padding:10px 20px;">
              ✓ Mark All Present
            </button>
            <button type="submit" class="btn" style="padding:10px 24px;">
              💾 Save Attendance
            </button>
          </div>
        </div>

        <?php if (count($employees) === 0): ?>
          <div class="alert alert-warning">No active employees found.</div>
        <?php else: ?>
          <div class="mark-att-table-responsive">
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Employee Code</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Name</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Department</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Designation</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;min-width:130px;">Status</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;min-width:120px;">Check-In</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;min-width:120px;">Check-Out</th>
                  <th style="padding:12px;text-align:center;font-weight:600;color:#003581;width:60px;">WFH</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;min-width:140px;">Leave Type</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;min-width:150px;">Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($employees as $emp): ?>
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px;font-weight:600;color:#003581;"><?php echo htmlspecialchars($emp['employee_code']); ?></td>
                    <td style="padding:12px;">
                      <?php echo htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']); ?>
                    </td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($emp['department'] ?? '—'); ?></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($emp['designation'] ?? '—'); ?></td>
                    <td style="padding:12px;">
                      <select name="attendance_data[<?php echo $emp['id']; ?>][status]" class="form-control status-select" data-employee="<?php echo $emp['id']; ?>" required>
                        <option value="Present" <?php echo ($emp['attendance_status'] === 'Present') ? 'selected' : ''; ?>>Present</option>
                        <option value="Absent" <?php echo ($emp['attendance_status'] === 'Absent' || !$emp['attendance_status']) ? 'selected' : ''; ?>>Absent</option>
                        <option value="Half Day" <?php echo ($emp['attendance_status'] === 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                        <option value="Leave" <?php echo ($emp['attendance_status'] === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                        <option value="Holiday" <?php echo ($emp['attendance_status'] === 'Holiday') ? 'selected' : ''; ?>>Holiday</option>
                        <option value="Week Off" <?php echo ($emp['attendance_status'] === 'Week Off') ? 'selected' : ''; ?>>Week Off</option>
                      </select>
                    </td>
                    <td style="padding:12px;">
                      <input type="time" name="attendance_data[<?php echo $emp['id']; ?>][check_in]" 
                             class="form-control time-input" data-employee="<?php echo $emp['id']; ?>"
                             value="<?php echo htmlspecialchars($emp['check_in_time'] ?? ''); ?>">
                    </td>
                    <td style="padding:12px;">
                      <input type="time" name="attendance_data[<?php echo $emp['id']; ?>][check_out]" 
                             class="form-control time-input" data-employee="<?php echo $emp['id']; ?>"
                             value="<?php echo htmlspecialchars($emp['check_out_time'] ?? ''); ?>">
                    </td>
                    <td style="padding:12px;text-align:center;">
                      <input type="checkbox" name="attendance_data[<?php echo $emp['id']; ?>][work_from_home]" 
                             value="1" <?php echo ($emp['work_from_home']) ? 'checked' : ''; ?> 
                             style="width:18px;height:18px;cursor:pointer;">
                    </td>
                    <td style="padding:12px;">
                      <select name="attendance_data[<?php echo $emp['id']; ?>][leave_type]" 
                              class="form-control leave-type" data-employee="<?php echo $emp['id']; ?>">
                        <option value="">—</option>
                        <?php foreach ($leave_types as $leave_type): ?>
                          <option value="<?php echo htmlspecialchars($leave_type); ?>" 
                                  <?php echo ($emp['leave_type'] === $leave_type) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($leave_type); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td style="padding:12px;">
                      <input type="text" name="attendance_data[<?php echo $emp['id']; ?>][remarks]" 
                             class="form-control" placeholder="Add remarks" 
                             value="<?php echo htmlspecialchars($emp['remarks'] ?? ''); ?>">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
function markAllPresent() {
  const statusSelects = document.querySelectorAll('.status-select');
  const timeInputs = document.querySelectorAll('.time-input');
  
  statusSelects.forEach(select => {
    select.value = 'Present';
  });
  
  // Set default check-in and check-out times
  const now = new Date();
  const checkInTime = '09:00';
  const checkOutTime = now.getHours() >= 18 ? '18:00' : now.toTimeString().slice(0, 5);
  
  timeInputs.forEach(input => {
    if (input.name.includes('[check_in]')) {
      input.value = checkInTime;
    } else if (input.name.includes('[check_out]')) {
      input.value = checkOutTime;
    }
  });
}

// Toggle time inputs and leave type based on status
document.addEventListener('DOMContentLoaded', function() {
  const statusSelects = document.querySelectorAll('.status-select');
  
  statusSelects.forEach(select => {
    select.addEventListener('change', function() {
      const employeeId = this.dataset.employee;
      const timeInputs = document.querySelectorAll(`[data-employee="${employeeId}"].time-input`);
      const leaveTypeSelect = document.querySelector(`[data-employee="${employeeId}"].leave-type`);
      const status = this.value;
      
      // Enable/disable time inputs based on status
      if (status === 'Present' || status === 'Half Day') {
        timeInputs.forEach(input => input.disabled = false);
        if (leaveTypeSelect) leaveTypeSelect.disabled = true;
      } else if (status === 'Leave') {
        timeInputs.forEach(input => input.disabled = true);
        if (leaveTypeSelect) leaveTypeSelect.disabled = false;
      } else {
        timeInputs.forEach(input => input.disabled = true);
        if (leaveTypeSelect) leaveTypeSelect.disabled = true;
      }
    });
    
    // Trigger on load
    select.dispatchEvent(new Event('change'));
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
