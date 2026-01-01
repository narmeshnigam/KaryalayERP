<?php
/**
 * Payments Module - Onboarding
 * Redirect users to setup if module tables don't exist
 */

require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Module - Setup Required</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .onboarding-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 48px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon {
            font-size: 80px;
            margin-bottom: 24px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 16px;
        }

        p {
            font-size: 18px;
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .btn {
            display: inline-block;
            background: #003581;
            color: white;
            padding: 16px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #002560;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 53, 129, 0.4);
        }

        .features {
            text-align: left;
            background: #f8f9fa;
            padding: 24px;
            border-radius: 8px;
            margin: 32px 0;
        }

        .features h3 {
            color: #003581;
            margin-bottom: 16px;
            font-size: 18px;
        }

        .features ul {
            list-style: none;
            padding: 0;
        }

        .features li {
            padding: 8px 0;
            padding-left: 28px;
            position: relative;
            color: #495057;
        }

        .features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="onboarding-card">
        <div class="icon">💰</div>
        <h1>Payments Module Setup</h1>
        <p>The Payments module is not yet installed. Click the button below to set up the database tables and start managing payments and collections.</p>
        
        <div class="features">
            <h3>What you'll get:</h3>
            <ul>
                <li>Record full, partial, and advance payments</li>
                <li>Multi-invoice allocation support</li>
                <li>Auto-update invoice balances</li>
                <li>Payment mode tracking (Cash, UPI, Bank, Cheque)</li>
                <li>Outstanding receivables reports</li>
                <li>Client-wise payment history</li>
            </ul>
        </div>

        <a href="<?php echo APP_URL; ?>/scripts/setup_payments_tables.php" class="btn">
            🚀 Set Up Payments Module
        </a>
        
        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6c757d;">
            <p style="margin: 0;"><strong>Tip:</strong> You can also install multiple modules at once using the <a href="<?php echo APP_URL; ?>/setup/module_installer.php?from=settings" style="color: #003581; text-decoration: underline;">Unified Module Installer</a></p>
        </div>
    </div>
</body>
</html>
