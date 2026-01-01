<?php
/**
 * Property-Based Tests for Setup Completion State Update
 * Feature: unified-module-installer, Property 19: Setup Completion State Update
 * Validates: Requirements 8.4
 */

// Start session before any output to avoid warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';
require_once __DIR__ . '/../config/db_connect.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Setup Completion State Update\n";
echo "===============================================================\n\n";

/**
 * Property 19: Setup Completion State Update
 * For any successful completion of the module installer (whether modules were 
 * installed or skipped), the system setup status flags should be updated to 
 * indicate initialization is complete.
 */
$framework->test(
    'Property 19: Setup Completion State Update - Mark Complete',
    function() {
        // Generator: Create a database connection
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Clear any existing completion state
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['module_installer_complete']);
        
        // Remove marker file if it exists
        $marker_file = __DIR__ . '/../.module_installer_complete';
        if (file_exists($marker_file)) {
            @unlink($marker_file);
        }
        
        return $conn;
    },
    function($conn) {
        // Before marking complete, should return false
        $before = isModuleInstallerComplete($conn);
        
        // Mark as complete
        $marked = markModuleInstallerComplete($conn);
        
        // After marking complete, should return true
        $after = isModuleInstallerComplete($conn);
        
        closeConnection($conn);
        
        // Property: Marking should succeed
        if (!$marked) {
            throw new Exception("Failed to mark module installer as complete");
        }
        
        // Property: State should change from false to true
        if ($before === true) {
            throw new Exception("Module installer was already marked complete before marking");
        }
        
        if ($after !== true) {
            throw new Exception("Module installer not marked complete after marking");
        }
        
        return true;
    }
);

/**
 * Property: Completion state persists across checks
 * For any system where module installer is marked complete, 
 * multiple checks should consistently return true
 */
$framework->test(
    'Property: Completion State Persistence',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Ensure it's marked complete
        markModuleInstallerComplete($conn);
        
        return $conn;
    },
    function($conn) {
        // Check multiple times
        $check1 = isModuleInstallerComplete($conn);
        $check2 = isModuleInstallerComplete($conn);
        $check3 = isModuleInstallerComplete($conn);
        
        closeConnection($conn);
        
        // Property: All checks should return true
        if (!$check1 || !$check2 || !$check3) {
            throw new Exception("Completion state not persistent across multiple checks");
        }
        
        // Property: All checks should be identical
        if ($check1 !== $check2 || $check2 !== $check3) {
            throw new Exception("Completion state inconsistent across checks");
        }
        
        return true;
    }
);

/**
 * Property: Setup status reflects module installer completion
 * For any system state, getSetupStatus should correctly reflect 
 * module installer completion status
 */
$framework->test(
    'Property: Setup Status Reflects Module Installer Completion',
    function() {
        // Generator: Randomly mark or unmark completion
        $should_be_complete = Generators::bool();
        
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        // Clear state first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['module_installer_complete']);
        
        $marker_file = __DIR__ . '/../.module_installer_complete';
        if (file_exists($marker_file)) {
            @unlink($marker_file);
        }
        
        // Set state based on generator
        if ($should_be_complete) {
            markModuleInstallerComplete($conn);
        }
        
        return ['conn' => $conn, 'expected' => $should_be_complete];
    },
    function($data) {
        $conn = $data['conn'];
        $expected = $data['expected'];
        
        // Get setup status
        $status = getSetupStatus();
        
        // Check module installer complete flag
        $actual = $status['module_installer_complete'];
        
        closeConnection($conn);
        
        // Property: Status should match expected state
        if ($actual !== $expected) {
            throw new Exception("Setup status module_installer_complete ($actual) does not match expected state ($expected)");
        }
        
        return true;
    }
);

/**
 * Property: Setup complete only when module installer complete
 * For any system state, setup_complete should be true only if 
 * module_installer_complete is also true (assuming admin exists)
 */
$framework->test(
    'Property: Setup Complete Requires Module Installer Complete',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        return $conn;
    },
    function($conn) {
        // Get setup status
        $status = getSetupStatus();
        
        closeConnection($conn);
        
        // Property: If setup_complete is true, module_installer_complete must also be true
        if ($status['setup_complete'] === true && $status['module_installer_complete'] !== true) {
            throw new Exception("Setup marked complete but module installer not complete");
        }
        
        // Property: If admin exists but module installer not complete, setup should not be complete
        if ($status['admin_exists'] === true && 
            $status['module_installer_complete'] !== true && 
            $status['setup_complete'] === true) {
            throw new Exception("Setup marked complete without module installer completion");
        }
        
        return true;
    }
);

/**
 * Property: Current step reflects module installer state
 * For any system state where admin exists, current_step should be 
 * 'module_installer' if not complete, or 'complete' if complete
 */
$framework->test(
    'Property: Current Step Reflects Module Installer State',
    function() {
        $conn = createConnection(true);
        if (!$conn) {
            throw new Exception('Failed to create database connection');
        }
        
        return $conn;
    },
    function($conn) {
        // Get setup status
        $status = getSetupStatus();
        
        closeConnection($conn);
        
        // Property: If admin exists and module installer not complete, step should be module_installer
        if ($status['admin_exists'] === true && 
            $status['module_installer_complete'] !== true &&
            $status['current_step'] !== 'module_installer') {
            throw new Exception("Current step should be 'module_installer' when admin exists but installer not complete. Got: {$status['current_step']}");
        }
        
        // Property: If module installer complete, step should be complete
        if ($status['module_installer_complete'] === true && 
            $status['current_step'] !== 'complete') {
            throw new Exception("Current step should be 'complete' when module installer is complete. Got: {$status['current_step']}");
        }
        
        return true;
    }
);

/**
 * Property: Marker file creation succeeds
 * For any system state, creating a marker file should succeed
 */
$framework->test(
    'Property: Marker File Creation',
    function() {
        // Remove marker file if exists
        $marker_file = __DIR__ . '/../.module_installer_complete';
        if (file_exists($marker_file)) {
            @unlink($marker_file);
        }
        
        return $marker_file;
    },
    function($marker_file) {
        // Create marker file
        $created = createModuleInstallerMarkerFile();
        
        // Property: Creation should succeed
        if (!$created) {
            throw new Exception("Failed to create marker file");
        }
        
        // Property: File should exist after creation
        if (!file_exists($marker_file)) {
            throw new Exception("Marker file does not exist after creation");
        }
        
        // Property: File should be readable
        $content = @file_get_contents($marker_file);
        if ($content === false) {
            throw new Exception("Marker file not readable after creation");
        }
        
        // Clean up
        @unlink($marker_file);
        
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
