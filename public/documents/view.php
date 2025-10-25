<?php
/**
 * Document Vault - Detailed view.
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
$doc_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($doc_id <= 0) {
		flash_add('error', 'Document not found.', 'documents');
		header('Location: index.php');
		exit;
}

$page_title = 'Document Details - ' . APP_NAME;

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

$current_employee_id = documents_current_employee_id($conn, (int) $_SESSION['user_id']);
$allowed_visibilities = documents_allowed_visibilities($user_role);

$base_conditions = ['d.deleted_at IS NULL'];
$base_params = [];
$base_types = '';

if ($user_role !== 'admin') {
		$placeholders = implode(',', array_fill(0, count($allowed_visibilities), '?'));
		$clause = 'd.visibility IN (' . $placeholders . ')';
		foreach ($allowed_visibilities as $visibility) {
				$base_params[] = $visibility;
				$base_types .= 's';
		}
		if ($current_employee_id) {
				$clause = '(' . $clause . ' OR d.uploaded_by = ? OR d.employee_id = ?)';
				$base_params[] = $current_employee_id;
				$base_types .= 'i';
				$base_params[] = $current_employee_id;
				$base_types .= 'i';
		}
		$base_conditions[] = $clause;
}

$detail_conditions = $base_conditions;
$detail_params = $base_params;
$detail_types = $base_types;
$detail_conditions[] = 'd.id = ?';
$detail_params[] = $doc_id;
$detail_types .= 'i';

function documents_fetch_detail(mysqli $conn, array $conditions, string $types, array $params): ?array
{
		$where = implode(' AND ', $conditions);
		$sql = "SELECT d.*, 
									 uploader.employee_code AS uploader_code, uploader.first_name AS uploader_first, uploader.last_name AS uploader_last,
									 subject.employee_code AS subject_code, subject.first_name AS subject_first, subject.last_name AS subject_last
						FROM documents d
						LEFT JOIN employees uploader ON uploader.id = d.uploaded_by
						LEFT JOIN employees subject ON subject.id = d.employee_id
						WHERE $where
						LIMIT 1";
		$stmt = mysqli_prepare($conn, $sql);
		if (!$stmt) {
				return null;
		}
		if ($types !== '') {
				documents_stmt_bind($stmt, $types, $params);
		}
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		$document = $result ? mysqli_fetch_assoc($result) : null;
		if ($result) {
				mysqli_free_result($result);
		}
		mysqli_stmt_close($stmt);
		return $document ?: null;
}

$document = documents_fetch_detail($conn, $detail_conditions, $detail_types, $detail_params);
if (!$document) {
		closeConnection($conn);
		flash_add('error', 'Document not accessible or no longer available.', 'documents');
		header('Location: index.php');
		exit;
}

$can_edit = in_array($user_role, ['admin', 'manager'], true);
$can_archive = $user_role === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$action = $_POST['action'] ?? '';

		if ($action === 'update_meta' && $can_edit) {
				$new_doc_type = trim($_POST['doc_type'] ?? '');
				$new_tags = trim($_POST['tags'] ?? '');
				$new_visibility = trim($_POST['visibility'] ?? $document['visibility']);
				$new_employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;

				if ($user_role !== 'admin' && $new_visibility === 'admin') {
					flash_add('error', 'Only administrators can mark documents as admin-only.', 'documents');
				} elseif (!in_array($new_visibility, ($user_role === 'admin' ? ['employee', 'manager', 'admin'] : $allowed_visibilities), true)) {
					flash_add('error', 'Invalid visibility selected.', 'documents');
				} else {
						$employee_value = $new_employee_id > 0 ? $new_employee_id : null;
						$doc_type_value = $new_doc_type !== '' ? $new_doc_type : null;
						$tags_value = $new_tags !== '' ? $new_tags : null;

						$update_sql = 'UPDATE documents SET doc_type = ?, employee_id = ?, tags = ?, visibility = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
						$stmt = mysqli_prepare($conn, $update_sql);
						if ($stmt) {
								mysqli_stmt_bind_param($stmt, 'sissi', $doc_type_value, $employee_value, $tags_value, $new_visibility, $doc_id);
								if (mysqli_stmt_execute($stmt)) {
							flash_add('success', 'Document details updated.', 'documents');
								} else {
							flash_add('error', 'Failed to update document: ' . mysqli_stmt_error($stmt), 'documents');
								}
								mysqli_stmt_close($stmt);
						} else {
						flash_add('error', 'Could not prepare update statement.', 'documents');
						}
				}
				header('Location: view.php?id=' . $doc_id);
				exit;
		}

		if ($action === 'archive' && $can_archive) {
				$archive_sql = 'UPDATE documents SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL';
				$stmt = mysqli_prepare($conn, $archive_sql);
				if ($stmt) {
						mysqli_stmt_bind_param($stmt, 'i', $doc_id);
						if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
						flash_add('success', 'Document archived successfully.', 'documents');
						} else {
						flash_add('error', 'Unable to archive document.', 'documents');
						}
						mysqli_stmt_close($stmt);
				} else {
					flash_add('error', 'Could not prepare archive statement.', 'documents');
				}
				header('Location: index.php');
				exit;
		}
}

// Refetch after possible updates for display.
$document = documents_fetch_detail($conn, $detail_conditions, $detail_types, $detail_params);

$employees = documents_fetch_employees($conn);

closeConnection($conn);

$file_relative = ltrim($document['file_path'] ?? '', '/');
$file_path = __DIR__ . '/../../' . $file_relative;
$file_exists = $file_relative !== '' && is_file($file_path);
$file_url = APP_URL . '/' . $file_relative;
$tags = documents_parse_tags($document['tags'] ?? '');
$uploaded_on = $document['created_at'] ? date('d M Y, h:i A', strtotime($document['created_at'])) : '—';
$updated_on = $document['updated_at'] ? date('d M Y, h:i A', strtotime($document['updated_at'])) : '—';
$visibility_choices = $user_role === 'admin' ? ['employee', 'manager', 'admin'] : $allowed_visibilities;
?>
<div class="main-wrapper">
	<div class="main-content">
		<div class="page-header">
			<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
				<div>
					<h1>📄 <?php echo htmlspecialchars($document['title'], ENT_QUOTES); ?></h1>
					<p>Document ID: #<?php echo (int) $document['id']; ?></p>
				</div>
						<div style="display:flex;gap:10px;flex-wrap:wrap;">
							<a href="index.php" class="btn btn-secondary">← Back to list</a>
							<?php if ($can_edit): ?>
								<a href="edit.php?id=<?php echo (int) $document['id']; ?>" class="btn" style="background:#003581;color:#fff;">Edit</a>
							<?php endif; ?>
							<?php if ($file_exists): ?>
								<a href="<?php echo htmlspecialchars($file_url, ENT_QUOTES); ?>" target="_blank" rel="noopener" class="btn" style="background:#17a2b8;color:#fff;">Download</a>
							<?php endif; ?>
							<?php if ($can_archive): ?>
								<form method="POST" onsubmit="return confirm('Archive this document?');">
									<input type="hidden" name="action" value="archive">
									<button type="submit" class="btn" style="background:#dc3545;color:#fff;">Archive</button>
								</form>
							<?php endif; ?>
						</div>
			</div>
		</div>

		<?php echo flash_render(); ?>

		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-bottom:24px;">
			<div class="card">
				<h3 style="margin-top:0;color:#003581;">Metadata</h3>
				<div style="display:grid;grid-template-columns:150px 1fr;gap:10px 20px;align-items:start;">
					<div style="font-weight:600;color:#495057;">Type</div>
					<div><?php echo $document['doc_type'] ? htmlspecialchars($document['doc_type'], ENT_QUOTES) : '—'; ?></div>

					<div style="font-weight:600;color:#495057;">Visibility</div>
					<div><?php echo documents_visibility_badge($document['visibility']); ?></div>

					<div style="font-weight:600;color:#495057;">Linked employee</div>
					<div><?php echo documents_format_employee($document['subject_code'] ?? null, $document['subject_first'] ?? null, $document['subject_last'] ?? null); ?></div>

					<div style="font-weight:600;color:#495057;">Project ID</div>
					<div><?php echo !empty($document['project_id']) ? htmlspecialchars((string)$document['project_id'], ENT_QUOTES) : '—'; ?></div>

					<div style="font-weight:600;color:#495057;">Uploaded by</div>
					<div><?php echo documents_format_employee($document['uploader_code'] ?? null, $document['uploader_first'] ?? null, $document['uploader_last'] ?? null); ?></div>

					<div style="font-weight:600;color:#495057;">Uploaded on</div>
					<div><?php echo $uploaded_on; ?></div>

					<div style="font-weight:600;color:#495057;">Last updated</div>
					<div><?php echo $updated_on; ?></div>

					<div style="font-weight:600;color:#495057;">Storage path</div>
					<div>
						<?php echo htmlspecialchars($document['file_path'], ENT_QUOTES); ?>
						<?php if (!$file_exists): ?>
							<div style="color:#dc3545;font-size:12px;margin-top:4px;">⚠️ File missing from disk.</div>
						<?php endif; ?>
					</div>

					<?php if (!empty($tags)): ?>
						<div style="font-weight:600;color:#495057;">Tags</div>
						<div style="display:flex;flex-wrap:wrap;gap:6px;">
							<?php foreach ($tags as $tag): ?>
								<span style="padding:6px 10px;border-radius:12px;background:#f1f5f9;color:#374151;font-size:12px;">#<?php echo htmlspecialchars($tag, ENT_QUOTES); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card">
				<h3 style="margin-top:0;color:#003581;">File preview</h3>
				<?php if ($file_exists && in_array(strtolower(pathinfo($file_relative, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif'], true)): ?>
					<img src="<?php echo htmlspecialchars($file_url, ENT_QUOTES); ?>" alt="Document preview" style="max-width:100%;border-radius:8px;">
				<?php else: ?>
					<div style="padding:20px;border:1px dashed #ced4da;border-radius:8px;text-align:center;color:#6c757d;">
						<p style="margin-bottom:8px;">Preview not available.</p>
						<?php if ($file_exists): ?>
							<p style="margin:0;">Download the file to view its contents.</p>
						<?php else: ?>
							<p style="margin:0;color:#dc3545;">File missing. Upload a new version.</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Edit form removed from view page. Use Edit button to modify document in edit.php -->
	</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
