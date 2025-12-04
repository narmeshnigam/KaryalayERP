<?php
/**
 * Projects Module - Manage Documents
 * Upload, versioning, activate/deactivate, and download links. Permissions ignored per instruction.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

if (!projects_tables_exist($conn)) {
  header('Location: ' . APP_URL . '/scripts/setup_projects_tables.php');
    exit;
}

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}

$project = get_project_by_id($conn, $project_id);
if (!$project) {
    $_SESSION['flash_message'] = 'Project not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

$errors = [];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload') {
        $doc_type = trim($_POST['doc_type'] ?? '') ?: null;
        if (empty($_FILES['file']['name'])) {
            $errors[] = 'Please choose a file to upload.';
        } else {
            $res = upload_project_document($conn, $project_id, $_FILES['file'], $doc_type, $_SESSION['user_id']);
            if (!empty($res['ok'])) {
                $_SESSION['flash_message'] = 'Document uploaded successfully.';
                $_SESSION['flash_type'] = 'success';
                header('Location: documents.php?project_id=' . $project_id . build_query_suffix());
                exit;
            } else {
                $errors = $res['errors'] ?? ['Upload failed.'];
            }
        }
    } elseif ($action === 'deactivate') {
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        if (deactivate_project_document($conn, $doc_id, $_SESSION['user_id'])) {
            $_SESSION['flash_message'] = 'Document deactivated.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to deactivate document.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: documents.php?project_id=' . $project_id . build_query_suffix());
        exit;
    } elseif ($action === 'activate') {
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        if (activate_project_document($conn, $doc_id, $_SESSION['user_id'])) {
            $_SESSION['flash_message'] = 'Document activated.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to activate document.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: documents.php?project_id=' . $project_id . build_query_suffix());
        exit;
    }
}

// Filters
$filters = [
    'status' => $_GET['status'] ?? 'active',
    'type' => $_GET['type'] ?? '',
    'search' => $_GET['search'] ?? ''
];

function build_query_suffix(): string {
    $parts = [];
    foreach (['status','type','search'] as $k) {
        if (!empty($_GET[$k])) { $parts[] = $k . '=' . urlencode((string)$_GET[$k]); }
    }
    return $parts ? ('&' . implode('&', $parts)) : '';
}

$documents = get_documents($conn, $project_id, $filters);
$doc_types = get_document_types($conn, $project_id);

$page_title = 'Documents - ' . $project['title'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.projects-documents-header-flex{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.projects-documents-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}
.projects-documents-filter-form{display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:12px;align-items:end;}
.projects-documents-upload-form{display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end;}
.projects-documents-error-alert{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:16px;border-radius:6px;margin-bottom:16px;}
.projects-documents-table-wrapper{overflow-x:auto;}

@media (max-width:1024px){
.projects-documents-filter-form{grid-template-columns:1fr 1fr;gap:12px;}
.projects-documents-upload-form{grid-template-columns:1fr 1fr;gap:12px;}
}

@media (max-width:768px){
.projects-documents-header-flex{flex-direction:column;align-items:stretch;}
.projects-documents-header-buttons{width:100%;flex-direction:column;gap:10px;}
.projects-documents-header-buttons .btn{width:100%;text-align:center;}
.projects-documents-filter-form{grid-template-columns:1fr;font-size:13px;}
.projects-documents-filter-form .btn{width:100%;}
.projects-documents-upload-form{grid-template-columns:1fr;font-size:13px;}
.projects-documents-upload-form .btn{width:100%;}
.projects-documents-error-alert{padding:12px;font-size:13px;}
}

@media (max-width:600px){
.projects-documents-table-wrapper table{display:block;width:100%;}
.projects-documents-table-wrapper thead{display:none;}
.projects-documents-table-wrapper tbody tr{display:block;margin-bottom:20px;border:1px solid #ddd;border-radius:6px;overflow:hidden;}
.projects-documents-table-wrapper tbody td{display:block;width:100%;padding:12px;border-top:1px solid #f0f0f0;text-align:left;position:relative;padding-left:40%;}
.projects-documents-table-wrapper tbody td:first-child{border-top:none;padding-left:12px;}
.projects-documents-table-wrapper tbody td::before{content:attr(data-label);position:absolute;left:12px;top:12px;font-weight:600;color:#003581;width:35%;word-wrap:break-word;}
.projects-documents-table-wrapper tbody td:first-child::before{content:'';}
}

@media (max-width:480px){
.projects-documents-header-flex h1{font-size:1.5rem;}
.projects-documents-filter-form input,.projects-documents-filter-form select{font-size:16px;}
.projects-documents-upload-form input,.projects-documents-upload-form select{font-size:16px;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="projects-documents-header-flex">
        <div>
          <h1 style="margin:0 0 8px 0;">📎 Project Documents</h1>
          <div style="color:#6c757d;">Manage documents for <strong><?= htmlspecialchars($project['title']) ?></strong> <span style="color:#6c757d;font-family:monospace;">#<?= htmlspecialchars($project['project_code']) ?></span></div>
        </div>
        <div class="projects-documents-header-buttons">
          <a href="tasks.php?project_id=<?= $project_id ?>" class="btn btn-secondary">✅ Manage Tasks</a>
          <a href="phases.php?project_id=<?= $project_id ?>" class="btn btn-secondary">📋 Manage Phases</a>
          <a href="members.php?project_id=<?= $project_id ?>" class="btn btn-secondary">👥 Manage Members</a>
          <a href="view.php?id=<?= $project_id ?>&tab=documents" class="btn btn-secondary">← Back to Project</a>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

    <?php if (!empty($errors)): ?>
      <div class="projects-documents-error-alert">
        <strong>Fix the following:</strong>
        <ul style="margin:8px 0 0 20px;">
          <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
      <form method="get" class="projects-documents-filter-form">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Status</label>
          <select name="status" class="form-control">
            <option value="">All</option>
            <option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Type</label>
          <select name="type" class="form-control">
            <option value="">All</option>
            <?php foreach ($doc_types as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>" <?= $filters['type']===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Search</label>
          <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="File name or path">
        </div>
        <div>
          <button class="btn btn-primary">Filter</button>
        </div>
      </form>
    </div>

    <!-- Upload -->
    <div class="card" style="margin-bottom:24px;">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">⬆️ Upload Document</h3>
      <form method="post" enctype="multipart/form-data" class="projects-documents-upload-form">
        <input type="hidden" name="action" value="upload">
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Choose File *</label>
          <input type="file" name="file" class="form-control" required>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Type (optional)</label>
          <input type="text" name="doc_type" class="form-control" placeholder="e.g., Specification, Design">
        </div>
        <div>
          <button class="btn btn-primary">Upload</button>
        </div>
      </form>
      <div style="font-size:12px;color:#6c757d;margin-top:6px;">Uploading a file with the same name creates a new version and deactivates older versions.</div>
    </div>

    <!-- Documents List -->
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">Documents</h3>
      <?php if ($documents): ?>
        <div class="projects-documents-table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Document</th>
              <th>Type</th>
              <th>Version</th>
              <th>Status</th>
              <th>Uploaded By</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($documents as $d): ?>
              <tr>
                <td data-label="Document">
                  <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:22px;">📄</span>
                    <div>
                      <div style="font-weight:600;color:#1b2a57;"><?= htmlspecialchars($d['file_name']) ?></div>
                      <div style="font-size:12px;color:#6c757d;">path: <span style="font-family:monospace;"><?= htmlspecialchars($d['file_path']) ?></span></div>
                    </div>
                  </div>
                </td>
                <td data-label="Type"><?= htmlspecialchars((string)$d['doc_type']) ?></td>
                <td data-label="Version">v<?= (int)$d['version'] ?></td>
                <td data-label="Status">
                  <span class="badge" style="background:<?= $d['is_active']?'#28a745':'#6c757d' ?>;">
                    <?= $d['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td data-label="Uploaded By"><?= htmlspecialchars($d['uploaded_by_name']) ?></td>
                <td data-label="Date"><?= date('M j, Y g:i A', strtotime($d['uploaded_at'])) ?></td>
                <td data-label="Actions" style="white-space:nowrap;">
                  <a href="<?= htmlspecialchars($d['file_path']) ?>" class="btn btn-sm btn-primary" download>⬇️ Download</a>
                  <?php if ($d['is_active']): ?>
                    <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('Deactivate this document?');">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-sm btn-warning">Deactivate</button>
                    </form>
                  <?php else: ?>
                    <form method="post" style="display:inline-block;margin-left:6px;">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-sm btn-success">Activate</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:#6c757d;">
          <div style="font-size:40px;">📎</div>
          <div style="margin-top:8px;">No documents match current filters.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); }
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
