<?php
/**
 * API: Create Delivery Item
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
    // Validate required fields
    $deliverable_id = isset($_POST['deliverable_id']) ? intval($_POST['deliverable_id']) : 0;
    $channel = isset($_POST['channel']) ? trim($_POST['channel']) : '';
    
    if (!$deliverable_id || empty($channel)) {
        throw new Exception('Deliverable and channel are required');
    }
    
    // Verify deliverable exists and is client-approved
    $check_query = "SELECT id, work_order_id, client_id, lead_id 
                    FROM deliverables 
                    WHERE id = ? AND status = 'Client Approved'";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 'i', $deliverable_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $deliverable = mysqli_fetch_assoc($result);
    
    if (!$deliverable) {
        throw new Exception('Deliverable not found or not client-approved');
    }
    
    // Get form data
    $delivered_by = !empty($_POST['delivered_by']) ? intval($_POST['delivered_by']) : null;
    $main_link = !empty($_POST['main_link']) ? trim($_POST['main_link']) : null;
    $delivered_to_name = !empty($_POST['delivered_to_name']) ? trim($_POST['delivered_to_name']) : null;
    $delivered_to_contact = !empty($_POST['delivered_to_contact']) ? trim($_POST['delivered_to_contact']) : null;
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
    $created_by = 1; // TODO: Replace with session user_id
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Insert delivery item
    $insert_query = "INSERT INTO delivery_items 
                     (deliverable_id, work_order_id, client_id, lead_id, channel, 
                      delivered_by, main_link, delivered_to_name, delivered_to_contact, 
                      notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, 'iiisisssssi',
        $deliverable_id,
        $deliverable['work_order_id'],
        $deliverable['client_id'],
        $deliverable['lead_id'],
        $channel,
        $delivered_by,
        $main_link,
        $delivered_to_name,
        $delivered_to_contact,
        $notes,
        $created_by
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to create delivery item: ' . mysqli_error($conn));
    }
    
    $delivery_id = mysqli_insert_id($conn);
    
    // Handle file uploads
    $uploaded_files = [];
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $upload_dir = __DIR__ . '/../../../uploads/delivery/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];
        $max_file_size = 20 * 1024 * 1024; // 20MB
        
        $file_count = count($_FILES['files']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $original_name = $_FILES['files']['name'][$i];
                $tmp_name = $_FILES['files']['tmp_name'][$i];
                $file_size = $_FILES['files']['size'][$i];
                
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
                $unique_name = time() . '_' . uniqid() . '_' . $original_name;
                $destination = $upload_dir . $unique_name;
                
                // Move uploaded file
                if (move_uploaded_file($tmp_name, $destination)) {
                    // Save to database
                    $file_insert = "INSERT INTO delivery_files 
                                   (delivery_id, file_name, file_path, file_size, uploaded_by) 
                                   VALUES (?, ?, ?, ?, ?)";
                    $file_stmt = mysqli_prepare($conn, $file_insert);
                    $relative_path = 'uploads/delivery/' . $unique_name;
                    mysqli_stmt_bind_param($file_stmt, 'issii',
                        $delivery_id,
                        $original_name,
                        $relative_path,
                        $file_size,
                        $created_by
                    );
                    
                    if (!mysqli_stmt_execute($file_stmt)) {
                        throw new Exception('Failed to save file record: ' . mysqli_error($conn));
                    }
                    
                    $uploaded_files[] = $original_name;
                }
            }
        }
    }
    
    // Log activity
    $activity_desc = "Delivery item created with status 'Pending'";
    if (!empty($uploaded_files)) {
        $activity_desc .= " | " . count($uploaded_files) . " file(s) attached";
    }
    
    $log_query = "INSERT INTO delivery_activity_log 
                  (delivery_id, activity_type, description, performed_by) 
                  VALUES (?, 'created', ?, ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    mysqli_stmt_bind_param($log_stmt, 'isi', $delivery_id, $activity_desc, $created_by);
    mysqli_stmt_execute($log_stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Delivery item created successfully',
        'delivery_id' => $delivery_id,
        'files_uploaded' => count($uploaded_files)
    ]);
    
    // Redirect
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
