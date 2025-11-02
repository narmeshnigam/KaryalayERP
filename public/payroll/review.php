<?php
/**
 * Payroll Module - Review & Edit Payroll
 * Review payroll records, edit values, and change status
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payroll_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

$payroll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$payroll_id) {
    $_SESSION['flash_error'] = 'Invalid payroll ID';
    header('Location: index.php');
    exit;
}

// Get payroll details
$payroll = get_payroll_by_id($conn, $payroll_id);
if (!$payroll) {
    $_SESSION['flash_error'] = 'Payroll not found';
    header('Location: index.php');
    exit;
}

// Get payroll records
$records = get_payroll_records($conn, $payroll_id);

// Get activity log
$activity_log = get_payroll_activity_log($conn, $payroll_id);

// Check if can edit
$can_edit = in_array($payroll['status'], ['Draft', 'Reviewed']);
$can_lock = $payroll['status'] === 'Reviewed';
$can_mark_paid = $payroll['status'] === 'Locked';

$page_title = 'Review Payroll: ' . format_month_display($payroll['month']) . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>📋 Review Payroll: <?php echo format_month_display($payroll['month']); ?></h1>
                    <p>Status: <?php echo get_status_badge($payroll['status']); ?> | Total Amount: <strong><?php echo format_currency($payroll['total_amount']); ?></strong></p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="list.php" class="btn btn-secondary">← Back to List</a>
                    <?php if ($payroll['status'] === 'Draft'): ?>
                        <button onclick="markAsReviewed()" class="btn btn-info">✓ Mark as Reviewed</button>
                    <?php elseif ($payroll['status'] === 'Reviewed'): ?>
                        <button onclick="lockPayroll()" class="btn btn-warning">🔒 Lock Payroll</button>
                    <?php elseif ($payroll['status'] === 'Locked'): ?>
                        <button onclick="showPaymentModal()" class="btn btn-success">💳 Mark as Paid</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Payroll Summary -->
        <div class="summary-card">
            <h2>📊 Payroll Summary</h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="summary-label">Total Employees:</span>
                    <span class="summary-value"><?php echo $payroll['total_employees']; ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Total Amount:</span>
                    <span class="summary-value"><?php echo format_currency($payroll['total_amount']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Created By:</span>
                    <span class="summary-value"><?php echo htmlspecialchars($payroll['created_by_name']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Created Date:</span>
                    <span class="summary-value"><?php echo date('d M Y, h:i A', strtotime($payroll['created_at'])); ?></span>
                </div>
                <?php if ($payroll['locked_by']): ?>
                    <div class="summary-item">
                        <span class="summary-label">Locked By:</span>
                        <span class="summary-value"><?php echo htmlspecialchars($payroll['locked_by_name']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Locked Date:</span>
                        <span class="summary-value"><?php echo date('d M Y, h:i A', strtotime($payroll['locked_at'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($payroll['paid_by']): ?>
                    <div class="summary-item">
                        <span class="summary-label">Paid By:</span>
                        <span class="summary-value"><?php echo htmlspecialchars($payroll['paid_by_name']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Paid Date:</span>
                        <span class="summary-value"><?php echo date('d M Y, h:i A', strtotime($payroll['paid_at'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Employee Records Table -->
        <div class="records-section">
            <div class="section-header">
                <h2>👥 Employee Salary Records</h2>
                <div class="section-actions">
                    <button onclick="exportToCSV()" class="btn btn-accent">📥 Export CSV</button>
                </div>
            </div>

            <div class="table-container">
                <table class="payroll-table" id="payrollTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Base Salary</th>
                            <th>Attendance</th>
                            <th>Allowances</th>
                            <th>Reimbursements</th>
                            <th>Deductions</th>
                            <th>Bonus</th>
                            <th>Penalties</th>
                            <th>Net Pay</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr data-record-id="<?php echo $record['id']; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($record['employee_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($record['employee_code']); ?></small>
                                </td>
                                <td><?php echo format_currency($record['base_salary']); ?></td>
                                <td>
                                    <span class="attendance-badge">
                                        <?php echo $record['attendance_days']; ?>/<?php echo $record['total_days']; ?> days
                                    </span>
                                </td>
                                <td><?php echo format_currency($record['allowances']); ?></td>
                                <td><?php echo format_currency($record['reimbursements']); ?></td>
                                <td><?php echo format_currency($record['deductions']); ?></td>
                                <td><?php echo format_currency($record['bonus']); ?></td>
                                <td><?php echo format_currency($record['penalties']); ?></td>
                                <td><strong class="net-pay"><?php echo format_currency($record['net_pay']); ?></strong></td>
                                <td>
                                    <a href="view.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                    <?php if ($can_edit): ?>
                                        <button onclick="editRecord(<?php echo $record['id']; ?>)" class="btn btn-sm btn-primary">Edit</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="8" style="text-align: right;"><strong>TOTAL:</strong></td>
                            <td><strong><?php echo format_currency($payroll['total_amount']); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="activity-section">
            <h2>📜 Activity Log</h2>
            <div class="activity-timeline">
                <?php foreach ($activity_log as $log): ?>
                    <div class="activity-item">
                        <div class="activity-icon"><?php echo $log['action'] === 'Generate' ? '➕' : ($log['action'] === 'Lock' ? '🔒' : ($log['action'] === 'Pay' ? '💳' : '✏️')); ?></div>
                        <div class="activity-details">
                            <div class="activity-action"><strong><?php echo htmlspecialchars($log['action']); ?></strong></div>
                            <div class="activity-user">by <?php echo htmlspecialchars($log['username']); ?></div>
                            <?php if ($log['details']): ?>
                                <div class="activity-description"><?php echo htmlspecialchars($log['details']); ?></div>
                            <?php endif; ?>
                            <div class="activity-time"><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Record Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeEditModal()">&times;</span>
        <h2>✏️ Edit Payroll Record</h2>
        <form id="editForm" onsubmit="saveRecord(event)">
            <input type="hidden" id="edit_record_id" name="record_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Employee Name</label>
                    <input type="text" id="edit_employee_name" class="form-control" readonly>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Bonus</label>
                    <input type="number" step="0.01" id="edit_bonus" name="bonus" class="form-control">
                </div>
                <div class="form-group">
                    <label>Penalties</label>
                    <input type="number" step="0.01" id="edit_penalties" name="penalties" class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>Remarks</label>
                <textarea id="edit_remarks" name="remarks" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closePaymentModal()">&times;</span>
        <h2>💳 Mark Payroll as Paid</h2>
        <form id="paymentForm" onsubmit="markAsPaid(event)">
            <div class="form-group">
                <label>Payment Reference</label>
                <input type="text" id="payment_ref" name="payment_ref" class="form-control" placeholder="Transaction ID / Cheque Number">
            </div>
            
            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Remarks</label>
                <textarea id="payment_remarks" name="remarks" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-success">✓ Confirm Payment</button>
                <button type="button" onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.summary-card {
    background: white;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.summary-card h2 {
    color: #003581;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e0e0e0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.summary-item {
    display: flex;
    flex-direction: column;
}

.summary-label {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.summary-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.records-section {
    background: white;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    color: #003581;
    margin: 0;
}

.table-container {
    overflow-x: auto;
}

.payroll-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.payroll-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.payroll-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #003581;
    white-space: nowrap;
}

.payroll-table td {
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
}

.payroll-table tbody tr:hover {
    background: #f8f9fa;
}

.payroll-table tfoot {
    background: #f8f9fa;
    border-top: 2px solid #003581;
}

.total-row td {
    padding: 16px 12px;
    font-size: 16px;
}

.attendance-badge {
    background: #e3f2fd;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: #1976d2;
    font-weight: 600;
}

.net-pay {
    color: #28a745;
    font-size: 15px;
}

.activity-section {
    background: white;
    padding: 24px;
    border-radius: 8px;
}

.activity-section h2 {
    color: #003581;
    margin-bottom: 20px;
}

.activity-timeline {
    border-left: 2px solid #e0e0e0;
    padding-left: 24px;
    margin-left: 12px;
}

.activity-item {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    position: relative;
}

.activity-icon {
    font-size: 24px;
    position: absolute;
    left: -37px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.activity-details {
    flex: 1;
}

.activity-action {
    color: #003581;
    margin-bottom: 4px;
}

.activity-user {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.activity-description {
    font-size: 14px;
    color: #444;
    margin-bottom: 4px;
}

.activity-time {
    font-size: 12px;
    color: #999;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow: auto;
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 32px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    position: relative;
}

.modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.modal-close:hover {
    color: #000;
}

.modal-content h2 {
    color: #003581;
    margin-bottom: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
}

.modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<script>
let currentRecords = <?php echo json_encode($records); ?>;

function editRecord(recordId) {
    const record = currentRecords.find(r => r.id == recordId);
    if (!record) return;
    
    document.getElementById('edit_record_id').value = record.id;
    document.getElementById('edit_employee_name').value = record.employee_name;
    document.getElementById('edit_bonus').value = record.bonus || 0;
    document.getElementById('edit_penalties').value = record.penalties || 0;
    document.getElementById('edit_remarks').value = record.remarks || '';
    
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function saveRecord(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', 'update_record');
    formData.append('payroll_id', <?php echo $payroll_id; ?>);
    
    fetch('actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error updating record');
        console.error(error);
    });
}

function markAsReviewed() {
    if (confirm('Mark this payroll as Reviewed? You can still edit after reviewing.')) {
        const formData = new FormData();
        formData.append('action', 'mark_reviewed');
        formData.append('payroll_id', <?php echo $payroll_id; ?>);
        
        fetch('actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function lockPayroll() {
    if (confirm('Lock this payroll? Once locked, you cannot edit the records anymore. Are you sure?')) {
        const formData = new FormData();
        formData.append('action', 'lock_payroll');
        formData.append('payroll_id', <?php echo $payroll_id; ?>);
        
        fetch('actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function showPaymentModal() {
    document.getElementById('paymentModal').style.display = 'block';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function markAsPaid(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', 'mark_paid');
    formData.append('payroll_id', <?php echo $payroll_id; ?>);
    
    fetch('actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function exportToCSV() {
    window.location.href = 'export.php?payroll_id=<?php echo $payroll_id; ?>';
}

// Close modals on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
