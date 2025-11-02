<?php
/**
 * Payments Module - List View
 * Display all payments with filters and search
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payments_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Check permissions via RBAC
$can_create = authz_user_can($conn, 'payments', 'create');
$can_edit = authz_user_can($conn, 'payments', 'edit_all');
$can_delete = authz_user_can($conn, 'payments', 'delete_all');
$can_export = authz_user_can($conn, 'payments', 'export');

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'client_id' => $_GET['client_id'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'payment_mode' => $_GET['payment_mode'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get payments
$payments = get_all_payments($conn, $filters);

// Get statistics
$stats = get_payment_statistics($conn);

// Get clients for filter
$clients = get_active_clients($conn);

$page_title = 'Payments - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .stat-card.green {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }
    .stat-card.orange {
        background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
    }
    .stat-card.blue {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .stat-label {
        opacity: 0.9;
        font-size: 14px;
    }
    .filters-card {
        background: white;
        padding: 24px;
        border-radius: 8px;
        margin-bottom: 24px;
        border: 1px solid #e0e0e0;
    }
    .table-container {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e0e0e0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th {
        background: #f8f9fa;
        padding: 16px;
        text-align: left;
        font-weight: 600;
        color: #003581;
        border-bottom: 2px solid #dee2e6;
    }
    td {
        padding: 16px;
        border-bottom: 1px solid #dee2e6;
    }
    tr:hover {
        background: #f8f9fa;
    }
    .badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-cash { background: #d4edda; color: #155724; }
    .badge-bank { background: #cce5ff; color: #004085; }
    .badge-upi { background: #fff3cd; color: #856404; }
    .badge-cheque { background: #f8d7da; color: #721c24; }
    .badge-other { background: #e2e3e5; color: #383d41; }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>💰 Payments</h1>
                    <p>Manage receipts and collections against invoices</p>
                </div>
                <div>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn">+ Add Payment</a>
                    <?php endif; ?>
                    <a href="export.php?<?php echo http_build_query($filters); ?>" class="btn btn-accent" target="_blank">📊 Export</a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);">
                <div class="stat-value">₹<?php echo number_format($stats['this_month_received'], 2); ?></div>
                <div class="stat-label">This Month Received</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #003581 0%, #004aad 100%);">
                <div class="stat-value">₹<?php echo number_format($stats['total_received'], 2); ?></div>
                <div class="stat-label">Total Payments Received</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #faa718 0%, #ffc04d 100%);">
                <div class="stat-value">₹<?php echo number_format($stats['pending_receivables'], 2); ?></div>
                <div class="stat-label">Pending Receivables</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%);">
                <div class="stat-value">₹<?php echo number_format($stats['unallocated_amount'], 2); ?></div>
                <div class="stat-label">Unallocated Amount</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                               placeholder="Payment No, Client, Reference..." 
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Client</label>
                        <select name="client_id" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $filters['client_id'] == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Payment Mode</label>
                        <select name="payment_mode" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">All Modes</option>
                            <option value="Cash" <?php echo $filters['payment_mode'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Bank Transfer" <?php echo $filters['payment_mode'] === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="UPI" <?php echo $filters['payment_mode'] === 'UPI' ? 'selected' : ''; ?>>UPI</option>
                            <option value="Cheque" <?php echo $filters['payment_mode'] === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            <option value="Other" <?php echo $filters['payment_mode'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" 
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" 
                               style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 8px;">
                        <button type="submit" class="btn" style="flex: 1;">🔍 Filter</button>
                        <a href="index.php" class="btn btn-accent">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Payment No</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Mode</th>
                        <th>Amount Received</th>
                        <th>Allocated</th>
                        <th>Balance</th>
                        <th>Invoices</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #6c757d;">
                                No payments found. <?php if ($can_create): ?>
                                    <a href="add.php" style="color: #003581;">Add your first payment</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($payment['payment_no']); ?></strong>
                                    <?php if (!empty($payment['reference_no'])): ?>
                                        <br><small style="color: #6c757d;">Ref: <?php echo htmlspecialchars($payment['reference_no']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                <td>
                                    <?php
                                    $mode_class = strtolower(str_replace(' ', '', $payment['payment_mode']));
                                    ?>
                                    <span class="badge badge-<?php echo $mode_class; ?>">
                                        <?php echo htmlspecialchars($payment['payment_mode']); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600;">₹<?php echo number_format($payment['amount_received'], 2); ?></td>
                                <td style="color: #28a745;">₹<?php echo number_format($payment['total_allocated'], 2); ?></td>
                                <td style="color: <?php echo $payment['unallocated_amount'] > 0 ? '#dc3545' : '#6c757d'; ?>;">
                                    ₹<?php echo number_format($payment['unallocated_amount'], 2); ?>
                                </td>
                                <td><?php echo (int)$payment['invoice_count']; ?> invoice(s)</td>
                                <td>
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="view.php?id=<?php echo $payment['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                            👁️ View
                                        </a>
                                        <?php if ($can_edit): ?>
                                            <a href="edit.php?id=<?php echo $payment['id']; ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                                ✏️ Edit
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($can_delete && $payment['total_allocated'] == 0): ?>
                                            <button onclick="deletePayment(<?php echo $payment['id']; ?>)" class="btn" style="background: #dc3545; color: white; padding: 6px 12px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer;">🗑️ Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deletePayment(paymentId) {
    if (!confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
        return;
    }
    
    fetch('../api/payments/delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({payment_id: paymentId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment deleted successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error deleting payment');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
