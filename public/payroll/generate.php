<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'employees.create');

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$payroll_type = $_POST['payroll_type'] ?? $_GET['type'] ?? '';
$month_year = $_POST['month_year'] ?? $_GET['month'] ?? date('Y-m');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create_draft') {
        $payroll_type = $_POST['payroll_type'];
        $month_year = $_POST['month_year'];
        $selected_items = $_POST['selected_items'] ?? [];
        
        if (empty($selected_items)) {
            flash_add('error', 'Please select at least one item.');
        } elseif (payroll_exists_for_month($conn, $month_year, $payroll_type)) {
            flash_add('error', 'A ' . $payroll_type . ' payroll already exists for ' . get_month_name($month_year));
        } else {
            // Calculate totals
            $total_employees = 0;
            $total_amount = 0;
            
            if ($payroll_type === 'Salary') {
                $employees = get_employees_for_payroll($conn, $month_year);
                foreach ($selected_items as $emp_id) {
                    foreach ($employees as $emp) {
                        if ($emp['id'] == $emp_id) {
                            $base = $emp['basic_salary'] ?? 0;
                            $allow = ($emp['hra'] ?? 0) + ($emp['other_allowances'] ?? 0);
                            $deduct = ($emp['pf_deduction'] ?? 0) + ($emp['professional_tax'] ?? 0) + ($emp['other_deductions'] ?? 0);
                            $total_amount += calculate_net_pay($base, $allow, $deduct);
                            $total_employees++;
                        }
                    }
                }
            } else {
                $reimbursements = get_unpaid_reimbursements($conn);
                foreach ($selected_items as $reimb_id) {
                    foreach ($reimbursements as $reimb) {
                        if ($reimb['id'] == $reimb_id) {
                            $total_amount += $reimb['amount'];
                            $total_employees++;
                        }
                    }
                }
            }
            
            // Create payroll draft
            $payroll_data = [
                'payroll_type' => $payroll_type,
                'month_year' => $month_year,
                'total_employees' => $total_employees,
                'total_amount' => $total_amount,
                'created_by' => $_SESSION['user_id']
            ];
            
            $payroll_id = create_payroll_draft($conn, $payroll_data);
            
            if ($payroll_id) {
                // Add payroll items
                if ($payroll_type === 'Salary') {
                    $employees = get_employees_for_payroll($conn, $month_year);
                    foreach ($selected_items as $emp_id) {
                        foreach ($employees as $emp) {
                            if ($emp['id'] == $emp_id) {
                                $base = $emp['basic_salary'] ?? 0;
                                $allow = ($emp['hra'] ?? 0) + ($emp['other_allowances'] ?? 0);
                                $deduct = ($emp['pf_deduction'] ?? 0) + ($emp['professional_tax'] ?? 0) + ($emp['other_deductions'] ?? 0);
                                
                                $item_data = [
                                    'payroll_id' => $payroll_id,
                                    'employee_id' => $emp_id,
                                    'item_type' => 'Salary',
                                    'base_salary' => $base,
                                    'allowances' => $allow,
                                    'deductions' => $deduct,
                                    'payable' => calculate_net_pay($base, $allow, $deduct),
                                    'attendance_days' => 26, // TODO: Get from attendance module
                                    'reimbursement_id' => null,
                                    'transaction_ref' => null,
                                    'remarks' => null,
                                    'status' => 'Pending'
                                ];
                                add_payroll_item($conn, $item_data);
                            }
                        }
                    }
                } else {
                    $reimbursements = get_unpaid_reimbursements($conn);
                    foreach ($selected_items as $reimb_id) {
                        foreach ($reimbursements as $reimb) {
                            if ($reimb['id'] == $reimb_id) {
                                $item_data = [
                                    'payroll_id' => $payroll_id,
                                    'employee_id' => $reimb['employee_id'],
                                    'item_type' => 'Reimbursement',
                                    'base_salary' => null,
                                    'allowances' => null,
                                    'deductions' => null,
                                    'payable' => $reimb['amount'],
                                    'attendance_days' => null,
                                    'reimbursement_id' => $reimb_id,
                                    'transaction_ref' => null,
                                    'remarks' => $reimb['description'] ?? null,
                                    'status' => 'Pending'
                                ];
                                add_payroll_item($conn, $item_data);
                            }
                        }
                    }
                }
                
                log_payroll_activity($conn, $payroll_id, 'Create', $_SESSION['user_id'], 'Payroll draft created with ' . $total_employees . ' items');
                flash_add('success', 'Payroll draft created successfully!');
                header('Location: view.php?id=' . $payroll_id);
                exit;
            } else {
                flash_add('error', 'Failed to create payroll draft.');
            }
        }
    }
}

