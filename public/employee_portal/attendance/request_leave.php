<?php
/**
 * Employee Portal - Request Leave
 * Submit leave requests for approval
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = "Request Leave - " . APP_NAME;
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
$message = '';
$error = '';

// Get active leave types
$leave_types_query = "SELECT leave_type_name, max_days_per_year FROM leave_types WHERE status = 'Active' ORDER BY leave_type_name";
$leave_types_result = mysqli_query($conn, $leave_types_query);
$leave_types = [];
if ($leave_types_result) {
    while ($row = mysqli_fetch_assoc($leave_types_result)) {
        $leave_types[] = $row;
    }
}

// Fallback if no leave types in DB
if (empty($leave_types)) {
    $leave_types = [
        ['leave_type_name' => 'Sick Leave', 'max_days_per_year' => 12],
        ['leave_type_name' => 'Casual Leave', 'max_days_per_year' => 12],
        ['leave_type_name' => 'Earned Leave', 'max_days_per_year' => 18]
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $from_date = mysqli_real_escape_string($conn, $_POST['from_date']);
    $to_date = mysqli_real_escape_string($conn, $_POST['to_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    // Calculate number of days
    $start = new DateTime($from_date);
    $end = new DateTime($to_date);
    $end->modify('+1 day'); // Include end date
    $interval = $start->diff($end);
    $days = $interval->days;
    
    if ($days < 1) {
        $error = "Invalid date range selected.";
    } else {
        // Insert leave attendance records for each day
        $success_count = 0;
        $date_iterator = clone $start;
        
        while ($date_iterator < $end) {
            $current_date = $date_iterator->format('Y-m-d');
            
            // Check if attendance already exists for this date
            $check_query = "SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'is', $employee_id, $current_date);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) == 0) {
                // Insert leave record
                $insert_query = "INSERT INTO attendance 
                                (employee_id, attendance_date, status, leave_type, remarks, marked_by, approval_status) 
                                VALUES (?, ?, 'Leave', ?, ?, ?, 'Pending')";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, 'isssi', $employee_id, $current_date, $leave_type, $reason, $user_id);
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    $success_count++;
                }
                mysqli_stmt_close($insert_stmt);
            }
            mysqli_stmt_close($check_stmt);
            
            $date_iterator->modify('+1 day');
        }
        
        if ($success_count > 0) {
            $message = "Leave request submitted successfully for $success_count day(s)! Pending approval.";
        } else {
            $error = "Could not submit leave request. Attendance may already exist for selected dates.";
        }
    }
}

// Get pending leave requests
$pending_query = "SELECT * FROM attendance 
                 WHERE employee_id = ? AND status = 'Leave' AND approval_status = 'Pending'
                 ORDER BY attendance_date DESC LIMIT 10";
$pending_stmt = mysqli_prepare($conn, $pending_query);
mysqli_stmt_bind_param($pending_stmt, 'i', $employee_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
$pending_leaves = [];
while ($row = mysqli_fetch_assoc($pending_result)) {
    $pending_leaves[] = $row;
}
mysqli_stmt_close($pending_stmt);

closeConnection($conn);
?>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>📝 Request Leave</h1>
          <p>Submit leave requests for manager approval</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to Dashboard</a>
        </div>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:25px;">
      <!-- Leave Request Form -->
      <div class="card">
        <h3 style="margin:0 0 20px 0;color:#003581;">📋 New Leave Request</h3>
        
        <form method="POST">
          <div class="form-group">
            <label class="form-label required">Leave Type</label>
            <select name="leave_type" class="form-control" required>
              <option value="">Select leave type...</option>
              <?php foreach ($leave_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type['leave_type_name']); ?>">
                  <?php echo htmlspecialchars($type['leave_type_name']); ?>
                  <?php if ($type['max_days_per_year']): ?>
                    (Max: <?php echo $type['max_days_per_year']; ?> days/year)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div class="form-group">
              <label class="form-label required">From Date</label>
              <input type="date" name="from_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
              <label class="form-label required">To Date</label>
              <input type="date" name="to_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label required">Reason</label>
            <textarea name="reason" class="form-control" rows="4" placeholder="Please provide reason for leave..." required></textarea>
          </div>

          <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" name="submit_leave" class="btn" style="flex:1;">Submit Leave Request</button>
            <button type="reset" class="btn btn-accent" style="flex:1;">Clear Form</button>
          </div>
        </form>
      </div>

      <!-- Pending Requests -->
      <div class="card">
        <h3 style="margin:0 0 20px 0;color:#003581;">⏳ Pending Leave Requests</h3>
        
        <?php if (count($pending_leaves) > 0): ?>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($pending_leaves as $leave): ?>
              <div style="padding:15px;background:#f8f9fa;border-radius:8px;border-left:4px solid #ffc107;">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                  <div>
                    <div style="font-weight:600;color:#003581;">
                      <?php echo htmlspecialchars($leave['leave_type']); ?>
                    </div>
                    <div style="font-size:13px;color:#6c757d;margin-top:4px;">
                      📅 <?php echo date('d M Y', strtotime($leave['attendance_date'])); ?>
                    </div>
                  </div>
                  <span style="padding:4px 10px;background:#fff3cd;color:#856404;border-radius:12px;font-size:12px;font-weight:600;">
                    Pending
                  </span>
                </div>
                <div style="font-size:13px;color:#495057;margin-top:8px;">
                  <?php echo htmlspecialchars($leave['remarks'] ?? 'No reason provided'); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info" style="margin:0;">No pending leave requests.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Leave Information -->
    <div class="card" style="margin-top:25px;">
      <h3 style="margin:0 0 20px 0;color:#003581;">ℹ️ Leave Policy Information</h3>
      
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
        <?php foreach ($leave_types as $type): ?>
          <div style="padding:20px;background:#f8f9fa;border-radius:8px;border-top:3px solid #003581;">
            <div style="font-weight:600;color:#003581;font-size:16px;margin-bottom:8px;">
              <?php echo htmlspecialchars($type['leave_type_name']); ?>
            </div>
            <?php if ($type['max_days_per_year']): ?>
              <div style="font-size:14px;color:#6c757d;">
                Maximum: <strong><?php echo $type['max_days_per_year']; ?> days</strong> per year
              </div>
            <?php else: ?>
              <div style="font-size:14px;color:#6c757d;">
                No annual limit
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:20px;padding:15px;background:#e7f3ff;border-radius:8px;font-size:14px;color:#004085;">
        <strong>📌 Important Notes:</strong>
        <ul style="margin:10px 0 0 20px;line-height:1.8;">
          <li>Leave requests require manager approval</li>
          <li>Submit leave requests at least 2 days in advance when possible</li>
          <li>Emergency leaves can be submitted and will be reviewed</li>
          <li>You can track the approval status in your attendance dashboard</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
