<?php
/**
 * Notebook Module - Shared With Me
 * Display notes shared by other users
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'notebook', 'view');

// Check if tables exist
if (!notebook_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=notebook');
    exit;
}

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'tag' => $_GET['tag'] ?? ''
];

// Get all accessible notes first
$all_notes = get_all_notes($conn, $CURRENT_USER_ID, $filters);

// Filter to only shared notes (not created by me)
$shared_notes = [];
foreach ($all_notes as $note) {
    if ($note['created_by'] != $CURRENT_USER_ID) {
        // Get share details for this note
        $sql = "
            SELECT ns.permission, u.username as shared_by_username, u.full_name as shared_by_full_name
            FROM notebook_shares ns
            LEFT JOIN users u ON ns.shared_with_id = u.id
            WHERE ns.note_id = ? AND (ns.shared_with_id = ? OR ns.shared_with_role IN (
                SELECT r.name FROM roles r 
                JOIN users ur ON ur.role_id = r.id 
                WHERE ur.id = ?
            ))
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $note['id'], $CURRENT_USER_ID, $CURRENT_USER_ID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $share_info = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($share_info || $note['share_scope'] === 'Organization' || $note['share_scope'] === 'Team') {
            $note['share_permission'] = $share_info['permission'] ?? 'View';
            $note['shared_by'] = $note['creator_full_name'] ?? $note['creator_username'];
            $shared_notes[] = $note;
        }
    }
}

// Get all tags
$all_tags = get_all_tags($conn);

// Check permissions
$notebook_permissions = authz_get_permission_set($conn, 'notebook');
$can_create = $notebook_permissions['can_create'] || $IS_SUPER_ADMIN;

$page_title = 'Shared With Me - Notebook - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="margin: 0 0 8px 0;">🔗 Shared With Me</h1>
                    <p style="color: #6c757d; margin: 0;">Notes shared by other users</p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="index.php" class="btn btn-secondary">📒 All Notes</a>
                    <a href="my.php" class="btn btn-secondary">📝 My Notes</a>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary">➕ Create Note</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 24px;">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; align-items: end;">
                <!-- Search -->
                <div>
                    <label for="search" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">Search</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        class="form-control" 
                        placeholder="Search shared notes..." 
                        value="<?php echo htmlspecialchars($filters['search']); ?>"
                    >
                </div>
                
                <!-- Tag Filter -->
                <div>
                    <label for="tag" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">Tag</label>
                    <select id="tag" name="tag" class="form-control">
                        <option value="">All Tags</option>
                        <?php foreach ($all_tags as $tag): ?>
                            <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo ($filters['tag'] === $tag) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tag); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Actions -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="shared.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Shared Notes Grid -->
        <div class="card">
            <h3 style="margin: 0 0 16px 0; color: #1b2a57;">
                📄 Shared Notes (<?php echo count($shared_notes); ?>)
            </h3>
            
            <?php if (empty($shared_notes)): ?>
                <div style="text-align: center; padding: 48px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">🔗</div>
                    <h3 style="color: #495057; margin-bottom: 8px;">No Shared Notes</h3>
                    <p>No notes have been shared with you yet.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($shared_notes as $note): ?>
                        <div class="note-card" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: #fff; transition: all 0.3s; position: relative;">
                            <!-- Share Badge -->
                            <div style="position: absolute; top: 8px; right: 8px; display: flex; gap: 4px;">
                                <?php if ($note['share_scope'] === 'Organization'): ?>
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;" title="Organization-wide">
                                        🌐
                                    </span>
                                <?php elseif ($note['share_scope'] === 'Team'): ?>
                                    <span style="background: #f3e5f5; color: #7b1fa2; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;" title="Team">
                                        👥
                                    </span>
                                <?php endif; ?>
                                <span style="background: #fff3e0; color: #f57c00; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;" title="Permission">
                                    <?php echo $note['share_permission']; ?>
                                </span>
                            </div>
                            
                            <div style="margin-bottom: 12px; margin-top: 24px;">
                                <a href="view.php?id=<?php echo $note['id']; ?>" style="text-decoration: none;">
                                    <h4 style="margin: 0 0 8px 0; color: #1b2a57; font-size: 16px;">
                                        <?php echo htmlspecialchars($note['title']); ?>
                                    </h4>
                                </a>
                                
                                <!-- Shared By -->
                                <div style="font-size: 12px; color: #6c757d; margin-bottom: 8px;">
                                    Shared by <strong><?php echo htmlspecialchars($note['shared_by']); ?></strong>
                                </div>
                                
                                <!-- Content Preview -->
                                <div style="color: #6c757d; font-size: 13px; line-height: 1.5; max-height: 60px; overflow: hidden;">
                                    <?php 
                                    $preview = strip_tags($note['content']);
                                    echo htmlspecialchars(mb_substr($preview, 0, 120)) . (mb_strlen($preview) > 120 ? '...' : '');
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Tags -->
                            <?php if (!empty($note['tags'])): ?>
                                <div style="margin-bottom: 12px; display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php 
                                    $tags = explode(',', $note['tags']);
                                    foreach (array_slice($tags, 0, 3) as $tag): 
                                    ?>
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 12px; font-size: 11px;">
                                            #<?php echo htmlspecialchars(trim($tag)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($tags) > 3): ?>
                                        <span style="color: #6c757d; font-size: 11px;">+<?php echo count($tags) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Meta Info -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6c757d;">
                                <div style="display: flex; gap: 12px;">
                                    <span title="Attachments">📎 <?php echo $note['attachment_count']; ?></span>
                                    <span title="Version">v<?php echo $note['version']; ?></span>
                                </div>
                                <div>
                                    <?php if ($note['share_permission'] === 'Edit'): ?>
                                        <a href="edit.php?id=<?php echo $note['id']; ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="Edit"
                                           style="padding: 4px 8px; font-size: 11px;">
                                            ✏️ Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="font-size: 11px; color: #9e9e9e; margin-top: 8px;">
                                <?php echo date('M d, Y h:i A', strtotime($note['updated_at'] ?? $note['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
.note-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
</style>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
