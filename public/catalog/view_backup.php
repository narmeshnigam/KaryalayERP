<?php
/**
 * Catalog Module - View Item Details
 * Display complete item information with tabs
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

// Get related data
$inventory_logs = get_item_inventory_log($conn, $item_id);
$files = get_item_files($conn, $item_id);
$change_logs = get_item_change_log($conn, $item_id);

// Check permissions
$catalog_permissions = authz_get_permission_set($conn, 'items_master');
$can_edit = $catalog_permissions['can_edit_all'] || $IS_SUPER_ADMIN;
$can_delete = $catalog_permissions['can_delete_all'] || $IS_SUPER_ADMIN;

// Active tab
$active_tab = $_GET['tab'] ?? 'overview';

$page_title = htmlspecialchars($item['name']) . ' - Catalog - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <h1 style="margin: 0;"><?php echo htmlspecialchars($item['name'] ?? ''); ?></h1>
                        <span class="badge <?php echo (isset($item['type']) && $item['type'] === 'Product') ? 'badge-info' : 'badge-success'; ?>">
                            <?php echo htmlspecialchars($item['type'] ?? ''); ?>
                        </span>
                        <span class="badge <?php echo (isset($item['status']) && $item['status'] === 'Active') ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo htmlspecialchars($item['status'] ?? ''); ?>
                        </span>
                        <?php if (!empty($item['is_low_stock'])): ?>
                            <span class="badge badge-warning">Low Stock</span>
                        <?php endif; ?>
                        <?php if (!empty($item['is_expired'])): ?>
                            <span class="badge badge-danger">Expired</span>
                        <?php endif; ?>
                    </div>
                    <p style="color: #6c757d; margin: 0;">SKU: <code><?php echo htmlspecialchars($item['sku'] ?? ''); ?></code></p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <?php if ($can_edit): ?>
                        <a href="edit.php?id=<?php echo $item_id; ?>" class="btn btn-warning">✏️ Edit</a>
                    <?php endif; ?>
                    <?php if ($can_edit && (($item['type'] ?? '') === 'Product')): ?>
                        <a href="stock_adjust.php?id=<?php echo $item_id; ?>" class="btn btn-primary">📊 Adjust Stock</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">← Back to Catalog</a>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card" style="margin-bottom: 0;">
            <div style="border-bottom: 2px solid #e0e0e0;">
                <nav style="display: flex; gap: 8px;">
                    <a href="?id=<?php echo $item_id; ?>&tab=overview" 
                       class="<?php echo $active_tab === 'overview' ? 'tab-active' : 'tab-link'; ?>">
                        📋 Overview
                    </a>
                    <?php if (($item['type'] ?? '') === 'Product'): ?>
                    <a href="?id=<?php echo $item_id; ?>&tab=inventory" 
                       class="<?php echo $active_tab === 'inventory' ? 'tab-active' : 'tab-link'; ?>">
                        📦 Inventory Log (<?php echo count($inventory_logs); ?>)
                    </a>
                    <?php endif; ?>
                    <a href="?id=<?php echo $item_id; ?>&tab=files" 
                       class="<?php echo $active_tab === 'files' ? 'tab-active' : 'tab-link'; ?>">
                        📎 Files (<?php echo count($files); ?>)
                    </a>
                    <a href="?id=<?php echo $item_id; ?>&tab=history" 
                       class="<?php echo $active_tab === 'history' ? 'tab-active' : 'tab-link'; ?>">
                        📜 Change History (<?php echo count($change_logs); ?>)
                    </a>
                </nav>
            </div>
        </div>

        <!-- Tab Content -->
        <?php if ($active_tab === 'overview'): ?>
            <!-- Overview Tab -->
            <div class="card">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                    <!-- Main Info -->
                    <div>
                        <h3 style="margin-top: 0;">Item Details</h3>
                        
                        <div style="display: grid; gap: 16px;">
                            <div>
                                <strong>Category:</strong> <?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?>
                            </div>
                            
                            <div>
                                <strong>Description:</strong>
                                <div style="margin-top: 8px; padding: 12px; background: #f8f9fa; border-radius: 4px;">
                                    <?php if (!empty($item['description_html'])): ?>
                                        <?php echo $item['description_html']; ?>
                                    <?php else: ?>
                                        <em class="text-muted">No description provided</em>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div>
                                        <strong>Base Price:</strong> ₹<?php echo number_format((float)($item['base_price'] ?? 0), 2); ?>
                                    </div>
                                    <div>
                                        <strong>Tax:</strong> <?php echo htmlspecialchars($item['tax_percent'] ?? '0'); ?>%
                                    </div>
                                    <div>
                                        <strong>Default Discount:</strong> ₹<?php echo number_format((float)($item['default_discount'] ?? 0), 2); ?>
                                    </div>
                                    <div>
                                        <strong>Expiry Date:</strong> 
                                        <?php if (!empty($item['expiry_date'])): ?>
                                            <?php echo date('d M Y', strtotime($item['expiry_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            
                            <?php if ((isset($item['type']) ? $item['type'] : '') === 'Product'): ?>
                            <div style="padding: 16px; background: #e3f2fd; border-radius: 4px;">
                                <h4 style="margin-top: 0;">Inventory</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <strong>Current Stock:</strong> 
                                        <span style="font-size: 1.5em; color: <?php echo !empty($item['is_low_stock']) ? '#f57c00' : '#0066cc'; ?>;">
                                            <?php echo htmlspecialchars($item['current_stock'] ?? '0'); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <strong>Low Stock Threshold:</strong> 
                                        <?php echo htmlspecialchars($item['low_stock_threshold'] ?? 'Not set'); ?>
                                    </div>
                                    <div>
                                        <strong>Stock Value:</strong> 
                                        ₹<?php echo number_format((float)($item['current_stock'] ?? 0) * (float)($item['base_price'] ?? 0), 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0;">
                                <div>
                                    <strong>Created By:</strong> <?php echo htmlspecialchars($item['created_by_name'] ?? 'Unknown'); ?>
                                </div>
                                <div>
                                    <strong>Created At:</strong> 
                                    <?php if (!empty($item['created_at'])): ?>
                                        <?php echo date('d M Y, h:i A', strtotime($item['created_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong>Last Updated:</strong> 
                                    <?php if (!empty($item['updated_at'])): ?>
                                        <?php echo date('d M Y, h:i A', strtotime($item['updated_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div>
                        <h3 style="margin-top: 0;">Media</h3>
                        
                        <div style="margin-bottom: 20px;">
                            <strong>Primary Image</strong>
                            <div style="margin-top: 8px;">
                                <?php 
                                    $primaryPath = isset($item['primary_image']) ? trim($item['primary_image']) : '';
                                    $primaryFs = $primaryPath ? __DIR__ . '/../../' . ltrim($primaryPath, '/') : '';
                                    $primaryUrl = APP_URL . '/' . ltrim($primaryPath, '/');
                                ?>
                                <?php if ($primaryPath && file_exists($primaryFs)): ?>
                                    <img src="<?php echo htmlspecialchars($primaryUrl); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" 
                                         style="width: 100%; border-radius: 4px; border: 1px solid #e0e0e0;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 48px;">
                                        <?php echo ((isset($item['type']) ? $item['type'] : '') === 'Product') ? '📦' : '🛠️'; ?>
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
                                ?>
                                <?php if ($brochurePath && file_exists($brochureFs)): ?>
                                    <a href="<?php echo htmlspecialchars($brochureUrl); ?>" target="_blank" class="btn btn-primary btn-block">
                                        📄 View Brochure (PDF)
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">No brochure available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'inventory' && (($item['type'] ?? '') === 'Product')): ?>
            <!-- Inventory Log Tab -->
            <div class="card">
                <h3 class="card-title">Inventory Movement Log</h3>
                
                <?php if (empty($inventory_logs)): ?>
                    <div class="alert alert-info">
                        <p style="margin: 0;">No inventory movements recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Quantity</th>
                                    <th>Before</th>
                                    <th>After</th>
                                    <th>Reason</th>
                                    <th>Reference</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_logs as $log): ?>
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
                                        <td><?php echo $log['qty_before']; ?></td>
                                        <td><?php echo $log['qty_after']; ?></td>
                                        <td><?php echo htmlspecialchars($log['reason'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($log['reference_id']): ?>
                                                <?php echo htmlspecialchars($log['reference_type']); ?> #<?php echo $log['reference_id']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['created_by_name'] ?? 'System'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'files'): ?>
            <!-- Files Tab -->
            <div class="card">
                <h3 class="card-title">File History</h3>
                
                <?php if (empty($files)): ?>
                    <div class="alert alert-info">
                        <p style="margin: 0;">No files uploaded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>File</th>
                                    <th>Uploaded By</th>
                                    <th>Uploaded At</th>
                                    <th>Actions</th>
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
                                    <tr>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($file['file_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($file['file_type'] === 'PrimaryImage'): ?>
                                                <?php if ($exists): ?>
                                                    <img src="<?php echo htmlspecialchars($fileUrl); ?>" 
                                                         alt="Image" style="height: 40px; border-radius: 4px;">
                                                <?php else: ?>
                                                    <span class="text-muted">(missing)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($exists): ?>
                                                    📄 PDF Document
                                                <?php else: ?>
                                                    <span class="text-muted">(missing)</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($file['uploaded_by_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($file['uploaded_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" class="btn btn-sm btn-primary" <?php echo !$exists ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'history'): ?>
            <!-- Change History Tab -->
            <div class="card">
                <h3 class="card-title">Change History</h3>
                
                <?php if (empty($change_logs)): ?>
                    <div class="alert alert-info">
                        <p style="margin: 0;">No changes recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Change Type</th>
                                    <th>Details</th>
                                    <th>Changed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($change_logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($log['change_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['changed_fields']): ?>
                                                <details>
                                                    <summary style="cursor: pointer;">View changes</summary>
                                                    <pre style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 0.85em;"><?php echo htmlspecialchars($log['changed_fields']); ?></pre>
                                                </details>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['changed_by_name'] ?? 'System'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
    .tab-link, .tab-active {
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

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
