<?php
/**
 * Deliverables Module - Edit Deliverable
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

$conn = createConnection();
$page_title = "Edit Deliverable - " . APP_NAME;

$deliverable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$deliverable_id) {
    header('Location: index.php');
    exit;
}

// Fetch deliverable details
$query = "SELECT * FROM deliverables WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $deliverable_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$deliverable = mysqli_fetch_assoc($result);

if (!$deliverable) {
    header('Location: index.php');
    exit;
}

// Fetch work orders
$wo_query = "SELECT id, work_order_code FROM work_orders ORDER BY work_order_code DESC LIMIT 100";
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
            <h1>✏️ Edit Deliverable</h1>
            <p>Update deliverable information</p>
        </div>

        <!-- Form -->
        <div class="form-card">
            <form action="api/update.php" method="POST">
                <input type="hidden" name="deliverable_id" value="<?php echo $deliverable_id; ?>">
                
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">Basic Information</h3>
                    
                    <div class="form-group">
                        <label>Work Order <span class="required">*</span></label>
                        <select name="work_order_id" required>
                            <option value="">Select Work Order</option>
                            <?php foreach ($work_orders as $wo): ?>
                                <option value="<?php echo $wo['id']; ?>" <?php echo $deliverable['work_order_id'] == $wo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($wo['work_order_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Deliverable Name <span class="required">*</span></label>
                        <input type="text" name="deliverable_name" value="<?php echo htmlspecialchars($deliverable['deliverable_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" required><?php echo htmlspecialchars($deliverable['description']); ?></textarea>
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
                                <option value="<?php echo $emp['id']; ?>" <?php echo $deliverable['assigned_to'] == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']) . ' (' . $emp['employee_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span>✓</span> Update Deliverable
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
