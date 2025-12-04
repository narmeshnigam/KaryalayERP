<?php
/**
 * Delivery Module - Create New Delivery Item
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();
$page_title = "Create Delivery - " . APP_NAME;

// Fetch client-approved deliverables
$deliv_query = "SELECT d.id, d.deliverable_name, d.work_order_id, wo.work_order_code
                FROM deliverables d
                LEFT JOIN work_orders wo ON d.work_order_id = wo.id
                WHERE d.status = 'Client Approved'
                ORDER BY d.created_at DESC";
$deliv_result = mysqli_query($conn, $deliv_query);
$deliverables = [];
while ($d = mysqli_fetch_assoc($deliv_result)) {
    $deliverables[] = $d;
}

// Fetch employees
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
            <h1>🚚 Create Delivery Item</h1>
            <p>Set up a new delivery for a client-approved deliverable</p>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <p><strong>💡 Tip:</strong> Delivery items track the final handover of approved deliverables to clients. Select a deliverable, choose delivery channel, and provide recipient details.</p>
        </div>

        <!-- Form -->
        <div class="form-card">
            <form action="api/create.php" method="POST" enctype="multipart/form-data">
                <!-- Deliverable Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">Deliverable Information</h3>
                    
                    <div class="form-group">
                        <label>Select Deliverable <span class="required">*</span></label>
                        <select name="deliverable_id" required id="deliverable-select">
                            <option value="">Choose a client-approved deliverable</option>
                            <?php foreach ($deliverables as $d): ?>
                                <option value="<?php echo $d['id']; ?>" data-wo="<?php echo $d['work_order_id']; ?>">
                                    <?php echo htmlspecialchars($d['deliverable_name']) . ' - WO: ' . htmlspecialchars($d['work_order_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Only client-approved deliverables are shown</small>
                    </div>
                </div>

                <!-- Delivery Details -->
                <div class="form-section">
                    <h3 class="form-section-title">Delivery Details</h3>
                    
                    <div class="form-group">
                        <label>Delivery Channel <span class="required">*</span></label>
                        <select name="channel" required>
                            <option value="">Select Channel</option>
                            <option value="Email">Email</option>
                            <option value="Portal">Portal</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Physical">Physical Handover</option>
                            <option value="Courier">Courier Service</option>
                            <option value="Cloud Link">Cloud Link (Drive/Dropbox)</option>
                            <option value="Other">Other</option>
                        </select>
                        <small>How will this deliverable be sent to the client?</small>
                    </div>

                    <div class="form-group">
                        <label>Delivered By</label>
                        <select name="delivered_by">
                            <option value="">Assign later</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['name']) . ' (' . $emp['employee_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Employee responsible for delivery</small>
                    </div>

                    <div class="form-group">
                        <label>Main Link/URL</label>
                        <input type="url" name="main_link" placeholder="https://drive.google.com/...">
                        <small>Primary cloud link or portal URL (if applicable)</small>
                    </div>
                </div>

                <!-- Recipient Information -->
                <div class="form-section">
                    <h3 class="form-section-title">Recipient Information</h3>
                    
                    <div class="form-group">
                        <label>Recipient Name</label>
                        <input type="text" name="delivered_to_name" placeholder="e.g., John Doe">
                        <small>Client contact person who will receive the delivery</small>
                    </div>

                    <div class="form-group">
                        <label>Recipient Contact</label>
                        <input type="text" name="delivered_to_contact" placeholder="Email or phone number">
                        <small>Email address or phone number for delivery</small>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="form-section">
                    <h3 class="form-section-title">Additional Information</h3>
                    
                    <div class="form-group">
                        <label>Internal Notes</label>
                        <textarea name="notes" placeholder="Any special instructions, packaging notes, or delivery requirements..."></textarea>
                        <small>Internal notes for the delivery team</small>
                    </div>

                    <div class="form-group">
                        <label>Attach Delivery Files (Optional)</label>
                        <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
                        <small>Upload final delivery files (max 20MB per file)</small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span>✓</span> Create Delivery Item
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
