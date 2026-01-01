<?php
/**
 * Dependency Resolver
 * Handles module dependency analysis and installation ordering
 */

require_once __DIR__ . '/../config/module_dependencies.php';

/**
 * Load module dependencies from configuration
 * 
 * @return array Module dependencies map
 */
function load_module_dependencies(): array {
    return get_module_dependencies();
}

/**
 * Get mandatory modules that must always be installed
 * 
 * @return array Array of mandatory module names
 */
function get_mandatory_module_list(): array {
    if (function_exists('get_mandatory_modules')) {
        return get_mandatory_modules();
    }
    return ['employees', 'catalog'];
}

/**
 * Get direct dependencies for a module
 * 
 * @param string $module_name Module name
 * @return array Array of module names that this module depends on
 */
function get_direct_dependencies(string $module_name): array {
    $dependencies = load_module_dependencies();
    return $dependencies[$module_name] ?? [];
}

/**
 * Get all modules that depend on a given module
 * 
 * @param string $module_name Module name
 * @return array Array of module names that depend on this module
 */
function get_dependent_modules(string $module_name): array {
    $dependencies = load_module_dependencies();
    $dependents = [];
    
    foreach ($dependencies as $module => $deps) {
        if (in_array($module_name, $deps)) {
            $dependents[] = $module;
        }
    }
    
    return $dependents;
}

/**
 * Resolve dependencies and return installation order using topological sort (Kahn's algorithm)
 * 
 * @param array $selected_modules Array of module names to install
 * @return array Ordered array of modules (dependencies first)
 */
function resolve_installation_order(array $selected_modules): array {
    $dependencies = load_module_dependencies();
    
    // Build graph with only selected modules and their dependencies
    $all_modules = $selected_modules;
    $queue = $selected_modules;
    
    // Add all transitive dependencies
    while (!empty($queue)) {
        $module = array_shift($queue);
        $deps = $dependencies[$module] ?? [];
        
        foreach ($deps as $dep) {
            if (!in_array($dep, $all_modules)) {
                $all_modules[] = $dep;
                $queue[] = $dep;
            }
        }
    }
    
    // Calculate in-degree for each module
    $in_degree = [];
    foreach ($all_modules as $module) {
        $in_degree[$module] = 0;
    }
    
    foreach ($all_modules as $module) {
        $deps = $dependencies[$module] ?? [];
        foreach ($deps as $dep) {
            if (in_array($dep, $all_modules)) {
                $in_degree[$module]++;
            }
        }
    }
    
    // Kahn's algorithm
    $result = [];
    $zero_in_degree = [];
    
    foreach ($in_degree as $module => $degree) {
        if ($degree === 0) {
            $zero_in_degree[] = $module;
        }
    }
    
    while (!empty($zero_in_degree)) {
        $module = array_shift($zero_in_degree);
        $result[] = $module;
        
        // Reduce in-degree for dependent modules
        foreach ($all_modules as $other_module) {
            $deps = $dependencies[$other_module] ?? [];
            if (in_array($module, $deps)) {
                $in_degree[$other_module]--;
                if ($in_degree[$other_module] === 0) {
                    $zero_in_degree[] = $other_module;
                }
            }
        }
    }
    
    // Check for cycles (if result doesn't contain all modules, there's a cycle)
    if (count($result) !== count($all_modules)) {
        throw new Exception("Circular dependency detected in module dependencies");
    }
    
    return $result;
}

/**
 * Validate module selection for missing dependencies
 * 
 * @param array $selected_modules Array of module names
 * @param array $installed_modules Array of already installed module names
 * @return array Validation result with 'valid' (bool) and 'missing' (array)
 */
function validate_module_selection(array $selected_modules, array $installed_modules = []): array {
    $dependencies = load_module_dependencies();
    $missing = [];
    
    foreach ($selected_modules as $module) {
        $deps = $dependencies[$module] ?? [];
        
        foreach ($deps as $dep) {
            // Check if dependency is either selected or already installed
            if (!in_array($dep, $selected_modules) && !in_array($dep, $installed_modules)) {
                if (!isset($missing[$module])) {
                    $missing[$module] = [];
                }
                $missing[$module][] = $dep;
            }
        }
    }
    
    return [
        'valid' => empty($missing),
        'missing' => $missing
    ];
}

?>
