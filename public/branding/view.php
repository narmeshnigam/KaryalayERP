<?php
/**
 * Branding Settings - View Only
 * Read-only display of organization information for all users
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if module is set up
if (!branding_table_exists($conn)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Branding Module Not Installed - <?php echo APP_NAME; ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 600px;
                width: 100%;
                padding: 40px;
                text-align: center;
            }
            h1 {
                color: #2d3748;
                margin-bottom: 10px;
                font-size: 28px;
            }
            .icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            .message {
                color: #4a5568;
                margin-bottom: 30px;
                font-size: 16px;
                line-height: 1.6;
            }
            .btn {
                padding: 12px 24px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 500;
                display: inline-block;
                transition: all 0.3s;
                margin: 5px;
            }
            .btn-primary {
                background: #667eea;
                color: white;
            }
            .btn-primary:hover {
                background: #5568d3;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: #e2e8f0;
                color: #2d3748;
            }
            .btn-secondary:hover {
                background: #cbd5e0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">🎨</div>
            <h1>Branding Module Not Installed</h1>
            <p class="message">
                The branding settings table has not been created yet.<br>
                Please install the Branding module to customize your organization's branding.
            </p>
            <a href="<?php echo APP_URL; ?>/scripts/setup_branding_table.php" class="btn btn-primary">Install Branding Module</a>
            <a href="<?php echo APP_URL; ?>/public/index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$page_title = 'Organization Information - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get current settings
$settings = branding_get_settings($conn);

if (!$settings) {
    echo '<div class="main-wrapper"><div class="main-content"><div class="alert alert-warning">No branding information available.</div></div></div>';
    if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
        closeConnection($conn);
    }
    require_once __DIR__ . '/../../includes/footer_sidebar.php';
    exit;
}
?>

<style>
  .card-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }
  .logo-display { 
    padding:24px; 
    background:#f8f9fa; 
    border-radius:8px; 
    text-align:center; 
    min-height:140px; 
    display:flex; 
    align-items:center; 
    justify-content:center; 
  }
  .logo-display img { 
    max-width:100%; 
    max-height:120px; 
    object-fit:contain; 
  }
  .logo-display.empty { 
    color:#a0aec0; 
    font-style:italic; 
  }

  @media (max-width:1024px){
  .card-container{grid-template-columns:repeat(2, 1fr);gap:15px;}
  }

  @media (max-width:768px){
  .card-container{grid-template-columns:1fr;gap:12px;}
  .card-container .card[style*="grid-column"]{grid-column:unset !important;}
  }
</style>

<style>
.branding-view-header-flex{display:flex;justify-content:space-between;align-items:center;}
.branding-view-logo-card{display:flex;gap:20px;align-items:center;flex-wrap:wrap;}
.branding-view-logo-avatar{width:84px;height:84px;border-radius:50%;background:#003581;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:28px;flex-shrink:0;}
.branding-view-logo-content{flex:1;min-width:200px;}
.branding-logo-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;margin-top:16px;}
.branding-org-details-grid{display:grid;grid-template-columns:1fr;gap:12px;font-size:14px;}
.branding-system-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;font-size:14px;}

@media (max-width:1024px){
.branding-logo-grid{grid-template-columns:repeat(2,1fr);gap:15px;}
.branding-system-info-grid{grid-template-columns:1fr;}
}

@media (max-width:768px){
.branding-view-header-flex{flex-direction:column;align-items:stretch;gap:16px;}
.branding-view-header-flex .btn{width:100%;text-align:center;}
.branding-view-header-flex h1{font-size:1.3rem;}
.branding-view-header-flex p{font-size:13px;}
.branding-view-logo-card{flex-direction:column;gap:16px;}
.branding-view-logo-avatar{width:64px;height:64px;font-size:24px;margin:0 auto;}
.branding-view-logo-content{text-align:center;}
.branding-view-logo-content > div:first-child{font-size:18px;}
.branding-logo-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
.logo-display{padding:16px;min-height:100px;}
.logo-display img{max-height:80px;}
}

@media (max-width:480px){
.branding-view-header-flex h1{font-size:1.2rem;}
.branding-view-header-flex .btn{padding:8px 16px !important;font-size:12px;}
.branding-view-logo-avatar{width:56px;height:56px;font-size:20px;}
.branding-view-logo-content > div:first-child{font-size:16px;}
.branding-view-logo-content > div:nth-child(2){font-size:12px;}
.branding-logo-grid{grid-template-columns:1fr;gap:10px;}
.logo-display{padding:12px;min-height:80px;font-size:12px;}
.logo-display img{max-height:60px;}
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div class="branding-view-header-flex">
        <div>
          <h1>🏢 Organization Information</h1>
          <p>Company branding and contact details</p>
        </div>
        <div>
          <?php if (branding_user_can_edit()): ?>
            <a href="index.php" class="btn">✏️ Edit Settings</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Header Card with Organization Name -->
    <div class="card branding-view-logo-card">
      <div class="branding-view-logo-avatar">
        <?php echo strtoupper(substr($settings['org_name'] ?? 'K', 0, 1)); ?>
      </div>
      <div class="branding-view-logo-content">
        <div style="font-size:20px;color:#003581;font-weight:700;">
          <?php echo htmlspecialchars($settings['org_name'] ?? 'Organization'); ?>
        </div>
        <?php if (!empty($settings['tagline'])): ?>
          <div style="color:#6c757d;font-size:13px;margin-top:4px;">
            <?php echo htmlspecialchars($settings['tagline']); ?>
          </div>
        <?php endif; ?>
        <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
          <?php if (!empty($settings['legal_name'])): ?>
            <span style="background:#e3f2fd;color:#003581;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
              Legal: <?php echo htmlspecialchars($settings['legal_name']); ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($settings['gstin'])): ?>
            <span style="background:#fff3cd;color:#856404;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
              GSTIN/Reg. No.: <?php echo htmlspecialchars($settings['gstin']); ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card-container" style="margin-top:20px;">
      <!-- Logo Assets Card - Full Width -->
      <div class="card" style="grid-column:1/-1;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🎨 Logo Assets</h3>
        <div class="branding-logo-grid">
          <div>
            <strong style="font-size:13px;color:#6c757d;display:block;margin-bottom:8px;">Login Page Logo</strong>
            <div class="logo-display">
              <?php if (!empty($settings['login_page_logo'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['login_page_logo']); ?>" alt="Login Logo">
              <?php else: ?>
                <div class="empty">Not configured</div>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <strong style="font-size:13px;color:#6c757d;display:block;margin-bottom:8px;">Sidebar Header Logo (Expanded)</strong>
            <div class="logo-display" style="background:#1b2a57;">
              <?php if (!empty($settings['sidebar_header_full_logo'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['sidebar_header_full_logo']); ?>" alt="Sidebar Logo">
              <?php else: ?>
                <div class="empty" style="color:#cbd5e0;">Not configured</div>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <strong style="font-size:13px;color:#6c757d;display:block;margin-bottom:8px;">Favicon</strong>
            <div class="logo-display">
              <?php if (!empty($settings['favicon'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['favicon']); ?>" alt="Favicon">
              <?php else: ?>
                <div class="empty">Not configured</div>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <strong style="font-size:13px;color:#6c757d;display:block;margin-bottom:8px;">Sidebar Square Logo (Collapsed)</strong>
            <div class="logo-display" style="background:#1b2a57;">
              <?php if (!empty($settings['sidebar_square_logo'])): ?>
                <img src="../../<?php echo htmlspecialchars($settings['sidebar_square_logo']); ?>" alt="Sidebar Square Icon">
              <?php else: ?>
                <div class="empty" style="color:#cbd5e0;">Not configured</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Organization Details Card -->
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🏢 Organization Details</h3>
        <div class="branding-org-details-grid">
          <div><strong>Organization Name:</strong> <?php echo htmlspecialchars($settings['org_name'] ?? '—'); ?></div>
          <?php if (!empty($settings['legal_name'])): ?>
            <div><strong>Legal Name:</strong> <?php echo htmlspecialchars($settings['legal_name']); ?></div>
          <?php endif; ?>
          <?php if (!empty($settings['tagline'])): ?>
            <div><strong>Tagline:</strong> <?php echo htmlspecialchars($settings['tagline']); ?></div>
          <?php endif; ?>
          <?php if (!empty($settings['gstin'])): ?>
            <div><strong>GSTIN/Reg. No.:</strong> <?php echo htmlspecialchars($settings['gstin']); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Address Card -->
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📍 Address</h3>
        <div class="branding-org-details-grid">
          <?php if (!empty($settings['address_line1']) || !empty($settings['address_line2'])): ?>
            <div>
              <strong>Address:</strong><br>
              <?php if (!empty($settings['address_line1'])): ?>
                <?php echo htmlspecialchars($settings['address_line1']); ?><br>
              <?php endif; ?>
              <?php if (!empty($settings['address_line2'])): ?>
                <?php echo htmlspecialchars($settings['address_line2']); ?><br>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($settings['city'])): ?>
            <div><strong>City:</strong> <?php echo htmlspecialchars($settings['city']); ?></div>
          <?php endif; ?>
          <?php if (!empty($settings['state'])): ?>
            <div><strong>State / Province:</strong> <?php echo htmlspecialchars($settings['state']); ?></div>
          <?php endif; ?>
          <?php if (!empty($settings['zip'])): ?>
            <div><strong>ZIP / Postal Code:</strong> <?php echo htmlspecialchars($settings['zip']); ?></div>
          <?php endif; ?>
          <?php if (!empty($settings['country'])): ?>
            <div><strong>Country:</strong> <?php echo htmlspecialchars($settings['country']); ?></div>
          <?php endif; ?>
          <?php if (empty($settings['address_line1']) && empty($settings['city']) && empty($settings['state'])): ?>
            <div style="color:#a0aec0;font-style:italic;">No address information available</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Contact Information Card -->
      <div class="card">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">📞 Contact Information</h3>
        <div class="branding-org-details-grid">
          <?php if (!empty($settings['email'])): ?>
            <div>
              <strong>Email:</strong><br>
              <a href="mailto:<?php echo htmlspecialchars($settings['email']); ?>" style="color:#003581;">
                <?php echo htmlspecialchars($settings['email']); ?>
              </a>
            </div>
          <?php endif; ?>
          <?php if (!empty($settings['phone'])): ?>
            <div>
              <strong>Phone:</strong><br>
              <a href="tel:<?php echo htmlspecialchars($settings['phone']); ?>" style="color:#003581;">
                <?php echo htmlspecialchars($settings['phone']); ?>
              </a>
            </div>
          <?php endif; ?>
          <?php if (!empty($settings['website'])): ?>
            <div>
              <strong>Website:</strong><br>
              <a href="<?php echo htmlspecialchars($settings['website']); ?>" target="_blank" style="color:#003581;">
                <?php echo htmlspecialchars($settings['website']); ?> ↗
              </a>
            </div>
          <?php endif; ?>
          <?php if (empty($settings['email']) && empty($settings['phone']) && empty($settings['website'])): ?>
            <div style="color:#a0aec0;font-style:italic;">No contact information available</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Branding Elements Card -->
      <div class="card" style="grid-column:1/-1;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">✨ Branding Elements</h3>
        <div style="font-size:14px;">
          <?php if (!empty($settings['footer_text'])): ?>
            <div><strong>Footer Text:</strong></div>
            <div style="margin-top:8px;padding:12px;background:#f8f9fa;border-radius:6px;">
              <?php echo nl2br(htmlspecialchars($settings['footer_text'])); ?>
            </div>
          <?php else: ?>
            <div style="color:#a0aec0;font-style:italic;">No footer text configured</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- System Information Card -->
      <div class="card" style="grid-column:1/-1;">
        <h3 style="color:#003581;margin:0 0 12px;border-bottom:2px solid #003581;padding-bottom:8px;">🗂️ System Information</h3>
        <div class="branding-system-info-grid">
          <div><strong>Last Updated:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($settings['updated_at'])); ?></div>
          <?php if (!empty($settings['created_by'])): ?>
            <div><strong>Created By:</strong> User ID <?php echo htmlspecialchars($settings['created_by']); ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
