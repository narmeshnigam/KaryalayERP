<?php
/**
 * Property-Based Tests for Dependency Information Display
 * Feature: unified-module-installer, Property 6: Dependency Information Display
 * Validates: Requirements 3.2, 3.3, 3.4, 7.4
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/module_discovery.php';
require_once __DIR__ . '/../includes/module_categories.php';
require_once __DIR__ . '/../includes/dependency_resolver.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Dependency Information Display\n";
echo "================================================================\n\n";

/**
 * Property 6: Dependency Information Display - Forward Dependencies
 * For any module with dependencies, when its details are displayed, 
 * all dependency relationships (modules it depends on) should be clearly shown.
 */
$framework->test(
    'Property 6: Forward Dependencies Display Completeness',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get all modules
        $modules = discover_modules($conn);
        $dependencies = load_module_dependencies();
        
        // Find modules that have dependencies
        $modules_with_deps = [];
        foreach ($dependencies as $module_name => $deps) {
            if (!empty($deps) && isset($modules[$module_name])) {
                $modules_with_deps[] = $module_name;
            }
        }
        
        if (empty($modules_with_deps)) {
            closeConnection($conn);
            throw new Exception('No modules with dependencies found for testing');
        }
        
        // Pick a random module with dependencies
        $module_name = Generators::element($modules_with_deps);
        
        return [
            'conn' => $conn,
            'module_name' => $module_name,
            'module' => $modules[$module_name],
            'dependencies' => $dependencies[$module_name]
        ];
    },
    function($data) {
        $conn = $data['conn'];
        $module_name = $data['module_name'];
        $module = $data['module'];
        $expected_deps = $data['dependencies'];
        
        // Get dependencies using the resolver
        $actual_deps = get_direct_dependencies($module_name);
        
        // Property: All expected dependencies should be returned
        foreach ($expected_deps as $dep) {
            if (!in_array($dep, $actual_deps)) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' missing dependency '$dep' in display");
            }
        }
        
        // Property: No extra dependencies should be shown
        foreach ($actual_deps as $dep) {
            if (!in_array($dep, $expected_deps)) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' has unexpected dependency '$dep' in display");
            }
        }
        
        // Property: Dependency count should match
        if (count($actual_deps) !== count($expected_deps)) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' dependency count mismatch");
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property 6: Backward Dependencies Display (Dependents)
 * For any module, when its details are displayed, all modules that depend on it 
 * should be clearly shown.
 */
$framework->test(
    'Property 6: Backward Dependencies Display Completeness',
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
        
        // Get modules that depend on this one
        $dependents = get_dependent_modules($module_name);
        
        // Verify by checking each dependent's dependencies
        $dependencies = load_module_dependencies();
        $expected_dependents = [];
        
        foreach ($dependencies as $mod => $deps) {
            if (in_array($module_name, $deps)) {
                $expected_dependents[] = $mod;
            }
        }
        
        // Property: All expected dependents should be found
        foreach ($expected_dependents as $dep) {
            if (!in_array($dep, $dependents)) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' missing dependent '$dep' in display");
            }
        }
        
        // Property: No extra dependents should be shown
        foreach ($dependents as $dep) {
            if (!in_array($dep, $expected_dependents)) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' has unexpected dependent '$dep' in display");
            }
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property 6: Table Information Display
 * For any module, when its details are displayed, all database tables 
 * that will be created should be shown.
 */
