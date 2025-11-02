<?php
/**
 * Catalog Module - View Item Details
 * Professional item view matching employee module design
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!catalog_tables_exist($conn)) {
    header('Location: ' . APP_URL . '/setup/index.php?module=catalog');
    exit;
}

authz_require_permission($conn, 'items_master', 'view_all');

$item_id = (int)($_GET['id'] ?? 0);
$not_found = false;

$item = null;
if ($item_id > 0) {
    $item = get_item_by_id($conn, $item_id);
    if (!is_array($item) || empty($item['name'])) {
        $not_found = true;
    }
} else {
    $not_found = true;
}

// Get related data only if item exists
$inventory_logs = [];
$files = [];
$change_logs = [];
if (!$not_found) {
    $inventory_logs = get_item_inventory_log($conn, $item_id);
    $files = get_item_files($conn, $item_id);
    $change_logs = get_item_change_log($conn, $item_id);
}

// Check permissions
$catalog_permissions = authz_get_permission_set($conn, 'items_master');
$can_edit = !empty($catalog_permissions['can_edit_all']) || $IS_SUPER_ADMIN;
$can_delete = !empty($catalog_permissions['can_delete_all']) || $IS_SUPER_ADMIN;

// Helper functions
function safeValue($value, $fallback = '—') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return htmlspecialchars((string)$value);
}

function safeDate($value, $fallback = '—') {
    if (!$value || $value === '0000-00-00') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return htmlspecialchars($value);
    }
    return date('d M Y', $timestamp);
}

function safeDateTime($value, $fallback = '—') {
    if (!$value) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return htmlspecialchars($value);
    }
    return date('d M Y, h:i A', $timestamp);
}

function formatCurrency($value, $fallback = '—') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return '₹' . number_format((float)$value, 2);
}

function formatNumber($value, $decimals = 0, $fallback = '—') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return number_format((float)$value, $decimals);
}

$page_title = ($not_found ? 'Item Not Found' : safeValue($item['name'])) . ' - Catalog - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<style>
    .card-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    
    @media (max-width: 1200px) {
        .card-container {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .card-container {
            grid-template-columns: 1fr;
        }
    }
    
    .info-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .info-card h3 {
        color: #003581;
        margin: 0 0 16px;
        border-bottom: 2px solid #003581;
        padding-bottom: 8px;
        font-size: 16px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        font-size: 14px;
    }
    
    .info-grid > div {
        display: flex;
        flex-direction: column;
    }
    
    .info-grid strong {
        color: #495057;
        font-weight: 600;
        margin-bottom: 4px;
    }
    
    .badge-custom {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }
    
    .badge-product {
        background: #e3f2fd;
        color: #003581;
    }
    
    .badge-service {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .badge-active {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
    
    .tab-navigation {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid #e0e0e0;
        margin-bottom: 20px;
    }
    
    .tab-link {
        padding: 12px 20px;
        text-decoration: none;
        color: #666;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        display: inline-block;
    }
    
    .tab-link:hover {
        color: #0066cc;
        background: #f8f9fa;
    }
    
    .tab-active {
        color: #0066cc;
        border-bottom-color: #0066cc;
        font-weight: 600;
    }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🛍️ Catalog Item Details</h1>
                    <p>Comprehensive product and service information</p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="index.php" class="btn btn-accent">← Back to Catalog</a>
                    <?php if (!$not_found && $can_edit): ?>
                        <a href="edit.php?id=<?php echo $item_id; ?>" class="btn" style="margin-left: 8px;">✏️ Edit</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($not_found): ?>
            <!-- Item Not Found -->
            <div class="alert alert-danger">
                <strong>❌ Item Not Found</strong><br>
                The requested catalog item could not be found or you don't have permission to view it.
            </div>
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 80px; margin-bottom: 20px;">📭</div>
                <h2 style="color: #003581; margin-bottom: 15px;">Item Not Found</h2>
                <p style="color: #6c757d; margin-bottom: 30px;">
                    The catalog item you're looking for doesn't exist or has been removed.
                </p>
                <a href="index.php" class="btn">Return to Catalog</a>
            </div>
        <?php else: ?>
            <!-- Item Header Card -->
            <div class="card" style="display: flex; gap: 20px; align-items: center;">
                <!-- Item Image/Icon -->
                <?php
                    $primaryPath = isset($item['primary_image']) ? trim($item['primary_image']) : '';
                    $primaryFs = $primaryPath ? __DIR__ . '/../../' . ltrim($primaryPath, '/') : '';
                    $primaryUrl = APP_URL . '/' . ltrim($primaryPath, '/');
                    $imageExists = $primaryPath && file_exists($primaryFs);
                ?>
                <?php if ($imageExists): ?>
                    <img src="<?php echo htmlspecialchars($primaryUrl); ?>" 
                         alt="<?php echo safeValue($item['name']); ?>" 
                         style="width: 120px; height: 120px; border-radius: 12px; object-fit: cover; border: 2px solid #e0e0e0;">
                <?php else: ?>
                    <div style="width: 120px; height: 120px; border-radius: 12px; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 48px;">
                        <?php echo $item['type'] === 'Product' ? '📦' : '🛠️'; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Item Info -->
                <div style="flex: 1;">
                    <div style="font-size: 24px; color: #003581; font-weight: 700; margin-bottom: 8px;">
                        <?php echo safeValue($item['name']); ?>
                        <span style="font-size: 14px; color: #6c757d; font-weight: 500; margin-left: 8px; font-family: monospace;">
                            (SKU: <?php echo safeValue($item['sku']); ?>)
                        </span>
                    </div>
                    <div style="color: #6c757d; font-size: 14px; margin-bottom: 10px;">
                        Category: <?php echo safeValue($item['category'], 'Uncategorized'); ?>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <span class="badge-custom <?php echo $item['type'] === 'Product' ? 'badge-product' : 'badge-service'; ?>">
                            <?php echo $item['type'] === 'Product' ? '📦 Product' : '🛠️ Service'; ?>
                        </span>
                        <span class="badge-custom <?php echo $item['status'] === 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo safeValue($item['status']); ?>
                        </span>
                        <?php if ($item['type'] === 'Product' && !empty($item['is_low_stock'])): ?>
                            <span class="badge-custom badge-warning">⚠️ Low Stock</span>
                        <?php endif; ?>
                        <?php if (!empty($item['is_expired'])): ?>
                            <span class="badge-custom badge-danger">⏰ Expired</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div style="text-align: right;">
                    <?php if ($can_edit && $item['type'] === 'Product'): ?>
                        <a href="stock_adjust.php?id=<?php echo $item_id; ?>" class="btn btn-primary" style="margin-bottom: 8px; display: block; text-decoration: none;">
                            📊 Adjust Stock
                        </a>
                    <?php endif; ?>
                    <div style="font-size: 28px; font-weight: 700; color: #003581;">
                        <?php echo formatCurrency($item['base_price']); ?>
                    </div>
                    <div style="font-size: 12px; color: #6c757d;">
                        Base Price
                    </div>
                </div>
            </div>

            <!-- Information Cards Grid -->
            <div class="card-container">
                <!-- Pricing Information -->
                <div class="info-card">
                    <h3>💰 Pricing & Tax</h3>
                    <div class="info-grid">
                        <div>
                            <strong>Base Price</strong>
                            <span><?php echo formatCurrency($item['base_price']); ?></span>
                        </div>
                        <div>
                            <strong>Tax Rate</strong>
                            <span><?php echo formatNumber($item['tax_percent'], 2); ?>%</span>
                        </div>
                        <div>
                            <strong>Default Discount</strong>
                            <span><?php echo formatCurrency($item['default_discount']); ?></span>
                        </div>
                        <div>
                            <strong>Final Price (with tax)</strong>
                            <span style="color: #28a745; font-weight: 600;">
                                <?php 
                                    $basePrice = (float)($item['base_price'] ?? 0);
                                    $taxPercent = (float)($item['tax_percent'] ?? 0);
                                    $discount = (float)($item['default_discount'] ?? 0);
                                    $finalPrice = ($basePrice - $discount) * (1 + $taxPercent / 100);
                                    echo formatCurrency($finalPrice);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Inventory (Products Only) -->
                <?php if ($item['type'] === 'Product'): ?>
                <div class="info-card">
                    <h3>📦 Inventory Status</h3>
                    <div class="info-grid">
                        <div>
                            <strong>Current Stock</strong>
                            <span style="font-size: 24px; font-weight: 700; color: <?php echo !empty($item['is_low_stock']) ? '#f57c00' : '#28a745'; ?>;">
                                <?php echo formatNumber($item['current_stock']); ?>
                            </span>
                        </div>
                        <div>
                            <strong>Low Stock Threshold</strong>
                            <span><?php echo formatNumber($item['low_stock_threshold'], 0, 'Not set'); ?></span>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <strong>Stock Value</strong>
                            <span style="font-size: 18px; font-weight: 600; color: #003581;">
                                <?php 
                                    $stockValue = (float)($item['current_stock'] ?? 0) * (float)($item['base_price'] ?? 0);
                                    echo formatCurrency($stockValue);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Item Information -->
                <div class="info-card">
                    <h3>📋 Item Information</h3>
                    <div class="info-grid">
                        <div>
                            <strong>Item Type</strong>
                            <span><?php echo safeValue($item['type']); ?></span>
                        </div>
                        <div>
                            <strong>Category</strong>
                            <span><?php echo safeValue($item['category'], 'Uncategorized'); ?></span>
                        </div>
                        <div>
                            <strong>Status</strong>
                            <span><?php echo safeValue($item['status']); ?></span>
                        </div>
                        <div>
                            <strong>Expiry Date</strong>
                            <span><?php echo safeDate($item['expiry_date']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Audit Information -->
                <div class="info-card">
                    <h3>👤 Audit Trail</h3>
                    <div class="info-grid">
                        <div>
                            <strong>Created By</strong>
                            <span><?php echo safeValue($item['created_by_name'], 'System'); ?></span>
                        </div>
                        <div>
                            <strong>Created At</strong>
                            <span><?php echo safeDateTime($item['created_at']); ?></span>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <strong>Last Updated</strong>
                            <span><?php echo safeDateTime($item['updated_at']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="info-card" style="grid-column: 1 / -1;">
                    <h3>📝 Description</h3>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px; min-height: 60px;">
                        <?php if (!empty($item['description_html'])): ?>
                            <?php echo $item['description_html']; ?>
                        <?php else: ?>
                            <em class="text-muted">No description provided</em>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Media -->
                <div class="info-card">
                    <h3>🖼️ Media & Documents</h3>
                    <div style="display: grid; gap: 16px;">
                        <div>
                            <strong>Primary Image</strong>
                            <div style="margin-top: 8px;">
                                <?php if ($imageExists): ?>
                                    <a href="<?php echo htmlspecialchars($primaryUrl); ?>" target="_blank">
                                        <img src="<?php echo htmlspecialchars($primaryUrl); ?>" 
                                             alt="<?php echo safeValue($item['name']); ?>" 
                                             style="width: 100%; max-width: 300px; border-radius: 8px; border: 1px solid #e0e0e0;">
                                    </a>
                                <?php else: ?>
                                    <div style="padding: 20px; background: #f0f0f0; border-radius: 8px; text-align: center; color: #6c757d;">
                                        No image uploaded
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <strong>Brochure</strong>
                            <div style="margin-top: 8px;">
                                <?php 
                                    $brochurePath = isset($item['brochure_pdf']) ? trim($item['brochure_pdf']) : '';
                                    $brochureFs = $brochurePath ? __DIR__ . '/../../' . ltrim($brochurePath, '/') : '';
                                    $brochureUrl = APP_URL . '/' . ltrim($brochurePath, '/');
                                    $brochureExists = $brochurePath && file_exists($brochureFs);
                                ?>
                                <?php if ($brochureExists): ?>
                                    <a href="<?php echo htmlspecialchars($brochureUrl); ?>" target="_blank" class="btn btn-primary btn-block">
                                        📄 View Brochure (PDF)
                                    </a>
                                <?php else: ?>
                                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px; text-align: center; color: #6c757d; font-size: 13px;">
                                        No brochure available
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <?php if ($item['type'] === 'Product'): ?>
            <div class="card" style="margin-top: 30px;">
                <div class="tab-navigation">
                    <a href="?id=<?php echo $item_id; ?>&tab=inventory" 
                       class="tab-link <?php echo ($_GET['tab'] ?? '') === 'inventory' ? 'tab-active' : ''; ?>">
                        📦 Inventory Log (<?php echo count($inventory_logs); ?>)
                    </a>
                    <a href="?id=<?php echo $item_id; ?>&tab=files" 
                       class="tab-link <?php echo ($_GET['tab'] ?? '') === 'files' ? 'tab-active' : ''; ?>">
                        📎 Files (<?php echo count($files); ?>)
                    </a>
                    <a href="?id=<?php echo $item_id; ?>&tab=history" 
                       class="tab-link <?php echo ($_GET['tab'] ?? '') === 'history' ? 'tab-active' : ''; ?>">
                        📜 Change History (<?php echo count($change_logs); ?>)
                    </a>
                </div>

                <?php
                $active_tab = $_GET['tab'] ?? 'inventory';
                ?>

                <!-- Inventory Log Tab -->
                <?php if ($active_tab === 'inventory'): ?>
                    <h3 style="color: #003581; margin-bottom: 16px;">Inventory Movement Log</h3>
                    <?php if (empty($inventory_logs)): ?>
                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                            <div style="font-size: 48px; margin-bottom: 12px;">📊</div>
                            <p>No inventory movements recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Date</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Action</th>
                                        <th style="padding: 12px; text-align: right; font-weight: 600; color: #003581;">Quantity</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Before</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">After</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Reason</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory_logs as $log): ?>
                                        <tr style="border-bottom: 1px solid #dee2e6;">
                                            <td style="padding: 12px; font-size: 13px;">
                                                <?php echo safeDateTime($log['created_at']); ?>
                                            </td>
                                            <td style="padding: 12px;">
                                                <?php
                                                $actionColors = [
                                                    'Add' => 'background: #d4edda; color: #155724;',
                                                    'Reduce' => 'background: #fff3cd; color: #856404;',
                                                    'InvoiceDeduct' => 'background: #f8d7da; color: #721c24;',
                                                    'Correction' => 'background: #cce5ff; color: #004085;'
                                                ];
                                                $style = $actionColors[$log['action']] ?? 'background: #e2e3e5; color: #383d41;';
                                                ?>
                                                <span style="<?php echo $style; ?> padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                                    <?php echo safeValue($log['action']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px; text-align: right; font-weight: 600; font-size: 15px; color: <?php echo $log['quantity_delta'] > 0 ? '#28a745' : '#dc3545'; ?>;">
                                                <?php echo $log['quantity_delta'] > 0 ? '+' : ''; ?><?php echo formatNumber($log['quantity_delta']); ?>
                                            </td>
                                            <td style="padding: 12px; text-align: center; font-weight: 600;">
                                                <?php echo formatNumber($log['qty_before']); ?>
                                            </td>
                                            <td style="padding: 12px; text-align: center; font-weight: 600;">
                                                <?php echo formatNumber($log['qty_after']); ?>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                                <?php echo safeValue($log['reason']); ?>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px;">
                                                <?php echo safeValue($log['created_by_name'], 'System'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Files Tab -->
                <?php if ($active_tab === 'files'): ?>
                    <h3 style="color: #003581; margin-bottom: 16px;">File History</h3>
                    <?php if (empty($files)): ?>
                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                            <div style="font-size: 48px; margin-bottom: 12px;">📎</div>
                            <p>No files uploaded yet.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Type</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Preview</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Uploaded By</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Uploaded At</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file): ?>
                                        <?php 
                                            $filePath = trim($file['file_path'] ?? '');
                                            $fileFs = $filePath ? (__DIR__ . '/../../' . ltrim($filePath, '/')) : '';
                                            $fileUrl = $filePath ? (APP_URL . '/' . ltrim($filePath, '/')) : '#';
                                            $exists = $filePath && file_exists($fileFs);
                                        ?>
                                        <tr style="border-bottom: 1px solid #dee2e6;">
                                            <td style="padding: 12px;">
                                                <span style="background: #e3f2fd; color: #003581; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                                    <?php echo safeValue($file['file_type']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px;">
                                                <?php if ($file['file_type'] === 'PrimaryImage' && $exists): ?>
                                                    <img src="<?php echo htmlspecialchars($fileUrl); ?>" 
                                                         alt="Image" style="height: 50px; border-radius: 6px; border: 1px solid #dee2e6;">
                                                <?php elseif ($file['file_type'] === 'Brochure' && $exists): ?>
                                                    <span style="font-size: 24px;">📄</span>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 12px;">(file missing)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px;">
                                                <?php echo safeValue($file['uploaded_by_name'], 'Unknown'); ?>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                                <?php echo safeDateTime($file['uploaded_at']); ?>
                                            </td>
                                            <td style="padding: 12px; text-align: center;">
                                                <?php if ($exists): ?>
                                                    <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" class="btn btn-sm btn-primary" style="text-decoration: none;">
                                                        👁️ View
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 12px;">Not available</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Change History Tab -->
                <?php if ($active_tab === 'history'): ?>
                    <h3 style="color: #003581; margin-bottom: 16px;">Change History</h3>
                    <?php if (empty($change_logs)): ?>
                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                            <div style="font-size: 48px; margin-bottom: 12px;">📜</div>
                            <p>No changes recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Date</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Change Type</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Details</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Changed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($change_logs as $log): ?>
                                        <tr style="border-bottom: 1px solid #dee2e6;">
                                            <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                                <?php echo safeDateTime($log['created_at']); ?>
                                            </td>
                                            <td style="padding: 12px;">
                                                <span style="background: #cce5ff; color: #004085; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                                    <?php echo safeValue($log['change_type']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px;">
                                                <?php if (!empty($log['changed_fields'])): ?>
                                                    <details>
                                                        <summary style="cursor: pointer; color: #003581; font-weight: 600;">View changes</summary>
                                                        <pre style="margin-top: 8px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 11px; overflow-x: auto; max-width: 500px;"><?php echo htmlspecialchars($log['changed_fields']); ?></pre>
                                                    </details>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px;">
                                                <?php echo safeValue($log['changed_by_name'], 'System'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
