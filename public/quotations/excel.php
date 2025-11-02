<?php
/**
 * Quotations Module - Excel Export
 * Generate Excel file for quotation with all details
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!quotations_tables_exist($conn)) {
    header('Location: ../../scripts/setup_quotations_tables.php');
    exit;
}

$quotation_id = (int)($_GET['id'] ?? 0);
if (!$quotation_id) {
    header('Location: index.php');
    exit;
}

$quotation = get_quotation_by_id($conn, $quotation_id);
if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found.';
    header('Location: index.php');
    exit;
}

$quotation_items = get_quotation_items($conn, $quotation_id);

// Get branding details
$branding_result = $conn->query("SELECT * FROM branding_settings LIMIT 1");
$branding = $branding_result ? $branding_result->fetch_assoc() : null;

// Safe access for branding
$companyName = $branding['org_name'] ?? APP_NAME;
$companyAddress = trim(implode(', ', array_filter([
    $branding['address_line1'] ?? '',
    $branding['address_line2'] ?? '',
    $branding['city'] ?? '',
    $branding['state'] ?? '',
    $branding['zip'] ?? '',
    $branding['country'] ?? ''
])));
$companyPhone = $branding['phone'] ?? '';
$companyEmail = $branding['email'] ?? '';
$companyWebsite = $branding['website'] ?? '';

// Safe access for quotation
$quotationNo = (string)($quotation['quotation_no'] ?? '');
$title = (string)($quotation['title'] ?? '');
$clientName = (string)($quotation['client_name'] ?? 'N/A');
$clientEmail = (string)($quotation['client_email'] ?? '');
$clientPhone = (string)($quotation['client_phone'] ?? '');
$clientAddress = (string)($quotation['client_address'] ?? '');
$quotationDate = $quotation['quotation_date'] ?? '';
$validityDate = $quotation['validity_date'] ?? '';
$currency = (string)($quotation['currency'] ?? 'INR');
$currencySymbol = $currency === 'INR' ? '₹' : '$';
$subtotal = $quotation['subtotal'] ?? 0;
$taxAmount = $quotation['tax_amount'] ?? 0;
$discountAmount = $quotation['discount_amount'] ?? 0;
$totalAmount = $quotation['total_amount'] ?? 0;
$notes = (string)($quotation['notes'] ?? '');
$terms = (string)($quotation['terms'] ?? '');
$status = (string)($quotation['status'] ?? 'Draft');

// Set headers for Excel download
$filename = 'Quotation_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $quotationNo) . '_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output Excel content using HTML table format (compatible with Excel)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quotation - <?php echo htmlspecialchars($quotationNo); ?></title>
    <style>
        .header { font-size: 16pt; font-weight: bold; color: #003581; }
        .section-title { font-size: 12pt; font-weight: bold; color: #003581; background-color: #f0f0f0; }
        .company-name { font-size: 14pt; font-weight: bold; color: #003581; }
        .label { font-weight: bold; }
        .amount { text-align: right; }
        .total { font-weight: bold; font-size: 11pt; }
    </style>
</head>
<body>
    <table border="0" cellpadding="5" cellspacing="0" style="width: 100%;">
        <!-- Company Header -->
        <tr>
            <td colspan="7" class="company-name"><?php echo htmlspecialchars($companyName); ?></td>
        </tr>
        <?php if ($companyAddress): ?>
        <tr>
            <td colspan="7"><?php echo htmlspecialchars($companyAddress); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="7">
                <?php 
                $contact_parts = array_filter([$companyPhone, $companyEmail, $companyWebsite]);
                echo htmlspecialchars(implode(' | ', $contact_parts));
                ?>
            </td>
        </tr>
        <tr><td colspan="7">&nbsp;</td></tr>
        
        <!-- Quotation Header -->
        <tr>
            <td colspan="7" class="header">QUOTATION</td>
        </tr>
        <tr>
            <td colspan="7"><b>Quotation No:</b> <?php echo htmlspecialchars($quotationNo); ?></td>
        </tr>
        <tr>
            <td colspan="7"><b>Date:</b> <?php echo date('d M Y', strtotime($quotationDate)); ?></td>
        </tr>
        <?php if ($validityDate): ?>
        <tr>
            <td colspan="7"><b>Valid Until:</b> <?php echo date('d M Y', strtotime($validityDate)); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="7"><b>Status:</b> <?php echo htmlspecialchars($status); ?></td>
        </tr>
        <tr><td colspan="7">&nbsp;</td></tr>
        
        <!-- Quotation Title -->
        <tr>
            <td colspan="7" class="section-title">QUOTATION DETAILS</td>
        </tr>
        <tr>
            <td colspan="7"><b><?php echo htmlspecialchars($title); ?></b></td>
        </tr>
        <tr><td colspan="7">&nbsp;</td></tr>
        
        <!-- Bill To -->
        <tr>
            <td colspan="7" class="section-title">BILL TO</td>
        </tr>
        <tr>
            <td colspan="7"><b><?php echo htmlspecialchars($clientName); ?></b></td>
        </tr>
        <?php if ($clientAddress): ?>
        <tr>
            <td colspan="7"><?php echo htmlspecialchars($clientAddress); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($clientEmail): ?>
        <tr>
            <td colspan="7">Email: <?php echo htmlspecialchars($clientEmail); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($clientPhone): ?>
        <tr>
            <td colspan="7">Phone: <?php echo htmlspecialchars($clientPhone); ?></td>
        </tr>
        <?php endif; ?>
        <tr><td colspan="7">&nbsp;</td></tr>
        
        <!-- Items Table -->
        <tr>
            <td colspan="7" class="section-title">ITEMS</td>
        </tr>
        <tr style="background-color: #003581; color: white; font-weight: bold;">
            <td>#</td>
            <td>Item Name</td>
            <td>Description</td>
            <td style="text-align: right;">Quantity</td>
            <td style="text-align: right;">Unit Price</td>
            <td style="text-align: right;">Tax %</td>
            <td style="text-align: right;">Discount</td>
            <td style="text-align: right;">Total</td>
        </tr>
        <?php foreach ($quotation_items as $index => $item): ?>
        <tr>
            <td><?php echo $index + 1; ?></td>
            <td><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
            <td class="amount"><?php echo number_format($item['quantity'], 2); ?></td>
            <td class="amount"><?php echo number_format($item['unit_price'], 2); ?></td>
            <td class="amount"><?php echo number_format($item['tax_percent'], 2); ?>%</td>
            <td class="amount"><?php echo number_format($item['discount'], 2); ?></td>
            <td class="amount"><?php echo number_format($item['total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="8">&nbsp;</td></tr>
        
        <!-- Totals -->
        <tr>
            <td colspan="6"></td>
            <td class="label">Subtotal:</td>
            <td class="amount"><?php echo $currencySymbol . number_format($subtotal, 2); ?></td>
        </tr>
        <tr>
            <td colspan="6"></td>
            <td class="label">Discount:</td>
            <td class="amount">-<?php echo $currencySymbol . number_format($discountAmount, 2); ?></td>
        </tr>
        <tr>
            <td colspan="6"></td>
            <td class="label">Tax:</td>
            <td class="amount"><?php echo $currencySymbol . number_format($taxAmount, 2); ?></td>
        </tr>
        <tr>
            <td colspan="6"></td>
            <td class="label total">TOTAL AMOUNT:</td>
            <td class="amount total"><?php echo $currencySymbol . number_format($totalAmount, 2); ?></td>
        </tr>
        <tr><td colspan="8">&nbsp;</td></tr>
        
        <!-- Notes -->
        <?php if ($notes): ?>
        <tr>
            <td colspan="8" class="section-title">NOTES</td>
        </tr>
        <tr>
            <td colspan="8"><?php echo nl2br(htmlspecialchars($notes)); ?></td>
        </tr>
        <tr><td colspan="8">&nbsp;</td></tr>
        <?php endif; ?>
        
        <!-- Terms -->
        <?php if ($terms): ?>
        <tr>
            <td colspan="8" class="section-title">TERMS & CONDITIONS</td>
        </tr>
        <tr>
            <td colspan="8"><?php echo nl2br(htmlspecialchars($terms)); ?></td>
        </tr>
        <tr><td colspan="8">&nbsp;</td></tr>
        <?php endif; ?>
        
        <!-- Footer -->
        <tr><td colspan="8">&nbsp;</td></tr>
        <tr>
            <td colspan="8" style="text-align: center; font-style: italic;">Thank you for your business!</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: center; font-size: 9pt;">
                <?php echo htmlspecialchars($companyName); ?> | <?php echo htmlspecialchars($companyEmail); ?> | <?php echo htmlspecialchars($companyPhone); ?>
            </td>
        </tr>
    </table>
</body>
</html>
