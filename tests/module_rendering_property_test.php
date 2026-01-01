<?php
/**
 * Property-Based Tests for Module Rendering Consistency
 * Feature: unified-module-installer, Property 3: Module Rendering Consistency
 * Validates: Requirements 2.1, 2.2, 3.1
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/module_discovery.php';
require_once __DIR__ . '/../includes/module_categories.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Module Rendering Consistency\n";
echo "==============================================================\n\n";

/**
 * Property 3: Module Rendering Consistency
 * For any module in the system, when displayed in the module list, it should have 
 * a checkbox (disabled if installed), and show all required information 
 * (name, icon, category, description, installation status indicator).
 */
$framework->test(
    'Property 3: Module Rendering Consistency - All Required Fields Present',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get all modules
        $modules = discover_modules($conn);
        
        // Pick a random module
        $module_names = array_keys($modules);
        $module_name = Generators::element($module_names);
        
        return ['conn' => $conn, 'module_name' => $module_name, 'module' => $modules[$module_name]];
    },
    function($data) {
        $conn = $data['conn'];
        $module = $data['module'];
        $module_name = $data['module_name'];
        
        // Property: Module must have all required rendering fields
        $required_fields = ['name', 'display_name', 'description', 'icon', 'category', 'installed'];
        
        foreach ($required_fields as $field) {
            if (!isset($module[$field])) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' missing required rendering field: $field");
            }
        }
        
        // Property: display_name must be non-empty string
        if (!is_string($module['display_name']) || empty($module['display_name'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' has invalid display_name");
        }
        
        // Property: description must be non-empty string
        if (!is_string($module['description']) || empty($module['description'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' has invalid description");
        }
        
        // Property: icon must be non-empty string
        if (!is_string($module['icon']) || empty($module['icon'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' has invalid icon");
        }
        
        // Property: category must be non-empty string
        if (!is_string($module['category']) || empty($module['category'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' has invalid category");
        }
        
        // Property: installed must be boolean
        if (!is_bool($module['installed'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' has non-boolean installed status");
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property: Module checkbox state consistency
 * For any module, if it's installed, the checkbox should be disabled and checked
 * If not installed, the checkbox should be enabled
 */
$framework->test(
    'Property 3: Module Checkbox State Consistency',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        $module_names = array_keys($modules);
        $module_name = Generators::element($module_names);
        
        return ['conn' => $conn, 'module_name' => $module_name, 'module' => $modules[$module_name]];
    },
    function($data) {
        $conn = $data['conn'];
        $module = $data['module'];
        $module_name = $data['module_name'];
        
        // Property: Installed modules should have checkbox disabled
        // Non-installed modules should have checkbox enabled
        // This is a logical property - we verify the data structure supports this
        
        if ($module['installed']) {
            // For installed modules, we expect the UI to disable the checkbox
            // The module data should clearly indicate this state
            if (!is_bool($module['installed']) || $module['installed'] !== true) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' installed state is inconsistent");
            }
        } else {
            // For non-installed modules, checkbox should be selectable
            if (!is_bool($module['installed']) || $module['installed'] !== false) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' non-installed state is inconsistent");
            }
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property: Module category is valid
 * For any module, its category should be one of the defined categories
 */
$framework->test(
    'Property 3: Module Category Validity',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        $module_names = array_keys($modules);
        $module_name = Generators::element($module_names);
        
        return ['conn' => $conn, 'module_name' => $module_name, 'module' => $modules[$module_name]];
    },
    function($data) {
        $conn = $data['conn'];
        $module = $data['module'];
        $module_name = $data['module_name'];
        
        // Get valid categories
        $valid_categories = get_module_categories();
        
        // Property: Module category must be in the list of valid categories
        if (!in_array($module['category'], $valid_categories)) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' has invalid category: {$module['category']}");
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property: Module rendering data is immutable
 * For any module, getting its data multiple times should return identical results
 */
$framework->test(
    'Property 3: Module Rendering Data Immutability',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = get_available_module_names();
        $module_name = Generators::element($modules);
        
        return ['conn' => $conn, 'module_name' => $module_name];
    },
    function($data) {
        $conn = $data['conn'];
        $module_name = $data['module_name'];
        
        // Get module data twice
        $modules1 = discover_modules($conn);
        $modules2 = discover_modules($conn);
        
        $module1 = $modules1[$module_name] ?? null;
        $module2 = $modules2[$module_name] ?? null;
        
        // Property: Module data should be identical across calls
        if ($module1 !== $module2) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' data is not consistent across calls");
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property: All modules can be grouped by category
 * For any set of modules, grouping by category should preserve all modules
 */
$framework->test(
    'Property 3: Module Category Grouping Preserves All Modules',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = discover_modules($conn);
        
        return ['conn' => $conn, 'modules' => $modules];
    },
    function($data) {
        $conn = $data['conn'];
        $modules = $data['modules'];
        
        // Group modules by category
        $grouped = get_modules_by_category($modules);
        
        // Property: All modules should appear in exactly one category
        $grouped_module_count = 0;
        $found_modules = [];
        
        foreach ($grouped as $category => $category_modules) {
            foreach ($category_modules as $module_name => $module_data) {
                if (in_array($module_name, $found_modules)) {
                    closeConnection($conn);
                    throw new Exception("Module '$module_name' appears in multiple categories");
                }
                $found_modules[] = $module_name;
                $grouped_module_count++;
            }
        }
        
        // Property: Count of grouped modules should equal original count
        if ($grouped_module_count !== count($modules)) {
            closeConnection($conn);
            throw new Exception("Module count mismatch: original=" . count($modules) . ", grouped=$grouped_module_count");
        }
        
        // Property: All original modules should be found in groups
        foreach (array_keys($modules) as $module_name) {
            if (!in_array($module_name, $found_modules)) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' not found in category groups");
            }
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property: Module installation status indicator is consistent
 * For any module, the installed field should match the actual table existence
 */
$framework->test(
    'Property 3: Installation Status Indicator Accuracy',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = get_available_module_names();
        $module_name = Generators::element($modules);
        
        return ['conn' => $conn, 'module_name' => $module_name];
    },
    function($data) {
        $conn = $data['conn'];
        $module_name = $data['module_name'];
        
        // Get module from discovery (includes installed status)
        $modules = discover_modules($conn);
        $module = $modules[$module_name];
        
        // Check installation status directly
        $actual_installed = check_module_installed($conn, $module_name);
        
        // Property: The installed field should match the actual check
        if ($module['installed'] !== $actual_installed) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' installed status mismatch: reported={$module['installed']}, actual=$actual_installed");
        }
        
        closeConnection($conn);
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
