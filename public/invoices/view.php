<?php
/**
 * Invoices Module - View Invoice
 * Detailed view with tabs: Overview, Items, Payments, Activity Log
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!invoices_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// All logged-in users have access to invoices
$can_edit = true;
$can_delete = true;

// Get invoice ID
$invoice_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$invoice_id) {
    $_SESSION['flash_error'] = 'Invalid invoice ID';
    header('Location: index.php');
    exit;
}

// Get invoice
// Get invoice
$invoice = get_invoice_by_id($conn, $invoice_id);
if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found';
    header('Location: index.php');
    exit;
}
// Calculate pending_amount for display safety
$invoice['pending_amount'] = max(0, (float)($invoice['total_amount'] ?? 0) - (float)($invoice['amount_paid'] ?? 0));

// Get related data
$invoice_items = get_invoice_items($conn, $invoice_id);
$activity_log = get_invoice_activity_log($conn, $invoice_id);

// Get client details
$client_stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$client_stmt->bind_param("i", $invoice['client_id']);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();
$client_stmt->close();

// Get quotation if linked
$quotation = null;
if ($invoice['quotation_id']) {
    $quot_stmt = $conn->prepare("SELECT quotation_no, status FROM quotations WHERE id = ?");
    $quot_stmt->bind_param("i", $invoice['quotation_id']);
    $quot_stmt->execute();
    $quotation = $quot_stmt->get_result()->fetch_assoc();
    $quot_stmt->close();
}

// Status badge colors
$status_colors = [
    'Draft' => 'background: #6c757d;',
    'Issued' => 'background: #17a2b8;',
    'Partially Paid' => 'background: #ffc107; color: #000;',
    'Paid' => 'background: #28a745;',
    'Overdue' => 'background: #dc3545;',
    'Cancelled' => 'background: #343a40;'
];

$page_title = 'Invoice: ' . $invoice['invoice_no'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .invoice-header { background: linear-gradient(135deg, #003581 0%, #0055d4 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
    .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; color: white; font-weight: 600; font-size: 14px; }
    .tabs { display: flex; gap: 0; border-bottom: 2px solid #e0e0e0; margin-bottom: 30px; background: #f8f9fa; border-radius: 8px 8px 0 0; padding: 0 20px; }
    .tab-btn { background: none; border: none; padding: 16px 24px; cursor: pointer; font-size: 15px; font-weight: 500; color: #6c757d; border-bottom: 3px solid transparent; transition: all 0.3s; }
    .tab-btn.active { color: #003581; border-bottom-color: #003581; background: white; }
    .tab-btn:hover { color: #003581; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .info-card { background: white; padding: 24px; border-radius: 8px; border: 1px solid #e0e0e0; margin-bottom: 20px; }
    .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .info-label { font-weight: 600; color: #495057; }
    .info-value { color: #212529; }
    .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .items-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #dee2e6; }
    .items-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
    .totals-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; }
    .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
    .total-row.grand { font-size: 20px; font-weight: 700; color: #003581; border-top: 2px solid #003581; margin-top: 8px; padding-top: 12px; }
    .timeline { position: relative; padding-left: 30px; }
    .timeline-item { position: relative; padding-bottom: 20px; }
    .timeline-item:before { content: ''; position: absolute; left: -22px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: #003581; }
    .timeline-item:after { content: ''; position: absolute; left: -17px; top: 17px; width: 2px; height: calc(100% - 10px); background: #e0e0e0; }
    .timeline-item:last-child:after { display: none; }
    .action-buttons { display: flex; gap: 12px; flex-wrap: wrap; }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 12px;">
                        <h1 style="margin: 0; font-size: 32px;"><?php echo htmlspecialchars($invoice['invoice_no']); ?></h1>
                        <span class="status-badge" style="<?php echo $status_colors[$invoice['status']] ?? 'background: #6c757d;'; ?>">
                            <?php echo $invoice['status']; ?>
                        </span>
                    </div>
                    <div style="opacity: 0.9; font-size: 16px;">
                        <strong>Client:</strong> <?php echo htmlspecialchars($client['name']); ?><br>
                        <strong>Issue Date:</strong> <?php echo date('d M Y', strtotime($invoice['issue_date'])); ?>
                        <?php if ($invoice['due_date']): ?>
                            | <strong>Due:</strong> <?php echo date('d M Y', strtotime($invoice['due_date'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 28px; font-weight: 700; margin-bottom: 8px;">
                        <?php 
                        $currency_symbol = $invoice['currency'] === 'INR' ? '₹' : '$';
                        echo $currency_symbol . number_format($invoice['total_amount'], 2); 
                        ?>
                    </div>
                    <?php if ($invoice['status'] !== 'Paid'): ?>
                        <div style="font-size: 14px; opacity: 0.9;">
                            Pending: <?php echo $currency_symbol . number_format($invoice['pending_amount'], 2); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons" style="margin-bottom: 30px;">
            <a href="index.php" class="btn btn-secondary">← Back to List</a>
            
            <?php if ($invoice['status'] === 'Draft' && $can_edit): ?>
                <a href="edit.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary">✏️ Edit Invoice</a>
                <button onclick="issueInvoice()" class="btn btn-success">✅ Issue Invoice</button>
            <?php endif; ?>
            
            <?php if ($invoice['status'] !== 'Cancelled'): ?>
                <a href="excel.php?id=<?php echo $invoice_id; ?>" class="btn btn-accent" target="_blank">📊 Export to Excel</a>
            <?php endif; ?>
            
            <?php if (in_array($invoice['status'], ['Draft', 'Issued']) && $can_delete): ?>
                <button onclick="cancelInvoice()" class="btn" style="background: #ffc107; color: #000;">🚫 Cancel Invoice</button>
            <?php endif; ?>
            
            <?php if ($invoice['status'] === 'Draft' && $can_delete): ?>
                <button onclick="deleteInvoice()" class="btn btn-danger">🗑️ Delete</button>
            <?php endif; ?>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('overview')">📋 Overview</button>
            <button class="tab-btn" onclick="showTab('items')">🛒 Items (<?php echo count($invoice_items); ?>)</button>
            <button class="tab-btn" onclick="showTab('payments')">💰 Payments</button>
            <button class="tab-btn" onclick="showTab('activity')">📝 Activity Log</button>
        </div>

        <!-- Tab Content: Overview -->
        <div id="tab-overview" class="tab-content active">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                <!-- Invoice Details -->
                <div class="info-card">
                    <h3 style="margin: 0 0 16px 0; color: #003581; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px;">
                        📄 Invoice Details
                    </h3>
                    <div class="info-row">
                        <span class="info-label">Invoice Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($invoice['invoice_no']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="status-badge" style="<?php echo $status_colors[$invoice['status']] ?? 'background: #6c757d;'; ?> font-size: 12px; padding: 4px 12px;">
                            <?php echo $invoice['status']; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Issue Date:</span>
                        <span class="info-value"><?php echo date('d M Y', strtotime($invoice['issue_date'])); ?></span>
                    </div>
                    <?php if ($invoice['due_date']): ?>
                        <div class="info-row">
                            <span class="info-label">Due Date:</span>
                            <span class="info-value" style="color: <?php echo strtotime($invoice['due_date']) < time() && $invoice['status'] !== 'Paid' ? '#dc3545' : '#212529'; ?>">
                                <?php echo date('d M Y', strtotime($invoice['due_date'])); ?>
                                <?php if (strtotime($invoice['due_date']) < time() && $invoice['status'] !== 'Paid'): ?>
                                    <span style="font-size: 12px; color: #dc3545;">(Overdue)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($invoice['payment_terms']): ?>
                        <div class="info-row">
                            <span class="info-label">Payment Terms:</span>
                            <span class="info-value"><?php echo htmlspecialchars($invoice['payment_terms']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Currency:</span>
                        <span class="info-value"><?php echo htmlspecialchars($invoice['currency']); ?></span>
                    </div>
                    <?php if ($quotation): ?>
                        <div class="info-row">
                            <span class="info-label">From Quotation:</span>
                            <span class="info-value">
                                <a href="../quotations/view.php?id=<?php echo $invoice['quotation_id']; ?>" style="color: #003581; text-decoration: none;">
                                    <?php echo htmlspecialchars($quotation['quotation_no']); ?>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Client Information -->
                <div class="info-card">
                    <h3 style="margin: 0 0 16px 0; color: #003581; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px;">
                        🏢 Client Information
                    </h3>
                    <div class="info-row">
                        <span class="info-label">Client Name:</span>
                        <span class="info-value">
                            <a href="../clients/view.php?id=<?php echo $client['id']; ?>" style="color: #003581; text-decoration: none;">
                                <?php echo htmlspecialchars($client['name']); ?>
                            </a>
                        </span>
                    </div>
                    <?php if (!empty($client['email'])): ?>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($client['email']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($client['phone'])): ?>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($client['phone']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($client['billing_address'])): ?>
                        <div class="info-row" style="display: block;">
                            <span class="info-label">Billing Address:</span>
                            <div class="info-value" style="margin-top: 8px; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($client['billing_address'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Financial Summary -->
                <div class="info-card">
                    <h3 style="margin: 0 0 16px 0; color: #003581; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px;">
                        💰 Financial Summary
                    </h3>
                    <div class="info-row">
                        <span class="info-label">Subtotal:</span>
                        <span class="info-value"><?php echo $currency_symbol . number_format($invoice['subtotal'], 2); ?></span>
                    </div>
                    <?php if ($invoice['tax_amount'] > 0): ?>
                        <div class="info-row">
                            <span class="info-label">Tax:</span>
                            <span class="info-value"><?php echo $currency_symbol . number_format($invoice['tax_amount'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($invoice['discount_amount'] > 0): ?>
                        <div class="info-row">
                            <span class="info-label">Discount:</span>
                            <span class="info-value" style="color: #28a745;">-<?php echo $currency_symbol . number_format($invoice['discount_amount'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($invoice['round_off'] != 0): ?>
                        <div class="info-row">
                            <span class="info-label">Round Off:</span>
                            <span class="info-value"><?php echo $currency_symbol . number_format($invoice['round_off'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row grand" style="font-size: 16px; color: #003581; font-weight: 700;">
                        <span>Total Amount:</span>
                        <span><?php echo $currency_symbol . number_format($invoice['total_amount'], 2); ?></span>
                    </div>
                    <div class="info-row" style="background: #e3f2fd; margin: 12px -10px -10px; padding: 12px 10px; border-radius: 0 0 6px 6px;">
                        <span class="info-label">Pending Amount:</span>
                        <span class="info-value" style="font-size: 18px; font-weight: 700; color: <?php echo $invoice['pending_amount'] > 0 ? '#dc3545' : '#28a745'; ?>">
                            <?php echo $currency_symbol . number_format($invoice['pending_amount'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Notes and Terms -->
            <?php if (!empty($invoice['notes']) || !empty($invoice['terms'])): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php if (!empty($invoice['notes'])): ?>
                        <div class="info-card">
                            <h3 style="margin: 0 0 12px 0; color: #003581;">📝 Notes</h3>
                            <p style="margin: 0; line-height: 1.6; color: #495057;"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['terms'])): ?>
                        <div class="info-card">
                            <h3 style="margin: 0 0 12px 0; color: #003581;">📄 Terms & Conditions</h3>
                            <p style="margin: 0; line-height: 1.6; color: #495057;"><?php echo nl2br(htmlspecialchars($invoice['terms'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Attachment -->
            <?php if (!empty($invoice['attachment'])): ?>
                <div class="info-card" style="margin-top: 20px;">
                    <h3 style="margin: 0 0 12px 0; color: #003581;">📎 Attachment</h3>
                    <a href="../../<?php echo htmlspecialchars($invoice['attachment']); ?>" target="_blank" class="btn btn-secondary">
                        📄 View Attachment: <?php echo basename($invoice['attachment']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Content: Items -->
        <div id="tab-items" class="tab-content">
            <div class="info-card">
                <h3 style="margin: 0 0 20px 0; color: #003581;">🛒 Invoice Items</h3>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 25%;">Item</th>
                            <th style="width: 20%;">Description</th>
                            <th style="width: 10%;">Quantity</th>
                            <th style="width: 10%;">Unit Price</th>
                            <th style="width: 10%;">Tax</th>
                            <th style="width: 10%;">Discount</th>
                            <th style="width: 10%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice_items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <?php if (!empty($item['item_code'])): ?>
                                        <br><small style="color: #6c757d;"><?php echo htmlspecialchars($item['item_code']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($item['description']) ? nl2br(htmlspecialchars($item['description'])) : '-'; ?></td>
                                <td><?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></td>
                                <td><?php echo $currency_symbol . number_format($item['unit_price'], 2); ?></td>
                                <td><?php echo number_format($item['tax_percent'], 2); ?>%</td>
                                <td><?php echo $currency_symbol . number_format($item['discount'], 2); ?></td>
                                <td style="font-weight: 600;"><?php echo $currency_symbol . number_format($item['line_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div style="max-width: 400px; margin-left: auto;">
                    <div class="totals-box">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span><?php echo $currency_symbol . number_format($invoice['subtotal'], 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Tax:</span>
                            <span><?php echo $currency_symbol . number_format($invoice['tax_amount'], 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Discount:</span>
                            <span><?php echo $currency_symbol . number_format($invoice['discount_amount'], 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Round Off:</span>
                            <span><?php echo $currency_symbol . number_format($invoice['round_off'], 2); ?></span>
                        </div>
                        <div class="total-row grand">
                            <span>Grand Total:</span>
                            <span><?php echo $currency_symbol . number_format($invoice['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Payments -->
        <div id="tab-payments" class="tab-content">
            <div class="info-card">
                <h3 style="margin: 0 0 20px 0; color: #003581;">💰 Payment Information</h3>
                
                <div class="info-row">
                    <span class="info-label">Total Amount:</span>
                    <span class="info-value"><?php echo $currency_symbol . number_format($invoice['total_amount'], 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Paid Amount:</span>
                    <span class="info-value" style="color: #28a745; font-weight: 600;">
                        <?php echo $currency_symbol . number_format((float)($invoice['paid_amount'] ?? 0), 2); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Pending Amount:</span>
                    <span class="info-value" style="color: <?php echo $invoice['pending_amount'] > 0 ? '#dc3545' : '#28a745'; ?>; font-weight: 600;">
                        <?php echo $currency_symbol . number_format($invoice['pending_amount'], 2); ?>
                    </span>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <strong>ℹ️ Note:</strong> Payment tracking will be integrated with the Payments module.
                </div>
            </div>
        </div>

        <!-- Tab Content: Activity Log -->
        <div id="tab-activity" class="tab-content">
            <div class="info-card">
                <h3 style="margin: 0 0 20px 0; color: #003581;">📝 Activity Log</h3>
                
                <?php if (!empty($activity_log)): ?>
                    <div class="timeline">
                        <?php foreach ($activity_log as $activity): ?>
                            <div class="timeline-item">
                                <div style="font-weight: 600; color: #003581; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($activity['activity_type'] ?? ($activity['action'] ?? '')); ?>
                                </div>
                                <?php if (!empty($activity['description'])): ?>
                                    <div style="color: #6c757d; margin-bottom: 8px; font-size: 14px;">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                <?php endif; ?>
                                <div style="color: #6c757d; font-size: 13px;">
                                    <strong><?php echo htmlspecialchars($activity['created_by_name'] ?? $activity['user_name'] ?? ''); ?></strong>
                                    • <?php echo date('d M Y, h:i A', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6c757d; text-align: center; padding: 40px 0;">No activity recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

// Issue invoice
function issueInvoice() {
    if (!confirm('Issue this invoice? This will mark it as Issued and deduct inventory for product items.')) {
        return;
    }
    
    fetch('../api/invoices/issue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({invoice_id: <?php echo $invoice_id; ?>})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while issuing the invoice.');
    });
}

// Cancel invoice
function cancelInvoice() {
    const restore = confirm('Cancel this invoice?\n\nClick OK to cancel and restore inventory.\nClick Cancel to cancel without restoring inventory.');
    
    fetch('../api/invoices/cancel.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            invoice_id: <?php echo $invoice_id; ?>,
            restore_inventory: restore
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while cancelling the invoice.');
    });
}

// Delete invoice
function deleteInvoice() {
    if (!confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
        return;
    }
    
    fetch('../api/invoices/delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({invoice_id: <?php echo $invoice_id; ?>})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'index.php';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the invoice.');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
