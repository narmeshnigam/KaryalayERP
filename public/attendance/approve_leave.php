<?php
/**
 * Admin - Approve Leave Requests
 * View and approve/reject employee leave requests
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$page_title = "Approve Leave Requests - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<style>
.leave-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.leave-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}
.leave-filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.leave-filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.leave-action-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}
@media (max-width: 1200px) {
    .leave-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .leave-filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 600px) {
    .leave-header-flex {
        flex-direction: column;
        align-items: stretch;
    }
    .leave-header-flex > div {
        width: 100%;
    }
    .leave-header-flex .btn {
        width: 100%;
        text-align: center;
        display: block;
    }
    .leave-stats-grid {
        grid-template-columns: 1fr;
    }
    .leave-filter-grid {
        grid-template-columns: 1fr;
    }
    .leave-filter-buttons {
        flex-direction: column;
        width: 100%;
    }
    .leave-filter-buttons .btn {
        width: 100%;
    }
    .leave-action-buttons {
        flex-direction: column;
        width: 100%;
    }
    .leave-action-buttons button,
    .leave-action-buttons select {
        width: 100%;
    }
}
</style>

<?php

$message = '';
$error = '';

// Handle approve/reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $attendance_ids = isset($_POST['attendance_ids']) ? $_POST['attendance_ids'] : [];
  $action = $_POST['action'];
  $remarks_input = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
  $remarks = mysqli_real_escape_string($conn, $remarks_input);
    
  $allowed_actions = ['approve', 'reject', 'pending'];
  if (!in_array($action, $allowed_actions, true)) {
    $error = "Invalid action selected.";
  } else {
    if (!empty($attendance_ids)) {
  $user_id = $CURRENT_USER_ID;
            
      switch ($action) {
        case 'approve':
          $new_status = 'Approved';
          break;
        case 'reject':
          $new_status = 'Rejected';
          break;
        case 'pending':
          $new_status = 'Pending';
          break;
      }
            
      $success_count = 0;
      foreach ($attendance_ids as $id) {
        $id = intval($id);
                
        if ($action === 'pending') {
          $update_query = "UPDATE attendance 
                   SET approval_status = ?, approved_by = NULL, approval_remarks = ?
                   WHERE id = ?";
          $update_stmt = mysqli_prepare($conn, $update_query);
          mysqli_stmt_bind_param($update_stmt, 'ssi', $new_status, $remarks, $id);
        } else {
          $update_query = "UPDATE attendance 
                   SET approval_status = ?, approved_by = ?, approval_remarks = ?
                   WHERE id = ?";
          $update_stmt = mysqli_prepare($conn, $update_query);
          mysqli_stmt_bind_param($update_stmt, 'sisi', $new_status, $user_id, $remarks, $id);
        }
                
        if (mysqli_stmt_execute($update_stmt) && mysqli_stmt_affected_rows($update_stmt) > 0) {
          $success_count++;
        }
        mysqli_stmt_close($update_stmt);
      }
            
      if ($success_count > 0) {
        $status_messages = [
          'approve' => 'approved',
          'reject' => 'rejected',
          'pending' => 'marked as pending'
        ];
        $message = "$success_count leave request(s) " . $status_messages[$action] . " successfully!";
      } else {
        $error = "No leave requests were updated.";
      }
    } else {
      $error = "Please select at least one leave request.";
    }
  }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$employee_filter = isset($_GET['employee']) ? intval($_GET['employee']) : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');

// Build query with filters
$where_conditions = ["a.status = 'Leave'"];
$params = [];
$types = '';

if ($status_filter !== 'All') {
    $where_conditions[] = "a.approval_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($employee_filter > 0) {
    $where_conditions[] = "a.employee_id = ?";
    $params[] = $employee_filter;
    $types .= 'i';
}

$where_conditions[] = "a.attendance_date BETWEEN ? AND ?";
$params[] = $from_date;
$params[] = $to_date;
$types .= 'ss';

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT a.*, 
          e.first_name, e.last_name, e.employee_code, e.department,
          u.username as marked_by_name,
          ap.username as approved_by_name
          FROM attendance a
          LEFT JOIN employees e ON a.employee_id = e.id
          LEFT JOIN users u ON a.marked_by = u.id
          LEFT JOIN users ap ON a.approved_by = ap.id
          WHERE $where_clause
          ORDER BY a.attendance_date DESC, a.approval_status, e.first_name";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$leave_requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $leave_requests[] = $row;
}
mysqli_stmt_close($stmt);

// Get employee list for filter
$emp_query = "SELECT id, employee_code, first_name, last_name FROM employees ORDER BY first_name, last_name";
$emp_result = mysqli_query($conn, $emp_query);
$employees = [];
while ($row = mysqli_fetch_assoc($emp_result)) {
    $employees[] = $row;
}

// Calculate statistics
$stats_query = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN approval_status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN approval_status = 'Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN approval_status = 'Rejected' THEN 1 ELSE 0 END) as rejected
                FROM attendance 
                WHERE status = 'Leave' AND attendance_date BETWEEN ? AND ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, 'ss', $from_date, $to_date);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));
mysqli_stmt_close($stats_stmt);

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
  closeConnection($conn);
}
?>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="leave-header-flex">
        <div>
          <h1>✈️ Leave Requests</h1>
          <p>Review and approve employee leave applications</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to Attendance</a>
        </div>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="leave-stats-grid">
      <div style="padding:20px;background:linear-gradient(135deg,#003581 0%,#0056b3 100%);color:white;border-radius:8px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:5px;"><?php echo (int)($stats['total_requests'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.9;">Total Requests</div>
      </div>

      <div style="padding:20px;background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:white;border-radius:8px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:5px;"><?php echo (int)($stats['pending'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.9;">Pending Approval</div>
      </div>

      <div style="padding:20px;background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:white;border-radius:8px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:5px;"><?php echo (int)($stats['approved'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.9;">Approved</div>
      </div>

      <div style="padding:20px;background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);color:white;border-radius:8px;">
        <div style="font-size:32px;font-weight:700;margin-bottom:5px;"><?php echo (int)($stats['rejected'] ?? 0); ?></div>
        <div style="font-size:14px;opacity:0.9;">Rejected</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card">
      <h3 style="margin:0 0 20px 0;color:#003581;">🔍 Filter Leave Requests</h3>
      
      <form method="GET" id="filterForm">
        <div class="leave-filter-grid">
          <div class="form-group" style="margin:0;">
            <label class="form-label">Approval Status</label>
            <select name="status" class="form-control">
              <option value="All" <?php echo $status_filter === 'All' ? 'selected' : ''; ?>>All Statuses</option>
              <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
              <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
          </div>

          <div class="form-group" style="margin:0;">
            <label class="form-label">Employee</label>
            <select name="employee" class="form-control">
              <option value="0">All Employees</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?php echo $emp['id']; ?>" <?php echo $employee_filter == $emp['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="margin:0;">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
          </div>

          <div class="form-group" style="margin:0;">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
          </div>
        </div>

        <div class="leave-filter-buttons">
          <button type="submit" class="btn">Apply Filters</button>
          <a href="approve_leave.php" class="btn btn-accent">Reset Filters</a>
        </div>
      </form>
    </div>

    <!-- Leave Requests Table -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="margin:0;color:#003581;">📋 Leave Requests (<?php echo count($leave_requests); ?>)</h3>
      </div>

      <?php if (count($leave_requests) > 0): ?>
        <form method="POST" id="approvalForm">
          <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                  <th style="padding:12px;text-align:center;font-weight:600;color:#003581;width:50px;">
                    <input type="checkbox" id="selectAll" style="width:18px;height:18px;cursor:pointer;">
                  </th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Employee</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Department</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Date</th>
                  <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Leave Type</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Reason</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Requested By</th>
                  <th style="padding:12px;text-align:center;font-weight:600;color:#003581;">Status</th>
                  <th style="padding:12px;text-align:left;font-weight:600;color:#003581;">Approved By</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($leave_requests as $request): ?>
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px;text-align:center;">
                      <input type="checkbox" name="attendance_ids[]" value="<?php echo $request['id']; ?>" class="leave-checkbox" style="width:18px;height:18px;cursor:pointer;">
                    </td>
                    <td style="padding:12px;white-space:nowrap;">
                      <strong style="color:#003581;display:block;"><?php echo htmlspecialchars($request['employee_code']); ?></strong>
                      <span style="font-size:13px;color:#6c757d;display:block;">
                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                      </span>
                    </td>
                    <td style="padding:12px;">
                      <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
                    </td>
                    <td style="padding:12px;white-space:nowrap;">
                      <?php 
                        $date = new DateTime($request['attendance_date']);
                        echo $date->format('d M Y');
                      ?>
                      <div style="font-size:12px;color:#6c757d;">
                        <?php echo $date->format('l'); ?>
                      </div>
                    </td>
                    <td style="padding:12px;text-align:center;">
                      <span style="padding:4px 10px;background:#e7f3ff;color:#004085;border-radius:12px;font-size:13px;font-weight:600;display:inline-block;">
                        <?php echo htmlspecialchars($request['leave_type'] ?? 'General'); ?>
                      </span>
                    </td>
                    <td style="padding:12px;max-width:280px;color:#495057;">
                      <?php echo htmlspecialchars($request['remarks'] ?? 'No reason provided'); ?>
                    </td>
                    <td style="padding:12px;">
                      <?php echo htmlspecialchars($request['marked_by_name'] ?? 'Self'); ?>
                    </td>
                    <td style="padding:12px;text-align:center;">
                      <?php
                        $status_colors = [
                            'Pending' => 'background:#fff3cd;color:#856404;',
                            'Approved' => 'background:#d4edda;color:#155724;',
                            'Rejected' => 'background:#f8d7da;color:#721c24;'
                        ];
                        $status_style = $status_colors[$request['approval_status']] ?? '';
                      ?>
                      <span style="padding:4px 10px;<?php echo $status_style; ?>border-radius:12px;font-size:13px;font-weight:600;display:inline-block;min-width:90px;">
                        <?php echo $request['approval_status']; ?>
                      </span>
                      <?php if ($request['approval_remarks']): ?>
                        <div style="font-size:12px;color:#6c757d;margin-top:4px;" title="<?php echo htmlspecialchars($request['approval_remarks']); ?>">
                          📝 Has remarks
                        </div>
                      <?php endif; ?>
                    </td>
                    <td style="padding:12px;">
                      <?php if ($request['approved_by_name']): ?>
                        <?php echo htmlspecialchars($request['approved_by_name']); ?>
                      <?php else: ?>
                        <span style="color:#6c757d;">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Bulk Actions -->
          <div style="margin-top:20px;padding:20px;background:#f8f9fa;border-radius:8px;">
            <div class="leave-action-buttons">
              <div class="form-group" style="margin:0;flex:1;">
                <label class="form-label">Remarks (Optional)</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Add remarks for approval/rejection..."></textarea>
              </div>

              <button type="submit" name="action" value="approve" class="btn" style="background:#28a745;height:45px;">
                ✓ Approve Selected
              </button>

              <button type="submit" name="action" value="pending" class="btn" style="background:#17a2b8;height:45px;">
                ⏳ Mark as Pending
              </button>

              <button type="submit" name="action" value="reject" class="btn" style="background:#dc3545;height:45px;" 
                      onclick="return confirm('Are you sure you want to reject the selected leave requests?');">
                ✗ Reject Selected
              </button>
            </div>
          </div>
        </form>
      <?php else: ?>
        <div class="alert alert-info" style="margin:0;">
          No leave requests found matching the selected filters.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Select/deselect all checkboxes
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.leave-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Update select all checkbox based on individual checkboxes
document.querySelectorAll('.leave-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const allCheckboxes = document.querySelectorAll('.leave-checkbox');
        const selectAll = document.getElementById('selectAll');
        selectAll.checked = Array.from(allCheckboxes).every(c => c.checked);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
