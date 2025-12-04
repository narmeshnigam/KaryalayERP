<?php
/**
 * Asset & Resource Management - Asset List
 * Browse all assets with filters, search, and bulk actions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$conn = createConnection(true);

// Check if module is set up
$table_check = @mysqli_query($conn, "SHOW TABLES LIKE 'assets_master'");
if (!$table_check || mysqli_num_rows($table_check) == 0) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

require_once __DIR__ . '/helpers.php';

$page_title = 'Asset Registry - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$filters = [
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? '',
    'department' => $_GET['department'] ?? '',
    'search' => $_GET['search'] ?? '',
    'warranty_expiring' => $_GET['warranty_expiring'] ?? ''
];

// Get assets
$assets = getAssets($conn, $filters);

// Get unique departments for filter
$dept_query = "SELECT DISTINCT department FROM assets_master WHERE department IS NOT NULL ORDER BY department";
$dept_result = mysqli_query($conn, $dept_query);
$departments = [];
while ($row = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $row['department'];
}

closeConnection($conn);
?>

<div class="main-wrapper">
<style>
.assets-list-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.assets-list-header-buttons{display:flex;gap:10px;flex-wrap:wrap;}

@media (max-width:768px){
.assets-list-header-flex{flex-direction:column;align-items:stretch;}
.assets-list-header-buttons{width:100%;flex-direction:column;gap:10px;}
.assets-list-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.assets-list-header-flex h1{font-size:1.5rem;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="assets-list-header-flex">
                <div>
                    <h1 style="margin: 0;">📋 Asset Registry</h1>
                    <p style="margin: 5px 0 0 0; color: #666;"><?php echo count($assets); ?> asset(s) found</p>
                </div>
                <div class="assets-list-header-buttons">
                    <a href="add.php" class="btn">
                        ➕ Add New Asset
                    </a>
                    <a href="index.php" class="btn btn-accent">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 20px;">
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Code, Serial..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <option value="IT" <?php echo $filters['category'] === 'IT' ? 'selected' : ''; ?>>IT</option>
                        <option value="Vehicle" <?php echo $filters['category'] === 'Vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                        <option value="Tool" <?php echo $filters['category'] === 'Tool' ? 'selected' : ''; ?>>Tool</option>
                        <option value="Machine" <?php echo $filters['category'] === 'Machine' ? 'selected' : ''; ?>>Machine</option>
                        <option value="Furniture" <?php echo $filters['category'] === 'Furniture' ? 'selected' : ''; ?>>Furniture</option>
                        <option value="Space" <?php echo $filters['category'] === 'Space' ? 'selected' : ''; ?>>Space</option>
                        <option value="Other" <?php echo $filters['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Available" <?php echo $filters['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="In Use" <?php echo $filters['status'] === 'In Use' ? 'selected' : ''; ?>>In Use</option>
                        <option value="Under Maintenance" <?php echo $filters['status'] === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        <option value="Broken" <?php echo $filters['status'] === 'Broken' ? 'selected' : ''; ?>>Broken</option>
                        <option value="Decommissioned" <?php echo $filters['status'] === 'Decommissioned' ? 'selected' : ''; ?>>Decommissioned</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Department</label>
                    <select name="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" 
                                <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Warranty</label>
                    <select name="warranty_expiring" class="form-control">
                        <option value="">All</option>
                        <option value="7" <?php echo $filters['warranty_expiring'] === '7' ? 'selected' : ''; ?>>Expiring in 7 days</option>
                        <option value="15" <?php echo $filters['warranty_expiring'] === '15' ? 'selected' : ''; ?>>Expiring in 15 days</option>
                        <option value="30" <?php echo $filters['warranty_expiring'] === '30' ? 'selected' : ''; ?>>Expiring in 30 days</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="flex: 1;">🔍 Filter</button>
                    <a href="list.php" class="btn btn-accent" style="flex: 1; text-align: center;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Assets Table -->
        <div class="card">
            <?php if (!empty($assets)): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #003581;">
                    📦 Asset List
                    <span style="font-size: 14px; color: #6c757d; font-weight: normal;">(<?php echo count($assets); ?> records)</span>
                </h3>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Asset Code</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Name</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Category</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Type/Model</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Condition</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Location</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Warranty</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 12px;">
                                <a href="view.php?id=<?php echo $asset['id']; ?>" style="text-decoration: none; color: #003581; font-weight: 600;">
                                    <?php echo htmlspecialchars($asset['asset_code']); ?>
                                </a>
                            </td>
                            <td style="padding: 12px;">
                                <strong><?php echo htmlspecialchars($asset['name']); ?></strong>
                                <?php if ($asset['serial_no']): ?>
                                <br><small style="color: #6c757d;">S/N: <?php echo htmlspecialchars($asset['serial_no']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <span style="background: #e3f2fd; color: #003581; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($asset['category']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                <?php 
                                $type_model = [];
                                if ($asset['type']) $type_model[] = $asset['type'];
                                if ($asset['make']) $type_model[] = $asset['make'];
                                if ($asset['model']) $type_model[] = $asset['model'];
                                echo htmlspecialchars(implode(' ', $type_model) ?: '-');
                                ?>
                            </td>
                            <td style="padding: 12px;">
                                <span style="<?php 
                                    $condition_styles = [
                                        'New' => 'background: #d4edda; color: #155724;',
                                        'Good' => 'background: #d1ecf1; color: #0c5460;',
                                        'Fair' => 'background: #fff3cd; color: #856404;',
                                        'Poor' => 'background: #f8d7da; color: #721c24;',
                                    ];
                                    echo $condition_styles[$asset['condition']] ?? 'background: #e2e3e5; color: #383d41;';
                                ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                    <?php echo $asset['condition']; ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <span style="<?php 
                                    $status_styles = [
                                        'Available' => 'background: #d4edda; color: #155724;',
                                        'In Use' => 'background: #fff3cd; color: #856404;',
                                        'Under Maintenance' => 'background: #e7d4f5; color: #6f42c1;',
                                        'Broken' => 'background: #f8d7da; color: #721c24;',
                                        'Decommissioned' => 'background: #e2e3e5; color: #383d41;',
                                    ];
                                    echo $status_styles[$asset['status']] ?? 'background: #e2e3e5; color: #383d41;';
                                ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                    <?php echo $asset['status']; ?>
                                </span>
                            </td>
                            <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                <?php echo htmlspecialchars($asset['location'] ?: '-'); ?>
                            </td>
                            <td style="padding: 12px; font-size: 13px;">
                                <?php if ($asset['warranty_expiry']): ?>
                                    <?php 
                                    $days_left = (strtotime($asset['warranty_expiry']) - time()) / 86400;
                                    $color = $days_left < 7 ? '#dc3545' : ($days_left < 30 ? '#fd7e14' : '#28a745');
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                        <?php echo date('M d, Y', strtotime($asset['warranty_expiry'])); ?>
                                    </span>
                                    <?php if ($days_left > 0): ?>
                                    <br><small style="color: #6c757d;"><?php echo ceil($days_left); ?> days</small>
                                    <?php else: ?>
                                    <br><small style="color: #dc3545;">Expired</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="view.php?id=<?php echo $asset['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                        👁️ View
                                    </a>
                                    <a href="edit.php?id=<?php echo $asset['id']; ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                        ✏️ Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 64px; margin-bottom: 20px;">🔍</div>
                <h3 style="color: #003581; margin-bottom: 10px;">No Assets Found</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    <?php if (array_filter($filters)): ?>
                        No assets match your current filters. Try adjusting the filters or clearing them.
                    <?php else: ?>
                        Get started by adding your first asset to the registry.
                    <?php endif; ?>
                </p>
                <?php if (array_filter($filters)): ?>
                <a href="list.php" class="btn btn-accent">Clear Filters</a>
                <?php else: ?>
                <a href="add.php" class="btn">➕ Add First Asset</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>


<style>
.badge-purple {
    background-color: #6f42c1;
    color: white;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
