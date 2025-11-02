<?php
/**
 * Catalog Module - Stock Adjustment
 * Add, reduce, or correct product inventory
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!catalog_tables_exist($conn)) {
    header('Location: ' . APP_URL . '/setup/index.php?module=catalog');
    exit;
}

// All logged-in users can adjust stock
$item_id = (int)($_GET['id'] ?? 0);
if (!$item_id) {
    header('Location: index.php');
    exit;
}


$item = get_item_by_id($conn, $item_id);
if (!is_array($item) || empty($item['name'])) {
    $_SESSION['flash_error'] = 'Item not found.';
    header('Location: index.php');
    exit;
}

$itemName = (string)($item['name'] ?? '');
$itemSku = (string)($item['sku'] ?? '');
$currentStock = isset($item['current_stock']) ? $item['current_stock'] : 0;
$lowStockThreshold = isset($item['low_stock_threshold']) ? $item['low_stock_threshold'] : '';

if (isset($item['type']) && $item['type'] === 'Service') {
    $_SESSION['flash_error'] = 'Cannot adjust stock for services.';
    header('Location: view.php?id=' . $item_id);
    exit;
}

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason']);
    $reference_type = $_POST['reference_type'] ?? 'Manual';
    $reference_id = $_POST['reference_id'] ? (int)$_POST['reference_id'] : null;
    
    // Validate
    if (empty($action) || !in_array($action, ['Add', 'Reduce', 'Correction'])) {
        $errors[] = 'Please select a valid action.';
    }
    
    if ($quantity <= 0 && $action !== 'Correction') {
        $errors[] = 'Quantity must be greater than zero.';
    }
    
    if (empty($reason)) {
        $errors[] = 'Reason is required.';
    }
    
    if (empty($errors)) {
        $result = adjust_item_stock($conn, $item_id, $action, $quantity, $reason, $reference_type, $reference_id, $CURRENT_USER_ID);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = 'Stock adjusted successfully! New stock: ' . $result['new_stock'];
            header('Location: view.php?id=' . $item_id . '&tab=inventory');
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
    
    // Refresh item data
    $item = get_item_by_id($conn, $item_id);
}

$page_title = 'Adjust Stock - ' . htmlspecialchars($itemName) . ' - Catalog - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="margin: 0 0 8px 0;">📊 Adjust Stock</h1>
                    <p style="color: #6c757d; margin: 0;">
                        <?php echo htmlspecialchars($itemName); ?> 
                        (<code><?php echo htmlspecialchars($itemSku); ?></code>)
                    </p>
                </div>
                <a href="view.php?id=<?php echo $item_id; ?>" class="btn btn-secondary">← Back to Item</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Errors:</strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Current Stock Display -->
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 24px;">
            <div style="text-align: center;">
                <h3 style="margin: 0 0 8px 0; font-size: 1.2em; opacity: 0.9;">Current Stock</h3>
                <div style="font-size: 3em; font-weight: bold; margin: 8px 0;">
                    <?php echo htmlspecialchars((string)$currentStock); ?>
                </div>
                <?php if ($lowStockThreshold !== ''): ?>
                    <p style="margin: 0; opacity: 0.8;">
                        Low stock threshold: <?php echo htmlspecialchars((string)$lowStockThreshold); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stock Adjustment Form -->
        <form method="POST" class="card">
            <h3 class="card-title">Adjust Inventory</h3>
            
            <div class="form-group">
                <label for="action">Adjustment Action <span class="text-danger">*</span></label>
                <select name="action" id="action" class="form-control" required onchange="updatePreview()">
                    <option value="">-- Select Action --</option>
                    <option value="Add">➕ Add Stock (Purchase/Receive)</option>
                    <option value="Reduce">➖ Reduce Stock (Damage/Loss/Manual Sale)</option>
                    <option value="Correction">🔧 Correction (Set Absolute Value)</option>
                </select>
                <small class="text-muted">Choose the type of stock adjustment</small>
            </div>

            <div class="form-group">
                <label for="quantity">Quantity <span class="text-danger">*</span></label>
                <input type="number" name="quantity" id="quantity" class="form-control" min="0" value="0" required oninput="updatePreview()">
                <small class="text-muted" id="quantity_help">Enter the quantity to add/reduce</small>
            </div>

            <!-- Live Preview -->
            <div id="preview" style="padding: 16px; background: #f8f9fa; border-radius: 4px; margin-bottom: 20px; display: none;">
                <h4 style="margin-top: 0;">Preview</h4>
                <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: center; text-align: center;">
                    <div>
                        <div style="font-size: 0.85em; color: #666; margin-bottom: 4px;">Before</div>
                        <div style="font-size: 2em; font-weight: bold; color: #0066cc;" id="preview_before">
                            <?php echo htmlspecialchars((string)$currentStock); ?>
                        </div>
                    </div>
                    <div style="font-size: 2em; color: #666;">→</div>
                    <div>
                        <div style="font-size: 0.85em; color: #666; margin-bottom: 4px;">After</div>
                        <div style="font-size: 2em; font-weight: bold;" id="preview_after">
                            <?php echo htmlspecialchars((string)$currentStock); ?>
                        </div>
                    </div>
                </div>
                <div id="preview_warning" style="margin-top: 12px; padding: 12px; background: #fff3cd; border-radius: 4px; display: none;">
                    <strong>⚠️ Warning:</strong> <span id="preview_warning_text"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="reason">Reason <span class="text-danger">*</span></label>
                <textarea name="reason" id="reason" class="form-control" rows="3" required placeholder="Explain why this adjustment is being made"></textarea>
                <small class="text-muted">Required for audit trail</small>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label for="reference_type">Reference Type</label>
                    <select name="reference_type" id="reference_type" class="form-control">
                        <option value="Manual">Manual Adjustment</option>
                        <option value="Invoice">Invoice (Automated)</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reference_id">Reference ID (Optional)</label>
                    <input type="number" name="reference_id" id="reference_id" class="form-control" placeholder="e.g., Invoice #123">
                    <small class="text-muted">Link to related document</small>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <button type="submit" class="btn btn-primary">✓ Confirm Adjustment</button>
                <a href="view.php?id=<?php echo $item_id; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <!-- Recent Stock Movements -->
        <?php
        $recent_logs = get_item_inventory_log($conn, $item_id);
        $recent_logs = array_slice($recent_logs, 0, 5); // Last 5
        ?>
        
        <?php if (!empty($recent_logs)): ?>
        <div class="card">
            <h3 class="card-title">Recent Stock Movements</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Change</th>
                            <th>Stock</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            echo $log['action'] === 'Add' ? 'badge-success' : 
                                                 ($log['action'] === 'Reduce' || $log['action'] === 'InvoiceDeduct' ? 'badge-warning' : 'badge-info'); 
                                        ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: <?php echo $log['quantity_delta'] > 0 ? 'green' : 'red'; ?>;">
                                        <?php echo $log['quantity_delta'] > 0 ? '+' : ''; ?><?php echo $log['quantity_delta']; ?>
                                    </strong>
                                </td>
                                <td><?php echo $log['qty_before']; ?> → <?php echo $log['qty_after']; ?></td>
                                <td><?php echo htmlspecialchars($log['reason'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="view.php?id=<?php echo $item_id; ?>&tab=inventory" class="btn btn-sm btn-secondary">View Full History</a>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
    const currentStock = <?php echo json_encode($currentStock); ?>;
    const lowThreshold = <?php echo json_encode($lowStockThreshold !== '' ? $lowStockThreshold : 10); ?>;

    function updatePreview() {
        const action = document.getElementById('action').value;
        const quantity = parseInt(document.getElementById('quantity').value) || 0;
        const preview = document.getElementById('preview');
        const previewAfter = document.getElementById('preview_after');
        const previewWarning = document.getElementById('preview_warning');
        const previewWarningText = document.getElementById('preview_warning_text');
        const quantityHelp = document.getElementById('quantity_help');

        if (!action || quantity === 0) {
            preview.style.display = 'none';
            return;
        }

        preview.style.display = 'block';

        let newStock = currentStock;
        
        switch(action) {
            case 'Add':
                newStock = currentStock + quantity;
                quantityHelp.textContent = 'Enter quantity to add to current stock';
                break;
            case 'Reduce':
                newStock = currentStock - quantity;
                quantityHelp.textContent = 'Enter quantity to remove from current stock';
                break;
            case 'Correction':
                newStock = quantity;
                quantityHelp.textContent = 'Enter the new absolute stock value (replaces current)';
                break;
        }

        previewAfter.textContent = newStock;
        previewAfter.style.color = newStock < 0 ? '#d32f2f' : (newStock <= lowThreshold ? '#f57c00' : '#2e7d32');

        // Warnings
        if (newStock < 0) {
            previewWarning.style.display = 'block';
            previewWarning.style.background = '#ffebee';
            previewWarningText.textContent = 'Stock will go negative! Only allowed for Correction with admin rights.';
        } else if (newStock <= lowThreshold) {
            previewWarning.style.display = 'block';
            previewWarning.style.background = '#fff3cd';
            previewWarningText.textContent = 'Stock will fall below low stock threshold (' + lowThreshold + ')';
        } else {
            previewWarning.style.display = 'none';
        }
    }

    // Initialize
    updatePreview();
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
