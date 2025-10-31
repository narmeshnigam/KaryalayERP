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

                        $hasStatusColumn = false;
                        $statusColumnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
                        if ($statusColumnCheck && $statusColumnCheck->num_rows > 0) {
                            $hasStatusColumn = true;
                        }
                        if ($statusColumnCheck) {
                            $statusColumnCheck->free();
                        }

                        $rolesTablesExist = false;
                        $rolesTableCheck = $conn->query("SHOW TABLES LIKE 'roles'");
                        if ($rolesTableCheck && $rolesTableCheck->num_rows > 0) {
                            $rolesTablesExist = true;
                        }
                        if ($rolesTableCheck) {
                            $rolesTableCheck->free();
                        }

                        if ($hasStatusColumn && $rolesTablesExist) {
                            $adminQuery = "
                                SELECT COUNT(*) AS count
                                FROM users u
                                LEFT JOIN user_roles ur ON ur.user_id = u.id
                                LEFT JOIN roles r ON ur.role_id = r.id
                                WHERE u.status = 'Active'
                                  AND (r.name IN ('Super Admin', 'Admin') OR r.is_system_role = 1)
                                LIMIT 1
                            ";
                        } elseif ($hasStatusColumn) {
                            $adminQuery = "SELECT COUNT(*) AS count FROM users WHERE status = 'Active'";
                        } else {
                            $adminQuery = "SELECT COUNT(*) AS count FROM users WHERE role = 'admin'";
                        }

                        $adminResult = $conn->query($adminQuery);
                        if ($adminResult) {
                            $row = $adminResult->fetch_assoc();
                            if (!empty($row['count'])) {
                                $status['admin_exists'] = true;
                                $status['setup_complete'] = true;
                                $status['current_step'] = 'complete';
                            }
                            $adminResult->free();
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
