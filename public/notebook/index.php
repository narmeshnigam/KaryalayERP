<?php
/**
 * Notebook Module - Main Listing Page
 * Display all accessible notes with filters and search
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'notebook', 'view');

// Check if tables exist
if (!notebook_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=notebook');
    exit;
}

// Get filters from request
$filters = [
    'search' => $_GET['search'] ?? '',
    'tag' => $_GET['tag'] ?? '',
    'share_scope' => $_GET['share_scope'] ?? '',
    'created_by' => $_GET['created_by'] ?? ''
];

// Get all notes
$notes = get_all_notes($conn, $CURRENT_USER_ID, $filters);

// Get statistics
$stats = get_notebook_statistics($conn, $CURRENT_USER_ID);

// Get all tags for filter
$all_tags = get_all_tags($conn);

// Check permissions
$notebook_permissions = authz_get_permission_set($conn, 'notebook');
$can_create = $notebook_permissions['can_create'] || $IS_SUPER_ADMIN;

$page_title = 'Notebook - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="margin: 0 0 8px 0;">📒 Notebook</h1>
                    <p style="color: #6c757d; margin: 0;">Organize your notes, documents, and knowledge base</p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="my.php" class="btn btn-secondary">📝 My Notes</a>
                    <a href="shared.php" class="btn btn-secondary">🔗 Shared With Me</a>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary">➕ Create Note</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e3f2fd;">📊</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['total_notes']; ?></div>
                    <div class="stat-label">Total Notes</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f3e5f5;">📝</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['my_notes']; ?></div>
                    <div class="stat-label">My Notes</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #e8f5e9;">🔗</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['shared_with_me']; ?></div>
                    <div class="stat-label">Shared</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff3e0;">📌</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['pinned_notes']; ?></div>
                    <div class="stat-label">Pinned</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fce4ec;">📎</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['total_attachments']; ?></div>
                    <div class="stat-label">Attachments</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 24px;">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                <!-- Search -->
                <div>
                    <label for="search" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">Search</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        class="form-control" 
                        placeholder="Search notes..." 
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
                
                <!-- Share Scope -->
                <div>
                    <label for="share_scope" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">Scope</label>
                    <select id="share_scope" name="share_scope" class="form-control">
                        <option value="">All Scopes</option>
                        <option value="Private" <?php echo ($filters['share_scope'] === 'Private') ? 'selected' : ''; ?>>Private</option>
                        <option value="Team" <?php echo ($filters['share_scope'] === 'Team') ? 'selected' : ''; ?>>Team</option>
                        <option value="Organization" <?php echo ($filters['share_scope'] === 'Organization') ? 'selected' : ''; ?>>Organization</option>
                    </select>
                </div>
                
                <!-- Actions -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Notes Grid -->
        <div class="card">
            <h3 style="margin: 0 0 16px 0; color: #1b2a57;">
                📄 All Notes (<?php echo count($notes); ?>)
            </h3>
            
            <?php if (empty($notes)): ?>
                <div style="text-align: center; padding: 48px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">📒</div>
                    <h3 style="color: #495057; margin-bottom: 8px;">No Notes Found</h3>
                    <p>Start creating notes to organize your knowledge base.</p>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary" style="margin-top: 16px;">➕ Create First Note</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($notes as $note): ?>
                        <div class="note-card" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: #fff; transition: all 0.3s; position: relative;">
                            <?php if ($note['is_pinned']): ?>
                                <div style="position: absolute; top: 8px; right: 8px; background: #ffc107; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    📌 PINNED
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-bottom: 12px;">
                                <a href="view.php?id=<?php echo $note['id']; ?>" style="text-decoration: none;">
                                    <h4 style="margin: 0 0 8px 0; color: #1b2a57; font-size: 16px;">
                                        <?php echo htmlspecialchars($note['title']); ?>
                                    </h4>
                                </a>
                                
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
                                    <?php if ($note['share_scope'] === 'Organization'): ?>
                                        <span title="Organization-wide">🌐</span>
                                    <?php elseif ($note['share_scope'] === 'Team'): ?>
                                        <span title="Team">👥</span>
                                    <?php else: ?>
                                        <span title="Private">🔒</span>
                                    <?php endif; ?>
                                </div>
                                <div title="<?php echo htmlspecialchars($note['creator_full_name'] ?? $note['creator_username']); ?>">
                                    <?php echo htmlspecialchars($note['creator_username']); ?>
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
