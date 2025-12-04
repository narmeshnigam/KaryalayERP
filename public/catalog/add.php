<?php
/**
 * Catalog Module - Add New Item
 * Create a new product or service
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist first
if (!catalog_tables_exist($conn)) {
    header('Location: ' . APP_URL . '/setup/index.php?module=catalog');
    exit;
}

// All logged-in users can add catalog items
$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $data = [
        'sku' => trim($_POST['sku'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'type' => $_POST['type'] ?? 'Product',
        'category' => !empty(trim($_POST['category'] ?? '')) ? trim($_POST['category']) : null,
        'description_html' => !empty($_POST['description_html']) ? $_POST['description_html'] : null,
        'base_price' => (float)($_POST['base_price'] ?? 0),
        'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
        'default_discount' => (float)($_POST['default_discount'] ?? 0),
        'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        'current_stock' => (int)($_POST['current_stock'] ?? 0),
        'low_stock_threshold' => !empty($_POST['low_stock_threshold']) ? (int)$_POST['low_stock_threshold'] : null,
        'status' => $_POST['status'] ?? 'Active'
    ];
    
    // Auto-generate SKU if empty
    if (empty($data['sku'])) {
        $data['sku'] = generate_item_sku($conn, $data['type']);
    }
    
    // Create item
    $result = create_catalog_item($conn, $data, $CURRENT_USER_ID);
    
    if ($result['success']) {
        $item_id = $result['item_id'];
        
        // Handle primary image upload
        if (!empty($_FILES['primary_image']['name'])) {
            $upload_result = upload_item_file($conn, $item_id, 'PrimaryImage', $_FILES['primary_image'], $CURRENT_USER_ID);
            if (!$upload_result['success']) {
                $errors[] = 'Image: ' . $upload_result['message'];
            }
        }
        
        // Handle brochure upload
        if (!empty($_FILES['brochure_pdf']['name'])) {
            $upload_result = upload_item_file($conn, $item_id, 'Brochure', $_FILES['brochure_pdf'], $CURRENT_USER_ID);
            if (!$upload_result['success']) {
                $errors[] = 'Brochure: ' . $upload_result['message'];
            }
        }
        
        if (empty($errors)) {
            $_SESSION['flash_success'] = 'Item created successfully!';
            header('Location: view.php?id=' . $item_id);
            exit;
        } else {
            $success_message = 'Item created with warnings: ' . implode(', ', $errors);
        }
    } else {
        $errors[] = $result['message'];
    }
}

$page_title = 'Add Item - Catalog - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Include TinyMCE from CDN -->
<script src="https://cdn.tiny.mce.com/1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>


<div class="main-wrapper">
<style>
.catalog-add-header-flex{display:flex;justify-content:space-between;align-items:center;}

@media (max-width:768px){
.catalog-add-header-flex{flex-direction:column;align-items:stretch;gap:16px;}
.catalog-add-header-flex .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.catalog-add-header-flex h1{font-size:1.5rem;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="catalog-add-header-flex">
                <div>
                    <h1>➕ Add New Catalog Item</h1>
                    <p>Enter product or service details below</p>
                </div>
                <a href="index.php" class="btn btn-accent">← Back to Catalog</a>
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

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Item Details Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📦 Item Details
                </h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="type">Item Type <span style="color: #dc3545;">*</span></label>
                        <select name="type" id="type" class="form-control" required onchange="toggleStockFields()">
                            <option value="Product">Product (Physical Item)</option>
                            <option value="Service">Service (Non-Physical)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sku">SKU (Stock Keeping Unit)</label>
                        <input type="text" name="sku" id="sku" class="form-control" placeholder="Leave blank to auto-generate">
                    </div>
                    <div class="form-group">
                        <label for="name">Item Name <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="Enter item name">
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" name="category" id="category" class="form-control" placeholder="e.g., Electronics, Consulting">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Pricing & Expiry Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    💰 Pricing & Expiry
                </h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="base_price">Base Price (₹) <span style="color: #dc3545;">*</span></label>
                        <input type="number" name="base_price" id="base_price" class="form-control" step="0.01" min="0" value="0.00" required>
                    </div>
                    <div class="form-group">
                        <label for="tax_percent">Tax Percentage (%)</label>
                        <input type="number" name="tax_percent" id="tax_percent" class="form-control" step="0.01" min="0" max="100" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="default_discount">Default Discount (₹)</label>
                        <input type="number" name="default_discount" id="default_discount" class="form-control" step="0.01" min="0" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <!-- Inventory Card (Products Only) -->
            <div class="card" id="stock_fields" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📊 Inventory Settings (Products Only)
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="current_stock">Initial Stock Quantity</label>
                        <input type="number" name="current_stock" id="current_stock" class="form-control" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="low_stock_threshold">Low Stock Alert Threshold</label>
                        <input type="number" name="low_stock_threshold" id="low_stock_threshold" class="form-control" min="0" placeholder="e.g., 10">
                    </div>
                </div>
            </div>

            <!-- Description Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📝 Description
                </h3>
                <div class="form-group">
                    <textarea name="description_html" id="description_html" class="form-control" rows="8" placeholder="Enter item description..."></textarea>
                </div>
            </div>

            <!-- Media Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    🖼️ Media & Documents
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="primary_image">Primary Image</label>
                        <input type="file" name="primary_image" id="primary_image" class="form-control" accept="image/png,image/jpeg,image/jpg">
                        <small class="text-muted">PNG/JPG, max 2MB</small>
                    </div>
                    <div class="form-group">
                        <label for="brochure_pdf">Brochure (PDF)</label>
                        <input type="file" name="brochure_pdf" id="brochure_pdf" class="form-control" accept="application/pdf">
                        <small class="text-muted">PDF only, max 10MB</small>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="text-align: center; padding: 20px 0;">
                <button type="submit" class="btn btn-primary" style="padding: 15px 60px; font-size: 16px;">
                    ✅ Create Item
                </button>
                <a href="index.php" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
                    ❌ Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize TinyMCE
    tinymce.init({
        selector: '#description_html',
        height: 400,
        menubar: false,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | formatselect | bold italic underline | \
                  alignleft aligncenter alignright alignjustify | \
                  bullist numlist outdent indent | link image | removeformat | help',
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
    });

    // Toggle stock fields based on item type
    function toggleStockFields() {
        const type = document.getElementById('type').value;
        const stockFields = document.getElementById('stock_fields');
        
        if (type === 'Service') {
            stockFields.style.display = 'none';
            document.getElementById('current_stock').value = '0';
            document.getElementById('low_stock_threshold').value = '';
        } else {
            stockFields.style.display = 'block';
        }
    }

    // Initialize on page load
    toggleStockFields();
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
