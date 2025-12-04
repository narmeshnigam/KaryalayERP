<?php
/**
 * API: Upload Proof of Delivery
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = createConnection();

try {
    $delivery_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $uploaded_by = 1; // TODO: Replace with session user_id
    
    if (!$delivery_id) {
        throw new Exception('Delivery ID is required');
    }
    
    // Verify delivery exists and is delivered
    $check_query = "SELECT id, status FROM delivery_items WHERE id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $delivery = mysqli_fetch_assoc($result);
    
    if (!$delivery) {
        throw new Exception('Delivery not found');
    }
    
    if ($delivery['status'] !== 'Delivered' && $delivery['status'] !== 'Confirmed') {
        throw new Exception('POD can only be uploaded for delivered items');
    }
    
    // Validate file upload
    if (!isset($_FILES['pod_files']) || empty($_FILES['pod_files']['name'][0])) {
        throw new Exception('At least one POD file is required');
    }
    
    mysqli_begin_transaction($conn);
    
    // Handle file uploads
    $upload_dir = __DIR__ . '/../../../uploads/delivery/pod/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_file_size = 20 * 1024 * 1024; // 20MB
    $uploaded_count = 0;
    
    $file_count = count($_FILES['pod_files']['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['pod_files']['error'][$i] === UPLOAD_ERR_OK) {
            $original_name = $_FILES['pod_files']['name'][$i];
            $tmp_name = $_FILES['pod_files']['tmp_name'][$i];
            $file_size = $_FILES['pod_files']['size'][$i];
            
            // Validate file size
            if ($file_size > $max_file_size) {
                throw new Exception("File {$original_name} exceeds 20MB limit");
            }
            
            // Validate file extension
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed_extensions)) {
                throw new Exception("File type .{$extension} not allowed for {$original_name}");
            }
            
            // Generate unique filename
            $unique_name = 'pod_' . time() . '_' . uniqid() . '_' . $original_name;
            $destination = $upload_dir . $unique_name;
            
            // Move uploaded file
            if (move_uploaded_file($tmp_name, $destination)) {
                // Save to database
                $pod_insert = "INSERT INTO delivery_pod 
                              (delivery_id, file_name, file_path, file_size, notes, uploaded_by) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $pod_stmt = mysqli_prepare($conn, $pod_insert);
                $relative_path = 'uploads/delivery/pod/' . $unique_name;
                mysqli_stmt_bind_param($pod_stmt, 'issisi',
                    $delivery_id,
                    $original_name,
                    $relative_path,
                    $file_size,
                    $notes,
                    $uploaded_by
                );
                
                if (!mysqli_stmt_execute($pod_stmt)) {
                    throw new Exception('Failed to save POD record: ' . mysqli_error($conn));
                }
                
                $uploaded_count++;
            }
        }
    }
    
    if ($uploaded_count === 0) {
        throw new Exception('No files were uploaded successfully');
    }
    
    // Update delivery status to Confirmed if not already
    if ($delivery['status'] === 'Delivered') {
        $update_query = "UPDATE delivery_items SET status = 'Confirmed' WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, 'i', $delivery_id);
        mysqli_stmt_execute($update_stmt);
    }
    
    // Log activity
    $activity_desc = "POD uploaded: {$uploaded_count} file(s)";
    if (!empty($notes)) {
        $activity_desc .= " | Notes: " . substr($notes, 0, 100);
    }
    
    $log_query = "INSERT INTO delivery_activity_log 
                  (delivery_id, activity_type, description, performed_by) 
                  VALUES (?, 'pod_uploaded', ?, ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    mysqli_stmt_bind_param($log_stmt, 'isi', $delivery_id, $activity_desc, $uploaded_by);
    mysqli_stmt_execute($log_stmt);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'POD uploaded successfully',
        'files_uploaded' => $uploaded_count
    ]);
    
    header('Location: ../view.php?id=' . $delivery_id);
    exit;
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

closeConnection($conn);
?>
