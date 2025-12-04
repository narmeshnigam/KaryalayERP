<?php
// Start session if not already active (prevents "session_start(): Ignoring session_start() because a session is already active" notices)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/public/login.php');
    exit;
}

$page_title = 'Visitor Log - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
// show minimal onboarding card matching other modules
closeConnection($conn);

echo '<div class="main-wrapper"><div class="main-content">';
echo '<div class="card" style="max-width:760px;margin:0 auto;">';
echo '<h2 style="margin-top:0;color:#003581;">Visitor Log module not set up</h2>';
echo '<p>Run the setup script to create the visitor_logs table.</p>';
echo '<a href="' . APP_URL . '/scripts/setup_visitor_logs_table.php" class="btn" style="margin-top:20px;">🚀 Setup Visitor Log Module</a>';
echo '<a href="' . APP_URL . '/public/index.php" class="btn btn-accent" style="margin-left:10px;margin-top:20px;">Back to dashboard</a>';
echo '</div></div>';

require_once __DIR__ . '/../../includes/footer_sidebar.php';
exit;
?>
