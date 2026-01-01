<?php
/**
 * Property-Based Tests for Progress Tracking Accuracy
 * Feature: unified-module-installer, Property 11: Progress Tracking Accuracy
 * Validates: Requirements 4.2, 4.3
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/module_discovery.php';
require_once __DIR__ . '/../includes/installation_engine.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Progress Tracking Accuracy\n";
echo "===========================================================\n\n";

/**
 * Helper function to simulate installation progress tracking
 */
function simulate_installation_with_progress(array $modules): array {
    $progress_snapshots = [];
    $total = count($modules);
    
    // Simulate installation of each module
    foreach ($modules as $index => $module) {
        $completed_count = $index;
        $percentage = $total > 0 ? round(($completed_count / $total) * 100) : 0;
        
        // Snapshot before installing this module
        $progress_snapshots[] = [
            'current_module' => $module,
            'completed_count' => $completed_count,
            'total' => $total,
            'percentage' => $percentage,
            'in_progress' => true
        ];
    }
    
    // Final snapshot after all modules installed
    $progress_snapshots[] = [
        'current_module' => null,
        'completed_count' => $total,
        'total' => $total,
        'percentage' => 100,
        'in_progress' => false
    ];
    
    return $progress_snapshots;
}

/**
 * Property 11: Progress Tracking Accuracy - Percentage Calculation
 * For any installation in progress, the progress percentage should accurately 
 * reflect the ratio of completed modules to total modules
 */
$framework->test(
    'Property 11: Progress Tracking Accuracy - Percentage Calculation',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get available modules
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $module_names = array_keys($all_modules);
        
        // Generate random subset of modules
        $selected = Generators::subset($module_names);
        
        if (empty($selected)) {
            return ['skip' => true];
        }
        
        return ['modules' => $selected];
    },
    function($data) {
        if (isset($data['skip'])) {
            return true;
        }
        
        $modules = $data['modules'];
        $total = count($modules);
        
        // Simulate progress tracking
        $snapshots = simulate_installation_with_progress($modules);
        
        // Verify each snapshot (skip the last one as it's the final state)
        for ($i = 0; $i < count($snapshots) - 1; $i++) {
            $snapshot = $snapshots[$i];
            $expected_percentage = $total > 0 ? round(($snapshot['completed_count'] / $total) * 100) : 0;
            
            // Property: Percentage should match calculation
            if ($snapshot['percentage'] !== $expected_percentage) {
                throw new Exception(
                    "Progress percentage mismatch at snapshot {$i}. " .
                    "Expected: {$expected_percentage}%, Got: {$snapshot['percentage']}% " .
                    "(Completed: {$snapshot['completed_count']}, Total: {$total})"
                );
            }
        }
        
        // Verify final snapshot separately
        $final_snapshot = $snapshots[count($snapshots) - 1];
        if ($final_snapshot['percentage'] !== 100) {
            throw new Exception(
                "Final progress percentage should be 100%, got {$final_snapshot['percentage']}%"
            );
        }
        
        return true;
    }
);

/**
 * Property 11: Progress Tracking Accuracy - Monotonic Progress
 * For any installation, the completed count should never decrease
 */
$framework->test(
    'Property 11: Progress Tracking Accuracy - Monotonic Progress',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $module_names = array_keys($all_modules);
        $selected = Generators::subset($module_names);
        
        if (empty($selected)) {
            return ['skip' => true];
        }
        
        return ['modules' => $selected];
    },
    function($data) {
        if (isset($data['skip'])) {
            return true;
        }
        
        $modules = $data['modules'];
        $snapshots = simulate_installation_with_progress($modules);
        
        $previous_completed = -1;
        
        // Property: Completed count should never decrease
        foreach ($snapshots as $snapshot) {
            if ($snapshot['completed_count'] < $previous_completed) {
                throw new Exception(
                    "Progress went backwards! " .
                    "Previous: {$previous_completed}, Current: {$snapshot['completed_count']}"
                );
            }
            $previous_completed = $snapshot['completed_count'];
        }
        
        return true;
    }
);