$framework->test(
    'Property 6: Table Information Display Completeness',
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
        $module_name = $data['module_name'];
        $module = $data['module'];
        
        // Property: Module must have tables field
        if (!isset($module['tables'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' missing tables information");
        }
        
        // Property: Tables must be an array
        if (!is_array($module['tables'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' tables is not an array");
        }
        
        // Property: Tables array should not be empty
        if (empty($module['tables'])) {
            closeConnection($conn);
            throw new Exception("Module '$module_name' has empty tables array");
        }
        
        // Property: All table names should be non-empty strings
        foreach ($module['tables'] as $table) {
            if (!is_string($table) || empty($table)) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' has invalid table name");
            }
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property 6: Dependency Relationship Symmetry
 * For any two modules A and B, if A depends on B, then B should list A as a dependent.
 */
$framework->test(
    'Property 6: Dependency Relationship Symmetry',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $dependencies = load_module_dependencies();
        
        // Find a module with dependencies
        $modules_with_deps = array_filter($dependencies, fn($deps) => !empty($deps));
        
        if (empty($modules_with_deps)) {
            closeConnection($conn);
            throw new Exception('No modules with dependencies found');
        }
        
        $module_a = Generators::element(array_keys($modules_with_deps));
        $deps = $dependencies[$module_a];
        $module_b = Generators::element($deps);
        
        return ['conn' => $conn, 'module_a' => $module_a, 'module_b' => $module_b];
    },
    function($data) {
        $conn = $data['conn'];
        $module_a = $data['module_a'];
        $module_b = $data['module_b'];
        
        // Get A's dependencies
        $a_deps = get_direct_dependencies($module_a);
        
        // Get B's dependents
        $b_dependents = get_dependent_modules($module_b);
        
        // Property: If A depends on B, then B should list A as dependent
        if (in_array($module_b, $a_deps)) {
            if (!in_array($module_a, $b_dependents)) {
                closeConnection($conn);
                throw new Exception("Asymmetric dependency: '$module_a' depends on '$module_b', but '$module_b' doesn't list '$module_a' as dependent");
            }
        }
        
        closeConnection($conn);
        return true;
    }
);

/**
 * Property 6: Dependency Information Consistency
 * For any module, getting its dependency information multiple times 
 * should return identical results.
 */
$framework->test(
    'Property 6: Dependency Information Consistency',
    function() {
        $modules = get_available_module_names();
        $module_name = Generators::element($modules);
        
        return ['module_name' => $module_name];
    },
    function($data) {
        $module_name = $data['module_name'];
        
        // Get dependencies twice
        $deps1 = get_direct_dependencies($module_name);
        $deps2 = get_direct_dependencies($module_name);
        
        // Property: Results should be identical
        if ($deps1 !== $deps2) {
            throw new Exception("Module '$module_name' dependency information is inconsistent");
        }
        
        // Get dependents twice
        $dependents1 = get_dependent_modules($module_name);
        $dependents2 = get_dependent_modules($module_name);
        
        // Property: Results should be identical
        if ($dependents1 !== $dependents2) {
            throw new Exception("Module '$module_name' dependent information is inconsistent");
        }
        
        return true;
    }
);

/**
 * Property 6: Module Metadata Includes Dependency Information
 * For any module, its metadata should be sufficient to display all dependency information.
 */
$framework->test(
    'Property 6: Module Metadata Completeness for Dependency Display',
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
        $module_name = $data['module_name'];
        $module = $data['module'];
        
        // Property: Module must have all fields needed for dependency display
        $required_fields = ['name', 'display_name', 'icon', 'tables'];
        
        foreach ($required_fields as $field) {
            if (!isset($module[$field])) {
                closeConnection($conn);
                throw new Exception("Module '$module_name' missing required field '$field' for dependency display");
            }
        }
        
        // Property: For each dependency, we should be able to get its metadata
        $deps = get_direct_dependencies($module_name);
        foreach ($deps as $dep) {
            $dep_metadata = get_module_metadata($dep);
            if (!$dep_metadata) {
                closeConnection($conn);
                throw new Exception("Cannot get metadata for dependency '$dep' of module '$module_name'");
            }
            
            // Property: Dependency metadata should have display fields
            if (!isset($dep_metadata['display_name']) || !isset($dep_metadata['icon'])) {
                closeConnection($conn);
                throw new Exception("Dependency '$dep' missing display fields");
            }
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
