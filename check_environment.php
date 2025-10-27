<?php
/**
 * Server Environment Detection Test Page
 * 
 * This page shows auto-detected server environment configuration
 * and helps verify that URL handling is working correctly.
 */

require_once __DIR__ . '/config/config.php';

$detection = ServerDetector::detect();
$is_auto_detected = defined('SERVER_AUTO_DETECTED') && SERVER_AUTO_DETECTED;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Environment Detection - <?php echo APP_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #003581 0%, #004aad 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #003581;
        }
        .section h2 {
            color: #003581;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .icon {
            font-size: 24px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .info-item label {
            display: block;
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-item .value {
            font-size: 16px;
            color: #333;
            word-break: break-all;
            font-family: 'Courier New', monospace;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-green {
            background: #28a745;
        }
        .status-yellow {
            background: #ffc107;
        }
        .status-red {
            background: #dc3545;
        }
        .test-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .test-link {
            background: white;
            border: 2px solid #003581;
            color: #003581;
            padding: 12px 20px;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .test-link:hover {
            background: #003581;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,53,129,0.3);
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 10px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .btn-primary {
            background: #003581;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #004aad;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,53,129,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Server Environment Detection</h1>
            <p>Auto-detected configuration for <?php echo htmlspecialchars(APP_NAME); ?></p>
        </div>
        
        <div class="content">
            <!-- Detection Status -->
            <?php if ($is_auto_detected): ?>
                <div class="alert alert-info">
                    <span class="icon">ℹ️</span>
                    <div>
                        <strong>Auto-Detection Active</strong><br>
                        APP_URL was not found in .env file, so it's being auto-detected from server environment.
                        This is perfectly fine! The system will adapt automatically.
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <span class="icon">✓</span>
                    <div>
                        <strong>Using .env Configuration</strong><br>
                        APP_URL is explicitly set in your .env file. Auto-detection is available as fallback.
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Server Detection Results -->
            <div class="section">
                <h2><span class="icon">🖥️</span> Server Detection Results</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Environment Type</label>
                        <div class="value">
                            <span class="status-indicator status-<?php echo $detection['is_localhost'] ? 'yellow' : 'green'; ?>"></span>
                            <?php echo htmlspecialchars($detection['environment']); ?>
                            <?php if ($detection['is_localhost']): ?>
                                <span class="badge badge-warning">Localhost</span>
                            <?php else: ?>
                                <span class="badge badge-success">Live Server</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label>Server Name</label>
                        <div class="value"><?php echo htmlspecialchars($detection['server_name']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <label>Protocol</label>
                        <div class="value">
                            <?php echo htmlspecialchars($detection['scheme']); ?>://
                            <?php if ($detection['scheme'] === 'https'): ?>
                                <span class="badge badge-success">Secure</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Not Encrypted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label>Port</label>
                        <div class="value"><?php echo htmlspecialchars($detection['server_port']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <label>Base Path</label>
                        <div class="value">
                            <?php echo $detection['base_path'] ?: '/' . ' <span class="badge badge-info">Root</span>'; ?>
                            <?php if ($detection['has_subdirectory']): ?>
                                <span class="badge badge-warning">Subdirectory</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label>Detected APP_URL</label>
                        <div class="value"><?php echo htmlspecialchars($detection['base_url']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Configuration Status -->
            <div class="section">
                <h2><span class="icon">⚙️</span> Active Configuration</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>APP_URL (In Use)</label>
                        <div class="value"><?php echo htmlspecialchars(APP_URL); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <label>APP_NAME</label>
                        <div class="value"><?php echo htmlspecialchars(APP_NAME); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <label>APP_ENVIRONMENT</label>
                        <div class="value">
                            <?php echo defined('APP_ENVIRONMENT') ? htmlspecialchars(APP_ENVIRONMENT) : 'Not Set'; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label>Debug Mode</label>
                        <div class="value">
                            <?php 
                            $debug = defined('APP_DEBUG') && APP_DEBUG;
                            echo $debug ? 'Enabled' : 'Disabled';
                            ?>
                            <span class="badge <?php echo $debug ? 'badge-warning' : 'badge-success'; ?>">
                                <?php echo $debug ? 'Development' : 'Production'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label>.env File Status</label>
                        <div class="value">
                            <?php 
                            $env_exists = defined('ENV_FILE_LOADED') && ENV_FILE_LOADED;
                            echo $env_exists ? 'Loaded' : 'Not Found';
                            ?>
                            <span class="badge <?php echo $env_exists ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $env_exists ? 'Present' : 'Auto-Detect Mode'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label>Database Host</label>
                        <div class="value"><?php echo htmlspecialchars(DB_HOST); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- URL Generation Test -->
            <div class="section">
                <h2><span class="icon">🔗</span> URL Generation Tests</h2>
                <p style="margin-bottom: 15px; color: #666;">
                    Click these links to verify URL generation is working correctly:
                </p>
                <div class="test-links">
                    <a href="<?php echo url('index.php'); ?>" class="test-link" target="_blank">
                        Dashboard URL
                    </a>
                    <a href="<?php echo url('login.php'); ?>" class="test-link" target="_blank">
                        Login URL
                    </a>
                    <a href="<?php echo asset('assets/icons/dashboard.png'); ?>" class="test-link" target="_blank">
                        Asset URL
                    </a>
                    <a href="<?php echo APP_URL . '/setup/index.php'; ?>" class="test-link" target="_blank">
                        Setup URL
                    </a>
                </div>
            </div>
            
            <!-- Suggested .env Configuration -->
            <div class="section">
                <h2><span class="icon">📝</span> Suggested .env Configuration</h2>
                <p style="margin-bottom: 10px; color: #666;">
                    Copy this configuration to your .env file:
                </p>
                <div class="code-block"><?php echo htmlspecialchars(ServerDetector::suggestEnvConfig()); ?></div>
            </div>
            
            <!-- Server Variables -->
            <div class="section">
                <h2><span class="icon">🔧</span> Server Variables (Debug Info)</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>DOCUMENT_ROOT</label>
                        <div class="value" style="font-size: 12px;">
                            <?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>SCRIPT_NAME</label>
                        <div class="value" style="font-size: 12px;">
                            <?php echo htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>REQUEST_URI</label>
                        <div class="value" style="font-size: 12px;">
                            <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>HTTP_HOST</label>
                        <div class="value" style="font-size: 12px;">
                            <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="validate_config.php" class="btn-primary">
                    Run Full Configuration Validator
                </a>
                <a href="<?php echo url('index.php'); ?>" class="btn-primary" style="margin-left: 10px;">
                    Go to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
