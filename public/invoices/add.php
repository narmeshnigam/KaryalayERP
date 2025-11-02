<?php
/**
 * Invoices Module - Add New Invoice
 * Create a new invoice with line items and optional inventory deduction
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!invoices_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get clients and items for dropdowns
$clients = get_active_clients($conn);

// Get catalog items
$items_result = $conn->query("SELECT id, sku, name, type, base_price, tax_percent, NULL AS unit FROM items_master WHERE status = 'Active' ORDER BY name ASC");
$catalog_items = [];
if ($items_result) {
    while ($row = $items_result->fetch_assoc()) {
        $catalog_items[] = $row;
    }
    $items_result->free();
}

// Default values
$default_issue_date = date('Y-m-d');
$default_payment_terms = 'NET 30';
$default_due_date = date('Y-m-d', strtotime('+30 days'));

$page_title = 'Add Invoice - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    .items-table th { background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border-bottom: 2px solid #dee2e6; }
    .items-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
    .items-table input, .items-table select, .items-table textarea { width: 100%; padding: 6px 8px; border: 1px solid #ced4da; border-radius: 4px; }
    .items-table textarea { resize: vertical; min-height: 40px; }
    .btn-remove { background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .btn-remove:hover { background: #c82333; }
    .totals-panel { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; }
    .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
    .total-row.grand { font-size: 18px; font-weight: 700; color: #003581; border-top: 2px solid #003581; margin-top: 8px; padding-top: 12px; }
    .autocomplete-results { position: absolute; background: white; border: 1px solid #ced4da; border-top: none; max-height: 200px; overflow-y: auto; width: calc(100% - 20px); z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .autocomplete-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #f8f9fa; }
    .autocomplete-item:hover { background: #f8f9fa; }
    .form-section { background: white; padding: 24px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
    .section-header { font-size: 16px; font-weight: 600; color: #003581; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #e3f2fd; }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>➕ Add New Invoice</h1>
                    <p>Create a new invoice for a client</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-accent">← Back to Invoices</a>
                </div>
            </div>
        </div>

        <!-- Error Container -->
        <div id="errorContainer" style="display: none; margin-bottom: 20px;">
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 16px; color: #721c24;">
                <h4 style="margin: 0 0 10px 0; font-weight: 600;">⚠️ Error</h4>
                <div id="errorMessage" style="margin: 0; line-height: 1.5;"></div>
            </div>
        </div>

        <!-- Form -->
    <form id="invoiceForm" method="POST" action="<?php echo APP_URL; ?>/public/api/invoices/add.php" enctype="multipart/form-data">
            <!-- Basic Information Section -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📋 Basic Information
                </h3>
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
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Issue Date *</label>
                        <input type="date" name="issue_date" id="issue_date" value="<?php echo $default_issue_date; ?>" required 
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Payment Terms</label>
                        <select name="payment_terms" id="payment_terms" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">Select Terms</option>
                            <option value="NET 7">NET 7 Days</option>
                            <option value="NET 15">NET 15 Days</option>
                            <option value="NET 30" selected>NET 30 Days</option>
                            <option value="NET 45">NET 45 Days</option>
                            <option value="NET 60">NET 60 Days</option>
                            <option value="NET 90">NET 90 Days</option>
                            <option value="Custom">Custom</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Due Date</label>
                        <input type="date" name="due_date" id="due_date" value="<?php echo $default_due_date; ?>"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Currency</label>
                        <select name="currency" id="currency" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="INR" selected>INR (₹)</option>
                            <option value="USD">USD ($)</option>
                            <option value="EUR">EUR (€)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Attachment (PO/WO)</label>
                        <input type="file" name="attachment" id="attachment" accept=".pdf,.doc,.docx,.png,.jpg"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                        <small style="color: #6c757d; font-size: 12px;">Max 5MB (PDF, DOC, Image)</small>
                    </div>
                </div>
            </div>

            <!-- Line Items Section -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    🛒 Invoice Items
                </h3>
                
                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Item *</th>
                            <th style="width: 20%;">Description</th>
                            <th style="width: 8%;">Qty *</th>
                            <th style="width: 8%;">Unit</th>
                            <th style="width: 10%;">Price *</th>
                            <th style="width: 8%;">Tax %</th>
                            <th style="width: 10%;">Discount</th>
                            <th style="width: 10%;">Total</th>
                            <th style="width: 6%;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <!-- Dynamic rows will be added here -->
                    </tbody>
                </table>

                <button type="button" onclick="addItemRow()" class="btn btn-accent" style="margin-top: 10px;">
                    ➕ Add Item
                </button>
            </div>

            <!-- Totals Panel -->
            <div style="display: grid; grid-template-columns: 1fr 400px; gap: 20px;">
                <!-- Notes and Terms -->
                <div class="card" style="margin-bottom: 25px;">
                    <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                        📝 Additional Information
                    </h3>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Notes</label>
                        <textarea name="notes" rows="4" placeholder="Any additional notes for this invoice..."
                                  style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;"></textarea>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057;">Terms & Conditions</label>
                        <textarea name="terms" rows="4" placeholder="Payment terms and conditions..."
                                  style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;"></textarea>
                    </div>
                </div>

                <!-- Totals Calculation -->
                <div class="card totals-panel">
                    <h3 style="color: #003581; margin-bottom: 12px; border-bottom: 2px solid #003581; padding-bottom: 8px;">📊 Calculation Summary</h3>
                    
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="display_subtotal">₹0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Tax:</span>
                        <span id="display_tax">₹0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Discount:</span>
                        <span id="display_discount">₹0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Round Off:</span>
                        <span id="display_roundoff">₹0.00</span>
                    </div>
                    <div class="total-row grand">
                        <span>Grand Total:</span>
                        <span id="display_grand_total">₹0.00</span>
                    </div>

                    <!-- Hidden fields for submission -->
                    <input type="hidden" name="subtotal" id="subtotal" value="0">
                    <input type="hidden" name="tax_amount" id="tax_amount" value="0">
                    <input type="hidden" name="discount_amount" id="discount_amount" value="0">
                    <input type="hidden" name="round_off" id="round_off" value="0">
                    <input type="hidden" name="total_amount" id="total_amount" value="0">
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="text-align: center; padding: 20px 0;">
                <button type="submit" name="action" value="issue" class="btn" style="padding: 12px 48px; font-size: 16px;">
                    🚀 Save & Issue Invoice
                </button>
                <button type="submit" name="action" value="draft" class="btn btn-accent" style="padding: 12px 32px; font-size: 16px; margin-left: 12px;">
                    💾 Save as Draft
                </button>
                <a href="index.php" class="btn btn-accent" style="padding: 12px 32px; font-size: 16px; margin-left: 12px; text-decoration: none;">
                    ❌ Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Catalog items data
const catalogItems = <?php echo json_encode($catalog_items); ?>;
let itemRowCounter = 0;

// Add initial row
document.addEventListener('DOMContentLoaded', function() {
    addItemRow();
    const currencySelect = document.getElementById('currency');
    if (currencySelect) {
        currencySelect.addEventListener('change', calculateGrandTotal);
    }
});

function getCurrencySymbol() {
    const value = document.getElementById('currency').value;
    const map = { INR: '₹', USD: '$', EUR: '€' };
    return map[value] || (value ? value + ' ' : '');
}

// Add item row
function addItemRow() {
    const tbody = document.getElementById('itemsTableBody');
    const rowId = ++itemRowCounter;
    
    const row = document.createElement('tr');
    row.id = `item-row-${rowId}`;
    row.innerHTML = `
        <td style="position: relative;">
            <input type="text" 
                   class="item-search" 
                   id="item-search-${rowId}"
                   placeholder="Search item..."
                   autocomplete="off"
                   onkeyup="searchItems(${rowId})"
                   onfocus="searchItems(${rowId})"
                   tabindex="0">
            <div class="autocomplete-results" id="autocomplete-${rowId}" style="display: none;"></div>
            <input type="hidden" name="items[${rowId}][item_id]" id="item-id-${rowId}">
        </td>
        <td>
            <textarea name="items[${rowId}][description]" id="item-desc-${rowId}" rows="2" tabindex="0"></textarea>
        </td>
        <td>
            <input type="number" name="items[${rowId}][quantity]" id="item-qty-${rowId}" 
                   value="1" step="0.01" min="0.01" required onchange="calculateLineTotal(${rowId})">
        </td>
        <td>
            <input type="text" name="items[${rowId}][unit]" id="item-unit-${rowId}" 
                   placeholder="pcs">
        </td>
        <td>
            <input type="number" name="items[${rowId}][unit_price]" id="item-price-${rowId}" 
                   value="0" step="0.01" min="0" required onchange="calculateLineTotal(${rowId})">
        </td>
        <td>
            <input type="number" name="items[${rowId}][tax_percent]" id="item-tax-${rowId}" 
                   value="0" step="0.01" min="0" max="100" onchange="calculateLineTotal(${rowId})">
        </td>
        <td>
            <input type="number" name="items[${rowId}][discount]" id="item-discount-${rowId}" 
                   value="0" step="0.01" min="0" onchange="calculateLineTotal(${rowId})">
            <input type="hidden" name="items[${rowId}][discount_type]" value="Amount">
        </td>
        <td>
            <input type="number" id="item-total-${rowId}" value="0" step="0.01" readonly 
                   style="background: #f8f9fa; font-weight: 600;">
            <input type="hidden" name="items[${rowId}][line_total]" id="item-line-total-${rowId}" value="0">
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn-remove" onclick="removeItemRow(${rowId})" title="Remove">❌</button>
        </td>
    `;
    
    tbody.appendChild(row);
    // Explicitly enable item and description fields
    document.getElementById(`item-search-${rowId}`).disabled = false;
    document.getElementById(`item-desc-${rowId}`).disabled = false;
}

// Remove item row
function removeItemRow(rowId) {
    const row = document.getElementById(`item-row-${rowId}`);
    if (row) {
        row.remove();
        calculateGrandTotal();
    }
}

// Search items with autocomplete
function searchItems(rowId) {
    const input = document.getElementById(`item-search-${rowId}`);
    const resultsDiv = document.getElementById(`autocomplete-${rowId}`);
    const query = input.value.toLowerCase();
    
    if (query.length < 1) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    const matches = catalogItems.filter(item => 
        item.name.toLowerCase().includes(query) || 
        (item.sku && item.sku.toLowerCase().includes(query))
    );
    
    if (matches.length === 0) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    resultsDiv.innerHTML = matches.map(item => `
        <div class="autocomplete-item" onclick="selectItem(${rowId}, ${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.base_price || 0}, ${item.tax_percent || 0}, '${item.unit || 'pcs'}', '${item.type}')">
            <strong>${item.name}</strong> ${item.sku ? '(' + item.sku + ')' : ''}
            <br><small style="color: #6c757d;">₹${item.base_price || 0} | Tax: ${item.tax_percent || 0}% | Type: ${item.type}</small>
        </div>
    `).join('');
    
    resultsDiv.style.display = 'block';
}

// Select item from autocomplete
function selectItem(rowId, itemId, name, base_price, tax_percent, unit, type) {
    document.getElementById(`item-search-${rowId}`).value = name;
    document.getElementById(`item-id-${rowId}`).value = itemId;
    document.getElementById(`item-price-${rowId}`).value = base_price;
    document.getElementById(`item-tax-${rowId}`).value = tax_percent;
    document.getElementById(`item-unit-${rowId}`).value = unit;
    document.getElementById(`autocomplete-${rowId}`).style.display = 'none';
    
    calculateLineTotal(rowId);
}

// Close autocomplete when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('item-search')) {
        document.querySelectorAll('.autocomplete-results').forEach(div => {
            div.style.display = 'none';
        });
    }
});

// Calculate line total
function calculateLineTotal(rowId) {
    const qty = parseFloat(document.getElementById(`item-qty-${rowId}`).value) || 0;
    const price = parseFloat(document.getElementById(`item-price-${rowId}`).value) || 0;
    const taxPercent = parseFloat(document.getElementById(`item-tax-${rowId}`).value) || 0;
    const discount = parseFloat(document.getElementById(`item-discount-${rowId}`).value) || 0;
    
    // Calculate: (qty * price - discount) * (1 + tax/100)
    const subtotal = qty * price;
    const afterDiscount = subtotal - discount;
    const taxAmount = afterDiscount * (taxPercent / 100);
    const total = afterDiscount + taxAmount;
    
    document.getElementById(`item-total-${rowId}`).value = total.toFixed(2);
    document.getElementById(`item-line-total-${rowId}`).value = total.toFixed(2);
    
    calculateGrandTotal();
}

// Calculate grand total
function calculateGrandTotal() {
    let subtotal = 0;
    let taxTotal = 0;
    let discountTotal = 0;
    
    document.querySelectorAll('[id^="item-row-"]').forEach(row => {
        const rowId = row.id.split('-')[2];
        const qty = parseFloat(document.getElementById(`item-qty-${rowId}`).value) || 0;
        const price = parseFloat(document.getElementById(`item-price-${rowId}`).value) || 0;
        const taxPercent = parseFloat(document.getElementById(`item-tax-${rowId}`).value) || 0;
        const discount = parseFloat(document.getElementById(`item-discount-${rowId}`).value) || 0;
        
        const lineSubtotal = qty * price;
        const afterDiscount = lineSubtotal - discount;
        const lineTax = afterDiscount * (taxPercent / 100);
        
        subtotal += lineSubtotal;
        discountTotal += discount;
        taxTotal += lineTax;
    });
    
    const beforeRound = subtotal - discountTotal + taxTotal;
    const roundOff = Math.round(beforeRound) - beforeRound;
    const grandTotal = Math.round(beforeRound);
    
    // Update display
    const currencySymbol = getCurrencySymbol();
    document.getElementById('display_subtotal').textContent = currencySymbol + subtotal.toFixed(2);
    document.getElementById('display_tax').textContent = currencySymbol + taxTotal.toFixed(2);
    document.getElementById('display_discount').textContent = currencySymbol + discountTotal.toFixed(2);
    document.getElementById('display_roundoff').textContent = currencySymbol + roundOff.toFixed(2);
    document.getElementById('display_grand_total').textContent = currencySymbol + grandTotal.toFixed(2);
    
    // Update hidden fields
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('tax_amount').value = taxTotal.toFixed(2);
    document.getElementById('discount_amount').value = discountTotal.toFixed(2);
    document.getElementById('round_off').value = roundOff.toFixed(2);
    document.getElementById('total_amount').value = grandTotal.toFixed(2);
}

// Payment terms change - auto calculate due date
document.getElementById('payment_terms').addEventListener('change', function() {
    const issueDate = document.getElementById('issue_date').value;
    if (!issueDate) return;
    
    const terms = this.value;
    const daysMap = {
        'NET 7': 7,
        'NET 15': 15,
        'NET 30': 30,
        'NET 45': 45,
        'NET 60': 60,
        'NET 90': 90
    };
    
    if (daysMap[terms]) {
        const dueDate = new Date(issueDate);
        dueDate.setDate(dueDate.getDate() + daysMap[terms]);
        document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];
    }
});

// Helper function to show error on page
function showError(message) {
    const errorContainer = document.getElementById('errorContainer');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = message;
    errorContainer.style.display = 'block';
    // Scroll to error
    errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Helper function to hide error
function hideError() {
    document.getElementById('errorContainer').style.display = 'none';
}

// Form submission
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Hide previous errors
    hideError();
    
    // Validate at least one item
    const itemRows = document.querySelectorAll('[id^="item-row-"]');
    let hasValidItem = false;
    
    itemRows.forEach(row => {
        const rowId = row.id.split('-')[2];
        const itemId = document.getElementById(`item-id-${rowId}`).value;
        if (itemId) {
            hasValidItem = true;
        }
    });
    
    if (!hasValidItem) {
        showError('Please add at least one item to the invoice.');
        return;
    }
    
    // Submit form
    const formData = new FormData(this);
    const action = event.submitter.value; // 'draft' or 'issue'
    formData.append('action', action);
    
    // Convert relative URL to absolute URL
    const form = this;
    const actionURL = new URL(form.getAttribute('action'), window.location.href).href;
    
    fetch(actionURL, {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            console.error('Server error response:', response.status, text);
            showError(`Server error (${response.status}): ${text.substring(0, 500)}`);
            return;
        }
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Server returned non-JSON:', text);
            showError('Server error: ' + text.substring(0, 500));
            return;
        }
        if (data.success) {
            window.location.href = 'view.php?id=' + data.invoice_id;
        } else {
            showError(data.message || 'An error occurred while saving the invoice.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred while saving the invoice. ' + (error && error.message ? error.message : ''));
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
