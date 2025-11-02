<?php
/**
 * Diagnostic helper: check existence of Quotations tables
 * Shows which tables exist and provides links to run the setup script.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

$conn = createConnection(true);
if (!$conn) {
    die("<h2>Database connection failed</h2>");
}

$required = [
    'quotations',
    'quotation_items',
    'quotation_activity_log'
];

$results = [];
foreach ($required as $t) {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    $exists = ($res && $res->num_rows > 0);
    $results[$t] = $exists;
    if ($res) $res->free();
}

// Build simple HTML report
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotations Tables Check</title>
    <style>
        body{font-family: Arial, sans-serif; padding:20px}
        table{border-collapse: collapse; width: 600px}
        th, td{border:1px solid #ddd; padding:8px}
        th{background:#f4f4f4}
        .yes{color:green; font-weight:700}
        .no{color:#dc3545; font-weight:700}
        .actions{margin-top:20px}
        a.btn{display:inline-block; padding:10px 18px; background:#0066cc; color:#fff; text-decoration:none; border-radius:4px}
    </style>
</head>
<body>
    <h1>Quotations Tables Diagnostic</h1>
    <p>This page checks whether the quotations module tables exist in the configured database (<strong><?php echo htmlspecialchars(DB_NAME); ?></strong>).</p>
    <table>
        <thead>
            <tr><th>Table</th><th>Exists</th></tr>
        </thead>
        <tbody>
            <?php foreach ($results as $table => $exists): ?>
                <tr>
                    <td><?php echo htmlspecialchars($table); ?></td>
                    <td><?php echo $exists ? '<span class="yes">Yes</span>' : '<span class="no">Missing</span>'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="actions">
        <a class="btn" href="setup_quotations_tables.php">Run setup_quotations_tables.php</a>
        <a class="btn" style="background:#28a745; margin-left:8px" href="check_quotations_tables.php">Refresh</a>
        <a class="btn" style="background:#6c757d; margin-left:8px" href="../public/quotations/index.php">Open Quotations Index</a>
    </div>

    <h3 style="margin-top:24px">Notes</h3>
    <ul>
        <li>If a table is reported as <strong>Missing</strong>, re-run the setup script. If the setup fails, check the web server/PHP error log for exact SQL errors.</li>
        <li>If you see the tables in phpMyAdmin but this page reports them missing, verify that <code>config/config.php</code> DB_NAME is the same database you inspected.</li>
    </ul>
</body>
</html>
<?php

$conn->close();

?>