<?php
/**
 * Quotations Module - Add New Quotation
 * Create a new quotation with line items
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!quotations_tables_exist($conn)) {
    header('Location: ../../scripts/setup_quotations_tables.php');
    exit;
}

$errors = [];
$success_message = '';

// Get clients and items for dropdowns
$clients = get_clients_for_dropdown($conn);
$items = get_items_for_dropdown($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'client_id' => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
        'lead_id' => !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null,
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'quotation_date' => $_POST['quotation_date'] ?? date('Y-m-d'),
        'validity_date' => !empty($_POST['validity_date']) ? $_POST['validity_date'] : null,
        'currency' => $_POST['currency'] ?? 'INR',
        'status' => $_POST['status'] ?? 'Draft',
        'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
        'terms' => !empty($_POST['terms']) ? trim($_POST['terms']) : null
    ];
    
    // Validate
    if (empty($data['title'])) {
        $errors[] = 'Title is required';
    }
    if (empty($data['client_id']) && empty($data['lead_id'])) {
        $errors[] = 'Client or Lead is required';
    }
    if (empty($data['quotation_date'])) {
        $errors[] = 'Quotation date is required';
    }
    
    // Validate items
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
        // Create quotation
        $result = create_quotation($conn, $data, $CURRENT_USER_ID);
        
        if ($result['success']) {
            $quotation_id = $result['quotation_id'];
            
            // Add items
            foreach ($items_data as $item_data) {
                add_quotation_item($conn, $quotation_id, $item_data, $CURRENT_USER_ID);
            }
            
            // Handle file upload
            if (!empty($_FILES['brochure_pdf']['name']) && $_FILES['brochure_pdf']['error'] === UPLOAD_ERR_OK) {
                upload_quotation_brochure($conn, $quotation_id, $_FILES['brochure_pdf'], $CURRENT_USER_ID);
            }
            
            $_SESSION['flash_success'] = 'Quotation created successfully!';
            header('Location: view.php?id=' . $quotation_id);
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
}

$page_title = 'Add New Quotation - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>➕ Add New Quotation</h1>
                    <p>Create a professional quotation for clients or leads</p>
                </div>
                <a href="index.php" class="btn btn-accent">← Back to Quotations</a>
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
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="title">Quotation Title <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="title" id="title" class="form-control" required 
                               placeholder="e.g., Website Development Project" 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="client_id">Client <span style="color: #dc3545;">*</span></label>
                        <select name="client_id" id="client_id" class="form-control" required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" 
                                        <?php echo (isset($_POST['client_id']) && $_POST['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                    <?php if ($client['email']): ?>
                                        (<?php echo htmlspecialchars($client['email']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quotation_date">Quotation Date <span style="color: #dc3545;">*</span></label>
                        <input type="date" name="quotation_date" id="quotation_date" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['quotation_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="validity_date">Valid Until</label>
                        <input type="date" name="validity_date" id="validity_date" class="form-control"
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo htmlspecialchars($_POST['validity_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select name="currency" id="currency" class="form-control">
                            <option value="INR" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'INR') ? 'selected' : ''; ?>>INR (₹)</option>
                            <option value="USD" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="EUR" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'EUR') ? 'selected' : ''; ?>>EUR (€)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="Sent" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Sent') ? 'selected' : ''; ?>>Sent</option>
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
                    <!-- Items will be added dynamically -->
                </div>

                <div style="margin-top: 16px;">
                    <button type="button" class="btn btn-accent" onclick="addItemRow()">
                        ➕ Add Item
                    </button>
                </div>

                <!-- Totals Summary -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                    <div style="max-width: 400px; margin-left: auto;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong>Subtotal:</strong>
                            <span id="displaySubtotal">₹0.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong>Discount:</strong>
                            <span id="displayDiscount">₹0.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong>Tax:</strong>
                            <span id="displayTax">₹0.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 2px solid #003581; font-size: 18px; font-weight: 700; color: #003581;">
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
                <div style="display: grid; gap: 20px;">
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4" 
                                  placeholder="Additional notes or special instructions..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="terms">Terms & Conditions</label>
                        <textarea name="terms" id="terms" class="form-control" rows="4" 
                                  placeholder="Payment terms, delivery terms, warranty information..."><?php echo htmlspecialchars($_POST['terms'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Attachments Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📎 Attachments
                </h3>
                <div class="form-group">
                    <label for="brochure_pdf">Brochure / Additional Document (PDF)</label>
                    <input type="file" name="brochure_pdf" id="brochure_pdf" class="form-control" accept="application/pdf">
                    <small class="text-muted">PDF only, max 10MB</small>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="text-align: center; padding: 20px 0;">
                <button type="submit" class="btn btn-primary" style="padding: 15px 60px; font-size: 16px;">
                    ✅ Create Quotation
                </button>
                <a href="index.php" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
                    ❌ Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let itemRowCounter = 0;
const itemsData = <?php echo json_encode($items); ?>;

function addItemRow() {
    itemRowCounter++;
    const container = document.getElementById('itemsContainer');
    const row = document.createElement('div');
    row.className = 'item-row';
    row.id = 'itemRow' + itemRowCounter;
    row.style.cssText = 'border: 1px solid #e0e0e0; padding: 16px; margin-bottom: 16px; border-radius: 8px; background: #f8f9fa;';
    
    row.innerHTML = `
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label>Item <span style="color: #dc3545;">*</span></label>
                <select name="items[${itemRowCounter}][item_id]" class="form-control item-select" required onchange="loadItemDetails(this, ${itemRowCounter})">
                    <option value="">-- Select Item --</option>
                    ${itemsData.map(item => `<option value="${item.id}" data-price="${item.base_price}" data-tax="${item.tax_percent}" data-type="${item.type}">${item.name} (${item.sku})</option>`).join('')}
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Quantity</label>
                <input type="number" name="items[${itemRowCounter}][quantity]" class="form-control" step="0.01" min="0.01" value="1" required onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Unit Price</label>
                <input type="number" name="items[${itemRowCounter}][unit_price]" id="unitPrice${itemRowCounter}" class="form-control" step="0.01" min="0" value="0" required onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Tax %</label>
                <input type="number" name="items[${itemRowCounter}][tax_percent]" id="taxPercent${itemRowCounter}" class="form-control" step="0.01" min="0" max="100" value="0" onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Discount</label>
                <input type="number" name="items[${itemRowCounter}][discount]" class="form-control" step="0.01" min="0" value="0" onchange="calculateItemTotal(${itemRowCounter})">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Total</label>
                <input type="text" id="itemTotal${itemRowCounter}" class="form-control" readonly style="background: #e9ecef; font-weight: 600;" value="₹0.00">
            </div>
            <div style="padding-top: 4px;">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(${itemRowCounter})" title="Remove">🗑️</button>
            </div>
        </div>
        <div class="form-group" style="margin-top: 12px; margin-bottom: 0;">
            <label>Description (Optional)</label>
            <input type="text" name="items[${itemRowCounter}][description]" class="form-control" placeholder="Custom description...">
        </div>
    `;
    
    container.appendChild(row);
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

// Add first item row on page load
document.addEventListener('DOMContentLoaded', function() {
    addItemRow();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
