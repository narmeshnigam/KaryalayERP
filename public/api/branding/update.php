<?php
/**
 * Branding API: Update Settings
 * Updates organization details and branding information
 */

require_once __DIR__ . '/../../branding/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!branding_user_can_edit()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$conn = createConnection(true);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Collect and validate input
$data = [];
$errors = [];

// Required field
if (empty($_POST['org_name'])) {
    $errors[] = 'Organization name is required';
} else {
    $data['org_name'] = trim($_POST['org_name']);
}

// Optional text fields
$optional_fields = [
    'legal_name', 'tagline', 'address_line1', 'address_line2', 
    'city', 'state', 'zip', 'country', 'phone', 'gstin'
];

foreach ($optional_fields as $field) {
    if (isset($_POST[$field])) {
        $data[$field] = trim($_POST[$field]);
    }
}

// Validate email
if (!empty($_POST['email'])) {
    if (branding_validate_email($_POST['email'])) {
        $data['email'] = trim($_POST['email']);
    } else {
        $errors[] = 'Invalid email format';
    }
}

// Validate website URL
if (!empty($_POST['website'])) {
    if (branding_validate_url($_POST['website'])) {
        $data['website'] = trim($_POST['website']);
    } else {
        $errors[] = 'Invalid website URL (must start with http:// or https://)';
    }
}

// Footer text with length check
if (isset($_POST['footer_text'])) {
    $footer = trim($_POST['footer_text']);
    if (strlen($footer) > 150) {
        $errors[] = 'Footer text must not exceed 150 characters';
    } else {
        $data['footer_text'] = $footer;
    }
}

// Tagline length check
if (isset($_POST['tagline'])) {
    $tagline = trim($_POST['tagline']);
    if (strlen($tagline) > 100) {
        $errors[] = 'Tagline must not exceed 100 characters';
    } else {
        $data['tagline'] = $tagline;
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    closeConnection($conn);
    exit;
}

// Update settings
$success = branding_update_settings($conn, $data, (int)$_SESSION['user_id']);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Branding settings updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update settings']);
}

closeConnection($conn);
?>
