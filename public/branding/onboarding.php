<?php
/**
 * Branding Module Onboarding
 * Initial setup page when branding_settings table doesn't exist
 */

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

$page_title = 'Branding Settings - Setup - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$conn = createConnection(true);
closeConnection($conn);
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="card" style="max-width:760px;margin:40px auto;padding:48px;">
      <div style="text-align:center;margin-bottom:32px;">
        <div style="font-size:64px;margin-bottom:16px;">🏢</div>
        <h2 style="margin:0 0 8px 0;color:#003581;">Branding & Organization Settings</h2>
        <p style="color:#6c757d;font-size:15px;">Set up your organization's identity and branding elements</p>
      </div>

      <div style="background:#f8f9fa;border-left:4px solid #003581;padding:20px;margin-bottom:32px;border-radius:6px;">
        <h3 style="margin:0 0 12px 0;font-size:16px;color:#1b2a57;">📋 What this module does:</h3>
        <ul style="margin:0;padding-left:20px;color:#495057;line-height:1.8;">
          <li>Upload and manage company logos (light, dark, square variants)</li>
          <li>Configure organization details (name, address, contact info)</li>
          <li>Set custom tagline and footer text for system-wide use</li>
          <li>Automatically integrate branding across reports, PDFs, and interface</li>
        </ul>
      </div>

      <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin-bottom:32px;border-radius:6px;">
        <h3 style="margin:0 0 12px 0;font-size:16px;color:#856404;">⚠️ Before you proceed:</h3>
        <ul style="margin:0;padding-left:20px;color:#856404;line-height:1.8;">
          <li>Prepare logo files in PNG, JPG, or SVG format (max 2MB each)</li>
          <li>Have organization details ready (legal name, address, GSTIN, etc.)</li>
          <li>Only <strong>Admin</strong> role can configure branding settings</li>
        </ul>
      </div>

      <div style="text-align:center;">
        <a href="<?php echo APP_URL; ?>/scripts/setup_branding_table.php" class="btn btn-primary" style="padding:12px 32px;font-size:16px;">
          🚀 Setup Branding Module
        </a>
        <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-secondary" style="margin-left:12px;padding:12px 32px;font-size:16px;">
          ← Back to Dashboard
        </a>
      </div>

      <div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6c757d;text-align:center;">
        <p style="margin:0;">After setup, you'll be able to configure your organization's branding from <strong>Settings &gt; Branding</strong></p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
