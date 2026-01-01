<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: ' . APP_URL . '/public/login.php');
	exit;
}

$page_title = 'Document Vault - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
closeConnection($conn);

echo '<div class="main-wrapper"><div class="main-content">';
echo '<div class="card" style="max-width:760px;margin:0 auto;">';
echo '<h2 style="margin-top:0;color:#003581;">Document Vault module not set up</h2>';
echo '<p>Run the setup script to create the documents table.</p>';
echo '<a href="' . APP_URL . '/scripts/setup_documents_table.php" class="btn" style="margin-top:20px;">🚀 Setup Document Vault Module</a>';
echo '<a href="' . APP_URL . '/public/index.php" class="btn btn-accent" style="margin-left:10px;margin-top:20px;">Back to dashboard</a>';
echo '<div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6c757d;text-align:center;">';
echo '<p style="margin:0;"><strong>Tip:</strong> You can also install multiple modules at once using the <a href="' . APP_URL . '/setup/module_installer.php?from=settings" style="color:#003581;text-decoration:underline;">Unified Module Installer</a></p>';
echo '</div>';
echo '</div></div>';

require_once __DIR__ . '/../../includes/footer_sidebar.php';
exit;
?>
