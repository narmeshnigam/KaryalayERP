<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Reimbursements - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
if ($conn) {
    closeConnection($conn);
}

echo '<div class="main-wrapper"><div class="main-content">';
echo '<div class="card" style="max-width:760px;margin:0 auto;">';
echo '<h2 style="margin-top:0;color:#003581;">Reimbursements module not set up</h2>';
echo '<p>Run the setup script to create the reimbursements table and upload directory.</p>';
echo '<a href="../../scripts/setup_reimbursements_table.php" class="btn" style="margin-top:20px;">🚀 Setup Reimbursements Module</a>';
echo '<a href="../index.php" class="btn btn-accent" style="margin-left:10px;margin-top:20px;">Back to dashboard</a>';
echo '</div></div>';

require_once __DIR__ . '/../../includes/footer_sidebar.php';
exit;
