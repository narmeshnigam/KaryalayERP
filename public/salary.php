<?php
require_once __DIR__ . '/../includes/auth_check.php';

$salaryPermissions = authz_get_permission_set($conn, 'salary_records');
$canManagePayroll = !empty($salaryPermissions['can_view_all'])
    || !empty($salaryPermissions['can_edit_all'])
    || !empty($salaryPermissions['can_create'])
    || authz_is_super_admin($conn);

if ($canManagePayroll) {
    $destination = APP_URL . '/public/salary/admin.php';
} else {
    $destination = APP_URL . '/public/employee_portal/salary/index.php';
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}

header('Location: ' . $destination);
exit;
