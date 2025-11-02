<?php
/**
 * Catalog Module - Main Listing Page
 * View all products and services with filters, search, and statistics
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist - show setup prompt if not
if (!catalog_tables_exist($conn)) {
    $page_title = 'Catalog Module Setup Required - ' . APP_NAME;
    require_once __DIR__ . '/../../includes/header_sidebar.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    ?>
    <div class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1>🛍️ Catalog - Products & Services</h1>
                    <p>Manage your products and services inventory</p>
                </div>
            </div>
            
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <strong>⚠️ Setup Required</strong><br>
                Catalog module database tables need to be created first.
            </div>
            
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 80px; margin-bottom: 20px;">📦</div>
                <h2 style="color: #003581; margin-bottom: 15px;">Catalog Module Not Set Up</h2>
                <p style="color: #6c757d; margin-bottom: 30px; font-size: 16px;">
                    Create the required database tables to start managing products and services
                </p>
                <a href="../../scripts/setup_catalog_tables.php" class="btn" style="padding: 15px 40px; font-size: 16px;">
                    🚀 Setup Catalog Module
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

// All logged-in users have access to catalog
$can_create = true;
$can_edit = true;
$can_delete = true;
$can_export = true;

// Get filters from request
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$low_stock = isset($_GET['low_stock']) ? $_GET['low_stock'] : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
if ($search) {
    $where_clauses[] = "(name LIKE '%$search%' OR sku LIKE '%$search%' OR category LIKE '%$search%')";
}
if ($type_filter) {
    $where_clauses[] = "type = '$type_filter'";
}
if ($status_filter) {
    $where_clauses[] = "status = '$status_filter'";
}
if ($category_filter) {
    $where_clauses[] = "category = '$category_filter'";
}
if ($low_stock === '1') {
    $where_clauses[] = "type = 'Product' AND current_stock <= COALESCE(low_stock_threshold, 10)";
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM items_master $where_sql";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Get items
$query = "SELECT id, sku, name, type, category, base_price, tax_percent, current_stock, 
          low_stock_threshold, status, primary_image, created_at,
          CASE WHEN type = 'Product' AND current_stock <= COALESCE(low_stock_threshold, 10) THEN 1 ELSE 0 END as is_low_stock
          FROM items_master 
          $where_sql 
          ORDER BY created_at DESC 
          LIMIT $per_page OFFSET $offset";
$result = mysqli_query($conn, $query);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM items_master WHERE category IS NOT NULL AND category != '' ORDER BY category";
$cat_result = mysqli_query($conn, $cat_query);
$categories = [];
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat['category'];
}

// Get statistics
$stats_conn = createConnection(true);
$stats = [
    'total_items' => mysqli_fetch_assoc(mysqli_query($stats_conn, "SELECT COUNT(*) as count FROM items_master"))['count'],
    'products' => mysqli_fetch_assoc(mysqli_query($stats_conn, "SELECT COUNT(*) as count FROM items_master WHERE type='Product'"))['count'],
    'services' => mysqli_fetch_assoc(mysqli_query($stats_conn, "SELECT COUNT(*) as count FROM items_master WHERE type='Service'"))['count'],
    'low_stock_items' => mysqli_fetch_assoc(mysqli_query($stats_conn, "SELECT COUNT(*) as count FROM items_master WHERE type='Product' AND current_stock <= COALESCE(low_stock_threshold, 10)"))['count'],
    'active_items' => mysqli_fetch_assoc(mysqli_query($stats_conn, "SELECT COUNT(*) as count FROM items_master WHERE status='Active'"))['count'],
    'total_stock_value' => mysqli_fetch_assoc(mysqli_query($stats_conn, "SELECT COALESCE(SUM(current_stock * base_price), 0) as value FROM items_master WHERE type='Product'"))['value']
];
closeConnection($stats_conn);

$total_pages = ceil($total_records / $per_page);

$page_title = 'Catalog - Products & Services - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🛍️ Catalog - Products & Services</h1>
                    <p>Manage your products and services inventory</p>
                </div>
                <div>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn" style="display: inline-flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">➕</span> Add New Item
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <strong>✅ Success!</strong><br>
                <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <strong>❌ Error!</strong><br>
                <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['total_items']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Items</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['products']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Products</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['services']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Services</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #faa718 0%, #ffc04d 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['low_stock_items']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Low Stock Alerts</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #17a2b8 0%, #20c9e3 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['active_items']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Active Items</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #dc3545 0%, #e63946 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;">₹<?php echo number_format($stats['total_stock_value'], 0); ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Stock Value</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card" style="margin-bottom: 25px;">
            <form method="GET" action="index.php" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>🔍 Search Items</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, SKU, Category..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>📦 Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Product" <?php echo $type_filter == 'Product' ? 'selected' : ''; ?>>Product</option>
                        <option value="Service" <?php echo $type_filter == 'Service' ? 'selected' : ''; ?>>Service</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>📊 Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>🏷️ Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="white-space: nowrap;">Search</button>
                    <a href="index.php" class="btn btn-accent" style="white-space: nowrap; text-decoration: none; display: inline-block; text-align: center;">Clear</a>
                </div>
            </form>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" onchange="toggleLowStock(this)" <?php echo $low_stock === '1' ? 'checked' : ''; ?>>
                    <span>⚠️ Show only low stock items</span>
                </label>
            </div>
        </div>

        <!-- Catalog List -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #003581;">
                    📋 Catalog Items 
                    <span style="font-size: 14px; color: #6c757d; font-weight: normal;">(<?php echo $total_records; ?> records)</span>
                </h3>
                <div style="display: flex; gap: 10px;">
                    <?php if ($can_export): ?>
                        <a href="<?php echo APP_URL; ?>/public/api/catalog/export.php?search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&low_stock=<?php echo urlencode($low_stock); ?>" class="btn btn-accent" style="padding: 8px 16px; font-size: 13px; text-decoration: none;">
                            📊 Export to CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($items) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Image</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">SKU</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Name</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Type</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Category</th>
                                <th style="padding: 12px; text-align: right; font-weight: 600; color: #003581;">Base Price</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Stock</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Status</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 12px;">
                                        <?php if ($item['primary_image'] && file_exists(__DIR__ . '/../../' . $item['primary_image'])): ?>
                                            <img src="<?php echo APP_URL . '/' . htmlspecialchars($item['primary_image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid #dee2e6;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; border-radius: 8px; background: #e3f2fd; color: #003581; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                                <?php echo $item['type'] === 'Product' ? '📦' : '🛠️'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; font-weight: 600; color: #003581; font-family: monospace;">
                                        <?php echo htmlspecialchars($item['sku']); ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <div style="font-weight: 600; color: #212529;"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <?php if ($item['is_low_stock'] && $item['type'] === 'Product'): ?>
                                            <div style="margin-top: 4px;">
                                                <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">
                                                    ⚠️ Low Stock
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <span style="background: <?php echo $item['type'] === 'Product' ? '#e3f2fd' : '#e8f5e9'; ?>; color: <?php echo $item['type'] === 'Product' ? '#003581' : '#2e7d32'; ?>; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                            <?php echo htmlspecialchars($item['type']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; color: #6c757d; font-size: 13px;">
                                        <?php echo htmlspecialchars($item['category'] ?: 'Uncategorized'); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-weight: 600; color: #212529;">
                                        ₹<?php echo number_format($item['base_price'], 2); ?>
                                        <?php if ($item['tax_percent'] > 0): ?>
                                            <div style="font-size: 11px; color: #6c757d; font-weight: normal;">+<?php echo $item['tax_percent']; ?>% tax</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <?php if ($item['type'] === 'Product'): ?>
                                            <div style="font-weight: 600; color: <?php echo $item['is_low_stock'] ? '#dc3545' : '#28a745'; ?>;">
                                                <?php echo $item['current_stock']; ?>
                                            </div>
                                            <?php if ($item['low_stock_threshold']): ?>
                                                <div style="font-size: 11px; color: #6c757d;">min: <?php echo $item['low_stock_threshold']; ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 12px;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <?php
                                        $status_colors = [
                                            'Active' => 'background: #d4edda; color: #155724;',
                                            'Inactive' => 'background: #f8d7da; color: #721c24;'
                                        ];
                                        $status_style = $status_colors[$item['status']] ?? 'background: #e2e3e5; color: #383d41;';
                                        ?>
                                        <span style="<?php echo $status_style; ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                            <?php echo htmlspecialchars($item['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <a href="view.php?id=<?php echo $item['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                                👁️ View
                                            </a>
                                            <?php if ($can_edit): ?>
                                                <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                                    ✏️ Edit
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="margin-top: 25px; display: flex; justify-content: center; align-items: center; gap: 10px;">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&low_stock=<?php echo urlencode($low_stock); ?>" class="btn" style="padding: 8px 16px; text-decoration: none;">
                                « Previous
                            </a>
                        <?php endif; ?>
                        
                        <span style="color: #6c757d; font-size: 14px;">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&low_stock=<?php echo urlencode($low_stock); ?>" class="btn" style="padding: 8px 16px; text-decoration: none;">
                                Next »
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                    <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                    <h3 style="color: #003581; margin-bottom: 10px;">No Items Found</h3>
                    <p>No items match your search criteria. Try adjusting your filters or add a new item.</p>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn" style="margin-top: 20px; text-decoration: none;">➕ Add New Item</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-accent" style="margin-top: 20px; text-decoration: none;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleLowStock(checkbox) {
    const url = new URL(window.location.href);
    if (checkbox.checked) {
        url.searchParams.set('low_stock', '1');
    } else {
        url.searchParams.delete('low_stock');
    }
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
