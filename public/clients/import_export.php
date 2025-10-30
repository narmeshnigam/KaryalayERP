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

$page_title = "Import/Export Clients";
include __DIR__ . '/../../includes/header_sidebar.php';
?>

<style>
.import-export-container {
    max-width: 900px;
    margin: 2rem auto;
}
.section-card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #333;
}
.section-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}
.feature-list {
    list-style: none;
    padding: 0;
    margin: 1rem 0;
}
.feature-list li {
    padding: 0.5rem 0;
    color: #666;
}
.feature-list li::before {
    content: "✓ ";
    color: #28a745;
    font-weight: bold;
    margin-right: 0.5rem;
}
.csv-format {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.875rem;
    overflow-x: auto;
}
.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}
.alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}
.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
}
</style>

<div class="container mt-4">
    <div class="import-export-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Import/Export Clients</h2>
            <a href="index.php" class="btn btn-secondary">← Back to List</a>
        </div>

        <!-- Export Section -->
        <div class="section-card">
            <div class="text-center section-icon">📤</div>
            <h3 class="section-title text-center">Export Clients</h3>
            <p class="text-center text-muted">Download your client data as CSV file</p>
            
            <ul class="feature-list">
                <li>Export all clients or filtered subset</li>
                <li>CSV format compatible with Excel and Google Sheets</li>
                <li>Includes all client details</li>
                <li>Can be re-imported after editing</li>
            </ul>
            
            <div class="text-center mt-4">
                <a href="?action=export" class="btn btn-success btn-lg">
                    📥 Export All Clients to CSV
                </a>
            </div>
            
            <div class="alert alert-info mt-3">
                <strong>💡 Tip:</strong> Use filters on the main clients page, then click export to download only filtered results.
            </div>
        </div>

        <!-- Import Section -->
        <div class="section-card">
            <div class="text-center section-icon">📥</div>
            <h3 class="section-title text-center">Import Clients</h3>
            <p class="text-center text-muted">Bulk upload clients from CSV file</p>
            
            <ul class="feature-list">
                <li>Bulk import multiple clients at once</li>
                <li>Automatic duplicate detection by email/phone</li>
                <li>Preview before importing</li>
                <li>Skip or update duplicates</li>
            </ul>
            
            <div class="alert alert-warning">
                <strong>⚠️ Import Requirements:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li>CSV file must have headers in first row</li>
                    <li>At minimum, "Name" column is required</li>
                    <li>Owner must exist (use username)</li>
                    <li>Status must be "Active" or "Inactive"</li>
                </ul>
            </div>
            
            <h4 style="margin-top: 2rem;">CSV Format</h4>
            <p>Your CSV file should have the following columns:</p>
            <div class="csv-format">
Name,Legal Name,Industry,Email,Phone,Website,GSTIN,Status,Owner,Tags,Notes<br>
"Acme Corporation","Acme Corp Ltd","IT Services","info@acme.com","9876543210","https://acme.com","22AAAAA0000A1Z5","Active","admin","VIP,Enterprise","Important client"<br>
"TechStart Inc","TechStart Private Limited","Software","hello@techstart.com","9876543211","","","Active","admin","Startup",""
            </div>
            
            <div class="text-center mt-4">
                <a href="import_wizard.php" class="btn btn-primary btn-lg">
                    📤 Start Import Wizard
                </a>
                <a href="sample_clients.csv" class="btn btn-outline-secondary btn-lg" download>
                    📄 Download Sample CSV
                </a>
            </div>
        </div>

        <!-- Quick Stats -->
        <?php
        $stats = get_clients_statistics($conn, $_SESSION['user_id']);
        ?>
        <div class="section-card">
            <h3 class="section-title">Current Database</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #007bff;">
                        <?= $stats['total'] ?>
                    </div>
                    <div style="color: #666;">Total Clients</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #28a745;">
                        <?= $stats['active'] ?>
                    </div>
                    <div style="color: #666;">Active</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #6c757d;">
                        <?= $stats['inactive'] ?>
                    </div>
                    <div style="color: #666;">Inactive</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer_sidebar.php'; ?>
