<?php
/**
 * Employee Profile View (Scoped under /public/employee)
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Employee Profile - " . APP_NAME;

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$not_found = false;

$conn = createConnection(true);

$emp = null;
if ($id > 0) {
    $sql = "SELECT e.*, 
            (SELECT CONCAT(m.first_name, ' ', m.last_name, ' (', m.employee_code, ')') 
             FROM employees m WHERE m.id = e.reporting_manager_id) AS manager_name
            FROM employees e WHERE e.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $emp = mysqli_fetch_assoc($result);
    } else {
        $not_found = true;
    }
    mysqli_stmt_close($stmt);
} else {
    $not_found = true;
}

closeConnection($conn);

function safeValue($value, $fallback = '—') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return htmlspecialchars((string)$value);
}

function safeDate($value, $fallback = '—') {
    if (!$value || $value === '0000-00-00') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return htmlspecialchars($value);
    }
    return date('d M Y', $timestamp);
}

function formatCurrencyValue($value, $fallback = '—') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return '₹ ' . number_format((float)$value, 2);
}

function boolDisplay($value) {
    if ($value === null) {
        return '—';
    }
    return (int)$value === 1 ? 'Yes' : 'No';
}

function documentChip($label, $path) {
    if (!$path) {
        return '<span style="background:#e9ecef;color:#6c757d;padding:6px 12px;border-radius:12px;font-size:12px;">' . htmlspecialchars($label) . ': Not uploaded</span>';
    }
    $safe_path = htmlspecialchars($path);
    return '<a href="../../' . $safe_path . '" target="_blank" style="background:#e3f2fd;color:#003581;padding:6px 12px;border-radius:12px;font-size:12px;text-decoration:none;">' . htmlspecialchars($label) . ' ⤓</a>';
}

function renderDocumentList($label, $jsonPaths) {
    if (!$jsonPaths) {
        return '<div><strong>' . htmlspecialchars($label) . ':</strong> —</div>';
    }
    $decoded = json_decode($jsonPaths, true);
    if (!is_array($decoded) || count($decoded) === 0) {
        return '<div><strong>' . htmlspecialchars($label) . ':</strong> —</div>';
    }
    $items = [];
    foreach ($decoded as $idx => $path) {
        $safePath = htmlspecialchars($path);
        $items[] = '<a href="../../' . $safePath . '" target="_blank">Document ' . ($idx + 1) . '</a>';
    }
    return '<div><strong>' . htmlspecialchars($label) . ':</strong> ' . implode(' | ', $items) . '</div>';
}
?>

<style>
  .card-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>👤 Employee Profile</h1>
          <p>Comprehensive employee information and documents</p>
        </div>
        <div>
          <a href="index.php" class="btn btn-accent">← Back to List</a>
          <?php if (!$not_found): ?>
          <a href="edit_employee.php?id=<?php echo $id; ?>" class="btn" style="margin-left:8px;">✏️ Edit</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($not_found): ?>
      <div class="alert alert-error">Employee not found.</div>
    <?php else: ?>
      <div class="card" style="display:flex;gap:20px;align-items:center;">
        <?php
          $photo_exists = $emp['photo_path'] && file_exists(__DIR__ . '/../../' . $emp['photo_path']);
          if ($photo_exists): ?>
          <img src="<?php echo '../../' . htmlspecialchars($emp['photo_path']); ?>" alt="Photo" style="width:84px;height:84px;border-radius:50%;object-fit:cover;">
        <?php else: ?>
          <div style="width:84px;height:84px;border-radius:50%;background:#003581;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:28px;">
            <?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?>
          </div>
        <?php endif; ?>
        <div style="flex:1;">
          <div style="font-size:20px;color:#003581;font-weight:700;">
            <?php echo safeValue($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']); ?>
            <span style="font-size:12px;color:#6c757d;font-weight:500;margin-left:8px;">(<?php echo safeValue($emp['employee_code']); ?>)</span>
          </div>
          <div style="color:#6c757d;font-size:13px;">
            Designation: <?php echo safeValue($emp['designation']); ?> • Department: <?php echo safeValue($emp['department']); ?>
          </div>
          <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
            <span style="background:#e3f2fd;color:#003581;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Status: <?php echo safeValue($emp['status']); ?></span>
            <?php if (!empty($emp['manager_name'])): ?>
              <span style="background:#fff3cd;color:#856404;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Manager: <?php echo safeValue($emp['manager_name']); ?></span>
            <?php endif; ?>
            <span style="background:#f8f9fa;color:#495057;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">Employee Type: <?php echo safeValue($emp['employee_type']); ?></span>
          </div>
        </div>
      </div>

      <div class="card-container" style="margin-top:20px;">
        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📇 Personal & Contact</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;">
            <div><strong>First Name:</strong> <?php echo safeValue($emp['first_name']); ?></div>
            <div><strong>Last Name:</strong> <?php echo safeValue($emp['last_name']); ?></div>
            <div><strong>Middle Name:</strong> <?php echo safeValue($emp['middle_name']); ?></div>
            <div><strong>Date of Birth:</strong> <?php echo safeDate($emp['date_of_birth']); ?></div>
            <div><strong>Gender:</strong> <?php echo safeValue($emp['gender']); ?></div>
            <div><strong>Blood Group:</strong> <?php echo safeValue($emp['blood_group']); ?></div>
            <div><strong>Marital Status:</strong> <?php echo safeValue($emp['marital_status']); ?></div>
            <div><strong>Nationality:</strong> <?php echo safeValue($emp['nationality']); ?></div>
            <div><strong>Official Email:</strong> <?php echo safeValue($emp['official_email']); ?></div>
            <div><strong>Personal Email:</strong> <?php echo safeValue($emp['personal_email']); ?></div>
            <div><strong>Mobile:</strong> <?php echo safeValue($emp['mobile_number']); ?></div>
            <div><strong>Alternate Mobile:</strong> <?php echo safeValue($emp['alternate_mobile']); ?></div>
            <div><strong>Emergency Contact:</strong> <?php echo safeValue($emp['emergency_contact_name']); ?></div>
            <div><strong>Emergency Number:</strong> <?php echo safeValue($emp['emergency_contact_number']); ?></div>
            <div style="grid-column:1/-1;"><strong>Emergency Relation:</strong> <?php echo safeValue($emp['emergency_contact_relation']); ?></div>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">💼 Employment</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;">
            <div><strong>Employee Code:</strong> <?php echo safeValue($emp['employee_code']); ?></div>
            <div><strong>Status:</strong> <?php echo safeValue($emp['status']); ?></div>
            <div><strong>Department:</strong> <?php echo safeValue($emp['department']); ?></div>
            <div><strong>Designation:</strong> <?php echo safeValue($emp['designation']); ?></div>
            <div><strong>Employee Type:</strong> <?php echo safeValue($emp['employee_type']); ?></div>
            <div><strong>Date of Joining:</strong> <?php echo safeDate($emp['date_of_joining']); ?></div>
            <div><strong>Date of Leaving:</strong> <?php echo safeDate($emp['date_of_leaving']); ?></div>
            <div><strong>Probation Period (days):</strong> <?php echo safeValue($emp['probation_period']); ?></div>
            <div><strong>Confirmation Date:</strong> <?php echo safeDate($emp['confirmation_date']); ?></div>
            <div><strong>Reporting Manager:</strong> <?php echo safeValue($emp['manager_name']); ?></div>
            <div><strong>Work Location:</strong> <?php echo safeValue($emp['work_location']); ?></div>
            <div><strong>Shift Timing:</strong> <?php echo safeValue($emp['shift_timing']); ?></div>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🏠 Address</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;font-size:14px;">
            <div>
              <strong>Current Address</strong>
              <div style="margin-top:6px;">
                <?php echo nl2br(safeValue($emp['current_address'])); ?><br>
                <?php echo safeValue(trim(($emp['current_city'] ? $emp['current_city'] . ', ' : '') . ($emp['current_state'] ? $emp['current_state'] . ' ' : '') . ($emp['current_pincode'] ?? ''))); ?>
              </div>
            </div>
            <div>
              <strong>Permanent Address</strong>
              <div style="margin-top:6px;">
                <?php echo nl2br(safeValue($emp['permanent_address'])); ?><br>
                <?php echo safeValue(trim(($emp['permanent_city'] ? $emp['permanent_city'] . ', ' : '') . ($emp['permanent_state'] ? $emp['permanent_state'] . ' ' : '') . ($emp['permanent_pincode'] ?? ''))); ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">💰 Compensation</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;">
            <div><strong>Salary Type:</strong> <?php echo safeValue($emp['salary_type']); ?></div>
            <div><strong>Basic Salary:</strong> <?php echo formatCurrencyValue($emp['basic_salary']); ?></div>
            <div><strong>HRA:</strong> <?php echo formatCurrencyValue($emp['hra']); ?></div>
            <div><strong>Conveyance:</strong> <?php echo formatCurrencyValue($emp['conveyance_allowance']); ?></div>
            <div><strong>Medical Allowance:</strong> <?php echo formatCurrencyValue($emp['medical_allowance']); ?></div>
            <div><strong>Special Allowance:</strong> <?php echo formatCurrencyValue($emp['special_allowance']); ?></div>
            <div style="grid-column:1/-1;"><strong>Gross Salary:</strong> <?php echo formatCurrencyValue($emp['gross_salary']); ?></div>
            <div><strong>PF Number:</strong> <?php echo safeValue($emp['pf_number']); ?></div>
            <div><strong>ESI Number:</strong> <?php echo safeValue($emp['esi_number']); ?></div>
            <div><strong>UAN Number:</strong> <?php echo safeValue($emp['uan_number']); ?></div>
            <div><strong>PAN Number:</strong> <?php echo safeValue($emp['pan_number']); ?></div>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🏦 Bank Details</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;">
            <div><strong>Bank Name:</strong> <?php echo safeValue($emp['bank_name']); ?></div>
            <div><strong>Account Number:</strong> <?php echo safeValue($emp['bank_account_number']); ?></div>
            <div><strong>IFSC Code:</strong> <?php echo safeValue($emp['bank_ifsc_code']); ?></div>
            <div><strong>Branch:</strong> <?php echo safeValue($emp['bank_branch']); ?></div>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🪪 Identification</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;">
            <div><strong>Aadhar Number:</strong> <?php echo safeValue($emp['aadhar_number']); ?></div>
            <div><strong>Passport Number:</strong> <?php echo safeValue($emp['passport_number']); ?></div>
            <div><strong>Driving License:</strong> <?php echo safeValue($emp['driving_license']); ?></div>
            <div><strong>Voter ID:</strong> <?php echo safeValue($emp['voter_id']); ?></div>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📎 Documents</h3>
          <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:14px;">
            <?php
              echo documentChip('Photo', $emp['photo_path']);
              echo documentChip('Resume', $emp['resume_path']);
              echo documentChip('Aadhar Document', $emp['aadhar_document_path']);
              echo documentChip('PAN Document', $emp['pan_document_path']);
            ?>
          </div>
          <div style="margin-top:16px;font-size:14px;display:grid;gap:8px;">
            <?php
              echo renderDocumentList('Education Documents', $emp['education_documents_path']);
              echo renderDocumentList('Experience Documents', $emp['experience_documents_path']);
            ?>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🎓 Education & Experience</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;">
            <div><strong>Highest Qualification:</strong> <?php echo safeValue($emp['highest_qualification']); ?></div>
            <div><strong>Specialization:</strong> <?php echo safeValue($emp['specialization']); ?></div>
            <div><strong>University:</strong> <?php echo safeValue($emp['university']); ?></div>
            <div><strong>Year of Passing:</strong> <?php echo safeValue($emp['year_of_passing']); ?></div>
            <div><strong>Previous Company:</strong> <?php echo safeValue($emp['previous_company']); ?></div>
            <div><strong>Previous Designation:</strong> <?php echo safeValue($emp['previous_designation']); ?></div>
            <div><strong>Previous Experience (Years):</strong> <?php echo safeValue($emp['previous_experience_years']); ?></div>
            <div><strong>Total Experience (Years):</strong> <?php echo safeValue($emp['total_experience_years']); ?></div>
          </div>
        </div>

        <div class="card">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">ℹ️ Additional Information</h3>
          <div style="display:grid;gap:12px;font-size:14px;">
            <div><strong>Skills:</strong><br><?php echo nl2br(safeValue($emp['skills'])); ?></div>
            <div><strong>Certifications:</strong><br><?php echo nl2br(safeValue($emp['certifications'])); ?></div>
            <div><strong>Notes:</strong><br><?php echo nl2br(safeValue($emp['notes'])); ?></div>
          </div>
        </div>

        <div class="card" style="grid-column:1/-1;">
          <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🗂️ System Information</h3>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;">
            <div><strong>Created At:</strong> <?php echo safeDate($emp['created_at']); ?></div>
            <div><strong>Updated At:</strong> <?php echo safeDate($emp['updated_at']); ?></div>
            <div><strong>Created By:</strong> <?php echo safeValue($emp['created_by']); ?></div>
            <div><strong>Updated By:</strong> <?php echo safeValue($emp['updated_by']); ?></div>
            <div><strong>User Account Created:</strong> <?php echo boolDisplay($emp['is_user_created']); ?></div>
            <div><strong>User ID:</strong> <?php echo safeValue($emp['user_id']); ?></div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
