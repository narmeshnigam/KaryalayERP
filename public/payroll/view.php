<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'employees.view');

$payroll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$payroll = get_payroll_by_id($conn, $payroll_id);

if (!$payroll) {
    header('Location: index.php');
    exit;
}

// Handle lock payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lock_payroll') {
    if (authz_user_can($conn, 'employees.create') && $payroll['status'] === 'Draft') {
        $transaction_mode = $_POST['transaction_mode'];
        $transaction_ref = $_POST['transaction_ref'];
        
        if (empty($transaction_mode) || empty($transaction_ref)) {
            flash_add('error', 'Payment mode and reference are required to lock payroll.');
        } else {
            if (lock_payroll($conn, $payroll_id, $transaction_mode, $transaction_ref)) {
                // Update reimbursements if applicable
                if ($payroll['payroll_type'] === 'Reimbursement') {
                    $items = get_payroll_items($conn, $payroll_id);
                    foreach ($items as $item) {
                        if ($item['reimbursement_id']) {
                            $conn->query("UPDATE reimbursements SET payment_status = 'Paid', paid_date = NOW() WHERE id = " . $item['reimbursement_id']);
                        }
                    }
                }
                
                log_payroll_activity($conn, $payroll_id, 'Lock', $_SESSION['user_id'], 'Payroll locked with ' . $transaction_mode . ' payment');
                flash_add('success', 'Payroll locked successfully!');
                header('Location: view.php?id=' . $payroll_id);
                exit;
            } else {
                flash_add('error', 'Failed to lock payroll.');
            }
        }
    } else {
        flash_add('error', 'You do not have permission to lock payroll or it is already locked.');
    }
}

$items = get_payroll_items($conn, $payroll_id);
$activity_log = get_payroll_activity_log($conn, $payroll_id);
$active_tab = $_GET['tab'] ?? 'overview';

