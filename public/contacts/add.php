<?php
/**
 * Contacts Module - Add Contact
 * Create a new contact entry
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'contacts', 'create');

// Check if tables exist
if (!contacts_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=contacts');
    exit;
}

$errors = [];
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'organization' => !empty($_POST['organization']) ? trim($_POST['organization']) : null,
        'designation' => !empty($_POST['designation']) ? trim($_POST['designation']) : null,
        'contact_type' => $_POST['contact_type'] ?? 'Personal',
        'phone' => !empty($_POST['phone']) ? trim($_POST['phone']) : null,
        'alt_phone' => !empty($_POST['alt_phone']) ? trim($_POST['alt_phone']) : null,
        'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
        'whatsapp' => !empty($_POST['whatsapp']) ? trim($_POST['whatsapp']) : null,
        'linkedin' => !empty($_POST['linkedin']) ? trim($_POST['linkedin']) : null,
        'address' => !empty($_POST['address']) ? trim($_POST['address']) : null,
        'tags' => !empty($_POST['tags']) ? trim($_POST['tags']) : null,
        'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
        'linked_entity_id' => !empty($_POST['linked_entity_id']) ? (int)$_POST['linked_entity_id'] : null,
        'linked_entity_type' => !empty($_POST['linked_entity_type']) ? $_POST['linked_entity_type'] : null,
        'share_scope' => $_POST['share_scope'] ?? 'Private'
    ];
    
    // Validate
    $errors = validate_contact_data($form_data);
    
    // Check for duplicates
    if (empty($errors)) {
        $duplicates = find_duplicate_contacts($conn, $form_data['email'], $form_data['phone']);
        if (!empty($duplicates)) {
            $errors[] = "A contact with this email or phone already exists. Please check the existing contact.";
        }
    }
    
    // Create contact if no errors
    if (empty($errors)) {
        $contact_id = create_contact($conn, $form_data, $CURRENT_USER_ID);
        
        if ($contact_id) {
            $_SESSION['flash_success'] = 'Contact created successfully!';
            header('Location: view.php?id=' . $contact_id);
            exit;
        } else {
            $errors[] = 'Failed to create contact. Please try again.';
        }
    }
}

$page_title = 'Add Contact - Contacts - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1>➕ Add New Contact</h1>
                    <p>Create a new contact entry in your address book</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-accent">← Back to Contacts</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Error Display -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>⚠️ Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Add Contact Form -->
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                
                <!-- Main Form -->
                <div>
                    <!-- Basic Information -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 style="margin: 0 0 20px 0; color: #003581; border-bottom: 2px solid #003581; padding-bottom: 12px;">
                            👤 Basic Information
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div style="grid-column: 1 / -1;">
                                <label class="form-label required">Full Name</label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                       placeholder="Enter full name">
                            </div>
                            
                            <div>
                                <label class="form-label">Organization</label>
                                <input type="text" name="organization" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['organization'] ?? ''); ?>"
                                       placeholder="Company name">
                            </div>
                            
                            <div>
                                <label class="form-label">Designation</label>
                                <input type="text" name="designation" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['designation'] ?? ''); ?>"
                                       placeholder="Job title">
                            </div>
                            
                            <div style="grid-column: 1 / -1;">
                                <label class="form-label required">Contact Type</label>
                                <select name="contact_type" class="form-control" required>
                                    <option value="Personal" <?php echo ($form_data['contact_type'] ?? 'Personal') === 'Personal' ? 'selected' : ''; ?>>📱 Personal</option>
                                    <option value="Client" <?php echo ($form_data['contact_type'] ?? '') === 'Client' ? 'selected' : ''; ?>>👤 Client</option>
                                    <option value="Vendor" <?php echo ($form_data['contact_type'] ?? '') === 'Vendor' ? 'selected' : ''; ?>>🏢 Vendor</option>
                                    <option value="Partner" <?php echo ($form_data['contact_type'] ?? '') === 'Partner' ? 'selected' : ''; ?>>🤝 Partner</option>
                                    <option value="Other" <?php echo ($form_data['contact_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>📇 Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 style="margin: 0 0 20px 0; color: #003581; border-bottom: 2px solid #003581; padding-bottom: 12px;">
                            📞 Contact Methods
                        </h3>
                        
                        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                            ℹ️ <strong>Note:</strong> At least one contact method (phone, email, or WhatsApp) is required.
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label class="form-label">Primary Phone</label>
                                <input type="tel" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                                       placeholder="+91 98765 43210">
                            </div>
                            
                            <div>
                                <label class="form-label">Alternate Phone</label>
                                <input type="tel" name="alt_phone" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['alt_phone'] ?? ''); ?>"
                                       placeholder="Secondary number">
                            </div>
                            
                            <div style="grid-column: 1 / -1;">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                       placeholder="email@example.com">
                            </div>
                            
                            <div>
                                <label class="form-label">WhatsApp Number</label>
                                <input type="tel" name="whatsapp" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['whatsapp'] ?? ''); ?>"
                                       placeholder="WhatsApp number">
                            </div>
                            
                            <div>
                                <label class="form-label">LinkedIn Profile</label>
                                <input type="url" name="linkedin" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['linkedin'] ?? ''); ?>"
                                       placeholder="https://linkedin.com/in/...">
                            </div>
                            
                            <div style="grid-column: 1 / -1;">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2"
                                          placeholder="Physical address or location"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 style="margin: 0 0 20px 0; color: #003581; border-bottom: 2px solid #003581; padding-bottom: 12px;">
                            📝 Additional Information
                        </h3>
                        
                        <div style="display: grid; gap: 16px;">
                            <div>
                                <label class="form-label">Tags</label>
                                <input type="text" name="tags" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['tags'] ?? ''); ?>"
                                       placeholder="Comma-separated tags (max 10)">
                                <small style="color: #6c757d; font-size: 12px; margin-top: 4px; display: block;">
                                    Example: important, marketing, investor
                                </small>
                            </div>
                            
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="4"
                                          placeholder="Additional notes or remarks about this contact"><?php echo htmlspecialchars($form_data['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar Settings -->
                <div>
                    <!-- Share Settings -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 style="margin: 0 0 16px 0; color: #003581; font-size: 16px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                            👁️ Visibility
                        </h3>
                        
                        <div>
                            <label class="form-label required">Share Scope</label>
                            <select name="share_scope" class="form-control" required>
                                <option value="Private" <?php echo ($form_data['share_scope'] ?? 'Private') === 'Private' ? 'selected' : ''; ?>>
                                    🔒 Private (Only Me)
                                </option>
                                <option value="Team" <?php echo ($form_data['share_scope'] ?? '') === 'Team' ? 'selected' : ''; ?>>
                                    👥 Team (Same Role)
                                </option>
                                <option value="Organization" <?php echo ($form_data['share_scope'] ?? '') === 'Organization' ? 'selected' : ''; ?>>
                                    🌐 Organization (Everyone)
                                </option>
                            </select>
                            <small style="color: #6c757d; font-size: 11px; margin-top: 6px; display: block;">
                                Controls who can view this contact
                            </small>
                        </div>
                    </div>
                    
                    <!-- Entity Linking -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 style="margin: 0 0 16px 0; color: #003581; font-size: 16px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                            🔗 Entity Linking
                        </h3>
                        
                        <div style="display: grid; gap: 12px;">
                            <div>
                                <label class="form-label">Link To</label>
                                <select name="linked_entity_type" class="form-control">
                                    <option value="">None</option>
                                    <option value="Client" <?php echo ($form_data['linked_entity_type'] ?? '') === 'Client' ? 'selected' : ''; ?>>Client</option>
                                    <option value="Project" <?php echo ($form_data['linked_entity_type'] ?? '') === 'Project' ? 'selected' : ''; ?>>Project</option>
                                    <option value="Lead" <?php echo ($form_data['linked_entity_type'] ?? '') === 'Lead' ? 'selected' : ''; ?>>Lead</option>
                                    <option value="Other" <?php echo ($form_data['linked_entity_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label">Entity ID</label>
                                <input type="number" name="linked_entity_id" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['linked_entity_id'] ?? ''); ?>"
                                       placeholder="Record ID">
                                <small style="color: #6c757d; font-size: 11px; margin-top: 4px; display: block;">
                                    Optional: Link this contact to a specific record
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card">
                        <div style="display: grid; gap: 10px;">
                            <button type="submit" class="btn" style="width: 100%;">
                                ✅ Create Contact
                            </button>
                            <a href="index.php" class="btn btn-accent" style="width: 100%; text-align: center;">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
                
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
