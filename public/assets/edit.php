<?php
/**
 * Asset & Resource Management - Edit Asset
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

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

// Get asset ID
$asset_id = (int)($_GET['id'] ?? 0);
if (!$asset_id) {
    header('Location: list.php');
    exit;
}

// Get asset details
$asset = getAssetById($conn, $asset_id);
if (!$asset) {
    header('Location: list.php');
    exit;
}

$page_title = 'Edit Asset - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'category' => $_POST['category'] ?? '',
        'type' => trim($_POST['type'] ?? '') ?: null,
        'make' => trim($_POST['make'] ?? '') ?: null,
        'model' => trim($_POST['model'] ?? '') ?: null,
        'serial_no' => trim($_POST['serial_no'] ?? '') ?: null,
        'department' => trim($_POST['department'] ?? '') ?: null,
        'location' => trim($_POST['location'] ?? '') ?: null,
        'condition' => $_POST['condition'] ?? 'Good',
        'status' => $_POST['status'] ?? 'Available',
        'purchase_date' => trim($_POST['purchase_date'] ?? '') ?: null,
        'purchase_cost' => trim($_POST['purchase_cost'] ?? '') ?: null,
        'vendor' => trim($_POST['vendor'] ?? '') ?: null,
        'warranty_expiry' => trim($_POST['warranty_expiry'] ?? '') ?: null,
        'notes' => trim($_POST['notes'] ?? '') ?: null,
        'primary_image' => $asset['primary_image']
    ];
    
    // Validation
    if (empty($data['name'])) {
        $error = 'Asset name is required';
    } elseif (empty($data['category'])) {
        $error = 'Category is required';
    } else {
        // Handle image upload
        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/assets/';
            $file_ext = strtolower(pathinfo($_FILES['primary_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $file_name = 'asset_' . time() . '_' . uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['primary_image']['tmp_name'], $file_path)) {
                    // Delete old image if exists
                    if ($asset['primary_image'] && file_exists(__DIR__ . '/../../' . $asset['primary_image'])) {
                        unlink(__DIR__ . '/../../' . $asset['primary_image']);
                    }
                    $data['primary_image'] = 'uploads/assets/' . $file_name;
                }
            }
        }
        
        // Update asset
        $result = updateAsset($conn, $asset_id, $data, $_SESSION['user_id']);
        
        if ($result) {
            $success = 'Asset updated successfully!';
            $asset = getAssetById($conn, $asset_id); // Refresh data
        } else {
            $error = 'Failed to update asset. Please try again.';
        }
    }
}

closeConnection($conn);
?>

<div class="main-wrapper">
<style>
.assets-edit-header-flex{display:flex;justify-content:space-between;align-items:center;}
.assets-edit-header-buttons{display:flex;gap:10px;}

@media (max-width:768px){
.assets-edit-header-flex{flex-direction:column;align-items:stretch;gap:16px;}
.assets-edit-header-buttons{width:100%;flex-direction:column;gap:10px;}
.assets-edit-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.assets-edit-header-flex h1{font-size:1.5rem;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="assets-edit-header-flex">
                <div>
                    <h1 style="margin: 0;">✏️ Edit Asset</h1>
                    <p style="margin: 5px 0 0 0; color: #666;"><?php echo htmlspecialchars($asset['asset_code']); ?></p>
                </div>
                <div class="assets-edit-header-buttons">
                    <a href="view.php?id=<?php echo $asset_id; ?>" class="btn btn-accent">👁️ View Details</a>
                    <a href="list.php" class="btn btn-accent">← Back to List</a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="card">
                <h3 style="margin-top: 0; color: #003581; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">Basic Information</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label>Asset Name <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="name" class="form-control" required 
                               value="<?php echo htmlspecialchars($asset['name']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Category <span style="color: #dc3545;">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="IT" <?php echo $asset['category'] === 'IT' ? 'selected' : ''; ?>>IT</option>
                            <option value="Vehicle" <?php echo $asset['category'] === 'Vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                            <option value="Tool" <?php echo $asset['category'] === 'Tool' ? 'selected' : ''; ?>>Tool</option>
                            <option value="Machine" <?php echo $asset['category'] === 'Machine' ? 'selected' : ''; ?>>Machine</option>
                            <option value="Furniture" <?php echo $asset['category'] === 'Furniture' ? 'selected' : ''; ?>>Furniture</option>
                            <option value="Space" <?php echo $asset['category'] === 'Space' ? 'selected' : ''; ?>>Space</option>
                            <option value="Other" <?php echo $asset['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Type/Model Name</label>
                        <input type="text" name="type" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['type'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Make/Brand</label>
                        <input type="text" name="make" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['make'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Model Number</label>
                        <input type="text" name="model" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['model'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_no" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['serial_no'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; color: #003581; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">Location & Status</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['department'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['location'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition" class="form-control">
                            <option value="New" <?php echo $asset['condition'] === 'New' ? 'selected' : ''; ?>>New</option>
                            <option value="Good" <?php echo $asset['condition'] === 'Good' ? 'selected' : ''; ?>>Good</option>
                            <option value="Fair" <?php echo $asset['condition'] === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                            <option value="Poor" <?php echo $asset['condition'] === 'Poor' ? 'selected' : ''; ?>>Poor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Available" <?php echo $asset['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="In Use" <?php echo $asset['status'] === 'In Use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="Under Maintenance" <?php echo $asset['status'] === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                            <option value="Broken" <?php echo $asset['status'] === 'Broken' ? 'selected' : ''; ?>>Broken</option>
                            <option value="Decommissioned" <?php echo $asset['status'] === 'Decommissioned' ? 'selected' : ''; ?>>Decommissioned</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; color: #003581; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">Purchase & Warranty</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label>Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['purchase_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Purchase Cost</label>
                        <input type="number" step="0.01" name="purchase_cost" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['purchase_cost'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Vendor/Supplier</label>
                        <input type="text" name="vendor" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['vendor'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" class="form-control" 
                               value="<?php echo htmlspecialchars($asset['warranty_expiry'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; color: #003581; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">Additional Details</h3>
                
                <div class="form-group">
                    <label>Primary Image</label>
                    <?php if ($asset['primary_image']): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="../../<?php echo htmlspecialchars($asset['primary_image']); ?>" 
                             alt="Current Image" 
                             style="max-width: 200px; border-radius: 8px; border: 2px solid #e9ecef;">
                        <p style="margin: 5px 0; color: #666; font-size: 13px;">Current image (upload new to replace)</p>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="primary_image" class="form-control" accept="image/*">
                    <small style="color: #666;">Supported formats: JPG, PNG, GIF (Max 5MB)</small>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($asset['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <a href="view.php?id=<?php echo $asset_id; ?>" class="btn btn-accent">Cancel</a>
                <button type="submit" class="btn">💾 Update Asset</button>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