$page_title = 'View Payroll #' . $payroll_id . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.tabs{display:flex;border-bottom:2px solid #e0e0e0;margin-bottom:30px;gap:5px}
.tab-btn{padding:12px 24px;background:transparent;border:none;color:#666;cursor:pointer;font-size:15px;border-bottom:3px solid transparent;transition:all 0.3s}
.tab-btn.active{color:#003581;border-bottom-color:#003581;font-weight:600}
.tab-content{display:none}
.tab-content.active{display:block}
.activity-timeline{border-left:3px solid #e0e0e0;padding-left:20px}
.activity-item{position:relative;padding:15px 0;border-bottom:1px solid #f0f0f0}
.activity-item:before{content:'';position:absolute;left:-26px;top:20px;width:12px;height:12px;border-radius:50%;background:#003581}
.lock-form{background:#f8f9fa;padding:25px;border-radius:8px;margin-top:20px}
.item-row{transition:background 0.2s}
.item-row:hover{background:#f8f9fa !important}
.amount-input,.transaction-number-input{font-size:14px;padding:6px 10px;border:2px solid #003581;border-radius:4px}
.amount-input:focus,.transaction-number-input:focus{outline:none;box-shadow:0 0 0 3px rgba(0,53,129,0.1)}
#itemsTable th{font-size:13px;text-transform:uppercase;letter-spacing:0.5px;color:#003581;font-weight:700;padding:14px 12px}
#itemsTable td{padding:12px;font-size:14px}
.badge{padding:5px 12px;border-radius:12px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
.badge-pending{background:#fff3cd;color:#856404;border:1px solid #ffc107}
.badge-paid{background:#d4edda;color:#155724;border:1px solid #28a745}
.badge-draft{background:#e2e3e5;color:#383d41;border:1px solid #6c757d}
.badge-locked{background:#cfe2ff;color:#084298;border:1px solid #0d6efd}
.badge-salary{background:#e7f3ff;color:#003581;border:1px solid #003581}
.badge-reimbursement{background:#fff4e6;color:#ff8c00;border:1px solid #ff8c00}
@media (max-width:768px){
    .tabs{overflow-x:auto}.tab-btn{white-space:nowrap}
    #itemsTable{font-size:12px}
    #itemsTable th,#itemsTable td{padding:8px 6px}
    .btn-sm{padding:4px 8px;font-size:11px}
}
</style>

<div class="main-wrapper">
<div class="main-content">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h1 style="color:#003581;margin:0">Payroll #<?php echo $payroll['id']; ?></h1>
    <div>
        <span class="badge badge-<?php echo strtolower($payroll['status']); ?>"><?php echo $payroll['status']; ?></span>
        <a href="export.php?id=<?php echo $payroll_id; ?>" class="btn btn-outline-secondary">Export</a>
        <a href="index.php" class="btn btn-outline-secondary"> Back</a>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <button class="tab-btn <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" onclick="switchTab('overview')">Overview</button>
    <button class="tab-btn <?php echo $active_tab === 'items' ? 'active' : ''; ?>" onclick="switchTab('items')">Items (<?php echo count($items); ?>)</button>
    <button class="tab-btn <?php echo $active_tab === 'activity' ? 'active' : ''; ?>" onclick="switchTab('activity')">Activity Log</button>
    <?php if ($payroll['status'] === 'Draft' && authz_user_can($conn, 'employees.create')): ?>
    <button class="tab-btn <?php echo $active_tab === 'lock' ? 'active' : ''; ?>" onclick="switchTab('lock')">Lock Payroll</button>
    <?php endif; ?>
</div>

<div style="background:white;padding:30px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05)">

<!-- Overview Tab -->
<div id="overview-tab" class="tab-content <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
    <h3 style="margin-bottom:20px">Payroll Details</h3>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:30px">
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Type</small><br>
            <span class="badge badge-<?php echo strtolower($payroll['payroll_type']); ?>"><?php echo $payroll['payroll_type']; ?></span>
        </div>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Month</small><br>
            <strong><?php echo get_month_name($payroll['month_year']); ?></strong>
        </div>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Total Amount</small><br>
            <strong style="color:#003581"><?php echo format_currency($payroll['total_amount']); ?></strong>
        </div>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Employees</small><br>
            <strong><?php echo $payroll['total_employees']; ?></strong>
        </div>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Payment Mode</small><br>
            <strong><?php echo $payroll['transaction_mode'] ?? 'Not set'; ?></strong>
        </div>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Reference</small><br>
            <strong><?php echo $payroll['transaction_ref'] ?? 'Not set'; ?></strong>
        </div>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Created By</small><br>
            <strong><?php echo htmlspecialchars($payroll['created_by_name'] ?? 'Unknown'); ?></strong>
        </div>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Created At</small><br>
            <strong><?php echo date('d M Y, h:i A', strtotime($payroll['created_at'])); ?></strong>
        </div>
        <?php if ($payroll['locked_at']): ?>
        <div style="padding:15px;background:#f8f9fa;border-radius:8px">
            <small style="color:#666">Locked At</small><br>
            <strong><?php echo date('d M Y, h:i A', strtotime($payroll['locked_at'])); ?></strong>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Items Tab -->
<div id="items-tab" class="tab-content <?php echo $active_tab === 'items' ? 'active' : ''; ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h3 style="margin:0">Payroll Items (<?php echo count($items); ?>)</h3>
        <div style="display:flex;gap:10px">
            <?php if ($payroll['status'] === 'Draft'): ?>
                <button class="btn btn-sm btn-primary" onclick="toggleEditMode()" id="editModeBtn">
                    ✏️ Edit Items
                </button>
                <button class="btn btn-sm btn-success" onclick="saveBulkChanges()" id="saveBulkBtn" style="display:none">
                    💾 Save Changes
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="cancelEditMode()" id="cancelEditBtn" style="display:none">
                    ❌ Cancel
                </button>
            <?php endif; ?>
            <button class="btn btn-sm btn-accent" onclick="exportItems()">
                📊 Export Items
            </button>
        </div>
    </div>
    
    <div style="background:#fff3cd;padding:12px 16px;border-radius:6px;margin-bottom:20px;border-left:4px solid #ffc107" id="editModeAlert" style="display:none">
        <strong>📝 Edit Mode Active:</strong> Click on transaction numbers or amounts to edit. Changes are highlighted in yellow.
    </div>
    
    <div style="overflow-x:auto">
    <table class="table table-bordered" id="itemsTable">
        <thead style="background:#f8f9fa">
            <tr>
                <th style="width:40px">#</th>
                <th style="min-width:150px">Transaction No.</th>
                <th style="min-width:180px">Employee</th>
                <th style="min-width:100px">Code</th>
                <th style="min-width:120px">Department</th>
                <th style="text-align:right;min-width:110px">Base</th>
                <th style="text-align:right;min-width:110px">Allowances</th>
                <th style="text-align:right;min-width:110px">Deductions</th>
                <th style="text-align:right;min-width:120px">Payable</th>
                <th style="min-width:100px">Status</th>
                <?php if ($payroll['status'] === 'Draft'): ?>
                <th style="width:80px">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="itemsTableBody">
            <?php if (empty($items)): ?>
                <tr><td colspan="<?php echo $payroll['status'] === 'Draft' ? '11' : '10'; ?>" style="text-align:center;color:#666;padding:40px">
                    <div style="font-size:48px;margin-bottom:10px;opacity:0.5">📄</div>
                    No items found in this payroll
                </td></tr>
            <?php else: ?>
                <?php 
                $total_base = 0;
                $total_allowances = 0;
                $total_deductions = 0;
                $total_payable = 0;
                $row_num = 1;
                foreach ($items as $item): 
                    $total_base += $item['base_salary'] ?? 0;
                    $total_allowances += $item['allowances'] ?? 0;
                    $total_deductions += $item['deductions'] ?? 0;
                    $total_payable += $item['payable'];
                ?>
                <tr data-item-id="<?php echo $item['id']; ?>" class="item-row">
                    <td style="text-align:center;color:#666"><?php echo $row_num++; ?></td>
                    <td>
                        <span class="transaction-number-display" style="font-family:monospace;font-size:13px;color:#003581;font-weight:600">
                            <?php echo htmlspecialchars($item['transaction_number'] ?? 'N/A'); ?>
                        </span>
                        <input type="text" class="form-control form-control-sm transaction-number-input" 
                               value="<?php echo htmlspecialchars($item['transaction_number'] ?? ''); ?>"
                               style="display:none;font-family:monospace" 
                               data-original="<?php echo htmlspecialchars($item['transaction_number'] ?? ''); ?>">
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['employee_name']); ?></strong>
                        <?php if ($item['designation']): ?>
                        <br><small style="color:#666"><?php echo htmlspecialchars($item['designation']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span style="color:#003581;font-weight:600"><?php echo htmlspecialchars($item['employee_code']); ?></span></td>
                    <td><?php echo htmlspecialchars($item['department']); ?></td>
                    <td style="text-align:right">
                        <span class="amount-display"><?php echo format_currency($item['base_salary']??0); ?></span>
                        <input type="number" step="0.01" class="form-control form-control-sm amount-input" 
                               name="base_salary" value="<?php echo $item['base_salary']??0; ?>" 
                               style="display:none;text-align:right" 
                               data-original="<?php echo $item['base_salary']??0; ?>"
                               oninput="recalculatePayable(this)">
                    </td>
                    <td style="text-align:right">
                        <span class="amount-display"><?php echo format_currency($item['allowances']??0); ?></span>
                        <input type="number" step="0.01" class="form-control form-control-sm amount-input" 
                               name="allowances" value="<?php echo $item['allowances']??0; ?>" 
                               style="display:none;text-align:right" 
                               data-original="<?php echo $item['allowances']??0; ?>"
                               oninput="recalculatePayable(this)">
                    </td>
                    <td style="text-align:right">
                        <span class="amount-display"><?php echo format_currency($item['deductions']??0); ?></span>
                        <input type="number" step="0.01" class="form-control form-control-sm amount-input" 
                               name="deductions" value="<?php echo $item['deductions']??0; ?>" 
                               style="display:none;text-align:right" 
                               data-original="<?php echo $item['deductions']??0; ?>"
                               oninput="recalculatePayable(this)">
                    </td>
                    <td style="text-align:right">
                        <strong class="payable-amount" style="color:#003581"><?php echo format_currency($item['payable']); ?></strong>
                        <input type="hidden" class="payable-value" value="<?php echo $item['payable']; ?>">
                    </td>
                    <td><span class="badge badge-<?php echo strtolower($item['status']); ?>"><?php echo $item['status']; ?></span></td>
                    <?php if ($payroll['status'] === 'Draft'): ?>
                    <td style="text-align:center">
                        <button class="btn btn-sm btn-danger delete-item-btn" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Delete Item">
                            🗑️
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <tr style="background:#e7f3ff;font-weight:bold;border-top:3px solid #003581">
                    <td colspan="5" style="text-align:right;padding:12px">
                        <strong style="font-size:15px">TOTAL:</strong>
                    </td>
                    <td style="text-align:right" id="totalBase"><?php echo format_currency($total_base); ?></td>
                    <td style="text-align:right" id="totalAllowances"><?php echo format_currency($total_allowances); ?></td>
                    <td style="text-align:right" id="totalDeductions"><?php echo format_currency($total_deductions); ?></td>
                    <td style="text-align:right" id="totalPayable">
                        <strong style="color:#003581;font-size:16px"><?php echo format_currency($total_payable); ?></strong>
                    </td>
                    <td colspan="<?php echo $payroll['status'] === 'Draft' ? '2' : '1'; ?>"></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    
    <?php if (!empty($items)): ?>
    <div style="background:#f8f9fa;padding:15px;border-radius:6px;margin-top:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px">
        <div>
            <small style="color:#666;display:block;margin-bottom:3px">Total Items</small>
            <strong style="font-size:18px"><?php echo count($items); ?></strong>
        </div>
        <div>
            <small style="color:#666;display:block;margin-bottom:3px">Total Amount</small>
            <strong style="font-size:18px;color:#003581"><?php echo format_currency($total_payable); ?></strong>
        </div>
        <div>
            <small style="color:#666;display:block;margin-bottom:3px">Average per Employee</small>
            <strong style="font-size:18px;color:#28a745"><?php echo format_currency($total_payable / count($items)); ?></strong>
        </div>
        <div>
            <small style="color:#666;display:block;margin-bottom:3px">Status</small>
            <span class="badge badge-<?php echo strtolower($payroll['status']); ?>" style="font-size:14px"><?php echo $payroll['status']; ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Activity Log Tab -->
<div id="activity-tab" class="tab-content <?php echo $active_tab === 'activity' ? 'active' : ''; ?>">
    <h3 style="margin-bottom:20px">Activity Log</h3>
    <div class="activity-timeline">
        <?php if (empty($activity_log)): ?>
            <p style="color:#666">No activity recorded yet.</p>
        <?php else: ?>
            <?php foreach ($activity_log as $activity): ?>
            <div class="activity-item">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                    <strong style="color:#003581"><?php echo htmlspecialchars($activity['action']); ?></strong>
                    <small style="color:#999"><?php echo date('d M Y, h:i A', strtotime($activity['created_at'])); ?></small>
                </div>
                <div style="color:#666">
                    By: <strong><?php echo htmlspecialchars($activity['username'] ?? 'Unknown'); ?></strong>
                    <?php if ($activity['description']): ?>
                        <br><?php echo htmlspecialchars($activity['description']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Lock Payroll Tab -->
<?php if ($payroll['status'] === 'Draft' && authz_user_can($conn, 'employees.create')): ?>
<div id="lock-tab" class="tab-content <?php echo $active_tab === 'lock' ? 'active' : ''; ?>">
    <h3 style="margin-bottom:10px">Lock Payroll</h3>
    <p style="color:#666;margin-bottom:20px">Once locked, this payroll cannot be edited or deleted. Enter payment details to finalize.</p>
    
    <div class="lock-form">
        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to lock this payroll? This action cannot be undone.');">
            <input type="hidden" name="action" value="lock_payroll">
            
            <div class="form-group">
                <label>Payment Mode <span style="color:red">*</span></label>
                <select name="transaction_mode" class="form-control" required>
                    <option value="">-- Select Payment Mode --</option>
                    <option value="Bank">Bank Transfer</option>
                    <option value="UPI">UPI</option>
                    <option value="Cash">Cash</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Transaction Reference <span style="color:red">*</span></label>
                <input type="text" name="transaction_ref" class="form-control" placeholder="e.g., UTR Number, Cheque Number, etc." required>
                <small style="color:#666">Enter transaction ID, cheque number, or reference for payment tracking</small>
            </div>
            
            <div style="background:white;padding:15px;border-radius:6px;border-left:4px solid #ffc107;margin-bottom:20px">
                <strong>Summary</strong><br>
                <small>Total Amount: <strong><?php echo format_currency($payroll['total_amount']); ?></strong></small><br>
                <small>Total Items: <strong><?php echo count($items); ?></strong></small><br>
                <small>Type: <strong><?php echo $payroll['payroll_type']; ?></strong></small>
            </div>
            
            <button type="submit" class="btn btn-primary"> Lock Payroll</button>
            <button type="button" class="btn btn-outline-secondary" onclick="switchTab('overview')">Cancel</button>
        </form>
    </div>
</div>
<?php endif; ?>

</div>

</div>
</div>

<script>
let editMode = false;
let changedItems = new Map(); // Track changes: item_id => { field: value }

function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function toggleEditMode() {
    editMode = !editMode;
    
    if (editMode) {
        // Show input fields, hide display spans
        document.querySelectorAll('.transaction-number-display, .amount-display').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.transaction-number-input, .amount-input').forEach(el => {
            el.style.display = 'block';
        });
        
        // Show/hide buttons
        document.getElementById('editModeBtn').style.display = 'none';
        document.getElementById('saveBulkBtn').style.display = 'inline-block';
        document.getElementById('cancelEditBtn').style.display = 'inline-block';
        document.getElementById('editModeAlert').style.display = 'block';
        
        // Hide delete buttons in edit mode
        document.querySelectorAll('.delete-item-btn').forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        });
    } else {
        cancelEditMode();
    }
}

function cancelEditMode() {
    editMode = false;
    changedItems.clear();
    
    // Restore all values to original
    document.querySelectorAll('.transaction-number-input, .amount-input').forEach(input => {
        input.value = input.getAttribute('data-original');
        input.closest('tr').style.background = '';
    });
    
    // Recalculate all payable amounts
    document.querySelectorAll('.item-row').forEach(row => {
        const baseInput = row.querySelector('input[name="base_salary"]');
        if (baseInput) {
            recalculatePayable(baseInput);
        }
    });
    
    // Hide input fields, show display spans
    document.querySelectorAll('.transaction-number-display, .amount-display').forEach(el => {
        el.style.display = 'block';
    });
    document.querySelectorAll('.transaction-number-input, .amount-input').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show/hide buttons
    document.getElementById('editModeBtn').style.display = 'inline-block';
    document.getElementById('saveBulkBtn').style.display = 'none';
    document.getElementById('cancelEditBtn').style.display = 'none';
    document.getElementById('editModeAlert').style.display = 'none';
    
    // Re-enable delete buttons
    document.querySelectorAll('.delete-item-btn').forEach(btn => {
        btn.disabled = false;
        btn.style.opacity = '1';
    });
}

function recalculatePayable(input) {
    const row = input.closest('tr');
    const base = parseFloat(row.querySelector('input[name="base_salary"]').value) || 0;
    const allowances = parseFloat(row.querySelector('input[name="allowances"]').value) || 0;
    const deductions = parseFloat(row.querySelector('input[name="deductions"]').value) || 0;
    
    const payable = base + allowances - deductions;
    
    // Update displayed payable amount
    row.querySelector('.payable-amount').textContent = formatCurrency(payable);
    row.querySelector('.payable-value').value = payable.toFixed(2);
    
    // Mark row as changed
    if (input.value !== input.getAttribute('data-original')) {
        row.style.background = '#fff9e6';
        trackChange(row);
    } else {
        // Check if any other field changed
        const hasChanges = Array.from(row.querySelectorAll('.transaction-number-input, .amount-input')).some(
            inp => inp.value !== inp.getAttribute('data-original')
        );
        if (!hasChanges) {
            row.style.background = '';
            const itemId = row.getAttribute('data-item-id');
            changedItems.delete(itemId);
        }
    }
    
    // Recalculate totals
    updateTotals();
}

function trackChange(row) {
    const itemId = row.getAttribute('data-item-id');
    const transactionNumber = row.querySelector('.transaction-number-input').value;
    const base = parseFloat(row.querySelector('input[name="base_salary"]').value) || 0;
    const allowances = parseFloat(row.querySelector('input[name="allowances"]').value) || 0;
    const deductions = parseFloat(row.querySelector('input[name="deductions"]').value) || 0;
    const payable = parseFloat(row.querySelector('.payable-value').value) || 0;
    
    changedItems.set(itemId, {
        transaction_number: transactionNumber,
        base_salary: base,
        allowances: allowances,
        deductions: deductions,
        payable: payable
    });
}

function updateTotals() {
    let totalBase = 0;
    let totalAllowances = 0;
    let totalDeductions = 0;
    let totalPayable = 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        totalBase += parseFloat(row.querySelector('input[name="base_salary"]').value) || 0;
        totalAllowances += parseFloat(row.querySelector('input[name="allowances"]').value) || 0;
        totalDeductions += parseFloat(row.querySelector('input[name="deductions"]').value) || 0;
        totalPayable += parseFloat(row.querySelector('.payable-value').value) || 0;
    });
    
    document.getElementById('totalBase').textContent = formatCurrency(totalBase);
    document.getElementById('totalAllowances').textContent = formatCurrency(totalAllowances);
    document.getElementById('totalDeductions').textContent = formatCurrency(totalDeductions);
    document.getElementById('totalPayable').innerHTML = '<strong style="color:#003581;font-size:16px">' + formatCurrency(totalPayable) + '</strong>';
}

function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function saveBulkChanges() {
    if (changedItems.size === 0) {
        alert('No changes to save');
        return;
    }
    
    if (!confirm(`Save changes for ${changedItems.size} item(s)?`)) {
        return;
    }
    
    // Show loading state
    const saveBtn = document.getElementById('saveBulkBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '⏳ Saving...';
    saveBtn.disabled = true;
    
    try {
        const response = await fetch('api_update_items.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                payroll_id: <?php echo $payroll_id; ?>,
                items: Object.fromEntries(changedItems)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Changes saved successfully!');
            
            // Update original values
            document.querySelectorAll('.item-row').forEach(row => {
                row.querySelectorAll('.transaction-number-input, .amount-input').forEach(input => {
                    input.setAttribute('data-original', input.value);
                });
                row.style.background = '';
            });
            
            // Update display values
            document.querySelectorAll('.item-row').forEach(row => {
                const transactionDisplay = row.querySelector('.transaction-number-display');
                const transactionInput = row.querySelector('.transaction-number-input');
                transactionDisplay.textContent = transactionInput.value;
            });
            
            changedItems.clear();
            cancelEditMode();
            
            // Reload page to refresh all data
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('❌ Error: ' + (result.message || 'Failed to save changes'));
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    } catch (error) {
        alert('❌ Network error: ' + error.message);
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

async function deleteItem(itemId) {
    if (!confirm('Are you sure you want to delete this item? This will reduce the total payroll amount.')) {
        return;
    }
    
    try {
        const response = await fetch('api_delete_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                item_id: itemId,
                payroll_id: <?php echo $payroll_id; ?>
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Item deleted successfully!');
            location.reload();
        } else {
            alert('❌ Error: ' + (result.message || 'Failed to delete item'));
        }
    } catch (error) {
        alert('❌ Network error: ' + error.message);
    }
}

function exportItems() {
    window.location.href = 'export.php?id=<?php echo $payroll_id; ?>&format=items';
}

// Track changes on input
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.transaction-number-input').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            if (this.value !== this.getAttribute('data-original')) {
                row.style.background = '#fff9e6';
                trackChange(row);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>