<?php
/**
 * Skip Module Installer Handler
 * Marks the module installer as complete and redirects to dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/setup_helper.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mark module installer as complete
markModuleInstallerComplete();

// Redirect to dashboard
header('Location: ' . APP_URL . '/public/index.php');
exit;
