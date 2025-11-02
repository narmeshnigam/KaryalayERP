<?php
/**
 * Invoices Module - Export Individual Invoice to Excel
 * Exports a single invoice with company branding
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Get invoice ID
$invoice_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$invoice_id) {
    $_SESSION['flash_error'] = 'Invalid invoice ID';
    header('Location: index.php');
    exit;
}

// Get invoice
$invoice = get_invoice_by_id($conn, $invoice_id);
if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found';
    header('Location: index.php');
    exit;
}

// Get invoice items
$invoice_items = get_invoice_items($conn, $invoice_id);

// Get client details
$client_stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$client_stmt->bind_param("i", $invoice['client_id']);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();
$client_stmt->close();

// Get company branding
$branding_result = $conn->query("SELECT * FROM branding LIMIT 1");
$branding = $branding_result ? $branding_result->fetch_assoc() : null;

// Currency symbol
$currency_symbol = $invoice['currency'] === 'INR' ? '₹' : '$';

// Set headers for Excel download
$filename = 'Invoice_' . $invoice['invoice_no'] . '_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 24px; font-weight: bold; color: #003581; }
        .invoice-title { font-size: 18px; font-weight: bold; margin: 20px 0; color: #003581; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #003581; color: white; padding: 10px; text-align: left; font-weight: bold; }
        td { padding: 8px; border: 1px solid #ddd; }
        .info-table td { border: none; padding: 5px; }
        .totals-table { width: 40%; margin-left: 60%; margin-top: 20px; }
        .totals-table td { padding: 8px; }
        .grand-total { font-weight: bold; font-size: 14px; background-color: #f0f0f0; }
        .status { padding: 5px 10px; border-radius: 3px; display: inline-block; }
        .status-draft { background-color: #6c757d; color: white; }
        .status-issued { background-color: #17a2b8; color: white; }
        .status-paid { background-color: #28a745; color: white; }
        .status-overdue { background-color: #dc3545; color: white; }
        .status-cancelled { background-color: #343a40; color: white; }
    </style>
</head>
<body>
    <!-- Company Header -->
    <div class="header">
        <?php if ($branding && !empty($branding['company_name'])): ?>
            <div class="company-name"><?php echo htmlspecialchars($branding['company_name']); ?></div>
            <?php if (!empty($branding['company_address'])): ?>
                <div><?php echo nl2br(htmlspecialchars($branding['company_address'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($branding['company_phone'])): ?>
                <div>Phone: <?php echo htmlspecialchars($branding['company_phone']); ?></div>
            <?php endif; ?>
            <?php if (!empty($branding['company_email'])): ?>
                <div>Email: <?php echo htmlspecialchars($branding['company_email']); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Invoice Title -->
    <div class="invoice-title">INVOICE</div>

    <!-- Invoice & Client Info -->
    <table class="info-table" style="margin-bottom: 20px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <strong>BILL TO:</strong><br>
                <strong><?php echo htmlspecialchars($client['name']); ?></strong><br>
                <?php if (!empty($client['billing_address'])): ?>
                    <?php echo nl2br(htmlspecialchars($client['billing_address'])); ?><br>
                <?php endif; ?>
                <?php if (!empty($client['phone'])): ?>
                    Phone: <?php echo htmlspecialchars($client['phone']); ?><br>
                <?php endif; ?>
                <?php if (!empty($client['email'])): ?>
                    Email: <?php echo htmlspecialchars($client['email']); ?>
                <?php endif; ?>
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right;">
                <strong>Invoice No:</strong> <?php echo htmlspecialchars($invoice['invoice_no']); ?><br>
                <strong>Status:</strong> 
                <span class="status status-<?php echo strtolower($invoice['status']); ?>">
                    <?php echo $invoice['status']; ?>
                </span><br>
                <strong>Issue Date:</strong> <?php echo date('d M Y', strtotime($invoice['issue_date'])); ?><br>
                <?php if ($invoice['due_date']): ?>
                    <strong>Due Date:</strong> <?php echo date('d M Y', strtotime($invoice['due_date'])); ?><br>
                <?php endif; ?>
                <?php if ($invoice['payment_terms']): ?>
                    <strong>Payment Terms:</strong> <?php echo htmlspecialchars($invoice['payment_terms']); ?><br>
                <?php endif; ?>
                <strong>Currency:</strong> <?php echo htmlspecialchars($invoice['currency']); ?>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">Item Name</th>
                <th style="width: 20%;">Description</th>
                <th style="width: 10%;">Quantity</th>
                <th style="width: 10%;">Unit Price</th>
                <th style="width: 10%;">Tax %</th>
                <th style="width: 10%;">Discount</th>
                <th style="width: 10%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice_items as $index => $item): ?>
                <tr>
                    <td style="text-align: center;"><?php echo $index + 1; ?></td>
                    <td>
                        <?php echo htmlspecialchars($item['item_name']); ?>
                        <?php if (!empty($item['item_code'])): ?>
                            <br><small><?php echo htmlspecialchars($item['item_code']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($item['description']) ? htmlspecialchars($item['description']) : '-'; ?></td>
                    <td style="text-align: right;">
                        <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?>
                    </td>
                    <td style="text-align: right;"><?php echo $currency_symbol . number_format($item['unit_price'], 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format($item['tax_percent'], 2); ?>%</td>
                    <td style="text-align: right;"><?php echo $currency_symbol . number_format($item['discount'], 2); ?></td>
                    <td style="text-align: right; font-weight: bold;">
                        <?php echo $currency_symbol . number_format($item['line_total'], 2); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <table class="totals-table">
        <tr>
            <td><strong>Subtotal:</strong></td>
            <td style="text-align: right;"><?php echo $currency_symbol . number_format($invoice['subtotal'], 2); ?></td>
        </tr>
        <?php if ($invoice['tax_amount'] > 0): ?>
            <tr>
                <td><strong>Tax:</strong></td>
                <td style="text-align: right;"><?php echo $currency_symbol . number_format($invoice['tax_amount'], 2); ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($invoice['discount_amount'] > 0): ?>
            <tr>
                <td><strong>Discount:</strong></td>
                <td style="text-align: right;">-<?php echo $currency_symbol . number_format($invoice['discount_amount'], 2); ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($invoice['round_off'] != 0): ?>
            <tr>
                <td><strong>Round Off:</strong></td>
                <td style="text-align: right;"><?php echo $currency_symbol . number_format($invoice['round_off'], 2); ?></td>
            </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td><strong>GRAND TOTAL:</strong></td>
            <td style="text-align: right; font-size: 16px;">
                <strong><?php echo $currency_symbol . number_format($invoice['total_amount'], 2); ?></strong>
            </td>
        </tr>
        <?php if ($invoice['status'] !== 'Paid'): ?>
            <tr style="background-color: #fff3cd;">
                <td><strong>Amount Paid:</strong></td>
                <td style="text-align: right;"><?php echo $currency_symbol . number_format($invoice['paid_amount'], 2); ?></td>
            </tr>
            <tr style="background-color: #f8d7da;">
                <td><strong>Amount Pending:</strong></td>
                <td style="text-align: right; color: #dc3545; font-weight: bold;">
                    <?php echo $currency_symbol . number_format($invoice['pending_amount'], 2); ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <!-- Notes and Terms -->
    <?php if (!empty($invoice['notes']) || !empty($invoice['terms'])): ?>
        <div style="margin-top: 30px;">
            <?php if (!empty($invoice['notes'])): ?>
                <div style="margin-bottom: 15px;">
                    <strong>Notes:</strong><br>
                    <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($invoice['terms'])): ?>
                <div>
                    <strong>Terms & Conditions:</strong><br>
                    <?php echo nl2br(htmlspecialchars($invoice['terms'])); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div style="margin-top: 40px; text-align: center; font-size: 11px; color: #666;">
        <p>This is a computer-generated invoice and does not require a signature.</p>
        <p>Generated on <?php echo date('d M Y, h:i A'); ?></p>
    </div>
</body>
</html>
