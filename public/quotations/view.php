<?php
/**
 * Quotations Module - View Quotation
 * Display quotation details with tabs for Overview, Items, and Activity Log
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!quotations_tables_exist($conn)) {
    header('Location: ../../scripts/setup_quotations_tables.php');
    exit;
}

// All logged-in users have access to quotations
$can_edit = true;
$can_delete = true;


// Get quotation ID from URL and validate
$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$quotation_id) {
    die("Invalid quotation ID.");
}

$quotation = get_quotation_by_id($conn, $quotation_id);
if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found.';
    header('Location: index.php');
    exit;
}

$quotation_items = get_quotation_items($conn, $quotation_id);
$activity_log = get_quotation_activity_log($conn, $quotation_id);

// Safe access to quotation data
$quotationNo = (string)($quotation['quotation_no'] ?? '');
$title = (string)($quotation['title'] ?? '');
$clientName = (string)($quotation['client_name'] ?? 'N/A');
$clientEmail = (string)($quotation['client_email'] ?? '');
$clientPhone = (string)($quotation['client_phone'] ?? '');
$clientAddress = (string)($quotation['client_address'] ?? '');
$quotationDate = $quotation['quotation_date'] ?? '';
$validityDate = $quotation['validity_date'] ?? '';
$status = (string)($quotation['status'] ?? 'Draft');
$currency = (string)($quotation['currency'] ?? 'INR');
$subtotal = $quotation['subtotal'] ?? 0;
$taxAmount = $quotation['tax_amount'] ?? 0;
$discountAmount = $quotation['discount_amount'] ?? 0;
$totalAmount = $quotation['total_amount'] ?? 0;
$notes = (string)($quotation['notes'] ?? '');
$terms = (string)($quotation['terms'] ?? '');
$brochurePdf = (string)($quotation['brochure_pdf'] ?? '');
$createdBy = (string)($quotation['created_by_name'] ?? '');
$createdAt = $quotation['created_at'] ?? '';

$active_tab = $_GET['tab'] ?? 'overview';

$page_title = 'View Quotation - ' . $quotationNo . ' - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>🧾 <?php echo htmlspecialchars($quotationNo); ?></h1>
                    <p><?php echo htmlspecialchars($title); ?></p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if ($can_edit): ?>
                        <a href="edit.php?id=<?php echo $quotation_id; ?>" class="btn btn-accent">✏️ Edit</a>
                    <?php endif; ?>
                    <a href="excel.php?id=<?php echo $quotation_id; ?>" class="btn btn-primary">📊 Export to Excel</a>
                    <?php if ($status === 'Accepted'): ?>
                        <a href="convert_to_invoice.php?id=<?php echo $quotation_id; ?>" class="btn btn-success">💰 Convert to Invoice</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">← Back to List</a>
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

        <!-- Status Banner -->
        <div class="card" style="margin-bottom: 25px;">
            <?php
            $status_colors = [
                'Draft' => ['bg' => '#6c757d', 'text' => '#fff'],
                'Sent' => ['bg' => '#0066cc', 'text' => '#fff'],
                'Accepted' => ['bg' => '#28a745', 'text' => '#fff'],
                'Rejected' => ['bg' => '#dc3545', 'text' => '#fff'],
                'Expired' => ['bg' => '#ffc107', 'text' => '#000']
            ];
            $status_style = $status_colors[$status] ?? ['bg' => '#6c757d', 'text' => '#fff'];
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: <?php echo $status_style['bg']; ?>; color: <?php echo $status_style['text']; ?>; border-radius: 8px;">
                <div>
                    <h3 style="margin: 0; font-size: 24px;">Status: <?php echo htmlspecialchars($status); ?></h3>
                    <?php if ($validityDate): ?>
                        <p style="margin: 4px 0 0 0; opacity: 0.9;">
                            Valid until: <?php echo date('d M Y', strtotime($validityDate)); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 32px; font-weight: 700;">
                        <?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($totalAmount, 2); ?>
                    </div>
                    <div style="opacity: 0.9;">Total Amount</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; gap: 16px; border-bottom: 2px solid #e0e0e0; margin-bottom: 24px;">
                <a href="?id=<?php echo $quotation_id; ?>&tab=overview" 
                   style="padding: 12px 24px; text-decoration: none; color: <?php echo $active_tab === 'overview' ? '#003581' : '#6c757d'; ?>; border-bottom: 3px solid <?php echo $active_tab === 'overview' ? '#003581' : 'transparent'; ?>; font-weight: 600;">
                    📋 Overview
                </a>
                <a href="?id=<?php echo $quotation_id; ?>&tab=items" 
                   style="padding: 12px 24px; text-decoration: none; color: <?php echo $active_tab === 'items' ? '#003581' : '#6c757d'; ?>; border-bottom: 3px solid <?php echo $active_tab === 'items' ? '#003581' : 'transparent'; ?>; font-weight: 600;">
                    🛒 Items (<?php echo count($quotation_items); ?>)
                </a>
                <a href="?id=<?php echo $quotation_id; ?>&tab=activity" 
                   style="padding: 12px 24px; text-decoration: none; color: <?php echo $active_tab === 'activity' ? '#003581' : '#6c757d'; ?>; border-bottom: 3px solid <?php echo $active_tab === 'activity' ? '#003581' : 'transparent'; ?>; font-weight: 600;">
                    📜 Activity Log
                </a>
            </div>

            <!-- Tab Content -->
            <?php if ($active_tab === 'overview'): ?>
                <!-- Overview Tab -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <!-- Left Column -->
                    <div>
                        <h3 style="color: #003581; margin-bottom: 16px;">Client Information</h3>
                        <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                            <div style="margin-bottom: 12px;">
                                <strong>Name:</strong> <?php echo htmlspecialchars($clientName); ?>
                            </div>
                            <?php if ($clientEmail): ?>
                                <div style="margin-bottom: 12px;">
                                    <strong>Email:</strong> 
                                    <a href="mailto:<?php echo htmlspecialchars($clientEmail); ?>" style="color: #0066cc;">
                                        <?php echo htmlspecialchars($clientEmail); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($clientPhone): ?>
                                <div style="margin-bottom: 12px;">
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($clientPhone); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($clientAddress): ?>
                                <div>
                                    <strong>Address:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($clientAddress)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h3 style="color: #003581; margin-bottom: 16px;">Quotation Details</h3>
                        <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                            <div style="margin-bottom: 12px;">
                                <strong>Quotation No:</strong> <code><?php echo htmlspecialchars($quotationNo); ?></code>
                            </div>
                            <div style="margin-bottom: 12px;">
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($quotationDate)); ?>
                            </div>
                            <?php if ($validityDate): ?>
                                <div style="margin-bottom: 12px;">
                                    <strong>Valid Until:</strong> <?php echo date('d M Y', strtotime($validityDate)); ?>
                                </div>
                            <?php endif; ?>
                            <div style="margin-bottom: 12px;">
                                <strong>Created By:</strong> <?php echo htmlspecialchars($createdBy); ?>
                            </div>
                            <div>
                                <strong>Created At:</strong> <?php echo date('d M Y H:i', strtotime($createdAt)); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div>
                        <h3 style="color: #003581; margin-bottom: 16px;">Amount Breakdown</h3>
                        <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
                                <span>Subtotal:</span>
                                <strong><?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($subtotal, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
                                <span>Discount:</span>
                                <strong style="color: #dc3545;">-<?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($discountAmount, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
                                <span>Tax:</span>
                                <strong><?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($taxAmount, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 18px; padding-top: 8px;">
                                <strong style="color: #003581;">Total Amount:</strong>
                                <strong style="color: #003581;"><?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($totalAmount, 2); ?></strong>
                            </div>
                        </div>

                        <?php if ($notes): ?>
                            <h3 style="color: #003581; margin-bottom: 16px;">Notes</h3>
                            <div style="background: #fff3cd; padding: 16px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #ffc107;">
                                <?php echo nl2br(htmlspecialchars($notes)); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($terms): ?>
                            <h3 style="color: #003581; margin-bottom: 16px;">Terms & Conditions</h3>
                            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                                <?php echo nl2br(htmlspecialchars($terms)); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($brochurePdf): ?>
                            <h3 style="color: #003581; margin-bottom: 16px;">Attachments</h3>
                            <div style="background: #e7f3ff; padding: 16px; border-radius: 8px; border-left: 4px solid #0066cc;">
                                <a href="<?php echo APP_URL . '/' . htmlspecialchars($brochurePdf); ?>" 
                                   target="_blank" class="btn btn-info">
                                    📄 View Brochure
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($active_tab === 'items'): ?>
                <!-- Items Tab -->
                <h3 style="color: #003581; margin-bottom: 20px;">Line Items</h3>
                
                <?php if (empty($quotation_items)): ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <p>No items found in this quotation.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #003581;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600;">#</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600;">Item</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600;">Description</th>
                                    <th style="padding: 12px; text-align: right; font-weight: 600;">Qty</th>
                                    <th style="padding: 12px; text-align: right; font-weight: 600;">Unit Price</th>
                                    <th style="padding: 12px; text-align: right; font-weight: 600;">Tax %</th>
                                    <th style="padding: 12px; text-align: right; font-weight: 600;">Discount</th>
                                    <th style="padding: 12px; text-align: right; font-weight: 600;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quotation_items as $index => $item): ?>
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="padding: 12px;"><?php echo $index + 1; ?></td>
                                        <td style="padding: 12px;">
                                            <strong><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></strong>
                                            <br>
                                            <small style="color: #6c757d;"><?php echo htmlspecialchars($item['item_sku'] ?? ''); ?></small>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php echo htmlspecialchars($item['description'] ?? '-'); ?>
                                        </td>
                                        <td style="padding: 12px; text-align: right;">
                                            <?php echo number_format($item['quantity'], 2); ?>
                                        </td>
                                        <td style="padding: 12px; text-align: right;">
                                            <?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($item['unit_price'], 2); ?>
                                        </td>
                                        <td style="padding: 12px; text-align: right;">
                                            <?php echo number_format($item['tax_percent'], 2); ?>%
                                        </td>
                                        <td style="padding: 12px; text-align: right;">
                                            <?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($item['discount'], 2); ?>
                                        </td>
                                        <td style="padding: 12px; text-align: right; font-weight: 600;">
                                            <?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($item['total'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: 600;">
                                    <td colspan="7" style="padding: 12px; text-align: right;">Grand Total:</td>
                                    <td style="padding: 12px; text-align: right; color: #003581; font-size: 18px;">
                                        <?php echo $currency === 'INR' ? '₹' : '$'; ?><?php echo number_format($totalAmount, 2); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($active_tab === 'activity'): ?>
                <!-- Activity Log Tab -->
                <h3 style="color: #003581; margin-bottom: 20px;">Activity Log</h3>
                
                <?php if (empty($activity_log)): ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <p>No activity recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div style="max-width: 800px;">
                        <?php foreach ($activity_log as $log): ?>
                            <div style="border-left: 3px solid #0066cc; padding: 16px; margin-bottom: 16px; background: #f8f9fa; border-radius: 4px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <strong style="color: #003581;">
                                        <?php
                                        $action_icons = [
                                            'Create' => '➕',
                                            'Update' => '✏️',
                                            'StatusChange' => '🔄',
                                            'ConvertToInvoice' => '💰',
                                            'Send' => '📧',
                                            'Delete' => '🗑️'
                                        ];
                                        echo $action_icons[$log['action']] ?? '📝';
                                        ?>
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </strong>
                                    <span style="color: #6c757d; font-size: 14px;">
                                        <?php echo date('d M Y H:i', strtotime($log['created_at'])); ?>
                                    </span>
                                </div>
                                <?php if ($log['description']): ?>
                                    <p style="margin: 8px 0 0 0; color: #495057;">
                                        <?php echo htmlspecialchars($log['description']); ?>
                                    </p>
                                <?php endif; ?>
                                <small style="color: #6c757d;">
                                    By: <?php echo htmlspecialchars($log['user_full_name'] ?? $log['user_name'] ?? 'System'); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
