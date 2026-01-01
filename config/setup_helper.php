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
        'module_installer_complete' => false,
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
                                $status['current_step'] = 'module_installer';
                                
                                // Check if module installer has been completed
                                $status['module_installer_complete'] = isModuleInstallerComplete($conn);
                                
                                // Setup is complete only if module installer is also complete
                                if ($status['module_installer_complete']) {
                                    $status['setup_complete'] = true;
                                    $status['current_step'] = 'complete';
                                }
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
        case 'module_installer':
            return APP_URL . '/setup/install_tables.php';
        case 'complete':
            return APP_URL . '/public/index.php';
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

/**
 * Check if module installer has been completed
 * 
 * @param mysqli $conn Database connection
 * @return bool True if module installer has been completed
 */
function isModuleInstallerComplete($conn = null) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check session flag first (for current session)
    if (isset($_SESSION['module_installer_complete']) && $_SESSION['module_installer_complete'] === true) {
        return true;
    }
    
    // Check if a persistent flag exists in the database
    // We'll use the system_settings table if it exists, or check for a marker file
    $close_conn = false;
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                return false;
            }
            $close_conn = true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Check if system_settings table exists
    $result = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($result && $result->num_rows > 0) {
        $result->free();
        
        // Check for module_installer_complete setting
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        if ($stmt) {
            $key = 'module_installer_complete';
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                if ($close_conn) {
                    $conn->close();
                }
                return $row['setting_value'] === '1' || $row['setting_value'] === 'true';
            }
            $stmt->close();
        }
    }
    
    if ($close_conn) {
        $conn->close();
    }
    
    // Check for marker file as fallback
    $marker_file = __DIR__ . '/../.module_installer_complete';
    return file_exists($marker_file);
}

/**
 * Mark module installer as completed
 * 
 * @param mysqli $conn Database connection (optional)
 * @return bool True if successfully marked as complete
 */
function markModuleInstallerComplete($conn = null) {
    // Set session flag
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['module_installer_complete'] = true;
    
    // Try to save to database if system_settings table exists
    $close_conn = false;
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                // Fall back to file marker
                return createModuleInstallerMarkerFile();
            }
            $close_conn = true;
        } catch (Exception $e) {
            // Fall back to file marker
            return createModuleInstallerMarkerFile();
        }
    }
    
    // Check if system_settings table exists
    $result = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($result && $result->num_rows > 0) {
        $result->free();
        
        // Insert or update the setting
        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES (?, '1', NOW()) 
            ON DUPLICATE KEY UPDATE setting_value = '1', updated_at = NOW()
        ");
        
        if ($stmt) {
            $key = 'module_installer_complete';
            $stmt->bind_param('s', $key);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($close_conn) {
                $conn->close();
            }
            
            if ($success) {
                return true;
            }
        }
    }
    
    if ($close_conn) {
        $conn->close();
    }
    
    // Fall back to file marker
    return createModuleInstallerMarkerFile();
}

/**
 * Create a marker file to indicate module installer completion
 * 
 * @return bool True if file was created successfully
 */
function createModuleInstallerMarkerFile() {
    $marker_file = __DIR__ . '/../.module_installer_complete';
    return file_put_contents($marker_file, date('Y-m-d H:i:s')) !== false;
}
?>
