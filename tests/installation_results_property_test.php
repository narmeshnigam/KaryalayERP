<?php
/**
 * Property-Based Tests for Installation Results Display
 * Feature: unified-module-installer
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';

// Initialize test framework
$framework = new PropertyTestFramework(100);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  Property-Based Tests: Installation Results Display               ║\n";
echo "║  Feature: unified-module-installer                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";

/**
 * Property 12: Installation Completion Summary
 * Validates: Requirements 4.4, 5.3
 * 
 * For any completed installation (successful or with errors), a summary should be
 * displayed showing all attempted modules with their success/failure status and
 * relevant messages.
 */
$framework->test(
    'Property 12: Installation Completion Summary',
    function() {
        // Generator: Create random installation results
        $num_modules = rand(1, 10);
        $results = [];
        
        $module_names = ['employees', 'clients', 'crm', 'invoices', 'catalog', 
                        'attendance', 'payroll', 'projects', 'documents', 'visitors'];
        
        for ($i = 0; $i < $num_modules; $i++) {
            $module = $module_names[array_rand($module_names)];
            $success = (bool)rand(0, 1);
            
            $results[] = [
                'module' => $module,
                'success' => $success,
                'message' => $success 
                    ? "Module $module installed successfully" 
                    : "Failed to install module $module: " . Generators::string(10, 50)
            ];
        }
        
        return ['results' => $results];
    },
    function($data) {
        $results = $data['results'];
        
        // Simulate the summary generation logic
        $summary = generate_installation_summary($results);
        
        // Property: Summary should exist
        if (empty($summary)) {
            echo "FAIL: Installation summary is empty\n";
            return false;
        }
        
        // Property: Summary should contain all attempted modules
        foreach ($results as $result) {
            if (strpos($summary, $result['module']) === false) {
                echo "FAIL: Summary missing module: {$result['module']}\n";
                echo "Summary: $summary\n";
                return false;
            }
        }
        
        // Property: Summary should show success/failure status for each module
        $successful_count = count(array_filter($results, fn($r) => $r['success']));
        $failed_count = count(array_filter($results, fn($r) => !$r['success']));
        
        // Check that counts are mentioned in summary
        if ($successful_count > 0 && strpos($summary, (string)$successful_count) === false) {
            echo "FAIL: Summary doesn't show successful count: $successful_count\n";
            echo "Summary: $summary\n";
            return false;
        }
        
        if ($failed_count > 0 && strpos($summary, (string)$failed_count) === false) {
            echo "FAIL: Summary doesn't show failed count: $failed_count\n";
            echo "Summary: $summary\n";
            return false;
        }
        
        // Property: Summary should include messages for each module
        foreach ($results as $result) {
            // At minimum, the module name should be present
            if (strpos($summary, $result['module']) === false) {
                echo "FAIL: Summary missing information for module: {$result['module']}\n";
                return false;
            }
        }
        
        // Property: Summary should indicate overall status
        if ($failed_count === 0) {
            // All successful - should have success indicator
            if (strpos(strtolower($summary), 'success') === false && 
                strpos($summary, '✅') === false &&
                strpos($summary, '✓') === false) {
                echo "FAIL: All-successful summary doesn't indicate success\n";
                echo "Summary: $summary\n";
                return false;
            }
        } else {
            // Has failures - should indicate mixed results or failure
            $has_failure_indicator = 
                strpos(strtolower($summary), 'fail') !== false ||
                strpos($summary, '❌') !== false ||
                strpos($summary, '✗') !== false ||
                strpos(strtolower($summary), 'error') !== false;
                
            if (!$has_failure_indicator) {
                echo "FAIL: Summary with failures doesn't indicate failure\n";
                echo "Summary: $summary\n";
                return false;
            }
        }
        
        return true;
    }
);

/**
 * Helper function to generate installation summary
 * This simulates the logic in the frontend
 */
function generate_installation_summary(array $results): string {
    $successful = array_filter($results, fn($r) => $r['success']);
    $failed = array_filter($results, fn($r) => !$r['success']);
    
    $successful_count = count($successful);
    $failed_count = count($failed);
    
    $summary = "Installation Summary\n";
    $summary .= "===================\n\n";
    
    if ($failed_count === 0) {
        $summary .= "✅ Installation Successful!\n";
        $summary .= "All $successful_count module(s) have been installed successfully.\n\n";
        
        // List all successful modules
        $summary .= "Installed Modules:\n";
        foreach ($successful as $result) {
            $summary .= "  ✓ {$result['module']}: {$result['message']}\n";
        }
    } else {
        $summary .= "Installation Complete with Errors\n";
        $summary .= "✓ $successful_count successful | ✗ $failed_count failed\n\n";
        
        if ($successful_count > 0) {
            $summary .= "Successfully Installed:\n";
            foreach ($successful as $result) {
                $summary .= "  ✓ {$result['module']}: {$result['message']}\n";
            }
            $summary .= "\n";
        }
        
        if ($failed_count > 0) {
            $summary .= "Failed Modules:\n";
            foreach ($failed as $result) {
                $summary .= "  ✗ {$result['module']}: {$result['message']}\n";
            }
        }
    }
    
    return $summary;
}

