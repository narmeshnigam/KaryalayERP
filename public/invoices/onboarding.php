<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/public/login.php');
    exit;
}

$page_title = 'Invoices - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
closeConnection($conn);

echo '<div class="main-wrapper"><div class="main-content">';
echo '<div class="card" style="max-width:760px;margin:40px auto;padding:40px;text-align:center;">';
echo '<div style="font-size:64px;margin-bottom:20px;">🧾</div>';
echo '<h2 style="margin-top:0;color:#003581;font-size:28px;">Invoices Module Not Set Up</h2>'; 
echo '<p style="color:#6c757d;font-size:16px;line-height:1.6;margin-bottom:32px;">The Invoices module requires database setup before you can start creating and managing invoices.</p>';
echo '<div style="background:#f8f9fa;padding:24px;border-radius:8px;margin:24px 0;text-align:left;">';
echo '<h3 style="color:#003581;font-size:16px;margin-bottom:12px;">✨ Features:</h3>';
echo '<ul style="list-style:none;padding:0;margin:0;">';
echo '<li style="padding:6px 0;"><strong>✓</strong> Create & manage itemized invoices</li>';
echo '<li style="padding:6px 0;"><strong>✓</strong> Convert quotations to invoices</li>';
echo '<li style="padding:6px 0;"><strong>✓</strong> Track payments & outstanding balances</li>';
echo '<li style="padding:6px 0;"><strong>✓</strong> Automatic inventory deduction</li>';
echo '<li style="padding:6px 0;"><strong>✓</strong> Payment terms & overdue tracking</li>';
echo '<li style="padding:6px 0;"><strong>✓</strong> Export to Excel & professional PDFs</li>';
echo '</ul></div>';
echo '<a href="' . APP_URL . '/scripts/setup_invoices_tables.php" class="btn btn-primary" style="padding:14px 32px;font-size:16px;margin-top:20px;">🚀 Setup Invoices Module</a>';
echo '<a href="' . APP_URL . '/public/index.php" class="btn btn-accent" style="margin-left:10px;margin-top:20px;padding:14px 32px;font-size:16px;">← Back to Dashboard</a>';
echo '<div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6c757d;text-align:center;">';
echo '<p style="margin:0;"><strong>Tip:</strong> You can also install multiple modules at once using the <a href="' . APP_URL . '/setup/module_installer.php?from=settings" style="color:#003581;text-decoration:underline;">Unified Module Installer</a></p>';
echo '</div>';
echo '</div></div>';

require_once __DIR__ . '/../../includes/footer_sidebar.php';
exit;
?>
