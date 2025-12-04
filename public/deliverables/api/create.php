<?php
/**
 * API: Create Deliverable
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = createConnection();

// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user ID (you may need to adjust this based on your auth system)
$created_by = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = "Invalid request method";
    $_SESSION['flash_type'] = 'error';
    header('Location: ../index.php');
    exit;
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Validate required fields
    $work_order_id = isset($_POST['work_order_id']) ? intval($_POST['work_order_id']) : 0;
    $deliverable_name = trim($_POST['deliverable_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : 0;
    $submission_notes = trim($_POST['submission_notes'] ?? '');

    if (empty($work_order_id) || empty($deliverable_name) || empty($description) || empty($assigned_to)) {
        throw new Exception("All required fields must be filled");
    }

    // Insert deliverable
    $insert_query = "INSERT INTO deliverables 
        (work_order_id, deliverable_name, description, assigned_to, current_version, status, created_by, created_at) 
        VALUES (?, ?, ?, ?, 1, 'Draft', ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, 'issii', $work_order_id, $deliverable_name, $description, $assigned_to, $created_by);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to create deliverable: " . mysqli_error($conn));
    }

    $deliverable_id = mysqli_insert_id($conn);

    // Create initial version record
    $version_query = "INSERT INTO deliverable_versions 
        (deliverable_id, version_no, submitted_by, submission_notes, approval_internal, approval_client, revision_requested, created_at) 
        VALUES (?, 1, ?, ?, 0, 0, 0, NOW())";
    
    $version_stmt = mysqli_prepare($conn, $version_query);
    mysqli_stmt_bind_param($version_stmt, 'iis', $deliverable_id, $created_by, $submission_notes);
    
    if (!mysqli_stmt_execute($version_stmt)) {
        throw new Exception("Failed to create version record: " . mysqli_error($conn));
    }

    // Handle file uploads
    if (!empty($_FILES['files']['name'][0])) {
        $upload_dir = __DIR__ . '/../../../uploads/deliverables/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];
        $max_file_size = 10 * 1024 * 1024; // 10MB

        $file_query = "INSERT INTO deliverable_files 
            (deliverable_id, version_no, file_name, file_path, file_size, file_type, uploaded_by, uploaded_at) 
            VALUES (?, 1, ?, ?, ?, ?, ?, NOW())";
        $file_stmt = mysqli_prepare($conn, $file_query);

        foreach ($_FILES['files']['name'] as $index => $file_name) {
            if ($_FILES['files']['error'][$index] === UPLOAD_ERR_OK) {
                $file_size = $_FILES['files']['size'][$index];
                $file_tmp = $_FILES['files']['tmp_name'][$index];

                // Validate file size
                if ($file_size > $max_file_size) {
                    throw new Exception("File {$file_name} exceeds 10MB limit");
                }

                // Validate file extension
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_extensions)) {
                    throw new Exception("File type .{$file_ext} is not allowed");
                }

                // Generate unique file name
                $unique_name = 'deliv_' . $deliverable_id . '_v1_' . time() . '_' . uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $unique_name;

                if (move_uploaded_file($file_tmp, $file_path)) {
                    $db_file_path = 'uploads/deliverables/' . $unique_name;
                    $file_type = $_FILES['files']['type'][$index];

                    mysqli_stmt_bind_param($file_stmt, 'isssis', 
                        $deliverable_id, $file_name, $db_file_path, $file_size, $file_type, $created_by
                    );
                    mysqli_stmt_execute($file_stmt);
                }
            }
        }
    }

    // Log activity
    $activity_query = "INSERT INTO deliverable_activity_log 
        (deliverable_id, action_by, action_type, notes, created_at) 
        VALUES (?, ?, 'Create', 'Deliverable created', NOW())";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    mysqli_stmt_bind_param($activity_stmt, 'ii', $deliverable_id, $created_by);
    mysqli_stmt_execute($activity_stmt);

    // Commit transaction
    mysqli_commit($conn);

    $_SESSION['flash_message'] = "Deliverable created successfully!";
    $_SESSION['flash_type'] = 'success';

    header('Location: ../view.php?id=' . $deliverable_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    
    header('Location: ../create.php');
    exit;
}

closeConnection($conn);
?>
