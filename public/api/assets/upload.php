<?php
/**
 * Asset API - File Upload
 * POST endpoint to upload files for an asset
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../assets/helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = createConnection(true);

try {
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $file_type = trim($_POST['file_type'] ?? '');
    
    // Validation
    if (!$asset_id) {
        throw new Exception('Asset ID is required');
    }
    
    if (empty($file_type)) {
        throw new Exception('File type is required');
    }
    
    // Check if asset exists
    $asset = getAssetById($conn, $asset_id);
    if (!$asset) {
        throw new Exception('Asset not found');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }
    
    $file = $_FILES['file'];
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size must be less than 10MB');
    }
    
    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
    if (!in_array($ext, $allowed_ext)) {
        throw new Exception('File type not allowed. Allowed: ' . implode(', ', $allowed_ext));
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../../uploads/assets/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $filename = 'asset_' . $asset_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    $upload_path = $upload_dir . $filename;
    $db_path = 'uploads/assets/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Save to database
    $result = uploadAssetFile($conn, $asset_id, $file_type, $db_path, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_path' => $db_path
        ]);
    } else {
        // Delete uploaded file if database insert failed
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        throw new Exception('Failed to save file information to database');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    closeConnection($conn);
}
