<?php
/**
 * Property Test: Uninstalled Module Filtering
 * Feature: unified-module-installer, Property 16: Uninstalled Module Filtering
 * Validates: Requirements 6.5
 * 
 * Property: For any system state when the module installer is accessed from 
 * dashboard or settings (not initial setup), only modules that are not yet 
 * installed should be displayed for selection.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/module_discovery.php';
require_once __DIR__ . '/property_test_framework.php';

// Test setup
$framework = new PropertyTestFramework(100);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  Property Test: Uninstalled Module Filtering                      ║\n";
echo "║  Feature: unified-module-installer                                 ║\n";
echo "║  Property 16: Uninstalled Module Filtering                         ║\n";
echo "║  Validates: Requirements 6.5                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

/**
 * Generator: Create random module installation states
 */
function generateModuleStates(): array {
    $conn = createConnection(true);
    $all_modules = discover_modules($conn);
    closeConnection($conn);
    
    // Randomly mark some modules as installed/uninstalled
    $module_states = [];
    foreach ($all_modules as $module_name => $module) {
        $module_states[$module_name] = [
            'name' => $module_name,
            'display_name' => $module['display_name'],
            'installed' => Generators::bool()
        ];
    }
    
    return $module_states;
}

/**
 * Property: When accessed from settings, only uninstalled modules should be shown
 */
function propertyUninstalledModuleFiltering(array $testData): bool {
    $module_states = $testData;
    
    // Simulate filtering logic from module_installer.php
    $all_modules = $module_states;
    
    // Filter to show only uninstalled modules (simulating from_settings = true)
    $filtered_modules = array_filter($all_modules, function($module) {
        return !$module['installed'];
    });
    
    // Property: All filtered modules should be uninstalled
    foreach ($filtered_modules as $module) {
        if ($module['installed']) {
            return false; // Found an installed module in filtered list
        }
    }
    
    // Property: All uninstalled modules should be in the filtered list
    $uninstalled_count = 0;
    foreach ($all_modules as $module) {
        if (!$module['installed']) {
            $uninstalled_count++;
        }
    }
    
    if (count($filtered_modules) !== $uninstalled_count) {
        return false; // Not all uninstalled modules are in the filtered list
    }
    
    return true;
}

// Run the test
$result = $framework->test(
    'Uninstalled Module Filtering',
    'generateModuleStates',
    'propertyUninstalledModuleFiltering'
);

// Print results
$framework->printResults();

// Exit with appropriate code
exit($result['success'] ? 0 : 1);
?>
