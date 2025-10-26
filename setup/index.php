<?php
/**
 * Setup Wizard - Welcome Page
 * First-time setup for Karyalay ERP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';

$status = getSetupStatus();

// If setup is complete, redirect to login
if ($status['setup_complete']) {
    header('Location: ' . APP_URL . '/public/login.php');
    exit;
}

$page_title = 'Setup Wizard - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow: hidden;
        }
        .logo-top {
            position: fixed;
            top: 24px;
            left: 28px;
            z-index: 100;
        }
        .logo-top img {
            height: 48px;
            width: auto;
        }
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            /* use viewport-aware height so the white box always fits */
            height: calc(100vh - 120px);
            max-height: calc(100vh - 120px);
            padding: 30px 40px;
            text-align: center;
            /* ensure content is vertically centered and does not force the container to scroll */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: visible;
        }
        h1 {
            color: #003581;
            font-size: 26px;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 14px;
        }
        .feature-list {
            text-align: left;
            margin: 12px 0 18px 0;
            padding: 0 6px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            width: 100%;
            max-width: 620px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .feature-icon {
            font-size: 18px;
            flex-shrink: 0;
            width: 32px;
            text-align: center;
        }
        .feature-text {
            color: #1b2a57;
            font-size: 13px;
            line-height: 1.25;
        }
        .feature-text strong {
            display: block;
            margin-bottom: 2px;
        }
        .btn {
            display: inline-block;
            padding: 12px 36px;
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 53, 129, 0.3);
        }
        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .step {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e1e8ed;
        }
        .step.active {
            background: #003581;
        }
        @media (max-width: 640px) {
            .feature-list {
                grid-template-columns: 1fr;
            }
            .setup-container {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="logo-top">
        <img src="<?php echo APP_URL; ?>/assets/logo/logo_white_text_transparent.png" alt="<?php echo APP_NAME; ?>">
    </div>
    
    <div class="setup-container">
        <h1>Welcome to <?php echo APP_NAME; ?></h1>
        <p class="subtitle">Let's get your system set up in just a few steps</p>
        
        <div class="progress-steps">
            <div class="step active"></div>
            <div class="step"></div>
            <div class="step"></div>
            <div class="step"></div>
        </div>
        
        <div class="feature-list" style="margin-bottom: 32px;">
            <div class="feature-item" style="justify-content:center;">
                <div class="feature-icon">🗄️</div>
                <div class="feature-text"><strong>Database Config</strong><br><span style="font-size:12px; color:#6c757d;">Connect to MySQL database</span></div>
            </div>
            <div class="feature-item" style="justify-content:center;">
                <div class="feature-icon">📋</div>
                <div class="feature-text"><strong>Create Tables</strong><br><span style="font-size:12px; color:#6c757d;">Set up database structure</span></div>
            </div>
            <div class="feature-item" style="justify-content:center;">
                <div class="feature-icon">👤</div>
                <div class="feature-text"><strong>Admin Account</strong><br><span style="font-size:12px; color:#6c757d;">Create admin credentials</span></div>
            </div>
            <div class="feature-item" style="justify-content:center;">
                <div class="feature-icon">🎨</div>
                <div class="feature-text"><strong>Branding Setup</strong><br><span style="font-size:12px; color:#6c757d;">Customize your branding</span></div>
            </div>
        </div>
        <div style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: center; gap: 32px;">
                <div style="text-align:center;">
                    <div style="font-size:18px; color:#003581; font-weight:600;">4 Steps</div>
                    <div style="font-size:12px; color:#6c757d;">Quick onboarding</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:18px; color:#003581; font-weight:600;">Secure</div>
                    <div style="font-size:12px; color:#6c757d;">No default credentials</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:18px; color:#003581; font-weight:600;">Customizable</div>
                    <div style="font-size:12px; color:#6c757d;">Branding options</div>
                </div>
            </div>
        </div>
        <a href="<?php echo APP_URL; ?>/setup/database.php" class="btn">Start Setup →</a>
    </div>
</body>
</html>
