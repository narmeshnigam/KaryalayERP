<?php
/**
 * Quotations Module - Edit Quotation
 * Update existing quotation and its line items
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!quotations_tables_exist($conn)) {
    header('Location: ' . APP_URL . '/scripts/setup_quotations_tables.php');
    exit;
}

$quotation_id = (int)($_GET['id'] ?? 0);
if (!$quotation_id) {
    header('Location: index.php');
    exit;
}

$quotation = get_quotation_by_id($conn, $quotation_id);
if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found.';
    header('Location: index.php');
    exit;
}

$quotation_items = get_quotation_items($conn, $quotation_id);

$errors = [];
$success_message = '';

// Get clients and items for dropdowns
$clients = get_clients_for_dropdown($conn);
$items = get_items_for_dropdown($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'quotation_date' => $_POST['quotation_date'] ?? date('Y-m-d'),
        'validity_date' => !empty($_POST['validity_date']) ? $_POST['validity_date'] : null,
        'status' => $_POST['status'] ?? 'Draft',
        'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
        'terms' => !empty($_POST['terms']) ? trim($_POST['terms']) : null
    ];
    
    // Validate
    if (empty($data['title'])) {
        $errors[] = 'Title is required';
    }
    
    // Validate and process items
    $items_data = [];
    if (!empty($_POST['items'])) {
        foreach ($_POST['items'] as $index => $item) {
            if (empty($item['item_id'])) continue;
            
            $item_data = [
                'item_id' => (int)$item['item_id'],
                'description' => !empty($item['description']) ? trim($item['description']) : null,
                'quantity' => (float)($item['quantity'] ?? 1),
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'tax_percent' => (float)($item['tax_percent'] ?? 0),
                'discount' => (float)($item['discount'] ?? 0)
            ];
            
            if ($item_data['quantity'] <= 0) {
                $errors[] = "Item #" . ($index + 1) . ": Quantity must be greater than 0";
            }
            
            $items_data[] = $item_data;
        }
    }
    
    if (empty($items_data)) {
        $errors[] = 'At least one item is required';
    }
    
    // Handle file upload
    if (!empty($_FILES['brochure_pdf']['name'])) {
        $allowed_types = ['application/pdf'];
        if (!in_array($_FILES['brochure_pdf']['type'], $allowed_types)) {
            $errors[] = 'Brochure: Only PDF files are allowed';
        } elseif ($_FILES['brochure_pdf']['size'] > 10 * 1024 * 1024) {
            $errors[] = 'Brochure: File size must be less than 10MB';
        }
    }
    
    if (empty($errors)) {
        // Update quotation
        $result = update_quotation($conn, $quotation_id, $data, $CURRENT_USER_ID);
        
        if ($result['success']) {
            // Delete existing items
            $conn->query("DELETE FROM quotation_items WHERE quotation_id = $quotation_id");
            
            // Add updated items
            foreach ($items_data as $item_data) {
                add_quotation_item($conn, $quotation_id, $item_data, $CURRENT_USER_ID);
            }
            
            // Handle file upload
            if (!empty($_FILES['brochure_pdf']['name']) && $_FILES['brochure_pdf']['error'] === UPLOAD_ERR_OK) {
                upload_quotation_brochure($conn, $quotation_id, $_FILES['brochure_pdf'], $CURRENT_USER_ID);
            }
            
            $_SESSION['flash_success'] = 'Quotation updated successfully!';
            header('Location: view.php?id=' . $quotation_id);
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
    
    // Refresh data after error
    $quotation = get_quotation_by_id($conn, $quotation_id);
    $quotation_items = get_quotation_items($conn, $quotation_id);
}

$page_title = 'Edit Quotation - ' . $quotation['quotation_no'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
<style>
.quotations-edit-header-flex{display:flex;justify-content:space-between;align-items:center;}
.quotations-edit-readonly-grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;}
.quotations-edit-basic-grid{display:grid;grid-template-columns:repeat(2, 1fr);gap:20px;}
.quotations-edit-notes-grid{display:grid;gap:20px;}
.quotations-edit-buttons{text-align:center;padding:20px 0;display:flex;justify-content:center;gap:15px;flex-wrap:wrap;}
.quotations-edit-totals-summary{max-width:400px;margin-left:auto;}
.quotations-edit-totals-row{display:flex;justify-content:space-between;margin-bottom:8px;}
.item-row{border:1px solid #e0e0e0;padding:16px;margin-bottom:16px;border-radius:8px;background:#f8f9fa;}
.item-row-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto;gap:12px;align-items:end;}
.item-row-description{margin-top:12px;margin-bottom:0;}

@media (max-width:1024px){
.quotations-edit-basic-grid{grid-template-columns:1fr;gap:15px;}
.quotations-edit-readonly-grid{grid-template-columns:1fr;gap:15px;}
.quotations-edit-totals-summary{margin-left:0;}
.item-row-grid{grid-template-columns:2fr 1fr 1fr;gap:10px;}
}

@media (max-width:768px){
.quotations-edit-header-flex{flex-direction:column;align-items:stretch;gap:16px;}
.quotations-edit-header-flex .btn{width:100%;text-align:center;}
.quotations-edit-header-flex h1{font-size:13px;}
.quotations-edit-header-flex p{font-size:13px;}
.quotations-edit-basic-grid .form-group label{font-size:13px;}
.quotations-edit-basic-grid .form-control{font-size:13px;}
.quotations-edit-notes-grid .form-group label{font-size:13px;}
.quotations-edit-notes-grid .form-control{font-size:13px;}
.quotations-edit-buttons{flex-direction:column;gap:10px;}
.quotations-edit-buttons .btn{width:100%;}
.quotations-edit-totals-row strong{font-size:13px;}
.quotations-edit-totals-row span{font-size:13px;}
.item-row{padding:12px;margin-bottom:12px;}
.item-row-grid{grid-template-columns:1fr 1fr;gap:8px;font-size:12px;}
.item-row-grid label{font-size:11px;}
.item-row-grid input{font-size:12px;}
.item-row-grid select{font-size:12px;}
.item-row-description input{font-size:12px;}
}

@media (max-width:480px){
.quotations-edit-header-flex h1{font-size:1.3rem;}
.quotations-edit-basic-grid .form-control{font-size:16px;}
.quotations-edit-basic-grid input,.quotations-edit-basic-grid select{font-size:16px;}
.quotations-edit-notes-grid .form-control{font-size:16px;}
.quotations-edit-notes-grid textarea{font-size:16px;}
.quotations-edit-buttons .btn{font-size:14px;}
.quotations-edit-totals-row strong{font-size:12px;}
.quotations-edit-totals-row span{font-size:12px;}
.quotations-edit-totals-row.total-amount{font-size:1rem;}
.item-row{padding:10px;margin-bottom:10px;border:1px solid #d0d0d0;}
.item-row-grid{grid-template-columns:1fr;gap:8px;font-size:12px;}
.item-row-grid label{font-size:12px;font-weight:bold;}
.item-row-grid input,.item-row-grid select{font-size:16px;width:100%;}
.item-row-description{margin-top:8px;}
.item-row-description input{font-size:16px;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="quotations-edit-header-flex">
                <div>
                    <h1>✏️ Edit Quotation</h1>
                    <p><?php echo htmlspecialchars($quotation['quotation_no']); ?> - <?php echo htmlspecialchars($quotation['title']); ?></p>
                </div>
                <a href="view.php?id=<?php echo $quotation_id; ?>" class="btn btn-accent">← Back to Quotation</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>❌ Error:</strong><br>
                <?php foreach ($errors as $error): ?>
                    • <?php echo htmlspecialchars($error); ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="quotationForm">
            <!-- Basic Details Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📋 Basic Details
                </h3>
                
                <!-- Read-only fields -->
                <div style="background: #f8f9fa; padding: 16px; border-radius: 4px; margin-bottom: 20px;">
                    <div class="quotations-edit-readonly-grid">
                        <div>
                            <strong>Quotation No:</strong> <code><?php echo htmlspecialchars($quotation['quotation_no']); ?></code>
                        </div>
                        <div>
                            <strong>Client:</strong> <?php echo htmlspecialchars($quotation['client_name'] ?? 'N/A'); ?>
                        </div>
                        <div>
                            <strong>Created:</strong> <?php echo date('d M Y', strtotime($quotation['created_at'])); ?>
                        </div>
                    </div>
                    <small class="text-muted">Quotation No and Client cannot be changed after creation</small>
                </div>
                
                <div class="quotations-edit-basic-grid">
                    <div class="form-group">
                        <label for="title">Quotation Title <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="title" id="title" class="form-control" required 
                               placeholder="e.g., Website Development Project" 
                               value="<?php echo htmlspecialchars($quotation['title']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="quotation_date">Quotation Date <span style="color: #dc3545;">*</span></label>
                        <input type="date" name="quotation_date" id="quotation_date" class="form-control" required
                               value="<?php echo htmlspecialchars($quotation['quotation_date']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="validity_date">Valid Until</label>
                        <input type="date" name="validity_date" id="validity_date" class="form-control"
                               value="<?php echo htmlspecialchars($quotation['validity_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Draft" <?php echo $quotation['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="Sent" <?php echo $quotation['status'] === 'Sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="Accepted" <?php echo $quotation['status'] === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="Rejected" <?php echo $quotation['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="Expired" <?php echo $quotation['status'] === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Line Items Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    🛒 Line Items
                </h3>
                
                <div id="itemsContainer">
                    <!-- Existing items will be loaded here -->
                </div>

                <div style="margin-top: 16px;">
                    <button type="button" class="btn btn-accent" onclick="addItemRow()">
                        ➕ Add Item
                    </button>
                </div>

                <!-- Totals Summary -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                    <div class="quotations-edit-totals-summary">
                        <div class="quotations-edit-totals-row">
                            <strong>Subtotal:</strong>
                            <span id="displaySubtotal">₹0.00</span>
                        </div>
                        <div class="quotations-edit-totals-row">
                            <strong>Discount:</strong>
                            <span id="displayDiscount">₹0.00</span>
                        </div>
                        <div class="quotations-edit-totals-row">
                            <strong>Tax:</strong>
                            <span id="displayTax">₹0.00</span>
                        </div>
                        <div class="quotations-edit-totals-row total-amount" style="padding-top: 8px; border-top: 2px solid #003581; font-size: 18px; font-weight: 700; color: #003581;">
                            <strong>Total Amount:</strong>
                            <span id="displayTotal">₹0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes & Terms Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📝 Notes & Terms
                </h3>
                <div class="quotations-edit-notes-grid">
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4" 
                                  placeholder="Additional notes or special instructions..."><?php echo htmlspecialchars($quotation['notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="terms">Terms & Conditions</label>
                        <textarea name="terms" id="terms" class="form-control" rows="4" 
                                  placeholder="Payment terms, delivery terms, warranty information..."><?php echo htmlspecialchars($quotation['terms'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Attachments Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📎 Attachments
                </h3>
                <?php if (!empty($quotation['brochure_pdf'])): ?>
                    <div style="padding: 12px; background: #e7f3ff; border-radius: 6px; margin-bottom: 16px;">
                        <strong>Current Brochure:</strong>
                        <a href="<?php echo APP_URL . '/' . htmlspecialchars($quotation['brochure_pdf']); ?>" 
                           target="_blank" class="btn btn-sm btn-info" style="margin-left: 12px;">
                            📄 View PDF
                        </a>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="brochure_pdf">Replace Brochure / Additional Document (PDF)</label>
                    <input type="file" name="brochure_pdf" id="brochure_pdf" class="form-control" accept="application/pdf">
                    <small class="text-muted">PDF only, max 10MB (replaces current if uploaded)</small>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="quotations-edit-buttons">
                <button type="submit" class="btn btn-primary" style="padding: 15px 60px; font-size: 16px;">
                    ✅ Update Quotation
                </button>
                <a href="view.php?id=<?php echo $quotation_id; ?>" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; text-decoration: none;">
                    ❌ Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let itemRowCounter = 0;
const itemsData = <?php echo json_encode($items); ?>;
const existingItems = <?php echo json_encode($quotation_items); ?>;

function addItemRow(existingItem = null) {
    itemRowCounter++;
    const container = document.getElementById('itemsContainer');
    const row = document.createElement('div');
    row.className = 'item-row';
    row.id = 'itemRow' + itemRowCounter;
    
    const selectedItemId = existingItem ? existingItem.item_id : '';
    const quantity = existingItem ? existingItem.quantity : '1';
    const unitPrice = existingItem ? existingItem.unit_price : '0';
    const taxPercent = existingItem ? existingItem.tax_percent : '0';
    const discount = existingItem ? existingItem.discount : '0';
    const description = existingItem ? existingItem.description || '' : '';
    
    row.innerHTML = `
        <div class="item-row-grid">
            <div class="form-group" style="margin: 0;">
                <label>Item <span style="color: #dc3545;">*</span></label>
                <select name="items[${itemRowCounter}][item_id]" class="form-control item-select" required onchange="loadItemDetails(this, ${itemRowCounter})">
                    <option value="">-- Select Item --</option>
                    ${itemsData.map(item => `<option value="${item.id}" data-price="${item.base_price}" data-tax="${item.tax_percent}" data-type="${item.type}" ${item.id == selectedItemId ? 'selected' : ''}>${item.name} (${item.sku})</option>`).join('')}
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Quantity</label>
                <input type="number" name="items[${itemRowCounter}][quantity]" class="form-control" step="0.01" min="0.01" value="${quantity}" required onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Unit Price</label>
                <input type="number" name="items[${itemRowCounter}][unit_price]" id="unitPrice${itemRowCounter}" class="form-control" step="0.01" min="0" value="${unitPrice}" required onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Tax %</label>
                <input type="number" name="items[${itemRowCounter}][tax_percent]" id="taxPercent${itemRowCounter}" class="form-control" step="0.01" min="0" max="100" value="${taxPercent}" onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Discount</label>
                <input type="number" name="items[${itemRowCounter}][discount]" class="form-control" step="0.01" min="0" value="${discount}" onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Total</label>
                <input type="text" id="itemTotal${itemRowCounter}" class="form-control" readonly style="background: #e9ecef; font-weight: 600;" value="₹0.00">
            </div>
            <div style="padding-top: 4px;">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(${itemRowCounter})" title="Remove">🗑️</button>
            </div>
        </div>
        <div class="form-group item-row-description">
            <label>Description (Optional)</label>
            <input type="text" name="items[${itemRowCounter}][description]" class="form-control" placeholder="Custom description..." value="${description}">
        </div>
    `;
    
    container.appendChild(row);
    calculateItemTotal(itemRowCounter);
}

function removeItemRow(id) {
    const row = document.getElementById('itemRow' + id);
    if (row) {
        row.remove();
        calculateGrandTotal();
    }
}

function loadItemDetails(select, rowId) {
    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || 0;
    const tax = selectedOption.getAttribute('data-tax') || 0;
    
    document.getElementById('unitPrice' + rowId).value = price;
    document.getElementById('taxPercent' + rowId).value = tax;
    
    calculateItemTotal(rowId);
}

function calculateItemTotal(rowId) {
    const row = document.getElementById('itemRow' + rowId);
    if (!row) return;
    
    const quantity = parseFloat(row.querySelector('[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]').value) || 0;
    const taxPercent = parseFloat(row.querySelector('[name*="[tax_percent]"]').value) || 0;
    const discount = parseFloat(row.querySelector('[name*="[discount]"]').value) || 0;
    
    const subtotal = quantity * unitPrice;
    const afterDiscount = subtotal - discount;
    const tax = (afterDiscount * taxPercent) / 100;
    const total = afterDiscount + tax;
    
    document.getElementById('itemTotal' + rowId).value = '₹' + total.toFixed(2);
    
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let grandSubtotal = 0;
    let grandDiscount = 0;
    let grandTax = 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        const quantity = parseFloat(row.querySelector('[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]').value) || 0;
        const taxPercent = parseFloat(row.querySelector('[name*="[tax_percent]"]').value) || 0;
        const discount = parseFloat(row.querySelector('[name*="[discount]"]').value) || 0;
        
        const subtotal = quantity * unitPrice;
        const afterDiscount = subtotal - discount;
        const tax = (afterDiscount * taxPercent) / 100;
        
        grandSubtotal += subtotal;
        grandDiscount += discount;
        grandTax += tax;
    });
    
    const grandTotal = grandSubtotal - grandDiscount + grandTax;
    
    document.getElementById('displaySubtotal').textContent = '₹' + grandSubtotal.toFixed(2);
    document.getElementById('displayDiscount').textContent = '₹' + grandDiscount.toFixed(2);
    document.getElementById('displayTax').textContent = '₹' + grandTax.toFixed(2);
    document.getElementById('displayTotal').textContent = '₹' + grandTotal.toFixed(2);
}

// Load existing items on page load
document.addEventListener('DOMContentLoaded', function() {
    if (existingItems.length > 0) {
        existingItems.forEach(item => {
            addItemRow(item);
        });
    } else {
        addItemRow();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
