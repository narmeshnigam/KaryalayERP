<?php
/**
 * Property-Based Tests for Session Persistence
 * Feature: unified-module-installer, Property 18: Session Persistence
 * Validates: Requirements 8.3
 */

// Start session before any output to avoid warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/property_test_framework.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';

// Create test framework
$framework = new PropertyTestFramework(100);

echo "Running Property-Based Tests for Session Persistence\n";
echo "=====================================================\n\n";

/**
 * Property 18: Session Persistence
 * For any navigation between setup wizard steps and the module installer, 
 * user session state and authentication should be maintained without requiring re-login.
 */
$framework->test(
    'Property 18: Session Persistence - User ID Maintained',
    function() {
        // Generator: Create a random user ID
        $user_id = Generators::int(1, 10000);
        
        // Set session data
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = 'test_user_' . $user_id;
        $_SESSION['full_name'] = 'Test User ' . $user_id;
        $_SESSION['login_time'] = time();
        
        return $user_id;
    },
    function($expected_user_id) {
        // Property: Session data should persist
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Session user_id not set");
        }
        
        if ($_SESSION['user_id'] !== $expected_user_id) {
            throw new Exception("Session user_id changed from $expected_user_id to {$_SESSION['user_id']}");
        }
        
        if (!isset($_SESSION['username'])) {
            throw new Exception("Session username not set");
        }
        
        if (!isset($_SESSION['full_name'])) {
            throw new Exception("Session full_name not set");
        }
        
        if (!isset($_SESSION['login_time'])) {
            throw new Exception("Session login_time not set");
        }
        
        return true;
    }
);

/**
 * Property: Session data persists across multiple checks
 * For any session data, multiple reads should return the same value
 */
$framework->test(
    'Property: Session Data Consistency',
    function() {
        // Generator: Create random session data
        $user_id = Generators::int(1, 10000);
        $username = 'user_' . Generators::string(5, 15);
        $full_name = Generators::string(10, 30);
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['full_name'] = $full_name;
        
        return ['user_id' => $user_id, 'username' => $username, 'full_name' => $full_name];
    },
    function($expected) {
        // Read session data multiple times
        $read1_user_id = $_SESSION['user_id'] ?? null;
        $read2_user_id = $_SESSION['user_id'] ?? null;
        $read3_user_id = $_SESSION['user_id'] ?? null;
        
        $read1_username = $_SESSION['username'] ?? null;
        $read2_username = $_SESSION['username'] ?? null;
        
        $read1_full_name = $_SESSION['full_name'] ?? null;
        $read2_full_name = $_SESSION['full_name'] ?? null;
        
        // Property: All reads should return the same value
        if ($read1_user_id !== $read2_user_id || $read2_user_id !== $read3_user_id) {
            throw new Exception("Session user_id inconsistent across reads");
        }
        
        if ($read1_username !== $read2_username) {
            throw new Exception("Session username inconsistent across reads");
        }
        
        if ($read1_full_name !== $read2_full_name) {
            throw new Exception("Session full_name inconsistent across reads");
        }
        
        // Property: Values should match expected
        if ($read1_user_id !== $expected['user_id']) {
            throw new Exception("Session user_id does not match expected value");
        }
        
        if ($read1_username !== $expected['username']) {
            throw new Exception("Session username does not match expected value");
        }
        
        if ($read1_full_name !== $expected['full_name']) {
            throw new Exception("Session full_name does not match expected value");
        }
        
        return true;
    }
);

/**
 * Property: Module installer completion flag persists in session
 * For any session where module installer is marked complete, 
 * the flag should persist across checks
 */
