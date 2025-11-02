<?php
/**
 * Catalog Module - Edit Item
 * Update an existing product or service
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!catalog_tables_exist($conn)) {
    header('Location: ' . APP_URL . '/setup/index.php?module=catalog');
    exit;
}

// All logged-in users can edit catalog items
$item_id = (int)($_GET['id'] ?? 0);
if (!$item_id) {
    header('Location: index.php');
    exit;
}

$item = get_item_by_id($conn, $item_id);
if (!$item) {
    $_SESSION['flash_error'] = 'Item not found.';
    header('Location: index.php');
    exit;
}

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'category' => !empty(trim($_POST['category'] ?? '')) ? trim($_POST['category']) : null,
        'description_html' => !empty($_POST['description_html']) ? $_POST['description_html'] : null,
        'base_price' => (float)($_POST['base_price'] ?? 0),
        'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
        'default_discount' => (float)($_POST['default_discount'] ?? 0),
        'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        'low_stock_threshold' => !empty($_POST['low_stock_threshold']) ? (int)$_POST['low_stock_threshold'] : null,
        'status' => $_POST['status'] ?? 'Active'
    ];
    
    $result = update_catalog_item($conn, $item_id, $data, $CURRENT_USER_ID);
    
    if ($result['success']) {
        // Handle file uploads
        if (!empty($_FILES['primary_image']['name'])) {
            $upload_result = upload_item_file($conn, $item_id, 'PrimaryImage', $_FILES['primary_image'], $CURRENT_USER_ID);
            if (!$upload_result['success']) {
                $errors[] = 'Image: ' . $upload_result['message'];
            }
        }
        
        if (!empty($_FILES['brochure_pdf']['name'])) {
            $upload_result = upload_item_file($conn, $item_id, 'Brochure', $_FILES['brochure_pdf'], $CURRENT_USER_ID);
            if (!$upload_result['success']) {
                $errors[] = 'Brochure: ' . $upload_result['message'];
            }
        }
        
        if (empty($errors)) {
            $_SESSION['flash_success'] = 'Item updated successfully!';
            header('Location: view.php?id=' . $item_id);
            exit;
        } else {
            $success_message = 'Item updated with warnings: ' . implode(', ', $errors);
        }
        
        // Refresh item data
        $item = get_item_by_id($conn, $item_id);
    } else {
        $errors[] = $result['message'];
    }
}

$itemName = (string)($item['name'] ?? '');
$itemSku = (string)($item['sku'] ?? '');
$itemType = (string)($item['type'] ?? '');
$itemStatus = (string)($item['status'] ?? 'Active');
$itemCategory = (string)($item['category'] ?? '');
$itemBasePrice = $item['base_price'] ?? 0;
$itemTaxPercent = $item['tax_percent'] ?? 0;
$itemDefaultDiscount = $item['default_discount'] ?? 0;
$itemExpiryDate = $item['expiry_date'] ?? '';
$itemLowStockThreshold = $item['low_stock_threshold'] ?? '';
$itemDescription = $item['description_html'] ?? '';
$itemCurrentStock = $item['current_stock'] ?? 0;
$isProduct = ($itemType === 'Product');

$primaryImagePath = trim((string)($item['primary_image'] ?? ''));
$primaryImageUrl = $primaryImagePath !== '' ? APP_URL . '/' . ltrim($primaryImagePath, '/') : '';
$primaryImageFs = $primaryImagePath !== '' ? __DIR__ . '/../../' . ltrim($primaryImagePath, '/') : '';
$primaryImageExists = $primaryImagePath !== '' && file_exists($primaryImageFs);

$brochurePath = trim((string)($item['brochure_pdf'] ?? ''));
$brochureUrl = $brochurePath !== '' ? APP_URL . '/' . ltrim($brochurePath, '/') : '';
$brochureFs = $brochurePath !== '' ? __DIR__ . '/../../' . ltrim($brochurePath, '/') : '';
$brochureExists = $brochurePath !== '' && file_exists($brochureFs);

$page_title = 'Edit Item - ' . htmlspecialchars($itemName) . ' - Catalog - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Include TinyMCE from CDN -->
<script src="https://cdn.tiny.mce.com/1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>


<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>✏️ Edit Catalog Item</h1>
                    <p>Update product or service details below</p>
                </div>
                <a href="view.php?id=<?php echo $item_id; ?>" class="btn btn-accent">← Back to Item</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>❌ Error:</strong><br>
                <?php foreach ($errors as $error): ?>• <?php echo htmlspecialchars($error); ?><br><?php endforeach; ?>
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
                <div style="background: #f8f9fa; padding: 16px; border-radius: 4px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <strong>SKU:</strong> <code><?php echo htmlspecialchars($itemSku); ?></code>
                        </div>
                        <div>
                            <strong>Type:</strong> 
                            <span class="badge <?php echo $isProduct ? 'badge-info' : 'badge-success'; ?>">
                                <?php echo htmlspecialchars($itemType); ?>
                            </span>
                        </div>
                    </div>
                    <small class="text-muted">SKU and Type cannot be changed after creation</small>
                </div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="name">Item Name <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required value="<?php echo htmlspecialchars($itemName); ?>">
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" name="category" id="category" class="form-control" value="<?php echo htmlspecialchars($itemCategory); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Active" <?php echo $itemStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $itemStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                        <input type="number" name="base_price" id="base_price" class="form-control" step="0.01" min="0" required value="<?php echo htmlspecialchars((string)$itemBasePrice); ?>">
                    </div>
                    <div class="form-group">
                        <label for="tax_percent">Tax Percentage (%)</label>
                        <input type="number" name="tax_percent" id="tax_percent" class="form-control" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars((string)$itemTaxPercent); ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_discount">Default Discount (₹)</label>
                        <input type="number" name="default_discount" id="default_discount" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars((string)$itemDefaultDiscount); ?>">
                    </div>
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="<?php echo htmlspecialchars($itemExpiryDate); ?>">
                    </div>
                </div>
            </div>

            <!-- Inventory Card (Products Only) -->
            <?php if ($isProduct): ?>
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📊 Inventory Settings
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <strong>Current Stock:</strong> <span style="font-size: 1.2em; color: #0066cc;"><?php echo htmlspecialchars((string)$itemCurrentStock); ?></span>
                        <br><small class="text-muted">Use <a href="stock_adjust.php?id=<?php echo $item_id; ?>">Stock Adjustment</a> to modify</small>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="low_stock_threshold">Low Stock Alert Threshold</label>
                        <input type="number" name="low_stock_threshold" id="low_stock_threshold" class="form-control" min="0" value="<?php echo htmlspecialchars((string)$itemLowStockThreshold); ?>">
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Description Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📝 Description
                </h3>
                <div class="form-group">
                    <textarea name="description_html" id="description_html" class="form-control" rows="8" placeholder="Enter item description..."><?php echo htmlspecialchars($itemDescription); ?></textarea>
                </div>
            </div>

            <!-- Media Card -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    🖼️ Media & Documents
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <strong>Primary Image:</strong><br>
                        <?php if ($primaryImageExists): ?>
                            <img src="<?php echo htmlspecialchars($primaryImageUrl); ?>" alt="Primary Image" style="max-width: 200px; margin-top: 8px; border-radius: 4px;">
                        <?php else: ?>
                            <span class="text-muted">No image uploaded</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Brochure:</strong><br>
                        <?php if ($brochureExists): ?>
                            <a href="<?php echo htmlspecialchars($brochureUrl); ?>" target="_blank" class="btn btn-sm btn-info" style="margin-top: 8px;">📄 View Brochure</a>
                        <?php else: ?>
                            <span class="text-muted">No brochure uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div class="form-group">
                        <label for="primary_image">Replace Primary Image</label>
                        <input type="file" name="primary_image" id="primary_image" class="form-control" accept="image/png,image/jpeg,image/jpg">
                        <small class="text-muted">PNG/JPG, max 2MB (replaces current)</small>
                    </div>
                    <div class="form-group">
                        <label for="brochure_pdf">Replace Brochure (PDF)</label>
                        <input type="file" name="brochure_pdf" id="brochure_pdf" class="form-control" accept="application/pdf">
                        <small class="text-muted">PDF only, max 10MB (replaces current)</small>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="text-align: center; padding: 20px 0;">
                <button type="submit" class="btn btn-primary" style="padding: 15px 60px; font-size: 16px;">✅ Update Item</button>
                <a href="view.php?id=<?php echo $item_id; ?>" class="btn btn-accent" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">❌ Cancel</a>
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
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
