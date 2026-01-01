<?php
/**
 * Property-Based Tests for Module Categories
 * Feature: unified-module-installer, Property 5: Category Grouping Correctness
 * Validates: Requirements 2.5
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/module_discovery.php';
require_once __DIR__ . '/../includes/module_categories.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Module Categories\n";
echo "===================================================\n\n";

/**
 * Property 5: Category Grouping Correctness
 * For any set of modules, when displayed, each module should appear exactly once 
 * under its designated category, and all categories should be displayed in the correct order.
 */
$framework->test(
    'Property 5: Category Grouping Correctness - Each Module Appears Once',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        closeConnection($conn);
        
        return $modules;
    },
    function($modules) {
        // Group modules by category
        $grouped = get_modules_by_category($modules);
        
        // Property: Each module should appear exactly once across all categories
        $seen_modules = [];
        
        foreach ($grouped as $category => $category_modules) {
            foreach ($category_modules as $module_name => $module_data) {
                if (isset($seen_modules[$module_name])) {
                    throw new Exception("Module '$module_name' appears in multiple categories: {$seen_modules[$module_name]} and $category");
                }
                $seen_modules[$module_name] = $category;
            }
        }
        
        // Property: All original modules should be present in grouped result
        foreach ($modules as $module_name => $module_data) {
            if (!isset($seen_modules[$module_name])) {
                throw new Exception("Module '$module_name' missing from grouped categories");
            }
        }
        
        // Property: No extra modules should be added
        if (count($seen_modules) !== count($modules)) {
            throw new Exception("Module count mismatch: " . count($seen_modules) . " in groups vs " . count($modules) . " original");
        }
        
        return true;
    }
);

/**
 * Property: Modules are in their designated category
 * For any module, it should appear in the category specified in its metadata
 */
$framework->test(
    'Property: Modules In Designated Category',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        closeConnection($conn);
        
        return $modules;
    },
    function($modules) {
        $grouped = get_modules_by_category($modules);
        
        // Property: Each module should be in its designated category
        foreach ($modules as $module_name => $module_data) {
            $expected_category = $module_data['category'] ?? 'Other';
            
            // Find which category the module is in
            $found_in_category = null;
            foreach ($grouped as $category => $category_modules) {
                if (isset($category_modules[$module_name])) {
                    $found_in_category = $category;
                    break;
                }
            }
            
            if ($found_in_category !== $expected_category) {
                throw new Exception("Module '$module_name' expected in category '$expected_category' but found in '$found_in_category'");
            }
        }
        
        return true;
    }
);

/**
 * Property: All defined categories are present
 * For any grouping, all categories from get_module_categories() should be present
 */
$framework->test(
    'Property: All Categories Present',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        closeConnection($conn);
        
        return $modules;
    },
    function($modules) {
        $grouped = get_modules_by_category($modules);
        $expected_categories = get_module_categories();
        
        // Property: All expected categories should be present as keys
        foreach ($expected_categories as $category) {
            if (!isset($grouped[$category])) {
                throw new Exception("Expected category '$category' not found in grouped result");
            }
        }
        
        return true;
    }
);

/**
 * Property: Category order is preserved
 * For any grouping, categories should appear in the order defined by get_module_categories()
 */
$framework->test(
    'Property: Category Order Preserved',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        closeConnection($conn);
        
        return $modules;
    },
    function($modules) {
        $grouped = get_modules_by_category($modules);
        $expected_order = get_module_categories();
        $actual_order = array_keys($grouped);
        
        // Property: Order should match
        if ($expected_order !== $actual_order) {
            throw new Exception("Category order mismatch. Expected: " . implode(', ', $expected_order) . " Got: " . implode(', ', $actual_order));
        }
        
        return true;
    }
);

/**
 * Property: get_module_category returns correct category
 * For any module, get_module_category should return the same category as in its metadata
 */
$framework->test(
    'Property: get_module_category Correctness',
    function() {
        $modules = get_available_module_names();
        return Generators::element($modules);
    },
    function($module_name) {
        $metadata = get_module_metadata($module_name);
        $expected_category = $metadata['category'] ?? 'Other';
        $actual_category = get_module_category($module_name);
        
        // Property: Categories should match
        if ($expected_category !== $actual_category) {
            throw new Exception("Category mismatch for '$module_name'. Expected: $expected_category, Got: $actual_category");
        }
        
        return true;
    }
);

/**
 * Property: get_modules_in_category returns only modules from that category
 * For any category, all returned modules should belong to that category
 */
$framework->test(
    'Property: get_modules_in_category Correctness',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        closeConnection($conn);
        
        $categories = get_module_categories();
        $category = Generators::element($categories);
        
        return ['modules' => $modules, 'category' => $category];
    },
    function($data) {
        $modules = $data['modules'];
        $category = $data['category'];
        
        $category_modules = get_modules_in_category($category, $modules);
        
        // Property: All returned modules should have the specified category
        foreach ($category_modules as $module_name => $module_data) {
            $module_category = $module_data['category'] ?? 'Other';
            if ($module_category !== $category) {
                throw new Exception("Module '$module_name' in category '$module_category' returned for category '$category'");
            }
        }
        
        // Property: All modules with this category should be returned
        foreach ($modules as $module_name => $module_data) {
            $module_category = $module_data['category'] ?? 'Other';
            if ($module_category === $category && !isset($category_modules[$module_name])) {
                throw new Exception("Module '$module_name' with category '$category' not returned by get_modules_in_category");
            }
        }
        
        return true;
    }
);

// Print results
$framework->printResults();

// Exit with appropriate code
$results = $framework->getResults();
$allPassed = array_reduce($results, fn($carry, $r) => $carry && $r['success'], true);
exit($allPassed ? 0 : 1);
?>
