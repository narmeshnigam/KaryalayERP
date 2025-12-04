<?php
/**
 * Asset & Resource Management - Add New Asset
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

// Get context data for allocation dropdown
$employees = getAllEmployees($conn);
$projects = getAllProjects($conn);
$clients = getAllClients($conn);
$leads = getAllLeads($conn);

$page_title = 'Add New Asset - ' . APP_NAME;
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
        'primary_image' => null
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
                    $data['primary_image'] = 'uploads/assets/' . $file_name;
                }
            }
        }
        
        // Create asset
        $asset_id = createAsset($conn, $data, $_SESSION['user_id']);
        
        if ($asset_id) {
            // Check if immediate allocation is requested
            $allocate_now = isset($_POST['allocate_now']) && $_POST['allocate_now'] === '1';
            
            if ($allocate_now) {
                $context_type = $_POST['context_type'] ?? '';
                $context_id = (int)($_POST['context_id'] ?? 0);
                $purpose = trim($_POST['purpose'] ?? '');
                $expected_return = trim($_POST['expected_return'] ?? '') ?: null;
                
                if ($context_type && $context_id) {
                    $allocation_result = assignAsset($conn, $asset_id, $context_type, $context_id, $purpose, $_SESSION['user_id'], $expected_return);
                    
                    if (!$allocation_result['success']) {
                        $error = 'Asset created but allocation failed: ' . $allocation_result['message'];
                    }
                }
            }
            
            if (!$error) {
                $success = 'Asset created successfully!';
                header('Location: view.php?id=' . $asset_id);
                exit;
            }
        } else {
            $error = 'Failed to create asset. Please try again.';
        }
    }
}

closeConnection($conn);
?>

<div class="main-wrapper">
<style>
.assets-add-header-flex{display:flex;justify-content:space-between;align-items:center;}

@media (max-width:768px){
.assets-add-header-flex{flex-direction:column;align-items:stretch;gap:16px;}
.assets-add-header-flex .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.assets-add-header-flex h1{font-size:1.5rem;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="assets-add-header-flex">
                <div>
                    <h1 style="margin: 0;">➕ Add New Asset</h1>
                    <p style="margin: 5px 0 0 0; color: #666;">Register a new asset to the system</p>
                </div>
                <a href="list.php" class="btn btn-accent">← Back to List</a>
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
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                               placeholder="e.g., Dell Latitude 5420">
                    </div>

                    <div class="form-group">
                        <label>Category <span style="color: #dc3545;">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="IT" <?php echo ($_POST['category'] ?? '') === 'IT' ? 'selected' : ''; ?>>IT</option>
                            <option value="Vehicle" <?php echo ($_POST['category'] ?? '') === 'Vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                            <option value="Tool" <?php echo ($_POST['category'] ?? '') === 'Tool' ? 'selected' : ''; ?>>Tool</option>
                            <option value="Machine" <?php echo ($_POST['category'] ?? '') === 'Machine' ? 'selected' : ''; ?>>Machine</option>
                            <option value="Furniture" <?php echo ($_POST['category'] ?? '') === 'Furniture' ? 'selected' : ''; ?>>Furniture</option>
                            <option value="Space" <?php echo ($_POST['category'] ?? '') === 'Space' ? 'selected' : ''; ?>>Space</option>
                            <option value="Other" <?php echo ($_POST['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Type/Model Name</label>
                        <input type="text" name="type" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['type'] ?? ''); ?>"
                               placeholder="e.g., Laptop, SUV, Drill">
                    </div>

                    <div class="form-group">
                        <label>Make/Brand</label>
                        <input type="text" name="make" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['make'] ?? ''); ?>"
                               placeholder="e.g., Dell, Toyota, Bosch">
                    </div>

                    <div class="form-group">
                        <label>Model Number</label>
                        <input type="text" name="model" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>"
                               placeholder="e.g., Latitude 5420">
                    </div>

                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_no" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['serial_no'] ?? ''); ?>"
                               placeholder="S/N, VIN, IMEI">
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; color: #003581; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">Location & Status</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>"
                               placeholder="e.g., IT, HR, Operations">
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                               placeholder="e.g., Head Office, Floor 3, Desk 15">
                    </div>

                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition" class="form-control">
                            <option value="New" <?php echo ($_POST['condition'] ?? 'Good') === 'New' ? 'selected' : ''; ?>>New</option>
                            <option value="Good" <?php echo ($_POST['condition'] ?? 'Good') === 'Good' ? 'selected' : ''; ?>>Good</option>
                            <option value="Fair" <?php echo ($_POST['condition'] ?? '') === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                            <option value="Poor" <?php echo ($_POST['condition'] ?? '') === 'Poor' ? 'selected' : ''; ?>>Poor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Available" <?php echo ($_POST['status'] ?? 'Available') === 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="In Use" <?php echo ($_POST['status'] ?? '') === 'In Use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="Under Maintenance" <?php echo ($_POST['status'] ?? '') === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                            <option value="Broken" <?php echo ($_POST['status'] ?? '') === 'Broken' ? 'selected' : ''; ?>>Broken</option>
                            <option value="Decommissioned" <?php echo ($_POST['status'] ?? '') === 'Decommissioned' ? 'selected' : ''; ?>>Decommissioned</option>
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
                               value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Purchase Cost</label>
                        <input type="number" step="0.01" name="purchase_cost" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['purchase_cost'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Vendor/Supplier</label>
                        <input type="text" name="vendor" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['vendor'] ?? ''); ?>"
                               placeholder="Vendor name">
                    </div>

                    <div class="form-group">
                        <label>Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['warranty_expiry'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; color: #003581; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">Additional Details</h3>
                
                <div class="form-group">
                    <label>Primary Image</label>
                    <input type="file" name="primary_image" class="form-control" accept="image/*">
                    <small style="color: #666;">Supported formats: JPG, PNG, GIF (Max 5MB)</small>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="4" 
                              placeholder="Any additional information about this asset..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Allocation Section (Optional) -->
            <div class="card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">
                    <input type="checkbox" id="allocate_now" name="allocate_now" value="1" 
                           style="width: 20px; height: 20px; cursor: pointer;"
                           onchange="toggleAllocationFields()"
                           <?php echo isset($_POST['allocate_now']) && $_POST['allocate_now'] === '1' ? 'checked' : ''; ?>>
                    <label for="allocate_now" style="margin: 0; font-size: 16px; font-weight: 700; color: #003581; cursor: pointer;">
                        🔖 Assign this asset immediately after creation
                    </label>
                </div>

                <div id="allocation-fields" style="display: <?php echo isset($_POST['allocate_now']) && $_POST['allocate_now'] === '1' ? 'block' : 'none'; ?>;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <div class="form-group">
                            <label>Assign To <span style="color: #dc3545;">*</span></label>
                            <select name="context_type" id="context_type" class="form-control" onchange="updateContextOptions()">
                                <option value="">Select Context</option>
                                <option value="Employee" <?php echo ($_POST['context_type'] ?? '') === 'Employee' ? 'selected' : ''; ?>>Employee</option>
                                <option value="Project" <?php echo ($_POST['context_type'] ?? '') === 'Project' ? 'selected' : ''; ?>>Project</option>
                                <option value="Client" <?php echo ($_POST['context_type'] ?? '') === 'Client' ? 'selected' : ''; ?>>Client</option>
                                <option value="Lead" <?php echo ($_POST['context_type'] ?? '') === 'Lead' ? 'selected' : ''; ?>>Lead</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label id="context-label">Select <span style="color: #dc3545;">*</span></label>
                            <select name="context_id" id="context_id" class="form-control">
                                <option value="">Select context first</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Purpose</label>
                            <input type="text" name="purpose" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?>"
                                   placeholder="Purpose of allocation">
                        </div>

                        <div class="form-group">
                            <label>Expected Return Date</label>
                            <input type="date" name="expected_return" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['expected_return'] ?? ''); ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div style="background: #fff3cd; border-left: 4px solid #faa718; padding: 12px; border-radius: 4px; margin-top: 15px;">
                        <strong>ℹ️ Note:</strong> The asset status will automatically be changed to "In Use" once assigned.
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <a href="list.php" class="btn btn-accent">Cancel</a>
                <button type="submit" class="btn">💾 Save Asset</button>
            </div>
        </form>

    </div>
</div>

<script>
// Context data from PHP
const contextData = {
    Employee: <?php echo json_encode($employees); ?>,
    Project: <?php echo json_encode($projects); ?>,
    Client: <?php echo json_encode($clients); ?>,
    Lead: <?php echo json_encode($leads); ?>
};

function toggleAllocationFields() {
    const checkbox = document.getElementById('allocate_now');
    const fields = document.getElementById('allocation-fields');
    fields.style.display = checkbox.checked ? 'block' : 'none';
}

function updateContextOptions() {
    const contextType = document.getElementById('context_type').value;
    const contextId = document.getElementById('context_id');
    const label = document.getElementById('context-label');
    
    // Clear existing options
    contextId.innerHTML = '<option value="">Select...</option>';
    
    if (contextType && contextData[contextType]) {
        // Update label
        label.textContent = contextType + ' *';
        
        // Add options
        contextData[contextType].forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            contextId.appendChild(option);
        });
    } else {
        label.textContent = 'Select *';
        contextId.innerHTML = '<option value="">Select context first</option>';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const contextType = document.getElementById('context_type').value;
    if (contextType) {
        updateContextOptions();
        // Restore selected value if form was submitted
        const selectedId = '<?php echo $_POST['context_id'] ?? ''; ?>';
        if (selectedId) {
            document.getElementById('context_id').value = selectedId;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
