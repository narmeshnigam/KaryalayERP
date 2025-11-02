<?php
session_start();
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

// Check permission
authz_require_permission($conn, 'clients', 'create');

// Get current filters for export
$filters = [];
foreach (['search', 'status', 'owner_id', 'industry'] as $key) {
    if (!empty($_GET[$key])) {
        $filters[$key] = $_GET[$key];
    }
}

// Handle export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $clients = get_all_clients($conn, $_SESSION['user_id'], $filters);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="clients_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, ['Name', 'Legal Name', 'Industry', 'Email', 'Phone', 'Website', 'GSTIN', 'Status', 'Owner', 'Tags', 'Notes']);
    
    // Data
    foreach ($clients as $client) {
        fputcsv($output, [
            $client['name'],
            $client['legal_name'] ?? '',
            $client['industry'] ?? '',
            $client['email'] ?? '',
            $client['phone'] ?? '',
            $client['website'] ?? '',
            $client['gstin'] ?? '',
            $client['status'],
            $client['owner_username'],
            $client['tags'] ?? '',
            $client['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

$page_title = "Import/Export Clients - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📤 Import/Export Clients</h1>
                    <p>Manage client data in bulk</p>
                </div>
                <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Back to List</a>
            </div>
        </div>

        <!-- Export Section -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; font-size: 18px; font-weight: 700; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                📤 Export Clients
            </h3>
            <p style="color: #6c757d; margin-bottom: 16px;">Download your client data as CSV file</p>
            
            <ul style="list-style: none; padding: 0; margin: 16px 0;">
                <li style="padding: 8px 0; color: #495057;">✓ Export all clients or filtered subset</li>
                <li style="padding: 8px 0; color: #495057;">✓ CSV format compatible with Excel and Google Sheets</li>
                <li style="padding: 8px 0; color: #495057;">✓ Includes all client details</li>
                <li style="padding: 8px 0; color: #495057;">✓ Can be re-imported after editing</li>
            </ul>
            
            <div style="margin-top: 24px;">
                <a href="?action=export" class="btn" style="padding: 12px 32px; font-size: 16px; text-decoration: none; display: inline-block;">
                    📥 Export All Clients to CSV
                </a>
            </div>
            
            <div class="alert alert-info" style="margin-top: 16px; padding: 16px; border-radius: 6px; background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;">
                <strong>💡 Tip:</strong> Use filters on the main clients page, then click export to download only filtered results.
            </div>
        </div>

        <!-- Import Section -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; font-size: 18px; font-weight: 700; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                📥 Import Clients
            </h3>
            <p style="color: #6c757d; margin-bottom: 16px;">Bulk upload clients from CSV file</p>
            
            <ul style="list-style: none; padding: 0; margin: 16px 0;">
                <li style="padding: 8px 0; color: #495057;">✓ Bulk import multiple clients at once</li>
                <li style="padding: 8px 0; color: #495057;">✓ Automatic duplicate detection by email/phone</li>
                <li style="padding: 8px 0; color: #495057;">✓ Preview before importing</li>
                <li style="padding: 8px 0; color: #495057;">✓ Skip or update duplicates</li>
            </ul>
            
            <div class="alert alert-warning" style="margin-top: 24px; padding: 16px; border-radius: 6px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
                <strong>⚠️ Import Requirements:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <li>CSV file must have headers in first row</li>
                    <li>At minimum, "Name" column is required</li>
                    <li>Owner must exist (use username)</li>
                    <li>Status must be "Active" or "Inactive"</li>
                </ul>
            </div>
            
            <h4 style="margin-top: 24px; color: #003581; font-weight: 600;">CSV Format</h4>
            <p style="color: #6c757d; margin-bottom: 12px;">Your CSV file should have the following columns:</p>
            <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; font-family: monospace; font-size: 13px; overflow-x: auto; border: 1px solid #dee2e6;">
Name,Legal Name,Industry,Email,Phone,Website,GSTIN,Status,Owner,Tags,Notes<br>
"Acme Corporation","Acme Corp Ltd","IT Services","info@acme.com","9876543210","https://acme.com","22AAAAA0000A1Z5","Active","admin","VIP,Enterprise","Important client"<br>
"TechStart Inc","TechStart Private Limited","Software","hello@techstart.com","9876543211","","","Active","admin","Startup",""
            </div>
            
            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <a href="import_wizard.php" class="btn" style="padding: 12px 32px; font-size: 16px; text-decoration: none; display: inline-block;">
                    📤 Start Import Wizard
                </a>
                <a href="sample_clients.csv" class="btn btn-accent" style="padding: 12px 32px; font-size: 16px; text-decoration: none; display: inline-block;" download>
                    📄 Download Sample CSV
                </a>
            </div>
        </div>

        <!-- Quick Stats -->
        <?php
        $stats = get_clients_statistics($conn, $_SESSION['user_id']);
        ?>
        <div class="card">
            <h3 style="color: #003581; font-size: 18px; font-weight: 700; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                📊 Current Database
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white; border-radius: 8px;">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;">
                        <?= $stats['total'] ?>
                    </div>
                    <div style="font-size: 14px; opacity: 0.9;">Total Clients</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white; border-radius: 8px;">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;">
                        <?= $stats['active'] ?>
                    </div>
                    <div style="font-size: 14px; opacity: 0.9;">Active Clients</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%); color: white; border-radius: 8px;">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;">
                        <?= $stats['inactive'] ?>
                    </div>
                    <div style="font-size: 14px; opacity: 0.9;">Inactive Clients</div>
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
