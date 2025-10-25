<?php
/**
 * Attendance Module Database Setup
 * Creates attendance and related tables with comprehensive fields
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

// Page title for header
$page_title = "Attendance Module - Database Setup";

// Include header with sidebar
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function setupAttendanceModule() {
    $conn = createConnection(true);
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Check if attendance table already exists
        $check_query = "SHOW TABLES LIKE 'attendance'";
        $result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($result) > 0) {
            return ['success' => false, 'message' => 'Attendance table already exists!'];
        }
        
        // Check if employees table exists (prerequisite)
        $check_employees = "SHOW TABLES LIKE 'employees'";
        $emp_result = mysqli_query($conn, $check_employees);
        
        if (mysqli_num_rows($emp_result) == 0) {
            return ['success' => false, 'message' => 'Employees table does not exist! Please setup Employee module first.'];
        }
        
        // Create attendance table
        $create_attendance = "CREATE TABLE attendance (
            -- Primary Key
            id INT AUTO_INCREMENT PRIMARY KEY,
            
            -- Employee Reference
            employee_id INT NOT NULL,
            
            -- Attendance Details
            attendance_date DATE NOT NULL,
            check_in_time TIME DEFAULT NULL,
            check_out_time TIME DEFAULT NULL,
            
            -- Geo-location Tracking
            checkin_latitude DECIMAL(10,8) DEFAULT NULL COMMENT 'Check-in location latitude',
            checkin_longitude DECIMAL(11,8) DEFAULT NULL COMMENT 'Check-in location longitude',
            checkout_latitude DECIMAL(10,8) DEFAULT NULL COMMENT 'Check-out location latitude',
            checkout_longitude DECIMAL(11,8) DEFAULT NULL COMMENT 'Check-out location longitude',
            
            -- Status & Type
            status ENUM('Present', 'Absent', 'Half Day', 'Leave', 'Holiday', 'Week Off') NOT NULL DEFAULT 'Absent',
            leave_type VARCHAR(50) DEFAULT NULL COMMENT 'Sick Leave, Casual Leave, etc.',
            
            -- Working Hours Calculation
            total_hours DECIMAL(5,2) DEFAULT NULL,
            late_by_minutes INT DEFAULT 0,
            early_leave_minutes INT DEFAULT 0,
            overtime_minutes INT DEFAULT 0,
            
            -- Additional Information
            work_from_home TINYINT(1) DEFAULT 0,
            remarks TEXT DEFAULT NULL,
            
            -- Approval Workflow
            marked_by INT DEFAULT NULL COMMENT 'User ID who marked attendance',
            approved_by INT DEFAULT NULL COMMENT 'User ID who approved',
            approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            approval_remarks TEXT DEFAULT NULL,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Constraints & Indexes
            UNIQUE KEY unique_employee_date (employee_id, attendance_date),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            INDEX idx_attendance_date (attendance_date),
            INDEX idx_status (status),
            INDEX idx_employee_date (employee_id, attendance_date),
            INDEX idx_approval_status (approval_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee Attendance Records'";
        
        if (!mysqli_query($conn, $create_attendance)) {
            closeConnection($conn);
            return ['success' => false, 'message' => 'Error creating attendance table: ' . mysqli_error($conn)];
        }
        
        // Create leave_types table for managing different leave types
        $create_leave_types = "CREATE TABLE IF NOT EXISTS leave_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            leave_type_name VARCHAR(100) UNIQUE NOT NULL,
            leave_type_code VARCHAR(20) UNIQUE NOT NULL,
            description TEXT DEFAULT NULL,
            is_paid TINYINT(1) DEFAULT 1,
            max_days_per_year INT DEFAULT NULL,
            requires_approval TINYINT(1) DEFAULT 1,
            color_code VARCHAR(7) DEFAULT '#007bff' COMMENT 'For UI display',
            status ENUM('Active', 'Inactive') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        mysqli_query($conn, $create_leave_types);
        
        // Create holidays table for managing public holidays
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
        
        // Insert sample leave types
        $sample_leave_types = [
            ['Sick Leave', 'SL', 'Medical leave for illness', 1, 12],
            ['Casual Leave', 'CL', 'Short notice personal leave', 1, 12],
            ['Earned Leave', 'EL', 'Annual vacation leave', 1, 18],
            ['Maternity Leave', 'ML', 'Maternity leave for female employees', 1, 180],
            ['Paternity Leave', 'PL', 'Paternity leave for male employees', 1, 15],
            ['Unpaid Leave', 'UL', 'Leave without pay', 0, null],
            ['Compensatory Off', 'CO', 'Compensation for overtime work', 1, null]
        ];
        
        foreach ($sample_leave_types as $leave) {
            $stmt = mysqli_prepare($conn, "INSERT INTO leave_types (leave_type_name, leave_type_code, description, is_paid, max_days_per_year) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssii', $leave[0], $leave[1], $leave[2], $leave[3], $leave[4]);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Insert sample holidays for current year
        $current_year = date('Y');
        $sample_holidays = [
            ['New Year', $current_year . '-01-01', 'Public Holiday'],
            ['Republic Day', $current_year . '-01-26', 'Public Holiday'],
            ['Holi', $current_year . '-03-14', 'Public Holiday'],
            ['Good Friday', $current_year . '-03-29', 'Restricted Holiday'],
            ['Independence Day', $current_year . '-08-15', 'Public Holiday'],
            ['Gandhi Jayanti', $current_year . '-10-02', 'Public Holiday'],
            ['Diwali', $current_year . '-10-24', 'Public Holiday'],
            ['Christmas', $current_year . '-12-25', 'Public Holiday']
        ];
        
        foreach ($sample_holidays as $holiday) {
            $stmt = mysqli_prepare($conn, "INSERT INTO holidays (holiday_name, holiday_date, holiday_type) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sss', $holiday[0], $holiday[1], $holiday[2]);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        closeConnection($conn);
        return [
            'success' => true, 
            'message' => 'Attendance module database setup completed successfully! Created tables: attendance, leave_types, holidays with sample data.'
        ];
        
    } catch (Exception $e) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}

// Run setup if form submitted
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = setupAttendanceModule();
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📅 Attendance Module Setup</h1>
                    <p>Create database tables for Attendance Management</p>
                </div>
                <div>
                    <a href="../public/index.php" class="btn btn-accent">
                        ← Back to Dashboard
                    </a>
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
        <?php else: ?>
            <div style="text-align: center; margin-top: 30px;">
                <a href="../public/index.php" class="btn btn-accent">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <strong>ℹ️ Setup Information</strong><br>
            This will create the following database tables:<br><br>
            <strong>1. attendance</strong> - Main attendance records with check-in/out, status, hours tracking<br>
            <strong>2. leave_types</strong> - Leave type master (Sick, Casual, Earned, etc.)<br>
            <strong>3. holidays</strong> - Public holidays calendar with sample data<br><br>
            <strong>⚠️ Prerequisite:</strong> Employee module must be setup first.<br><br>
            Click below to create these tables.
        </div>

        <form method="POST" style="text-align: center; margin-top: 30px;">
            <button type="submit" class="btn" style="padding: 15px 40px; font-size: 16px;">
                🚀 Create Attendance Module Tables
            </button>
        </form>
    <?php endif; ?>
    
    <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #6c757d;">
        <strong>📋 Tables to be created:</strong><br>
        • <strong>attendance:</strong> Date, Check-in/out, Geo-coordinates, Status, Hours, Late/Early/Overtime tracking, WFH, Approval workflow<br>
        • <strong>leave_types:</strong> Sick Leave, Casual Leave, Earned Leave, Maternity/Paternity Leave, etc.<br>
        • <strong>holidays:</strong> New Year, Republic Day, Independence Day, Diwali, Christmas, etc.<br><br>
        
        <strong>🎯 Key Features:</strong><br>
        • Comprehensive attendance tracking with check-in/check-out times<br>
        • GPS location tracking for check-in and check-out (latitude/longitude)<br>
        • Automatic calculation of working hours, late arrivals, early departures<br>
        • Multiple attendance statuses: Present, Absent, Half Day, Leave, Holiday, Week Off<br>
        • Work from home tracking<br>
        • Leave type management with paid/unpaid classification<br>
        • Public holiday calendar<br>
        • Approval workflow for attendance records<br>
        • Unique constraint to prevent duplicate attendance for same date<br>
        • Foreign key relationship with employees table
    </div>
</div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
