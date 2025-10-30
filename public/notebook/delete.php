<?php
/**
 * Notebook Module - Delete Note
 * Delete a note and all associated data
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Must be POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Get note ID
$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;

if ($note_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid note ID';
    header('Location: index.php');
    exit;
}

// Check if user can delete
$note = get_note_by_id($conn, $note_id, $CURRENT_USER_ID);

if (!$note) {
    $_SESSION['flash_error'] = 'Note not found or access denied';
    header('Location: index.php');
    exit;
}

// Check permission
$can_delete = ($note['created_by'] == $CURRENT_USER_ID) || $IS_SUPER_ADMIN;

if (!$can_delete) {
    $_SESSION['flash_error'] = 'You do not have permission to delete this note';
    header('Location: view.php?id=' . $note_id);
    exit;
}

// Delete note
if (delete_note($conn, $note_id)) {
    $_SESSION['flash_success'] = 'Note deleted successfully';
} else {
    $_SESSION['flash_error'] = 'Failed to delete note';
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}

header('Location: index.php');
exit;
?>
