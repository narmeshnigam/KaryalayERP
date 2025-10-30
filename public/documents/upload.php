<?php
/**
 * Document Vault - Upload new document.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'documents', 'create');
$document_permissions = authz_get_permission_set($conn, 'documents');

$page_title = 'Upload Document - ' . APP_NAME;

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = $conn ?? createConnection(true);
if (!$conn) {
		echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
		require_once __DIR__ . '/../../includes/footer_sidebar.php';
		exit;
}

if (!documents_table_exists($conn)) {
		if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
				closeConnection($conn);
		}
		require_once __DIR__ . '/onboarding.php';
		exit;
}

$current_employee_id = documents_current_employee_id($conn, (int) $CURRENT_USER_ID);
$allowed_visibilities = documents_allowed_visibilities_for_permissions($document_permissions);
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

	if (!in_array($form_data['visibility'], array_merge($allowed_visibilities, ['admin']), true)) {
			$errors[] = 'Invalid visibility option selected.';
	}

	if (!$document_permissions['can_view_all'] && $form_data['visibility'] === 'admin') {
			$errors[] = 'Only administrators can mark documents as admin-only.';
	}		if (!$current_employee_id) {
				$errors[] = 'Your user profile is not linked to an employee record. Contact administrator to fix this before uploading.';
		}

		if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
				$errors[] = 'Please choose a file to upload.';
		}

		$uploaded_file_path = null;
		if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
				$file_error = $_FILES['document_file']['error'];
				if ($file_error !== UPLOAD_ERR_OK) {
						$errors[] = 'Error while uploading the file (code ' . $file_error . ').';
				} else {
						$allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
						$original_name = $_FILES['document_file']['name'];
						$file_tmp = $_FILES['document_file']['tmp_name'];
						$file_size = (int) $_FILES['document_file']['size'];
						$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

						if (!in_array($extension, $allowed_extensions, true)) {
								$errors[] = 'Unsupported file type. Allowed: ' . implode(', ', $allowed_extensions) . '.';
						}

						$max_size_bytes = 10 * 1024 * 1024; // 10 MB limit.
						if ($file_size > $max_size_bytes) {
								$errors[] = 'File size exceeds the 10 MB limit.';
						}

						if (!$errors) {
								$upload_dir = __DIR__ . '/../../uploads/documents/';
								if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
										$errors[] = 'Unable to create uploads directory.';
								} else {
										$safe_title = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($form_data['title']));
										$safe_title = trim($safe_title, '-') ?: 'document';
										$unique_suffix = bin2hex(random_bytes(4));
										$file_name = date('YmdHis') . '_' . $safe_title . '_' . $unique_suffix . '.' . $extension;
										$destination = $upload_dir . $file_name;

										if (move_uploaded_file($file_tmp, $destination)) {
												$uploaded_file_path = 'uploads/documents/' . $file_name;
										} else {
												$errors[] = 'Failed to move uploaded file.';
										}
								}
						}
				}
		}

		if (!$errors && $uploaded_file_path) {
			$title = $form_data['title'];
			$doc_type_value = $form_data['doc_type'] !== '' ? $form_data['doc_type'] : null;
			$employee_value = (int) $form_data['employee_id'];
			$employee_value = $employee_value > 0 ? $employee_value : null;
			$project_value = (int) $form_data['project_id'];
			$project_value = $project_value > 0 ? $project_value : null;
			$tags_value = $form_data['tags'] !== '' ? $form_data['tags'] : null;
			$visibility_value = $form_data['visibility'];
			$uploaded_by_value = $current_employee_id;

			$insert_sql = 'INSERT INTO documents (title, file_path, doc_type, employee_id, project_id, tags, uploaded_by, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
			$stmt = mysqli_prepare($conn, $insert_sql);
			if ($stmt) {
				$params = [$title, $uploaded_file_path, $doc_type_value, $employee_value, $project_value, $tags_value, $uploaded_by_value, $visibility_value];
				$types = '';
				// build types: title(s), file_path(s), doc_type(s), employee_id(i), project_id(i), tags(s), uploaded_by(i), visibility(s)
				$types .= 's'; $types .= 's'; $types .= 's'; $types .= 'i'; $types .= 'i'; $types .= 's'; $types .= 'i'; $types .= 's';
				documents_stmt_bind($stmt, $types, $params);
				$executed = mysqli_stmt_execute($stmt);
				if ($executed) {
					mysqli_stmt_close($stmt);
					if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
						closeConnection($conn);
					}
					flash_add('success', 'Document uploaded successfully.', 'documents');
					header('Location: index.php');
					exit;
				}
				$errors[] = 'Database error: ' . mysqli_stmt_error($stmt);
				mysqli_stmt_close($stmt);
			} else {
				$errors[] = 'Unable to prepare database statement.';
			}
		}

		if (!$uploaded_file_path && isset($destination) && is_file($destination)) {
				unlink($destination);
		}
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
	closeConnection($conn);
}
?>
<div class="main-wrapper">
	<div class="main-content">
		<div class="page-header">
			<div style="display:flex;justify-content:space-between;align-items:center;">
				<div>
					<h1>📤 Upload Document</h1>
					<p>Add a file to the central vault with proper tagging.</p>
				</div>
				<div>
					<a href="index.php" class="btn btn-accent">← Back to vault</a>
				</div>
			</div>
		</div>

		<?php echo flash_render(); ?>

		<?php if (!$current_employee_id): ?>
			<div class="alert alert-error">Your user account is not linked to an employee record. Please ask an administrator to connect it before uploading documents.</div>
		<?php endif; ?>

		<?php if (!empty($errors)): ?>
			<div class="alert alert-error">
				<ul style="margin:0;padding-left:20px;">
					<?php foreach ($errors as $error): ?>
						<li><?php echo htmlspecialchars($error); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="card" style="max-width:720px;">
			<form method="POST" enctype="multipart/form-data" style="display:grid;gap:18px;">
				<div class="form-group">
					<label for="title">Title <span style="color:#dc3545;">*</span></label>
					<input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($form_data['title']); ?>" maxlength="100" required>
				</div>

				<div class="form-group">
					<label for="doc_type">Document type</label>
					<input type="text" id="doc_type" name="doc_type" class="form-control" placeholder="e.g. Policy, Invoice" value="<?php echo htmlspecialchars($form_data['doc_type']); ?>" maxlength="50">
				</div>

				<div class="form-group">
					<label for="employee_id">Link to employee</label>
					<select id="employee_id" name="employee_id" class="form-control">
						<option value="0">Not linked</option>
						<?php foreach ($employees as $employee): ?>
							<?php $selected = $form_data['employee_id'] === (string) (int) $employee['id'] ? 'selected' : ''; ?>
							<option value="<?php echo (int) $employee['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($employee['employee_code'] ?? '') . ' - ' . trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

						<div class="form-group">
							<label for="project_id">Project ID</label>
							<input type="number" id="project_id" name="project_id" class="form-control" min="0" value="<?php echo htmlspecialchars($form_data['project_id']); ?>">
							<small style="color:#6c757d;">Optional numeric project id (if applicable)</small>
						</div>

				<div class="form-group">
					<label for="tags">Tags</label>
					<input type="text" id="tags" name="tags" class="form-control" placeholder="Comma separated tags" value="<?php echo htmlspecialchars($form_data['tags']); ?>" maxlength="255">
					<small style="color:#6c757d;">Example: policy, onboarding, finance</small>
				</div>

				<div class="form-group">
					<label for="visibility">Visibility <span style="color:#dc3545;">*</span></label>
					<select id="visibility" name="visibility" class="form-control" required>
						<?php
							$visibility_choices = $user_role === 'admin' ? ['employee', 'manager', 'admin'] : $allowed_visibilities;
						?>
						<?php foreach ($visibility_choices as $visibility_choice): ?>
							<option value="<?php echo htmlspecialchars($visibility_choice, ENT_QUOTES); ?>" <?php echo $form_data['visibility'] === $visibility_choice ? 'selected' : ''; ?>><?php echo htmlspecialchars(documents_visibility_label($visibility_choice)); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="form-group">
					<label for="document_file">File <span style="color:#dc3545;">*</span></label>
					<input type="file" id="document_file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt" required>
					<small style="color:#6c757d;">Maximum size 10 MB. Supported formats: PDF, Office documents, images, text.</small>
				</div>

				<div>
					<button type="submit" class="btn" <?php echo !$current_employee_id ? 'disabled' : ''; ?>>Upload document</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
