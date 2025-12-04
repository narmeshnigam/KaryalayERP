<?php
/**
 * Deliverables Module - Create New Deliverable
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();
$page_title = "Create Deliverable - " . APP_NAME;

// Fetch work orders
$wo_query = "SELECT id, work_order_code, linked_type, linked_id 
             FROM work_orders 
             WHERE status NOT IN ('Completed', 'Cancelled') 
             ORDER BY work_order_code DESC";
$wo_result = mysqli_query($conn, $wo_query);
$work_orders = [];
while ($wo = mysqli_fetch_assoc($wo_result)) {
    $work_orders[] = $wo;
}

// Fetch active employees
$emp_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name, employee_code 
              FROM employees WHERE status = 'Active' ORDER BY first_name";
$emp_result = mysqli_query($conn, $emp_query);
$employees = [];
while ($emp = mysqli_fetch_assoc($emp_result)) {
    $employees[] = $emp;
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

.form-section {
    margin-bottom: 32px;
}

.form-section:last-child {
    margin-bottom: 0;
}

.form-section-title {
    font-size: 18px;
    color: #1a202c;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e2e8f0;
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
.form-group select,
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
.form-group select:focus,
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
    transform: translateY(-2px);
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
            <h1>📦 Create New Deliverable</h1>
            <p>Define a new deliverable linked to a work order</p>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <p><strong>💡 Tip:</strong> Deliverables represent tangible outputs for work orders. Each deliverable can have multiple versions and goes through internal and client approval workflows.</p>
        </div>

        <!-- Form -->
        <div class="form-card">
            <form action="api/create.php" method="POST" enctype="multipart/form-data">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">Basic Information</h3>
                    
                    <div class="form-group">
                        <label>Work Order <span class="required">*</span></label>
                        <select name="work_order_id" required>
                            <option value="">Select Work Order</option>
                            <?php foreach ($work_orders as $wo): ?>
                                <option value="<?php echo $wo['id']; ?>">
                                    <?php echo htmlspecialchars($wo['work_order_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Select the work order this deliverable belongs to</small>
                    </div>

                    <div class="form-group">
                        <label>Deliverable Name <span class="required">*</span></label>
                        <input type="text" name="deliverable_name" placeholder="e.g., Financial Report Q4 2025" required>
                        <small>Clear, descriptive name for this deliverable</small>
                    </div>

                    <div class="form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" placeholder="Detailed description of the deliverable, its purpose, and expected content..." required></textarea>
                        <small>Provide comprehensive details about what this deliverable includes</small>
                    </div>
                </div>

                <!-- Assignment -->
                <div class="form-section">
                    <h3 class="form-section-title">Assignment</h3>
                    
                    <div class="form-group">
                        <label>Assigned To <span class="required">*</span></label>
                        <select name="assigned_to" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['name']) . ' (' . $emp['employee_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Employee responsible for creating this deliverable</small>
                    </div>
                </div>

                <!-- Initial Files (Optional) -->
                <div class="form-section">
                    <h3 class="form-section-title">Initial Files (Optional)</h3>
                    
                    <div class="form-group">
                        <label>Attachments</label>
                        <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
                        <small>Upload initial reference files, templates, or drafts (max 10MB per file)</small>
                    </div>

                    <div class="form-group">
                        <label>Submission Notes</label>
                        <textarea name="submission_notes" placeholder="Any notes or instructions about the initial files..."></textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span>✓</span> Create Deliverable
                    </button>
                    <a href="index.php" class="btn btn-secondary">
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
