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

$page_title = "Add New Client";
include __DIR__ . '/../../includes/header_sidebar.php';
?>

<style>
.form-container {
    max-width: 900px;
    margin: 2rem auto;
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.form-section {
    margin-bottom: 2rem;
}
.form-section-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e0e0e0;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-group-full {
    grid-column: 1 / -1;
}
.form-label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.25rem;
    color: #333;
}
.required::after {
    content: " *";
    color: #dc3545;
}
.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}
.alert-danger {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
}
.duplicate-list {
    margin-top: 0.5rem;
}
.duplicate-item {
    background: white;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
}
.help-text {
    font-size: 0.875rem;
    color: #666;
    margin-top: 0.25rem;
}
.lead-badge {
    background: #007bff;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
    display: inline-block;
    margin-bottom: 1rem;
}
</style>

<div class="container mt-4">
    <div class="form-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Add New Client</h2>
            <a href="index.php" class="btn btn-secondary">← Back to List</a>
        </div>

        <?php if ($from_lead_id && $lead_data): ?>
            <div class="lead-badge">
                🎯 Converting from Lead: <?= htmlspecialchars($lead_data['name'] ?? $lead_data['company']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($duplicate_warning)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Potential Duplicate Clients Found:</strong>
                <div class="duplicate-list">
                    <?php foreach ($duplicate_warning as $dup): ?>
                        <div class="duplicate-item">
                            <strong><?= htmlspecialchars($dup['name']) ?></strong> (<?= htmlspecialchars($dup['code']) ?>)<br>
                            <small>
                                <?php if ($dup['email']): ?>Email: <?= htmlspecialchars($dup['email']) ?> &nbsp;&nbsp;<?php endif; ?>
                                <?php if ($dup['phone']): ?>Phone: <?= htmlspecialchars($dup['phone']) ?><?php endif; ?>
                            </small>
                            <br>
                            <a href="view.php?id=<?= $dup['id'] ?>" target="_blank">View Client →</a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" form="addClientForm" name="ignore_duplicates" value="1" class="btn btn-warning mt-2">
                    Proceed Anyway (Create Duplicate)
                </button>
            </div>
        <?php endif; ?>

        <form method="POST" id="addClientForm">
            <!-- Basic Information -->
            <div class="form-section">
                <div class="form-section-title">Basic Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Client Name</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['name'] ?? $lead_data['name'] ?? $lead_data['company'] ?? '') ?>">
                        <div class="help-text">This will be the primary display name</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Legal Name</label>
                        <input type="text" name="legal_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['legal_name'] ?? $lead_data['company'] ?? '') ?>">
                        <div class="help-text">Full legal entity name</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Industry</label>
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
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" placeholder="https://"
                               value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <div class="form-section-title">Contact Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($_POST['email'] ?? $lead_data['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= htmlspecialchars($_POST['phone'] ?? $lead_data['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Business Details -->
            <div class="form-section">
                <div class="form-section-title">Business Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">GSTIN</label>
                        <input type="text" name="gstin" class="form-control" placeholder="22AAAAA0000A1Z5"
                               value="<?= htmlspecialchars($_POST['gstin'] ?? '') ?>">
                        <div class="help-text">GST Identification Number (if applicable)</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Client Owner</label>
                        <select name="owner_id" class="form-control" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" 
                                    <?= ($_POST['owner_id'] ?? $lead_data['assigned_to'] ?? $_SESSION['user_id']) == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Primary account manager</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?= ($_POST['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($_POST['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="VIP, Enterprise, Partner"
                               value="<?= htmlspecialchars($_POST['tags'] ?? $lead_data['interests'] ?? '') ?>">
                        <div class="help-text">Comma-separated tags</div>
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="form-section">
                <div class="form-section-title">Additional Notes</div>
                
                <div class="form-group form-group-full">
                    <label class="form-label">Internal Notes</label>
                    <textarea name="notes" class="form-control" rows="4"
                              placeholder="Any additional information about this client..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Client</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer_sidebar.php'; ?>
