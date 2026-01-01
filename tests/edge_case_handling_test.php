<?php
/**
 * Edge Case Handling Test
 * Tests for empty module list and all modules installed states
 */

require_once __DIR__ . '/../includes/module_discovery.php';

// Test helper function to simulate different module states
function test_edge_case_logic() {
    echo "Testing Edge Case Handling Logic\n";
    echo "=================================\n\n";
    
    // Test 1: Empty module list
    echo "Test 1: Empty Module List\n";
    $all_modules = [];
    $no_modules_available = empty($all_modules);
    $from_settings = false;
    
    if ($from_settings) {
        $all_modules = array_filter($all_modules, function($module) {
            return !$module['installed'];
        });
    }
    
    $all_installed = false;
    if (!$no_modules_available && !empty($all_modules)) {
        $all_installed = true;
        foreach ($all_modules as $module) {
            if (!$module['installed']) {
                $all_installed = false;
                break;
            }
        }
    }
    
    $no_uninstalled_modules = $from_settings && empty($all_modules);
    
    echo "  no_modules_available: " . ($no_modules_available ? 'true' : 'false') . "\n";
    echo "  all_installed: " . ($all_installed ? 'true' : 'false') . "\n";
    echo "  no_uninstalled_modules: " . ($no_uninstalled_modules ? 'true' : 'false') . "\n";
    echo "  Expected: Should show 'No Modules Available' message\n";
    echo "  Result: " . ($no_modules_available ? "✓ PASS" : "✗ FAIL") . "\n\n";
    
    // Test 2: All modules installed
    echo "Test 2: All Modules Installed\n";
    $all_modules = [
        'employees' => ['name' => 'employees', 'installed' => true],
        'clients' => ['name' => 'clients', 'installed' => true],
        'crm' => ['name' => 'crm', 'installed' => true]
    ];
    $no_modules_available = empty($all_modules);
    $from_settings = false;
    
    if ($from_settings) {
        $all_modules = array_filter($all_modules, function($module) {
            return !$module['installed'];
        });
    }
    
    $all_installed = false;
    if (!$no_modules_available && !empty($all_modules)) {
        $all_installed = true;
        foreach ($all_modules as $module) {
            if (!$module['installed']) {
                $all_installed = false;
                break;
            }
        }
    }
    
    $no_uninstalled_modules = $from_settings && empty($all_modules);
    
    echo "  no_modules_available: " . ($no_modules_available ? 'true' : 'false') . "\n";
    echo "  all_installed: " . ($all_installed ? 'true' : 'false') . "\n";
    echo "  no_uninstalled_modules: " . ($no_uninstalled_modules ? 'true' : 'false') . "\n";
    echo "  Expected: Should show 'All Modules Installed' message\n";
    echo "  Result: " . ($all_installed ? "✓ PASS" : "✗ FAIL") . "\n\n";
    
    // Test 3: Some modules installed, some not
    echo "Test 3: Mixed Installation State\n";
    $all_modules = [
        'employees' => ['name' => 'employees', 'installed' => true],
        'clients' => ['name' => 'clients', 'installed' => false],
        'crm' => ['name' => 'crm', 'installed' => true]
    ];
    $no_modules_available = empty($all_modules);
    $from_settings = false;
    
    if ($from_settings) {
        $all_modules = array_filter($all_modules, function($module) {
            return !$module['installed'];
        });
    }
    
    $all_installed = false;
    if (!$no_modules_available && !empty($all_modules)) {
        $all_installed = true;
        foreach ($all_modules as $module) {
            if (!$module['installed']) {
                $all_installed = false;
                break;
            }
        }
    }
    
    $no_uninstalled_modules = $from_settings && empty($all_modules);
    
    echo "  no_modules_available: " . ($no_modules_available ? 'true' : 'false') . "\n";
    echo "  all_installed: " . ($all_installed ? 'true' : 'false') . "\n";
    echo "  no_uninstalled_modules: " . ($no_uninstalled_modules ? 'true' : 'false') . "\n";
    echo "  Expected: Should show module selection interface\n";
    echo "  Result: " . (!$no_modules_available && !$all_installed && !$no_uninstalled_modules ? "✓ PASS" : "✗ FAIL") . "\n\n";
    
    // Test 4: All modules installed when accessed from settings
    echo "Test 4: All Modules Installed (from settings)\n";
    $all_modules = [
        'employees' => ['name' => 'employees', 'installed' => true],
        'clients' => ['name' => 'clients', 'installed' => true],
        'crm' => ['name' => 'crm', 'installed' => true]
    ];
    $no_modules_available = empty($all_modules);
    $from_settings = true;
    
    if ($from_settings) {
        $all_modules = array_filter($all_modules, function($module) {
            return !$module['installed'];
        });
    }
    
    $all_installed = false;
    if (!$no_modules_available && !empty($all_modules)) {
        $all_installed = true;
        foreach ($all_modules as $module) {
            if (!$module['installed']) {
                $all_installed = false;
                break;
            }
        }
    }
    
    $no_uninstalled_modules = $from_settings && empty($all_modules);
    
    echo "  no_modules_available: " . ($no_modules_available ? 'true' : 'false') . "\n";
    echo "  all_installed: " . ($all_installed ? 'true' : 'false') . "\n";
    echo "  no_uninstalled_modules: " . ($no_uninstalled_modules ? 'true' : 'false') . "\n";
    echo "  Expected: Should show 'All Modules Installed' message (no additional modules)\n";
    echo "  Result: " . ($no_uninstalled_modules ? "✓ PASS" : "✗ FAIL") . "\n\n";
    
    // Test 5: Some uninstalled modules when accessed from settings
    echo "Test 5: Some Uninstalled Modules (from settings)\n";
    $all_modules = [
        'employees' => ['name' => 'employees', 'installed' => true],
        'clients' => ['name' => 'clients', 'installed' => false],
        'crm' => ['name' => 'crm', 'installed' => true]
    ];
    $no_modules_available = empty($all_modules);
    $from_settings = true;
    
    if ($from_settings) {
        $all_modules = array_filter($all_modules, function($module) {
            return !$module['installed'];
        });
    }
    
    $all_installed = false;
    if (!$no_modules_available && !empty($all_modules)) {
        $all_installed = true;
        foreach ($all_modules as $module) {
            if (!$module['installed']) {
                $all_installed = false;
                break;
            }
        }
    }
    
    $no_uninstalled_modules = $from_settings && empty($all_modules);
    
    echo "  no_modules_available: " . ($no_modules_available ? 'true' : 'false') . "\n";
    echo "  all_installed: " . ($all_installed ? 'true' : 'false') . "\n";
    echo "  no_uninstalled_modules: " . ($no_uninstalled_modules ? 'true' : 'false') . "\n";
    echo "  Filtered modules count: " . count($all_modules) . "\n";
    echo "  Expected: Should show module selection interface with only uninstalled modules\n";
    echo "  Result: " . (!$no_modules_available && !$all_installed && !$no_uninstalled_modules && count($all_modules) > 0 ? "✓ PASS" : "✗ FAIL") . "\n\n";
    
    echo "=================================\n";
    echo "All edge case tests completed!\n";
}

// Run the test
test_edge_case_logic();
?>
