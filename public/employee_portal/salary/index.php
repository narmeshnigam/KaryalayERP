<?php
/**
 * Salary Viewer - Employee portal listing page.
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/module_dependencies.php';
require_once __DIR__ . '/../../salary/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check salary module prerequisites
$conn_check = createConnection(true);
if ($conn_check) {
    $prereq_check = get_prerequisite_check_result($conn_check, 'salary');
    if (!$prereq_check['allowed']) {
        closeConnection($conn_check);
        display_prerequisite_error('salary', $prereq_check['missing_modules']);
    }
    closeConnection($conn_check);
}

$user_role = $_SESSION['role'] ?? 'employee';
$page_title = 'My Salary Records - ' . APP_NAME;

require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    exit;
}

if (!salary_table_exists($conn)) {
    closeConnection($conn);
    require_once __DIR__ . '/../../salary/onboarding.php';
    exit;
}

$current_employee_id = salary_current_employee_id($conn, (int) $_SESSION['user_id']);
if (!$current_employee_id) {
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="card" style="max-width:720px;margin:0 auto;">';
    echo '<h2 style="margin-top:0;color:#003581;">Employee profile missing</h2>';
    echo '<p>We could not find an employee record linked to your user account. Please contact HR or the administrator.</p>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer_sidebar.php';
    closeConnection($conn);
    exit;
}

$defaults = salary_month_range_default();
$from_month = isset($_GET['from_month']) ? trim($_GET['from_month']) : $defaults[0];
$to_month = isset($_GET['to_month']) ? trim($_GET['to_month']) : $defaults[1];

$validate_month = static function (string $value, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : $fallback;
};

$from_month = $validate_month($from_month, $defaults[0]);
$to_month = $validate_month($to_month, $defaults[1]);

$where = ['sr.employee_id = ?', 'sr.month BETWEEN ? AND ?'];
$params = [$current_employee_id, $from_month, $to_month];
$types = 'iss';

$sql = "SELECT sr.id, sr.month, sr.base_salary, sr.allowances, sr.deductions, sr.net_pay, sr.slip_path, sr.is_locked, sr.created_at, sr.updated_at,
               uploader.employee_code AS uploader_code, uploader.first_name AS uploader_first, uploader.last_name AS uploader_last
        FROM salary_records sr
        LEFT JOIN employees uploader ON sr.uploaded_by = uploader.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY sr.month DESC";

$stmt = mysqli_prepare($conn, $sql);
$records = [];
if ($stmt) {
    salary_stmt_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$summary_sql = "SELECT COUNT(*) AS record_count,
                       SUM(net_pay) AS total_net,
                       SUM(allowances) AS total_allowances,
                       SUM(deductions) AS total_deductions
                FROM salary_records
                WHERE employee_id = ? AND month BETWEEN ? AND ?";
$summary_stmt = mysqli_prepare($conn, $summary_sql);
$totals = ['record_count' => 0, 'total_net' => 0.0, 'total_allowances' => 0.0, 'total_deductions' => 0.0];
if ($summary_stmt) {
    mysqli_stmt_bind_param($summary_stmt, 'iss', $current_employee_id, $from_month, $to_month);
    mysqli_stmt_execute($summary_stmt);
    $res = mysqli_stmt_get_result($summary_stmt);
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $totals['record_count'] = (int) ($row['record_count'] ?? 0);
            $totals['total_net'] = (float) ($row['total_net'] ?? 0);
            $totals['total_allowances'] = (float) ($row['total_allowances'] ?? 0);
            $totals['total_deductions'] = (float) ($row['total_deductions'] ?? 0);
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($summary_stmt);
}

closeConnection($conn);
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;">
                <div>
                    <h1>💰 My Salary Records</h1>
                    <p>Review your monthly payouts, allowances, and deductions.</p>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
            <div class="card" style="background:linear-gradient(135deg,#003581 0%,#0056b3 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo $totals['record_count']; ?>
                </div>
                <div>Salary records in range</div>
            </div>
            <div class="card" style="background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo salary_format_currency($totals['total_net']); ?>
                </div>
                <div>Total net pay</div>
            </div>
            <div class="card" style="background:linear-gradient(135deg,#17a2b8 0%,#138496 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo salary_format_currency($totals['total_allowances']); ?>
                </div>
                <div>Total allowances</div>
            </div>
            <div class="card" style="background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);color:#fff;text-align:center;padding:20px;">
                <div style="font-size:28px;font-weight:700;margin-bottom:6px;">
                    <?php echo salary_format_currency($totals['total_deductions']); ?>
                </div>
                <div>Total deductions</div>
            </div>
        </div>

        <?php echo flash_render(); ?>

        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin-top:0;color:#003581;">Filter by month range</h3>
            <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label for="from_month">From</label>
                    <input type="month" id="from_month" name="from_month" class="form-control" value="<?php echo htmlspecialchars($from_month, ENT_QUOTES); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="to_month">To</label>
                    <input type="month" id="to_month" name="to_month" class="form-control" value="<?php echo htmlspecialchars($to_month, ENT_QUOTES); ?>">
                </div>
                <div>
                    <button type="submit" class="btn" style="width:100%;">Apply filters</button>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary" style="width:100%;text-align:center;">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
                <h3 style="margin:0;color:#003581;">Salary slips (<?php echo count($records); ?>)</h3>
            </div>

            <?php if (empty($records)): ?>
                <div class="alert alert-info" style="margin:0;">No salary data available for the selected range.</div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Month</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Base</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Allowances</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Deductions</th>
                                <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Net Pay</th>
                                <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Uploaded by</th>
                                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Status</th>
                                <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <?php
                                    $status = $record['is_locked'] ? '<span style="padding:4px 10px;border-radius:12px;background:#d4edda;color:#155724;font-size:12px;">Finalised</span>' : '<span style="padding:4px 10px;border-radius:12px;background:#ffeeba;color:#856404;font-size:12px;">Draft</span>';
                                    $has_slip = !empty($record['slip_path']);
                                    $download_link = $has_slip ? 'view.php?id=' . (int) $record['id'] . '&download=1' : null;
                                    $uploaded_by = salary_format_employee($record['uploader_code'] ?? null, $record['uploader_first'] ?? null, $record['uploader_last'] ?? null);
                                ?>
                                <tr style="border-bottom:1px solid #e1e8ed;">
                                    <td style="padding:12px;white-space:nowrap;font-weight:600;color:#1b2a57;">
                                        <?php echo salary_format_month_label($record['month']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;">
                                        <?php echo salary_format_currency($record['base_salary']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;">
                                        <?php echo salary_format_currency($record['allowances']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;">
                                        <?php echo salary_format_currency($record['deductions']); ?>
                                    </td>
                                    <td style="padding:12px;text-align:right;font-weight:600;color:#155724;">
                                        <?php echo salary_format_currency($record['net_pay']); ?>
                                    </td>
                                    <td style="padding:12px;">
                                        <?php echo $uploaded_by; ?>
                                    </td>
                                    <td style="padding:12px;text-align:center;">
                                        <?php echo $status; ?>
                                    </td>
                                    <td style="padding:12px;text-align:center;white-space:nowrap;">
                                        <a href="view.php?id=<?php echo (int) $record['id']; ?>" class="btn btn-accent" style="padding:6px 14px;font-size:13px;">Details</a>
                                        <?php if ($download_link): ?>
                                            <a href="<?php echo htmlspecialchars($download_link, ENT_QUOTES); ?>" class="btn" style="padding:6px 14px;font-size:13px;background:#17a2b8;color:#fff;">Download PDF</a>
                                        <?php else: ?>
                                            <span style="color:#6c757d;font-size:12px;">No slip</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
