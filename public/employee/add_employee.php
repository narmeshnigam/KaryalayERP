<?php
/**
 * Add New Employee (Scoped under /public/employee)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Page title
$page_title = "Add New Employee - " . APP_NAME;

// Get departments and designations for dropdowns
$departments = [];
$dept_result = mysqli_query($conn, "SELECT department_name FROM departments WHERE status='Active' ORDER BY department_name");
while ($row = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $row['department_name'];
}

$designations = [];
$desig_result = mysqli_query($conn, "SELECT designation_name FROM designations WHERE status='Active' ORDER BY designation_name");
while ($row = mysqli_fetch_assoc($desig_result)) {
    $designations[] = $row['designation_name'];
}

// Get reporting managers (existing employees)
$managers = [];
$mgr_result = mysqli_query($conn, "SELECT id, CONCAT(first_name, ' ', last_name, ' (', employee_code, ')') as name FROM employees WHERE status='Active' ORDER BY first_name");
while ($row = mysqli_fetch_assoc($mgr_result)) {
    $managers[] = $row;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'date_of_birth', 'gender', 'official_email', 'mobile_number', 'department', 'designation', 'date_of_joining'];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucwords(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (count($errors) == 0) {
        // Generate employee code
        $dept_code = strtoupper(substr($_POST['department'], 0, 3));
        $count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM employees");
        $count = mysqli_fetch_assoc($count_result)['count'] + 1;
        $employee_code = $dept_code . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        // Check if employee code already exists
        $check_query = "SELECT id FROM employees WHERE employee_code = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 's', $employee_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Generate new code with timestamp
            $employee_code = $dept_code . time();
        }
        
        // Handle file uploads
        $photo_path = null;
        $resume_path = null;
        $aadhar_doc_path = null;
        $pan_doc_path = null;
        
        $upload_dir = __DIR__ . '/../../uploads/employees/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = $employee_code . '_photo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                $photo_path = 'uploads/employees/' . $filename;
            }
        }
        
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
            $ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
            $filename = $employee_code . '_resume_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_dir . $filename)) {
                $resume_path = 'uploads/employees/' . $filename;
            }
        }
        
        if (isset($_FILES['aadhar_document']) && $_FILES['aadhar_document']['error'] == 0) {
            $ext = pathinfo($_FILES['aadhar_document']['name'], PATHINFO_EXTENSION);
            $filename = $employee_code . '_aadhar_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['aadhar_document']['tmp_name'], $upload_dir . $filename)) {
                $aadhar_doc_path = 'uploads/employees/' . $filename;
            }
        }
        
        if (isset($_FILES['pan_document']) && $_FILES['pan_document']['error'] == 0) {
            $ext = pathinfo($_FILES['pan_document']['name'], PATHINFO_EXTENSION);
            $filename = $employee_code . '_pan_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['pan_document']['tmp_name'], $upload_dir . $filename)) {
                $pan_doc_path = 'uploads/employees/' . $filename;
            }
        }
        
        // Calculate gross salary
        $basic_salary = floatval($_POST['basic_salary'] ?? 0);
        $hra = floatval($_POST['hra'] ?? 0);
        $conveyance = floatval($_POST['conveyance_allowance'] ?? 0);
        $medical = floatval($_POST['medical_allowance'] ?? 0);
        $special = floatval($_POST['special_allowance'] ?? 0);
        $gross_salary = $basic_salary + $hra + $conveyance + $medical + $special;
        
        // Insert employee (build placeholders programmatically to avoid mismatches)
        $columns_sql = "employee_code, first_name, middle_name, last_name, date_of_birth, gender, blood_group, marital_status, nationality,
            personal_email, official_email, mobile_number, alternate_mobile, emergency_contact_name, emergency_contact_number, emergency_contact_relation,
            current_address, current_city, current_state, current_pincode,
            permanent_address, permanent_city, permanent_state, permanent_pincode,
            department, designation, employee_type, date_of_joining, reporting_manager_id, work_location, shift_timing, probation_period,
            salary_type, basic_salary, hra, conveyance_allowance, medical_allowance, special_allowance, gross_salary,
            pf_number, esi_number, uan_number, pan_number,
            bank_name, bank_account_number, bank_ifsc_code, bank_branch,
            aadhar_number, passport_number, driving_license, voter_id,
            photo_path, resume_path, aadhar_document_path, pan_document_path,
            highest_qualification, specialization, university, year_of_passing,
            previous_company, previous_designation, previous_experience_years, total_experience_years,
            status, skills, certifications, notes, created_by";

        // 68 placeholders for 68 columns
        $placeholders = implode(', ', array_fill(0, 68, '?'));
        $insert_query = "INSERT INTO employees ($columns_sql) VALUES ($placeholders)";

        $stmt = mysqli_prepare($conn, $insert_query);

        if (!$stmt) {
            $errors[] = 'Database prepare error: ' . mysqli_error($conn);
        } else {
            // Types map for 68 params
            $types = 'ssssssssssssssssssssssssssss'  // 1-28
                   . 'i'                              // 29
                   . 'ss'                             // 30-31
                   . 'i'                              // 32
                   . 's'                              // 33
                   . 'dddddd'                         // 34-39
                   . 'ssssssss'                       // 40-47
                   . 'ssss'                           // 48-51
                   . 'ssss'                           // 52-55
                   . 'sss'                            // 56-58
                   . 'i'                              // 59
                   . 'ss'                             // 60-61
                   . 'dd'                             // 62-63
                   . 'ssss'                           // 64-67
                   . 'i';                             // 68

            // Normalize certain values/types
            $reporting_manager_id = !empty($_POST['reporting_manager_id']) ? (int)$_POST['reporting_manager_id'] : null;
            $probation_period = isset($_POST['probation_period']) && $_POST['probation_period'] !== '' ? (int)$_POST['probation_period'] : 90;
            $year_of_passing = !empty($_POST['year_of_passing']) ? (int)$_POST['year_of_passing'] : null;
            $prev_exp_years = isset($_POST['previous_experience_years']) && $_POST['previous_experience_years'] !== '' ? (float)$_POST['previous_experience_years'] : 0;
            $total_exp_years = isset($_POST['total_experience_years']) && $_POST['total_experience_years'] !== '' ? (float)$_POST['total_experience_years'] : 0;
            $created_by = (int)$CURRENT_USER_ID;

            $values = [
                $employee_code,
                $_POST['first_name'],
                $_POST['middle_name'] ?? null,
                $_POST['last_name'],
                $_POST['date_of_birth'],
                $_POST['gender'],
                $_POST['blood_group'] ?? null,
                $_POST['marital_status'] ?? 'Single',
                $_POST['nationality'] ?? 'Indian',
                $_POST['personal_email'] ?? null,
                $_POST['official_email'],
                $_POST['mobile_number'],
                $_POST['alternate_mobile'] ?? null,
                $_POST['emergency_contact_name'] ?? null,
                $_POST['emergency_contact_number'] ?? null,
                $_POST['emergency_contact_relation'] ?? null,
                $_POST['current_address'] ?? null,
                $_POST['current_city'] ?? null,
                $_POST['current_state'] ?? null,
                $_POST['current_pincode'] ?? null,
                $_POST['permanent_address'] ?? null,
                $_POST['permanent_city'] ?? null,
                $_POST['permanent_state'] ?? null,
                $_POST['permanent_pincode'] ?? null,
                $_POST['department'],
                $_POST['designation'],
                $_POST['employee_type'] ?? 'Full-time',
                $_POST['date_of_joining'],
                $reporting_manager_id,
                $_POST['work_location'] ?? null,
                $_POST['shift_timing'] ?? null,
                $probation_period,
                $_POST['salary_type'] ?? 'Monthly',
                $basic_salary,
                $hra,
                $conveyance,
                $medical,
                $special,
                $gross_salary,
                $_POST['pf_number'] ?? null,
                $_POST['esi_number'] ?? null,
                $_POST['uan_number'] ?? null,
                $_POST['pan_number'] ?? null,
                $_POST['bank_name'] ?? null,
                $_POST['bank_account_number'] ?? null,
                $_POST['bank_ifsc_code'] ?? null,
                $_POST['bank_branch'] ?? null,
                $_POST['aadhar_number'] ?? null,
                $_POST['passport_number'] ?? null,
                $_POST['driving_license'] ?? null,
                $_POST['voter_id'] ?? null,
                $photo_path,
                $resume_path,
                $aadhar_doc_path,
                $pan_doc_path,
                $_POST['highest_qualification'] ?? null,
                $_POST['specialization'] ?? null,
                $_POST['university'] ?? null,
                $year_of_passing,
                $_POST['previous_company'] ?? null,
                $_POST['previous_designation'] ?? null,
                $prev_exp_years,
                $total_exp_years,
                $_POST['status'] ?? 'Active',
                $_POST['skills'] ?? null,
                $_POST['certifications'] ?? null,
                $_POST['notes'] ?? null,
                $created_by
            ];

            $bindParams = array_merge([$stmt, $types], $values);
            $bindReferences = [];
            foreach ($bindParams as $key => $value) {
                $bindReferences[$key] = &$bindParams[$key];
            }

            call_user_func_array('mysqli_stmt_bind_param', $bindReferences);
        
            if (mysqli_stmt_execute($stmt)) {
                $success = true;
                $employee_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
                    closeConnection($conn);
                }
                header('Location: index.php?success=1&employee_code=' . urlencode($employee_code));
                exit;
            } else {
                $errors[] = 'Error adding employee: ' . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}

// Include header with sidebar AFTER all processing logic
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="emp-header-flex">
                <div>
                    <h1>➕ Add New Employee</h1>
                    <p>Enter employee information and details</p>
                </div>
                <div class="emp-header-btn-mobile">
                    <a href="index.php" class="btn btn-accent">
                        ← Back to Employee List
                    </a>
                </div>
            </div>
        </div>

        <?php if (count($errors) > 0): ?>
            <div class="alert alert-error">
                <strong>❌ Error:</strong><br>
                <?php foreach ($errors as $error): ?>
                    • <?php echo htmlspecialchars($error); ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Personal Information -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    👤 Personal Information
                </h3>
                <div class="emp-form-grid-3">
                    <div class="form-group">
                        <label>First Name <span class="emp-required">*</span></label>
                        <input type="text" name="first_name" class="form-control" required value="<?php echo $_POST['first_name'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="<?php echo $_POST['middle_name'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="emp-required">*</span></label>
                        <input type="text" name="last_name" class="form-control" required value="<?php echo $_POST['last_name'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth <span class="emp-required">*</span></label>
                        <input type="date" name="date_of_birth" class="form-control" required value="<?php echo $_POST['date_of_birth'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="emp-required">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($_POST['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group</label>
                        <select name="blood_group" class="form-control">
                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marital Status</label>
                        <select name="marital_status" class="form-control">
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" name="nationality" class="form-control" value="Indian">
                    </div>
                    <div class="form-group">
                        <label>Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    📞 Contact Information
                </h3>
                <div class="emp-form-grid-3">
                    <div class="form-group">
                        <label>Official Email <span class="emp-required">*</span></label>
                        <input type="email" name="official_email" class="form-control" required value="<?php echo $_POST['official_email'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Personal Email</label>
                        <input type="email" name="personal_email" class="form-control" value="<?php echo $_POST['personal_email'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Mobile Number <span class="emp-required">*</span></label>
                        <input type="tel" name="mobile_number" class="form-control" required value="<?php echo $_POST['mobile_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Alternate Mobile</label>
                        <input type="tel" name="alternate_mobile" class="form-control" value="<?php echo $_POST['alternate_mobile'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo $_POST['emergency_contact_name'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Number</label>
                        <input type="tel" name="emergency_contact_number" class="form-control" value="<?php echo $_POST['emergency_contact_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Relation</label>
                        <input type="text" name="emergency_contact_relation" class="form-control" placeholder="Father, Mother, Spouse, etc." value="<?php echo $_POST['emergency_contact_relation'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    🏠 Address Information
                </h3>
                <div class="emp-subsection-row">
                    <strong class="emp-subtitle-label">Current Address:</strong>
                </div>
                <div class="emp-form-grid-2 emp-subsection-row-large">
                    <div class="form-group emp-grid-full">
                        <label>Current Address</label>
                        <textarea name="current_address" class="form-control" rows="2"><?php echo $_POST['current_address'] ?? ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="current_city" class="form-control" value="<?php echo $_POST['current_city'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" name="current_state" class="form-control" value="<?php echo $_POST['current_state'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="current_pincode" class="form-control" value="<?php echo $_POST['current_pincode'] ?? ''; ?>">
                    </div>
                </div>
                <div class="emp-subsection-row">
                    <strong class="emp-subtitle-label">Permanent Address:</strong>
                    <label class="emp-checkbox-inline">
                        <input type="checkbox" onclick="copyAddress(this)"> Same as Current Address
                    </label>
                </div>
                <div class="emp-form-grid-2">
                    <div class="form-group emp-grid-full">
                        <label>Permanent Address</label>
                        <textarea name="permanent_address" class="form-control" rows="2" id="permanent_address"><?php echo $_POST['permanent_address'] ?? ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="permanent_city" class="form-control" id="permanent_city" value="<?php echo $_POST['permanent_city'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" name="permanent_state" class="form-control" id="permanent_state" value="<?php echo $_POST['permanent_state'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="permanent_pincode" class="form-control" id="permanent_pincode" value="<?php echo $_POST['permanent_pincode'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Employment Information -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    💼 Employment Information
                </h3>
                <div class="emp-form-grid-3">
                    <div class="form-group">
                        <label>Department <span class="emp-required">*</span></label>
                        <select name="department" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Designation <span class="emp-required">*</span></label>
                        <select name="designation" class="form-control" required>
                            <option value="">Select Designation</option>
                            <?php foreach ($designations as $desig): ?>
                                <option value="<?php echo htmlspecialchars($desig); ?>"><?php echo htmlspecialchars($desig); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Employee Type</label>
                        <select name="employee_type" class="form-control">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Intern">Intern</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Joining <span class="emp-required">*</span></label>
                        <input type="date" name="date_of_joining" class="form-control" required value="<?php echo $_POST['date_of_joining'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Reporting Manager</label>
                        <select name="reporting_manager_id" class="form-control">
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $mgr): ?>
                                <option value="<?php echo $mgr['id']; ?>"><?php echo htmlspecialchars($mgr['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Work Location</label>
                        <input type="text" name="work_location" class="form-control" placeholder="e.g., Head Office, Branch" value="<?php echo $_POST['work_location'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Shift Timing</label>
                        <input type="text" name="shift_timing" class="form-control" placeholder="e.g., 9:00 AM - 6:00 PM" value="<?php echo $_POST['shift_timing'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Probation Period (days)</label>
                        <input type="number" name="probation_period" class="form-control" value="90">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="On Leave">On Leave</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Salary Information -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    💰 Salary & Financial Information
                </h3>
                <div class="emp-form-grid-3">
                    <div class="form-group">
                        <label>Salary Type</label>
                        <select name="salary_type" class="form-control">
                            <option value="Monthly">Monthly</option>
                            <option value="Hourly">Hourly</option>
                            <option value="Daily">Daily</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Basic Salary</label>
                        <input type="number" step="0.01" name="basic_salary" class="form-control" value="0" onchange="calculateGross()">
                    </div>
                    <div class="form-group">
                        <label>HRA</label>
                        <input type="number" step="0.01" name="hra" class="form-control" value="0" onchange="calculateGross()">
                    </div>
                    <div class="form-group">
                        <label>Conveyance Allowance</label>
                        <input type="number" step="0.01" name="conveyance_allowance" class="form-control" value="0" onchange="calculateGross()">
                    </div>
                    <div class="form-group">
                        <label>Medical Allowance</label>
                        <input type="number" step="0.01" name="medical_allowance" class="form-control" value="0" onchange="calculateGross()">
                    </div>
                    <div class="form-group">
                        <label>Special Allowance</label>
                        <input type="number" step="0.01" name="special_allowance" class="form-control" value="0" onchange="calculateGross()">
                    </div>
                    <div class="form-group emp-grid-full">
                        <label><strong>Gross Salary</strong></label>
                        <input type="text" id="gross_display" class="form-control emp-gross-display" readonly value="₹ 0.00">
                    </div>
                    <div class="form-group">
                        <label>PF Number</label>
                        <input type="text" name="pf_number" class="form-control" value="<?php echo $_POST['pf_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>ESI Number</label>
                        <input type="text" name="esi_number" class="form-control" value="<?php echo $_POST['esi_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>UAN Number</label>
                        <input type="text" name="uan_number" class="form-control" value="<?php echo $_POST['uan_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>PAN Number</label>
                        <input type="text" name="pan_number" class="form-control" value="<?php echo $_POST['pan_number'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    🏦 Bank Details
                </h3>
                <div class="emp-form-grid-2">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="<?php echo $_POST['bank_name'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="bank_account_number" class="form-control" value="<?php echo $_POST['bank_account_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="bank_ifsc_code" class="form-control" value="<?php echo $_POST['bank_ifsc_code'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Branch</label>
                        <input type="text" name="bank_branch" class="form-control" value="<?php echo $_POST['bank_branch'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Documents & Identification -->
                <div class="card emp-card">
                <h3 class="emp-section-title">
                    📄 Documents & Identification
                </h3>
                <div class="emp-form-grid-2">
                    <div class="form-group">
                        <label>Aadhar Number</label>
                        <input type="text" name="aadhar_number" class="form-control" value="<?php echo $_POST['aadhar_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Aadhar Document</label>
                        <input type="file" name="aadhar_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="form-group">
                        <label>Passport Number</label>
                        <input type="text" name="passport_number" class="form-control" value="<?php echo $_POST['passport_number'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Driving License</label>
                        <input type="text" name="driving_license" class="form-control" value="<?php echo $_POST['driving_license'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Voter ID</label>
                        <input type="text" name="voter_id" class="form-control" value="<?php echo $_POST['voter_id'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>PAN Document</label>
                        <input type="file" name="pan_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="form-group">
                        <label>Resume/CV</label>
                        <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
                    </div>
                </div>
            </div>

            <!-- Education & Experience -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    🎓 Education & Experience
                </h3>
                <div class="emp-form-grid-2">
                    <div class="form-group">
                        <label>Highest Qualification</label>
                        <input type="text" name="highest_qualification" class="form-control" placeholder="e.g., B.Tech, MBA, etc." value="<?php echo $_POST['highest_qualification'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="form-control" placeholder="e.g., Computer Science" value="<?php echo $_POST['specialization'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>University</label>
                        <input type="text" name="university" class="form-control" value="<?php echo $_POST['university'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Year of Passing</label>
                        <input type="number" name="year_of_passing" class="form-control" min="1950" max="2050" value="<?php echo $_POST['year_of_passing'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Previous Company</label>
                        <input type="text" name="previous_company" class="form-control" value="<?php echo $_POST['previous_company'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Previous Designation</label>
                        <input type="text" name="previous_designation" class="form-control" value="<?php echo $_POST['previous_designation'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Previous Experience (Years)</label>
                        <input type="number" step="0.5" name="previous_experience_years" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Total Experience (Years)</label>
                        <input type="number" step="0.5" name="total_experience_years" class="form-control" value="0">
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="card emp-card">
                <h3 class="emp-section-title">
                    ℹ️ Additional Information
                </h3>
                <div class="emp-single-column-grid">
                    <div class="form-group">
                        <label>Skills (comma-separated)</label>
                        <textarea name="skills" class="form-control" rows="2" placeholder="e.g., PHP, JavaScript, Project Management"><?php echo $_POST['skills'] ?? ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Certifications (comma-separated)</label>
                        <textarea name="certifications" class="form-control" rows="2" placeholder="e.g., PMP, AWS Certified, etc."><?php echo $_POST['certifications'] ?? ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?php echo $_POST['notes'] ?? ''; ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="emp-button-group">
                <button type="submit" class="btn">
                    ✅ Add Employee
                </button>
                <a href="index.php" class="btn btn-accent">
                    ❌ Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function copyAddress(checkbox) {
    if (checkbox.checked) {
        document.getElementById('permanent_address').value = document.getElementsByName('current_address')[0].value;
        document.getElementById('permanent_city').value = document.getElementsByName('current_city')[0].value;
        document.getElementById('permanent_state').value = document.getElementsByName('current_state')[0].value;
        document.getElementById('permanent_pincode').value = document.getElementsByName('current_pincode')[0].value;
    }
}

function calculateGross() {
    const basic = parseFloat(document.getElementsByName('basic_salary')[0].value) || 0;
    const hra = parseFloat(document.getElementsByName('hra')[0].value) || 0;
    const conveyance = parseFloat(document.getElementsByName('conveyance_allowance')[0].value) || 0;
    const medical = parseFloat(document.getElementsByName('medical_allowance')[0].value) || 0;
    const special = parseFloat(document.getElementsByName('special_allowance')[0].value) || 0;
    
    const gross = basic + hra + conveyance + medical + special;
    document.getElementById('gross_display').value = '₹ ' + gross.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
