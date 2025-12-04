<?php
/**
 * Delivery Module - Upload Proof of Delivery (POD)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();
$page_title = "Upload POD - " . APP_NAME;

// Get delivery ID
$delivery_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$delivery_id) {
    header('Location: index.php');
    exit;
}

// Fetch delivery details
$query = "SELECT di.*, d.deliverable_name 
          FROM delivery_items di
          INNER JOIN deliverables d ON di.deliverable_id = d.id
          WHERE di.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    header('Location: index.php');
    exit;
}

if ($delivery['status'] !== 'Delivered' && $delivery['status'] !== 'Confirmed') {
    $_SESSION['error'] = 'POD can only be uploaded for delivered items';
    header('Location: view.php?id=' . $delivery_id);
    exit;
}

require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.main-wrapper { background: #f8fafc; min-height: 100vh; }
.main-content { padding: 24px; max-width: 800px; margin: 0 auto; }

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

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-size: 14px;
    color: #4a5568;
    margin-bottom: 8px;
    font-weight: 500;
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
    min-height: 100px;
}

.form-group small {
    display: block;
    margin-top: 6px;
    color: #718096;
    font-size: 13px;
}

.upload-zone {
    border: 2px dashed #cbd5e0;
    border-radius: 8px;
    padding: 48px 24px;
    text-align: center;
    background: #f7fafc;
    transition: all 0.3s;
    cursor: pointer;
}

.upload-zone:hover {
    border-color: #667eea;
    background: #edf2f7;
}

.upload-zone-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.upload-zone-text {
    font-size: 16px;
    color: #4a5568;
    margin-bottom: 8px;
}

.upload-zone-hint {
    font-size: 13px;
    color: #718096;
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

.info-box {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 16px;
    margin-bottom: 24px;
    border-radius: 8px;
}

.info-box p {
    color: #1e40af;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📸 Upload Proof of Delivery</h1>
            <p>Delivery #<?php echo $delivery_id; ?> - <?php echo htmlspecialchars($delivery['deliverable_name']); ?></p>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <p><strong>📋 Instructions:</strong> Upload proof of delivery documents such as signed receipts, delivery screenshots, email confirmations, or photos showing successful handover.</p>
        </div>

        <!-- Form -->
        <div class="form-card">
            <form action="api/upload_pod.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                
                <div class="form-group">
                    <label>Upload POD Files <span style="color: #e53e3e;">*</span></label>
                    <div class="upload-zone" onclick="document.getElementById('pod-files').click()">
                        <div class="upload-zone-icon">📁</div>
                        <div class="upload-zone-text">Click to select files or drag and drop</div>
                        <div class="upload-zone-hint">PDF, Images, or Documents (max 20MB per file)</div>
                    </div>
                    <input type="file" id="pod-files" name="pod_files[]" multiple required 
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" 
                           style="display: none;"
                           onchange="showSelectedFiles(this)">
                    <div id="selected-files" style="margin-top: 12px;"></div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Add any notes about the delivery confirmation..."></textarea>
                    <small>Optional details about the POD</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span>✓</span> Upload POD
                    </button>
                    <a href="view.php?id=<?php echo $delivery_id; ?>" class="btn btn-secondary">
                        <span>✕</span> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showSelectedFiles(input) {
    const container = document.getElementById('selected-files');
    container.innerHTML = '';
    
    if (input.files.length > 0) {
        const fileList = document.createElement('div');
        fileList.style.cssText = 'background: #f7fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;';
        
        const title = document.createElement('div');
        title.style.cssText = 'font-size: 14px; color: #4a5568; font-weight: 500; margin-bottom: 12px;';
        title.textContent = `${input.files.length} file(s) selected:`;
        fileList.appendChild(title);
        
        Array.from(input.files).forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.style.cssText = 'font-size: 13px; color: #718096; margin-bottom: 4px;';
            fileItem.textContent = `${index + 1}. ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
            fileList.appendChild(fileItem);
        });
        
        container.appendChild(fileList);
    }
}
</script>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../includes/footer_sidebar.php';
?>
