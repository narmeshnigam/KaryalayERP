<?php
/**
 * Contacts Module - Delete Contact
 * Handle contact deletion
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'contacts', 'view');

// Check if tables exist
if (!contacts_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=contacts');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$contact_id = isset($_POST['contact_id']) ? (int)$_POST['contact_id'] : 0;

if ($contact_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid contact ID';
    header('Location: index.php');
    exit;
}

// Check if contact exists and user has permission
$contact = get_contact_by_id($conn, $contact_id, $CURRENT_USER_ID);

if (!$contact) {
    $_SESSION['flash_error'] = 'Contact not found or access denied';
    header('Location: index.php');
    exit;
}

if (!can_edit_contact($conn, $contact_id, $CURRENT_USER_ID)) {
    $_SESSION['flash_error'] = 'You do not have permission to delete this contact';
    header('Location: view.php?id=' . $contact_id);
    exit;
}

// Delete contact
if (delete_contact($conn, $contact_id, $CURRENT_USER_ID)) {
    $_SESSION['flash_success'] = 'Contact deleted successfully';
} else {
    $_SESSION['flash_error'] = 'Failed to delete contact. Please try again.';
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}

header('Location: index.php');
exit;
?>
