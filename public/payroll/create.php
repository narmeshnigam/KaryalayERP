<?php
/**
 * Payroll Module - Generate New Payroll
 * Create monthly payroll batch with auto-computation
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payroll_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = trim($_POST['month'] ?? '');
    
    // Validation
    if (empty($month)) {
        $errors[] = 'Please select a month';
    } elseif (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $errors[] = 'Invalid month format';
    } elseif (payroll_exists_for_month($conn, $month)) {
        $errors[] = 'Payroll already exists for this month. Please edit the existing payroll instead.';
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Get all active employees
            $employees = get_active_employees_for_payroll($conn);
            
            if (empty($employees)) {
                throw new Exception('No active employees found with salary configured');
            }
            
            // Create payroll master
            $stmt = $conn->prepare("INSERT INTO payroll_master (month, total_employees, total_amount, created_by) VALUES (?, 0, 0, ?)");
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param("si", $month, $user_id);
            $stmt->execute();
            $payroll_id = $conn->insert_id;
            
            $total_amount = 0;
            $total_employees = 0;
            
            // Process each employee
            foreach ($employees as $employee) {
                $emp_id = $employee['id'];
                $base_salary = $employee['basic_salary'];
                
                // Get attendance
                $attendance = get_attendance_days($conn, $emp_id, $month);
                $present_days = $attendance['present_days'];
                $total_days = $attendance['total_days'];
                
                // Calculate attendance-adjusted base
                $adjusted_base = calculate_attendance_based_salary($base_salary, $present_days, $total_days);
                
                // Get allowances and deductions
                $allowances = calculate_allowances($conn, $adjusted_base);
                $deductions = calculate_deductions($conn, $adjusted_base);
                
                // Get reimbursements
                $reimbursements = get_approved_reimbursements($conn, $emp_id, $month);
                
                // Calculate net pay
                $net_pay = calculate_net_pay($adjusted_base, $allowances, $reimbursements, $deductions);
                
                // Insert payroll record
                $stmt2 = $conn->prepare("INSERT INTO payroll_records 
                    (payroll_id, employee_id, base_salary, attendance_days, total_days, allowances, reimbursements, deductions, net_pay) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("iidiidddd", $payroll_id, $emp_id, $adjusted_base, $present_days, $total_days, $allowances, $reimbursements, $deductions, $net_pay);
                $stmt2->execute();
                
                $total_amount += $net_pay;
                $total_employees++;
            }
            
            // Update payroll master with totals
            $stmt3 = $conn->prepare("UPDATE payroll_master SET total_employees = ?, total_amount = ? WHERE id = ?");
            $stmt3->bind_param("idi", $total_employees, $total_amount, $payroll_id);
            $stmt3->execute();
            
            // Log activity
            log_payroll_activity($conn, $payroll_id, $user_id, 'Generate', "Generated payroll for $total_employees employees");
            
            $conn->commit();
            
            $_SESSION['flash_success'] = "Payroll generated successfully for " . format_month_display($month) . " with $total_employees employees";
            header('Location: review.php?id=' . $payroll_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Failed to generate payroll: ' . $e->getMessage();
        }
    }
}

// Default month: current month
$default_month = date('Y-m');

$page_title = 'Generate Payroll - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <h1>➕ Generate New Payroll</h1>
            <p>Automatically compute salaries with attendance and reimbursements</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-section">
                    <h2>Payroll Month</h2>
                    <div class="form-group">
                        <label for="month">Select Month <span class="required">*</span></label>
                        <input type="month" id="month" name="month" class="form-control" value="<?php echo htmlspecialchars($default_month); ?>" required>
                        <small class="form-text">The system will fetch attendance and reimbursements for this month</small>
                    </div>
                </div>

                <div class="info-section">
                    <h3>📊 What will be processed:</h3>
                    <ul class="info-list">
                        <li>✅ All active employees with configured base salary</li>
                        <li>✅ Attendance records for the selected month</li>
                        <li>✅ Approved reimbursements for the month</li>
                        <li>✅ Automatic calculation of allowances and deductions</li>
                        <li>✅ Final net pay computation (attendance-adjusted)</li>
                    </ul>
                </div>

                <div class="warning-section">
                    <h3>⚠️ Important Notes:</h3>
                    <ul class="warning-list">
                        <li>Only one payroll batch per month is allowed</li>
                        <li>Payroll will be created in <strong>Draft</strong> status</li>
                        <li>You can review and edit before locking</li>
                        <li>Attendance-based salary calculation will be applied</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">🔄 Generate Payroll</button>
                    <a href="index.php" class="btn btn-secondary btn-lg">← Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-section {
    background: white;
    padding: 28px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.form-section h2 {
    color: #003581;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e0e0e0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.required {
    color: #dc3545;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 15px;
}

.form-text {
    display: block;
    margin-top: 6px;
    color: #666;
    font-size: 13px;
}

.info-section, .warning-section {
    background: white;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info-section {
    border-left: 4px solid #28a745;
}

.warning-section {
    border-left: 4px solid #ffc107;
}

.info-section h3, .warning-section h3 {
    color: #003581;
    margin-bottom: 16px;
}

.info-list, .warning-list {
    list-style: none;
    padding: 0;
}

.info-list li, .warning-list li {
    padding: 8px 0;
    font-size: 15px;
    color: #444;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
