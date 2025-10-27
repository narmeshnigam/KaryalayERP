<?php
/**
 * Setup Helper Functions
 * 
 * Functions to detect and manage the first-time setup workflow
 */

/**
 * Check if the application has been set up
 * Returns array with setup status details
 */
function getSetupStatus() {
    $status = [
        'config_exists' => false,
        'db_connection' => false,
        'database_exists' => false,
        'users_table_exists' => false,
        'admin_exists' => false,
        'setup_complete' => false,
        'current_step' => 'database_config'
    ];
    
    // Check if config file exists and has DB credentials
    if (defined('DB_HOST') && defined('DB_NAME')) {
        $status['config_exists'] = true;
        
        // Try to connect to database server
        try {
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
            if (!$conn->connect_error) {
                $status['db_connection'] = true;
                
                // Check if database exists
                $result = $conn->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
                if ($result && $result->num_rows > 0) {
                    $status['database_exists'] = true;
                    
                    // Select the database
                    $conn->select_db(DB_NAME);
                    
                    // Check if users table exists
                    $result = $conn->query("SHOW TABLES LIKE 'users'");
                    if ($result && $result->num_rows > 0) {
                        $status['users_table_exists'] = true;
                        $status['current_step'] = 'create_admin';
                        
                        // Check if at least one admin user exists
                        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                        if ($result) {
                            $row = $result->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status['admin_exists'] = true;
                                $status['setup_complete'] = true;
                                $status['current_step'] = 'complete';
                            }
                        }
                    } else {
                        $status['current_step'] = 'create_tables';
                    }
                } else {
                    $status['current_step'] = 'create_database';
                }
                
                $conn->close();
            }
        } catch (Exception $e) {
            // Connection failed
        }
    }
    
    return $status;
}

/**
 * Check if setup is complete
 */
function isSetupComplete() {
    $status = getSetupStatus();
    return $status['setup_complete'];
}

/**
 * Get the next setup step URL
 */
function getSetupStepUrl() {
    $status = getSetupStatus();
    
    switch ($status['current_step']) {
        case 'database_config':
            return APP_URL . '/setup/database.php';
        case 'create_database':
            return APP_URL . '/setup/create_database.php';
        case 'create_tables':
            return APP_URL . '/setup/create_tables.php';
        case 'create_admin':
            return APP_URL . '/setup/create_admin.php';
        case 'complete':
            return APP_URL . '/public/branding/onboarding.php';
        default:
            return APP_URL . '/setup/index.php';
    }
}

/**
 * Update database configuration in .env file
 */
function updateDatabaseConfig($host, $user, $pass, $name) {
    $env_file = __DIR__ . '/../.env';
    
    // Read existing .env file or use template
    $env_template = __DIR__ . '/../.env.example';
    
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES);
    } elseif (file_exists($env_template)) {
        $lines = file($env_template, FILE_IGNORE_NEW_LINES);
    } else {
        // Create basic template if neither exists
        $lines = [
            '# Environment Configuration',
            '# Database Configuration',
            'DB_HOST=',
            'DB_USER=',
            'DB_PASS=',
            'DB_NAME=',
            'DB_CHARSET=utf8mb4',
            '',
            '# Application Configuration',
            'APP_NAME=Karyalay ERP',
            'APP_URL=http://localhost/KaryalayERP',
            '',
            '# Session Configuration',
            'SESSION_NAME=karyalay_session',
            'SESSION_LIFETIME=3600',
            '',
            '# Timezone',
            'TIMEZONE=Asia/Kolkata',
            '',
            '# Environment (development, production)',
            'ENVIRONMENT=development',
            '',
            '# Debug Mode (true/false)',
            'DEBUG_MODE=true'
        ];
    }
    
    // Update database credentials
    $updated_lines = [];
    $db_keys_found = ['DB_HOST' => false, 'DB_USER' => false, 'DB_PASS' => false, 'DB_NAME' => false];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Skip comments and empty lines
        if (empty($trimmed) || strpos($trimmed, '#') === 0) {
            $updated_lines[] = $line;
            continue;
        }
        
        // Parse and update database keys
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            
            switch ($key) {
                case 'DB_HOST':
                    $updated_lines[] = "DB_HOST=" . $host;
                    $db_keys_found['DB_HOST'] = true;
                    break;
                case 'DB_USER':
                    $updated_lines[] = "DB_USER=" . $user;
                    $db_keys_found['DB_USER'] = true;
                    break;
                case 'DB_PASS':
                    $updated_lines[] = "DB_PASS=" . $pass;
                    $db_keys_found['DB_PASS'] = true;
                    break;
                case 'DB_NAME':
                    $updated_lines[] = "DB_NAME=" . $name;
                    $db_keys_found['DB_NAME'] = true;
                    break;
                default:
                    $updated_lines[] = $line;
            }
        } else {
            $updated_lines[] = $line;
        }
    }
    
    // Add any missing database keys
    if (!$db_keys_found['DB_HOST']) $updated_lines[] = "DB_HOST=" . $host;
    if (!$db_keys_found['DB_USER']) $updated_lines[] = "DB_USER=" . $user;
    if (!$db_keys_found['DB_PASS']) $updated_lines[] = "DB_PASS=" . $pass;
    if (!$db_keys_found['DB_NAME']) $updated_lines[] = "DB_NAME=" . $name;
    
    // Write back to .env file
    $content = implode("\n", $updated_lines) . "\n";
    
    if (file_put_contents($env_file, $content) !== false) {
        // Reload environment variables
        require_once __DIR__ . '/env_loader.php';
        EnvLoader::load($env_file);
        return true;
    }
    
    return false;
}
?>
