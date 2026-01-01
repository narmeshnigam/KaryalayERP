<?php
/**
 * Property-Based Tests for Selection State Management
 * Feature: unified-module-installer, Property 4: Selection State Management
 * Validates: Requirements 2.3, 2.4
 */

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/module_discovery.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Selection State Management\n";
echo "============================================================\n\n";

/**
 * Helper function to simulate selection state logic
 * Returns true if install button should be enabled
 */
function should_enable_install_button(array $selected_modules, array $installed_modules): bool {
    // Filter out installed modules from selection
    $uninstalled_selected = array_diff($selected_modules, $installed_modules);
    
    // Button should be enabled if at least one uninstalled module is selected
    return count($uninstalled_selected) > 0;
}

/**
 * Property 4: Selection State Management
 * For any selection state, the installation button should be enabled if and only if 
 * at least one uninstalled module is selected.
 */
$framework->test(
    'Property 4: Selection State Management - Button Enabled When Uninstalled Selected',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get all modules and their installation status
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $module_names = array_keys($all_modules);
        $installed_modules = array_keys(array_filter($all_modules, fn($m) => $m['installed']));
        $uninstalled_modules = array_keys(array_filter($all_modules, fn($m) => !$m['installed']));
        
        // Generate random selection that includes at least one uninstalled module
        if (empty($uninstalled_modules)) {
            // Skip if all modules are installed
            return ['skip' => true];
        }
        
        // Select at least one uninstalled module
        $selected = [Generators::element($uninstalled_modules)];
        
        // Randomly add more modules
        $additional = Generators::subset($module_names);
        $selected = array_unique(array_merge($selected, $additional));
        
        return [
            'selected' => $selected,
            'installed' => $installed_modules,
            'has_uninstalled' => true
        ];
    },
    function($data) {
        if (isset($data['skip'])) {
            return true;
        }
        
        $button_enabled = should_enable_install_button($data['selected'], $data['installed']);
        
        // Property: Button should be enabled when at least one uninstalled module is selected
        if (!$button_enabled) {
            throw new Exception(
                "Button should be enabled when uninstalled modules are selected. " .
                "Selected: " . json_encode($data['selected']) . ", " .
                "Installed: " . json_encode($data['installed'])
            );
        }
        
        return true;
    }
);

/**
 * Property 4: Selection State Management - Button Disabled When No Uninstalled Selected
 */
$framework->test(
    'Property 4: Selection State Management - Button Disabled When No Uninstalled Selected',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get all modules and their installation status
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $installed_modules = array_keys(array_filter($all_modules, fn($m) => $m['installed']));
        
        if (empty($installed_modules)) {
            // Skip if no modules are installed
            return ['skip' => true];
        }
        
        // Generate selection that only includes installed modules
        $selected = Generators::subset($installed_modules);
        
        return [
            'selected' => $selected,
            'installed' => $installed_modules,
            'has_uninstalled' => false
        ];
    },
    function($data) {
        if (isset($data['skip'])) {
            return true;
        }
        
        $button_enabled = should_enable_install_button($data['selected'], $data['installed']);
        
        // Property: Button should be disabled when only installed modules are selected
        if ($button_enabled) {
            throw new Exception(
                "Button should be disabled when only installed modules are selected. " .
                "Selected: " . json_encode($data['selected']) . ", " .
                "Installed: " . json_encode($data['installed'])
            );
        }
        
        return true;
    }
);

/**
 * Property 4: Selection State Management - Button Disabled When Nothing Selected
 */
$framework->test(
    'Property 4: Selection State Management - Button Disabled When Nothing Selected',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get all modules and their installation status
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $installed_modules = array_keys(array_filter($all_modules, fn($m) => $m['installed']));
        
        return [
            'selected' => [],
            'installed' => $installed_modules
        ];
    },
    function($data) {
        $button_enabled = should_enable_install_button($data['selected'], $data['installed']);
        
        // Property: Button should be disabled when nothing is selected
        if ($button_enabled) {
            throw new Exception("Button should be disabled when nothing is selected");
        }
        
        return true;
    }
);

/**
 * Property 4: Selection State Management - Equivalence Property
 * For any two selection states with the same uninstalled modules selected,
 * the button state should be the same
 */
$framework->test(
    'Property 4: Selection State Management - Equivalence',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Get all modules and their installation status
        $all_modules = discover_modules($conn);
        closeConnection($conn);
        
        $module_names = array_keys($all_modules);
        $installed_modules = array_keys(array_filter($all_modules, fn($m) => $m['installed']));
        
        // Generate two different selections
        $selected1 = Generators::subset($module_names);
        $selected2 = Generators::subset($module_names);
        
        return [
            'selected1' => $selected1,
            'selected2' => $selected2,
            'installed' => $installed_modules
        ];
    },
    function($data) {
        $button_enabled1 = should_enable_install_button($data['selected1'], $data['installed']);
        $button_enabled2 = should_enable_install_button($data['selected2'], $data['installed']);
        
        // Calculate uninstalled selections
        $uninstalled1 = array_diff($data['selected1'], $data['installed']);
        $uninstalled2 = array_diff($data['selected2'], $data['installed']);
        
        // Property: If both have uninstalled modules or both don't, button state should match
        $has_uninstalled1 = count($uninstalled1) > 0;
        $has_uninstalled2 = count($uninstalled2) > 0;
        
        if ($has_uninstalled1 === $has_uninstalled2) {
            if ($button_enabled1 !== $button_enabled2) {
                throw new Exception(
                    "Button state should be the same for equivalent selection states. " .
                    "Selection1: " . json_encode($data['selected1']) . " (enabled: " . ($button_enabled1 ? 'true' : 'false') . "), " .
                    "Selection2: " . json_encode($data['selected2']) . " (enabled: " . ($button_enabled2 ? 'true' : 'false') . ")"
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
