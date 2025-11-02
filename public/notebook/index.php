<?php
/**
 * Notebook Module - Main Listing Page
 * Display all accessible notes with filters and search
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// If Notebook tables are missing, show an in-page setup prompt instead of redirecting away
if (!notebook_tables_exist($conn)) {
    $page_title = 'Notebook Module Setup Required - ' . APP_NAME;
    require_once __DIR__ . '/../../includes/header_sidebar.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="card" style="max-width:720px;margin:40px auto;">';
    echo '<h3 class="card-title">📒 Notebook Module Not Set Up</h3>';
    echo '<p class="text-muted">The Notebook module database tables have not been created yet. To use this module, please run the setup for Notebook.</p>';
    echo '<div style="margin-top:16px;display:flex;gap:10px;">';
    echo '<a href="/KaryalayERP/scripts/setup_notebook_tables.php" class="btn btn-primary">Run Notebook Setup</a>';
    echo '<a href="/KaryalayERP/setup/index.php" class="btn btn-secondary">Open Setup Wizard</a>';
    echo '</div>';
    echo '</div>';
    echo '</div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

// Now check permissions (tables exist, so permissions should be defined)
if (!authz_user_can_any($conn, [
    ['table' => 'notebook_notes', 'permission' => 'view_all'],
    ['table' => 'notebook_notes', 'permission' => 'view_assigned'],
    ['table' => 'notebook_notes', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'notebook_notes', 'view_all');
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
$notebook_permissions = authz_get_permission_set($conn, 'notebook_notes');
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
                    <a href="my.php" class="btn btn-accent" style="text-decoration: none;">📝 My Notes</a>
                    <a href="shared.php" class="btn btn-accent" style="text-decoration: none;">🔗 Shared With Me</a>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn" style="text-decoration: none;">➕ Create Note</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['total_notes']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Notes</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['my_notes']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">My Notes</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['shared_with_me']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Shared With Me</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #faa718 0%, #ffc04d 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['pinned_notes']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Pinned Notes</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #17a2b8 0%, #20c9e0 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['total_attachments']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Attachments</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 25px;">
            <form method="GET" action="" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <!-- Search -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="search">🔍 Search Notes</label>
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
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="tag">🏷️ Tag</label>
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
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="share_scope">📊 Scope</label>
                    <select id="share_scope" name="share_scope" class="form-control">
                        <option value="">All Scopes</option>
                        <option value="Private" <?php echo ($filters['share_scope'] === 'Private') ? 'selected' : ''; ?>>Private</option>
                        <option value="Team" <?php echo ($filters['share_scope'] === 'Team') ? 'selected' : ''; ?>>Team</option>
                        <option value="Organization" <?php echo ($filters['share_scope'] === 'Organization') ? 'selected' : ''; ?>>Organization</option>
                    </select>
                </div>
                
                <!-- Actions -->
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="white-space: nowrap;">Search</button>
                    <a href="index.php" class="btn btn-accent" style="white-space: nowrap; text-decoration: none; display: inline-block; text-align: center;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Notes Grid -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #003581;">
                    📄 All Notes 
                    <span style="font-size: 14px; color: #6c757d; font-weight: normal;">(<?php echo count($notes); ?> records)</span>
                </h3>
            </div>
            
            <?php if (empty($notes)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                    <div style="font-size: 80px; margin-bottom: 20px;">📒</div>
                    <h3 style="color: #003581; margin-bottom: 15px;">No Notes Found</h3>
                    <p style="color: #6c757d; margin-bottom: 30px; font-size: 16px;">
                        Start creating notes to organize your knowledge base.
                    </p>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn" style="padding: 15px 40px; font-size: 16px; text-decoration: none;">
                            ➕ Create First Note
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($notes as $note): ?>
                        <div class="note-card" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; background: #fff; transition: all 0.3s; position: relative;">
                            <?php if ($note['is_pinned']): ?>
                                <div style="position: absolute; top: 8px; right: 8px; background: #faa718; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    📌 PINNED
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-bottom: 12px;">
                                <a href="view.php?id=<?php echo $note['id']; ?>" style="text-decoration: none;">
                                    <h4 style="margin: 0 0 8px 0; color: #003581; font-size: 16px; font-weight: 600;">
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
                                        <span style="background: #e3f2fd; color: #003581; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
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
