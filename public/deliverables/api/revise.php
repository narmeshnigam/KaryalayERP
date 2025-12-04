<?php
/**
 * API: Submit Revision for Deliverable
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = createConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = "Invalid request method";
    $_SESSION['flash_type'] = 'error';
    header('Location: ../index.php');
    exit;
}

mysqli_begin_transaction($conn);

try {
    $deliverable_id = isset($_POST['deliverable_id']) ? intval($_POST['deliverable_id']) : 0;
    $submission_notes = trim($_POST['submission_notes'] ?? '');

    if (!$deliverable_id || empty($submission_notes)) {
        throw new Exception("Deliverable ID and revision notes are required");
    }

    // Check if files are uploaded
    if (empty($_FILES['files']['name'][0])) {
        throw new Exception("At least one file must be uploaded for revision");
    }

    // Get current version
    $check_query = "SELECT current_version, status FROM deliverables WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, 'i', $deliverable_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $current = mysqli_fetch_assoc($check_result);

    if (!$current) {
        throw new Exception("Deliverable not found");
    }

    // Increment version
    $new_version = $current['current_version'] + 1;

    // Update deliverable
    $update_query = "UPDATE deliverables SET current_version = ?, status = 'Submitted', updated_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'ii', $new_version, $deliverable_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception("Failed to update deliverable");
    }

    // Create new version record
    $version_query = "INSERT INTO deliverable_versions 
        (deliverable_id, version_no, submitted_by, submission_notes, approval_internal, approval_client, revision_requested, created_at) 
        VALUES (?, ?, ?, ?, 0, 0, 0, NOW())";
    $version_stmt = mysqli_prepare($conn, $version_query);
    mysqli_stmt_bind_param($version_stmt, 'iiis', $deliverable_id, $new_version, $user_id, $submission_notes);
    
    if (!mysqli_stmt_execute($version_stmt)) {
        throw new Exception("Failed to create version record");
    }

    // Handle file uploads
    $upload_dir = __DIR__ . '/../../../uploads/deliverables/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];
    $max_file_size = 10 * 1024 * 1024; // 10MB

    $file_query = "INSERT INTO deliverable_files 
        (deliverable_id, version_no, file_name, file_path, file_size, file_type, uploaded_by, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $file_stmt = mysqli_prepare($conn, $file_query);

    foreach ($_FILES['files']['name'] as $index => $file_name) {
        if ($_FILES['files']['error'][$index] === UPLOAD_ERR_OK) {
            $file_size = $_FILES['files']['size'][$index];
            $file_tmp = $_FILES['files']['tmp_name'][$index];

            if ($file_size > $max_file_size) {
                throw new Exception("File {$file_name} exceeds 10MB limit");
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception("File type .{$file_ext} is not allowed");
            }

            $unique_name = 'deliv_' . $deliverable_id . '_v' . $new_version . '_' . time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $unique_name;

            if (move_uploaded_file($file_tmp, $file_path)) {
                $db_file_path = 'uploads/deliverables/' . $unique_name;
                $file_type = $_FILES['files']['type'][$index];

                mysqli_stmt_bind_param($file_stmt, 'iissisi', 
                    $deliverable_id, $new_version, $file_name, $db_file_path, $file_size, $file_type, $user_id
                );
                mysqli_stmt_execute($file_stmt);
            }
        }
    }

    // Log activity
    $activity_query = "INSERT INTO deliverable_activity_log 
        (deliverable_id, action_by, action_type, notes, created_at) 
        VALUES (?, ?, 'Submit', ?, NOW())";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    $activity_note = "Revision v{$new_version} submitted for review";
    mysqli_stmt_bind_param($activity_stmt, 'iis', $deliverable_id, $user_id, $activity_note);
    mysqli_stmt_execute($activity_stmt);

    mysqli_commit($conn);

    $_SESSION['flash_message'] = "Revision v{$new_version} submitted successfully!";
    $_SESSION['flash_type'] = 'success';

    header('Location: ../view.php?id=' . $deliverable_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    
    $deliverable_id = $_POST['deliverable_id'] ?? 0;
    header('Location: ../revise.php?id=' . $deliverable_id);
    exit;
}

closeConnection($conn);
?>
