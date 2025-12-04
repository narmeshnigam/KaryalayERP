<?php
/**
 * Clients Module - Main Listing Page
 * View all clients with filters, search, and statistics
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

if (!authz_user_can_any($conn, [
    ['table' => 'clients', 'permission' => 'view_all'],
    ['table' => 'clients', 'permission' => 'view_assigned'],
    ['table' => 'clients', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'clients', 'view_all');
}

// Check if tables exist

if (!clients_tables_exist($conn)) {
    $page_title = 'Clients Module Setup Required - ' . APP_NAME;
    require_once __DIR__ . '/../../includes/header_sidebar.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-warning" style="margin:40px auto;max-width:600px;padding:32px 28px;font-size:1.1em;">';
    echo '<h2 style="margin-bottom:16px;color:#b85c00;">Clients Module Not Set Up</h2>';
    echo '<p>The clients module database tables have not been created yet. To use this module, please run the setup for clients.</p>';
    echo '<a href="' . APP_URL . '/scripts/setup_clients_tables.php" class="btn btn-primary" style="margin-top:18px;">Set Up Clients Module</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Get filters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['owner_id'])) {
    $filters['owner_id'] = (int)$_GET['owner_id'];
}
if (!empty($_GET['industry'])) {
    $filters['industry'] = $_GET['industry'];
}
if (!empty($_GET['tag'])) {
    $filters['tag'] = trim($_GET['tag']);
}

// Get all clients with filters
$clients = get_all_clients($conn, $_SESSION['user_id'], $filters);

// Get statistics
$stats = get_clients_statistics($conn, $_SESSION['user_id']);

// Get all owners for filter
$owners = $conn->query("SELECT id, username FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Get all industries for filter
$industries = get_all_industries($conn);

// Get all tags for filter
$tags = get_all_client_tags($conn);

// Check permissions
$can_create = authz_user_can($conn, 'clients', 'create');
$can_export = authz_user_can($conn, 'clients', 'update'); // Can export if can update

$page_title = 'All Clients - Clients - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
<style>
.clients-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.clients-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}

@media (max-width:768px){
.clients-header-flex{flex-direction:column;align-items:stretch;}
.clients-header-buttons{width:100%;flex-direction:column;gap:10px;}
.clients-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.clients-header-flex h1{font-size:1.5rem;}
}

/* Statistics Cards Responsive */
.clients-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:25px;}

@media (max-width:768px){
.clients-stats-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;}
}

@media (max-width:480px){
.clients-stats-grid{grid-template-columns:1fr;gap:12px;}
.clients-stats-grid .card{padding:16px !important;}
.clients-stats-grid .card>div:first-child{font-size:28px !important;}
}

/* Filter Form Responsive */
.clients-filter-form{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:15px;align-items:end;}

@media (max-width:1024px){
.clients-filter-form{grid-template-columns:1fr 1fr 1fr auto;gap:12px;}
}

@media (max-width:768px){
.clients-filter-form{grid-template-columns:1fr 1fr;gap:12px;}
.clients-filter-form>div:nth-child(4){grid-column:1/2;}
.clients-filter-form>div:nth-child(5){grid-column:2/3;}
}

@media (max-width:480px){
.clients-filter-form{grid-template-columns:1fr;gap:12px;}
.clients-filter-form>div:nth-child(4){grid-column:1/-1;}
.clients-filter-form>div:nth-child(5){grid-column:1/-1;}
.clients-filter-buttons{grid-column:1/-1 !important;display:flex;gap:8px;}
.clients-filter-buttons button,.clients-filter-buttons a{flex:1;}
}

/* Table Responsive */
.clients-table-container{overflow-x:auto;}
.clients-table-wrapper{width:100%;}

@media (max-width:768px){
.clients-table-wrapper{font-size:13px;}
.clients-table-wrapper th{padding:10px 8px !important;}
.clients-table-wrapper td{padding:10px 8px !important;}
.clients-table-wrapper a{padding:4px 8px !important;font-size:11px !important;}
}

