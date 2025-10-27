<?php
/**
 * Server Environment Detection Utility
 * 
 * Automatically detects the server environment and generates appropriate URLs.
 * Supports localhost, production, subdirectories, and various configurations.
 */

class ServerDetector {
    
    private static $detected = null;
    
    /**
     * Detect and return server environment details
     * 
     * @return array Server environment information
     */
    public static function detect() {
        if (self::$detected !== null) {
            return self::$detected;
        }
        
        $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $server_port = $_SERVER['SERVER_PORT'] ?? '80';
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $request_scheme = self::detectScheme();
        
        // Detect if localhost
        $is_localhost = in_array($server_name, ['localhost', '127.0.0.1', '::1']);
        
        // Detect base path (project directory in URL)
        $base_path = self::detectBasePath($script_name);
        
        // Build base URL
        $base_url = self::buildBaseUrl($request_scheme, $server_name, $server_port, $base_path);
        
        // Detect environment type
        $environment = self::detectEnvironment($is_localhost, $server_name);
        
        self::$detected = [
            'is_localhost' => $is_localhost,
            'server_name' => $server_name,
            'server_port' => $server_port,
            'scheme' => $request_scheme,
            'base_path' => $base_path,
            'base_url' => $base_url,
            'environment' => $environment,
            'has_subdirectory' => !empty($base_path),
            'full_url' => $request_scheme . '://' . $server_name . 
                         ($server_port != '80' && $server_port != '443' ? ':' . $server_port : '') . 
                         $_SERVER['REQUEST_URI']
        ];
        
        return self::$detected;
    }
    
    /**
     * Detect HTTP scheme (http or https)
     * 
     * @return string 'http' or 'https'
     */
    private static function detectScheme() {
        // Check HTTPS server variable
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }
        
        // Check forwarded protocol (for proxies/load balancers)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
        }
        
        // Check forwarded SSL
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return 'https';
        }
        
        // Check port
        if ($_SERVER['SERVER_PORT'] == 443) {
            return 'https';
        }
        
        return 'http';
    }
    
    /**
     * Detect base path (project directory)
     * 
     * @param string $script_name Current script name
     * @return string Base path without trailing slash
     */
    private static function detectBasePath($script_name) {
    // Get the script directory
    $script_dir = dirname($script_name);
        
    // Remove known web entry directories (public, setup) from the end so we point to project root
    $base_path = preg_replace('#/(public|setup)(/.*)?$#', '', $script_dir);
        
        // Remove trailing slash
        $base_path = rtrim($base_path, '/');
        
        // Return empty string if at root
        return $base_path === '/' ? '' : $base_path;
    }
    
    /**
     * Build complete base URL
     * 
     * @param string $scheme http or https
     * @param string $server_name Domain name
     * @param string $port Port number
     * @param string $base_path Base path
     * @return string Complete base URL
     */
    private static function buildBaseUrl($scheme, $server_name, $port, $base_path) {
        $url = $scheme . '://' . $server_name;
        
        // Add port if non-standard
        if (($scheme === 'http' && $port != '80') || ($scheme === 'https' && $port != '443')) {
            $url .= ':' . $port;
        }
        
        // Add base path
        $url .= $base_path;
        
        return $url;
    }
    
    /**
     * Detect environment type
     * 
     * @param bool $is_localhost Is localhost
     * @param string $server_name Server name
     * @return string Environment type
     */
    private static function detectEnvironment($is_localhost, $server_name) {
        if ($is_localhost) {
            return 'development';
        }
        
        // Check for staging/test domains
        if (preg_match('/\b(staging|test|dev|demo)\b/i', $server_name)) {
            return 'staging';
        }
        
        return 'production';
    }
    
    /**
     * Get auto-detected APP_URL
     * 
     * @return string Auto-detected base URL
     */
    public static function getAppUrl() {
        $info = self::detect();
        return $info['base_url'];
    }
    
    /**
     * Check if running on localhost
     * 
     * @return bool
     */
    public static function isLocalhost() {
        $info = self::detect();
        return $info['is_localhost'];
    }
    
    /**
     * Check if running in subdirectory
     * 
     * @return bool
     */
    public static function hasSubdirectory() {
        $info = self::detect();
        return $info['has_subdirectory'];
    }
    
    /**
     * Get environment type
     * 
     * @return string 'development', 'staging', or 'production'
     */
    public static function getEnvironment() {
        $info = self::detect();
        return $info['environment'];
    }
    
    /**
     * Get base path (subdirectory)
     * 
     * @return string Base path
     */
    public static function getBasePath() {
        $info = self::detect();
        return $info['base_path'];
    }
    
    /**
     * Check if using HTTPS
     * 
     * @return bool
     */
    public static function isHttps() {
        $info = self::detect();
        return $info['scheme'] === 'https';
    }
    
    /**
     * Get full detection information
     * 
     * @return array All detection details
     */
    public static function getInfo() {
        return self::detect();
    }
    
    /**
     * Generate suggested .env configuration
     * 
     * @return string Suggested .env content
     */
    public static function suggestEnvConfig() {
        $info = self::detect();
        
        $config = "# Auto-detected configuration\n";
        $config .= "# Generated on " . date('Y-m-d H:i:s') . "\n\n";
        
        $config .= "# Server Information\n";
        $config .= "APP_URL=" . $info['base_url'] . "\n";
        $config .= "ENVIRONMENT=" . $info['environment'] . "\n\n";
        
        $config .= "# Database Configuration\n";
        $config .= "DB_HOST=localhost\n";
        
        if ($info['is_localhost']) {
            $config .= "DB_USER=root\n";
            $config .= "DB_PASS=\n";
        } else {
            $config .= "DB_USER=your_db_user\n";
            $config .= "DB_PASS=your_secure_password\n";
        }
        
        $config .= "DB_NAME=karyalay_db\n";
        $config .= "DB_CHARSET=utf8mb4\n\n";
        
        $config .= "# Application Configuration\n";
        $config .= "APP_NAME=Karyalay ERP\n";
        $config .= "SESSION_NAME=karyalay_session\n";
        $config .= "SESSION_LIFETIME=3600\n\n";
        
        $config .= "# Environment Settings\n";
        $config .= "TIMEZONE=Asia/Kolkata\n";
        $config .= "DEBUG_MODE=" . ($info['is_localhost'] ? 'true' : 'false') . "\n";
        
        return $config;
    }
    
    /**
     * Reset cached detection (for testing)
     */
    public static function reset() {
        self::$detected = null;
    }
}

// Convenience functions for global use
if (!function_exists('detect_server')) {
    function detect_server() {
        return ServerDetector::detect();
    }
}

if (!function_exists('get_auto_app_url')) {
    function get_auto_app_url() {
        return ServerDetector::getAppUrl();
    }
}

if (!function_exists('is_localhost')) {
    function is_localhost() {
        return ServerDetector::isLocalhost();
    }
}

if (!function_exists('server_environment')) {
    function server_environment() {
        return ServerDetector::getEnvironment();
    }
}
?>
