<?php
/**
 * Payments Module - View Payment
 * Detailed view with tabs: Overview, Linked Invoices, Activity Log
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!payments_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Check permissions via RBAC
$can_edit = authz_user_can($conn, 'payments', 'edit_all');
$can_delete = authz_user_can($conn, 'payments', 'delete_all');

// Get payment ID
$payment_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$payment_id) {
    $_SESSION['flash_error'] = 'Invalid payment ID';
    header('Location: index.php');
    exit;
}

// Get payment
$payment = get_payment_by_id($conn, $payment_id);
if (!$payment) {
    $_SESSION['flash_error'] = 'Payment not found';
    header('Location: index.php');
    exit;
}

// Get related data
$allocations = get_payment_allocations($conn, $payment_id);
$activity_log = get_payment_activity_log($conn, $payment_id);

// Get client info
$client_stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$client_stmt->bind_param('i', $payment['client_id']);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();
$client_stmt->close();

$page_title = 'View Payment - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Mode badge colors
$mode_colors = [
    'Cash' => 'background: #d4edda; color: #155724;',
    'Bank Transfer' => 'background: #cce5ff; color: #004085;',
    'UPI' => 'background: #fff3cd; color: #856404;',
    'Cheque' => 'background: #f8d7da; color: #721c24;',
    'Other' => 'background: #e2e3e5; color: #383d41;'
];
?>

<style>
    .payment-header {
        background: linear-gradient(135deg, #003581 0%, #004aad 100%);
        color: white;
        padding: 32px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .mode-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    .tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        border-bottom: 2px solid #e0e0e0;
    }
    .tab-btn {
        padding: 12px 24px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: 15px;
        font-weight: 500;
        color: #6c757d;
        transition: all 0.3s;
    }
    .tab-btn.active {
        color: #003581;
        border-bottom-color: #003581;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    .info-card {
        background: white;
        padding: 24px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        margin-bottom: 20px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        color: #6c757d;
        font-weight: 500;
    }
    .info-value {
        color: #212529;
        font-weight: 600;
    }
    .allocations-table {
        width: 100%;
        border-collapse: collapse;
    }
    .allocations-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #003581;
        border-bottom: 2px solid #dee2e6;
    }
    .allocations-table td {
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    .timeline-item {
        position: relative;
        padding-bottom: 24px;
        border-left: 2px solid #e0e0e0;
        padding-left: 30px;
    }
    .timeline-item:before {
        content: "●";
        position: absolute;
        left: -6px;
        top: 0;
        color: #003581;
        font-size: 12px;
        background: white;
    }
    .timeline-item:last-child {
        border-left-color: transparent;
    }
</style>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Payment Header -->
        <div class="payment-header">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 12px;">
                        <h1 style="margin: 0; font-size: 32px;"><?php echo htmlspecialchars($payment['payment_no']); ?></h1>
                        <span class="mode-badge" style="<?php echo $mode_colors[$payment['payment_mode']] ?? 'background: #e2e3e5; color: #383d41;'; ?>">
                            <?php echo htmlspecialchars($payment['payment_mode']); ?>
                        </span>
                    </div>
                    <div style="opacity: 0.9; font-size: 16px;">
                        <strong>Client:</strong> <?php echo htmlspecialchars($client['name']); ?><br>
                        <strong>Payment Date:</strong> <?php echo date('d M Y', strtotime($payment['payment_date'])); ?>
                        <?php if (!empty($payment['reference_no'])): ?>
                            | <strong>Ref:</strong> <?php echo htmlspecialchars($payment['reference_no']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 28px; font-weight: 700; margin-bottom: 8px;">
                        ₹<?php echo number_format($payment['amount_received'], 2); ?>
                    </div>
                    <div style="font-size: 14px; opacity: 0.9;">
                        Allocated: ₹<?php echo number_format($payment['total_allocated'], 2); ?><br>
                        Unallocated: ₹<?php echo number_format($payment['unallocated_amount'], 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Messages -->
        <?php if (isset($_GET['created'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                ✅ Payment recorded successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                ✅ Payment updated successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['allocated'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                ✅ Payment allocated to invoices successfully!
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 12px; margin-bottom: 30px;">
            <a href="index.php" class="btn btn-accent" style="text-decoration: none;">← Back to List</a>
            
            <?php if ($can_edit): ?>
                <a href="edit.php?id=<?php echo $payment_id; ?>" class="btn btn-accent">✏️ Edit Payment</a>
            <?php endif; ?>
            
            <?php if ($payment['unallocated_amount'] > 0 && $can_edit): ?>
                <a href="allocate.php?id=<?php echo $payment_id; ?>" class="btn" style="background: #28a745; color: white;">💰 Allocate to Invoices</a>
            <?php endif; ?>
            
            <?php if ($can_delete && $payment['total_allocated'] == 0): ?>
                <button onclick="deletePayment()" class="btn" style="background: #dc3545; color: white;">🗑️ Delete</button>
            <?php endif; ?>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('overview')">📋 Overview</button>
            <button class="tab-btn" onclick="showTab('invoices')">📄 Linked Invoices (<?php echo count($allocations); ?>)</button>
            <button class="tab-btn" onclick="showTab('activity')">📝 Activity Log</button>
        </div>

        <!-- Tab Content: Overview -->
        <div id="tab-overview" class="tab-content active">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                <!-- Payment Details -->
                <div class="info-card">
                    <h3 style="margin: 0 0 16px 0; color: #003581; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px;">
                        💰 Payment Details
                    </h3>
                    <div class="info-row">
                        <span class="info-label">Payment Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($payment['payment_no']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Date:</span>
                        <span class="info-value"><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Mode:</span>
                        <span class="mode-badge" style="<?php echo $mode_colors[$payment['payment_mode']] ?? ''; ?>">
                            <?php echo htmlspecialchars($payment['payment_mode']); ?>
                        </span>
                    </div>
                    <?php if (!empty($payment['reference_no'])): ?>
                        <div class="info-row">
                            <span class="info-label">Reference No:</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['reference_no']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Created By:</span>
                        <span class="info-value"><?php echo htmlspecialchars($payment['created_by_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created At:</span>
                        <span class="info-value"><?php echo date('d M Y, h:i A', strtotime($payment['created_at'])); ?></span>
                    </div>
                </div>

                <!-- Client Details -->
                <div class="info-card">
                    <h3 style="margin: 0 0 16px 0; color: #003581; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px;">
                        👤 Client Information
                    </h3>
                    <div class="info-row">
                        <span class="info-label">Client Name:</span>
                        <span class="info-value">
                            <a href="../clients/view.php?id=<?php echo $payment['client_id']; ?>" style="color: #003581; text-decoration: none;">
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
                </div>

                <!-- Amount Breakdown -->
                <div class="info-card">
                    <h3 style="margin: 0 0 16px 0; color: #003581; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px;">
                        💵 Amount Breakdown
                    </h3>
                    <div class="info-row" style="font-size: 16px;">
                        <span class="info-label">Amount Received:</span>
                        <span class="info-value" style="color: #28a745;">₹<?php echo number_format($payment['amount_received'], 2); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Allocated:</span>
                        <span class="info-value">₹<?php echo number_format($payment['total_allocated'], 2); ?></span>
                    </div>
                    <div class="info-row" style="background: #f8f9fa; margin: 12px -24px -24px; padding: 12px 24px; border-radius: 0 0 6px 6px;">
                        <span class="info-label" style="font-weight: 700;">Unallocated Balance:</span>
                        <span class="info-value" style="font-size: 18px; color: <?php echo $payment['unallocated_amount'] > 0 ? '#dc3545' : '#28a745'; ?>;">
                            ₹<?php echo number_format($payment['unallocated_amount'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Remarks -->
            <?php if (!empty($payment['remarks'])): ?>
                <div class="info-card" style="margin-top: 20px;">
                    <h3 style="margin: 0 0 12px 0; color: #003581;">📝 Remarks</h3>
                    <p style="margin: 0; line-height: 1.6; color: #495057;"><?php echo nl2br(htmlspecialchars($payment['remarks'])); ?></p>
                </div>
            <?php endif; ?>

            <!-- Attachment -->
            <?php if (!empty($payment['attachment'])): ?>
                <div class="info-card" style="margin-top: 20px;">
                    <h3 style="margin: 0 0 12px 0; color: #003581;">📎 Payment Proof</h3>
                    <a href="../../<?php echo htmlspecialchars($payment['attachment']); ?>" target="_blank" class="btn btn-secondary">
                        📄 View Attachment: <?php echo basename($payment['attachment']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Content: Linked Invoices -->
        <div id="tab-invoices" class="tab-content">
            <div class="info-card">
                <h3 style="margin: 0 0 20px 0; color: #003581;">📄 Allocated Invoices</h3>
                
                <?php if (empty($allocations)): ?>
                    <p style="text-align: center; color: #6c757d; padding: 40px 0;">
                        No invoices allocated yet. 
                        <?php if ($payment['unallocated_amount'] > 0): ?>
                            <a href="allocate.php?id=<?php echo $payment_id; ?>" style="color: #003581;">Allocate now</a>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <table class="allocations-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Issue Date</th>
                                <th>Total Amount</th>
                                <th>Previously Paid</th>
                                <th>Allocated Amount</th>
                                <th>Current Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allocations as $allocation): ?>
                                <tr>
                                    <td>
                                        <a href="../invoices/view.php?id=<?php echo $allocation['invoice_id']; ?>" 
                                           style="color: #003581; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($allocation['invoice_no']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($allocation['issue_date'])); ?></td>
                                    <td>₹<?php echo number_format($allocation['total_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($allocation['amount_paid'] - $allocation['allocated_amount'], 2); ?></td>
                                    <td style="color: #28a745; font-weight: 600;">₹<?php echo number_format($allocation['allocated_amount'], 2); ?></td>
                                    <td style="color: <?php echo $allocation['balance'] > 0 ? '#dc3545' : '#28a745'; ?>;">
                                        ₹<?php echo number_format($allocation['balance'], 2); ?>
                                    </td>
                                    <td>
                                        <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;
                                                     <?php 
                                                     if ($allocation['status'] === 'Paid') echo 'background: #d4edda; color: #155724;';
                                                     elseif ($allocation['status'] === 'Partially Paid') echo 'background: #fff3cd; color: #856404;';
                                                     else echo 'background: #e2e3e5; color: #383d41;';
                                                     ?>">
                                            <?php echo htmlspecialchars($allocation['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../invoices/view.php?id=<?php echo $allocation['invoice_id']; ?>" class="btn btn-sm btn-secondary">View Invoice</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px; text-align: right;">
                        <strong>Total Allocated:</strong> 
                        <span style="font-size: 18px; color: #28a745; font-weight: 700;">
                            ₹<?php echo number_format($payment['total_allocated'], 2); ?>
                        </span>
                    </div>
                <?php endif; ?>
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
                                    <?php echo htmlspecialchars($activity['action'] ?? ''); ?>
                                </div>
                                <?php if (!empty($activity['description'])): ?>
                                    <div style="color: #6c757d; margin-bottom: 8px; font-size: 14px;">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                <?php endif; ?>
                                <div style="color: #6c757d; font-size: 13px;">
                                    <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong>
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
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

// Delete payment
function deletePayment() {
    if (!confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
        return;
    }
    
    fetch('../api/payments/delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({payment_id: <?php echo $payment_id; ?>})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment deleted successfully');
            window.location.href = 'index.php';
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
