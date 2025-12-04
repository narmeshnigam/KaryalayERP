<?php
/**
 * Quotations Module - Convert to Invoice
 * Convert accepted quotation into an invoice
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!quotations_tables_exist($conn)) {
    header('Location: ' . APP_URL . '/scripts/setup_quotations_tables.php');
    exit;
}

$quotation_id = (int)($_GET['id'] ?? 0);
if (!$quotation_id) {
    $_SESSION['flash_error'] = 'Invalid quotation ID.';
    header('Location: index.php');
    exit;
}

$quotation = get_quotation_by_id($conn, $quotation_id);
if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found.';
    header('Location: index.php');
    exit;
}

// Check if quotation is accepted
if ($quotation['status'] !== 'Accepted') {
    $_SESSION['flash_error'] = 'Only accepted quotations can be converted to invoices.';
    header('Location: view.php?id=' . $quotation_id);
    exit;
}

$quotation_items = get_quotation_items($conn, $quotation_id);

$page_title = 'Convert to Invoice - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>💰 Convert Quotation to Invoice</h1>
                    <p>Create an invoice from this accepted quotation</p>
                </div>
                <a href="view.php?id=<?php echo $quotation_id; ?>" class="btn btn-accent">← Back to Quotation</a>
            </div>
        </div>

        <!-- Information Card -->
        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <h3 style="margin: 0 0 10px 0; color: #155724;">✅ Ready to Convert</h3>
                <p style="margin: 0; color: #155724;">
                    This quotation has been accepted and is ready to be converted into an invoice. 
                    The invoice will retain all items, pricing, and client information from the quotation.
                </p>
            </div>

            <h3 style="color: #003581; margin-bottom: 20px;">Quotation Summary</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                    <div style="margin-bottom: 12px;">
                        <strong>Quotation No:</strong> <?php echo htmlspecialchars($quotation['quotation_no']); ?>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <strong>Client:</strong> <?php echo htmlspecialchars($quotation['client_name'] ?? 'N/A'); ?>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <strong>Date:</strong> <?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?>
                    </div>
                    <div>
                        <strong>Status:</strong> 
                        <span class="badge badge-success"><?php echo htmlspecialchars($quotation['status']); ?></span>
                    </div>
                </div>
                
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                    <div style="margin-bottom: 12px;">
                        <strong>Subtotal:</strong> ₹<?php echo number_format($quotation['subtotal'], 2); ?>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <strong>Discount:</strong> <span style="color: #dc3545;">-₹<?php echo number_format($quotation['discount_amount'], 2); ?></span>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <strong>Tax:</strong> ₹<?php echo number_format($quotation['tax_amount'], 2); ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 700; color: #003581;">
                        <strong>Total:</strong> ₹<?php echo number_format($quotation['total_amount'], 2); ?>
                    </div>
                </div>
            </div>

            <h3 style="color: #003581; margin-bottom: 16px;">Items (<?php echo count($quotation_items); ?>)</h3>
            <div style="overflow-x: auto; margin-bottom: 30px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #003581;">
                            <th style="padding: 10px; text-align: left;">#</th>
                            <th style="padding: 10px; text-align: left;">Item</th>
                            <th style="padding: 10px; text-align: right;">Qty</th>
                            <th style="padding: 10px; text-align: right;">Price</th>
                            <th style="padding: 10px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotation_items as $index => $item): ?>
                            <tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 10px;"><?php echo $index + 1; ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></td>
                                <td style="padding: 10px; text-align: right;"><?php echo number_format($item['quantity'], 2); ?></td>
                                <td style="padding: 10px; text-align: right;">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td style="padding: 10px; text-align: right; font-weight: 600;">₹<?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Placeholder for Invoice Module Integration -->
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <h4 style="margin: 0 0 10px 0; color: #856404;">ℹ️ Invoice Module Integration</h4>
                <p style="margin: 0; color: #856404;">
                    To complete the conversion process, the Invoice module needs to be installed and configured. 
                    This feature will automatically create an invoice with all quotation details, including:
                </p>
                <ul style="margin: 10px 0 0 20px; color: #856404;">
                    <li>All line items with pricing and taxes</li>
                    <li>Client information</li>
                    <li>Payment terms and notes</li>
                    <li>Reference to this quotation (<?php echo htmlspecialchars($quotation['quotation_no']); ?>)</li>
                </ul>
            </div>

            <form method="POST" id="convertForm">
                <input type="hidden" name="quotation_id" value="<?php echo $quotation_id; ?>">
                
                <div style="background: #e7f3ff; border-left: 4px solid #0066cc; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h4 style="margin: 0 0 10px 0; color: #004085;">📝 What happens next?</h4>
                    <ol style="margin: 10px 0 0 20px; color: #004085; line-height: 1.6;">
                        <li>A new invoice will be created with all quotation details</li>
                        <li>The quotation will be marked as "Converted"</li>
                        <li>Activity log will record the conversion</li>
                        <li>You'll be redirected to the new invoice</li>
                    </ol>
                </div>

                <div style="text-align: center;">
                    <button type="button" onclick="alert('Invoice module integration required. This feature will be available once the Invoice module is installed.')" 
                            class="btn btn-success" style="padding: 15px 60px; font-size: 16px;">
                        💰 Convert to Invoice
                    </button>
                    <a href="view.php?id=<?php echo $quotation_id; ?>" 
                       class="btn btn-secondary" style="padding: 15px 60px; font-size: 16px; margin-left: 15px; text-decoration: none;">
                        ❌ Cancel
                    </a>
                </div>
            </form>

            <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center; color: #6c757d; font-size: 14px;">
                <strong>Note:</strong> This conversion feature requires the Invoice module. 
                Once installed, the integration will be automatic and seamless.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
