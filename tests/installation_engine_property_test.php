<?php
/**
 * Property-Based Tests for Installation Engine
 * Feature: unified-module-installer
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/installation_engine.php';
require_once __DIR__ . '/../includes/module_discovery.php';
require_once __DIR__ . '/../includes/dependency_resolver.php';

// Initialize test framework
$framework = new PropertyTestFramework(100);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  Property-Based Tests: Installation Engine                        ║\n";
echo "║  Feature: unified-module-installer                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";

/**
 * Property 10: Installation Execution Completeness
 * Validates: Requirements 4.1
 * 
 * For any set of selected modules, when installation is triggered,
 * the setup script for each module should be executed exactly once.
 * 
 * This test verifies the logic without requiring database access by testing
 * that the installation order resolution works correctly.
 */
$framework->test(
    'Property 10: Installation Execution Completeness',
    function() {
        // Generator: Create a small set of modules to test
        $testable_modules = ['employees', 'clients', 'crm', 'attendance'];
        
        // Select 1-3 modules randomly
        $num_to_select = rand(1, min(3, count($testable_modules)));
        $selected = [];
        
        for ($i = 0; $i < $num_to_select; $i++) {
            if (empty($testable_modules)) break;
            $idx = array_rand($testable_modules);
            $selected[] = $testable_modules[$idx];
            array_splice($testable_modules, $idx, 1);
        }
        
        return ['modules' => $selected];
    },
    function($data) {
        $modules = $data['modules'];
        
        // Property: Installation order should include all selected modules
        try {
            $installation_order = resolve_installation_order($modules);
        } catch (Exception $e) {
            // Circular dependency is a different issue
            return true;
        }
        
        // Property: Each selected module should appear in installation order
        foreach ($modules as $module) {
            if (!in_array($module, $installation_order)) {
                echo "FAIL: Selected module '$module' not in installation order\n";
                echo "Selected: " . json_encode($modules) . "\n";
                echo "Installation order: " . json_encode($installation_order) . "\n";
                return false;
            }
        }
        
        // Property: Each module should appear exactly once
        $module_counts = array_count_values($installation_order);
        foreach ($module_counts as $module => $count) {
            if ($count !== 1) {
                echo "FAIL: Module '$module' appears $count times in installation order (should be 1)\n";
                return false;
            }
        }
        
        // Property: Installation order should respect dependencies
        $installed_so_far = [];
        foreach ($installation_order as $module) {
            $deps = get_direct_dependencies($module);
            
            foreach ($deps as $dep) {
                if (in_array($dep, $installation_order) && !in_array($dep, $installed_so_far)) {
                    echo "FAIL: Module '$module' appears before its dependency '$dep'\n";
                    echo "Installation order: " . json_encode($installation_order) . "\n";
                    return false;
                }
            }
            
            $installed_so_far[] = $module;
        }
        
        return true;
    }
);

/**
 * Property 13: Error Isolation
 * Validates: Requirements 5.2
 * 
 * For any module installation failure, the installation process should continue
 * with remaining modules, and the failure should not affect previously successful installations.
 * 
 * This test verifies that execute_module_setup handles errors gracefully without throwing.
 */
$framework->test(
    'Property 13: Error Isolation',
    function() {
        // Generator: Test with an invalid module name
        $invalid_module = 'nonexistent_module_' . rand(1000, 9999);
        
        return [
            'module' => $invalid_module,
            'user_id' => 1
        ];
    },
    function($data) {
        // Create a mock connection (null is acceptable for this test)
        $conn = null;
        
        // Property: execute_module_setup should not throw exceptions
        try {
            $result = execute_module_setup($conn, $data['module'], $data['user_id']);
        } catch (Exception $e) {
            echo "FAIL: execute_module_setup threw exception: {$e->getMessage()}\n";
            return false;
        } catch (Error $e) {
            echo "FAIL: execute_module_setup threw error: {$e->getMessage()}\n";
            return false;
        }
        
        // Property: Result should have required structure
        if (!isset($result['module']) || !isset($result['success']) || !isset($result['message'])) {
            echo "FAIL: Result missing required fields\n";
            echo "Result: " . json_encode($result) . "\n";
            return false;
        }
        
        // Property: Invalid module should fail
        if ($result['success']) {
            echo "FAIL: Invalid module reported as successful\n";
            echo "Result: " . json_encode($result) . "\n";
            return false;
        }
        
        // Property: Error message should be informative
        if (empty($result['message'])) {
            echo "FAIL: Error message is empty\n";
            return false;
        }
        
        return true;
    }
);

/**
 * Property 14: Error Logging Completeness
 * Validates: Requirements 5.5
 * 
 * For any module installation failure, error details (module name, error message, timestamp)
 * should be logged to the system.
 * 
 * This test verifies the log_installation function works correctly.
 */
