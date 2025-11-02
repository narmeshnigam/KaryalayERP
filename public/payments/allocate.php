<?php
/**
 * Payments Module - Allocate Payment
 * Allocate unallocated payment amount to invoices
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($payment_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch payment details
$payment = get_payment_by_id($conn, $payment_id);
if (!$payment) {
    header('Location: index.php');
    exit;
}

// Check if payment has unallocated balance
if ($payment['unallocated_amount'] <= 0) {
    $_SESSION['flash_error'] = 'This payment has no unallocated balance.';
    header('Location: view.php?id=' . $payment_id);
    exit;
}

// Get pending invoices for this client
$pending_invoices = get_pending_invoices_for_client($conn, $payment['client_id']);

// Page title
$page_title = "Allocate Payment - " . $payment['payment_no'];
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.form-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.invoices-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
.invoices-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #003581;
    border-bottom: 2px solid #dee2e6;
}
.invoices-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}
.invoices-table tr:hover {
    background: #f8f9fa;
}
.checkbox-cell {
    width: 40px;
    text-align: center;
}
.amount-input {
    width: 120px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
.amount-input:disabled {
    background: #f5f5f5;
    cursor: not-allowed;
}
.amount-input:focus {
    outline: none;
    border-color: #003581;
    box-shadow: 0 0 0 3px rgba(0, 53, 129, 0.1);
}
.allocation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.allocation-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid #e9ecef;
}
.allocation-label {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 8px;
}
.allocation-value {
    font-size: 24px;
    font-weight: 700;
    color: #003581;
}
.no-invoices {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}
.no-invoices h3 {
    color: #003581;
    margin-bottom: 10px;
}
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.badge-unpaid { 
    background: #f8d7da; 
    color: #721c24; 
}
.badge-partial { 
    background: #fff3cd; 
    color: #856404; 
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h1>💰 Allocate Payment</h1>
          <p>Assign payment amount to pending invoices</p>
        </div>
        <div>
          <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-accent" style="text-decoration: none;">← Back to Payment</a>
        </div>
      </div>
    </div>

    <!-- Payment Summary Card -->
    <div class="card" style="margin-bottom: 25px;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div style="border-right: 1px solid #e9ecef; padding-right: 20px;">
          <div style="color: #6c757d; font-size: 13px; margin-bottom: 5px;">Payment Number</div>
          <div style="font-size: 18px; font-weight: 600; color: #003581;"><?php echo htmlspecialchars($payment['payment_no']); ?></div>
        </div>
        <div style="border-right: 1px solid #e9ecef; padding-right: 20px;">
          <div style="color: #6c757d; font-size: 13px; margin-bottom: 5px;">Total Amount</div>
          <div style="font-size: 18px; font-weight: 600;">₹<?php echo number_format($payment['amount_received'], 2); ?></div>
        </div>
        <div style="border-right: 1px solid #e9ecef; padding-right: 20px;">
          <div style="color: #6c757d; font-size: 13px; margin-bottom: 5px;">Already Allocated</div>
          <div style="font-size: 18px; font-weight: 600; color: #28a745;">₹<?php echo number_format($payment['total_allocated'], 2); ?></div>
        </div>
        <div>
          <div style="color: #6c757d; font-size: 13px; margin-bottom: 5px;">Available Balance</div>
          <div style="font-size: 18px; font-weight: 600; color: #dc3545;">₹<?php echo number_format($payment['unallocated_amount'], 2); ?></div>
        </div>
      </div>
    </div>

    <!-- Instructions -->
    <div class="alert alert-info" style="margin-bottom: 25px; background: #e3f2fd; border-left: 4px solid #003581; color: #003581; padding: 15px; border-radius: 4px;">
      <strong>📌 Instructions:</strong> Select invoices and enter allocation amounts. The total cannot exceed the available balance of <strong>₹<?php echo number_format($payment['unallocated_amount'], 2); ?></strong>.
    </div>

    <!-- Error Container -->
    <div id="errorContainer" class="alert alert-error" style="display: none; margin-bottom: 25px;"></div>

    <?php if (empty($pending_invoices)): ?>
    <!-- No Invoices -->
    <div class="card">
      <div class="no-invoices">
        <div style="font-size: 80px; margin-bottom: 20px;">📄</div>
        <h3>No Pending Invoices</h3>
        <p>This client has no unpaid or partially paid invoices to allocate to.</p>
        <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-accent" style="margin-top: 20px; text-decoration: none;">← Back to Payment</a>
      </div>
    </div>

    <?php else: ?>
    <!-- Allocation Summary -->
    <div class="card" style="margin-bottom: 25px; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
      <h3 style="margin: 0 0 20px 0; font-size: 16px;">📊 Allocation Summary</h3>
      <div class="allocation-grid" style="gap: 20px;">
        <div style="text-align: center; background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px;">
          <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Total Allocating</div>
          <div style="font-size: 28px; font-weight: 700;" id="totalAllocating">₹0.00</div>
        </div>
        <div style="text-align: center; background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px;">
          <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Remaining Balance</div>
          <div style="font-size: 28px; font-weight: 700;" id="remainingBalance">₹<?php echo number_format($payment['unallocated_amount'], 2); ?></div>
        </div>
        <div style="text-align: center; background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px;">
          <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Selected Invoices</div>
          <div style="font-size: 28px; font-weight: 700;" id="selectedCount">0</div>
        </div>
      </div>
    </div>

    <!-- Form -->
    <form id="allocateForm">
      <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
      
      <!-- Invoices Table Section -->
      <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
          📄 Pending Invoices for <?php echo htmlspecialchars($payment['client_name']); ?>
        </h3>
        
        <div style="overflow-x: auto;">
          <table class="invoices-table">
            <thead>
              <tr>
                <th class="checkbox-cell">
                  <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
                </th>
                <th>Invoice No</th>
                <th>Invoice Date</th>
                <th>Total Amount</th>
                <th>Paid Amount</th>
                <th>Balance Due</th>
                <th>Status</th>
                <th style="text-align: center;">Allocate Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending_invoices as $invoice): ?>
              <tr class="invoice-row">
                <td class="checkbox-cell">
                  <input type="checkbox" 
                         class="invoice-checkbox" 
                         data-invoice-id="<?php echo $invoice['id']; ?>"
                         data-balance="<?php echo $invoice['balance_due']; ?>"
                         style="width: 18px; height: 18px; cursor: pointer;">
                </td>
                <td>
                  <a href="../invoices/view.php?id=<?php echo $invoice['id']; ?>" target="_blank" style="color: #003581; font-weight: 600; text-decoration: none;">
                    <?php echo htmlspecialchars($invoice['invoice_no']); ?>
                  </a>
                </td>
                <td><?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></td>
                <td style="font-weight: 600;">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                <td style="color: #28a745;">₹<?php echo number_format($invoice['paid_amount'], 2); ?></td>
                <td style="color: #dc3545; font-weight: 600;">₹<?php echo number_format($invoice['balance_due'], 2); ?></td>
                <td>
                  <span class="badge badge-<?php echo $invoice['payment_status'] == 'unpaid' ? 'unpaid' : 'partial'; ?>">
                    <?php echo $invoice['payment_status'] == 'unpaid' ? 'Unpaid' : 'Partially Paid'; ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <input type="number" 
                         class="amount-input" 
                         data-invoice-id="<?php echo $invoice['id']; ?>"
                         step="0.01" 
                         min="0.01"
                         max="<?php echo $invoice['balance_due']; ?>"
                         placeholder="0.00"
                         disabled>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #e9ecef;">
        <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-accent" style="text-decoration: none;">← Cancel</a>
        <button type="submit" class="btn" id="submitBtn" disabled>💰 Allocate Payment</button>
      </div>
    </form>
    <?php endif; ?>

<script>
const availableBalance = <?php echo $payment['unallocated_amount']; ?>;
let totalAllocating = 0;

function showError(message) {
    const errorContainer = document.getElementById('errorContainer');
    errorContainer.textContent = message;
    errorContainer.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function hideError() {
    document.getElementById('errorContainer').style.display = 'none';
}

function updateSummary() {
    totalAllocating = 0;
    let selectedCount = 0;

    document.querySelectorAll('.amount-input:not(:disabled)').forEach(input => {
        const amount = parseFloat(input.value) || 0;
        if (amount > 0) {
            totalAllocating += amount;
            selectedCount++;
        }
    });

    const remaining = availableBalance - totalAllocating;

    document.getElementById('totalAllocating').textContent = '₹' + totalAllocating.toFixed(2);
    document.getElementById('remainingBalance').textContent = '₹' + remaining.toFixed(2);
    document.getElementById('selectedCount').textContent = selectedCount;

    // Enable/disable submit button
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = selectedCount === 0 || remaining < 0;

    // Show error if over-allocated
    if (remaining < 0) {
        showError('Total allocation exceeds available balance by ₹' + Math.abs(remaining).toFixed(2));
    } else {
        hideError();
    }
}

// Handle checkbox changes
document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const invoiceId = this.dataset.invoiceId;
        const balance = parseFloat(this.dataset.balance);
        const amountInput = document.querySelector(`.amount-input[data-invoice-id="${invoiceId}"]`);

        if (this.checked) {
            amountInput.disabled = false;
            // Auto-fill with lesser of balance or available
            const autoFill = Math.min(balance, availableBalance - totalAllocating);
            amountInput.value = autoFill.toFixed(2);
        } else {
            amountInput.disabled = true;
            amountInput.value = '';
        }

        updateSummary();
    });
});

// Handle amount input changes
document.querySelectorAll('.amount-input').forEach(input => {
    input.addEventListener('input', updateSummary);
});

// Handle select all
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
        cb.dispatchEvent(new Event('change'));
    });
});

// Handle form submission
document.getElementById('allocateForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    hideError();

    // Collect allocations
    const allocations = [];
    document.querySelectorAll('.invoice-checkbox:checked').forEach(checkbox => {
        const invoiceId = parseInt(checkbox.dataset.invoiceId);
        const amountInput = document.querySelector(`.amount-input[data-invoice-id="${invoiceId}"]`);
        const amount = parseFloat(amountInput.value) || 0;

        if (amount > 0) {
            allocations.push({
                invoice_id: invoiceId,
                allocated_amount: amount
            });
        }
    });

    if (allocations.length === 0) {
        showError('Please select at least one invoice and enter allocation amount.');
        return;
    }

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Allocating...';

    try {
        const response = await fetch('../api/payments/allocate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                payment_id: <?php echo $payment_id; ?>,
                allocations: allocations
            })
        });

        const result = await response.json();

        if (result.success) {
            window.location.href = 'view.php?id=<?php echo $payment_id; ?>&allocated=1';
        } else {
            showError(result.message || 'Failed to allocate payment');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Allocate Payment';
        }
    } catch (error) {
        showError('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Allocate Payment';
    }
});
</script>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
