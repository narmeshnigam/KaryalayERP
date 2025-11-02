<?php
/**
 * Document Vault - Edit document (all fields)
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../config/module_dependencies.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/helpers.php';

$closeManagedConnection = static function () use (&$conn): void {
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED']) && $conn instanceof mysqli) {
        closeConnection($conn);
        $GLOBALS['AUTHZ_CONN_MANAGED'] = false;
    }
};

if (!authz_user_can_any($conn, [
    ['table' => 'documents', 'permission' => 'edit_all'],
    ['table' => 'documents', 'permission' => 'edit_assigned'],
    ['table' => 'documents', 'permission' => 'edit_own'],
])) {
    authz_require_permission($conn, 'documents', 'edit_all');
}

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

$document_permissions = authz_get_permission_set($conn, 'documents');
$current_employee_id = documents_current_employee_id($conn, (int) $CURRENT_USER_ID);

$doc_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($doc_id <= 0) {
    flash_add('error', 'Invalid document reference.', 'documents');
    $closeManagedConnection();
    header('Location: index.php');
    exit;
}

$fetch_stmt = mysqli_prepare($conn, 'SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL LIMIT 1');
if (!$fetch_stmt) {
    $closeManagedConnection();
    flash_add('error', 'Unable to look up the document.', 'documents');
    header('Location: index.php');
    exit;
}
mysqli_stmt_bind_param($fetch_stmt, 'i', $doc_id);
mysqli_stmt_execute($fetch_stmt);
$fetch_result = mysqli_stmt_get_result($fetch_stmt);
$document = $fetch_result ? mysqli_fetch_assoc($fetch_result) : null;
mysqli_stmt_close($fetch_stmt);

if (!$document) {
    flash_add('error', 'Document not found.', 'documents');
    $closeManagedConnection();
    header('Location: index.php');
    exit;
}

$is_uploader = $current_employee_id && ((int) $document['uploaded_by'] === (int) $current_employee_id);
$is_assigned = $current_employee_id && ((int) $document['employee_id'] === (int) $current_employee_id);
$can_edit = $IS_SUPER_ADMIN
    || !empty($document_permissions['can_edit_all'])
    || (!empty($document_permissions['can_edit_own']) && $is_uploader)
    || (!empty($document_permissions['can_edit_assigned']) && $is_assigned);

if (!$can_edit) {
    flash_add('error', 'You do not have permission to edit this document.', 'documents');
    $closeManagedConnection();
    header('Location: view.php?id=' . $doc_id);
    exit;
}

$employees = documents_fetch_employees($conn);
$visibility_options = (!empty($document_permissions['can_view_all']) || $IS_SUPER_ADMIN)
    ? ['employee', 'manager', 'admin']
    : documents_allowed_visibilities_for_permissions($document_permissions);

$form_data = [
    'title' => $document['title'] ?? '',
    'doc_type' => $document['doc_type'] ?? '',
    'employee_id' => $document['employee_id'] !== null ? (string) $document['employee_id'] : '0',
    'project_id' => $document['project_id'] !== null ? (string) $document['project_id'] : '',
    'tags' => $document['tags'] ?? '',
    'visibility' => $document['visibility'] ?? 'employee',
    'uploaded_by' => (string) ($document['uploaded_by'] ?? ''),
];

$errors = [];
$new_file_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['title'] = trim($_POST['title'] ?? '');
    $form_data['doc_type'] = trim($_POST['doc_type'] ?? '');
    $form_data['employee_id'] = isset($_POST['employee_id']) ? (string) (int) $_POST['employee_id'] : '0';
    $form_data['project_id'] = isset($_POST['project_id']) ? trim((string) $_POST['project_id']) : '';
    $form_data['tags'] = trim($_POST['tags'] ?? '');
    $form_data['visibility'] = trim($_POST['visibility'] ?? $form_data['visibility']);

    $uploaded_by_value = (int) $document['uploaded_by'];
    if ($IS_SUPER_ADMIN) {
        $uploaded_by_value = isset($_POST['uploaded_by']) ? (int) $_POST['uploaded_by'] : $uploaded_by_value;
        $form_data['uploaded_by'] = (string) $uploaded_by_value;
    }

    if ($form_data['title'] === '') {
        $errors[] = 'Title is required.';
    }

    $allowed_visibilities = (!empty($document_permissions['can_view_all']) || $IS_SUPER_ADMIN)
        ? ['employee', 'manager', 'admin']
        : documents_allowed_visibilities_for_permissions($document_permissions);
    if (!in_array($form_data['visibility'], $allowed_visibilities, true)) {
        $errors[] = 'Invalid visibility selected.';
    }

    $has_replacement_file = isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($has_replacement_file && empty($errors)) {
        $filename_seed = $form_data['title'] !== '' ? $form_data['title'] : ($document['title'] ?? 'document');
        $new_file_path = documents_store_uploaded_file($_FILES['document_file'], $filename_seed, $errors);
    }

    if (empty($errors)) {
        $employee_value = (int) $form_data['employee_id'];
        $employee_value = $employee_value > 0 ? $employee_value : null;
        $project_value = $form_data['project_id'] !== '' ? (int) $form_data['project_id'] : 0;
        $project_value = $project_value > 0 ? $project_value : null;
        $doc_type_value = $form_data['doc_type'] !== '' ? $form_data['doc_type'] : null;
        $tags_value = $form_data['tags'] !== '' ? $form_data['tags'] : null;

        $update_sql = 'UPDATE documents SET title = ?, doc_type = ?, employee_id = ?, project_id = ?, tags = ?, visibility = ?, uploaded_by = ?';
        $update_params = [
            $form_data['title'],
            $doc_type_value,
            $employee_value,
            $project_value,
            $tags_value,
            $form_data['visibility'],
            $uploaded_by_value,
        ];
        $update_type_parts = ['s', 's', 'i', 'i', 's', 's', 'i'];

        if ($new_file_path !== null) {
            $update_sql .= ', file_path = ?';
            $update_params[] = $new_file_path;
            $update_type_parts[] = 's';
        }

        $update_sql .= ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        $update_params[] = $doc_id;
        $update_type_parts[] = 'i';

        $update_stmt = mysqli_prepare($conn, $update_sql);
        if ($update_stmt) {
            $update_types = implode('', $update_type_parts);
            documents_stmt_bind($update_stmt, $update_types, $update_params);
            if (mysqli_stmt_execute($update_stmt)) {
                mysqli_stmt_close($update_stmt);
                if ($new_file_path !== null && !empty($document['file_path'])) {
                    documents_delete_file($document['file_path']);
                }
                flash_add('success', 'Document updated successfully.', 'documents');
                $closeManagedConnection();
                header('Location: view.php?id=' . $doc_id);
                exit;
            }
            $errors[] = 'Unable to save the document. Please try again.';
            mysqli_stmt_close($update_stmt);
        } else {
            $errors[] = 'Unable to prepare update statement.';
        }
    }

    if (!empty($errors) && $new_file_path) {
        documents_delete_file($new_file_path);
        $new_file_path = null;
    }
}

$page_title = 'Edit Document - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h1>✏️ Edit Document</h1>
          <p>Modify metadata or replace the file. Changes are audited in updated_at.</p>
        </div>
        <div>
          <a href="view.php?id=<?php echo (int) $doc_id; ?>" class="btn btn-accent" style="text-decoration: none;">← Back</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

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
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="title">Title <span style="color: #dc3545;">*</span></label>
            <input id="title" name="title" class="form-control" maxlength="100" required value="<?php echo htmlspecialchars($form_data['title'], ENT_QUOTES); ?>">
          </div>

          <div class="form-group">
            <label for="doc_type">Document Type</label>
            <input id="doc_type" name="doc_type" class="form-control" maxlength="50" value="<?php echo htmlspecialchars($form_data['doc_type'], ENT_QUOTES); ?>">
          </div>

          <div class="form-group">
            <label for="visibility">Visibility <span style="color: #dc3545;">*</span></label>
            <select id="visibility" name="visibility" class="form-control" required>
              <?php foreach ($visibility_options as $option): ?>
                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>" <?php echo $form_data['visibility'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars(documents_visibility_label($option)); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="tags">Tags</label>
            <input id="tags" name="tags" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($form_data['tags'], ENT_QUOTES); ?>">
            <small style="color: #6c757d;">Comma separated tags for categorization</small>
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
              <?php foreach ($employees as $emp): ?>
                <?php $selected = $form_data['employee_id'] === (string) (int) $emp['id'] ? 'selected' : ''; ?>
                <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
              <?php endforeach; ?>
            </select>
            <small style="color: #6c757d;">Optional: Link to an employee record</small>
          </div>

          <div class="form-group">
            <label for="project_id">Project ID</label>
            <input id="project_id" name="project_id" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($form_data['project_id'], ENT_QUOTES); ?>">
            <small style="color: #6c757d;">Optional: Numeric project ID</small>
          </div>

          <?php if ($IS_SUPER_ADMIN): ?>
            <div class="form-group">
              <label for="uploaded_by">Uploaded by <span style="color: #999;">(admin only)</span></label>
              <select id="uploaded_by" name="uploaded_by" class="form-control">
                <?php foreach ($employees as $emp): ?>
                  <?php $selectedUploader = $form_data['uploaded_by'] === (string) (int) $emp['id'] ? 'selected' : ''; ?>
                  <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selectedUploader; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- File Management -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📎 File Management
        </h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
          <div class="form-group">
            <label for="document_file">Replace File</label>
            <input id="document_file" name="document_file" type="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt">
            <small style="color: #6c757d;">Leave empty to keep current file. Maximum size 10 MB. Supported: PDF, Office docs, images, text.</small>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #e9ecef;">
        <a href="view.php?id=<?php echo (int) $doc_id; ?>" class="btn btn-accent" style="text-decoration: none;">← Cancel</a>
        <button type="submit" class="btn">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
