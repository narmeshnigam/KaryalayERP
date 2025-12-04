<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/authz.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'employees.delete');

$payroll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payroll_id > 0) {
    $payroll = get_payroll_by_id($conn, $payroll_id);
    
    if ($payroll && $payroll['status'] === 'Draft') {
        if (delete_payroll_draft($conn, $payroll_id)) {
            log_payroll_activity($conn, $payroll_id, 'Delete', $_SESSION['user_id'], 'Payroll draft deleted');
            flash_add('success', 'Payroll draft deleted successfully.');
        } else {
            flash_add('error', 'Failed to delete payroll draft.');
        }
    } else {
        flash_add('error', 'Cannot delete locked or finalized payrolls.');
    }
}

header('Location: ' . APP_URL . '/public/payroll/index.php');
exit;