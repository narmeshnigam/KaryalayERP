<?php
/**
 * Clients Module - Edit Client
 * Update existing client information
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check permission
authz_require_permission($conn, 'clients', 'update');

// Get client ID
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$client_id) {
    header("Location: index.php");
    exit;
}

// Get client details
$client = get_client_by_id($conn, $client_id);
if (!$client) {
    $_SESSION['flash_message'] = "Client not found.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit;
}

$errors = [];

// Get all users for owner dropdown
$users = $conn->query("SELECT id, username FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'legal_name' => trim($_POST['legal_name'] ?? ''),
        'industry' => trim($_POST['industry'] ?? ''),
        'website' => trim($_POST['website'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'gstin' => trim($_POST['gstin'] ?? ''),
        'status' => $_POST['status'] ?? 'Active',
        'owner_id' => (int)($_POST['owner_id'] ?? $client['owner_id']),
        'tags' => trim($_POST['tags'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    // Validate
    $errors = validate_client_data($data);
    
    // Check for duplicates (excluding current client)
    if (empty($errors)) {
        $duplicates = find_duplicate_clients($conn, $data['email'], $data['phone'], $client_id);
        if (!empty($duplicates)) {
            $errors[] = "Email or phone already exists for another client";
        }
    }
    
    // Update if no errors
    if (empty($errors)) {
        if (update_client($conn, $client_id, $data)) {
            $_SESSION['flash_message'] = "Client updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=" . $client_id);
            exit;
        } else {
            $errors[] = "Failed to update client. Please try again.";
        }
    }
    
    // If errors, keep form data
    $client = array_merge($client, $data);
}

$page_title = 'Edit ' . $client['name'] . ' - Clients - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">✏️ Edit Client</h1>
                    <p style="color: #6c757d; margin: 0;">Update client information and details</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="view.php?id=<?= $client_id ?>" class="btn btn-secondary">👁️ View Profile</a>
                    <a href="index.php" class="btn btn-secondary">← Back to Clients</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <?php if (!empty($errors)): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 16px; border-radius: 6px; margin-bottom: 24px;">
                <strong>⚠️ Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 24px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Client Code (Read-only) -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    🔖 Client Code
                </h3>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; font-family: monospace; font-size: 18px; font-weight: bold; color: #1b2a57;">
                    <?= htmlspecialchars($client['code']) ?>
                </div>
                <div style="font-size: 13px; color: #6c757d; margin-top: 8px;">
                    Client code cannot be changed after creation
                </div>
            </div>

            <!-- Basic Information -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    📋 Basic Information
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            Client Name <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($client['name']) ?>">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            Legal Name
                        </label>
                        <input type="text" name="legal_name" class="form-control"
                               value="<?= htmlspecialchars($client['legal_name'] ?? '') ?>">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            🏭 Industry
                        </label>
                        <input type="text" name="industry" class="form-control" list="industry-list"
                               value="<?= htmlspecialchars($client['industry'] ?? '') ?>">
                        <datalist id="industry-list">
                            <option value="IT Services">
                            <option value="Manufacturing">
                            <option value="Retail">
                            <option value="Healthcare">
                            <option value="Finance">
                            <option value="Education">
                            <option value="Real Estate">
                            <option value="Consulting">
                        </datalist>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            🌐 Website
                        </label>
                        <input type="url" name="website" class="form-control" placeholder="https://"
                               value="<?= htmlspecialchars($client['website'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    📞 Contact Information
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            📧 Email
                        </label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            📱 Phone
                        </label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Business Details -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    🏢 Business Details
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            GSTIN
                        </label>
                        <input type="text" name="gstin" class="form-control" placeholder="22AAAAA0000A1Z5"
                               value="<?= htmlspecialchars($client['gstin'] ?? '') ?>">
                        <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">GST Identification Number</div>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            👤 Client Owner <span style="color: #dc3545;">*</span>
                        </label>
                        <select name="owner_id" class="form-control" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" 
                                    <?= $client['owner_id'] == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            📊 Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="Active" <?= $client['status'] === 'Active' ? 'selected' : '' ?>>✅ Active</option>
                            <option value="Inactive" <?= $client['status'] === 'Inactive' ? 'selected' : '' ?>>⏸️ Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                            🏷️ Tags
                        </label>
                        <input type="text" name="tags" class="form-control" placeholder="VIP, Enterprise, Partner"
                               value="<?= htmlspecialchars($client['tags'] ?? '') ?>">
                        <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">Comma-separated tags</div>
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="card" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                    📝 Additional Notes
                </h3>
                
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1b2a57;">
                        Internal Notes
                    </label>
                    <textarea name="notes" class="form-control" rows="5" placeholder="Add internal notes about this client..."><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                    <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">These notes are internal and not visible to the client</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #e9ecef;">
                <a href="view.php?id=<?= $client_id ?>" class="btn btn-secondary">← Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 12px 32px;">💾 Save Changes</button>
            </div>
        </form>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
