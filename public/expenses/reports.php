<?php
/**
 * Expense Tracker - Analytics & Reports
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'user';
if (!in_array($user_role, ['admin', 'manager'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

$page_title = 'Expense Reports - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/date_range_selector.php';

$conn = createConnection(true);
if (!$conn) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-error">Unable to connect to the database.</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

function tableExists($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

function refValues(array &$arr)
{
    $refs = [];
    foreach ($arr as $key => &$value) {
        $refs[$key] = &$value;
    }
    return $refs;
}

function fetchAllRows($conn, $sql, $types = '', array $params = [])
{
    $rows = [];
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $rows;
    }
    if ($types !== '' && !empty($params)) {
        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = $params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
    }
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function fetchSingleRow($conn, $sql, $types = '', array $params = [])
{
    $rows = fetchAllRows($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

if (!tableExists($conn, 'office_expenses')) {
    closeConnection($conn);
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="card" style="max-width:760px;margin:0 auto;">';
    echo '<h2 style="margin-top:0;color:#003581;">Expense Tracker module not ready</h2>';
    echo '<p>The <code>office_expenses</code> table is missing. Please run the setup script.</p>';
    echo '<a href="index.php" class="btn" style="margin-top:20px;">← Back</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}

$category_options = [];
$category_sql = 'SELECT DISTINCT category FROM office_expenses ORDER BY category';
if ($category_res = mysqli_query($conn, $category_sql)) {
    while ($row = mysqli_fetch_assoc($category_res)) {
        if (!empty($row['category'])) {
            $category_options[] = $row['category'];
        }
    }
}

$payment_options = [];
$payment_sql = 'SELECT DISTINCT payment_mode FROM office_expenses ORDER BY payment_mode';
if ($payment_res = mysqli_query($conn, $payment_sql)) {
    while ($row = mysqli_fetch_assoc($payment_res)) {
        if (!empty($row['payment_mode'])) {
            $payment_options[] = $row['payment_mode'];
        }
    }
}

$today = date('Y-m-d');
$default_start = date('Y-01-01');

$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : $default_start;
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : $today;
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$payment_filter = isset($_GET['payment_mode']) ? trim($_GET['payment_mode']) : '';
$vendor_filter = isset($_GET['vendor']) ? trim($_GET['vendor']) : '';

$errors = [];
if ($from_date === '' || $to_date === '') {
    $errors[] = 'Please choose a valid date range.';
} elseif ($from_date > $to_date) {
    $errors[] = 'From date cannot be later than the To date.';
}

$where = ['e.date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];
$types = 'ss';

if ($category_filter !== '') {
    $where[] = 'e.category = ?';
    $params[] = $category_filter;
    $types .= 's';
}
if ($payment_filter !== '') {
    $where[] = 'e.payment_mode = ?';
    $params[] = $payment_filter;
    $types .= 's';
}
if ($vendor_filter !== '') {
    $where[] = 'e.vendor_name LIKE ?';
    $params[] = '%' . $vendor_filter . '%';
    $types .= 's';
}
$where_clause = implode(' AND ', $where);

$summary = [];
$category_breakdown = [];
$payment_breakdown = [];
$vendor_breakdown = [];
$monthly_trend = [];

if (empty($errors)) {
    $summary_sql = "SELECT SUM(e.amount) AS total_amount, COUNT(*) AS total_entries, MAX(e.amount) AS max_amount, MIN(e.amount) AS min_amount FROM office_expenses e WHERE $where_clause";
    $summary = fetchSingleRow($conn, $summary_sql, $types, $params);

    $category_sql = "SELECT e.category, SUM(e.amount) AS total_amount, COUNT(*) AS entries FROM office_expenses e WHERE $where_clause GROUP BY e.category ORDER BY total_amount DESC";
    $category_breakdown = fetchAllRows($conn, $category_sql, $types, $params);

    $payment_breakdown_sql = "SELECT e.payment_mode, SUM(e.amount) AS total_amount, COUNT(*) AS entries FROM office_expenses e WHERE $where_clause GROUP BY e.payment_mode ORDER BY total_amount DESC";
    $payment_breakdown = fetchAllRows($conn, $payment_breakdown_sql, $types, $params);

    $vendor_breakdown_sql = "SELECT COALESCE(NULLIF(TRIM(e.vendor_name), ''), 'Unspecified') AS vendor_name, SUM(e.amount) AS total_amount, COUNT(*) AS entries FROM office_expenses e WHERE $where_clause GROUP BY vendor_name ORDER BY total_amount DESC LIMIT 5";
    $vendor_breakdown = fetchAllRows($conn, $vendor_breakdown_sql, $types, $params);

    $daily_trend_sql = "SELECT e.date AS period_key, DATE_FORMAT(e.date, '%d %b') AS period_label, SUM(e.amount) AS total_amount, COUNT(*) AS entries FROM office_expenses e WHERE $where_clause GROUP BY e.date ORDER BY e.date";
    $daily_trend = fetchAllRows($conn, $daily_trend_sql, $types, $params);

    $expense_list_sql = "SELECT e.id, e.date, e.category, e.vendor_name, e.description, e.amount, e.payment_mode, e.receipt_file, CONCAT(COALESCE(emp.first_name, ''), ' ', COALESCE(emp.last_name, '')) AS added_by_name FROM office_expenses e LEFT JOIN employees emp ON e.added_by = emp.id WHERE $where_clause ORDER BY e.date DESC, e.id DESC";
    $expense_list = fetchAllRows($conn, $expense_list_sql, $types, $params);
}

closeConnection($conn);

$total_amount = isset($summary['total_amount']) ? (float) $summary['total_amount'] : 0.0;
$total_entries = isset($summary['total_entries']) ? (int) $summary['total_entries'] : 0;
$max_amount = isset($summary['max_amount']) ? (float) $summary['max_amount'] : 0.0;
$min_amount = isset($summary['min_amount']) ? (float) $summary['min_amount'] : 0.0;
$average_amount = $total_entries > 0 ? $total_amount / $total_entries : 0.0;
?>
<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>📊 Expense Insights</h1>
                    <p>Analyse spending patterns, top vendors, and payment mix.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="index.php" class="btn btn-secondary">← Back to Expenses</a>
                    <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn">📥 Export CSV</a>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-error" style="margin-bottom:20px;">
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:24px;padding:24px;">
            <form method="get" style="display:grid;gap:20px;">
                <?php renderDateRangeSelector($from_date, $to_date, false); ?>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
                    <div class="form-group" style="margin:0;">
                        <label for="category">Category</label>
                        <select class="form-control" name="category" id="category">
                            <option value="">All categories</option>
                            <?php foreach ($category_options as $option) : ?>
                                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>" <?php echo ($option === $category_filter) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option, ENT_QUOTES); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="payment_mode">Payment mode</label>
                        <select class="form-control" name="payment_mode" id="payment_mode">
                            <option value="">All payment modes</option>
                            <?php foreach ($payment_options as $option) : ?>
                                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>" <?php echo ($option === $payment_filter) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option, ENT_QUOTES); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="vendor">Vendor / Payee</label>
                        <input type="text" class="form-control" name="vendor" id="vendor" value="<?php echo htmlspecialchars($vendor_filter, ENT_QUOTES); ?>" placeholder="Search vendor">
                    </div>
                </div>
                
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <a href="reports.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <?php if (empty($errors)) : ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-bottom:24px;">
                <div class="card" style="padding:20px;border-left:4px solid #003581;">
                    <div style="color:#6c757d;font-size:13px;margin-bottom:4px;">Total Spend</div>
                    <div style="font-size:28px;font-weight:700;color:#003581;">₹ <?php echo number_format($total_amount, 2); ?></div>
                </div>
                <div class="card" style="padding:20px;border-left:4px solid #28a745;">
                    <div style="color:#6c757d;font-size:13px;margin-bottom:4px;">Number of Entries</div>
                    <div style="font-size:28px;font-weight:700;color:#28a745;"><?php echo number_format($total_entries); ?></div>
                </div>
                <div class="card" style="padding:20px;border-left:4px solid #faa718;">
                    <div style="color:#6c757d;font-size:13px;margin-bottom:4px;">Average Expense</div>
                    <div style="font-size:28px;font-weight:700;color:#faa718;">₹ <?php echo number_format($average_amount, 2); ?></div>
                </div>
                <div class="card" style="padding:20px;border-left:4px solid #dc3545;">
                    <div style="color:#6c757d;font-size:13px;margin-bottom:4px;">Highest Expense</div>
                    <div style="font-size:28px;font-weight:700;color:#dc3545;">₹ <?php echo number_format($max_amount, 2); ?></div>
                </div>
            </div>

            <?php if (!empty($daily_trend)) : ?>
            <div class="card" style="padding:24px;margin-bottom:24px;">
                <h3 style="margin-top:0;margin-bottom:20px;color:#003581;">Daily Expense Trend</h3>
                <canvas id="expenseTrendChart" style="max-height:350px;"></canvas>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                const ctx = document.getElementById('expenseTrendChart');
                const chartData = {
                    labels: <?php echo json_encode(array_column($daily_trend, 'period_label')); ?>,
                    datasets: [{
                        label: 'Daily Expenses (₹)',
                        data: <?php echo json_encode(array_map(function($row) { return (float)$row['total_amount']; }, $daily_trend)); ?>,
                        borderColor: '#003581',
                        backgroundColor: 'rgba(0, 53, 129, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#003581',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                };

                new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 13,
                                        family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                    },
                                    padding: 15,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                cornerRadius: 6,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        return 'Amount: ₹' + context.parsed.y.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value.toLocaleString('en-IN');
                                    },
                                    font: {
                                        size: 12
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 12
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            </script>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(450px,1fr));gap:24px;margin-bottom:24px;">
                <div class="card" style="padding:32px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;color:#003581;font-size:18px;font-weight:600;">Spend by Category</h3>
                            <p style="margin:0;color:#6c757d;font-size:13px;">Distribution across expense categories</p>
                        </div>
                    </div>
                    <?php if (!empty($category_breakdown)) : ?> 
                        <div style="display:flex;gap:32px;align-items:center;margin-bottom:24px;">
                            <div style="flex-shrink:0;">
                                <canvas id="categoryChart" style="width:180px;height:180px;"></canvas>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;flex-direction:column;gap:12px;">
                                    <?php 
                                    $category_colors = ['#003581', '#faa718', '#28a745', '#dc3545', '#17a2b8', '#6c757d', '#e83e8c'];
                                    foreach ($category_breakdown as $idx => $row) : 
                                        $percentage = $total_amount > 0 ? (((float)$row['total_amount'] / $total_amount) * 100) : 0;
                                    ?>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:12px;height:12px;border-radius:2px;background:<?php echo $category_colors[$idx % count($category_colors)]; ?>;flex-shrink:0;"></div>
                                            <div style="flex:1;min-width:0;">
                                                <div style="font-size:13px;font-weight:600;color:#1b2a57;margin-bottom:2px;"><?php echo htmlspecialchars($row['category'] ?: 'Uncategorised', ENT_QUOTES); ?></div>
                                                <div style="font-size:12px;color:#6c757d;">₹<?php echo number_format((float) $row['total_amount'], 2); ?> · <?php echo number_format($percentage, 1); ?>%</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info" style="margin:0;">No expenses found for the selected filters.</div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding:32px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;color:#003581;font-size:18px;font-weight:600;">Payment Mode Split</h3>
                            <p style="margin:0;color:#6c757d;font-size:13px;">Breakdown by payment method</p>
                        </div>
                    </div>
                    <?php if (!empty($payment_breakdown)) : ?>
                        <div style="display:flex;gap:32px;align-items:center;margin-bottom:24px;">
                            <div style="flex-shrink:0;">
                                <canvas id="paymentChart" style="width:180px;height:180px;"></canvas>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;flex-direction:column;gap:12px;">
                                    <?php 
                                    $payment_colors = ['#28a745', '#003581', '#faa718', '#dc3545', '#17a2b8', '#6c757d'];
                                    foreach ($payment_breakdown as $idx => $row) : 
                                        $percentage = $total_amount > 0 ? (((float)$row['total_amount'] / $total_amount) * 100) : 0;
                                    ?>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:12px;height:12px;border-radius:2px;background:<?php echo $payment_colors[$idx % count($payment_colors)]; ?>;flex-shrink:0;"></div>
                                            <div style="flex:1;min-width:0;">
                                                <div style="font-size:13px;font-weight:600;color:#1b2a57;margin-bottom:2px;"><?php echo htmlspecialchars($row['payment_mode'] ?: 'Unspecified', ENT_QUOTES); ?></div>
                                                <div style="font-size:12px;color:#6c757d;">₹<?php echo number_format((float) $row['total_amount'], 2); ?> · <?php echo number_format($percentage, 1); ?>%</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info" style="margin:0;">No expenses found for the selected filters.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(450px,1fr));gap:24px;">
                <div class="card" style="padding:32px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;color:#003581;font-size:18px;font-weight:600;">Top Vendors</h3>
                            <p style="margin:0;color:#6c757d;font-size:13px;">Highest spending by vendor</p>
                        </div>
                    </div>
                    <?php if (!empty($vendor_breakdown)) : ?>
                        <div style="display:flex;gap:32px;align-items:center;margin-bottom:24px;">
                            <div style="flex-shrink:0;">
                                <canvas id="vendorChart" style="width:180px;height:180px;"></canvas>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;flex-direction:column;gap:12px;">
                                    <?php 
                                    $vendor_colors = ['#faa718', '#003581', '#28a745', '#dc3545', '#17a2b8'];
                                    foreach ($vendor_breakdown as $idx => $row) : 
                                        $percentage = $total_amount > 0 ? (((float)$row['total_amount'] / $total_amount) * 100) : 0;
                                    ?>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:12px;height:12px;border-radius:2px;background:<?php echo $vendor_colors[$idx % count($vendor_colors)]; ?>;flex-shrink:0;"></div>
                                            <div style="flex:1;min-width:0;">
                                                <div style="font-size:13px;font-weight:600;color:#1b2a57;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($row['vendor_name'], ENT_QUOTES); ?></div>
                                                <div style="font-size:12px;color:#6c757d;">₹<?php echo number_format((float) $row['total_amount'], 2); ?> · <?php echo number_format($percentage, 1); ?>%</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info" style="margin:0;">No vendor insights available for the selected filters.</div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding:32px;">
                    <div style="margin-bottom:24px;">
                        <h3 style="margin:0 0 4px 0;color:#003581;font-size:18px;font-weight:600;">Daily Breakdown</h3>
                        <p style="margin:0;color:#6c757d;font-size:13px;">Day-wise expense summary</p>
                    </div>
                    <?php if (!empty($daily_trend)) : ?>
                        <div class="table-responsive" style="max-height:380px;overflow-y:auto;border:1px solid #e1e8ed;border-radius:6px;">
                            <table class="table" style="margin:0;">
                                <thead style="position:sticky;top:0;background:#f8f9fa;z-index:1;">
                                    <tr>
                                        <th style="padding:12px 16px;font-size:13px;font-weight:600;color:#6c757d;border-bottom:2px solid #e1e8ed;">Date</th>
                                        <th style="padding:12px 16px;text-align:right;font-size:13px;font-weight:600;color:#6c757d;border-bottom:2px solid #e1e8ed;">Total (₹)</th>
                                        <th style="padding:12px 16px;text-align:right;font-size:13px;font-weight:600;color:#6c757d;border-bottom:2px solid #e1e8ed;">Entries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daily_trend as $row) : ?>
                                        <?php $entries = (int) $row['entries']; ?>
                                        <?php $day_total = (float) $row['total_amount']; ?>
                                        <tr style="border-bottom:1px solid #f0f0f0;">
                                            <td style="padding:12px 16px;font-size:13px;color:#1b2a57;"><?php echo htmlspecialchars($row['period_label'], ENT_QUOTES); ?></td>
                                            <td style="padding:12px 16px;text-align:right;font-size:13px;font-weight:600;color:#003581;">₹ <?php echo number_format($day_total, 2); ?></td>
                                            <td style="padding:12px 16px;text-align:right;font-size:13px;color:#6c757d;"><?php echo number_format($entries); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info" style="margin:0;">No daily data available for the chosen filters.</div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                // Category Donut Chart
                <?php if (!empty($category_breakdown)) : ?>
                const categoryCtx = document.getElementById('categoryChart');
                const categoryColors = ['#003581', '#faa718', '#28a745', '#dc3545', '#17a2b8', '#6c757d', '#e83e8c'];
                new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_map(function($r) { return $r['category'] ?: 'Uncategorised'; }, $category_breakdown)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($category_breakdown, 'total_amount')); ?>,
                            backgroundColor: categoryColors.slice(0, <?php echo count($category_breakdown); ?>),
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                cornerRadius: 6,
                                titleFont: {
                                    size: 13,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': ₹' + context.parsed.toLocaleString('en-IN', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
                <?php endif; ?>

                // Payment Mode Donut Chart
                <?php if (!empty($payment_breakdown)) : ?>
                const paymentCtx = document.getElementById('paymentChart');
                const paymentColors = ['#28a745', '#003581', '#faa718', '#dc3545', '#17a2b8', '#6c757d'];
                new Chart(paymentCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_map(function($r) { return $r['payment_mode'] ?: 'Unspecified'; }, $payment_breakdown)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($payment_breakdown, 'total_amount')); ?>,
                            backgroundColor: paymentColors.slice(0, <?php echo count($payment_breakdown); ?>),
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                cornerRadius: 6,
                                titleFont: {
                                    size: 13,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': ₹' + context.parsed.toLocaleString('en-IN', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
                <?php endif; ?>

                // Vendor Donut Chart
                <?php if (!empty($vendor_breakdown)) : ?>
                const vendorCtx = document.getElementById('vendorChart');
                const vendorColors = ['#faa718', '#003581', '#28a745', '#dc3545', '#17a2b8'];
                new Chart(vendorCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_column($vendor_breakdown, 'vendor_name')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($vendor_breakdown, 'total_amount')); ?>,
                            backgroundColor: vendorColors.slice(0, <?php echo count($vendor_breakdown); ?>),
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                cornerRadius: 6,
                                titleFont: {
                                    size: 13,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': ₹' + context.parsed.toLocaleString('en-IN', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
                <?php endif; ?>
            </script>

            <!-- Detailed Expense List -->
            <div class="card" style="margin-top:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="margin:0;color:#003581;">Expense Transactions (<?php echo !empty($expense_list) ? count($expense_list) : 0; ?>)</h3>
                </div>
                
                <?php if (!empty($expense_list)) : ?>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                                    <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Date</th>
                                    <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Category</th>
                                    <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Vendor/Payee</th>
                                    <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Description</th>
                                    <th style="padding:12px;text-align:right;color:#003581;font-weight:600;">Amount (₹)</th>
                                    <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Payment Mode</th>
                                    <th style="padding:12px;text-align:left;color:#003581;font-weight:600;">Added By</th>
                                    <th style="padding:12px;text-align:center;color:#003581;font-weight:600;">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expense_list as $expense) : ?>
                                    <tr style="border-bottom:1px solid #e1e8ed;">
                                        <td style="padding:12px;white-space:nowrap;"><?php echo htmlspecialchars(date('d M Y', strtotime($expense['date'])), ENT_QUOTES); ?></td>
                                        <td style="padding:12px;">
                                            <?php if (!empty($expense['category'])) : ?>
                                                <?php echo htmlspecialchars($expense['category'], ENT_QUOTES); ?>
                                            <?php else : ?>
                                                <span style="color:#6c757d;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px;"><?php echo htmlspecialchars($expense['vendor_name'] ?: '—', ENT_QUOTES); ?></td>
                                        <td style="padding:12px;max-width:260px;">
                                            <?php 
                                            $desc = $expense['description'];
                                            if (strlen($desc) > 60) {
                                                echo htmlspecialchars(substr($desc, 0, 60), ENT_QUOTES) . '...';
                                            } else {
                                                echo htmlspecialchars($desc, ENT_QUOTES);
                                            }
                                            ?>
                                        </td>
                                        <td style="padding:12px;text-align:right;font-weight:600;color:#003581;">₹ <?php echo number_format((float) $expense['amount'], 2); ?></td>
                                        <td style="padding:12px;">
                                            <?php if (!empty($expense['payment_mode'])) : ?>
                                                <?php echo htmlspecialchars($expense['payment_mode'], ENT_QUOTES); ?>
                                            <?php else : ?>
                                                <span style="color:#6c757d;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px;">
                                            <?php 
                                            $added_by_name = trim($expense['added_by_name'] ?? '');
                                            if (!empty($added_by_name)) : 
                                            ?>
                                                <?php echo htmlspecialchars($added_by_name, ENT_QUOTES); ?>
                                            <?php else : ?>
                                                <span style="color:#6c757d;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px;text-align:center;white-space:nowrap;">
                                            <?php if (!empty($expense['receipt_file'])) : ?>
                                                <a href="<?php echo htmlspecialchars(APP_URL . '/uploads/office_expenses/' . $expense['receipt_file'], ENT_QUOTES); ?>" 
                                                   target="_blank" 
                                                   class="btn" 
                                                   style="padding:6px 14px;font-size:13px;background:#faa718;"
                                                   title="View receipt">
                                                    📎 View
                                                </a>
                                            <?php else : ?>
                                                <span style="color:#6c757d;font-size:13px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e1e8ed;color:#6c757d;font-size:13px;">
                        Showing <?php echo number_format(count($expense_list)); ?> transaction<?php echo count($expense_list) !== 1 ? 's' : ''; ?> · Total: ₹<?php echo number_format($total_amount, 2); ?>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info" style="margin:0;">No expense transactions found for the selected filters.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
