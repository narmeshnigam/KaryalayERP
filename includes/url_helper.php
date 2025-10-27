<?php
/**
 * URL Helper Functions
 * 
 * Provides utilities for generating proper URLs throughout the application.
 * Uses APP_URL constant from config to ensure paths work in any environment.
 */

/**
 * Generate a full URL from a relative path
 * 
 * @param string $path Relative path from public folder (e.g., 'login.php', 'employee/index.php')
 * @param array $params Optional query parameters as key-value array
 * @return string Full URL
 * 
 * Example:
 *   url('login.php') → 'http://yoursite.com/KaryalayERP/public/login.php'
 *   url('employee/view.php', ['id' => 5]) → 'http://yoursite.com/KaryalayERP/public/employee/view.php?id=5'
 */
function url($path, $params = []) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    
    // Build base URL
    $url = APP_URL . '/public/' . $path;
    
    // Add query parameters if provided
    if (!empty($params)) {
        $query = http_build_query($params);
        $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
    }
    
    return $url;
}

/**
 * Generate URL for asset files (images, CSS, JS)
 * 
 * @param string $path Relative path from project root (e.g., 'assets/icons/dashboard.png')
 * @return string Full asset URL
 * 
 * Example:
 *   asset('assets/icons/dashboard.png') → 'http://yoursite.com/KaryalayERP/assets/icons/dashboard.png'
 */
function asset($path) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    
    return APP_URL . '/' . $path;
}

/**
 * Redirect to another page
 * 
 * @param string $path Relative path from public folder or full URL
 * @param array $params Optional query parameters
 * @param int $status_code HTTP status code (default: 302)
 * @return void
 * 
 * Example:
 *   redirect('login.php'); // Redirects to login page
 *   redirect('employee/view.php', ['id' => 5]); // With parameters
 *   redirect_url('https://google.com'); // External redirect
 */
function redirect($path, $params = [], $status_code = 302) {
    // Check if it's already a full URL
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        $url = $params ? $path . '?' . http_build_query($params) : $path;
    } else {
        $url = url($path, $params);
    }
    
    header('Location: ' . $url, true, $status_code);
    exit;
}

/**
 * Get current page filename
 * 
 * @return string Current page filename (e.g., 'index.php')
 */
function current_page() {
    return basename($_SERVER['PHP_SELF']);
}

/**
 * Get current full path
 * 
 * @return string Current full path
 */
function current_path() {
    return $_SERVER['PHP_SELF'] ?? '';
}

/**
 * Check if current page is the given page
 * 
 * @param string $page Page filename to check
 * @return bool
 */
function is_page($page) {
    return current_page() === $page;
}

/**
 * Check if current path contains given string
 * 
 * @param string $path_part Part of path to check
 * @return bool
 */
function is_path($path_part) {
    return strpos(current_path(), $path_part) !== false;
}

/**
 * Generate relative path based on current location
 * Useful for include statements
 * 
 * @param int $levels Number of levels to go up (1 = ../, 2 = ../../)
 * @return string Relative path prefix
 */
function relative_path($levels = 1) {
    return str_repeat('../', $levels);
}

/**
 * Build query string from current URL, optionally adding/modifying parameters
 * 
 * @param array $new_params Parameters to add or modify
 * @param array $remove_params Parameter keys to remove
 * @return string Query string (including ?)
 */
function build_query_string($new_params = [], $remove_params = []) {
    $current_params = $_GET;
    
    // Remove specified parameters
    foreach ($remove_params as $key) {
        unset($current_params[$key]);
    }
    
    // Add/modify parameters
    $current_params = array_merge($current_params, $new_params);
    
    if (empty($current_params)) {
        return '';
    }
    
    return '?' . http_build_query($current_params);
}

/**
 * Generate back URL with fallback
 * 
 * @param string $fallback Fallback URL if referer is not available
 * @return string Back URL
 */
function back_url($fallback = 'index.php') {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Check if referer is from same domain
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
        return $referer;
    }
    
    return url($fallback);
}

/**
 * Safe redirect to previous page or fallback
 * 
 * @param string $fallback Fallback page if referer not available
 * @return void
 */
function redirect_back($fallback = 'index.php') {
    $url = back_url($fallback);
    header('Location: ' . $url);
    exit;
}

/**
 * Generate pagination URL
 * 
 * @param int $page Page number
 * @return string URL with page parameter
 */
function pagination_url($page) {
    return current_path() . build_query_string(['page' => $page]);
}

/**
 * Check if URL is absolute
 * 
 * @param string $url URL to check
 * @return bool
 */
function is_absolute_url($url) {
    return preg_match('/^https?:\/\//', $url) === 1;
}

/**
 * Convert relative URL to absolute if needed
 * 
 * @param string $url URL to convert
 * @return string Absolute URL
 */
function to_absolute_url($url) {
    if (is_absolute_url($url)) {
        return $url;
    }
    
    return url($url);
}
?>
