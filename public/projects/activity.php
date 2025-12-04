<?php
/**
 * Projects Module - Activity Log
 * Filterable and paginated activity log for a project. Permissions ignored per instruction.
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

// Filters + pagination
$filters = [
    'activity_type' => $_GET['activity_type'] ?? '',
    'user_id' => !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null,
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// For user filter dropdown: load project members (including removed?) We'll use current members for simplicity
$members = get_project_members($conn, $project_id);

$total = count_project_activity($conn, $project_id, $filters);
$rows = get_project_activity_filtered($conn, $project_id, $filters, $per_page, $offset);
$last_page = max(1, (int)ceil($total / $per_page));

function act_icon($t) {
    return match($t) {
        'Task' => '✅',
        'Phase' => '📋',
        'Document' => '📎',
        'Status' => '🔄',
        'Member' => '👤',
        default => '📝'
    };
}

function build_query_suffix_activity(array $extra = []): string {
    $keys = ['activity_type','user_id','date_from','date_to','search'];
    $parts = [];
    foreach ($keys as $k) {
        $val = $extra[$k] ?? ($_GET[$k] ?? '');
        if ($val !== '' && $val !== null) { $parts[] = $k . '=' . urlencode((string)$val); }
    }
    return $parts ? ('&' . implode('&', $parts)) : '';
}

$page_title = 'Activity - ' . $project['title'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.projects-activity-header-flex{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.projects-activity-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}
.projects-activity-filter-form{display:grid;grid-template-columns:1fr 1fr 1fr 1fr 2fr auto;gap:12px;align-items:end;}
.projects-activity-list{display:flex;flex-direction:column;gap:8px;}
.projects-activity-item{display:flex;align-items:flex-start;gap:12px;padding:12px;border:1px solid #e9ecef;border-radius:6px;background:#fafbfc;}
.projects-activity-item-icon{font-size:20px;flex-shrink:0;}
.projects-activity-item-content{flex:1;}
.projects-activity-pagination{display:flex;justify-content:center;gap:8px;margin-top:16px;flex-wrap:wrap;}

@media (max-width:1024px){
.projects-activity-filter-form{grid-template-columns:1fr 1fr 1fr;gap:10px;}
}

@media (max-width:768px){
.projects-activity-header-flex{flex-direction:column;align-items:stretch;}
.projects-activity-header-buttons{width:100%;flex-direction:column;gap:10px;}
.projects-activity-header-buttons .btn{width:100%;text-align:center;}
.projects-activity-filter-form{grid-template-columns:1fr;gap:10px;font-size:13px;}
.projects-activity-filter-form input,.projects-activity-filter-form select{font-size:13px;}
.projects-activity-item{padding:10px;}
}

@media (max-width:600px){
.projects-activity-item{flex-direction:column;gap:8px;}
.projects-activity-item-icon{font-size:24px;}
}

@media (max-width:480px){
.projects-activity-header-flex h1{font-size:1.5rem;}
.projects-activity-filter-form input,.projects-activity-filter-form select{font-size:16px;}
.projects-activity-pagination{flex-direction:column;}
.projects-activity-pagination .btn{width:100%;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="projects-activity-header-flex">
        <div>
          <h1 style="margin:0 0 8px 0;">📝 Project Activity</h1>
          <div style="color:#6c757d;">Audit log for <strong><?= htmlspecialchars($project['title']) ?></strong> <span style="color:#6c757d;font-family:monospace;">#<?= htmlspecialchars($project['project_code']) ?></span></div>
        </div>
        <div class="projects-activity-header-buttons">
          <a href="tasks.php?project_id=<?= $project_id ?>" class="btn btn-secondary">✅ Tasks</a>
          <a href="phases.php?project_id=<?= $project_id ?>" class="btn btn-secondary">📋 Phases</a>
          <a href="documents.php?project_id=<?= $project_id ?>" class="btn btn-secondary">📎 Documents</a>
          <a href="members.php?project_id=<?= $project_id ?>" class="btn btn-secondary">👥 Members</a>
          <a href="view.php?id=<?= $project_id ?>&tab=activity" class="btn btn-secondary">← Back to Project</a>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
      <form method="get" class="projects-activity-filter-form">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Type</label>
          <select name="activity_type" class="form-control">
            <option value="">All</option>
            <?php foreach (['Task','Phase','Document','Status','Member','General'] as $t): ?>
              <option value="<?= $t ?>" <?= ($filters['activity_type']===$t)?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">User</label>
          <select name="user_id" class="form-control">
            <option value="">All</option>
            <?php foreach ($members as $m): ?>
              <option value="<?= (int)$m['user_id'] ?>" <?= (!empty($filters['user_id']) && (int)$filters['user_id']===(int)$m['user_id'])?'selected':'' ?>><?= htmlspecialchars($m['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">From</label>
          <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">To</label>
          <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
        </div>
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">Search</label>
          <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Description contains...">
        </div>
        <div>
          <button class="btn btn-primary">Filter</button>
        </div>
      </form>
    </div>

    <!-- Activity List -->
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;color:#003581;margin-bottom:12px;">Activity (<?= number_format($total) ?>)</h3>
      <?php if ($rows): ?>
        <div class="projects-activity-list">
          <?php foreach ($rows as $activity): ?>
            <div class="projects-activity-item">
              <div class="projects-activity-item-icon"><?= act_icon($activity['activity_type']) ?></div>
              <div class="projects-activity-item-content">
                <div style="color:#1b2a57;margin-bottom:4px;">
                  <strong><?= htmlspecialchars($activity['username']) ?></strong>
                  <?= htmlspecialchars($activity['description']) ?>
                </div>
                <div style="font-size:13px;color:#6c757d;">
                  <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?> · <?= htmlspecialchars($activity['activity_type']) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($last_page > 1): ?>
          <div class="projects-activity-pagination">
            <?php for ($p = 1; $p <= $last_page; $p++): ?>
              <?php if ($p == $page): ?>
                <span class="badge" style="background:#003581;color:white;">Page <?= $p ?></span>
              <?php else: ?>
                <a class="btn btn-secondary" href="?project_id=<?= $project_id ?>&page=<?= $p ?><?= build_query_suffix_activity(['page'=>$p]) ?>">Page <?= $p ?></a>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:#6c757d;">
          <div style="font-size:40px;">📝</div>
          <div style="margin-top:8px;">No activity found for the selected filters.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) { closeConnection($conn); }
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
