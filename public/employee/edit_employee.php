<?php
/**
 * Edit Employee (Scoped under /public/employee)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Get available users for linking
$available_users = [];
$users_result = mysqli_query($conn, "SELECT u.id, u.username, u.full_name, u.email, u.role, 
                                      (SELECT COUNT(*) FROM employees WHERE user_id = u.id) as is_linked 
                                      FROM users u 
                                      WHERE u.is_active = 1 
                                      ORDER BY u.username");
while ($row = mysqli_fetch_assoc($users_result)) {
    $available_users[] = $row;
}

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

$managers = [];
$mgr_result = mysqli_query($conn, "SELECT id, CONCAT(first_name, ' ', last_name, ' (', employee_code, ')') as name FROM employees WHERE status='Active' ORDER BY first_name");
while ($row = mysqli_fetch_assoc($mgr_result)) {
    $managers[] = $row;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM employees WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$emp = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$emp) {
  if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
  }
    header('Location: index.php');
    exit;
}

$blood_groups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$marital_statuses = ['Single','Married','Divorced','Widowed'];
$employee_types = ['Full-time','Part-time','Contract','Intern'];
$salary_types = ['Monthly','Hourly','Daily'];
$status_options = ['Active','Inactive','On Leave','Terminated','Resigned'];
$gender_options = ['Male','Female','Other'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = ['first_name','last_name','official_email','mobile_number','department','designation','date_of_joining'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucwords(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    $photo_path = $emp['photo_path'];
    $resume_path = $emp['resume_path'];
    $aadhar_doc_path = $emp['aadhar_document_path'];
    $pan_doc_path = $emp['pan_document_path'];

    $upload_dir = __DIR__ . '/../../uploads/employees/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $employee_code = $emp['employee_code'];

  if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = $employee_code . '_photo_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
      $photo_path = 'uploads/employees/' . $filename;
    }
  }

  if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
    $filename = $employee_code . '_resume_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_dir . $filename)) {
      $resume_path = 'uploads/employees/' . $filename;
    }
  }

  if (isset($_FILES['aadhar_document']) && $_FILES['aadhar_document']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['aadhar_document']['name'], PATHINFO_EXTENSION);
    $filename = $employee_code . '_aadhar_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['aadhar_document']['tmp_name'], $upload_dir . $filename)) {
      $aadhar_doc_path = 'uploads/employees/' . $filename;
    }
  }

  if (isset($_FILES['pan_document']) && $_FILES['pan_document']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['pan_document']['name'], PATHINFO_EXTENSION);
    $filename = $employee_code . '_pan_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['pan_document']['tmp_name'], $upload_dir . $filename)) {
      $pan_doc_path = 'uploads/employees/' . $filename;
    }
  }

    if (count($errors) === 0) {
        $basic_salary = (float)($_POST['basic_salary'] ?? 0);
        $hra = (float)($_POST['hra'] ?? 0);
        $conveyance = (float)($_POST['conveyance_allowance'] ?? 0);
        $medical = (float)($_POST['medical_allowance'] ?? 0);
        $special = (float)($_POST['special_allowance'] ?? 0);
        $gross_salary = $basic_salary + $hra + $conveyance + $medical + $special;

        $reporting_manager_id = isset($_POST['reporting_manager_id']) && $_POST['reporting_manager_id'] !== ''
            ? $_POST['reporting_manager_id']
            : null;
        $probation_period = isset($_POST['probation_period']) && $_POST['probation_period'] !== ''
            ? $_POST['probation_period']
            : 90;
        $year_of_passing = isset($_POST['year_of_passing']) && $_POST['year_of_passing'] !== ''
            ? $_POST['year_of_passing']
            : null;
        $previous_experience_years = isset($_POST['previous_experience_years']) && $_POST['previous_experience_years'] !== ''
            ? $_POST['previous_experience_years']
            : null;
        $total_experience_years = isset($_POST['total_experience_years']) && $_POST['total_experience_years'] !== ''
            ? $_POST['total_experience_years']
            : null;

        // Handle user account linking
        $is_user_created = isset($_POST['is_user_created']) && $_POST['is_user_created'] == '1' ? 1 : 0;
        $user_id_to_link = null;
        
        if ($is_user_created && !empty($_POST['user_id'])) {
            $user_id_to_link = (int)$_POST['user_id'];
            
            // Check if this user is already linked to another employee
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM employees WHERE user_id = ? AND id != ?");
            mysqli_stmt_bind_param($check_stmt, 'ii', $user_id_to_link, $id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                $errors[] = 'This user account is already linked to another employee';
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);
            }
        }

        if (count($errors) === 0) {
        $update_data = [
            'first_name' => trim($_POST['first_name']),
            'middle_name' => trim($_POST['middle_name'] ?? '') ?: null,
            'last_name' => trim($_POST['last_name']),
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'blood_group' => $_POST['blood_group'] ?? null,
            'marital_status' => $_POST['marital_status'] ?? null,
            'nationality' => trim($_POST['nationality'] ?? '') ?: null,
            'personal_email' => trim($_POST['personal_email'] ?? '') ?: null,
            'official_email' => trim($_POST['official_email']),
            'mobile_number' => trim($_POST['mobile_number']),
            'alternate_mobile' => trim($_POST['alternate_mobile'] ?? '') ?: null,
            'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? '') ?: null,
            'emergency_contact_number' => trim($_POST['emergency_contact_number'] ?? '') ?: null,
            'emergency_contact_relation' => trim($_POST['emergency_contact_relation'] ?? '') ?: null,
            'current_address' => trim($_POST['current_address'] ?? '') ?: null,
            'current_city' => trim($_POST['current_city'] ?? '') ?: null,
            'current_state' => trim($_POST['current_state'] ?? '') ?: null,
            'current_pincode' => trim($_POST['current_pincode'] ?? '') ?: null,
            'permanent_address' => trim($_POST['permanent_address'] ?? '') ?: null,
            'permanent_city' => trim($_POST['permanent_city'] ?? '') ?: null,
            'permanent_state' => trim($_POST['permanent_state'] ?? '') ?: null,
            'permanent_pincode' => trim($_POST['permanent_pincode'] ?? '') ?: null,
            'department' => $_POST['department'],
            'designation' => $_POST['designation'],
            'employee_type' => $_POST['employee_type'] ?? 'Full-time',
            'date_of_joining' => $_POST['date_of_joining'] ?? null,
            'date_of_leaving' => $_POST['date_of_leaving'] ?? null,
            'reporting_manager_id' => $reporting_manager_id,
            'work_location' => trim($_POST['work_location'] ?? '') ?: null,
            'shift_timing' => trim($_POST['shift_timing'] ?? '') ?: null,
            'probation_period' => $probation_period,
            'confirmation_date' => $_POST['confirmation_date'] ?? null,
            'salary_type' => $_POST['salary_type'] ?? 'Monthly',
            'basic_salary' => $basic_salary,
            'hra' => $hra,
            'conveyance_allowance' => $conveyance,
            'medical_allowance' => $medical,
            'special_allowance' => $special,
            'gross_salary' => $gross_salary,
            'pf_number' => trim($_POST['pf_number'] ?? '') ?: null,
            'esi_number' => trim($_POST['esi_number'] ?? '') ?: null,
            'uan_number' => trim($_POST['uan_number'] ?? '') ?: null,
            'pan_number' => trim($_POST['pan_number'] ?? '') ?: null,
            'bank_name' => trim($_POST['bank_name'] ?? '') ?: null,
            'bank_account_number' => trim($_POST['bank_account_number'] ?? '') ?: null,
            'bank_ifsc_code' => trim($_POST['bank_ifsc_code'] ?? '') ?: null,
            'bank_branch' => trim($_POST['bank_branch'] ?? '') ?: null,
            'aadhar_number' => trim($_POST['aadhar_number'] ?? '') ?: null,
            'passport_number' => trim($_POST['passport_number'] ?? '') ?: null,
            'driving_license' => trim($_POST['driving_license'] ?? '') ?: null,
            'voter_id' => trim($_POST['voter_id'] ?? '') ?: null,
            'photo_path' => $photo_path,
            'resume_path' => $resume_path,
            'aadhar_document_path' => $aadhar_doc_path,
            'pan_document_path' => $pan_doc_path,
            'highest_qualification' => trim($_POST['highest_qualification'] ?? '') ?: null,
            'specialization' => trim($_POST['specialization'] ?? '') ?: null,
            'university' => trim($_POST['university'] ?? '') ?: null,
            'year_of_passing' => $year_of_passing,
            'previous_company' => trim($_POST['previous_company'] ?? '') ?: null,
            'previous_designation' => trim($_POST['previous_designation'] ?? '') ?: null,
            'previous_experience_years' => $previous_experience_years,
            'total_experience_years' => $total_experience_years,
            'status' => $_POST['status'] ?? 'Active',
            'skills' => trim($_POST['skills'] ?? '') ?: null,
            'certifications' => trim($_POST['certifications'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'is_user_created' => $is_user_created,
            'user_id' => $user_id_to_link,
      'updated_by' => (string)$CURRENT_USER_ID
        ];

        $set_clauses = [];
        $values = [];
        foreach ($update_data as $column => $value) {
            $set_clauses[] = $column . ' = ?';
            $values[] = $value;
        }

        $sql = 'UPDATE employees SET ' . implode(', ', $set_clauses) . ' WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = str_repeat('s', count($values)) . 'i';
            $values[] = $id;
            $bind_params = array_merge([$stmt, $types], $values);
            $refs = [];
            foreach ($bind_params as $idx => $val) {
                $refs[$idx] = &$bind_params[$idx];
            }
            call_user_func_array('mysqli_stmt_bind_param', $refs);

      if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
          closeConnection($conn);
        }
                header('Location: view_employee.php?id=' . $id);
                exit;
            }

            $errors[] = 'Update failed: ' . mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }

        $emp = array_merge($emp, $update_data);
        $emp['probation_period'] = $probation_period;
        $emp['year_of_passing'] = $year_of_passing;
        $emp['previous_experience_years'] = $previous_experience_years;
        $emp['total_experience_years'] = $total_experience_years;
        $emp['gross_salary'] = $gross_salary;
        $emp['photo_path'] = $photo_path;
        $emp['resume_path'] = $resume_path;
        $emp['aadhar_document_path'] = $aadhar_doc_path;
        $emp['pan_document_path'] = $pan_doc_path;
    }
    }
}

// Get current linked user info (fetch before closing connection)
$linked_user = null;
if ($emp['user_id']) {
    $user_stmt = mysqli_prepare($conn, "SELECT username, full_name, email, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($user_stmt, 'i', $emp['user_id']);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $linked_user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
  closeConnection($conn);
}

function selected($current, $value) {
    return $current == $value ? 'selected' : '';
}

function safe($value) {
    return htmlspecialchars((string)$value);
}

$gross_display = '₹ ' . number_format((float)($emp['gross_salary'] ?? 0), 2);

// Include headers after form processing to allow redirects
$page_title = "Edit Employee - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>✏️ Edit Employee</h1>
          <p>Update employee information across all sections</p>
        </div>
        <div>
          <a href="view_employee.php?id=<?php echo $id; ?>" class="btn btn-accent">← Back to Profile</a>
        </div>
      </div>
    </div>

    <?php if (count($errors) > 0): ?>
      <div class="alert alert-error">
        <strong>❌ Error:</strong><br>
        <?php foreach ($errors as $error): ?>• <?php echo safe($error); ?><br><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">👤 Personal Information</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div class="form-group">
            <label>First Name <span style="color:#dc3545;">*</span></label>
            <input type="text" name="first_name" class="form-control" value="<?php echo safe($emp['first_name']); ?>">
          </div>
          <div class="form-group">
            <label>Middle Name</label>
            <input type="text" name="middle_name" class="form-control" value="<?php echo safe($emp['middle_name']); ?>">
          </div>
          <div class="form-group">
            <label>Last Name <span style="color:#dc3545;">*</span></label>
            <input type="text" name="last_name" class="form-control" value="<?php echo safe($emp['last_name']); ?>">
          </div>
          <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" value="<?php echo safe($emp['date_of_birth']); ?>">
          </div>
          <div class="form-group">
            <label>Gender</label>
            <select name="gender" class="form-control">
              <option value="">Select Gender</option>
              <?php foreach ($gender_options as $option): ?>
                <option value="<?php echo safe($option); ?>" <?php echo selected($emp['gender'], $option); ?>><?php echo safe($option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Blood Group</label>
            <select name="blood_group" class="form-control">
              <option value="">Select Blood Group</option>
              <?php foreach ($blood_groups as $group): ?>
                <option value="<?php echo safe($group); ?>" <?php echo selected($emp['blood_group'], $group); ?>><?php echo safe($group); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Marital Status</label>
            <select name="marital_status" class="form-control">
              <option value="">Select Status</option>
              <?php foreach ($marital_statuses as $status): ?>
                <option value="<?php echo safe($status); ?>" <?php echo selected($emp['marital_status'], $status); ?>><?php echo safe($status); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Nationality</label>
            <input type="text" name="nationality" class="form-control" value="<?php echo safe($emp['nationality']); ?>">
          </div>
          <div class="form-group">
            <label>Photo</label>
            <input type="file" name="photo" class="form-control" accept="image/*">
            <?php if (!empty($emp['photo_path'])): ?>
              <small>Current: <a href="../../<?php echo safe($emp['photo_path']); ?>" target="_blank">View photo</a></small>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📞 Contact Information</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div class="form-group">
            <label>Official Email <span style="color:#dc3545;">*</span></label>
            <input type="email" name="official_email" class="form-control" value="<?php echo safe($emp['official_email']); ?>">
          </div>
          <div class="form-group">
            <label>Personal Email</label>
            <input type="email" name="personal_email" class="form-control" value="<?php echo safe($emp['personal_email']); ?>">
          </div>
          <div class="form-group">
            <label>Mobile Number <span style="color:#dc3545;">*</span></label>
            <input type="tel" name="mobile_number" class="form-control" value="<?php echo safe($emp['mobile_number']); ?>">
          </div>
          <div class="form-group">
            <label>Alternate Mobile</label>
            <input type="tel" name="alternate_mobile" class="form-control" value="<?php echo safe($emp['alternate_mobile']); ?>">
          </div>
          <div class="form-group">
            <label>Emergency Contact Name</label>
            <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo safe($emp['emergency_contact_name']); ?>">
          </div>
          <div class="form-group">
            <label>Emergency Contact Number</label>
            <input type="text" name="emergency_contact_number" class="form-control" value="<?php echo safe($emp['emergency_contact_number']); ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label>Emergency Contact Relation</label>
            <input type="text" name="emergency_contact_relation" class="form-control" value="<?php echo safe($emp['emergency_contact_relation']); ?>">
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🏠 Address Information</h3>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
          <div class="form-group" style="grid-column:1/-1;">
            <label>Current Address</label>
            <textarea name="current_address" class="form-control" rows="2"><?php echo safe($emp['current_address']); ?></textarea>
          </div>
          <div class="form-group">
            <label>City</label>
            <input type="text" name="current_city" class="form-control" value="<?php echo safe($emp['current_city']); ?>">
          </div>
          <div class="form-group">
            <label>State</label>
            <input type="text" name="current_state" class="form-control" value="<?php echo safe($emp['current_state']); ?>">
          </div>
          <div class="form-group">
            <label>Pincode</label>
            <input type="text" name="current_pincode" class="form-control" value="<?php echo safe($emp['current_pincode']); ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label>Permanent Address</label>
            <textarea name="permanent_address" class="form-control" rows="2"><?php echo safe($emp['permanent_address']); ?></textarea>
          </div>
          <div class="form-group">
            <label>City</label>
            <input type="text" name="permanent_city" class="form-control" value="<?php echo safe($emp['permanent_city']); ?>">
          </div>
          <div class="form-group">
            <label>State</label>
            <input type="text" name="permanent_state" class="form-control" value="<?php echo safe($emp['permanent_state']); ?>">
          </div>
          <div class="form-group">
            <label>Pincode</label>
            <input type="text" name="permanent_pincode" class="form-control" value="<?php echo safe($emp['permanent_pincode']); ?>">
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">💼 Employment Details</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div class="form-group">
            <label>Department <span style="color:#dc3545;">*</span></label>
            <select name="department" class="form-control">
              <?php foreach ($departments as $department): ?>
                <option value="<?php echo safe($department); ?>" <?php echo selected($emp['department'], $department); ?>><?php echo safe($department); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Designation <span style="color:#dc3545;">*</span></label>
            <select name="designation" class="form-control">
              <?php foreach ($designations as $designation): ?>
                <option value="<?php echo safe($designation); ?>" <?php echo selected($emp['designation'], $designation); ?>><?php echo safe($designation); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Employee Type</label>
            <select name="employee_type" class="form-control">
              <?php foreach ($employee_types as $type): ?>
                <option value="<?php echo safe($type); ?>" <?php echo selected($emp['employee_type'], $type); ?>><?php echo safe($type); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Date of Joining <span style="color:#dc3545;">*</span></label>
            <input type="date" name="date_of_joining" class="form-control" value="<?php echo safe($emp['date_of_joining']); ?>">
          </div>
          <div class="form-group">
            <label>Date of Leaving</label>
            <input type="date" name="date_of_leaving" class="form-control" value="<?php echo safe($emp['date_of_leaving']); ?>">
          </div>
          <div class="form-group">
            <label>Reporting Manager</label>
            <select name="reporting_manager_id" class="form-control">
              <option value="">Select Manager</option>
              <?php foreach ($managers as $manager): ?>
                <option value="<?php echo safe($manager['id']); ?>" <?php echo selected($emp['reporting_manager_id'], $manager['id']); ?>><?php echo safe($manager['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Work Location</label>
            <input type="text" name="work_location" class="form-control" value="<?php echo safe($emp['work_location']); ?>">
          </div>
          <div class="form-group">
            <label>Shift Timing</label>
            <input type="text" name="shift_timing" class="form-control" value="<?php echo safe($emp['shift_timing']); ?>">
          </div>
          <div class="form-group">
            <label>Probation (days)</label>
            <input type="number" name="probation_period" class="form-control" value="<?php echo safe($emp['probation_period']); ?>">
          </div>
          <div class="form-group">
            <label>Confirmation Date</label>
            <input type="date" name="confirmation_date" class="form-control" value="<?php echo safe($emp['confirmation_date']); ?>">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
              <?php foreach ($status_options as $status): ?>
                <option value="<?php echo safe($status); ?>" <?php echo selected($emp['status'], $status); ?>><?php echo safe($status); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">💰 Salary & Allowances</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
          <div class="form-group">
            <label>Salary Type</label>
            <select name="salary_type" class="form-control">
              <?php foreach ($salary_types as $type): ?>
                <option value="<?php echo safe($type); ?>" <?php echo selected($emp['salary_type'], $type); ?>><?php echo safe($type); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Basic Salary</label>
            <input type="number" step="0.01" name="basic_salary" class="form-control gross-field" value="<?php echo safe($emp['basic_salary']); ?>">
          </div>
          <div class="form-group">
            <label>HRA</label>
            <input type="number" step="0.01" name="hra" class="form-control gross-field" value="<?php echo safe($emp['hra']); ?>">
          </div>
          <div class="form-group">
            <label>Conveyance Allowance</label>
            <input type="number" step="0.01" name="conveyance_allowance" class="form-control gross-field" value="<?php echo safe($emp['conveyance_allowance']); ?>">
          </div>
          <div class="form-group">
            <label>Medical Allowance</label>
            <input type="number" step="0.01" name="medical_allowance" class="form-control gross-field" value="<?php echo safe($emp['medical_allowance']); ?>">
          </div>
          <div class="form-group">
            <label>Special Allowance</label>
            <input type="number" step="0.01" name="special_allowance" class="form-control gross-field" value="<?php echo safe($emp['special_allowance']); ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label><strong>Gross Salary</strong></label>
            <input type="text" id="gross_display" class="form-control" readonly style="background:#e9ecef;font-weight:700;font-size:18px;color:#003581;" value="<?php echo safe($gross_display); ?>">
          </div>
          <div class="form-group">
            <label>PF Number</label>
            <input type="text" name="pf_number" class="form-control" value="<?php echo safe($emp['pf_number']); ?>">
          </div>
          <div class="form-group">
            <label>ESI Number</label>
            <input type="text" name="esi_number" class="form-control" value="<?php echo safe($emp['esi_number']); ?>">
          </div>
          <div class="form-group">
            <label>UAN Number</label>
            <input type="text" name="uan_number" class="form-control" value="<?php echo safe($emp['uan_number']); ?>">
          </div>
          <div class="form-group">
            <label>PAN Number</label>
            <input type="text" name="pan_number" class="form-control" value="<?php echo safe($emp['pan_number']); ?>">
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🏦 Bank Details</h3>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
          <div class="form-group">
            <label>Bank Name</label>
            <input type="text" name="bank_name" class="form-control" value="<?php echo safe($emp['bank_name']); ?>">
          </div>
          <div class="form-group">
            <label>Account Number</label>
            <input type="text" name="bank_account_number" class="form-control" value="<?php echo safe($emp['bank_account_number']); ?>">
          </div>
          <div class="form-group">
            <label>IFSC Code</label>
            <input type="text" name="bank_ifsc_code" class="form-control" value="<?php echo safe($emp['bank_ifsc_code']); ?>">
          </div>
          <div class="form-group">
            <label>Branch</label>
            <input type="text" name="bank_branch" class="form-control" value="<?php echo safe($emp['bank_branch']); ?>">
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🪪 Documents & Identification</h3>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
          <div class="form-group">
            <label>Aadhar Number</label>
            <input type="text" name="aadhar_number" class="form-control" value="<?php echo safe($emp['aadhar_number']); ?>">
          </div>
          <div class="form-group">
            <label>Aadhar Document</label>
            <input type="file" name="aadhar_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <?php if (!empty($emp['aadhar_document_path'])): ?>
              <small>Current: <a href="../../<?php echo safe($emp['aadhar_document_path']); ?>" target="_blank">View document</a></small>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label>Passport Number</label>
            <input type="text" name="passport_number" class="form-control" value="<?php echo safe($emp['passport_number']); ?>">
          </div>
          <div class="form-group">
            <label>Driving License</label>
            <input type="text" name="driving_license" class="form-control" value="<?php echo safe($emp['driving_license']); ?>">
          </div>
          <div class="form-group">
            <label>Voter ID</label>
            <input type="text" name="voter_id" class="form-control" value="<?php echo safe($emp['voter_id']); ?>">
          </div>
          <div class="form-group">
            <label>PAN Document</label>
            <input type="file" name="pan_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <?php if (!empty($emp['pan_document_path'])): ?>
              <small>Current: <a href="../../<?php echo safe($emp['pan_document_path']); ?>" target="_blank">View document</a></small>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label>Resume</label>
            <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
            <?php if (!empty($emp['resume_path'])): ?>
              <small>Current: <a href="../../<?php echo safe($emp['resume_path']); ?>" target="_blank">View resume</a></small>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🎓 Education & Experience</h3>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
          <div class="form-group">
            <label>Highest Qualification</label>
            <input type="text" name="highest_qualification" class="form-control" value="<?php echo safe($emp['highest_qualification']); ?>">
          </div>
          <div class="form-group">
            <label>Specialization</label>
            <input type="text" name="specialization" class="form-control" value="<?php echo safe($emp['specialization']); ?>">
          </div>
          <div class="form-group">
            <label>University</label>
            <input type="text" name="university" class="form-control" value="<?php echo safe($emp['university']); ?>">
          </div>
          <div class="form-group">
            <label>Year of Passing</label>
            <input type="number" name="year_of_passing" class="form-control" min="1950" max="2050" value="<?php echo safe($emp['year_of_passing']); ?>">
          </div>
          <div class="form-group">
            <label>Previous Company</label>
            <input type="text" name="previous_company" class="form-control" value="<?php echo safe($emp['previous_company']); ?>">
          </div>
          <div class="form-group">
            <label>Previous Designation</label>
            <input type="text" name="previous_designation" class="form-control" value="<?php echo safe($emp['previous_designation']); ?>">
          </div>
          <div class="form-group">
            <label>Previous Experience (Years)</label>
            <input type="number" step="0.5" name="previous_experience_years" class="form-control" value="<?php echo safe($emp['previous_experience_years']); ?>">
          </div>
          <div class="form-group">
            <label>Total Experience (Years)</label>
            <input type="number" step="0.5" name="total_experience_years" class="form-control" value="<?php echo safe($emp['total_experience_years']); ?>">
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">ℹ️ Additional Information</h3>
        <div style="display:grid;gap:16px;">
          <div class="form-group">
            <label>Skills (comma-separated)</label>
            <textarea name="skills" class="form-control" rows="2"><?php echo safe($emp['skills']); ?></textarea>
          </div>
          <div class="form-group">
            <label>Certifications (comma-separated)</label>
            <textarea name="certifications" class="form-control" rows="2"><?php echo safe($emp['certifications']); ?></textarea>
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?php echo safe($emp['notes']); ?></textarea>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🔐 User Account & Login Access</h3>
        
        <?php if ($linked_user): ?>
          <!-- Already Linked User -->
          <div style="background:#d4edda;border-left:4px solid #28a745;padding:16px;border-radius:6px;margin-bottom:16px;">
            <h4 style="margin:0 0 10px 0;color:#155724;">✅ User Account Linked</h4>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;font-size:14px;color:#155724;">
              <div><strong>Username:</strong> <?php echo safe($linked_user['username']); ?></div>
              <div><strong>Full Name:</strong> <?php echo safe($linked_user['full_name']); ?></div>
              <div><strong>Email:</strong> <?php echo safe($linked_user['email']); ?></div>
              <div><strong>Role:</strong> <span style="text-transform:uppercase;font-weight:600;"><?php echo safe($linked_user['role']); ?></span></div>
            </div>
          </div>
          
          <div style="display:grid;gap:16px;">
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_user_created" value="1" id="enableUserCheckbox" <?php echo $emp['is_user_created'] ? 'checked' : ''; ?> style="width:18px;height:18px;">
                <span style="font-weight:600;">Keep User Account Enabled for Login</span>
              </label>
              <small style="color:#6c757d;display:block;margin-top:4px;">Uncheck to disable login access for this employee</small>
            </div>

            <div id="userSelectSection" style="display:<?php echo $emp['is_user_created'] ? 'block' : 'none'; ?>;">
              <div class="form-group">
                <label>Change Linked User Account</label>
                <select name="user_id" id="userSelect" class="form-control">
                  <option value="">-- Keep Current User --</option>
                  <?php foreach ($available_users as $user): ?>
                    <?php 
                    $is_current = ($emp['user_id'] == $user['id']);
                    $is_linked_elsewhere = ($user['is_linked'] > 0 && !$is_current);
                    ?>
                    <option value="<?php echo $user['id']; ?>" 
                            <?php echo $is_current ? 'selected' : ''; ?>
                            <?php echo $is_linked_elsewhere ? 'disabled' : ''; ?>>
                      <?php echo safe($user['username']); ?> 
                      - <?php echo safe($user['full_name']); ?> 
                      (<?php echo safe($user['role']); ?>)
                      <?php if ($is_current): ?>
                        [CURRENT]
                      <?php elseif ($is_linked_elsewhere): ?>
                        [Already Linked]
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small style="color:#6c757d;display:block;margin-top:4px;">
                  Search and select a different user account to link. Users already linked to other employees are disabled.
                </small>
              </div>
            </div>
          </div>

        <?php else: ?>
          <!-- No User Linked Yet -->
          <div style="background:#fff3cd;border-left:4px solid #faa718;padding:16px;border-radius:6px;margin-bottom:16px;">
            <h4 style="margin:0 0 8px 0;color:#856404;">⚠️ No User Account Linked</h4>
            <p style="margin:0;color:#856404;font-size:14px;">This employee doesn't have login access. Enable user account to grant system access.</p>
          </div>

          <div style="display:grid;gap:16px;">
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_user_created" value="1" id="enableUserCheckbox" <?php echo $emp['is_user_created'] ? 'checked' : ''; ?> style="width:18px;height:18px;">
                <span style="font-weight:600;">Enable User Account for Login</span>
              </label>
              <small style="color:#6c757d;display:block;margin-top:4px;">Check this box to grant login access to this employee</small>
            </div>

            <div id="userSelectSection" style="display:<?php echo $emp['is_user_created'] ? 'block' : 'none'; ?>;">
              <div class="form-group">
                <label>Search & Select User Account <span style="color:#dc3545;">*</span></label>
                <input type="text" id="userSearch" class="form-control" placeholder="Type to search by username or name..." style="margin-bottom:8px;">
                <select name="user_id" id="userSelect" class="form-control" size="8">
                  <option value="">-- Select a User Account --</option>
                  <?php foreach ($available_users as $user): ?>
                    <?php $is_linked = ($user['is_linked'] > 0); ?>
                    <option value="<?php echo $user['id']; ?>" 
                            data-username="<?php echo strtolower($user['username']); ?>"
                            data-fullname="<?php echo strtolower($user['full_name']); ?>"
                            data-email="<?php echo strtolower($user['email']); ?>"
                            <?php echo $is_linked ? 'disabled' : ''; ?>>
                      <?php echo safe($user['username']); ?> 
                      - <?php echo safe($user['full_name']); ?> 
                      (<?php echo safe($user['role']); ?>)
                      <?php if ($is_linked): ?>
                        [Already Linked to Another Employee]
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small style="color:#6c757d;display:block;margin-top:4px;">
                  Select a user account to link with this employee. Only unlinked users are available.
                </small>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <div style="background:#e7f3ff;border-left:4px solid #0066cc;padding:12px;border-radius:6px;margin-top:16px;">
          <strong style="color:#0066cc;">ℹ️ Note:</strong>
          <ul style="margin:8px 0 0 20px;color:#004085;font-size:13px;line-height:1.6;">
            <li>Linking a user account allows the employee to log in to the system</li>
            <li>Each user account can only be linked to one employee at a time</li>
            <li>If you need to create a new user account, go to Settings → User Management (if available)</li>
            <li>Changing the linked user will immediately update login access</li>
          </ul>
        </div>
      </div>

      <div style="text-align:center;padding:16px 0;">
        <button type="submit" class="btn" style="padding:12px 40px;">✅ Save Changes</button>
        <a href="view_employee.php?id=<?php echo $id; ?>" class="btn btn-accent" style="padding:12px 40px;margin-left:10px;text-decoration:none;">❌ Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  function calculateGross() {
    const fields = ['basic_salary', 'hra', 'conveyance_allowance', 'medical_allowance', 'special_allowance'];
    let total = 0;
    fields.forEach(function(name) {
      const input = document.querySelector('[name="' + name + '"]');
      if (!input) return;
      const value = parseFloat(input.value);
      if (!isNaN(value)) {
        total += value;
      }
    });
    const display = document.getElementById('gross_display');
    if (display) {
      display.value = '₹ ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
  }

  document.querySelectorAll('.gross-field').forEach(function(input) {
    input.addEventListener('input', calculateGross);
  });

  calculateGross();

  // User account section toggle
  const enableUserCheckbox = document.getElementById('enableUserCheckbox');
  const userSelectSection = document.getElementById('userSelectSection');
  
  if (enableUserCheckbox && userSelectSection) {
    enableUserCheckbox.addEventListener('change', function() {
      if (this.checked) {
        userSelectSection.style.display = 'block';
      } else {
        userSelectSection.style.display = 'none';
      }
    });
  }

  // User search functionality
  const userSearch = document.getElementById('userSearch');
  const userSelect = document.getElementById('userSelect');
  
  if (userSearch && userSelect) {
    userSearch.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase().trim();
      const options = userSelect.options;
      
      for (let i = 0; i < options.length; i++) {
        const option = options[i];
        if (i === 0) continue; // Skip the first "Select" option
        
        const username = option.getAttribute('data-username') || '';
        const fullname = option.getAttribute('data-fullname') || '';
        const email = option.getAttribute('data-email') || '';
        
        if (searchTerm === '' || 
            username.includes(searchTerm) || 
            fullname.includes(searchTerm) || 
            email.includes(searchTerm)) {
          option.style.display = '';
        } else {
          option.style.display = 'none';
        }
      }
    });
  }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
