<?php
/**
 * Quotations Module - Main Listing Page
 * View all quotations with filters, search, and statistics
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist - show setup prompt if not
if (!quotations_tables_exist($conn)) {
    $page_title = 'Quotations Module Setup Required - ' . APP_NAME;
    require_once __DIR__ . '/../../includes/header_sidebar.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    ?>
    <div class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1>🧾 Quotations</h1>
                    <p>Manage quotations for clients and leads</p>
                </div>
            </div>
            
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <strong>⚠️ Setup Required</strong><br>
                Quotations module database tables need to be created first.
            </div>
            
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 80px; margin-bottom: 20px;">🧾</div>
                <h2 style="color: #003581; margin-bottom: 15px;">Quotations Module Not Set Up</h2>
                <p style="color: #6c757d; margin-bottom: 30px; font-size: 16px;">
                    Create the required database tables to start managing quotations
                </p>
                <a href="<?php echo APP_URL; ?>/scripts/setup_quotations_tables.php" class="btn" style="padding: 15px 40px; font-size: 16px;">
                    🚀 Setup Quotations Module
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

// All logged-in users have access to quotations
$can_create = true;
$can_edit = true;
$can_delete = true;
$can_export = true;

// Get filters from request
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'client_id' => $_GET['client_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'expiring_days' => $_GET['expiring_days'] ?? ''
];

// Get quotations
$quotations = get_all_quotations($conn, $CURRENT_USER_ID, $filters);

// Get clients for filter dropdown
$clients = get_clients_for_dropdown($conn);

// Calculate statistics
$stats = [
    'total' => count($quotations),
    'draft' => count(array_filter($quotations, fn($q) => $q['status'] === 'Draft')),
    'sent' => count(array_filter($quotations, fn($q) => $q['status'] === 'Sent')),
    'accepted' => count(array_filter($quotations, fn($q) => $q['status'] === 'Accepted')),
    'rejected' => count(array_filter($quotations, fn($q) => $q['status'] === 'Rejected')),
    'expired' => count(array_filter($quotations, fn($q) => $q['status'] === 'Expired')),
    'total_value' => array_sum(array_column($quotations, 'total_amount')),
    'accepted_value' => array_sum(array_column(array_filter($quotations, fn($q) => $q['status'] === 'Accepted'), 'total_amount'))
];

$page_title = 'Quotations - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
<style>
.quotations-header-flex{display:flex;justify-content:space-between;align-items:center;}
.quotations-stats-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:25px;}
.quotations-filter-form{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto;gap:15px;align-items:end;}
.quotations-filter-buttons{display:flex;gap:10px;}
.quotations-table-wrapper{overflow-x:auto;}

@media (max-width:1024px){
.quotations-filter-form{grid-template-columns:1fr 1fr 1fr;gap:12px;}
.quotations-filter-buttons{width:100%;flex-direction:column;}
.quotations-filter-buttons .btn{width:100%;font-size:13px;}
}

@media (max-width:768px){
.quotations-header-flex{flex-direction:column;align-items:stretch;gap:16px;}
.quotations-header-flex .btn{width:100%;text-align:center;}
.quotations-stats-grid{grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;}
.quotations-filter-form{grid-template-columns:1fr;gap:10px;font-size:13px;}
.quotations-filter-form input,.quotations-filter-form select{font-size:13px;}
.quotations-filter-buttons{flex-direction:column;width:100%;}
}

@media (max-width:600px){
.quotations-table-wrapper table{display:block;width:100%;}
.quotations-table-wrapper thead{display:none;}
.quotations-table-wrapper tbody tr{display:block;margin-bottom:20px;border:1px solid #ddd;border-radius:6px;overflow:hidden;}
.quotations-table-wrapper tbody td{display:block;width:100%;padding:12px;border-top:1px solid #f0f0f0;text-align:left;position:relative;padding-left:40%;}
.quotations-table-wrapper tbody td:first-child{border-top:none;padding-left:12px;}
.quotations-table-wrapper tbody td::before{content:attr(data-label);position:absolute;left:12px;top:12px;font-weight:600;color:#003581;width:35%;word-wrap:break-word;}
.quotations-table-wrapper tbody td:first-child::before{content:'';}
}

@media (max-width:480px){
.quotations-header-flex h1{font-size:1.5rem;}
.quotations-stats-grid{grid-template-columns:1fr;gap:12px;}
.quotations-filter-form input,.quotations-filter-form select{font-size:16px;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="quotations-header-flex">
                <div>
                    <h1>🧾 Quotations</h1>
                    <p>Manage professional quotations for clients and leads</p>
                </div>
                <?php if ($can_create): ?>
                <div>
                    <a href="add.php" class="btn" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; font-size: 15px; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: #fff; border-radius: 6px; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                        <span style="font-size: 18px;">➕</span> New Quotation
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['flash_success']); 
                unset($_SESSION['flash_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['flash_error']); 
                unset($_SESSION['flash_error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards (Employee-style gradients) -->
        <div class="quotations-stats-grid">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['total']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Quotations</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #0066cc 0%, #33aaff 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['sent']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Sent</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['accepted']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Accepted</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #dc3545 0%, #ff6f91 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['rejected']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Rejected</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #00b894 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;">₹<?php echo number_format($stats['total_value'], 2); ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Value</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #00b894 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;">₹<?php echo number_format($stats['accepted_value'], 2); ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Accepted Value</div>
            </div>
        </div>

        <!-- Filters Card (Employee-style layout) -->
        <div class="card" style="margin-bottom: 25px;">
            <form method="GET" action="index.php" class="quotations-filter-form">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>🔍 Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Quotation No, Title, Client..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Draft" <?php echo $filters['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="Sent" <?php echo $filters['status'] === 'Sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="Accepted" <?php echo $filters['status'] === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="Rejected" <?php echo $filters['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="Expired" <?php echo $filters['status'] === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Client</label>
                    <select name="client_id" class="form-control">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $filters['client_id'] == $client['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Expiring In (Days)</label>
                    <input type="number" name="expiring_days" class="form-control" placeholder="e.g., 7" min="1" value="<?php echo htmlspecialchars($filters['expiring_days']); ?>">
                </div>
                <div class="quotations-filter-buttons">
                    <button type="submit" class="btn" style="white-space: nowrap;">Apply</button>
                    <a href="index.php" class="btn btn-accent" style="white-space: nowrap; text-decoration: none; display: inline-block; text-align: center;">Clear</a>
                    <?php if ($can_export): ?>
                        <a href="export.php?<?php echo http_build_query($filters); ?>" class="btn btn-accent">📥 Export</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Quotations Table -->
        <div class="card">
            <h3 style="margin-top: 0; color: #003581;">📋 Quotations List (<?php echo count($quotations); ?>)</h3>
            
            <?php if (empty($quotations)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">📄</div>
                    <h3>No quotations found</h3>
                    <p>Create your first quotation to get started</p>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn" style="margin-top: 16px;">➕ New Quotation</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #003581;">
                                <th style="padding: 12px; text-align: left; font-weight: 600;">Quotation No</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600;">Client</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600;">Title</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600;">Date</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600;">Validity</th>
                                <th style="padding: 12px; text-align: right; font-weight: 600;">Amount</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600;">Items</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600;">Status</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotations as $quotation): ?>
                                <tr style="border-bottom: 1px solid #e0e0e0;">
                                    <td style="padding: 12px;">
                                        <a href="view.php?id=<?php echo $quotation['id']; ?>" 
                                           style="color: #0066cc; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($quotation['quotation_no']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php if ($quotation['client_name']): ?>
                                            <?php echo htmlspecialchars($quotation['client_name']); ?>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php echo htmlspecialchars($quotation['title']); ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php if ($quotation['validity_date']): ?>
                                            <?php 
                                            $validity = date('d M Y', strtotime($quotation['validity_date']));
                                            $is_expiring = $quotation['is_expired'];
                                            ?>
                                            <span style="color: <?php echo $is_expiring ? '#dc3545' : '#6c757d'; ?>;">
                                                <?php echo $validity; ?>
                                                <?php if ($is_expiring): ?>
                                                    <span style="font-size: 10px;">⚠️</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-weight: 600;">
                                        ₹<?php echo number_format($quotation['total_amount'], 2); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <span class="badge badge-info">
                                            <?php echo $quotation['item_count']; ?> items
                                        </span>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <?php
                                        $status_colors = [
                                            'Draft' => 'background: #6c757d;',
                                            'Sent' => 'background: #0066cc;',
                                            'Accepted' => 'background: #28a745;',
                                            'Rejected' => 'background: #dc3545;',
                                            'Expired' => 'background: #ffc107; color: #000;'
                                        ];
                                        $status_style = $status_colors[$quotation['status']] ?? 'background: #6c757d;';
                                        ?>
                                        <span class="badge" style="<?php echo $status_style; ?>">
                                            <?php echo htmlspecialchars($quotation['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <?php
                                        $status_colors = [
                                            'Draft' => 'background: #6c757d;',
                                            'Sent' => 'background: #0066cc;',
                                            'Accepted' => 'background: #28a745;',
                                            'Rejected' => 'background: #dc3545;',
                                            'Expired' => 'background: #ffc107; color: #000;'
                                        ];
                                        $status_style = $status_colors[$quotation['status']] ?? 'background: #6c757d;';
                                        ?>
                                        <span class="badge" style="<?php echo $status_style; ?>">
                                            <?php echo htmlspecialchars($quotation['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <a href="view.php?id=<?php echo $quotation['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="View">👁️</a>
                                            <?php if ($can_edit): ?>
                                                <a href="edit.php?id=<?php echo $quotation['id']; ?>" 
                                                   class="btn btn-sm btn-accent" title="Edit">✏️</a>
                                            <?php endif; ?>
                                            <?php if ($quotation['status'] === 'Accepted'): ?>
                                                <a href="convert_to_invoice.php?id=<?php echo $quotation['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="Convert to Invoice">📄</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
