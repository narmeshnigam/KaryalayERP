<?php
/**
 * Notebook Module - Edit Note
 * Edit existing note with version control
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist first
if (!notebook_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=notebook');
    exit;
}

if (!authz_user_can_any($conn, [
    ['table' => 'notebook_notes', 'permission' => 'edit_all'],
    ['table' => 'notebook_notes', 'permission' => 'edit_assigned'],
    ['table' => 'notebook_notes', 'permission' => 'edit_own'],
])) {
    authz_require_permission($conn, 'notebook_notes', 'edit_all');
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

$notebook_permissions = authz_get_permission_set($conn, 'notebook_notes');
$can_edit_all = !empty($notebook_permissions['can_edit_all']);
$can_edit_own = !empty($notebook_permissions['can_edit_own']);
$can_edit_assigned = !empty($notebook_permissions['can_edit_assigned']);
$is_creator = ((int) ($note['created_by'] ?? 0) === (int) $CURRENT_USER_ID);

$has_rbac_edit_scope = $IS_SUPER_ADMIN
    || $can_edit_all
    || ($is_creator && $can_edit_own)
    || (!$is_creator && $can_edit_assigned);

if (!$has_rbac_edit_scope) {
    $_SESSION['flash_error'] = 'You do not have permission to edit notes.';
    header('Location: view.php?id=' . $note_id);
    exit;
}

// Check edit permission against note sharing/access rules
if (!can_edit_note($conn, $note_id, $CURRENT_USER_ID)) {
    $_SESSION['flash_error'] = 'You do not have permission to edit this note';
    header('Location: view.php?id=' . $note_id);
    exit;
}

// Get existing attachments
$attachments = get_note_attachments($conn, $note_id);

$errors = [];
$success_message = '';

// Handle attachment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attachment'])) {
    $attachment_id = (int)$_POST['attachment_id'];
    if (delete_attachment($conn, $attachment_id, $note_id, $CURRENT_USER_ID)) {
        $_SESSION['flash_success'] = 'Attachment deleted successfully';
        header('Location: edit.php?id=' . $note_id);
        exit;
    } else {
        $errors[] = "Failed to delete attachment";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_attachment'])) {
    // Collect form data
    $data = [
        'title' => trim($_POST['title']),
        'content' => $_POST['content'], // HTML content from editor
        'linked_entity_id' => !empty($_POST['linked_entity_id']) ? (int)$_POST['linked_entity_id'] : null,
        'linked_entity_type' => $_POST['linked_entity_type'] ?? null,
        'share_scope' => $_POST['share_scope'] ?? 'Private',
        'tags' => trim($_POST['tags']),
        'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0
    ];
    
    // Validate data
    $errors = validate_note_data($data, true);
    
    // If no errors, update note
    if (empty($errors)) {
        if (update_note($conn, $note_id, $data, $CURRENT_USER_ID)) {
            // Handle new file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['name'] as $key => $name) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['attachments']['name'][$key],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                            'size' => $_FILES['attachments']['size'][$key],
                            'error' => $_FILES['attachments']['error'][$key]
                        ];
                        
                        $upload_result = handle_notebook_file_upload($file, $note_id);
                        if ($upload_result['success']) {
                            add_note_attachment($conn, $note_id, $upload_result['file_data'], $CURRENT_USER_ID);
                        } else {
                            $errors = array_merge($errors, $upload_result['errors']);
                        }
                    }
                }
            }
            
            $success_message = "Note updated successfully!";
            $_SESSION['flash_success'] = $success_message;
            header('Location: view.php?id=' . $note_id);
            exit;
        } else {
            $errors[] = "Failed to update note. Please try again.";
        }
    }
    
    // Refresh attachments after operations
    $attachments = get_note_attachments($conn, $note_id);
}

$page_title = 'Edit Note - ' . $note['title'] . ' - Notebook - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Include TinyMCE from CDN -->
<script src="https://cdn.tiny.mce.com/1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="main-wrapper">
    <div class="main-content">
<style>
.notebook-edit-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.notebook-edit-header-buttons{display:flex;gap:8px;}
.notebook-edit-form-grid{display:grid;gap:20px;}

@media (max-width:768px){
.notebook-edit-header-flex{flex-direction:column;align-items:stretch;}
.notebook-edit-header-buttons{width:100%;flex-direction:column;gap:10px;}
.notebook-edit-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.notebook-edit-header-flex h1{font-size:1.5rem;}
}
</style>

        <!-- Page Header -->
        <div class="page-header">
            <div class="notebook-edit-header-flex">
                <div>
                    <h1 style="margin: 0 0 8px 0;">✏️ Edit Note</h1>
                    <p style="color: #6c757d; margin: 0;">Modify note details and content (Version: <?php echo $note['version']; ?>)</p>
                </div>
                <div class="notebook-edit-header-buttons">
                    <a href="view.php?id=<?php echo $note_id; ?>" class="btn btn-secondary">👁️ View</a>
                    <a href="index.php" class="btn btn-secondary">← Back to Notes</a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
        <div style="background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <strong>✓ Success!</strong> <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <strong>⚠️ Please fix the following errors:</strong>
            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Edit Note Form -->
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    📝 Note Details
                </h3>
                
                <div class="notebook-edit-form-grid">
                    <!-- Title -->
                    <div>
                        <label for="title" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Title <span style="color: #dc3545;">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($note['title']); ?>"
                            required
                            maxlength="200"
                        >
                        <small style="color: #6c757d;">Give your note a descriptive title</small>
                    </div>
                    
                    <!-- Content -->
                    <div>
                        <label for="content" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Content <span style="color: #dc3545;">*</span>
                        </label>
                        <textarea 
                            id="content" 
                            name="content" 
                            required
                        ><?php echo htmlspecialchars($note['content']); ?></textarea>
                        <small style="color: #6c757d;">Use the rich-text editor to format your note</small>
                    </div>
                    
                    <!-- Tags -->
                    <div>
                        <label for="tags" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Tags
                        </label>
                        <input 
                            type="text" 
                            id="tags" 
                            name="tags" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($note['tags'] ?? ''); ?>"
                            placeholder="e.g., meeting, project, documentation"
                        >
                        <small style="color: #6c757d;">Comma-separated tags for easy searching</small>
                    </div>
                </div>
            </div>

            <!-- Existing Attachments -->
            <?php if (!empty($attachments)): ?>
            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    📎 Existing Attachments (<?php echo count($attachments); ?>)
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
                                        <?php echo format_file_size($attachment['file_size']); ?>
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
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this attachment?');">
                                    <input type="hidden" name="delete_attachment" value="1">
                                    <input type="hidden" name="attachment_id" value="<?php echo $attachment['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Add New Attachments -->
            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    📎 Add New Attachments
                </h3>
                
                <div>
                    <label for="attachments" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                        Upload Files (Optional)
                    </label>
                    <input 
                        type="file" 
                        id="attachments" 
                        name="attachments[]" 
                        class="form-control" 
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.png,.jpg,.jpeg,.gif"
                    >
                    <small style="color: #6c757d;">Allowed: PDF, DOCX, XLSX, PNG, JPG, TXT (Max: 10MB per file)</small>
                </div>
            </div>

            <div class="card">
                <h3 style="margin: 0 0 20px 0; color: #1b2a57; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                    🔗 Linking & Sharing
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <!-- Share Scope -->
                    <div>
                        <label for="share_scope" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Share Scope <span style="color: #dc3545;">*</span>
                        </label>
                        <select id="share_scope" name="share_scope" class="form-control" required>
                            <option value="Private" <?php echo ($note['share_scope'] === 'Private') ? 'selected' : ''; ?>>🔒 Private (Only Me)</option>
                            <option value="Team" <?php echo ($note['share_scope'] === 'Team') ? 'selected' : ''; ?>>👥 Team (My Role)</option>
                            <option value="Organization" <?php echo ($note['share_scope'] === 'Organization') ? 'selected' : ''; ?>>🌐 Organization (Everyone)</option>
                        </select>
                        <small style="color: #6c757d;">Who can access this note</small>
                    </div>
                    
                    <!-- Entity Type -->
                    <div>
                        <label for="linked_entity_type" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Link to Entity (Optional)
                        </label>
                        <select id="linked_entity_type" name="linked_entity_type" class="form-control">
                            <option value="">None</option>
                            <option value="Client" <?php echo ($note['linked_entity_type'] === 'Client') ? 'selected' : ''; ?>>Client</option>
                            <option value="Project" <?php echo ($note['linked_entity_type'] === 'Project') ? 'selected' : ''; ?>>Project</option>
                            <option value="Lead" <?php echo ($note['linked_entity_type'] === 'Lead') ? 'selected' : ''; ?>>CRM Lead</option>
                            <option value="Other" <?php echo ($note['linked_entity_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <small style="color: #6c757d;">Associate with a record</small>
                    </div>
                    
                    <!-- Entity ID -->
                    <div>
                        <label for="linked_entity_id" style="display: block; margin-bottom: 4px; font-weight: 600; color: #495057;">
                            Entity ID (Optional)
                        </label>
                        <input 
                            type="number" 
                            id="linked_entity_id" 
                            name="linked_entity_id" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($note['linked_entity_id'] ?? ''); ?>"
                            min="1"
                        >
                        <small style="color: #6c757d;">ID of the linked record</small>
                    </div>
                    
                    <!-- Pin Note -->
                    <div style="display: flex; align-items: center; padding-top: 28px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                name="is_pinned" 
                                value="1"
                                <?php echo ($note['is_pinned']) ? 'checked' : ''; ?>
                                style="margin-right: 8px;"
                            >
                            <span style="font-weight: 600; color: #495057;">📌 Pin this note</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Version Info -->
            <div class="card" style="background: #f0f7ff; border: 1px solid #b3d9ff;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 24px;">📚</span>
                    <div>
                        <strong>Version Control:</strong> This note is currently at version <?php echo $note['version']; ?>. 
                        Saving will create version <?php echo $note['version'] + 1; ?> and preserve the previous version in history.
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="view.php?id=<?php echo $note_id; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">✓ Update Note</button>
            </div>
        </form>

    </div>
</div>

<script>
// Initialize TinyMCE
tinymce.init({
    selector: '#content',
    height: 400,
    menubar: false,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | ' +
        'bold italic forecolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | help',
    content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
});
</script>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