@media (max-width:600px){
.clients-table-wrapper{display:block;width:100%;}
.clients-table-wrapper table{display:block;width:100%;}
.clients-table-wrapper thead{display:none;}
.clients-table-wrapper tbody{display:block;}
.clients-table-wrapper tbody tr{display:block;margin-bottom:20px;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;}
.clients-table-wrapper td{display:block;text-align:left !important;padding:12px !important;border:none;border-bottom:1px solid #dee2e6;}
.clients-table-wrapper td:last-child{border-bottom:none;}
.clients-table-wrapper td:before{content:attr(data-label);font-weight:700;color:#003581;margin-bottom:4px;display:block;font-size:12px;}
}

/* Avatar and Name Stack */
.clients-card-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, #003581 0%, #0059b3 100%);color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;flex-shrink:0;}

@media (max-width:480px){
.clients-card-avatar{width:36px;height:36px;font-size:12px;}
}

/* Stats Icons Responsive */
.clients-stats-icons{display:flex;gap:12px;font-size:12px;color:#6c757d;}

@media (max-width:480px){
.clients-stats-icons{gap:8px;font-size:11px;}
.clients-stats-icons>div{white-space:nowrap;}
}

/* Contact Details Responsive */
.clients-contact-details{display:flex;flex-direction:column;gap:4px;font-size:13px;}

@media (max-width:480px){
.clients-contact-details{font-size:12px;}
.clients-contact-details>div{word-break:break-word;}
}

/* Action Buttons Responsive */
.clients-actions{display:flex;gap:8px;justify-content:center;}

@media (max-width:480px){
.clients-actions{flex-direction:column;gap:6px;}
.clients-actions .btn{font-size:11px;padding:4px 8px;}
}
</style>

        <!-- Page Header -->
        <div class="page-header">
            <div class="clients-header-flex">
                <div style="flex: 1;">
                    <h1>🏢 Clients Management</h1>
                    <p>Manage your client relationships and information</p>
                </div>
                <div class="clients-header-buttons">
                    <a href="my.php" class="btn btn-accent">👤 My Clients</a>
                    <a href="import_export.php" class="btn btn-accent">📤 Import/Export</a>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn">➕ Add Client</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>


        <!-- Statistics Cards -->
        <div class="clients-stats-grid">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?= $stats['total'] ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Clients</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #faa718 0%, #ffc04d 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?= $stats['active'] ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Active Clients</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?= $stats['my_clients'] ?></div>
                <div style="font-size: 14px; opacity: 0.9;">My Clients</div>
            </div>
            
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?= $stats['with_projects'] ?></div>
                <div style="font-size: 14px; opacity: 0.9;">With Projects</div>
            </div>
        </div>


        <!-- Filters and Search -->
        <div class="card" style="margin-bottom: 25px;">
            <form method="GET" action="" class="clients-filter-form">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>🔍 Search Clients</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, Email, Phone, Code..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>📊 Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Active" <?= ($_GET['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($_GET['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>👤 Owner</label>
                    <select name="owner_id" class="form-control">
                        <option value="">All Owners</option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= $owner['id'] ?>" 
                                <?= ($_GET['owner_id'] ?? '') == $owner['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($owner['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>🏭 Industry</label>
                    <select name="industry" class="form-control">
                        <option value="">All Industries</option>
                        <?php foreach ($industries as $industry): ?>
                            <option value="<?= htmlspecialchars($industry) ?>" 
                                <?= ($_GET['industry'] ?? '') === $industry ? 'selected' : '' ?>>
                                <?= htmlspecialchars($industry) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="clients-filter-buttons" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="white-space: nowrap;">Search</button>
                    <a href="index.php" class="btn btn-accent" style="white-space: nowrap; text-decoration: none; display: inline-block; text-align: center;">Clear</a>
                </div>
            </form>
        </div>


        <!-- Clients Table -->
        <?php if (count($clients) > 0): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #003581;">
                        📋 Client List 
                        <span style="font-size: 14px; color: #6c757d; font-weight: normal;">(<?= count($clients) ?> records)</span>
                    </h3>
                </div>
                <div class="clients-table-container">
                <div class="clients-table-wrapper">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Client</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Contact Details</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Industry</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Owner</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Stats</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <td style="padding: 12px;" data-label="Client">
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <div class="clients-card-avatar">
                                            <?= get_client_initials($client['name']) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #1b2a57; margin-bottom: 2px;">
                                                <a href="view.php?id=<?= $client['id'] ?>" style="color: #003581; text-decoration: none;">
                                                    <?= htmlspecialchars($client['name']) ?>
                                                </a>
                                            </div>
                                            <div style="font-size: 11px; color: #6c757d; font-family: monospace;">
                                                <?= htmlspecialchars($client['code']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 12px;" data-label="Contact Details">
                                    <div class="clients-contact-details">
                                        <?php if ($client['email']): ?>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span>📧</span>
                                                <a href="mailto:<?= htmlspecialchars($client['email']) ?>" style="color: #003581; text-decoration: none;">
                                                    <?= htmlspecialchars($client['email']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($client['phone']): ?>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span>📞</span>
                                                <a href="tel:<?= htmlspecialchars($client['phone']) ?>" style="color: #003581; text-decoration: none;">
                                                    <?= htmlspecialchars($client['phone']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 12px;" data-label="Industry">
                                    <?php if ($client['industry']): ?>
                                        <span style="background: #e3f2fd; color: #003581; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                            <?= htmlspecialchars($client['industry']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; font-size: 13px;" data-label="Owner"><?= htmlspecialchars($client['owner_username'] ?? 'N/A') ?></td>
                                <td style="padding: 12px; text-align: center;" data-label="Status">
                                    <?php
                                    $status_colors = [
                                        'Active' => 'background: #d4edda; color: #155724;',
                                        'Inactive' => 'background: #f8d7da; color: #721c24;'
                                    ];
                                    $status_style = $status_colors[$client['status']] ?? 'background: #e2e3e5; color: #383d41;';
                                    ?>
                                    <span style="<?= $status_style ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                        <?= htmlspecialchars($client['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;" data-label="Stats">
                                    <div class="clients-stats-icons">
                                        <div title="Contacts">👥 <?= $client['contact_count'] ?></div>
                                        <div title="Addresses">📍 <?= $client['address_count'] ?></div>
                                        <div title="Documents">📄 <?= $client['document_count'] ?></div>
                                    </div>
                                </td>
                                <td style="padding: 12px; text-align: center;" data-label="Actions">
                                    <div class="clients-actions">
                                        <a href="view.php?id=<?= $client['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                            👁️ View
                                        </a>
                                        <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                            ✏️ Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 60px 20px; color: #6c757d;">
                <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                <h3 style="color: #003581; margin-bottom: 10px;">No Clients Found</h3>
                <p>No clients match your search criteria. Try adjusting your filters.</p>
                <?php if ($can_create): ?>
                    <a href="add.php" class="btn" style="margin-top: 20px; text-decoration: none;">➕ Add First Client</a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-accent" style="margin-top: 20px; text-decoration: none;">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