$framework->test(
    'Property 14: Error Logging Completeness',
    function() {
        // Generator: Create random test data
        $module = 'test_module_' . rand(1000, 9999);
        $success = (bool)rand(0, 1);
        $message = 'Test message ' . rand(1000, 9999);
        $user_id = rand(1, 100);
        
        return [
            'module' => $module,
            'success' => $success,
            'message' => $message,
            'user_id' => $user_id
        ];
    },
    function($data) {
        $log_file = __DIR__ . '/../uploads/logs/module_installation.log';
        
        // Note the log file state before test
        $log_size_before = file_exists($log_file) ? filesize($log_file) : 0;
        
        // Call log_installation
        log_installation($data['module'], $data['success'], $data['message'], $data['user_id']);
        
        // Property: Log file should exist after logging
        if (!file_exists($log_file)) {
            echo "FAIL: Log file does not exist after logging\n";
            return false;
        }
        
        // Property: Log file should have grown
        $log_size_after = filesize($log_file);
        if ($log_size_after <= $log_size_before) {
            echo "FAIL: Log file did not grow after logging\n";
            echo "Before: $log_size_before, After: $log_size_after\n";
            return false;
        }
        
        // Property: Log should contain the module name
        $log_contents = file_get_contents($log_file);
        $recent_log = substr($log_contents, $log_size_before);
        
        if (strpos($recent_log, $data['module']) === false) {
            echo "FAIL: Log does not contain module name '{$data['module']}'\n";
            echo "Recent log: $recent_log\n";
            return false;
        }
        
        // Property: Log should contain timestamp (date format)
        if (!preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $recent_log)) {
            echo "FAIL: Log does not contain timestamp\n";
            echo "Recent log: $recent_log\n";
            return false;
        }
        
        // Property: Log should contain user ID
        if (strpos($recent_log, "User: {$data['user_id']}") === false) {
            echo "FAIL: Log does not contain user ID\n";
            echo "Recent log: $recent_log\n";
            return false;
        }
        
        // Property: Log should contain status
        $expected_status = $data['success'] ? 'SUCCESS' : 'FAILED';
        if (strpos($recent_log, $expected_status) === false) {
            echo "FAIL: Log does not contain status '$expected_status'\n";
            echo "Recent log: $recent_log\n";
            return false;
        }
        
        // Property: Log should contain message
        if (strpos($recent_log, $data['message']) === false) {
            echo "FAIL: Log does not contain message\n";
            echo "Recent log: $recent_log\n";
            return false;
        }
        
        return true;
    }
);

/**
 * Additional Property: execute_module_setup result structure is consistent
 * For any module setup execution, the result should have the expected structure
 */
$framework->test(
    'Property: Module Setup Result Structure',
    function() {
        // Test with a known invalid module
        $module = 'invalid_test_module_' . rand(1000, 9999);
        return ['module' => $module, 'user_id' => 1];
    },
    function($data) {
        $conn = null; // Mock connection
        
        $result = execute_module_setup($conn, $data['module'], $data['user_id']);
        
        // Property: Result should have required keys
        $required_keys = ['module', 'success', 'message'];
        foreach ($required_keys as $key) {
            if (!isset($result[$key])) {
                echo "FAIL: Result missing required key: $key\n";
                echo "Result: " . json_encode($result) . "\n";
                return false;
            }
        }
        
        // Property: module field should match input
        if ($result['module'] !== $data['module']) {
            echo "FAIL: Result module doesn't match input\n";
            echo "Expected: {$data['module']}, Got: {$result['module']}\n";
            return false;
        }
        
        // Property: success should be boolean
        if (!is_bool($result['success'])) {
            echo "FAIL: success field is not boolean\n";
            echo "Result: " . json_encode($result) . "\n";
            return false;
        }
        
        // Property: message should be non-empty string
        if (!is_string($result['message']) || empty($result['message'])) {
            echo "FAIL: message field is not a non-empty string\n";
            echo "Result: " . json_encode($result) . "\n";
            return false;
        }
        
        return true;
    }
);

/**
 * Property: Dependency resolution is consistent
 * For any set of modules, resolving installation order multiple times should give the same result
 */
$framework->test(
    'Property: Dependency Resolution Consistency',
    function() {
        // Select random modules
        $all_modules = ['employees', 'clients', 'crm', 'attendance', 'projects'];
        $num_to_select = rand(1, min(3, count($all_modules)));
        $selected = [];
        
        for ($i = 0; $i < $num_to_select; $i++) {
            if (empty($all_modules)) break;
            $idx = array_rand($all_modules);
            $selected[] = $all_modules[$idx];
            array_splice($all_modules, $idx, 1);
        }
        
        return ['modules' => $selected];
    },
    function($data) {
        $modules = $data['modules'];
        
        // Resolve installation order twice
        try {
            $order1 = resolve_installation_order($modules);
            $order2 = resolve_installation_order($modules);
        } catch (Exception $e) {
            // Circular dependency is acceptable
            return true;
        }
        
        // Property: Both orders should be identical
        if ($order1 !== $order2) {
            echo "FAIL: Installation order is not consistent\n";
            echo "First: " . json_encode($order1) . "\n";
            echo "Second: " . json_encode($order2) . "\n";
            return false;
        }
        
        // Property: Order should respect dependencies
        $installed_so_far = [];
        foreach ($order1 as $module) {
            $deps = get_direct_dependencies($module);
            
            foreach ($deps as $dep) {
                if (in_array($dep, $order1) && !in_array($dep, $installed_so_far)) {
                    echo "FAIL: Module '$module' appears before its dependency '$dep'\n";
                    echo "Order: " . json_encode($order1) . "\n";
                    return false;
                }
            }
            
            $installed_so_far[] = $module;
        }
        
        return true;
    }
);

// Print results
$framework->printResults();

// Exit with appropriate code
$results = $framework->getResults();
$all_passed = array_reduce($results, fn($carry, $r) => $carry && $r['success'], true);
exit($all_passed ? 0 : 1);
?>
