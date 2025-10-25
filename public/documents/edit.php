<?php
/**
 * Document Vault - Edit document (all fields)
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
$page_title = 'Edit Document - ' . APP_NAME;

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

if (!documents_table_exists($conn)) {
    closeConnection($conn);
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$doc_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($doc_id <= 0) {
  flash_add('error', 'Invalid document id.', 'documents');
    header('Location: index.php');
    exit;
}

$current_employee_id = documents_current_employee_id($conn, (int) $_SESSION['user_id']);

// fetch document
$sql = 'SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL LIMIT 1';
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    closeConnection($conn);
  flash_add('error', 'Unable to prepare statement.', 'documents');
    header('Location: index.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $doc_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$document = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$document) {
    closeConnection($conn);
  flash_add('error', 'Document not found.', 'documents');
    header('Location: index.php');
    exit;
}

$is_uploader = $current_employee_id && ((int) $document['uploaded_by'] === (int) $current_employee_id);
$can_edit = in_array($user_role, ['admin', 'manager'], true) || $is_uploader;
if (!$can_edit) {
    closeConnection($conn);
  flash_add('error', 'You do not have permission to edit this document.', 'documents');
    header('Location: view.php?id=' . $doc_id);
    exit;
}

$employees = documents_fetch_employees($conn);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $doc_type = trim($_POST['doc_type'] ?? '');
    $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    $tags = trim($_POST['tags'] ?? '');
    $visibility = trim($_POST['visibility'] ?? $document['visibility']);
    // admin only: change uploaded_by
    $uploaded_by = $document['uploaded_by'];
    if ($user_role === 'admin') {
        $uploaded_by = isset($_POST['uploaded_by']) ? (int) $_POST['uploaded_by'] : $uploaded_by;
    }

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if (!in_array($visibility, ['admin', 'manager', 'employee'], true)) {
        $errors[] = 'Invalid visibility selected.';
    }

    // file replacement
    $new_file_path = null;
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file_error = $_FILES['document_file']['error'];
        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error (code ' . $file_error . ').';
        } else {
            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
            $original_name = $_FILES['document_file']['name'];
            $file_tmp = $_FILES['document_file']['tmp_name'];
            $file_size = (int) $_FILES['document_file']['size'];
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowed_extensions, true)) {
                $errors[] = 'Unsupported file type. Allowed: ' . implode(', ', $allowed_extensions) . '.';
            }
            $max_size_bytes = 10 * 1024 * 1024;
            if ($file_size > $max_size_bytes) {
                $errors[] = 'File exceeds 10 MB size limit.';
            }

            if (empty($errors)) {
                $upload_dir = __DIR__ . '/../../uploads/documents/';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                    $errors[] = 'Unable to create uploads directory.';
                } else {
                    $safe_title = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($title));
                    $safe_title = trim($safe_title, '-') ?: 'document';
                    $unique_suffix = bin2hex(random_bytes(4));
                    $file_name = date('YmdHis') . '_' . $safe_title . '_' . $unique_suffix . '.' . $extension;
                    $dest = $upload_dir . $file_name;
                    if (move_uploaded_file($file_tmp, $dest)) {
                        $new_file_path = 'uploads/documents/' . $file_name;
                    } else {
                        $errors[] = 'Failed to store uploaded file.';
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        // prepare update
        $update_sql = 'UPDATE documents SET title = ?, doc_type = ?, employee_id = ?, project_id = ?, tags = ?, visibility = ?, uploaded_by = ?';
        $params = [];
        $types = '';
        $params[] = $title; $types .= 's';
        $params[] = $doc_type !== '' ? $doc_type : null; $types .= 's';
        $params[] = $employee_id > 0 ? $employee_id : null; $types .= 'i';
        $params[] = $project_id > 0 ? $project_id : null; $types .= 'i';
        $params[] = $tags !== '' ? $tags : null; $types .= 's';
        $params[] = $visibility; $types .= 's';
        $params[] = $uploaded_by; $types .= 'i';

        if ($new_file_path !== null) {
            $update_sql .= ', file_path = ?';
            $params[] = $new_file_path; $types .= 's';
        }

        $update_sql .= ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        $params[] = $doc_id; $types .= 'i';

        $stmt = mysqli_prepare($conn, $update_sql);
        if ($stmt) {
            // bind params
            $bind = [$types];
            foreach ($params as $i => &$p) { $bind[] = &$params[$i]; }
            array_unshift($bind, $stmt);
            call_user_func_array('mysqli_stmt_bind_param', $bind);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                // delete old file if replaced
                if ($new_file_path !== null && !empty($document['file_path'])) {
                    $old = __DIR__ . '/../../' . ltrim($document['file_path'], '/');
                    if (is_file($old)) { @unlink($old); }
                }
        flash_add('success', 'Document updated successfully.', 'documents');
                closeConnection($conn);
                header('Location: view.php?id=' . $doc_id);
                exit;
            } else {
                $errors[] = 'Database error: ' . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $errors[] = 'Unable to prepare update statement.';
        }
    }

    // if new file was uploaded but we had errors later, remove the new file to avoid orphan
    if (!empty($new_file_path)) {
        $maybe = __DIR__ . '/../../' . ltrim($new_file_path, '/');
        if (is_file($maybe)) { @unlink($maybe); }
    }
}

closeConnection($conn);
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
          <a href="view.php?id=<?php echo (int)$doc_id; ?>" class="btn btn-accent">← Back</a>
        </div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul style="margin:0;padding-left:20px;">
        <?php foreach ($errors as $e): ?>
          <li><?php echo htmlspecialchars($e); ?></li>
        <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card" style="max-width:820px;">
      <form method="POST" enctype="multipart/form-data" style="display:grid;gap:16px;">
        <div class="form-group">
          <label for="title">Title</label>
          <input id="title" name="title" class="form-control" maxlength="100" required value="<?php echo htmlspecialchars($document['title'] ?? '', ENT_QUOTES); ?>">
        </div>

        <div class="form-group">
          <label for="doc_type">Document type</label>
          <input id="doc_type" name="doc_type" class="form-control" maxlength="50" value="<?php echo htmlspecialchars($document['doc_type'] ?? '', ENT_QUOTES); ?>">
        </div>

        <div class="form-group">
          <label for="employee_id">Linked employee</label>
          <select id="employee_id" name="employee_id" class="form-control">
            <option value="0">Not linked</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo (int)$emp['id']; ?>" <?php echo ((int)$document['employee_id'] === (int)$emp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="project_id">Project ID</label>
          <input id="project_id" name="project_id" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($document['project_id'] ?? '', ENT_QUOTES); ?>">
          <small style="color:#6c757d;">Optional numeric project id (if applicable)</small>
        </div>

        <div class="form-group">
          <label for="tags">Tags</label>
          <input id="tags" name="tags" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($document['tags'] ?? '', ENT_QUOTES); ?>">
        </div>

        <div class="form-group">
          <label for="visibility">Visibility</label>
          <select id="visibility" name="visibility" class="form-control">
            <?php $choices = ['employee','manager','admin']; foreach ($choices as $c): ?>
              <option value="<?php echo $c; ?>" <?php echo ($document['visibility'] === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars(documents_visibility_label($c)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($user_role === 'admin'): ?>
          <div class="form-group">
            <label for="uploaded_by">Uploaded by (admin only)</label>
            <select id="uploaded_by" name="uploaded_by" class="form-control">
              <?php foreach ($employees as $emp): ?>
                <option value="<?php echo (int)$emp['id']; ?>" <?php echo ((int)$document['uploaded_by'] === (int)$emp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' - ' . trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="document_file">Replace file</label>
          <input id="document_file" name="document_file" type="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt">
          <small style="color:#6c757d;">Leave empty to keep current file. Max 10 MB.</small>
        </div>

        <div>
          <button type="submit" class="btn">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
