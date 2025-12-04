<?php
/**
 * Document Vault - Upload new document.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/helpers.php';

$closeManagedConnection = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
    }
};

authz_require_permission($conn, 'documents', 'create');
$document_permissions = authz_get_permission_set($conn, 'documents');

if (!($conn instanceof mysqli)) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$prereq_check = get_prerequisite_check_result($conn, 'documents');
if (!$prereq_check['allowed']) {
    $closeManagedConnection();
    display_prerequisite_error('documents', $prereq_check['missing_modules']);
    exit;
}

if (!documents_table_exists($conn)) {
    $closeManagedConnection();
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$current_employee_id = documents_current_employee_id($conn, (int) $CURRENT_USER_ID);
$allowed_visibilities = documents_allowed_visibilities_for_permissions($document_permissions);
$visibility_choices = (!empty($document_permissions['can_view_all']) || $IS_SUPER_ADMIN)
  ? ['employee', 'manager', 'admin']
  : $allowed_visibilities;
$employees = documents_fetch_employees($conn);

$form_data = [
    'title' => '',
    'doc_type' => '',
    'employee_id' => '0',
    'project_id' => '0',
    'tags' => '',
    'visibility' => $allowed_visibilities[0] ?? 'employee',
];

$errors = [];
$stored_file_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['title'] = trim($_POST['title'] ?? '');
    $form_data['doc_type'] = trim($_POST['doc_type'] ?? '');
    $form_data['employee_id'] = isset($_POST['employee_id']) ? (string) (int) $_POST['employee_id'] : '0';
    $form_data['project_id'] = isset($_POST['project_id']) ? (string) (int) $_POST['project_id'] : '0';
    $form_data['tags'] = trim($_POST['tags'] ?? '');
    $form_data['visibility'] = trim($_POST['visibility'] ?? ($allowed_visibilities[0] ?? 'employee'));

    if ($form_data['title'] === '') {
        $errors[] = 'Title is required.';
    }

    if (!in_array($form_data['visibility'], $visibility_choices, true)) {
        $errors[] = 'Invalid visibility option selected.';
    }

    if (!$current_employee_id) {
        $errors[] = 'Your user profile is not linked to an employee record. Please contact the administrator.';
    }

    $file_provided = isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE;
    if (!$file_provided) {
        $errors[] = 'Please choose a file to upload.';
    }

    if (empty($errors) && $file_provided) {
        $stored_file_path = documents_store_uploaded_file($_FILES['document_file'], $form_data['title'] ?: 'document', $errors);
    }

    if (empty($errors) && $stored_file_path) {
        $employee_value = (int) $form_data['employee_id'];
        $employee_value = $employee_value > 0 ? $employee_value : null;
        $project_value = (int) $form_data['project_id'];
        $project_value = $project_value > 0 ? $project_value : null;

        $insert_sql = 'INSERT INTO documents (title, file_path, doc_type, employee_id, project_id, tags, uploaded_by, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $insert_params = [
            $form_data['title'],
            $stored_file_path,
            $form_data['doc_type'] !== '' ? $form_data['doc_type'] : null,
            $employee_value,
            $project_value,
            $form_data['tags'] !== '' ? $form_data['tags'] : null,
            (int) $current_employee_id,
            $form_data['visibility'],
        ];
  $insert_types = 'sssiisis';

        $stmt = mysqli_prepare($conn, $insert_sql);
        if ($stmt) {
            documents_stmt_bind($stmt, $insert_types, $insert_params);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $closeManagedConnection();
                flash_add('success', 'Document uploaded successfully.', 'documents');
                header('Location: index.php');
                exit;
            }
            $errors[] = 'Unable to save the document. Please try again.';
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Unable to prepare database statement.';
        }
    }

    if (!empty($errors) && $stored_file_path) {
        documents_delete_file($stored_file_path);
        $stored_file_path = null;
    }
}

$page_title = 'Upload Document - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.docs-upload-header-flex{display:flex;justify-content:space-between;align-items:center;}
.docs-upload-header-buttons{display:flex;gap:10px;flex-wrap:wrap;}
.docs-upload-form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}

@media (max-width:768px){
.docs-upload-header-flex{flex-direction:column;align-items:stretch;gap:16px;}
.docs-upload-header-buttons{width:100%;flex-direction:column;gap:10px;}
.docs-upload-header-buttons .btn{width:100%;text-align:center;}
.docs-upload-form-grid{grid-template-columns:1fr;}
}

@media (max-width:480px){
.docs-upload-header-flex h1{font-size:1.5rem;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="docs-upload-header-flex">
        <div>
          <h1>📤 Upload Document</h1>
          <p>Add a file to the central vault with proper tagging</p>
        </div>
        <div class="docs-upload-header-buttons">
          <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Back to Vault</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <?php if (!$current_employee_id): ?>
      <div class="alert alert-error">Your user account is not linked to an employee record. Please ask an administrator to connect it before uploading documents.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <strong>❌ Error:</strong><br>
        <?php foreach ($errors as $error): ?>
          • <?php echo htmlspecialchars($error, ENT_QUOTES); ?><br>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <!-- Document Information -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📄 Document Information
        </h3>
        <div class="docs-upload-form-grid">
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="title">Title <span style="color: #dc3545;">*</span></label>
            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($form_data['title'], ENT_QUOTES); ?>" maxlength="100" required>
          </div>

          <div class="form-group">
            <label for="doc_type">Document Type</label>
            <input type="text" id="doc_type" name="doc_type" class="form-control" placeholder="e.g. Policy, Invoice, Contract" value="<?php echo htmlspecialchars($form_data['doc_type'], ENT_QUOTES); ?>" maxlength="50">
          </div>

          <div class="form-group">
            <label for="visibility">Visibility <span style="color: #dc3545;">*</span></label>
            <select id="visibility" name="visibility" class="form-control" required>
              <?php foreach ($visibility_choices as $visibility_choice): ?>
                <option value="<?php echo htmlspecialchars($visibility_choice, ENT_QUOTES); ?>" <?php echo $form_data['visibility'] === $visibility_choice ? 'selected' : ''; ?>><?php echo htmlspecialchars(documents_visibility_label($visibility_choice)); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="tags">Tags</label>
            <input type="text" id="tags" name="tags" class="form-control" placeholder="Comma separated tags" value="<?php echo htmlspecialchars($form_data['tags'], ENT_QUOTES); ?>" maxlength="255">
            <small style="color: #6c757d;">Example: policy, onboarding, finance</small>
          </div>
        </div>
      </div>

      <!-- Linking Information -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          🔗 Linking Information
        </h3>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
          <div class="form-group">
            <label for="employee_id">Link to Employee</label>
            <select id="employee_id" name="employee_id" class="form-control">
              <option value="0">Not linked</option>
              <?php foreach ($employees as $employee): ?>
                <?php $selected = $form_data['employee_id'] === (string) (int) $employee['id'] ? 'selected' : ''; ?>
                <option value="<?php echo (int) $employee['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($employee['employee_code'] ?? '') . ' - ' . trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?></option>
              <?php endforeach; ?>
            </select>
            <small style="color: #6c757d;">Optional: Link this document to an employee record</small>
          </div>

          <div class="form-group">
            <label for="project_id">Project ID</label>
            <input type="number" id="project_id" name="project_id" class="form-control" min="0" value="<?php echo htmlspecialchars($form_data['project_id'], ENT_QUOTES); ?>">
            <small style="color: #6c757d;">Optional: Numeric project ID (if applicable)</small>
          </div>
        </div>
      </div>

      <!-- File Upload -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📎 File Upload
        </h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
          <div class="form-group">
            <label for="document_file">File <span style="color: #dc3545;">*</span></label>
            <input type="file" id="document_file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt" required>
            <small style="color: #6c757d;">Maximum size 10 MB. Supported formats: PDF, Office documents, images, text.</small>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #e9ecef;">
        <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Cancel</a>
        <button type="submit" class="btn" <?php echo !$current_employee_id ? 'disabled' : ''; ?>>📤 Upload Document</button>
      </div>
    </form>
  </div>
</div>
<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
