<?php
/**
 * Notebook Module - Add New Note
 * Create a new note with rich-text content and attachments
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist first
if (!notebook_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=notebook');
    exit;
}

authz_require_permission($conn, 'notebook_notes', 'create');

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $data = [
        'title' => trim($_POST['title']),
        'content' => $_POST['content'], // HTML content from editor
        'created_by' => $CURRENT_USER_ID,
        'linked_entity_id' => !empty($_POST['linked_entity_id']) ? (int)$_POST['linked_entity_id'] : null,
        'linked_entity_type' => $_POST['linked_entity_type'] ?? null,
        'share_scope' => $_POST['share_scope'] ?? 'Private',
        'tags' => trim($_POST['tags']),
        'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0
    ];
    
    // Validate data
    $errors = validate_note_data($data, false);
    
    // If no errors, create note
    if (empty($errors)) {
        $note_id = create_note($conn, $data);
        
        if ($note_id) {
            // Handle file uploads
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
            
            $success_message = "Note created successfully!";
            $_SESSION['flash_success'] = $success_message;
            header('Location: view.php?id=' . $note_id);
            exit;
        } else {
            $errors[] = "Failed to create note. Please try again.";
        }
    }
}

$page_title = 'Create Note - Notebook - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Include TinyMCE from CDN -->
<script src="https://cdn.tiny.mce.com/1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>➕ Create New Note</h1>
                    <p>Add a new note with rich-text content and attachments</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-accent">
                        ← Back to Notes
                    </a>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>❌ Error:</strong><br>
            <?php foreach ($errors as $error): ?>
                • <?php echo htmlspecialchars($error); ?><br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Create Note Form -->
        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Note Details -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📝 Note Details
                </h3>
                
                <div style="display: grid; gap: 20px;">
                    <!-- Title -->
                    <div class="form-group">
                        <label>Title <span style="color: #dc3545;">*</span></label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                            required
                            maxlength="200"
                        >
                        <small style="color: #6c757d;">Give your note a descriptive title</small>
                    </div>
                    
                    <!-- Content -->
                    <div class="form-group">
                        <label>Content <span style="color: #dc3545;">*</span></label>
                        <textarea 
                            id="content" 
                            name="content" 
                            required
                        ><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                        <small style="color: #6c757d;">Use the rich-text editor to format your note</small>
                    </div>
                    
                    <!-- Tags -->
                    <div class="form-group">
                        <label>Tags</label>
                        <input 
                            type="text" 
                            id="tags" 
                            name="tags" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>"
                            placeholder="e.g., meeting, project, documentation"
                        >
                        <small style="color: #6c757d;">Comma-separated tags for easy searching</small>
                    </div>
                </div>
            </div>

            <!-- Attachments -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📎 Attachments
                </h3>
                
                <div class="form-group">
                    <label>Upload Files (Optional)</label>
                    <input 
                        type="file" 
                        id="attachments" 
                        name="attachments[]" 
                        class="form-control" 
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.png,.jpg,.jpeg,.gif"
                    >
                    <small style="color: #6c757d;">Allowed: PDF, DOCX, XLSX, PNG, JPG, TXT (Max: 10MB per file, up to 10 files)</small>
                </div>
            </div>

            <!-- Linking & Sharing -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    🔗 Linking & Sharing
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <!-- Share Scope -->
                    <div class="form-group">
                        <label>Share Scope <span style="color: #dc3545;">*</span></label>
                        <select id="share_scope" name="share_scope" class="form-control" required>
                            <option value="Private" <?php echo (($_POST['share_scope'] ?? 'Private') === 'Private') ? 'selected' : ''; ?>>🔒 Private (Only Me)</option>
                            <option value="Team" <?php echo (($_POST['share_scope'] ?? '') === 'Team') ? 'selected' : ''; ?>>👥 Team (My Role)</option>
                            <option value="Organization" <?php echo (($_POST['share_scope'] ?? '') === 'Organization') ? 'selected' : ''; ?>>🌐 Organization (Everyone)</option>
                        </select>
                        <small style="color: #6c757d;">Who can access this note</small>
                    </div>
                    
                    <!-- Entity Type -->
                    <div class="form-group">
                        <label>Link to Entity (Optional)</label>
                        <select id="linked_entity_type" name="linked_entity_type" class="form-control">
                            <option value="">None</option>
                            <option value="Client" <?php echo (($_POST['linked_entity_type'] ?? '') === 'Client') ? 'selected' : ''; ?>>Client</option>
                            <option value="Project" <?php echo (($_POST['linked_entity_type'] ?? '') === 'Project') ? 'selected' : ''; ?>>Project</option>
                            <option value="Lead" <?php echo (($_POST['linked_entity_type'] ?? '') === 'Lead') ? 'selected' : ''; ?>>CRM Lead</option>
                            <option value="Other" <?php echo (($_POST['linked_entity_type'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <small style="color: #6c757d;">Associate with a record</small>
                    </div>
                    
                    <!-- Entity ID -->
                    <div class="form-group">
                        <label>Entity ID (Optional)</label>
                        <input 
                            type="number" 
                            id="linked_entity_id" 
                            name="linked_entity_id" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($_POST['linked_entity_id'] ?? ''); ?>"
                            min="1"
                        >
                        <small style="color: #6c757d;">ID of the linked record</small>
                    </div>
                    
                    <!-- Pin Note -->
                    <div class="form-group">
                        <label style="display: flex; align-items: center; cursor: pointer; margin-top: 28px;">
                            <input 
                                type="checkbox" 
                                name="is_pinned" 
                                value="1"
                                <?php echo (isset($_POST['is_pinned'])) ? 'checked' : ''; ?>
                                style="margin-right: 8px;"
                            >
                            <span style="font-weight: 600;">📌 Pin this note</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div style="text-align: center; padding: 20px 0;">
                <button type="submit" class="btn" style="padding: 15px 60px; font-size: 16px;">
                    ✅ Create Note
                </button>
                <a href="index.php" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
                    ❌ Cancel
                </a>
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
