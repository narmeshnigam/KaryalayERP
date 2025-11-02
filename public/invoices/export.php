<?php
/**
 * Invoices Module - Export Filtered List to Excel
 * Exports invoice list with applied filters
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Build filter query
$where_clauses = [];
$params = [];
$types = '';

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clauses[] = "(i.invoice_no LIKE ? OR c.name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

// Status filter
if (!empty($_GET['status'])) {
    $where_clauses[] = "i.status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

// Client filter
if (!empty($_GET['client_id'])) {
    $where_clauses[] = "i.client_id = ?";
    $params[] = (int)$_GET['client_id'];
    $types .= 'i';
}

// Date range filters
if (!empty($_GET['date_from'])) {
    $where_clauses[] = "i.issue_date >= ?";
    $params[] = $_GET['date_from'];
    $types .= 's';
}
if (!empty($_GET['date_to'])) {
    $where_clauses[] = "i.issue_date <= ?";
    $params[] = $_GET['date_to'];
    $types .= 's';
}

// Overdue filter
if (!empty($_GET['overdue']) && $_GET['overdue'] === '1') {
    $where_clauses[] = "i.due_date < CURDATE() AND i.status NOT IN ('Paid', 'Cancelled')";
}

// Build query
$sql = "SELECT i.*, c.name as client_name, u.username as created_by_name
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    LEFT JOIN users u ON i.created_by = u.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY i.created_at DESC";

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$invoices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Set headers for Excel download
$filename = 'Invoices_Export_' . date('Y-m-d_His') . '.xls';
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
        h1 { color: #003581; font-size: 20px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #003581; color: white; padding: 10px; text-align: left; font-weight: bold; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .status { padding: 3px 8px; border-radius: 3px; display: inline-block; font-size: 11px; }
        .status-draft { background-color: #6c757d; color: white; }
        .status-issued { background-color: #17a2b8; color: white; }
        .status-partially-paid { background-color: #ffc107; color: black; }
        .status-paid { background-color: #28a745; color: white; }
        .status-overdue { background-color: #dc3545; color: white; }
        .status-cancelled { background-color: #343a40; color: white; }
    </style>
</head>
<body>
    <h1>📄 Invoices Export</h1>
    
    <p><strong>Export Date:</strong> <?php echo date('d M Y, h:i A'); ?></p>
    <p><strong>Total Records:</strong> <?php echo count($invoices); ?></p>
    
    <?php if (!empty($_GET['search'])): ?>
        <p><strong>Search:</strong> <?php echo htmlspecialchars($_GET['search']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_GET['status'])): ?>
        <p><strong>Status Filter:</strong> <?php echo htmlspecialchars($_GET['status']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
        <p><strong>Date Range:</strong> 
            <?php echo !empty($_GET['date_from']) ? date('d M Y', strtotime($_GET['date_from'])) : 'Start'; ?> to 
            <?php echo !empty($_GET['date_to']) ? date('d M Y', strtotime($_GET['date_to'])) : 'End'; ?>
        </p>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Invoice No</th>
                <th>Client</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Currency</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Total Amount</th>
                <th class="text-right">Paid Amount</th>
                <th class="text-right">Pending Amount</th>
                <th>Payment Terms</th>
                <th>Created By</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="16" style="text-align: center; padding: 30px; color: #6c757d;">
                        No invoices found matching the selected filters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $index => $invoice): ?>
                    <?php 
                    $currency_symbol = $invoice['currency'] === 'INR' ? '₹' : '$';
                    $status_class = 'status-' . strtolower(str_replace(' ', '-', $invoice['status']));
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                        <td class="text-center"><?php echo date('d M Y', strtotime($invoice['issue_date'])); ?></td>
                        <td class="text-center">
                            <?php 
                            if ($invoice['due_date']) {
                                echo date('d M Y', strtotime($invoice['due_date']));
                                if (strtotime($invoice['due_date']) < time() && $invoice['status'] !== 'Paid') {
                                    echo ' (OVERDUE)';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="text-center">
                            <span class="status <?php echo $status_class; ?>">
                                <?php echo $invoice['status']; ?>
                            </span>
                        </td>
                        <td class="text-center"><?php echo htmlspecialchars($invoice['currency']); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($invoice['subtotal'], 2); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($invoice['tax_amount'], 2); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($invoice['discount_amount'], 2); ?></td>
                        <td class="text-right"><strong><?php echo $currency_symbol . number_format($invoice['total_amount'], 2); ?></strong></td>
                        <td class="text-right" style="color: #28a745;"><?php echo $currency_symbol . number_format($invoice['paid_amount'], 2); ?></td>
                        <td class="text-right" style="color: <?php echo $invoice['pending_amount'] > 0 ? '#dc3545' : '#28a745'; ?>;">
                            <strong><?php echo $currency_symbol . number_format($invoice['pending_amount'], 2); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($invoice['payment_terms'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($invoice['created_by_name']); ?></td>
                        <td class="text-center"><?php echo date('d M Y, h:i A', strtotime($invoice['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if (!empty($invoices)): ?>
        <!-- Summary Statistics -->
        <?php
        $total_invoices = count($invoices);
        $total_amount = array_sum(array_column($invoices, 'total_amount'));
        $total_paid = array_sum(array_column($invoices, 'paid_amount'));
        $total_pending = array_sum(array_column($invoices, 'pending_amount'));
        
        $status_counts = [];
        foreach ($invoices as $inv) {
            $status_counts[$inv['status']] = ($status_counts[$inv['status']] ?? 0) + 1;
        }
        ?>
        
        <div style="margin-top: 40px; border-top: 2px solid #003581; padding-top: 20px;">
            <h2 style="color: #003581; font-size: 16px;">Summary Statistics</h2>
            
            <table style="width: 50%;">
                <tr>
                    <td style="border: none;"><strong>Total Invoices:</strong></td>
                    <td style="border: none; text-align: right;"><?php echo $total_invoices; ?></td>
                </tr>
                <tr>
                    <td style="border: none;"><strong>Total Invoice Amount:</strong></td>
                    <td style="border: none; text-align: right;">₹<?php echo number_format($total_amount, 2); ?></td>
                </tr>
                <tr>
                    <td style="border: none;"><strong>Total Paid Amount:</strong></td>
                    <td style="border: none; text-align: right; color: #28a745;">₹<?php echo number_format($total_paid, 2); ?></td>
                </tr>
                <tr>
                    <td style="border: none;"><strong>Total Pending Amount:</strong></td>
                    <td style="border: none; text-align: right; color: #dc3545;">₹<?php echo number_format($total_pending, 2); ?></td>
                </tr>
            </table>
            
            <h3 style="color: #003581; font-size: 14px; margin-top: 20px;">Status Breakdown</h3>
            <table style="width: 40%;">
                <?php foreach ($status_counts as $status => $count): ?>
                    <tr>
                        <td style="border: none;"><?php echo $status; ?>:</td>
                        <td style="border: none; text-align: right;"><?php echo $count; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px; text-align: center; font-size: 11px; color: #666;">
        <p>This report was generated from KaryalayERP - Invoices Module</p>
    </div>
</body>
</html>
