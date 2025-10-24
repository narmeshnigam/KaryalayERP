<?php
/**
 * Employee Module Database Setup
 * Creates employees table with comprehensive fields
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Page title for header
$page_title = "Employee Module - Database Setup";

// Include header
require_once __DIR__ . '/../includes/header.php';

function setupEmployeesTable() {
    $conn = createConnection(true);
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Check if employees table already exists
        $check_query = "SHOW TABLES LIKE 'employees'";
        $result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($result) > 0) {
            return ['success' => false, 'message' => 'Employees table already exists!'];
        }
        
        // Create employees table with comprehensive fields
        $create_table = "CREATE TABLE employees (
            -- Primary Key
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_code VARCHAR(20) UNIQUE NOT NULL,
            
            -- Personal Information
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) NOT NULL,
            date_of_birth DATE NOT NULL,
            gender ENUM('Male', 'Female', 'Other') NOT NULL,
            blood_group VARCHAR(5) DEFAULT NULL,
            marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') DEFAULT 'Single',
            nationality VARCHAR(50) DEFAULT 'Indian',
            
            -- Contact Information
            personal_email VARCHAR(150) DEFAULT NULL,
            official_email VARCHAR(150) UNIQUE NOT NULL,
            mobile_number VARCHAR(15) NOT NULL,
            alternate_mobile VARCHAR(15) DEFAULT NULL,
            emergency_contact_name VARCHAR(100) DEFAULT NULL,
            emergency_contact_number VARCHAR(15) DEFAULT NULL,
            emergency_contact_relation VARCHAR(50) DEFAULT NULL,
            
            -- Address Information
            current_address TEXT DEFAULT NULL,
            current_city VARCHAR(100) DEFAULT NULL,
            current_state VARCHAR(100) DEFAULT NULL,
            current_pincode VARCHAR(10) DEFAULT NULL,
            permanent_address TEXT DEFAULT NULL,
            permanent_city VARCHAR(100) DEFAULT NULL,
            permanent_state VARCHAR(100) DEFAULT NULL,
            permanent_pincode VARCHAR(10) DEFAULT NULL,
            
            -- Employment Information
            department VARCHAR(100) NOT NULL,
            designation VARCHAR(100) NOT NULL,
            employee_type ENUM('Full-time', 'Part-time', 'Contract', 'Intern') DEFAULT 'Full-time',
            date_of_joining DATE NOT NULL,
            date_of_leaving DATE DEFAULT NULL,
            reporting_manager_id INT DEFAULT NULL,
            work_location VARCHAR(100) DEFAULT NULL,
            shift_timing VARCHAR(50) DEFAULT NULL,
            probation_period INT DEFAULT 90 COMMENT 'in days',
            confirmation_date DATE DEFAULT NULL,
            
            -- Salary & Financial
            salary_type ENUM('Monthly', 'Hourly', 'Daily') DEFAULT 'Monthly',
            basic_salary DECIMAL(10, 2) DEFAULT 0.00,
            hra DECIMAL(10, 2) DEFAULT 0.00,
            conveyance_allowance DECIMAL(10, 2) DEFAULT 0.00,
            medical_allowance DECIMAL(10, 2) DEFAULT 0.00,
            special_allowance DECIMAL(10, 2) DEFAULT 0.00,
            gross_salary DECIMAL(10, 2) DEFAULT 0.00,
            pf_number VARCHAR(50) DEFAULT NULL,
            esi_number VARCHAR(50) DEFAULT NULL,
            uan_number VARCHAR(50) DEFAULT NULL,
            pan_number VARCHAR(20) DEFAULT NULL,
            
            -- Bank Details
            bank_name VARCHAR(100) DEFAULT NULL,
            bank_account_number VARCHAR(50) DEFAULT NULL,
            bank_ifsc_code VARCHAR(20) DEFAULT NULL,
            bank_branch VARCHAR(100) DEFAULT NULL,
            
            -- Documents & Identification
            aadhar_number VARCHAR(12) DEFAULT NULL,
            passport_number VARCHAR(20) DEFAULT NULL,
            driving_license VARCHAR(30) DEFAULT NULL,
            voter_id VARCHAR(30) DEFAULT NULL,
            
            -- Documents Uploaded
            photo_path VARCHAR(255) DEFAULT NULL,
            resume_path VARCHAR(255) DEFAULT NULL,
            aadhar_document_path VARCHAR(255) DEFAULT NULL,
            pan_document_path VARCHAR(255) DEFAULT NULL,
            education_documents_path TEXT DEFAULT NULL COMMENT 'JSON array of paths',
            experience_documents_path TEXT DEFAULT NULL COMMENT 'JSON array of paths',
            
            -- Educational Qualification
            highest_qualification VARCHAR(100) DEFAULT NULL,
            specialization VARCHAR(100) DEFAULT NULL,
            university VARCHAR(150) DEFAULT NULL,
            year_of_passing INT DEFAULT NULL,
            
            -- Previous Experience
            previous_company VARCHAR(150) DEFAULT NULL,
            previous_designation VARCHAR(100) DEFAULT NULL,
            previous_experience_years DECIMAL(4, 2) DEFAULT 0.00,
            total_experience_years DECIMAL(4, 2) DEFAULT 0.00,
            
            -- Access & Status
            status ENUM('Active', 'Inactive', 'On Leave', 'Terminated', 'Resigned') DEFAULT 'Active',
            is_user_created BOOLEAN DEFAULT FALSE,
            user_id INT DEFAULT NULL COMMENT 'Links to users table if login created',
            
            -- Additional Fields
            skills TEXT DEFAULT NULL,
            certifications TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            
            -- Indexes for performance
            INDEX idx_employee_code (employee_code),
            INDEX idx_official_email (official_email),
            INDEX idx_department (department),
            INDEX idx_status (status),
            INDEX idx_date_of_joining (date_of_joining),
            
            -- Foreign Keys
            FOREIGN KEY (reporting_manager_id) REFERENCES employees(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee Master Table'";
        
        if (mysqli_query($conn, $create_table)) {
            // Create departments table
            $create_departments = "CREATE TABLE IF NOT EXISTS departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                department_name VARCHAR(100) UNIQUE NOT NULL,
                department_code VARCHAR(20) UNIQUE NOT NULL,
                head_of_department INT DEFAULT NULL,
                description TEXT DEFAULT NULL,
                status ENUM('Active', 'Inactive') DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            mysqli_query($conn, $create_departments);
            
            // Create designations table
            $create_designations = "CREATE TABLE IF NOT EXISTS designations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                designation_name VARCHAR(100) UNIQUE NOT NULL,
                designation_code VARCHAR(20) UNIQUE NOT NULL,
                department_id INT DEFAULT NULL,
                level VARCHAR(50) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                status ENUM('Active', 'Inactive') DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            mysqli_query($conn, $create_designations);
            
            // Insert sample departments
            $sample_departments = [
                ['IT', 'IT001', 'Information Technology'],
                ['HR', 'HR001', 'Human Resources'],
                ['Finance', 'FIN001', 'Finance & Accounts'],
                ['Sales', 'SAL001', 'Sales & Marketing'],
                ['Operations', 'OPS001', 'Operations'],
                ['Admin', 'ADM001', 'Administration']
            ];
            
            foreach ($sample_departments as $dept) {
                $stmt = mysqli_prepare($conn, "INSERT INTO departments (department_name, department_code, description) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sss', $dept[0], $dept[1], $dept[2]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            
            // Insert sample designations
            $sample_designations = [
                ['Manager', 'MGR', 'Manager'],
                ['Senior Executive', 'SREX', 'Senior Executive'],
                ['Executive', 'EXE', 'Executive'],
                ['Team Leader', 'TL', 'Team Leader'],
                ['Developer', 'DEV', 'Developer'],
                ['HR Executive', 'HREX', 'HR Executive'],
                ['Accountant', 'ACC', 'Accountant'],
                ['Sales Executive', 'SALEX', 'Sales Executive']
            ];
            
            foreach ($sample_designations as $desig) {
                $stmt = mysqli_prepare($conn, "INSERT INTO designations (designation_name, designation_code, level) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sss', $desig[0], $desig[1], $desig[2]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            
            closeConnection($conn);
            return [
                'success' => true, 
                'message' => 'Employee module database setup completed successfully! Created tables: employees, departments, designations with sample data.'
            ];
        } else {
            closeConnection($conn);
            return ['success' => false, 'message' => 'Error creating employees table: ' . mysqli_error($conn)];
        }
        
    } catch (Exception $e) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}

// Run setup if form submitted
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = setupEmployeesTable();
}
?>

<div class="container" style="max-width: 700px; margin-top: 50px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #003581; margin-bottom: 10px;">👥 Employee Module Setup</h1>
        <p style="color: #6c757d;">Create database tables for Employee Management</p>
    </div>

    <?php if ($result): ?>
        <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($result['message']); ?>
        </div>
        
        <?php if ($result['success']): ?>
            <div style="text-align: center; margin-top: 30px;">
                <a href="../public/employees.php" class="btn">Go to Employee Module</a>
                <a href="../public/dashboard.php" class="btn btn-accent" style="margin-left: 10px;">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <strong>ℹ️ Setup Information</strong><br>
            This will create the following database tables:<br><br>
            <strong>1. employees</strong> - Main employee master table with 70+ fields<br>
            <strong>2. departments</strong> - Department master with sample data<br>
            <strong>3. designations</strong> - Designation master with sample data<br><br>
            Click below to create these tables.
        </div>

        <form method="POST" style="text-align: center; margin-top: 30px;">
            <button type="submit" class="btn" style="padding: 15px 40px; font-size: 16px;">
                🚀 Create Employee Module Tables
            </button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="../public/dashboard.php" class="btn btn-accent">Back to Dashboard</a>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #6c757d;">
        <strong>📋 Tables to be created:</strong><br>
        • <strong>employees:</strong> Personal, Contact, Employment, Salary, Bank, Documents, Education, Experience<br>
        • <strong>departments:</strong> IT, HR, Finance, Sales, Operations, Admin<br>
        • <strong>designations:</strong> Manager, Executive, Team Leader, Developer, etc.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