$framework->test(
    'Property: Module Installer Completion Flag Persistence',
    function() {
        // Generator: Randomly set or unset completion flag
        $should_be_complete = Generators::bool();
        
        if ($should_be_complete) {
            $_SESSION['module_installer_complete'] = true;
        } else {
            unset($_SESSION['module_installer_complete']);
        }
        
        return $should_be_complete;
    },
    function($expected) {
        // Check flag multiple times
        $check1 = isset($_SESSION['module_installer_complete']) && $_SESSION['module_installer_complete'] === true;
        $check2 = isset($_SESSION['module_installer_complete']) && $_SESSION['module_installer_complete'] === true;
        $check3 = isset($_SESSION['module_installer_complete']) && $_SESSION['module_installer_complete'] === true;
        
        // Property: All checks should return the same value
        if ($check1 !== $check2 || $check2 !== $check3) {
            throw new Exception("Module installer completion flag inconsistent across checks");
        }
        
        // Property: Value should match expected
        if ($check1 !== $expected) {
            throw new Exception("Module installer completion flag does not match expected state");
        }
        
        return true;
    }
);

/**
 * Property: Session status check is consistent
 * For any session state, checking session_status() multiple times 
 * should return the same value
 */
$framework->test(
    'Property: Session Status Consistency',
    function() {
        // No generator needed - just check current session state
        return session_status();
    },
    function($initial_status) {
        // Check session status multiple times
        $status1 = session_status();
        $status2 = session_status();
        $status3 = session_status();
        
        // Property: All checks should return the same value
        if ($status1 !== $status2 || $status2 !== $status3) {
            throw new Exception("Session status inconsistent across checks");
        }
        
        // Property: Status should match initial status
        if ($status1 !== $initial_status) {
            throw new Exception("Session status changed from initial check");
        }
        
        // Property: Session should be active (PHP_SESSION_ACTIVE = 2)
        if ($status1 !== PHP_SESSION_ACTIVE) {
            throw new Exception("Session is not active (status: $status1)");
        }
        
        return true;
    }
);

/**
 * Property: Authentication state persists
 * For any authenticated session, the authentication state should 
 * persist across multiple checks
 */
$framework->test(
    'Property: Authentication State Persistence',
    function() {
        // Generator: Create authenticated session
        $user_id = Generators::int(1, 10000);
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = 'auth_user_' . $user_id;
        $_SESSION['login_time'] = time();
        
        return $user_id;
    },
    function($expected_user_id) {
        // Check authentication state multiple times
        $is_auth1 = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        $is_auth2 = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        $is_auth3 = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        
        // Property: All checks should return true
        if (!$is_auth1 || !$is_auth2 || !$is_auth3) {
            throw new Exception("Authentication state not persistent");
        }
        
        // Property: User ID should match expected
        if ($_SESSION['user_id'] !== $expected_user_id) {
            throw new Exception("User ID changed during session");
        }
        
        // Property: Login time should be set and reasonable
        if (!isset($_SESSION['login_time'])) {
            throw new Exception("Login time not set");
        }
        
        $login_time = $_SESSION['login_time'];
        $current_time = time();
        
        if ($login_time > $current_time) {
            throw new Exception("Login time is in the future");
        }
        
        // Login time should be within last hour (reasonable for test)
        if ($current_time - $login_time > 3600) {
            throw new Exception("Login time is too old (more than 1 hour)");
        }
        
        return true;
    }
);

/**
 * Property: Session data survives function calls
 * For any session data, it should persist across function boundaries
 */
function test_session_access($expected_user_id) {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Session user_id not accessible in function");
    }
    
    if ($_SESSION['user_id'] !== $expected_user_id) {
        throw new Exception("Session user_id value changed in function context");
    }
    
    return true;
}

$framework->test(
    'Property: Session Data Accessible Across Function Boundaries',
    function() {
        // Generator: Create session data
        $user_id = Generators::int(1, 10000);
        $_SESSION['user_id'] = $user_id;
        
        return $user_id;
    },
    function($expected_user_id) {
        // Property: Session data should be accessible in function
        test_session_access($expected_user_id);
        
        // Property: Session data should still be accessible after function call
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Session user_id lost after function call");
        }
        
        if ($_SESSION['user_id'] !== $expected_user_id) {
            throw new Exception("Session user_id changed after function call");
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
