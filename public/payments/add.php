<?php
/**
 * Payments Module - Add New Payment
 * Record payment with optional invoice allocation
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payments_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get clients
$clients = get_active_clients($conn);

// Default values
$default_payment_date = date('Y-m-d');

$page_title = 'Add Payment - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .form-section { background: white; padding: 24px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
    .section-header { font-size: 16px; font-weight: 600; color: #003581; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #e3f2fd; }
    .invoice-allocation-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .invoice-allocation-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #003581; border-bottom: 2px solid #dee2e6; }
    .invoice-allocation-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
    .invoice-allocation-table input[type="number"] { width: 150px; padding: 6px 8px; border: 1px solid #ced4da; border-radius: 4px; }
    .invoice-allocation-table input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
    .summary-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; }
    .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
    .summary-row.total { font-size: 18px; font-weight: 700; color: #003581; border-top: 2px solid #003581; margin-top: 8px; padding-top: 12px; }
    #pendingInvoicesSection { display: none; margin-top: 20px; }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>💰 Add New Payment</h1>
                    <p>Record payment receipt and allocate to invoices</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Back to Payments</a>
                </div>
            </div>
        </div>

        <!-- Error Container -->
        <div id="errorContainer" style="display: none; margin-bottom: 20px;">
            <div class="alert alert-error">
                <strong>❌ Error</strong><br>
                <div id="errorMessage" style="margin: 0; line-height: 1.5;"></div>
            </div>
        </div>

        <!-- Form -->
        <form id="paymentForm" method="POST" action="<?php echo APP_URL; ?>/public/api/payments/add.php" enctype="multipart/form-data">
            
            <!-- Basic Information Section -->
            <div class="form-section">
                <h3 class="section-header">📋 Payment Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Client *</label>
                        <select name="client_id" id="client_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Payment Date *</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?php echo $default_payment_date; ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required 
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Payment Mode *</label>
                        <select name="payment_mode" id="payment_mode" required style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer" selected>Bank Transfer</option>
                            <option value="UPI">UPI</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Amount Received *</label>
                        <input type="number" name="amount_received" id="amount_received" step="0.01" min="0.01" required 
                               placeholder="0.00"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Reference No</label>
                        <input type="text" name="reference_no" id="reference_no" placeholder="UTR/Cheque No/Ref ID" 
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Payment Proof (Optional)</label>
                        <input type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png" 
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                        <small style="color: #6c757d;">Max 5MB - PDF, JPG, PNG</small>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Remarks</label>
                    <textarea name="remarks" id="remarks" rows="3" placeholder="Any additional notes..."
                              style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;"></textarea>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="allocateNowCheckbox" style="width: 20px; height: 20px; margin-right: 10px;">
                        <span style="font-weight: 600;">Allocate to invoices now</span>
                    </label>
                </div>
            </div>

            <!-- Invoice Allocation Section (Hidden by default) -->
            <div id="pendingInvoicesSection" class="form-section">
                <h3 class="section-header">📄 Allocate to Pending Invoices</h3>
                <p style="color: #6c757d; margin-bottom: 16px;">Select invoices and enter allocation amounts</p>
                
                <div id="invoicesTableContainer">
                    <p style="text-align: center; color: #6c757d; padding: 20px;">Select a client to see pending invoices</p>
                </div>

                <!-- Allocation Summary -->
                <div class="summary-box" id="allocationSummary" style="display: none;">
                    <div class="summary-row">
                        <span>Payment Amount:</span>
                        <span id="summaryPaymentAmount">₹0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Total Allocated:</span>
                        <span id="summaryAllocated">₹0.00</span>
                    </div>
                    <div class="summary-row total">
                        <span>Remaining (Unallocated):</span>
                        <span id="summaryRemaining">₹0.00</span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 16px; border-top: 2px solid #e9ecef;">
                <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Cancel</a>
                <button type="submit" class="btn">💾 Save Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
let pendingInvoices = [];

// Helper function to show error on page
function showError(message) {
    const errorContainer = document.getElementById('errorContainer');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = message;
    errorContainer.style.display = 'block';
    errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Helper function to hide error
function hideError() {
    document.getElementById('errorContainer').style.display = 'none';
}

// Toggle invoice allocation section
document.getElementById('allocateNowCheckbox').addEventListener('change', function() {
    const section = document.getElementById('pendingInvoicesSection');
    if (this.checked) {
        section.style.display = 'block';
        loadPendingInvoices();
    } else {
        section.style.display = 'none';
    }
});

// Load pending invoices when client changes
document.getElementById('client_id').addEventListener('change', function() {
    const allocateCheckbox = document.getElementById('allocateNowCheckbox');
    if (allocateCheckbox.checked && this.value) {
        loadPendingInvoices();
    }
});

// Load pending invoices for selected client
function loadPendingInvoices() {
    const clientId = document.getElementById('client_id').value;
    if (!clientId) {
        document.getElementById('invoicesTableContainer').innerHTML = 
            '<p style="text-align: center; color: #6c757d; padding: 20px;">Please select a client first</p>';
        return;
    }

    fetch(`../api/payments/get_pending_invoices.php?client_id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                pendingInvoices = data.invoices;
                renderInvoicesTable();
            } else {
                document.getElementById('invoicesTableContainer').innerHTML = 
                    '<p style="text-align: center; color: #dc3545; padding: 20px;">Error loading invoices</p>';
            }
        })
        .catch(error => {
            document.getElementById('invoicesTableContainer').innerHTML = 
                '<p style="text-align: center; color: #dc3545; padding: 20px;">Error loading invoices</p>';
        });
}

// Render invoices table
function renderInvoicesTable() {
    if (pendingInvoices.length === 0) {
        document.getElementById('invoicesTableContainer').innerHTML = 
            '<p style="text-align: center; color: #6c757d; padding: 20px;">No pending invoices for this client</p>';
        return;
    }

    let html = `
        <table class="invoice-allocation-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Select</th>
                    <th>Invoice No</th>
                    <th>Issue Date</th>
                    <th>Total Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Allocate Amount</th>
                </tr>
            </thead>
            <tbody>
    `;

    pendingInvoices.forEach((invoice, index) => {
        html += `
            <tr>
                <td style="text-align: center;">
                    <input type="checkbox" class="invoice-checkbox" data-index="${index}" 
                           onchange="toggleInvoiceAllocation(${index})">
                </td>
                <td><strong>${invoice.invoice_no}</strong></td>
                <td>${formatDate(invoice.issue_date)}</td>
                <td>₹${parseFloat(invoice.total_amount).toFixed(2)}</td>
                <td>₹${parseFloat(invoice.amount_paid).toFixed(2)}</td>
                <td style="color: #dc3545; font-weight: 600;">₹${parseFloat(invoice.balance).toFixed(2)}</td>
                <td>
                    <input type="number" 
                           class="allocation-input" 
                           data-invoice-id="${invoice.id}" 
                           data-balance="${invoice.balance}"
                           step="0.01" 
                           min="0" 
                           max="${invoice.balance}" 
                           placeholder="0.00"
                           disabled
                           oninput="updateAllocationSummary()">
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    document.getElementById('invoicesTableContainer').innerHTML = html;
    document.getElementById('allocationSummary').style.display = 'block';
    updateAllocationSummary();
}

// Toggle invoice allocation input
function toggleInvoiceAllocation(index) {
    const checkbox = document.querySelector(`.invoice-checkbox[data-index="${index}"]`);
    const input = document.querySelector(`.allocation-input[data-invoice-id="${pendingInvoices[index].id}"]`);
    
    if (checkbox.checked) {
        input.disabled = false;
        input.value = parseFloat(pendingInvoices[index].balance).toFixed(2);
    } else {
        input.disabled = true;
        input.value = '';
    }
    
    updateAllocationSummary();
}

// Update allocation summary
function updateAllocationSummary() {
    const paymentAmount = parseFloat(document.getElementById('amount_received').value) || 0;
    let totalAllocated = 0;

    document.querySelectorAll('.allocation-input:not([disabled])').forEach(input => {
        totalAllocated += parseFloat(input.value) || 0;
    });

    const remaining = paymentAmount - totalAllocated;

    document.getElementById('summaryPaymentAmount').textContent = '₹' + paymentAmount.toFixed(2);
    document.getElementById('summaryAllocated').textContent = '₹' + totalAllocated.toFixed(2);
    document.getElementById('summaryRemaining').textContent = '₹' + remaining.toFixed(2);
    document.getElementById('summaryRemaining').style.color = remaining < 0 ? '#dc3545' : '#28a745';
}

// Update summary when amount changes
document.getElementById('amount_received').addEventListener('input', updateAllocationSummary);

// Format date helper
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Form submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    hideError();

    // Validate payment amount
    const paymentAmount = parseFloat(document.getElementById('amount_received').value) || 0;
    if (paymentAmount <= 0) {
        showError('Payment amount must be greater than zero.');
        return;
    }

    // Validate allocations if checkbox is checked
    const allocateNow = document.getElementById('allocateNowCheckbox').checked;
    if (allocateNow) {
        let totalAllocated = 0;
        const allocations = [];

        document.querySelectorAll('.allocation-input:not([disabled])').forEach(input => {
            const amount = parseFloat(input.value) || 0;
            if (amount > 0) {
                const invoiceId = input.getAttribute('data-invoice-id');
                const balance = parseFloat(input.getAttribute('data-balance'));
                
                if (amount > balance) {
                    showError(`Allocated amount for an invoice exceeds its balance.`);
                    return;
                }
                
                totalAllocated += amount;
                allocations.push({
                    invoice_id: invoiceId,
                    amount: amount
                });
            }
        });

        if (totalAllocated > paymentAmount) {
            showError('Total allocated amount exceeds payment amount.');
            return;
        }
    }

    // Submit form
    const formData = new FormData(this);
    
    // Add allocations if applicable
    if (allocateNow) {
        const allocations = [];
        document.querySelectorAll('.allocation-input:not([disabled])').forEach(input => {
            const amount = parseFloat(input.value) || 0;
            if (amount > 0) {
                allocations.push({
                    invoice_id: input.getAttribute('data-invoice-id'),
                    amount: amount
                });
            }
        });
        formData.append('allocations', JSON.stringify(allocations));
    }

    const actionURL = new URL(this.getAttribute('action'), window.location.href).href;

    fetch(actionURL, {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            showError(`Server error (${response.status}): ${text.substring(0, 500)}`);
            return;
        }
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            showError('Server error: ' + text.substring(0, 500));
            return;
        }
        if (data.success) {
            window.location.href = 'view.php?id=' + data.payment_id + '&created=1';
        } else {
            showError(data.message || 'An error occurred while saving the payment.');
        }
    })
    .catch(error => {
        showError('An error occurred while saving the payment. ' + (error && error.message ? error.message : ''));
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
