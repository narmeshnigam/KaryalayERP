<?php
/**
 * Deliverables Module - Submit Revision
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();
$page_title = "Submit Revision - " . APP_NAME;

$deliverable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$deliverable_id) {
    header('Location: index.php');
    exit;
}

// Fetch deliverable details
$query = "SELECT d.*, wo.work_order_code 
    FROM deliverables d
    LEFT JOIN work_orders wo ON d.work_order_id = wo.id
    WHERE d.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $deliverable_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$deliverable = mysqli_fetch_assoc($result);

if (!$deliverable) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.main-wrapper { background: #f8fafc; min-height: 100vh; }
.main-content { padding: 24px; max-width: 900px; margin: 0 auto; }

.page-header {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.page-header h1 {
    font-size: 28px;
    color: #1a202c;
    margin-bottom: 8px;
}

.page-header p {
    color: #718096;
    font-size: 14px;
}

.form-card {
    background: white;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.info-box {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    padding: 16px;
    margin-bottom: 24px;
    border-radius: 8px;
}

.info-box p {
    color: #991b1b;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

.deliverable-info {
    background: #f8fafc;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.deliverable-info h3 {
    font-size: 16px;
    color: #1a202c;
    margin-bottom: 12px;
}

.deliverable-info p {
    font-size: 14px;
    color: #4a5568;
    margin: 6px 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    color: #4a5568;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group label .required {
    color: #e53e3e;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.form-group small {
    display: block;
    margin-top: 6px;
    color: #718096;
    font-size: 13px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5568d3;
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>🔄 Submit Revision</h1>
            <p>Submit a new version for this deliverable</p>
        </div>

        <!-- Info Box -->
        <?php if ($deliverable['status'] === 'Revision Requested'): ?>
        <div class="info-box">
            <p><strong>⚠️ Revision Required:</strong> Client has requested changes to this deliverable. Please address the feedback and submit an updated version.</p>
        </div>
        <?php endif; ?>

        <!-- Deliverable Info -->
        <div class="deliverable-info">
            <h3><?php echo htmlspecialchars($deliverable['deliverable_name']); ?></h3>
            <p><strong>Work Order:</strong> <?php echo htmlspecialchars($deliverable['work_order_code']); ?></p>
            <p><strong>Current Version:</strong> v<?php echo $deliverable['current_version']; ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($deliverable['status']); ?></p>
        </div>

        <!-- Form -->
        <div class="form-card">
            <form action="api/revise.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="deliverable_id" value="<?php echo $deliverable_id; ?>">
                
                <div class="form-group">
                    <label>Revision Notes <span class="required">*</span></label>
                    <textarea name="submission_notes" placeholder="Describe the changes made in this revision, how you addressed the feedback, etc..." required></textarea>
                    <small>Explain what has been changed or improved in this version</small>
                </div>

                <div class="form-group">
                    <label>Upload Files <span class="required">*</span></label>
                    <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip" required>
                    <small>Upload the revised deliverable files (max 10MB per file)</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span>📤</span> Submit Revision
                    </button>
                    <a href="view.php?id=<?php echo $deliverable_id; ?>" class="btn btn-secondary">
                        <span>✕</span> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