$page_title = 'Generate Payroll - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.gen-header{text-align:center;margin-bottom:40px}
.gen-header h1{color:#003581;font-size:28px;margin-bottom:8px;font-weight:700}
.gen-header p{color:#666;font-size:16px;margin:0}
.wizard-container{max-width:900px;margin:0 auto}
.wizard-steps{display:flex;justify-content:center;align-items:center;margin-bottom:50px;position:relative;padding:0 20px}
.wizard-steps::before{content:'';position:absolute;top:24px;left:50%;transform:translateX(-50%);width:60%;height:3px;background:#e0e0e0;z-index:0}
.wizard-step{display:flex;flex-direction:column;align-items:center;gap:12px;position:relative;z-index:1;background:white;padding:0 15px}
.wizard-circle{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;background:#e0e0e0;color:#999;border:3px solid #e0e0e0;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:all 0.3s}
.wizard-step-label{font-size:14px;font-weight:600;color:#999;transition:color 0.3s}
.wizard-step.active .wizard-circle{background:#003581;color:white;border-color:#003581;box-shadow:0 4px 12px rgba(0,53,129,0.3)}
.wizard-step.active .wizard-step-label{color:#003581}
.wizard-step.completed .wizard-circle{background:#28a745;color:white;border-color:#28a745;box-shadow:0 4px 12px rgba(40,167,69,0.3)}
.wizard-step.completed .wizard-step-label{color:#28a745}
.wizard-content{background:white;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);border:1px solid #e8e8e8}
.step-title{font-size:22px;font-weight:700;color:#003581;margin-bottom:10px;display:flex;align-items:center;gap:10px}
.step-desc{color:#666;font-size:15px;margin-bottom:30px;line-height:1.6}
.type-selector{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:30px 0}
.type-option{border:2px solid #e0e0e0;padding:30px;border-radius:12px;cursor:pointer;transition:all 0.3s;text-align:center;background:white;position:relative}
.type-option:hover{border-color:#003581;background:#f8f9fa;transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,53,129,0.15)}
.type-option input[type="radio"]{position:absolute;opacity:0}
.type-option.selected{border-color:#003581;background:#e7f3ff;box-shadow:0 4px 16px rgba(0,53,129,0.2)}
.type-icon{font-size:48px;margin-bottom:15px}
.type-title{font-size:18px;font-weight:700;color:#003581;margin-bottom:8px}
.type-desc{font-size:14px;color:#666;line-height:1.5}
.selection-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin:30px 0}
.selection-card{border:2px solid #e0e0e0;padding:20px;border-radius:12px;cursor:pointer;transition:all 0.3s;background:white;position:relative;display:flex;align-items:flex-start;gap:12px}
.selection-card:hover{border-color:#003581;background:#f8f9fa;transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,0.1)}
.selection-card.selected{border-color:#003581;background:#e7f3ff;box-shadow:0 4px 12px rgba(0,53,129,0.2)}
.selection-card input[type="checkbox"]{width:20px;height:20px;cursor:pointer;accent-color:#003581;flex-shrink:0;margin-top:2px}
.selection-content{flex:1}
.selection-name{font-size:16px;font-weight:700;color:#003581;margin-bottom:5px}
.selection-meta{font-size:13px;color:#666;margin-bottom:3px}
.selection-amount{font-size:18px;font-weight:700;color:#003581;margin-top:8px}
.select-all-bar{background:#f8f9fa;padding:15px 20px;border-radius:8px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;border:1px solid #e0e0e0}
.select-counter{font-weight:600;color:#003581}
.action-bar{margin-top:35px;padding-top:25px;border-top:2px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
.btn-group{display:flex;gap:10px;flex-wrap:wrap}
@media (max-width:768px){.wizard-steps{flex-direction:column;gap:25px}.wizard-steps::before{display:none}.wizard-step{flex-direction:row;padding:0}.type-selector{grid-template-columns:1fr}.selection-grid{grid-template-columns:1fr}.wizard-content{padding:25px}.action-bar{flex-direction:column-reverse}.btn-group{width:100%;flex-direction:column}.btn-group .btn{width:100%;text-align:center}}
</style>

<div class="main-wrapper">
<div class="main-content">

<div class="gen-header">
<h1>✨ Generate New Payroll</h1>
<p>Create a new payroll batch for salary or reimbursement processing</p>
</div>

<div class="wizard-container">

<!-- Wizard Steps -->
<div class="wizard-steps">
    <div class="wizard-step <?php echo $step === 1 ? 'active' : ($step > 1 ? 'completed' : ''); ?>">
        <div class="wizard-circle"><?php echo $step > 1 ? '✓' : '1'; ?></div>
        <span class="wizard-step-label">Select Type</span>
    </div>
    <div class="wizard-step <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">
        <div class="wizard-circle"><?php echo $step > 2 ? '✓' : '2'; ?></div>
        <span class="wizard-step-label">Choose Items</span>
    </div>
    <div class="wizard-step <?php echo $step === 3 ? 'active' : ''; ?>">
        <div class="wizard-circle">3</div>
        <span class="wizard-step-label">Review & Create</span>
    </div>
</div>

<div class="wizard-content">

<?php if ($step === 1): ?>
    <!-- Step 1: Select Payroll Type -->
    <div class="step-title">
        <span>📋</span> Step 1: Select Payroll Type
    </div>
    <p class="step-desc">Choose the type of payroll you want to generate and select the month/year period</p>
    
    <form method="GET" action="generate.php" id="step1Form">
        <input type="hidden" name="step" value="2">
        
        <div class="type-selector">
            <label class="type-option" data-type="Salary">
                <input type="radio" name="type" value="Salary" required>
                <div class="type-icon">💼</div>
                <div class="type-title">Salary Payroll</div>
                <div class="type-desc">Process monthly salaries for employees with automatic calculations</div>
            </label>
            
            <label class="type-option" data-type="Reimbursement">
                <input type="radio" name="type" value="Reimbursement" required>
                <div class="type-icon">💰</div>
                <div class="type-title">Reimbursement Payroll</div>
                <div class="type-desc">Process approved expense claims and reimbursements</div>
            </label>
        </div>
        
        <div class="form-group" style="max-width:400px;margin:30px auto 0">
            <label style="font-weight:600;color:#495057;margin-bottom:8px;display:block">
                <span style="font-size:16px">📅</span> Select Month/Year <span style="color:red">*</span>
            </label>
            <input type="month" name="month" class="form-control" value="<?php echo date('Y-m'); ?>" required style="font-size:16px;padding:12px">
            <small style="color:#666;margin-top:5px;display:block">Choose the payroll period you want to generate</small>
        </div>
        
        <div class="action-bar">
            <a href="index.php" class="btn btn-outline-secondary" style="display:inline-flex;align-items:center;gap:8px">
                <span>←</span> Back to Dashboard
            </a>
            <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;font-size:16px">
                Next: Choose Items <span>→</span>
            </button>
        </div>
    </form>

<?php elseif ($step === 2 && !empty($payroll_type)): ?>
    <!-- Step 2: Select Employees or Reimbursements -->
    <div class="step-title">
        <span><?php echo $payroll_type === 'Salary' ? '👥' : '🧾'; ?></span> 
        Step 2: Select <?php echo $payroll_type === 'Salary' ? 'Employees' : 'Reimbursements'; ?>
    </div>
    <p class="step-desc">
        <?php echo $payroll_type === 'Salary' 
            ? 'Select employees to include in this payroll. Net pay is automatically calculated from salary details.' 
            : 'Select approved reimbursement claims to process. Only unpaid claims are shown.'; ?>
    </p>
    
    <form method="POST" action="generate.php?step=3" id="step2Form">
        <input type="hidden" name="action" value="create_draft">
        <input type="hidden" name="payroll_type" value="<?php echo htmlspecialchars($payroll_type); ?>">
        <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
        
        <?php if ($payroll_type === 'Salary'): ?>
            <?php 
            $employees = get_employees_for_payroll($conn, $month_year);
            if (empty($employees)):
            ?>
                <div class="alert alert-warning" style="text-align:center;padding:40px">
                    <div style="font-size:48px;margin-bottom:15px">👤</div>
                    <strong style="display:block;margin-bottom:8px;font-size:18px">No Active Employees Found</strong>
                    <p style="margin:0;color:#666">Please add employees to your system before generating salary payroll.</p>
                    <a href="../employee/index.php" class="btn btn-primary" style="margin-top:20px">Go to Employees</a>
                </div>
            <?php else: ?>
                <div class="select-all-bar">
                    <div>
                        <input type="checkbox" id="selectAll" style="width:18px;height:18px;cursor:pointer;accent-color:#003581;margin-right:10px">
                        <label for="selectAll" style="cursor:pointer;font-weight:600;color:#003581">Select All Employees</label>
                    </div>
                    <span class="select-counter">
                        <span id="selectedCount">0</span> of <?php echo count($employees); ?> selected
                    </span>
                </div>
                
                <div class="selection-grid">
                    <?php foreach ($employees as $emp): ?>
                        <?php
                        $base = $emp['basic_salary'] ?? 0;
                        $hra = $emp['hra'] ?? 0;
                        $conveyance = $emp['conveyance_allowance'] ?? 0;
                        $medical = $emp['medical_allowance'] ?? 0;
                        $special = $emp['special_allowance'] ?? 0;
                        $gross = $emp['gross_salary'] ?? 0;
                        $net = $gross > 0 ? $gross : ($base + $hra + $conveyance + $medical + $special);
                        ?>
                        <label class="selection-card">
                            <input type="checkbox" name="selected_items[]" value="<?php echo $emp['id']; ?>" class="employee-checkbox">
                            <div class="selection-content">
                                <div class="selection-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                                <div class="selection-meta">
                                    <span style="color:#003581;font-weight:600"><?php echo htmlspecialchars($emp['employee_code']); ?></span>
                                    <span style="margin:0 8px;color:#ccc">•</span>
                                    <?php echo htmlspecialchars($emp['department']); ?>
                                </div>
                                <div class="selection-meta" style="color:#888">
                                    <?php echo htmlspecialchars($emp['designation']); ?>
                                </div>
                                <div class="selection-amount">₹<?php echo number_format($net, 2); ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <?php 
            $reimbursements = get_unpaid_reimbursements($conn);
            if (empty($reimbursements)):
            ?>
                <div class="alert alert-warning" style="text-align:center;padding:40px">
                    <div style="font-size:48px;margin-bottom:15px">💸</div>
                    <strong style="display:block;margin-bottom:8px;font-size:18px">No Unpaid Reimbursements Found</strong>
                    <p style="margin:0;color:#666">All reimbursement claims have been processed or there are no approved claims.</p>
                    <a href="../reimbursements/index.php" class="btn btn-primary" style="margin-top:20px">Go to Reimbursements</a>
                </div>
            <?php else: ?>
                <div class="select-all-bar">
                    <div>
                        <input type="checkbox" id="selectAll" style="width:18px;height:18px;cursor:pointer;accent-color:#003581;margin-right:10px">
                        <label for="selectAll" style="cursor:pointer;font-weight:600;color:#003581">Select All Claims</label>
                    </div>
                    <span class="select-counter">
                        <span id="selectedCount">0</span> of <?php echo count($reimbursements); ?> selected
                    </span>
                </div>
                
                <div class="selection-grid">
                    <?php foreach ($reimbursements as $reimb): ?>
                        <label class="selection-card">
                            <input type="checkbox" name="selected_items[]" value="<?php echo $reimb['id']; ?>" class="employee-checkbox">
                            <div class="selection-content">
                                <div class="selection-name"><?php echo htmlspecialchars($reimb['employee_name']); ?></div>
                                <div class="selection-meta">
                                    <span style="color:#003581;font-weight:600"><?php echo htmlspecialchars($reimb['employee_code']); ?></span>
                                    <span style="margin:0 8px;color:#ccc">•</span>
                                    <?php echo htmlspecialchars($reimb['category'] ?? 'Expense'); ?>
                                </div>
                                <div class="selection-meta" style="color:#888">
                                    Claimed: <?php echo date('d M Y', strtotime($reimb['date_submitted'])); ?>
                                </div>
                                <div class="selection-amount">₹<?php echo number_format($reimb['amount'], 2); ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (($payroll_type === 'Salary' && !empty($employees)) || ($payroll_type === 'Reimbursement' && !empty($reimbursements))): ?>
        <div class="action-bar">
            <div class="btn-group">
                <a href="generate.php?step=1" class="btn btn-outline-secondary" style="display:inline-flex;align-items:center;gap:8px">
                    <span>←</span> Back
                </a>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
            <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;font-size:16px" id="createBtn" disabled>
                <span>✓</span> Create Payroll Draft
            </button>
        </div>
        <?php endif; ?>
    </form>

<?php else: ?>
    <div class="alert alert-danger">Invalid step or missing parameters. Please start over.</div>
    <a href="generate.php?step=1" class="btn btn-primary">Start Over</a>
<?php endif; ?>

</div>

</div>
</div>

<script>
// Type selector functionality - visual card selection
document.querySelectorAll('.type-option input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('selected'));
        this.closest('.type-option').classList.add('selected');
    });
});

// Type card click handler
document.querySelectorAll('.type-option').forEach(card => {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        }
    });
});

// Initialize selected type on load
document.addEventListener('DOMContentLoaded', function() {
    const checkedRadio = document.querySelector('.type-option input[type="radio"]:checked');
    if (checkedRadio) {
        checkedRadio.closest('.type-option').classList.add('selected');
    }
});

// Selection card functionality
function updateSelectionCounter() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    const checkedCount = document.querySelectorAll('.employee-checkbox:checked').length;
    const counterElement = document.getElementById('selectedCount');
    const createBtn = document.getElementById('createBtn');
    
    if (counterElement) {
        counterElement.textContent = checkedCount;
    }
    
    if (createBtn) {
        createBtn.disabled = checkedCount === 0;
    }
    
    // Update select all checkbox state
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox && checkboxes.length > 0) {
        selectAllCheckbox.checked = checkedCount === checkboxes.length;
        selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }
}

// Auto-select card on checkbox change
document.querySelectorAll('.selection-card input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            this.closest('.selection-card').classList.add('selected');
        } else {
            this.closest('.selection-card').classList.remove('selected');
        }
        updateSelectionCounter();
    });
});

// Make entire card clickable
document.querySelectorAll('.selection-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') {
            const checkbox = this.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            }
        }
    });
});

// Select all functionality
const selectAllCheckbox = document.getElementById('selectAll');
if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.employee-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
            checkbox.dispatchEvent(new Event('change'));
        });
    });
}

// Initialize counter on page load
updateSelectionCounter();
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>