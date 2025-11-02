<?php
/**
 * Quotations Module - List Export to Excel
 * Export filtered quotations list to Excel
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!quotations_tables_exist($conn)) {
    header('Location: ../../scripts/setup_quotations_tables.php');
    exit;
}

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'client_id' => (int)($_GET['client_id'] ?? 0),
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];

// Get quotations
$quotations = get_all_quotations($conn, $filters);

// Get branding for company name
$branding_result = $conn->query("SELECT * FROM branding_settings LIMIT 1");
$branding = $branding_result ? $branding_result->fetch_assoc() : null;
$companyName = $branding['org_name'] ?? APP_NAME;

// Set headers for Excel download
$filename = 'Quotations_Export_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Build filter description
$filter_desc = [];
if (!empty($filters['status'])) {
    $filter_desc[] = 'Status: ' . $filters['status'];
}
if (!empty($filters['client_id'])) {
    $filter_desc[] = 'Client ID: ' . $filters['client_id'];
}
if (!empty($filters['search'])) {
    $filter_desc[] = 'Search: ' . $filters['search'];
}
if (!empty($filters['date_from'])) {
    $filter_desc[] = 'From: ' . date('d M Y', strtotime($filters['date_from']));
}
if (!empty($filters['date_to'])) {
    $filter_desc[] = 'To: ' . date('d M Y', strtotime($filters['date_to']));
}
$filter_text = !empty($filter_desc) ? implode(' | ', $filter_desc) : 'All Quotations';

// Output Excel content
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quotations Export</title>
    <style>
        .header { font-size: 14pt; font-weight: bold; color: #003581; }
        .subheader { font-size: 10pt; color: #6c757d; }
        .amount { text-align: right; }
    </style>
</head>
<body>
    <table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <!-- Header -->
        <tr>
            <td colspan="10" class="header"><?php echo htmlspecialchars($companyName); ?> - Quotations Export</td>
        </tr>
        <tr>
            <td colspan="10" class="subheader">Generated: <?php echo date('d M Y H:i:s'); ?></td>
        </tr>
        <tr>
            <td colspan="10" class="subheader">Filters: <?php echo htmlspecialchars($filter_text); ?></td>
        </tr>
        <tr>
            <td colspan="10">&nbsp;</td>
        </tr>
        
        <!-- Column Headers -->
        <tr style="background-color: #003581; color: white; font-weight: bold;">
            <td>Quotation No</td>
            <td>Title</td>
            <td>Client Name</td>
            <td>Client Email</td>
            <td>Client Phone</td>
            <td>Quotation Date</td>
            <td>Valid Until</td>
            <td>Status</td>
            <td>Item Count</td>
            <td style="text-align: right;">Total Amount</td>
        </tr>
        
        <!-- Data Rows -->
        <?php if (empty($quotations)): ?>
        <tr>
            <td colspan="10" style="text-align: center; font-style: italic;">No quotations found</td>
        </tr>
        <?php else: ?>
            <?php foreach ($quotations as $quotation): ?>
            <tr>
                <td><?php echo htmlspecialchars($quotation['quotation_no'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($quotation['title'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($quotation['client_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($quotation['client_email'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($quotation['client_phone'] ?? ''); ?></td>
                <td><?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?></td>
                <td>
                    <?php 
                    if ($quotation['validity_date']) {
                        echo date('d M Y', strtotime($quotation['validity_date']));
                        if ($quotation['is_expired']) {
                            echo ' (Expired)';
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($quotation['status']); ?></td>
                <td style="text-align: center;"><?php echo $quotation['item_count']; ?></td>
                <td class="amount"><?php echo number_format($quotation['total_amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- Summary Row -->
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <td colspan="8" style="text-align: right;">TOTAL:</td>
                <td style="text-align: center;"><?php echo count($quotations); ?> quotations</td>
                <td class="amount">
                    <?php 
                    $grand_total = array_sum(array_column($quotations, 'total_amount'));
                    echo number_format($grand_total, 2);
                    ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>
</body>
</html>
