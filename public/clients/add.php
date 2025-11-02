<?php
session_start();
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

// Check permission
authz_require_permission($conn, 'clients', 'create');

// Check if tables exist
if (!clients_tables_exist($conn)) {
    die("Clients tables not set up. Please run setup script first.");
}

$errors = [];
$success = false;
$duplicate_warning = null;

// Get all users for owner dropdown
$users = $conn->query("SELECT id, username FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Check if converting from lead
$from_lead_id = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : null;
$lead_data = null;

if ($from_lead_id) {
    $stmt = $conn->prepare("SELECT * FROM crm_leads WHERE id = ?");
    $stmt->bind_param("i", $from_lead_id);
    $stmt->execute();
    $lead_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

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
        'owner_id' => (int)($_POST['owner_id'] ?? $_SESSION['user_id']),
        'lead_id' => $from_lead_id,
        'tags' => trim($_POST['tags'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    // Validate
    $errors = validate_client_data($data);
    
    // Check for duplicates
    if (empty($errors)) {
        $duplicates = find_duplicate_clients($conn, $data['email'], $data['phone']);
        if (!empty($duplicates) && !isset($_POST['ignore_duplicates'])) {
            $duplicate_warning = $duplicates;
        }
    }
    
    // If no errors and no duplicate warning, create client
    if (empty($errors) && empty($duplicate_warning)) {
        $client_id = create_client($conn, $data, $_SESSION['user_id']);
        
        if ($client_id) {
            // If converting from lead, update lead status
            if ($from_lead_id) {
                $stmt = $conn->prepare("UPDATE crm_leads SET status = 'Converted' WHERE id = ?");
                $stmt->bind_param("i", $from_lead_id);
                $stmt->execute();
                $stmt->close();
            }
            
            $_SESSION['flash_message'] = "Client created successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=" . $client_id);
            exit;
        } else {
            $errors[] = "Failed to create client. Please try again.";
        }
    }
}

$page_title = "Add New Client - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>➕ Add New Client</h1>
                    <p>Enter client information and business details</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-accent">
                        ← Back to Client List
                    </a>
                </div>
            </div>
        </div>

        <?php if ($from_lead_id && $lead_data): ?>
            <div class="alert alert-info" style="margin-bottom: 20px;">
                <strong>🎯 Converting from Lead:</strong> <?= htmlspecialchars($lead_data['name'] ?? $lead_data['company']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>❌ Error:</strong><br>
                <?php foreach ($errors as $error): ?>
                    • <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($duplicate_warning)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Potential Duplicate Clients Found:</strong>
                <div style="margin-top: 10px;">
                    <?php foreach ($duplicate_warning as $dup): ?>
                        <div style="background: white; padding: 12px; margin-bottom: 8px; border-radius: 4px; border: 1px solid #e0e0e0;">
                            <strong><?= htmlspecialchars($dup['name']) ?></strong> (<?= htmlspecialchars($dup['code']) ?>)<br>
                            <small style="color: #6c757d;">
                                <?php if ($dup['email']): ?>Email: <?= htmlspecialchars($dup['email']) ?> &nbsp;&nbsp;<?php endif; ?>
                                <?php if ($dup['phone']): ?>Phone: <?= htmlspecialchars($dup['phone']) ?><?php endif; ?>
                            </small>
                            <br>
                            <a href="view.php?id=<?= $dup['id'] ?>" target="_blank" style="color: #0066cc;">View Client →</a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" form="addClientForm" name="ignore_duplicates" value="1" class="btn" style="margin-top: 12px; background: #ffc107; color: #000;">
                    Proceed Anyway (Create Duplicate)
                </button>
            </div>
        <?php endif; ?>

        <form method="POST" id="addClientForm">
            <!-- Basic Information -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📋 Basic Information
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label>Client Name <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['name'] ?? $lead_data['name'] ?? $lead_data['company'] ?? '') ?>">
                        <small style="color: #6c757d; font-size: 12px;">This will be the primary display name</small>
                    </div>
                    <div class="form-group">
                        <label>Legal Name</label>
                        <input type="text" name="legal_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['legal_name'] ?? $lead_data['company'] ?? '') ?>">
                        <small style="color: #6c757d; font-size: 12px;">Full legal entity name</small>
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <input type="text" name="industry" class="form-control" list="industry-list"
                               value="<?= htmlspecialchars($_POST['industry'] ?? $lead_data['industry'] ?? '') ?>">
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
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" class="form-control" placeholder="https://"
                               value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Client Owner <span style="color: #dc3545;">*</span></label>
                        <select name="owner_id" class="form-control" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" 
                                    <?= ($_POST['owner_id'] ?? $lead_data['assigned_to'] ?? $_SESSION['user_id']) == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6c757d; font-size: 12px;">Primary account manager</small>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?= ($_POST['status'] ?? 'Active') == 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($_POST['status'] ?? '') == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📞 Contact Information
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($_POST['email'] ?? $lead_data['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= htmlspecialchars($_POST['phone'] ?? $lead_data['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>GSTIN</label>
                        <input type="text" name="gstin" class="form-control" placeholder="22AAAAA0000A1Z5"
                               value="<?= htmlspecialchars($_POST['gstin'] ?? '') ?>">
                        <small style="color: #6c757d; font-size: 12px;">GST Identification Number</small>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                    📝 Additional Information
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="VIP, Enterprise, Partner"
                               value="<?= htmlspecialchars($_POST['tags'] ?? $lead_data['interests'] ?? '') ?>">
                        <small style="color: #6c757d; font-size: 12px;">Comma-separated tags for categorization</small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Internal Notes</label>
                        <textarea name="notes" class="form-control" rows="4"
                                  placeholder="Any additional information about this client..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="display: flex; justify-content: space-between; padding-top: 20px;">
                <a href="index.php" class="btn btn-accent" style="padding: 12px 30px; text-decoration: none;">Cancel</a>
                <button type="submit" class="btn" style="padding: 12px 30px;">
                    ✓ Create Client
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
