<?php
/**
 * Payroll Module - Payslip PDF Generation
 * Generate professional payslip for employee
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$record_id) {
    die('Invalid record ID');
}

// Get record details
$record = get_payroll_record($conn, $record_id);
if (!$record) {
    die('Record not found');
}

// Get payroll details
$payroll = get_payroll_by_id($conn, $record['payroll_id']);

// Only allow PDF generation for Locked or Paid payrolls
if (!in_array($payroll['status'], ['Locked', 'Paid'])) {
    die('Payslip can only be generated for Locked or Paid payrolls');
}


// Get organization branding (handle missing table gracefully)
$org_name = 'Karyalay ERP';
$org_address = '';
$org_phone = '';
$org_email = '';
$logo_path = null;
$branding_table_exists = false;
$check_branding = $conn->query("SHOW TABLES LIKE 'branding'");
if ($check_branding && $check_branding->num_rows > 0) {
    $branding_table_exists = true;
}
if ($branding_table_exists) {
    $branding_query = "SELECT * FROM branding LIMIT 1";
    $branding_result = mysqli_query($conn, $branding_query);
    if ($branding_result && ($branding = mysqli_fetch_assoc($branding_result))) {
        $org_name = $branding['organization_name'] ?? $org_name;
        $org_address = $branding['address'] ?? '';
        $org_phone = $branding['phone'] ?? '';
        $org_email = $branding['email'] ?? '';
        $logo_path = !empty($branding['logo_path']) ? __DIR__ . '/../../uploads/' . $branding['logo_path'] : null;
    }
}

// Calculate components
$total_earnings = $record['base_salary'] + $record['allowances'] + $record['reimbursements'] + $record['bonus'];
$total_deductions = $record['deductions'] + $record['penalties'];
$attendance_percentage = $record['total_days'] > 0 ? round(($record['attendance_days'] / $record['total_days']) * 100, 2) : 0;

// Set content type
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?php echo htmlspecialchars($record['employee_name']); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #003581 0%, #002a66 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header .logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header .org-details {
            font-size: 11px;
            opacity: 0.9;
            margin-top: 10px;
        }
        
        .payslip-title {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 3px solid #003581;
        }
        
        .payslip-title h2 {
            color: #003581;
            font-size: 20px;
        }
        
        .payslip-title .period {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            background: #e3f2fd;
            color: #003581;
            padding: 8px 15px;
            font-weight: bold;
            margin-bottom: 10px;
            border-left: 4px solid #003581;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .info-table td {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-table td:first-child {
            font-weight: 600;
            width: 40%;
            color: #555;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .salary-table th {
            background: #f5f5f5;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #003581;
            font-weight: 600;
        }
        
        .salary-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .salary-table td:last-child {
            text-align: right;
            font-weight: 600;
        }
        
        .salary-table .subtotal-row {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .net-pay-box {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin: 25px 0;
        }
        
        .net-pay-box .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .net-pay-box .amount {
            font-size: 32px;
            font-weight: bold;
        }
        
        .net-pay-box .words {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 8px;
            font-style: italic;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            border-top: 2px solid #003581;
            font-size: 11px;
            color: #666;
        }
        
        .footer .note {
            margin-bottom: 15px;
            font-style: italic;
        }
        
        .footer .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .footer .signature-box {
            text-align: center;
        }
        
        .footer .signature-line {
            border-top: 1px solid #666;
            margin-top: 50px;
            padding-top: 5px;
            font-weight: 600;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #003581;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .print-button:hover {
            background: #002a66;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(0,0,0,0.03);
            z-index: -1;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print Payslip</button>
    
    <div class="watermark">PAYSLIP</div>
    
    <div class="payslip-container">
        <!-- Header -->
        <div class="header">
            <?php if ($logo_path && file_exists($logo_path)): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($org_name); ?></h1>
            <div class="org-details">
                <?php if ($org_address): ?>
                    <?php echo htmlspecialchars($org_address); ?><br>
                <?php endif; ?>
                <?php if ($org_phone): ?>
                    Phone: <?php echo htmlspecialchars($org_phone); ?>
                <?php endif; ?>
                <?php if ($org_email): ?>
                    | Email: <?php echo htmlspecialchars($org_email); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payslip Title -->
        <div class="payslip-title">
            <h2>📄 SALARY SLIP</h2>
            <div class="period">For the month of <?php echo format_month_display($record['month']); ?></div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Employee Information -->
            <div class="section">
                <div class="section-title">👤 EMPLOYEE DETAILS</div>
                <table class="info-table">
                    <tr>
                        <td>Employee Code</td>
                        <td><?php echo htmlspecialchars($record['employee_code']); ?></td>
                    </tr>
                    <tr>
                        <td>Employee Name</td>
                        <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                    </tr>
                    <tr>
                        <td>Department</td>
                        <td><?php echo htmlspecialchars($record['department']); ?></td>
                    </tr>
                    <tr>
                        <td>Designation</td>
                        <td><?php echo htmlspecialchars($record['designation']); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Attendance Information -->
            <div class="section">
                <div class="section-title">📅 ATTENDANCE SUMMARY</div>
                <table class="info-table">
                    <tr>
                        <td>Total Working Days</td>
                        <td><?php echo $record['total_days']; ?> days</td>
                    </tr>
                    <tr>
                        <td>Days Present</td>
                        <td><?php echo $record['attendance_days']; ?> days</td>
                    </tr>
                    <tr>
                        <td>Attendance Percentage</td>
                        <td><?php echo $attendance_percentage; ?>%</td>
                    </tr>
                </table>
            </div>
            
            <!-- Salary Breakdown -->
            <div class="section">
                <div class="section-title">💰 SALARY BREAKDOWN</div>
                <table class="salary-table">
                    <thead>
                        <tr>
                            <th>EARNINGS</th>
                            <th style="text-align: right;">AMOUNT (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Base Salary (Attendance Adjusted)</td>
                            <td><?php echo format_currency($record['base_salary']); ?></td>
                        </tr>
                        <tr>
                            <td>Allowances</td>
                            <td><?php echo format_currency($record['allowances']); ?></td>
                        </tr>
                        <tr>
                            <td>Reimbursements</td>
                            <td><?php echo format_currency($record['reimbursements']); ?></td>
                        </tr>
                        <?php if ($record['bonus'] > 0): ?>
                            <tr>
                                <td>Bonus</td>
                                <td><?php echo format_currency($record['bonus']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="subtotal-row">
                            <td>GROSS EARNINGS</td>
                            <td><?php echo format_currency($total_earnings); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <table class="salary-table">
                    <thead>
                        <tr>
                            <th>DEDUCTIONS</th>
                            <th style="text-align: right;">AMOUNT (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Standard Deductions (PF, ESI, etc.)</td>
                            <td><?php echo format_currency($record['deductions']); ?></td>
                        </tr>
                        <?php if ($record['penalties'] > 0): ?>
                            <tr>
                                <td>Penalties</td>
                                <td><?php echo format_currency($record['penalties']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="subtotal-row">
                            <td>TOTAL DEDUCTIONS</td>
                            <td><?php echo format_currency($total_deductions); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Net Pay -->
            <div class="net-pay-box">
                <div class="label">NET PAY</div>
                <div class="amount"><?php echo format_currency($record['net_pay']); ?></div>
                <div class="words"><?php echo convert_number_to_words($record['net_pay']); ?> Only</div>
            </div>
            
            <!-- Payment Information -->
            <?php if ($payroll['status'] === 'Paid' && $record['payment_date']): ?>
                <div class="section">
                    <div class="section-title">💳 PAYMENT DETAILS</div>
                    <table class="info-table">
                        <?php if ($record['payment_ref']): ?>
                            <tr>
                                <td>Payment Reference</td>
                                <td><?php echo htmlspecialchars($record['payment_ref']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Payment Date</td>
                            <td><?php echo date('d M Y', strtotime($record['payment_date'])); ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="note">
                <strong>Note:</strong> This is a computer-generated payslip and does not require a physical signature. 
                Please verify all details and contact HR for any discrepancies.
            </div>
            
            <div style="font-size: 10px; color: #999;">
                Generated on: <?php echo date('d M Y, h:i A'); ?> | 
                Payslip ID: <?php echo $record['id']; ?>
            </div>
            
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line">Employee Signature</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Authorized Signatory</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php

/**
 * Helper function to convert number to words (Indian system)
 */
