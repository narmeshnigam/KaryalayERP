<?php
/**
 * Notebook Module - View Note
 * Display complete note details with attachments and version history
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist first
if (!notebook_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=notebook');
    exit;
}

if (!authz_user_can_any($conn, [
    ['table' => 'notebook_notes', 'permission' => 'view_all'],
    ['table' => 'notebook_notes', 'permission' => 'view_assigned'],
    ['table' => 'notebook_notes', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'notebook_notes', 'view_all');
}

// Get note ID
$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($note_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get note details
$note = get_note_by_id($conn, $note_id, $CURRENT_USER_ID);

if (!$note) {
    $_SESSION['flash_error'] = 'Note not found or access denied';
    header('Location: index.php');
    exit;
}

// Get attachments
$attachments = get_note_attachments($conn, $note_id);

// Get shares
$shares = get_note_shares($conn, $note_id);

// Get recent versions
$versions = array_slice(get_note_versions($conn, $note_id), 0, 5);

// Check permissions
$can_edit = can_edit_note($conn, $note_id, $CURRENT_USER_ID);
$can_delete = ($note['created_by'] == $CURRENT_USER_ID) || $IS_SUPER_ADMIN;

$page_title = $note['title'] . ' - Notebook - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <h1 style="margin: 0;">📄 <?php echo htmlspecialchars($note['title']); ?></h1>
                        <?php if ($note['is_pinned']): ?>
                            <span style="background: #ffc107; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                📌 PINNED
                            </span>
                        <?php endif; ?>
                    </div>
                    <p style="color: #6c757d; margin: 0;">
                        Created by <strong><?php echo htmlspecialchars($note['creator_full_name'] ?? $note['creator_username']); ?></strong> 
                        on <?php echo date('M d, Y h:i A', strtotime($note['created_at'])); ?>
                        <?php if ($note['updated_at']): ?>
                            • Updated <?php echo date('M d, Y h:i A', strtotime($note['updated_at'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if ($can_edit): ?>
                        <a href="edit.php?id=<?php echo $note_id; ?>" class="btn btn-primary">✏️ Edit</a>
                    <?php endif; ?>
                    <?php if ($can_delete): ?>
                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this note? This action cannot be undone.');">
                            <input type="hidden" name="note_id" value="<?php echo $note_id; ?>">
                            <button type="submit" class="btn btn-danger">🗑️ Delete</button>
                        </form>
                    <?php endif; ?>
                    <a href="versions.php?id=<?php echo $note_id; ?>" class="btn btn-secondary">📚 Version History (v<?php echo $note['version']; ?>)</a>
                    <a href="index.php" class="btn btn-secondary">← Back to Notes</a>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 24px;">
            <!-- Main Content -->
            <div>
                <!-- Note Content -->
                <div class="card">
                    <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                        📝 Content
                    </h3>
                    
                    <div style="line-height: 1.6; color: #333;">
                        <?php echo $note['content']; ?>
                    </div>
                </div>

                <!-- Attachments -->
                <?php if (!empty($attachments)): ?>
                <div class="card">
                    <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                        📎 Attachments (<?php echo count($attachments); ?>)
                    </h3>
                    
                    <div style="display: grid; gap: 12px;">
                        <?php foreach ($attachments as $attachment): ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e5e7eb;">
                                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                    <div style="font-size: 32px;">
                                        <?php
                                        $ext = pathinfo($attachment['file_name'], PATHINFO_EXTENSION);
                                        $icon = match(strtolower($ext)) {
                                            'pdf' => '📕',
                                            'doc', 'docx' => '📘',
                                            'xls', 'xlsx' => '📗',
                                            'jpg', 'jpeg', 'png', 'gif' => '🖼️',
                                            'txt' => '📄',
                                            default => '📎'
                                        };
                                        echo $icon;
                                        ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #1b2a57; word-break: break-word;">
                                            <?php echo htmlspecialchars($attachment['file_name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #6c757d;">
                                            <?php echo format_file_size($attachment['file_size']); ?> • 
                                            Uploaded by <?php echo htmlspecialchars($attachment['uploaded_by_username']); ?> • 
                                            <?php echo date('M d, Y', strtotime($attachment['uploaded_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="/KaryalayERP/<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-primary" 
                                       title="View/Download">
                                        ⬇️
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sharing Info -->
                <?php if (!empty($shares) || $note['share_scope'] !== 'Private'): ?>
                <div class="card">
                    <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                        🔗 Sharing & Access
                    </h3>
                    
                    <div style="margin-bottom: 16px;">
                        <strong>Share Scope:</strong>
                        <?php if ($note['share_scope'] === 'Organization'): ?>
                            <span style="background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 12px; font-size: 13px;">
                                🌐 Organization-wide
                            </span>
                        <?php elseif ($note['share_scope'] === 'Team'): ?>
                            <span style="background: #f3e5f5; color: #7b1fa2; padding: 4px 12px; border-radius: 12px; font-size: 13px;">
                                👥 Team Members
                            </span>
                        <?php else: ?>
                            <span style="background: #fff3e0; color: #f57c00; padding: 4px 12px; border-radius: 12px; font-size: 13px;">
                                🔒 Private
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($shares)): ?>
                        <div>
                            <strong style="display: block; margin-bottom: 8px;">Shared With:</strong>
                            <div style="display: grid; gap: 8px;">
                                <?php foreach ($shares as $share): ?>
                                    <div style="padding: 8px; background: #f8f9fa; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                                        <span>
                                            <?php if ($share['shared_with_id']): ?>
                                                👤 <?php echo htmlspecialchars($share['full_name'] ?? $share['username']); ?>
                                            <?php else: ?>
                                                👥 <?php echo htmlspecialchars($share['shared_with_role']); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span style="font-size: 12px; color: #6c757d;">
                                            <?php echo $share['permission']; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Metadata Card -->
                <div class="card">
                    <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px;">
                        ℹ️ Information
                    </h3>
                    
                    <div style="display: grid; gap: 12px; font-size: 14px;">
                        <div>
                            <div style="color: #6c757d; font-size: 12px; margin-bottom: 4px;">Version</div>
                            <div style="font-weight: 600;">v<?php echo $note['version']; ?></div>
                        </div>
                        
                        <?php if (!empty($note['tags'])): ?>
                        <div>
                            <div style="color: #6c757d; font-size: 12px; margin-bottom: 4px;">Tags</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                <?php 
                                $tags = explode(',', $note['tags']);
                                foreach ($tags as $tag): 
                                ?>
                                    <a href="index.php?tag=<?php echo urlencode(trim($tag)); ?>" 
                                       style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 11px; text-decoration: none;">
                                        #<?php echo htmlspecialchars(trim($tag)); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($note['linked_entity_type']): ?>
                        <div>
                            <div style="color: #6c757d; font-size: 12px; margin-bottom: 4px;">Linked To</div>
                            <div style="font-weight: 600;">
                                <?php echo htmlspecialchars($note['linked_entity_type']); ?>
                                <?php if ($note['linked_entity_id']): ?>
                                    #<?php echo $note['linked_entity_id']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <div style="color: #6c757d; font-size: 12px; margin-bottom: 4px;">Created</div>
                            <div><?php echo date('M d, Y h:i A', strtotime($note['created_at'])); ?></div>
                        </div>
                        
                        <?php if ($note['updated_at']): ?>
                        <div>
                            <div style="color: #6c757d; font-size: 12px; margin-bottom: 4px;">Last Updated</div>
                            <div><?php echo date('M d, Y h:i A', strtotime($note['updated_at'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Versions -->
                <?php if (!empty($versions)): ?>
                <div class="card">
                    <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px;">
                        📚 Recent Versions
                    </h3>
                    
                    <div style="display: grid; gap: 8px;">
                        <?php foreach ($versions as $version): ?>
                            <div style="padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 13px;">
                                <div style="font-weight: 600; color: #1b2a57;">
                                    Version <?php echo $version['version_number']; ?>
                                </div>
                                <div style="font-size: 11px; color: #6c757d;">
                                    by <?php echo htmlspecialchars($version['updated_by_username']); ?><br>
                                    <?php echo date('M d, Y h:i A', strtotime($version['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="versions.php?id=<?php echo $note_id; ?>" 
                       style="display: block; text-align: center; margin-top: 12px; color: #003581; text-decoration: none; font-size: 13px;">
                        View All Versions →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
