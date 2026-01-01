<?php
/**
 * Property-Based Tests for Module Discovery
 * Feature: unified-module-installer, Property 1: Module Discovery Completeness
 * Validates: Requirements 1.2, 6.3
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/module_discovery.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Module Discovery\n";
echo "=================================================\n\n";

/**
 * Property 1: Module Discovery Completeness
 * For any system state, when the module installer loads, all available modules 
 * (those with setup scripts in the scripts/ directory) should be displayed with 
 * their current installation status, name, icon, category, and description.
 */
$framework->test(
    'Property 1: Module Discovery Completeness',
    function() {
        // Generator: Create a database connection (system state)
        return createConnection(true);
    },
    function($conn) {
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get all modules from discovery
        $modules = discover_modules($conn);
        
        // Get all module names from registry
        $registry_modules = get_available_module_names();
        
        // Property: All modules in registry should be discovered
        foreach ($registry_modules as $module_name) {
            if (!isset($modules[$module_name])) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' from registry not found in discovered modules");
            }
            
            $module = $modules[$module_name];
            
            // Check required fields are present
            $required_fields = ['name', 'display_name', 'description', 'icon', 'category', 'setup_script', 'tables', 'installed'];
            foreach ($required_fields as $field) {
                if (!isset($module[$field])) {
                    closeConnection($conn);
                    throw new Exception("Module '$module_name' missing required field: $field");
                }
            }
            
            // Check installation status is boolean
            if (!is_bool($module['installed'])) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' has non-boolean installation status");
            }
        }
        
        // Property: All discovered modules should be in registry
        foreach ($modules as $module_name => $module_data) {
            if (!in_array($module_name, $registry_modules)) {
                closeConnection($conn);
                throw new Exception("Discovered module '$module_name' not found in registry");
            }
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Additional property: Module metadata consistency
 * For any module, its metadata should be consistent across multiple calls
 */
$framework->test(
    'Property: Module Metadata Consistency',
    function() {
        // Generator: Pick a random module name
        $modules = get_available_module_names();
        return Generators::element($modules);
    },
    function($module_name) {
        // Get metadata twice
        $metadata1 = get_module_metadata($module_name);
        $metadata2 = get_module_metadata($module_name);
        
        // Property: Metadata should be identical
        if ($metadata1 !== $metadata2) {
            throw new Exception("Module metadata inconsistent for '$module_name'");
        }
        
        // Property: Metadata should not be null for valid module
        if ($metadata1 === null) {
            throw new Exception("Module metadata is null for valid module '$module_name'");
        }
        
        return true;
    }
);

/**
 * Property: Setup script paths are valid
 * For any module, the setup script path should point to an existing file
 */
$framework->test(
    'Property: Setup Script Paths Valid',
    function() {
        $modules = get_available_module_names();
        return Generators::element($modules);
    },
    function($module_name) {
        $metadata = get_module_metadata($module_name);
        
        if (!$metadata) {
            throw new Exception("No metadata for module '$module_name'");
        }
        
        $script_path = __DIR__ . '/../' . $metadata['setup_script'];
        
        // Property: Setup script file should exist
        if (!file_exists($script_path)) {
            throw new Exception("Setup script does not exist: {$metadata['setup_script']} for module '$module_name'");
        }
        
        return true;
    }
);

/**
 * Property: Table existence check is consistent
 * For any table name, checking its existence multiple times should give the same result
 */
$framework->test(
    'Property: Table Existence Check Consistency',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get a random module and one of its tables
        $modules = get_available_module_names();
        $module_name = Generators::element($modules);
        $metadata = get_module_metadata($module_name);
        $table = Generators::element($metadata['tables']);
        
        return ['conn' => $conn, 'table' => $table];
    },
    function($data) {
        $conn = $data['conn'];
        $table = $data['table'];
        
        // Check table existence twice
        $exists1 = table_exists($conn, $table);
        $exists2 = table_exists($conn, $table);
        
        closeConnection($conn);
        
        // Property: Results should be identical
        if ($exists1 !== $exists2) {
            throw new Exception("Table existence check inconsistent for table '$table'");
        }
        
        return true;
    }
);

/**
 * Property: Module installation status is deterministic
 * For any module, checking installation status multiple times should give the same result
 */
$framework->test(
    'Property: Module Installation Status Deterministic',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $modules = get_available_module_names();
        $module_name = Generators::element($modules);
        
        return ['conn' => $conn, 'module' => $module_name];
    },
    function($data) {
        $conn = $data['conn'];
        $module_name = $data['module'];
        
        // Check installation status twice
        $installed1 = check_module_installed($conn, $module_name);
        $installed2 = check_module_installed($conn, $module_name);
        
        closeConnection($conn);
        
        // Property: Results should be identical
        if ($installed1 !== $installed2) {
            throw new Exception("Module installation status inconsistent for module '$module_name'");
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