function convert_number_to_words($number) {
    $number = (int)$number;
    
    if ($number == 0) return 'Zero Rupees';
    
    $words_array = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    );
    
    $digits_array = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    
    $number_string = (string)$number;
    $length = strlen($number_string);
    
    $result = '';
    $position = 0;
    
    // Process crores
    if ($length > 7) {
        $crores = (int)substr($number_string, 0, $length - 7);
        $result .= convert_two_digits($crores, $words_array) . ' Crore ';
        $number_string = substr($number_string, $length - 7);
        $length = 7;
    }
    
    // Process lakhs
    if ($length > 5) {
        $lakhs = (int)substr($number_string, 0, $length - 5);
        if ($lakhs > 0) {
            $result .= convert_two_digits($lakhs, $words_array) . ' Lakh ';
        }
        $number_string = substr($number_string, $length - 5);
        $length = 5;
    }
    
    // Process thousands
    if ($length > 3) {
        $thousands = (int)substr($number_string, 0, $length - 3);
        if ($thousands > 0) {
            $result .= convert_two_digits($thousands, $words_array) . ' Thousand ';
        }
        $number_string = substr($number_string, $length - 3);
        $length = 3;
    }
    
    // Process hundreds
    if ($length > 2) {
        $hundreds = (int)$number_string[0];
        if ($hundreds > 0) {
            $result .= $words_array[$hundreds] . ' Hundred ';
        }
        $number_string = substr($number_string, 1);
        $length = 2;
    }
    
    // Process remaining two digits
    if ($length > 0) {
        $last_two = (int)$number_string;
        if ($last_two > 0) {
            $result .= convert_two_digits($last_two, $words_array);
        }
    }
    
    return trim($result) . ' Rupees';
}

function convert_two_digits($number, $words_array) {
    if ($number < 20) {
        return $words_array[$number];
    }
    
    $tens = (int)($number / 10) * 10;
    $units = $number % 10;
    
    return $words_array[$tens] . ($units > 0 ? ' ' . $words_array[$units] : '');
}
?>
