<?php
/**
 * Module Categories
 * Defines module categories and provides functions to organize modules by category
 */

/**
 * Get all module categories in display order
 * 
 * @return array Array of category names
 */
function get_module_categories(): array {
    return [
        'Core',
        'Finance',
        'HR',
        'Operations',
        'CRM',
        'Other'
    ];
}

/**
 * Get modules grouped by category
 * 
 * @param array $modules Array of module definitions (from discover_modules)
 * @return array Associative array with categories as keys and module arrays as values
 */
function get_modules_by_category(array $modules): array {
    $categories = get_module_categories();
    $grouped = [];
    
    // Initialize all categories with empty arrays
    foreach ($categories as $category) {
        $grouped[$category] = [];
    }
    
    // Group modules by their category
    foreach ($modules as $module_name => $module_data) {
        $category = $module_data['category'] ?? 'Other';
        
        // Ensure category exists in our list
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        
        $grouped[$category][$module_name] = $module_data;
    }
    
    return $grouped;
}

/**
 * Get the category for a specific module
 * 
 * @param string $module_name Module identifier
 * @return string|null Category name or null if module not found
 */
function get_module_category(string $module_name): ?string {
    require_once __DIR__ . '/module_discovery.php';
    
    $metadata = get_module_metadata($module_name);
    
    if (!$metadata) {
        return null;
    }
    
    return $metadata['category'] ?? 'Other';
}

/**
 * Get all modules in a specific category
 * 
 * @param string $category_name Category name
 * @param array $modules Array of module definitions (from discover_modules)
 * @return array Array of modules in the specified category
 */
function get_modules_in_category(string $category_name, array $modules): array {
    $category_modules = [];
    
    foreach ($modules as $module_name => $module_data) {
        if (($module_data['category'] ?? 'Other') === $category_name) {
            $category_modules[$module_name] = $module_data;
        }
    }
    
    return $category_modules;
}

/**
 * Get category display information
 * 
 * @return array Associative array with category metadata
 */
function get_category_info(): array {
    return [
        'Core' => [
            'name' => 'Core',
            'description' => 'Essential system modules',
            'icon' => '⚙️',
            'order' => 1
        ],
        'Finance' => [
            'name' => 'Finance',
            'description' => 'Financial management modules',
            'icon' => '💰',
            'order' => 2
        ],
        'HR' => [
            'name' => 'HR',
            'description' => 'Human resources modules',
            'icon' => '👥',
            'order' => 3
        ],
        'Operations' => [
            'name' => 'Operations',
            'description' => 'Operational management modules',
            'icon' => '🔧',
            'order' => 4
        ],
        'CRM' => [
            'name' => 'CRM',
            'description' => 'Customer relationship modules',
            'icon' => '📊',
            'order' => 5
        ],
        'Other' => [
            'name' => 'Other',
            'description' => 'Additional utility modules',
            'icon' => '📦',
            'order' => 6
        ]
    ];
}
?>
