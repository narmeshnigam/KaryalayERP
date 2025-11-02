<?php
/**
 * Invoices Module - Main Listing Page
 * View all invoices with filters, search, and statistics
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist - show onboarding if not
if (!invoices_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// All logged-in users have access to invoices
$can_create = true;
$can_edit = true;
$can_delete = true;
$can_export = true;

// Get filters from request
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'client_id' => $_GET['client_id'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'overdue_only' => isset($_GET['overdue_only']) ? 1 : 0
];

// Get invoices
$invoices = get_all_invoices($conn, $filters);

// Get clients for filter dropdown
$clients = get_active_clients($conn);

// Calculate statistics
$stats = get_invoice_statistics($conn);

$page_title = 'Invoices - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>🧾 Invoices</h1>
                    <p>Create, track, and manage client invoices with payments</p>
                </div>
                <div>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn" style="display: inline-flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">➕</span> Add New Invoice
                        </a>
                    <?php endif; ?>
                </div>
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

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px;">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #003581 0%, #004aad 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['total_invoices']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Invoices</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #6c757d 0%, #868e96 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['draft_invoices']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Draft</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #0066cc 0%, #33aaff 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['issued_invoices']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Issued</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #dc3545 0%, #ff6f91 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['overdue_invoices']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Overdue</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #28a745 0%, #34ce57 100%); color: white;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo $stats['paid_invoices']; ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Paid</div>
            </div>
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #ffc107 0%, #ffdb4d 100%); color: #000;">
                <div style="font-size: 28px; font-weight: 700; margin-bottom: 5px;">₹<?php echo number_format((float)($stats['outstanding_amount'] ?? 0), 2); ?></div>
                <div style="font-size: 14px; opacity: 0.8;">Outstanding</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card" style="margin-bottom: 25px;">
            <form method="GET" action="index.php" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>🔍 Search Invoices</label>
                    <input type="text" name="search" class="form-control" placeholder="Invoice No, Client Name..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>📊 Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Draft" <?php echo $filters['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="Issued" <?php echo $filters['status'] === 'Issued' ? 'selected' : ''; ?>>Issued</option>
                        <option value="Partially Paid" <?php echo $filters['status'] === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                        <option value="Paid" <?php echo $filters['status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Overdue" <?php echo $filters['status'] === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="Cancelled" <?php echo $filters['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>🏢 Client</label>
                    <select name="client_id" class="form-control">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $filters['client_id'] == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="white-space: nowrap;">Search</button>
                    <a href="index.php" class="btn btn-accent" style="white-space: nowrap; text-decoration: none; display: inline-block; text-align: center;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Invoice List -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #003581;">
                    📋 Invoice List 
                    <span style="font-size: 14px; color: #6c757d; font-weight: normal;">(<?php echo count($invoices); ?> records)</span>
                </h3>
                <div style="display: flex; gap: 10px;">
                    <?php if ($can_export): ?>
                        <button onclick="exportToExcel()" class="btn btn-accent" style="padding: 8px 16px; font-size: 13px;">
                            📊 Export to Excel
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($invoices) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Invoice No</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Client</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Issue Date</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Due Date</th>
                                <th style="padding: 12px; text-align: right; font-weight: 600; color: #003581;">Total Amount</th>
                                <th style="padding: 12px; text-align: right; font-weight: 600; color: #003581;">Paid</th>
                                <th style="padding: 12px; text-align: right; font-weight: 600; color: #003581;">Balance</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Status</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 12px; font-weight: 600; color: #003581;">
                                        <?php echo htmlspecialchars($invoice['invoice_no']); ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php echo htmlspecialchars($invoice['client_name'] ?? '-'); ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                        <?php echo date('d-M-Y', strtotime($invoice['issue_date'])); ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 13px; color: #6c757d;">
                                        <?php if ($invoice['due_date']): ?>
                                            <?php 
                                            $due_date = date('d-M-Y', strtotime($invoice['due_date']));
                                            $is_overdue = $invoice['is_overdue'];
                                            ?>
                                            <span style="color: <?php echo $is_overdue ? '#dc3545' : '#6c757d'; ?>; font-weight: <?php echo $is_overdue ? '600' : 'normal'; ?>;">
                                                <?php echo $due_date; ?>
                                                <?php if ($is_overdue && $invoice['days_overdue'] > 0): ?>
                                                    <br><small>(<?php echo $invoice['days_overdue']; ?> days overdue)</small>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-weight: 600;">
                                        ₹<?php echo number_format($invoice['total_amount'], 2); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; color: #28a745; font-weight: 600;">
                                        ₹<?php echo number_format($invoice['amount_paid'], 2); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-weight: 600; color: <?php echo $invoice['balance'] > 0 ? '#dc3545' : '#28a745'; ?>;">
                                        ₹<?php echo number_format($invoice['balance'], 2); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <?php
                                        $status_colors = [
                                            'Draft' => 'background: #d6d8db; color: #383d41;',
                                            'Issued' => 'background: #cfe2ff; color: #084298;',
                                            'Partially Paid' => 'background: #fff3cd; color: #856404;',
                                            'Paid' => 'background: #d4edda; color: #155724;',
                                            'Overdue' => 'background: #f8d7da; color: #721c24;',
                                            'Cancelled' => 'background: #f5c6cb; color: #721c24;'
                                        ];
                                        $status_style = $status_colors[$invoice['status']] ?? 'background: #e2e3e5; color: #383d41;';
                                        ?>
                                        <span style="<?php echo $status_style; ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                            <?php echo htmlspecialchars($invoice['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <a href="view.php?id=<?php echo $invoice['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                                👁️ View
                                            </a>
                                            <span style="display: inline-block; position: relative; z-index: 2;">
                                                <a href="edit.php?id=<?php echo $invoice['id']; ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                                    ✏️ Edit
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                    <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                    <h3 style="color: #003581; margin-bottom: 10px;">No Invoices Found</h3>
                    <p>No invoices match your search criteria. Try adjusting your filters.</p>
                    <a href="index.php" class="btn btn-accent" style="margin-top: 20px; text-decoration: none;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    window.location.href = 'export.php?<?php echo http_build_query($filters); ?>';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
