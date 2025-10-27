<?php
/**
 * Environment Variable Loader
 * 
 * This file loads environment variables from a .env file
 * and makes them available through getenv() and $_ENV
 */

class EnvLoader {
    
    /**
     * Load environment variables from .env file
     * 
     * @param string $path Path to the .env file
     * @return bool Success status
     */
    public static function load($path) {
        if (!file_exists($path)) {
            // .env doesn't exist - auto-detection will be used
            return false;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse line
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                // Set environment variable
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get environment variable with optional default
     * 
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed Variable value or default
     */
    public static function get($key, $default = null) {
        $value = getenv($key);
        
        if ($value === false) {
            $value = $_ENV[$key] ?? $default;
        }
        
        return $value;
    }
    
    /**
     * Check if environment variable exists
     * 
     * @param string $key Variable name
     * @return bool
     */
    public static function has($key) {
        return getenv($key) !== false || isset($_ENV[$key]);
    }
    
    /**
     * Set environment variable
     * 
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public static function set($key, $value) {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
    
    /**
     * Check if .env file exists
     * 
     * @param string $path Path to check
     * @return bool
     */
    public static function envFileExists($path = null) {
        $path = $path ?? __DIR__ . '/../.env';
        return file_exists($path);
    }
    
    /**
     * Check if key is empty or missing in .env
     * 
     * @param string $key Variable name
     * @return bool True if missing or empty
     */
    public static function isMissing($key) {
        $value = self::get($key);
        return empty($value);
    }
}

// Auto-load .env file from project root
$env_file = __DIR__ . '/../.env';
$env_loaded = EnvLoader::load($env_file);

// Store whether .env was loaded for diagnostics
define('ENV_FILE_LOADED', $env_loaded);
?>
