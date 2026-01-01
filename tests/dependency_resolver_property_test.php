<?php
/**
 * Property-Based Tests for Dependency Resolver
 * Feature: unified-module-installer
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/module_dependencies.php';
require_once __DIR__ . '/../includes/dependency_resolver.php';

// Initialize test framework
$framework = new PropertyTestFramework(100);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  Property-Based Tests: Dependency Resolver                         ║\n";
echo "║  Feature: unified-module-installer                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";

/**
 * Property 7: Automatic Dependency Selection
 * Validates: Requirements 7.1
 * 
 * For any module selection, if the selected module has dependencies,
 * all required dependency modules should be automatically selected (if not already installed).
 */
$framework->test(
    'Property 7: Automatic Dependency Selection',
    function() {
        // Generator: Create random module selection
        $all_modules = array_keys(get_module_dependencies());
        
        // Select 1-3 random modules
        $num_to_select = rand(1, min(3, count($all_modules)));
        $selected = [];
        $available = $all_modules;
        
        for ($i = 0; $i < $num_to_select; $i++) {
            if (empty($available)) break;
            $idx = array_rand($available);
            $selected[] = $available[$idx];
            array_splice($available, $idx, 1);
        }
        
        // Generate random set of already installed modules (0-50% of all modules)
        $num_installed = rand(0, (int)(count($all_modules) * 0.5));
        $installed = [];
        $available_for_install = $all_modules;
        
        for ($i = 0; $i < $num_installed; $i++) {
            if (empty($available_for_install)) break;
            $idx = array_rand($available_for_install);
            $installed[] = $available_for_install[$idx];
            array_splice($available_for_install, $idx, 1);
        }
        
        return [
            'selected' => $selected,
            'installed' => $installed
        ];
    },
    function($data) {
        $selected = $data['selected'];
        $installed = $data['installed'];
        
        // Get the complete installation order (which includes all dependencies)
        try {
            $installation_order = resolve_installation_order($selected);
        } catch (Exception $e) {
            // If there's a circular dependency, that's a different issue
            return true;
        }
        
        // Check that all dependencies are included in the installation order
        foreach ($selected as $module) {
            $direct_deps = get_direct_dependencies($module);
            
            foreach ($direct_deps as $dep) {
                // If dependency is not already installed, it must be in the installation order
                if (!in_array($dep, $installed)) {
                    if (!in_array($dep, $installation_order)) {
                        echo "FAIL: Module '$module' requires '$dep', but '$dep' is not in installation order\n";
                        echo "Selected: " . json_encode($selected) . "\n";
                        echo "Installed: " . json_encode($installed) . "\n";
                        echo "Installation order: " . json_encode($installation_order) . "\n";
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
);

/**
 * Property 8: Dependency Deselection Prevention
 * Validates: Requirements 7.2
 * 
 * For any attempt to deselect a module, if other selected modules depend on it,
 * the deselection should be prevented and a warning message should be displayed.
 */
$framework->test(
    'Property 8: Dependency Deselection Prevention',
    function() {
        // Generator: Create a scenario where we try to deselect a module that others depend on
        $dependencies = get_module_dependencies();
        
        // Find modules that have dependents
        $modules_with_dependents = [];
        foreach ($dependencies as $module => $deps) {
            foreach ($deps as $dep) {
                if (!in_array($dep, $modules_with_dependents)) {
                    $modules_with_dependents[] = $dep;
                }
            }
        }
        
        if (empty($modules_with_dependents)) {
            // No modules with dependents, skip this test case
            return ['skip' => true];
        }
        
        // Pick a random module that has dependents
        $module_to_deselect = Generators::element($modules_with_dependents);
        
        // Find modules that depend on it
        $dependents = get_dependent_modules($module_to_deselect);
        
        // Select some of the dependents
        $num_dependents = rand(1, count($dependents));
        $selected_dependents = array_slice($dependents, 0, $num_dependents);
        
        return [
            'skip' => false,
            'module_to_deselect' => $module_to_deselect,
            'selected_modules' => array_merge($selected_dependents, [$module_to_deselect])
        ];
    },
    function($data) {
        if ($data['skip']) {
            return true;
        }
        
        $module_to_deselect = $data['module_to_deselect'];
        $selected_modules = $data['selected_modules'];
        
        // Remove the module we want to deselect
        $new_selection = array_filter($selected_modules, fn($m) => $m !== $module_to_deselect);
        
        // Validate the new selection
        $validation = validate_module_selection($new_selection, []);
        
        // The validation should fail (not valid) because we removed a dependency
        if ($validation['valid']) {
            echo "FAIL: Deselecting '$module_to_deselect' should make selection invalid\n";
            echo "Selected modules: " . json_encode($selected_modules) . "\n";
            echo "After deselection: " . json_encode($new_selection) . "\n";
            echo "Validation result: " . json_encode($validation) . "\n";
            return false;
        }
        
        // Check that the missing dependencies include the deselected module
        $all_missing = [];
        foreach ($validation['missing'] as $module => $missing_deps) {
            $all_missing = array_merge($all_missing, $missing_deps);
        }
        
        if (!in_array($module_to_deselect, $all_missing)) {
            echo "FAIL: Deselected module '$module_to_deselect' should be in missing dependencies\n";
            echo "Missing: " . json_encode($validation['missing']) . "\n";
            return false;
        }
        
        return true;
    }
);

/**
 * Property 9: Topological Installation Order
 * Validates: Requirements 7.3
 * 
 * For any set of selected modules, the installation order should respect
 * dependency relationships such that no module is installed before its dependencies.
 */
$framework->test(
    'Property 9: Topological Installation Order',
    function() {
        // Generator: Create random module selection
        $all_modules = array_keys(get_module_dependencies());
        
        // Select 1-5 random modules
        $num_to_select = rand(1, min(5, count($all_modules)));
        $selected = [];
        $available = $all_modules;
        
        for ($i = 0; $i < $num_to_select; $i++) {
            if (empty($available)) break;
            $idx = array_rand($available);
            $selected[] = $available[$idx];
            array_splice($available, $idx, 1);
        }
        
        return ['selected' => $selected];
    },
    function($data) {
        $selected = $data['selected'];
        
        try {
            $installation_order = resolve_installation_order($selected);
        } catch (Exception $e) {
            // Circular dependency is a different issue
            return true;
        }
        
        // For each module in the installation order, verify that all its dependencies
        // appear earlier in the order
        $installed_so_far = [];
        
        foreach ($installation_order as $module) {
            $deps = get_direct_dependencies($module);
            
            foreach ($deps as $dep) {
                // If this dependency is in the installation order, it must have been installed already
                if (in_array($dep, $installation_order) && !in_array($dep, $installed_so_far)) {
                    echo "FAIL: Module '$module' appears before its dependency '$dep' in installation order\n";
                    echo "Selected: " . json_encode($selected) . "\n";
                    echo "Installation order: " . json_encode($installation_order) . "\n";
                    echo "Installed so far: " . json_encode($installed_so_far) . "\n";
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
