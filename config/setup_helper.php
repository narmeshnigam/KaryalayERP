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
 * Update database configuration in config.php file
 */
function updateDatabaseConfig($host, $user, $pass, $name) {
    $config_file = __DIR__ . '/config.php';
    
    if (!file_exists($config_file)) {
        return false;
    }
    
    $content = file_get_contents($config_file);
    
    // Replace database configuration values
    $content = preg_replace("/define\('DB_HOST',\s*'[^']*'\);/", "define('DB_HOST', '" . addslashes($host) . "');", $content);
    $content = preg_replace("/define\('DB_USER',\s*'[^']*'\);/", "define('DB_USER', '" . addslashes($user) . "');", $content);
    $content = preg_replace("/define\('DB_PASS',\s*'[^']*'\);/", "define('DB_PASS', '" . addslashes($pass) . "');", $content);
    $content = preg_replace("/define\('DB_NAME',\s*'[^']*'\);/", "define('DB_NAME', '" . addslashes($name) . "');", $content);
    
    return file_put_contents($config_file, $content) !== false;
}
?>
