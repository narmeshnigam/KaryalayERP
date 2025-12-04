<?php
/**
 * Document Vault - Listing and overview page.
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

if (!authz_user_can_any($conn, [
    ['table' => 'documents', 'permission' => 'view_all'],
    ['table' => 'documents', 'permission' => 'view_assigned'],
    ['table' => 'documents', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'documents', 'view_all');
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
$can_create_document = !empty($document_permissions['can_create']);
$can_edit_all = !empty($document_permissions['can_edit_all']);
$can_edit_own = !empty($document_permissions['can_edit_own']);
$can_edit_assigned = !empty($document_permissions['can_edit_assigned']);

$current_employee_id = documents_current_employee_id($conn, (int) $CURRENT_USER_ID);
$accessible_visibilities = documents_allowed_visibilities_for_permissions($document_permissions);
$visibility_filter_options = (!empty($document_permissions['can_view_all']) || $IS_SUPER_ADMIN)
    ? ['employee', 'manager', 'admin']
    : $accessible_visibilities;

$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
$doc_type_filter = isset($_GET['doc_type']) ? trim($_GET['doc_type']) : '';
$visibility_filter = isset($_GET['visibility']) ? trim($_GET['visibility']) : '';
$assigned_employee = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$tag_filter = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$scope = isset($_GET['scope']) ? trim($_GET['scope']) : '';

$base_conditions = ['d.deleted_at IS NULL'];
$base_params = [];
$base_types = '';

if (!$IS_SUPER_ADMIN && empty($document_permissions['can_view_all'])) {
    $placeholders = implode(',', array_fill(0, count($accessible_visibilities), '?'));
    $clause = 'd.visibility IN (' . $placeholders . ')';
    foreach ($accessible_visibilities as $visibility) {
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

$list_conditions = $base_conditions;
$list_params = $base_params;
$list_types = $base_types;

if ($doc_type_filter !== '') {
    $list_conditions[] = 'd.doc_type = ?';
    $list_params[] = $doc_type_filter;
    $list_types .= 's';
}

if ($visibility_filter !== '') {
    $can_filter_visibility = $IS_SUPER_ADMIN || !empty($document_permissions['can_view_all']) || in_array($visibility_filter, $visibility_filter_options, true);
    if ($can_filter_visibility) {
        $list_conditions[] = 'd.visibility = ?';
        $list_params[] = $visibility_filter;
        $list_types .= 's';
    }
}

if ($assigned_employee > 0) {
    $list_conditions[] = 'd.employee_id = ?';
    $list_params[] = $assigned_employee;
    $list_types .= 'i';
}

if ($tag_filter !== '') {
    $list_conditions[] = 'd.tags LIKE ?';
    $like_tag = '%' . $tag_filter . '%';
    $list_params[] = $like_tag;
    $list_types .= 's';
}

if ($scope === 'mine' && $current_employee_id) {
    $list_conditions[] = '(d.uploaded_by = ? OR d.employee_id = ?)';
    $list_params[] = $current_employee_id;
    $list_types .= 'i';
    $list_params[] = $current_employee_id;
    $list_types .= 'i';
}

if ($from_date !== '') {
    $list_conditions[] = 'DATE(d.created_at) >= ?';
    $list_params[] = $from_date;
    $list_types .= 's';
}

if ($to_date !== '') {
    $list_conditions[] = 'DATE(d.created_at) <= ?';
    $list_params[] = $to_date;
    $list_types .= 's';
}

if ($search_term !== '') {
    $list_conditions[] = '(d.title LIKE ? OR d.tags LIKE ? OR d.doc_type LIKE ?)';
    $like_search = '%' . $search_term . '%';
    $list_params[] = $like_search;
    $list_types .= 's';
    $list_params[] = $like_search;
    $list_types .= 's';
    $list_params[] = $like_search;
    $list_types .= 's';
}

$where_clause = implode(' AND ', $list_conditions);
$documents = [];

$docs_sql = "SELECT d.id, d.title, d.file_path, d.doc_type, d.tags, d.visibility, d.created_at, d.updated_at,
                     d.employee_id, d.uploaded_by,
                     uploader.employee_code AS uploader_code, uploader.first_name AS uploader_first, uploader.last_name AS uploader_last,
                     subject.employee_code AS subject_code, subject.first_name AS subject_first, subject.last_name AS subject_last
              FROM documents d
              LEFT JOIN employees uploader ON uploader.id = d.uploaded_by
              LEFT JOIN employees subject ON subject.id = d.employee_id
              WHERE $where_clause
              ORDER BY d.created_at DESC
              LIMIT 200";

$stmt = mysqli_prepare($conn, $docs_sql);
if ($stmt) {
    if ($list_types !== '') {
        documents_stmt_bind($stmt, $list_types, $list_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $documents[] = $row;
        }
        mysqli_free_result($result);
    }
    mysqli_stmt_close($stmt);
}

$stats_conditions = $base_conditions;
$stats_params = $base_params;
$stats_types = $base_types;
$stats_where = implode(' AND ', $stats_conditions);

$total_docs = 0;
$recent_docs = 0;
$my_docs = 0;

$count_sql = 'SELECT COUNT(*) AS total FROM documents d WHERE ' . $stats_where;
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($count_stmt) {
    if ($stats_types !== '') {
        documents_stmt_bind($count_stmt, $stats_types, $stats_params);
    }
    mysqli_stmt_execute($count_stmt);
    $res = mysqli_stmt_get_result($count_stmt);
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $total_docs = (int) $row['total'];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($count_stmt);
}

$recent_conditions = $stats_conditions;
$recent_params = $stats_params;
$recent_types = $stats_types;
$recent_conditions[] = 'd.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
$recent_where = implode(' AND ', $recent_conditions);
$recent_sql = 'SELECT COUNT(*) AS total FROM documents d WHERE ' . $recent_where;
$recent_stmt = mysqli_prepare($conn, $recent_sql);
if ($recent_stmt) {
    if ($recent_types !== '') {
        documents_stmt_bind($recent_stmt, $recent_types, $recent_params);
    }
    mysqli_stmt_execute($recent_stmt);
    $res = mysqli_stmt_get_result($recent_stmt);
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $recent_docs = (int) $row['total'];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($recent_stmt);
}

if ($current_employee_id) {
    $my_conditions = $stats_conditions;
    $my_conditions[] = '(d.uploaded_by = ? OR d.employee_id = ?)';
    $my_params = $stats_params;
    $my_params[] = $current_employee_id;
    $my_params[] = $current_employee_id;
    $my_types = $stats_types . 'ii';
    $my_where = implode(' AND ', $my_conditions);
    $my_sql = 'SELECT COUNT(*) AS total FROM documents d WHERE ' . $my_where;
    $my_stmt = mysqli_prepare($conn, $my_sql);
    if ($my_stmt) {
        documents_stmt_bind($my_stmt, $my_types, $my_params);
        mysqli_stmt_execute($my_stmt);
        $res = mysqli_stmt_get_result($my_stmt);
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            if ($row) {
                $my_docs = (int) $row['total'];
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($my_stmt);
    }
}

$type_conditions = $base_conditions;
$type_params = $base_params;
$type_types = $base_types;
$type_conditions[] = 'd.doc_type IS NOT NULL';
$type_conditions[] = "TRIM(d.doc_type) <> ''";
$type_where = implode(' AND ', $type_conditions);
$doc_type_options = [];

$types_sql = 'SELECT DISTINCT d.doc_type FROM documents d WHERE ' . $type_where . ' ORDER BY d.doc_type ASC';
$type_stmt = mysqli_prepare($conn, $types_sql);
if ($type_stmt) {
    if ($type_types !== '') {
        documents_stmt_bind($type_stmt, $type_types, $type_params);
    }
    mysqli_stmt_execute($type_stmt);
    $res = mysqli_stmt_get_result($type_stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $doc_type_options[] = $row['doc_type'];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($type_stmt);
}

$employees = documents_fetch_employees($conn);

$page_title = 'Document Vault - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.docs-header-flex{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;}
.docs-header-buttons{display:flex;gap:10px;flex-wrap:wrap;}

@media (max-width:768px){
.docs-header-flex{flex-direction:column;align-items:stretch;}
.docs-header-buttons{width:100%;flex-direction:column;gap:10px;}
.docs-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.docs-header-flex h1{font-size:1.5rem;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="docs-header-flex">
        <div>
          <h1>📁 Document Vault</h1>
          <p>Store and share official records with visibility controls.</p>
        </div>
        <div class="docs-header-buttons">
          <?php if ($can_create_document): ?>
            <a href="upload.php" class="btn" style="text-decoration: none;">＋ Upload Document</a>
          <?php endif; ?>
          <?php if ($scope === 'mine'): ?>
            <a href="index.php?scope=mine" class="btn btn-accent" style="text-decoration: none;">📄 My Workspace</a>
          <?php else: ?>
            <a href="index.php?scope=mine" class="btn btn-accent" style="text-decoration: none;">📄 My Workspace</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:25px;">
      <div class="card" style="background:linear-gradient(135deg,#003581 0%,#004aad 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:5px;"><?php echo $total_docs; ?></div>
        <div style="font-size:14px;opacity:0.9;">Total Documents</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#faa718 0%,#ffc04d 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:5px;"><?php echo $recent_docs; ?></div>
        <div style="font-size:14px;opacity:0.9;">Recent (30 days)</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,#28a745 0%,#34ce57 100%);color:#fff;text-align:center;">
        <div style="font-size:32px;font-weight:700;margin-bottom:5px;"><?php echo $my_docs; ?></div>
        <div style="font-size:14px;opacity:0.9;">My Documents</div>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-top:0;color:#003581;">Filter documents</h3>
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
        <div class="form-group" style="margin:0;">
          <label for="q">Search</label>
          <input type="text" id="q" name="q" class="form-control" placeholder="Title, tags or type" value="<?php echo htmlspecialchars($search_term, ENT_QUOTES); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="doc_type">Document type</label>
          <select id="doc_type" name="doc_type" class="form-control">
            <option value="">All types</option>
            <?php foreach ($doc_type_options as $type_option): ?>
              <option value="<?php echo htmlspecialchars($type_option, ENT_QUOTES); ?>" <?php echo $doc_type_filter === $type_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($type_option); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="visibility">Visibility</label>
          <select id="visibility" name="visibility" class="form-control">
            <option value="">Any visibility</option>
            <?php foreach ($visibility_filter_options as $visibility_option): ?>
              <option value="<?php echo htmlspecialchars($visibility_option, ENT_QUOTES); ?>" <?php echo $visibility_filter === $visibility_option ? 'selected' : ''; ?>><?php echo htmlspecialchars(documents_visibility_label($visibility_option)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="employee_id">Linked employee</label>
          <select id="employee_id" name="employee_id" class="form-control">
            <option value="0">Any</option>
            <?php foreach ($employees as $employee): ?>
              <?php $selected = $assigned_employee === (int) $employee['id'] ? 'selected' : ''; ?>
              <option value="<?php echo (int) $employee['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(($employee['employee_code'] ?? '') . ' - ' . trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="tag">Tag</label>
          <input type="text" id="tag" name="tag" class="form-control" placeholder="e.g. policy" value="<?php echo htmlspecialchars($tag_filter, ENT_QUOTES); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="from_date">From</label>
          <input type="date" id="from_date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES); ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="to_date">To</label>
          <input type="date" id="to_date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES); ?>">
        </div>
        <div>
          <button type="submit" class="btn" style="width:100%;">Apply Filters</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;color:#003581;">📋 Documents <span style="font-size:14px;color:#6c757d;font-weight:normal;">(<?php echo count($documents); ?> of <?php echo $total_docs; ?>)</span></h3>
        <div>
          <a href="index.php" class="btn btn-accent" style="padding:6px 14px;font-size:13px;text-decoration:none;">Clear Filters</a>
        </div>
      </div>

      <?php if (empty($documents)): ?>
        <div class="alert alert-info" style="margin:0;">No documents found for the selected filters.</div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Title</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Type</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Linked employee</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Uploaded by</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Visibility</th>
                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Added on</th>
                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($documents as $document): ?>
                <?php
                $tags = documents_parse_tags($document['tags'] ?? '');
                $file_relative = $document['file_path'] ?? '';
                $file_relative = ltrim($file_relative, '/');
                $download_url = htmlspecialchars(APP_URL . '/' . $file_relative, ENT_QUOTES);
                $created_display = $document['created_at'] ? date('d M Y, h:i A', strtotime($document['created_at'])) : '—';
                $is_uploader = $current_employee_id && ((int) ($document['uploaded_by'] ?? 0) === (int) $current_employee_id);
                $is_assigned = $current_employee_id && ((int) ($document['employee_id'] ?? 0) === (int) $current_employee_id);
                $can_edit_this_doc = $IS_SUPER_ADMIN
                    || $can_edit_all
                    || ($can_edit_own && $is_uploader)
                    || ($can_edit_assigned && $is_assigned);
                ?>
                <tr style="border-bottom:1px solid #e1e8ed;">
                  <td style="padding:12px;max-width:260px;">
                    <div style="font-weight:600;color:#1b2a57;"><?php echo htmlspecialchars($document['title'], ENT_QUOTES); ?></div>
                    <?php if (!empty($tags)): ?>
                      <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
                        <?php foreach ($tags as $tag): ?>
                          <span style="padding:2px 8px;border-radius:10px;background:#edf2f7;color:#4a5568;font-size:11px;">#<?php echo htmlspecialchars($tag, ENT_QUOTES); ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px;color:#6c757d;">
                    <?php echo $document['doc_type'] ? htmlspecialchars($document['doc_type'], ENT_QUOTES) : '—'; ?>
                  </td>
                  <td style="padding:12px;">
                    <?php echo documents_format_employee($document['subject_code'] ?? null, $document['subject_first'] ?? null, $document['subject_last'] ?? null); ?>
                  </td>
                  <td style="padding:12px;">
                    <?php echo documents_format_employee($document['uploader_code'] ?? null, $document['uploader_first'] ?? null, $document['uploader_last'] ?? null); ?>
                  </td>
                  <td style="padding:12px;white-space:nowrap;">
                    <?php echo documents_visibility_badge($document['visibility']); ?>
                  </td>
                  <td style="padding:12px;white-space:nowrap;">
                    <?php echo $created_display; ?>
                  </td>
                  <td style="padding:12px;text-align:center;white-space:nowrap;">
                    <?php if ($can_edit_this_doc): ?>
                      <a href="edit.php?id=<?php echo (int) $document['id']; ?>" class="btn btn-accent" style="padding:6px 12px;font-size:12px;text-decoration:none;">✏️ Edit</a>
                    <?php endif; ?>
                    <a href="view.php?id=<?php echo (int) $document['id']; ?>" class="btn" style="padding:6px 12px;font-size:12px;text-decoration:none;">👁️ View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$closeManagedConnection();
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
