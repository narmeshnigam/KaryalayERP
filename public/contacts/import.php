<?php
/**
 * Contacts Module - Import Contacts
 * Bulk import contacts from CSV
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
$success_count = 0;
$parsed_contacts = [];
$import_errors = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please try again.';
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $errors[] = 'File size exceeds 5MB limit.';
    } elseif (!in_array(pathinfo($file['name'], PATHINFO_EXTENSION), ['csv'])) {
        $errors[] = 'Only CSV files are allowed.';
    } else {
        // Parse CSV
        $result = parse_contact_csv($file['tmp_name']);
        $parsed_contacts = $result['contacts'];
        $import_errors = $result['errors'];
        
        if (!empty($parsed_contacts) && empty($import_errors)) {
            // Import contacts
            foreach ($parsed_contacts as $contact_data) {
                if (create_contact($conn, $contact_data, $CURRENT_USER_ID)) {
                    $success_count++;
                } else {
                    $import_errors[] = "Failed to import: " . $contact_data['name'];
                }
            }
            
            if ($success_count > 0) {
                $_SESSION['flash_success'] = "$success_count contact(s) imported successfully!";
            }
            
            if (empty($import_errors)) {
                header('Location: index.php');
                exit;
            }
        }
    }
}

$page_title = 'Import Contacts - Contacts - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
<style>
.contacts-import-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}

@media (max-width:768px){
.contacts-import-header-flex{flex-direction:column;align-items:stretch;}
.contacts-import-header-flex .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.contacts-import-header-flex h1{font-size:1.5rem;}
}
</style>

        <!-- Page Header -->
        <div class="page-header">
            <div class="contacts-import-header-flex">
                <div style="flex: 1;">
                    <h1>📥 Import Contacts</h1>
                    <p>Bulk import contacts from CSV file</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-accent">← Back to Contacts</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Error Display -->
        <?php if (!empty($errors) || !empty($import_errors)): ?>
            <div class="alert alert-error">
                <strong>⚠️ Import Errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach (array_merge($errors, $import_errors) as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_count > 0): ?>
            <div class="alert alert-success">
                <strong>✅ Success!</strong> <?php echo $success_count; ?> contact(s) imported successfully.
                <?php if (!empty($import_errors)): ?>
                    <br>Some contacts failed to import. See errors above.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            
            <!-- Upload Form -->
            <div>
                <div class="card" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 20px 0; color: #003581; border-bottom: 2px solid #003581; padding-bottom: 12px;">
                        📤 Upload CSV File
                    </h3>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div style="margin-bottom: 20px;">
                            <label class="form-label required">Select CSV File</label>
                            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                            <small style="color: #6c757d; font-size: 12px; margin-top: 8px; display: block;">
                                Maximum file size: 5MB | Maximum contacts: 500 per import
                            </small>
                        </div>
                        
                        <button type="submit" class="btn" style="width: 100%;">
                            📥 Import Contacts
                        </button>
                    </form>
                </div>
                
                <!-- CSV Format Instructions -->
                <div class="card">
                    <h3 style="margin: 0 0 20px 0; color: #003581; border-bottom: 2px solid #003581; padding-bottom: 12px;">
                        📋 CSV Format Requirements
                    </h3>
                    
                    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                        <strong>ℹ️ Important:</strong> Your CSV file must follow this exact column order:
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 10px; text-align: left; font-weight: 700;">#</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 700;">Column Name</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 700;">Required</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">1</td>
                                    <td style="padding: 8px;"><code>Name</code></td>
                                    <td style="padding: 8px;">✅ Yes</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">2</td>
                                    <td style="padding: 8px;"><code>Organization</code></td>
                                    <td style="padding: 8px;">No</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">3</td>
                                    <td style="padding: 8px;"><code>Designation</code></td>
                                    <td style="padding: 8px;">No</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">4</td>
                                    <td style="padding: 8px;"><code>Contact Type</code></td>
                                    <td style="padding: 8px;">No (Default: Personal)</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">5</td>
                                    <td style="padding: 8px;"><code>Phone</code></td>
                                    <td style="padding: 8px;">⚠️ At least one required*</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">6</td>
                                    <td style="padding: 8px;"><code>Alt Phone</code></td>
                                    <td style="padding: 8px;">No</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">7</td>
                                    <td style="padding: 8px;"><code>Email</code></td>
                                    <td style="padding: 8px;">⚠️ At least one required*</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">8</td>
                                    <td style="padding: 8px;"><code>WhatsApp</code></td>
                                    <td style="padding: 8px;">⚠️ At least one required*</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">9</td>
                                    <td style="padding: 8px;"><code>LinkedIn</code></td>
                                    <td style="padding: 8px;">No</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">10</td>
                                    <td style="padding: 8px;"><code>Address</code></td>
                                    <td style="padding: 8px;">No</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">11</td>
                                    <td style="padding: 8px;"><code>Tags</code></td>
                                    <td style="padding: 8px;">No</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px;">12</td>
                                    <td style="padding: 8px;"><code>Notes</code></td>
                                    <td style="padding: 8px;">No</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px;">13</td>
                                    <td style="padding: 8px;"><code>Share Scope</code></td>
                                    <td style="padding: 8px;">No (Default: Private)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 16px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-size: 13px;">
                        <strong>* Contact Method Requirement:</strong> At least one of Phone, Email, or WhatsApp must be provided for each contact.
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div>
                <!-- Sample CSV -->
                <div class="card" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 16px 0; color: #003581; font-size: 16px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                        📄 Sample CSV
                    </h3>
                    
                    <p style="font-size: 13px; color: #6c757d; margin-bottom: 16px;">
                        Download a sample CSV file to see the correct format:
                    </p>
                    
                    <a href="sample_contacts.csv" class="btn btn-accent" style="width: 100%; text-align: center;" download>
                        ⬇️ Download Sample CSV
                    </a>
                    
                    <div style="margin-top: 16px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #6c757d;">
                        The sample file includes example contacts with all required columns in the correct order.
                    </div>
                </div>
                
                <!-- Tips -->
                <div class="card">
                    <h3 style="margin: 0 0 16px 0; color: #003581; font-size: 16px; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                        💡 Import Tips
                    </h3>
                    
                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #495057; line-height: 1.8;">
                        <li>Ensure your CSV has proper headers in the first row</li>
                        <li>Use UTF-8 encoding for special characters</li>
                        <li>Maximum 500 contacts per import batch</li>
                        <li>Duplicate email/phone will be rejected</li>
                        <li>Contact Type values: Client, Vendor, Partner, Personal, Other</li>
                        <li>Share Scope values: Private, Team, Organization</li>
                        <li>Tags should be comma-separated (max 10)</li>
                        <li>Empty fields are allowed for optional columns</li>
                    </ul>
                </div>
            </div>
            
        </div>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
