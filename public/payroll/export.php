<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'employees.view');

$payroll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = $_GET['format'] ?? '';

if ($payroll_id && $format) {
    $payroll = get_payroll_by_id($conn, $payroll_id);
    $items = get_payroll_items($conn, $payroll_id);
    
    if (!$payroll) {
        die('Payroll not found');
    }
    
    if ($format === 'csv') {
        // CSV Export for Bank Transfer
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payroll_' . $payroll_id . '_bank_transfer.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Employee Name', 'Employee Code', 'Department', 'Account Number', 'Amount', 'Reference']);
        
        foreach ($items as $item) {
            fputcsv($output, [
                $item['employee_name'],
                $item['employee_code'],
                $item['department'],
                $item['account_number'] ?? 'N/A',
                number_format($item['payable'], 2, '.', ''),
                $payroll['transaction_ref'] ?? 'N/A'
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    if ($format === 'excel') {
        // Simple Excel Export (HTML Table)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="payroll_' . $payroll_id . '_report.xls"');
        
        echo '<html><head><meta charset="utf-8"></head><body>';
        echo '<h2>Payroll Report #' . $payroll_id . '</h2>';
        echo '<p><strong>Type:</strong> ' . $payroll['payroll_type'] . '</p>';
        echo '<p><strong>Month:</strong> ' . get_month_name($payroll['month_year']) . '</p>';
        echo '<p><strong>Total Amount:</strong> ' . format_currency($payroll['total_amount']) . '</p>';
        echo '<p><strong>Status:</strong> ' . $payroll['status'] . '</p>';
        echo '<br>';
        
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        echo '<thead>';
        echo '<tr style="background-color:#003581;color:white">';
        echo '<th>Employee</th><th>Code</th><th>Department</th><th>Base</th><th>Allowances</th><th>Deductions</th><th>Payable</th><th>Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['employee_name']) . '</td>';
            echo '<td>' . htmlspecialchars($item['employee_code']) . '</td>';
            echo '<td>' . htmlspecialchars($item['department']) . '</td>';
            echo '<td>' . number_format($item['base_salary'] ?? 0, 2) . '</td>';
            echo '<td>' . number_format($item['allowances'] ?? 0, 2) . '</td>';
            echo '<td>' . number_format($item['deductions'] ?? 0, 2) . '</td>';
            echo '<td>' . number_format($item['payable'], 2) . '</td>';
            echo '<td>' . $item['status'] . '</td>';
            echo '</tr>';
        }
        
        echo '<tr style="background-color:#f0f0f0;font-weight:bold">';
        echo '<td colspan="6">Total</td>';
        echo '<td>' . number_format($payroll['total_amount'], 2) . '</td>';
        echo '<td></td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</body></html>';
        exit;
    }
    
    if ($format === 'pdf') {
        // Simple PDF generation using HTML2PDF approach
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="payroll_' . $payroll_id . '_report.pdf"');
        
        // For now, redirect to a print-friendly HTML page
        // In production, integrate with TCPDF or mPDF library
        die('PDF export requires additional library. Please use Excel export for now.');
    }
}

$page_title = 'Export Payroll - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.export-card{background:white;padding:25px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);margin-bottom:20px;border-left:4px solid #003581}
.export-card h4{color:#003581;margin-bottom:10px}
.export-btn{display:inline-block;padding:10px 20px;background:#003581;color:white;text-decoration:none;border-radius:6px;margin-right:10px;margin-top:10px}
.export-btn:hover{background:#002560;color:white}
.export-btn.csv{background:#28a745}
.export-btn.csv:hover{background:#218838}
</style>

<div class="main-wrapper">
<div class="main-content">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px">
    <h1 style="color:#003581;margin:0">Export Payroll Reports</h1>
    <a href="index.php" class="btn btn-outline-secondary"> Back to Dashboard</a>
</div>

<?php if ($payroll_id): ?>
    <?php 
    $payroll = get_payroll_by_id($conn, $payroll_id);
    if ($payroll): 
    ?>
    <div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:30px">
        <h3>Payroll #<?php echo $payroll_id; ?> - <?php echo get_month_name($payroll['month_year']); ?></h3>
        <p style="margin:0;color:#666">
            Type: <strong><?php echo $payroll['payroll_type']; ?></strong> | 
            Status: <span class="badge badge-<?php echo strtolower($payroll['status']); ?>"><?php echo $payroll['status']; ?></span> | 
            Total: <strong><?php echo format_currency($payroll['total_amount']); ?></strong>
        </p>
    </div>
    
    <div class="export-card">
        <h4> Salary Register (Excel)</h4>
        <p style="color:#666">Complete payroll report with employee details, salary breakdown, and totals in Excel format</p>
        <a href="export.php?id=<?php echo $payroll_id; ?>&format=excel" class="export-btn">Download Excel Report</a>
    </div>
    
    <div class="export-card">
        <h4> Bank Transfer Sheet (CSV)</h4>
        <p style="color:#666">Bank-ready CSV file with employee names, account details, and amounts for bulk transfer</p>
        <a href="export.php?id=<?php echo $payroll_id; ?>&format=csv" class="export-btn csv">Download CSV for Bank</a>
    </div>
    
    <div class="export-card">
        <h4> PDF Report (Coming Soon)</h4>
        <p style="color:#666">Professional PDF report with company branding, suitable for printing and records</p>
        <button class="export-btn" style="opacity:0.5;cursor:not-allowed" disabled>PDF Export (Coming Soon)</button>
        <small style="display:block;margin-top:10px;color:#999">Requires TCPDF/mPDF library integration</small>
    </div>
    
    <?php else: ?>
        <div class="alert alert-danger">Payroll not found.</div>
    <?php endif; ?>

<?php else: ?>
    <!-- Export Options Dashboard -->
    <div class="export-card">
        <h4> Monthly Salary Register</h4>
        <p style="color:#666">Export complete salary payrolls for a specific month with all employee details</p>
        <form method="GET" action="export.php" style="display:flex;gap:10px;align-items:end">
            <div>
                <label style="display:block;margin-bottom:5px">Select Month</label>
                <input type="month" name="month" class="form-control" value="<?php echo date('Y-m'); ?>" required>
            </div>
            <div>
                <label style="display:block;margin-bottom:5px">Format</label>
                <select name="format" class="form-control" required>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Export</button>
        </form>
    </div>
    
    <div class="export-card">
        <h4> Reimbursement Register</h4>
        <p style="color:#666">Export all reimbursement payrolls with claim details and payment status</p>
        <form method="GET" action="export.php" style="display:flex;gap:10px;align-items:end">
            <div>
                <label style="display:block;margin-bottom:5px">Date Range</label>
                <input type="month" name="from_month" class="form-control" placeholder="From" required>
            </div>
            <div>
                <label style="display:block;margin-bottom:5px">&nbsp;</label>
                <input type="month" name="to_month" class="form-control" placeholder="To" required>
            </div>
            <div>
                <label style="display:block;margin-bottom:5px">Format</label>
                <select name="format" class="form-control" required>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Export</button>
        </form>
    </div>
    
    <div class="export-card">
        <h4> Quick Export by Payroll ID</h4>
        <p style="color:#666">Enter a specific payroll ID to export its details</p>
        <form method="GET" action="export.php" style="display:flex;gap:10px;align-items:end">
            <div>
                <label style="display:block;margin-bottom:5px">Payroll ID</label>
                <input type="number" name="id" class="form-control" placeholder="Enter ID" required>
            </div>
            <button type="submit" class="btn btn-primary">View Export Options</button>
        </form>
    </div>
<?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>