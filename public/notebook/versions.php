<?php
/**
 * Notebook Module - Version History
 * View all versions of a note
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'notebook', 'view');

// Check if tables exist
if (!notebook_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=notebook');
    exit;
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

// Get all versions
$versions = get_note_versions($conn, $note_id);

// Handle version comparison
$compare_version = isset($_GET['compare']) ? (int)$_GET['compare'] : null;
$selected_version_content = null;

if ($compare_version) {
    foreach ($versions as $version) {
        if ($version['version_number'] == $compare_version) {
            $selected_version_content = $version['content_snapshot'];
            break;
        }
    }
}

$page_title = 'Version History - ' . $note['title'] . ' - Notebook - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">📚 Version History</h1>
                    <p style="color: #6c757d; margin: 0;">
                        <strong><?php echo htmlspecialchars($note['title']); ?></strong> 
                        (Current: v<?php echo $note['version']; ?>)
                    </p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="view.php?id=<?php echo $note_id; ?>" class="btn btn-secondary">👁️ View Note</a>
                    <?php if (can_edit_note($conn, $note_id, $CURRENT_USER_ID)): ?>
                        <a href="edit.php?id=<?php echo $note_id; ?>" class="btn btn-primary">✏️ Edit</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">← Back to Notes</a>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 400px 1fr; gap: 24px;">
            <!-- Version Timeline -->
            <div>
                <div class="card">
                    <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                        📜 Timeline (<?php echo count($versions); ?> versions)
                    </h3>
                    
                    <div style="position: relative;">
                        <!-- Timeline line -->
                        <div style="position: absolute; left: 20px; top: 0; bottom: 0; width: 2px; background: #e5e7eb;"></div>
                        
                        <div style="display: grid; gap: 16px;">
                            <?php foreach ($versions as $index => $version): ?>
                                <div style="position: relative; padding-left: 48px;">
                                    <!-- Timeline dot -->
                                    <div style="position: absolute; left: 12px; top: 8px; width: 16px; height: 16px; background: <?php echo ($version['version_number'] == $note['version']) ? '#003581' : '#fff'; ?>; border: 3px solid <?php echo ($version['version_number'] == $note['version']) ? '#003581' : '#b3d9ff'; ?>; border-radius: 50%;"></div>
                                    
                                    <div style="background: <?php echo ($version['version_number'] == $compare_version) ? '#fff3cd' : '#f8f9fa'; ?>; padding: 12px; border-radius: 8px; border: 1px solid <?php echo ($version['version_number'] == $compare_version) ? '#ffc107' : '#e5e7eb'; ?>;">
                                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                            <div>
                                                <div style="font-weight: 700; color: #1b2a57; font-size: 15px;">
                                                    Version <?php echo $version['version_number']; ?>
                                                    <?php if ($version['version_number'] == $note['version']): ?>
                                                        <span style="background: #28a745; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 4px;">CURRENT</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">
                                                    by <?php echo htmlspecialchars($version['updated_by_username']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="font-size: 11px; color: #6c757d; margin-bottom: 8px;">
                                            <?php echo date('M d, Y h:i A', strtotime($version['updated_at'])); ?>
                                        </div>
                                        
                                        <div style="display: flex; gap: 4px;">
                                            <a href="?id=<?php echo $note_id; ?>&compare=<?php echo $version['version_number']; ?>" 
                                               class="btn btn-sm <?php echo ($version['version_number'] == $compare_version) ? 'btn-warning' : 'btn-primary'; ?>" 
                                               style="font-size: 11px; padding: 4px 8px;">
                                                <?php echo ($version['version_number'] == $compare_version) ? '👁️ Viewing' : '👁️ View'; ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version Content Preview -->
            <div>
                <?php if ($selected_version_content !== null): ?>
                    <div class="card">
                        <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                            📄 Version <?php echo $compare_version; ?> Content
                        </h3>
                        
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; line-height: 1.6;">
                            <?php echo $selected_version_content; ?>
                        </div>
                        
                        <div style="margin-top: 20px; padding: 16px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;">
                            <strong>ℹ️ Note:</strong> This is a historical snapshot. To restore this version, you would need to copy the content and create a new version via the edit page.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div style="text-align: center; padding: 48px; color: #6c757d;">
                            <div style="font-size: 64px; margin-bottom: 16px;">📚</div>
                            <h3 style="color: #495057; margin-bottom: 8px;">Select a Version</h3>
                            <p>Click "View" on any version from the timeline to see its content here.</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Current Version -->
                <div class="card" style="margin-top: 24px;">
                    <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                        ✨ Current Version (v<?php echo $note['version']; ?>)
                    </h3>
                    
                    <div style="padding: 20px; background: #e8f5e9; border-radius: 8px; line-height: 1.6; border: 2px solid #4caf50;">
                        <?php echo $note['content']; ?>
                    </div>
                </div>
                
                <!-- Version Statistics -->
                <div class="card" style="margin-top: 24px;">
                    <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                        📊 Version Statistics
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div style="padding: 16px; background: #e3f2fd; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: 700; color: #1976d2;"><?php echo count($versions); ?></div>
                            <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">Total Versions</div>
                        </div>
                        
                        <div style="padding: 16px; background: #f3e5f5; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: 700; color: #7b1fa2;"><?php echo $note['version']; ?></div>
                            <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">Current Version</div>
                        </div>
                        
                        <div style="padding: 16px; background: #e8f5e9; border-radius: 8px;">
                            <div style="font-size: 13px; font-weight: 600; color: #2e7d32;">
                                <?php 
                                $contributors = array_unique(array_map(fn($v) => $v['updated_by_username'], $versions));
                                echo count($contributors); 
                                ?>
                            </div>
                            <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">Contributors</div>
                        </div>
                        
                        <div style="padding: 16px; background: #fff3e0; border-radius: 8px;">
                            <div style="font-size: 13px; font-weight: 600; color: #f57c00;">
                                <?php 
                                if (count($versions) >= 2) {
                                    $first = strtotime($versions[count($versions)-1]['updated_at']);
                                    $last = strtotime($versions[0]['updated_at']);
                                    $days = ceil(($last - $first) / 86400);
                                    echo $days . ' day' . ($days != 1 ? 's' : '');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                            <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">Edit Span</div>
                        </div>
                    </div>
                </div>
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
