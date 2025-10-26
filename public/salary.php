<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/bootstrap.php';

// Route based on user role
$user_role = $_SESSION['role'] ?? 'employee';
if (in_array(strtolower($user_role), ['admin', 'manager'], true)) {
    header('Location: ' . APP_URL . '/public/salary/admin.php');
} else {
    header('Location: ' . APP_URL . '/public/employee_portal/salary/index.php');
}
exit;
