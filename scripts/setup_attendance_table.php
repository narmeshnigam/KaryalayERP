<?php
/**
 * Attendance Module Database Setup
 * Creates attendance and related tables with comprehensive fields
 */

require_once __DIR__ . '/../config/db_connect.php';

function setupAttendanceModule() {
    $conn = createConnection(true);
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Check if attendance table already exists
        $check_query = "SHOW TABLES LIKE 'attendance'";
        $result = mysqli_query($conn, $check_query);
        
        $already_exists = mysqli_num_rows($result) > 0;
        
        // Check if employees table exists (prerequisite)
        $check_employees = "SHOW TABLES LIKE 'employees'";
        $emp_result = mysqli_query($conn, $check_employees);
        
        if (mysqli_num_rows($emp_result) == 0) {
            closeConnection($conn);
            return ['success' => false, 'message' => 'Employees table does not exist! Please setup Employee module first.'];
        }
        
        // Create attendance table
        $create_attendance = "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            check_in_time TIME DEFAULT NULL,
            check_out_time TIME DEFAULT NULL,
            checkin_latitude DECIMAL(10,8) DEFAULT NULL,
            checkin_longitude DECIMAL(11,8) DEFAULT NULL,
            checkout_latitude DECIMAL(10,8) DEFAULT NULL,
            checkout_longitude DECIMAL(11,8) DEFAULT NULL,
            status ENUM('Present', 'Absent', 'Half Day', 'Leave', 'Holiday', 'Week Off') NOT NULL DEFAULT 'Absent',
            leave_type VARCHAR(50) DEFAULT NULL,
            total_hours DECIMAL(5,2) DEFAULT NULL,
            late_by_minutes INT DEFAULT 0,
            early_leave_minutes INT DEFAULT 0,
            overtime_minutes INT DEFAULT 0,
            work_from_home TINYINT(1) DEFAULT 0,
            remarks TEXT DEFAULT NULL,
            marked_by INT DEFAULT NULL,
            approved_by INT DEFAULT NULL,
            approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            approval_remarks TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_date (employee_id, attendance_date),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            INDEX idx_attendance_date (attendance_date),
            INDEX idx_status (status),
            INDEX idx_employee_date (employee_id, attendance_date),
            INDEX idx_approval_status (approval_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!mysqli_query($conn, $create_attendance)) {
            closeConnection($conn);
            return ['success' => false, 'message' => 'Error creating attendance table: ' . mysqli_error($conn)];
        }
        
        // Create leave_types table
        $create_leave_types = "CREATE TABLE IF NOT EXISTS leave_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            leave_type_name VARCHAR(100) UNIQUE NOT NULL,
            leave_type_code VARCHAR(20) UNIQUE NOT NULL,
            description TEXT DEFAULT NULL,
            is_paid TINYINT(1) DEFAULT 1,
            max_days_per_year INT DEFAULT NULL,
            requires_approval TINYINT(1) DEFAULT 1,
            color_code VARCHAR(7) DEFAULT '#007bff',
            status ENUM('Active', 'Inactive') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        mysqli_query($conn, $create_leave_types);
        
        // Create holidays table
        $create_holidays = "CREATE TABLE IF NOT EXISTS holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_name VARCHAR(150) NOT NULL,
            holiday_date DATE NOT NULL,
            holiday_type ENUM('Public Holiday', 'Restricted Holiday', 'Optional Holiday') DEFAULT 'Public Holiday',
            description TEXT DEFAULT NULL,
            applicable_to ENUM('All', 'Specific Departments', 'Specific Locations') DEFAULT 'All',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_holiday_date (holiday_date),
            INDEX idx_holiday_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        mysqli_query($conn, $create_holidays);
        
        // Insert sample leave types if table was just created
        if (!$already_exists) {
            $sample_leave_types = [
                ['Sick Leave', 'SL', 'Medical leave for illness', 1, 12],
                ['Casual Leave', 'CL', 'Short notice personal leave', 1, 12],
                ['Earned Leave', 'EL', 'Annual vacation leave', 1, 18],
                ['Unpaid Leave', 'UL', 'Leave without pay', 0, null]
            ];
            
            foreach ($sample_leave_types as $leave) {
                $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO leave_types (leave_type_name, leave_type_code, description, is_paid, max_days_per_year) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssii', $leave[0], $leave[1], $leave[2], $leave[3], $leave[4]);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        closeConnection($conn);
        
        if ($already_exists) {
            return ['success' => true, 'message' => 'Attendance table already exists.'];
        }
        
        return [
            'success' => true, 
            'message' => 'Attendance module database setup completed successfully!'
        ];
        
    } catch (Exception $e) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}

// Only run HTML output if called directly
if (!defined('AJAX_MODULE_INSTALL') && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/module_dependencies.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }

    $page_title = "Attendance Module - Database Setup";
    require_once __DIR__ . '/../includes/header_sidebar.php';
    require_once __DIR__ . '/../includes/sidebar.php';

    $conn_check = createConnection(true);
    $prerequisite_check = $conn_check ? get_prerequisite_check_result($conn_check, 'attendance') : ['allowed' => false, 'missing_modules' => []];
    if ($conn_check) closeConnection($conn_check);

    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = setupAttendanceModule();
    }
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📅 Attendance Module Setup</h1>
                    <p>Create database tables for Attendance Management</p>
                </div>
                <div>
                    <a href="../public/index.php" class="btn btn-accent">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <?php if ($result): ?>
                <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
                
                <?php if ($result['success']): ?>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="../public/attendance/index.php" class="btn" style="padding: 15px 40px; font-size: 16px;">
                            Go to Attendance Module
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (!$prerequisite_check['allowed']): ?>
                    <div class="alert alert-error" style="margin-bottom:20px;">
                        <strong>⚠️ Prerequisites Not Met</strong><br>
                        Please install required modules first.
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <strong>ℹ️ Setup Information</strong><br>
                    This will create the following database tables:<br><br>
                    <strong>1. attendance</strong> - Main attendance records<br>
                    <strong>2. leave_types</strong> - Leave type master<br>
                    <strong>3. holidays</strong> - Public holidays calendar<br>
                </div>

                <form method="POST" style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn" style="padding: 15px 40px; font-size: 16px;" <?php echo !$prerequisite_check['allowed'] ? 'disabled' : ''; ?>>
                        🚀 Create Attendance Module Tables
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
    require_once __DIR__ . '/../includes/footer_sidebar.php';
} // End of direct execution block