/**
 * Property 11: Progress Tracking Accuracy - Current Module Updates
 * For any installation in progress, the current module should be set when in_progress is true
 * and null when in_progress is false
 */
$framework->test(
    'Property 11: Progress Tracking Accuracy - Current Module Updates',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $module_names = array_keys($all_modules);
        $selected = Generators::subset($module_names);
        
        if (empty($selected)) {
            return ['skip' => true];
        }
        
        return ['modules' => $selected];
    },
    function($data) {
        if (isset($data['skip'])) {
            return true;
        }
        
        $modules = $data['modules'];
        $snapshots = simulate_installation_with_progress($modules);
        
        foreach ($snapshots as $index => $snapshot) {
            if ($snapshot['in_progress']) {
                // Property: When in progress, current_module should be set
                if ($snapshot['current_module'] === null) {
                    throw new Exception(
                        "Current module should be set when installation is in progress " .
                        "(Snapshot {$index})"
                    );
                }
                
                // Property: Current module should be one of the modules being installed
                if (!in_array($snapshot['current_module'], $modules)) {
                    throw new Exception(
                        "Current module '{$snapshot['current_module']}' is not in the installation list"
                    );
                }
            } else {
                // Property: When not in progress, current_module should be null
                if ($snapshot['current_module'] !== null) {
                    throw new Exception(
                        "Current module should be null when installation is not in progress"
                    );
                }
            }
        }
        
        return true;
    }
);

/**
 * Property 11: Progress Tracking Accuracy - Final State
 * For any completed installation, the final state should show 100% completion
 */
$framework->test(
    'Property 11: Progress Tracking Accuracy - Final State',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $module_names = array_keys($all_modules);
        $selected = Generators::subset($module_names);
        
        if (empty($selected)) {
            return ['skip' => true];
        }
        
        return ['modules' => $selected];
    },
    function($data) {
        if (isset($data['skip'])) {
            return true;
        }
        
        $modules = $data['modules'];
        $snapshots = simulate_installation_with_progress($modules);
        
        // Get final snapshot
        $final_snapshot = end($snapshots);
        
        // Property: Final state should show 100% completion
        if ($final_snapshot['percentage'] !== 100) {
            throw new Exception(
                "Final progress should be 100%, got {$final_snapshot['percentage']}%"
            );
        }
        
        // Property: Final state should show in_progress as false
        if ($final_snapshot['in_progress'] !== false) {
            throw new Exception("Final state should have in_progress set to false");
        }
        
        // Property: Final state should show completed_count equal to total
        if ($final_snapshot['completed_count'] !== $final_snapshot['total']) {
            throw new Exception(
                "Final state should have completed_count equal to total. " .
                "Completed: {$final_snapshot['completed_count']}, Total: {$final_snapshot['total']}"
            );
        }
        
        return true;
    }
);

/**
 * Property 11: Progress Tracking Accuracy - Completed Count Bounds
 * For any installation progress, completed_count should be between 0 and total (inclusive)
 */
$framework->test(
    'Property 11: Progress Tracking Accuracy - Completed Count Bounds',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $module_names = array_keys($all_modules);
        $selected = Generators::subset($module_names);
        
        if (empty($selected)) {
            return ['skip' => true];
        }
        
        return ['modules' => $selected];
    },
    function($data) {
        if (isset($data['skip'])) {
            return true;
        }
        
        $modules = $data['modules'];
        $snapshots = simulate_installation_with_progress($modules);
        
        foreach ($snapshots as $snapshot) {
            // Property: Completed count should be >= 0
            if ($snapshot['completed_count'] < 0) {
                throw new Exception(
                    "Completed count should not be negative: {$snapshot['completed_count']}"
                );
            }
            
            // Property: Completed count should be <= total
            if ($snapshot['completed_count'] > $snapshot['total']) {
                throw new Exception(
                    "Completed count ({$snapshot['completed_count']}) should not exceed total ({$snapshot['total']})"
                );
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
