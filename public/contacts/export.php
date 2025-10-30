<?php
/**
 * Contacts Module - Export Contacts
 * Export filtered contacts to CSV
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'contacts', 'export');

// Check if tables exist
if (!contacts_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=contacts');
    exit;
}

// Get filters from query string
$filters = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (isset($_GET['contact_type']) && !empty($_GET['contact_type'])) {
    $filters['contact_type'] = $_GET['contact_type'];
}
if (isset($_GET['tag']) && !empty($_GET['tag'])) {
    $filters['tag'] = $_GET['tag'];
}
if (isset($_GET['share_scope']) && !empty($_GET['share_scope'])) {
    $filters['share_scope'] = $_GET['share_scope'];
}

// Get contacts to export
$contacts = get_all_contacts($conn, $CURRENT_USER_ID, $filters);

// Export to CSV
export_contacts_to_csv($contacts);

// This will exit in the export function
?>