/**
 * Property 15: Retry Functionality Availability
 * Validates: Requirements 5.4
 * 
 * For any installation that completes with one or more failures, a retry option
 * should be provided for each failed module.
 */
$framework->test(
    'Property 15: Retry Functionality Availability',
    function() {
        // Generator: Create installation results with at least one failure
        $module_names = ['employees', 'clients', 'crm', 'invoices', 'catalog', 
                        'attendance', 'payroll', 'projects', 'documents', 'visitors'];
        
        // Shuffle and select unique modules
        shuffle($module_names);
        $num_modules = rand(2, min(8, count($module_names)));
        $selected_modules = array_slice($module_names, 0, $num_modules);
        
        $results = [];
        $has_failure = false;
        
        foreach ($selected_modules as $i => $module) {
            // Force at least one failure
            if ($i === 0 && !$has_failure) {
                $success = false;
                $has_failure = true;
            } else {
                $success = (bool)rand(0, 1);
                if (!$success) {
                    $has_failure = true;
                }
            }
            
            $results[] = [
                'module' => $module,
                'success' => $success,
                'message' => $success 
                    ? "Module $module installed successfully" 
                    : "Failed to install module $module: " . Generators::string(10, 50)
            ];
        }
        
        return ['results' => $results];
    },
    function($data) {
        $results = $data['results'];
        
        // Get failed modules
        $failed_modules = array_filter($results, fn($r) => !$r['success']);
        $failed_count = count($failed_modules);
        
        // Property: There should be at least one failure (by generator design)
        if ($failed_count === 0) {
            echo "FAIL: Generator should have created at least one failure\n";
            return false;
        }
        
        // Simulate checking if retry functionality is available
        $retry_available = check_retry_functionality_available($results);
        
        // Property: Retry functionality should be available when there are failures
        if (!$retry_available) {
            echo "FAIL: Retry functionality not available despite having $failed_count failed module(s)\n";
            echo "Results: " . json_encode($results) . "\n";
            return false;
        }
        
        // Property: Retry should include all failed modules
        $retry_modules = get_retry_modules($results);
        
        if (count($retry_modules) !== $failed_count) {
            echo "FAIL: Retry modules count doesn't match failed count\n";
            echo "Expected: $failed_count, Got: " . count($retry_modules) . "\n";
            return false;
        }
        
        // Property: All failed modules should be in retry list
        foreach ($failed_modules as $failed) {
            if (!in_array($failed['module'], $retry_modules)) {
                echo "FAIL: Failed module '{$failed['module']}' not in retry list\n";
                echo "Retry modules: " . json_encode($retry_modules) . "\n";
                return false;
            }
        }
        
        // Property: No successful modules should be in retry list
        $successful_modules = array_filter($results, fn($r) => $r['success']);
        foreach ($successful_modules as $successful) {
            if (in_array($successful['module'], $retry_modules)) {
                echo "FAIL: Successful module '{$successful['module']}' incorrectly in retry list\n";
                echo "Retry modules: " . json_encode($retry_modules) . "\n";
                return false;
            }
        }
        
        return true;
    }
);

/**
 * Additional Property: Retry functionality should NOT be available when all succeed
 */
$framework->test(
    'Property: No Retry When All Successful',
    function() {
        // Generator: Create installation results with all successes
        $module_names = ['employees', 'clients', 'crm', 'invoices', 'catalog'];
        
        // Shuffle and select unique modules
        shuffle($module_names);
        $num_modules = rand(1, min(5, count($module_names)));
        $selected_modules = array_slice($module_names, 0, $num_modules);
        
        $results = [];
        
        foreach ($selected_modules as $module) {
            $results[] = [
                'module' => $module,
                'success' => true,
                'message' => "Module $module installed successfully"
            ];
        }
        
        return ['results' => $results];
    },
    function($data) {
        $results = $data['results'];
        
        // Property: All should be successful
        $failed_count = count(array_filter($results, fn($r) => !$r['success']));
        
        if ($failed_count !== 0) {
            echo "FAIL: Generator should have created all successful results\n";
            return false;
        }
        
        // Property: Retry functionality should NOT be available
        $retry_available = check_retry_functionality_available($results);
        
        if ($retry_available) {
            echo "FAIL: Retry functionality should not be available when all modules succeed\n";
            return false;
        }
        
        // Property: Retry modules list should be empty
        $retry_modules = get_retry_modules($results);
        
        if (count($retry_modules) > 0) {
            echo "FAIL: Retry modules list should be empty when all succeed\n";
            echo "Retry modules: " . json_encode($retry_modules) . "\n";
            return false;
        }
        
        return true;
    }
);

/**
 * Helper function to check if retry functionality is available
 */
function check_retry_functionality_available(array $results): bool {
    $failed_modules = array_filter($results, fn($r) => !$r['success']);
    return count($failed_modules) > 0;
}

/**
 * Helper function to get list of modules that should be retried
 */
function get_retry_modules(array $results): array {
    $failed_modules = array_filter($results, fn($r) => !$r['success']);
    return array_map(fn($r) => $r['module'], array_values($failed_modules));
}

// Print results
$framework->printResults();

// Exit with appropriate code
$results = $framework->getResults();
$all_passed = array_reduce($results, fn($carry, $r) => $carry && $r['success'], true);
exit($all_passed ? 0 : 1);
?>
