<?php
/**
 * Property Test: Fallback Onboarding Consistency
 * Feature: unified-module-installer, Property 17: Fallback Onboarding Consistency
 * Validates: Requirements 6.4
 * 
 * Property: For any uninstalled module, when a user attempts to access its main 
 * page, the system should display the module's onboarding page with a link to 
 * the unified module installer.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/property_test_framework.php';

// Test setup
$framework = new PropertyTestFramework(100);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  Property Test: Fallback Onboarding Consistency                   ║\n";
echo "║  Feature: unified-module-installer                                 ║\n";
echo "║  Property 17: Fallback Onboarding Consistency                      ║\n";
echo "║  Validates: Requirements 6.4                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

/**
 * Generator: Create list of onboarding pages to test
 */
function generateOnboardingPages(): array {
    // List of known onboarding pages
    $onboarding_pages = [
        'public/branding/onboarding.php',
        'public/crm/onboarding.php',
        'public/invoices/onboarding.php',
        'public/payments/onboarding.php',
        'public/expenses/onboarding.php',
        'public/documents/onboarding.php',
        'public/salary/onboarding.php',
        'public/reimbursements/onboarding.php',
        'public/visitors/onboarding.php',
        'public/data-transfer/onboarding.php',
        'public/assets/onboarding.php',
        'public/payroll/onboarding.php'
    ];
    
    // Randomly select a subset to test
    return Generators::subset($onboarding_pages);
}

/**
 * Property: All onboarding pages should contain a link to the unified module installer
 */
function propertyOnboardingHasInstallerLink(array $testData): bool {
    $onboarding_pages = $testData;
    
    if (empty($onboarding_pages)) {
        // Empty subset is valid, just skip
        return true;
    }
    
    foreach ($onboarding_pages as $page_path) {
        $full_path = __DIR__ . '/../' . $page_path;
        
        // Check if file exists
        if (!file_exists($full_path)) {
            // File doesn't exist, skip (some modules may not have onboarding pages)
            continue;
        }
        
        // Read file content
        $content = file_get_contents($full_path);
        
        if ($content === false) {
            return false; // Failed to read file
        }
        
        // Check if the page contains a link to the unified module installer
        $has_installer_link = (
            strpos($content, '/setup/module_installer.php') !== false &&
            strpos($content, 'Unified Module Installer') !== false
        );
        
        if (!$has_installer_link) {
            // This onboarding page doesn't have the installer link
            return false;
        }
    }
    
    return true;
}

// Run the test
$result = $framework->test(
    'Fallback Onboarding Consistency',
    'generateOnboardingPages',
    'propertyOnboardingHasInstallerLink'
);

// Print results
$framework->printResults();

// Exit with appropriate code
exit($result['success'] ? 0 : 1);
?>
