<?php
/**
 * Migration Helper
 * 
 * This script helps migrate from hardcoded credentials to environment-based configuration.
 * It reads your current config.php and suggests .env values.
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Configuration Migration Helper</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #003581; border-bottom: 3px solid #003581; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin: 15px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8; margin: 15px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 15px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #dee2e6; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .step-number { background: #003581; color: white; border-radius: 50%; width: 30px; height: 30px; display: inline-block; text-align: center; line-height: 30px; font-weight: bold; margin-right: 10px; }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔄 Configuration Migration Helper</h1>";

// Check if .env already exists
$env_file = __DIR__ . '/.env';
$env_exists = file_exists($env_file);

if ($env_exists) {
    echo "<div class='success'>";
    echo "<strong>✓ .env file already exists!</strong><br>";
    echo "Your environment configuration is already set up. You can edit .env file to update your credentials.";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "<strong>⚠ .env file not found</strong><br>";
    echo "We'll help you create one based on your current or default settings.";
    echo "</div>";
}

echo "<div class='info'>";
echo "<strong>ℹ What This Tool Does:</strong><br>";
echo "This migration helper will guide you through setting up environment-based configuration for KaryalayERP.";
echo "</div>";

// Try to detect current configuration values
$detected_values = [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'karyalay_db',
    'DB_CHARSET' => 'utf8mb4',
    'APP_NAME' => 'Karyalay ERP',
    'APP_URL' => 'http://localhost/KaryalayERP',
    'SESSION_NAME' => 'karyalay_session',
    'SESSION_LIFETIME' => '3600',
    'TIMEZONE' => 'Asia/Kolkata',
    'ENVIRONMENT' => 'development',
    'DEBUG_MODE' => 'true'
];

// Try to load current config if it exists (legacy format)
$config_file = __DIR__ . '/config/config.php';
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    
    // Try to extract old hardcoded values using regex
    if (preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']*)'\s*\)/", $config_content, $matches)) {
        $detected_values['DB_HOST'] = $matches[1];
    }
    if (preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']*)'\s*\)/", $config_content, $matches)) {
        $detected_values['DB_USER'] = $matches[1];
    }
    if (preg_match("/define\s*\(\s*'DB_PASS'\s*,\s*'([^']*)'\s*\)/", $config_content, $matches)) {
        $detected_values['DB_PASS'] = $matches[1];
    }
    if (preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']*)'\s*\)/", $config_content, $matches)) {
        $detected_values['DB_NAME'] = $matches[1];
    }
}

echo "<h2>📋 Migration Steps</h2>";

echo "<div class='step'>";
echo "<span class='step-number'>1</span>";
echo "<strong>Create Your .env File</strong><br><br>";
if (!$env_exists) {
    echo "Copy this content to a new file named <code>.env</code> in your project root:<br><br>";
    echo "<pre>";
    echo "# Environment Configuration\n";
    echo "# This file contains sensitive configuration. DO NOT commit to version control!\n\n";
    echo "# Database Configuration\n";
    echo "DB_HOST=" . htmlspecialchars($detected_values['DB_HOST']) . "\n";
    echo "DB_USER=" . htmlspecialchars($detected_values['DB_USER']) . "\n";
    echo "DB_PASS=" . htmlspecialchars($detected_values['DB_PASS']) . "\n";
    echo "DB_NAME=" . htmlspecialchars($detected_values['DB_NAME']) . "\n";
    echo "DB_CHARSET=" . htmlspecialchars($detected_values['DB_CHARSET']) . "\n\n";
    echo "# Application Configuration\n";
    echo "APP_NAME=" . htmlspecialchars($detected_values['APP_NAME']) . "\n";
    echo "APP_URL=" . htmlspecialchars($detected_values['APP_URL']) . "\n\n";
    echo "# Session Configuration\n";
    echo "SESSION_NAME=" . htmlspecialchars($detected_values['SESSION_NAME']) . "\n";
    echo "SESSION_LIFETIME=" . htmlspecialchars($detected_values['SESSION_LIFETIME']) . "\n\n";
    echo "# Timezone\n";
    echo "TIMEZONE=" . htmlspecialchars($detected_values['TIMEZONE']) . "\n\n";
    echo "# Environment (development, production)\n";
    echo "ENVIRONMENT=" . htmlspecialchars($detected_values['ENVIRONMENT']) . "\n\n";
    echo "# Debug Mode (true/false)\n";
    echo "DEBUG_MODE=" . htmlspecialchars($detected_values['DEBUG_MODE']) . "\n";
    echo "</pre>";
} else {
    echo "✓ Your .env file already exists. You can edit it if needed.";
}
echo "</div>";

echo "<div class='step'>";
echo "<span class='step-number'>2</span>";
echo "<strong>Verify Configuration Files</strong><br><br>";
echo "Make sure these files exist:<br>";
echo "<ul>";
echo "<li><code>config/env_loader.php</code> - " . (file_exists(__DIR__ . '/config/env_loader.php') ? "✓ Found" : "✗ Missing") . "</li>";
echo "<li><code>config/config.php</code> - " . (file_exists(__DIR__ . '/config/config.php') ? "✓ Found" : "✗ Missing") . "</li>";
echo "<li><code>.env.example</code> - " . (file_exists(__DIR__ . '/.env.example') ? "✓ Found" : "✗ Missing") . "</li>";
echo "<li><code>.gitignore</code> - " . (file_exists(__DIR__ . '/.gitignore') ? "✓ Found" : "✗ Missing") . "</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step'>";
echo "<span class='step-number'>3</span>";
echo "<strong>Validate Your Configuration</strong><br><br>";
echo "Run the configuration validator to ensure everything is working:<br><br>";
echo "<a href='validate_config.php' style='display: inline-block; background: #003581; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Configuration Validator →</a>";
echo "</div>";

echo "<div class='step'>";
echo "<span class='step-number'>4</span>";
echo "<strong>Alternative: Use Setup Wizard</strong><br><br>";
echo "If you prefer a guided setup, you can use the setup wizard:<br><br>";
echo "<a href='setup/' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Open Setup Wizard →</a>";
echo "</div>";

echo "<h2>📖 Additional Resources</h2>";
echo "<ul>";
echo "<li>Read the <a href='ENV_CONFIGURATION_GUIDE.md' target='_blank'>Environment Configuration Guide</a></li>";
echo "<li>Check <a href='README.md' target='_blank'>README.md</a> for general setup instructions</li>";
echo "<li>Review <a href='.env.example' target='_blank'>.env.example</a> for all available options</li>";
echo "</ul>";

echo "<div class='info' style='margin-top: 30px;'>";
echo "<strong>🔒 Security Reminder:</strong><br>";
echo "Never commit your .env file to version control. It contains sensitive credentials!<br>";
echo "The .gitignore file has been configured to automatically exclude it.";
echo "</div>";

echo "</div></body></html>";
?>
