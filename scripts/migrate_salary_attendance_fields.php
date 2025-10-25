<?php
/**
 * Salary Module - Add Attendance Integration Fields
 * Migration to add working days, leaves, and deduction tracking
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = 'Salary Module - Attendance Integration Migration';
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function migrate_salary_attendance_fields(): array
{
    $conn = createConnection(true);
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    // Check if salary_records table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'salary_records'");
    if (!$check_table || mysqli_num_rows($check_table) === 0) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'salary_records table not found. Please run the salary module setup first.'];
    }

    // Check if migration already ran
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM salary_records LIKE 'working_days'");
    if ($check_col && mysqli_num_rows($check_col) > 0) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Migration already completed. New fields already exist.'];
    }

    $migrations = [];
    
    // Add working_days column
    $migrations[] = "ALTER TABLE salary_records ADD COLUMN working_days INT DEFAULT NULL COMMENT 'Total working days in the month' AFTER net_pay";
    
    // Add days_worked column
    $migrations[] = "ALTER TABLE salary_records ADD COLUMN days_worked DECIMAL(5,2) DEFAULT NULL COMMENT 'Actual days worked (including half days as 0.5)' AFTER working_days";
    
    // Add leaves_taken column
    $migrations[] = "ALTER TABLE salary_records ADD COLUMN leaves_taken DECIMAL(5,2) DEFAULT NULL COMMENT 'Total leaves taken' AFTER days_worked";
    
    // Add leave_details column (JSON)
    $migrations[] = "ALTER TABLE salary_records ADD COLUMN leave_details TEXT DEFAULT NULL COMMENT 'JSON array of leave breakdown by type' AFTER leaves_taken";
    
    // Add deduction_details column (JSON)
    $migrations[] = "ALTER TABLE salary_records ADD COLUMN deduction_details TEXT DEFAULT NULL COMMENT 'JSON array of itemized deductions' AFTER leave_details";
    
    // Add allowance_details column (JSON)
    $migrations[] = "ALTER TABLE salary_records ADD COLUMN allowance_details TEXT DEFAULT NULL COMMENT 'JSON array of itemized allowances' AFTER deduction_details";
    
    $success_count = 0;
    $errors = [];

    foreach ($migrations as $sql) {
        if (mysqli_query($conn, $sql)) {
            $success_count++;
        } else {
            $errors[] = mysqli_error($conn);
        }
    }

    closeConnection($conn);

    if ($success_count === count($migrations)) {
        return [
            'success' => true,
            'message' => 'Migration completed successfully! Added ' . $success_count . ' new columns to salary_records table
