<?php
/**
 * Roles & Permissions Onboarding
 * Guide users to set up the roles and permissions tables
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = 'Roles & Permissions Setup - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

$conn = createConnection(true);

// Check which tables exist
$required_tables = ['roles', 'permissions', 'role_permissions', 'user_roles', 'permission_audit_log'];
$setup_status = [];
$all_tables_exist = true;

foreach ($required_tables as $table) {
    $result = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = ($result && mysqli_num_rows($result) > 0);
    $setup_status[$table] = $exists;
    if (!$exists) {
        $all_tables_exist = false;
    }
    if ($result) @mysqli_free_result($result);
}

closeConnection($conn);

// If all tables exist, redirect to roles index
if ($all_tables_exist) {
    header('Location: index.php');
    exit;
}
?>

<div class="main-wrapper">
  <div class="main-content">
    <div class="card" style="max-width:760px;margin:40px auto;padding:48px;">
      <div style="text-align:center;margin-bottom:32px;">
        <div style="font-size:64px;margin-bottom:16px;">🔐</div>
        <h2 style="margin:0 0 8px 0;color:#003581;">Roles & Permissions Setup</h2>
        <p style="color:#6c757d;font-size:15px;">Set up role-based access control for your KaryalayERP system</p>
      </div>

      <div style="background:#f8f9fa;border-left:4px solid #003581;padding:20px;margin-bottom:32px;border-radius:6px;">
        <h3 style="margin:0 0 12px 0;font-size:16px;color:#1b2a57;">📋 What this module does:</h3>
        <ul style="margin:0;padding-left:20px;color:#495057;line-height:1.8;">
          <li>Manage user roles (Admin, Manager, Employee, HR Manager, etc.)</li>
          <li>Control page-level access permissions (view, create, edit, delete)</li>
          <li>Assign roles to users for granular access control</li>
          <li>Track all permission changes with audit logging</li>
          <li>Secure all modules with role-based authentication</li>
        </ul>
      </div>

      <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin-bottom:32px;border-radius:6px;">
        <h3 style="margin:0 0 12px 0;font-size:16px;color:#856404;">📊 Setup Status:</h3>
        <ul style="margin:0;padding-left:20px;color:#856404;line-height:1.8;">
          <?php foreach ($required_tables as $table): ?>
            <li>
              <strong><?php echo ucfirst(str_replace('_', ' ', $table)); ?></strong> table - 
              <?php if ($setup_status[$table]): ?>
                <span style="color:#28a745;">✓ Ready</span>
              <?php else: ?>
                <span style="color:#dc3545;">✗ Missing</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div style="background:#d1ecf1;border-left:4px solid #17a2b8;padding:20px;margin-bottom:32px;border-radius:6px;">
        <h3 style="margin:0 0 12px 0;font-size:16px;color:#0c5460;">🚀 What will be created:</h3>
        <ul style="margin:0;padding-left:20px;color:#0c5460;line-height:1.8;">
          <li><strong>5 database tables</strong> for roles, permissions, and audit logging</li>
          <li><strong>8 default roles</strong> (Super Admin, Admin, Manager, Employee, HR Manager, Accountant, Sales Executive, Guest)</li>
          <li><strong>40+ default permissions</strong> covering all modules</li>
          <li><strong>Super Admin</strong> configured with full access</li>
        </ul>
      </div>

      <div style="text-align:center;" id="setupContainer">
        <button onclick="runSetup()" class="btn btn-primary" style="padding:12px 32px;font-size:16px;" id="setupButton">
          🚀 Setup Roles & Permissions Module
        </button>
        <a href="../../index.php" class="btn btn-secondary" style="margin-left:12px;padding:12px 32px;font-size:16px;">
          ← Back to Dashboard
        </a>
      </div>

      <div id="setupStatus" style="display:none;margin-top:24px;text-align:center;">
        <div style="background:#d1ecf1;border:1px solid #bee5eb;border-radius:6px;padding:16px;color:#0c5460;">
          <strong>⏳ Running setup...</strong> Please wait, this may take a few moments.
        </div>
      </div>

      <div id="setupResult" style="margin-top:24px;"></div>

      <div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6c757d;text-align:center;">
        <p style="margin:0;">After setup, you'll be able to manage roles and permissions from <strong>Settings &gt; Roles & Permissions</strong></p>
      </div>
    </div>
  </div>
</div>

<script>
function runSetup() {
    const statusDiv = document.getElementById('setupStatus');
    const resultDiv = document.getElementById('setupResult');
    const setupButton = document.getElementById('setupButton');
    
    // Show loading status
    statusDiv.style.display = 'block';
    resultDiv.innerHTML = '';
    setupButton.disabled = true;
    setupButton.innerHTML = '⏳ Setting up...';
    
    // Call the setup API
    fetch('setup_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        statusDiv.style.display = 'none';
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;padding:20px;color:#155724;">
                    <h3 style="margin:0 0 12px 0;font-size:18px;color:#155724;">
                        ✓ Setup Complete!
                    </h3>
                    <p style="margin:0 0 16px 0;">${data.message}</p>
                    <a href="index.php" class="btn btn-primary">
                        → Go to Roles Management
                    </a>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;padding:20px;color:#721c24;">
                    <h3 style="margin:0 0 12px 0;font-size:18px;color:#721c24;">
                        ✗ Setup Failed
                    </h3>
                    <p style="margin:0 0 12px 0;">${data.message}</p>
                    <details style="margin-top:12px;">
                        <summary style="cursor:pointer;font-weight:bold;">View Details</summary>
                        <pre style="margin:12px 0 0 0;padding:12px;background:#fff;border:1px solid #ddd;border-radius:4px;font-size:12px;overflow:auto;">${data.details || 'No additional details available.'}</pre>
                    </details>
                </div>
            `;
            setupButton.disabled = false;
            setupButton.innerHTML = '🚀 Setup Roles & Permissions Module';
        }
    })
    .catch(error => {
        statusDiv.style.display = 'none';
        resultDiv.innerHTML = `
            <div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;padding:20px;color:#721c24;">
                <h3 style="margin:0 0 12px 0;font-size:18px;color:#721c24;">
                    ✗ Error
                </h3>
                <p style="margin:0;">An error occurred while running the setup: ${error.message}</p>
            </div>
        `;
        setupButton.disabled = false;
        setupButton.innerHTML = '🚀 Setup Roles & Permissions Module';
    });
}
</script>

<?php require_once __DIR__ . '/../../../includes/footer_sidebar.php'; ?>
