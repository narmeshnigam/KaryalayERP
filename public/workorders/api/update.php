require_once __DIR__ . '/../../../config/config.php';
<?php
/**
 * Work Orders API - Update Work Order
 */

// Removed auth_check.php include

// Permission checks removed

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$work_order_id = isset($_POST['work_order_id']) ? intval($_POST['work_order_id']) : 0;

if (!$work_order_id) {
    $_SESSION['flash_message'] = "Invalid work order ID";
    $_SESSION['flash_type'] = 'error';
    header('Location: ../index.php');
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Determine linked_id based on linked_type
    $linked_type = $_POST['linked_type'];
    $linked_id = null;
    
    if ($linked_type === 'Lead') {
        $linked_id = intval($_POST['linked_id_lead']);
    } elseif ($linked_type === 'Client') {
        $linked_id = intval($_POST['linked_id_client']);
    }
    
    if (!$linked_id) {
        throw new Exception("Lead or Client selection is required");
    }
    
    // Validate required fields
    if (empty($_POST['service_type']) || empty($_POST['priority']) || empty($_POST['start_date']) || empty($_POST['due_date']) || empty($_POST['description'])) {
        throw new Exception("All required fields must be filled");
    }
    
    // Update work order
    $update_query = "UPDATE work_orders SET 
        order_date = ?, linked_type = ?, linked_id = ?, service_type = ?, 
        priority = ?, status = ?, start_date = ?, due_date = ?, description = ?, 
        dependencies = ?, exceptions = ?, remarks = ?, internal_approver = ?, 
        updated_at = NOW()
        WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    
    $order_date = $_POST['order_date'];
    $service_type = $_POST['service_type'];
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $description = $_POST['description'];
    $dependencies = $_POST['dependencies'] ?? null;
    $exceptions = $_POST['exceptions'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $internal_approver = !empty($_POST['internal_approver']) ? intval($_POST['internal_approver']) : null;
    
    mysqli_stmt_bind_param($stmt, 'ssissississsii', 
        $order_date, $linked_type, $linked_id, $service_type,
        $priority, $status, $start_date, $due_date, $description,
        $dependencies, $exceptions, $remarks, $internal_approver, $work_order_id
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update work order: " . mysqli_error($conn));
    }
    
    // Delete all existing team members and re-insert
    $delete_team_query = "DELETE FROM work_order_team WHERE work_order_id = ?";
    $delete_team_stmt = mysqli_prepare($conn, $delete_team_query);
    mysqli_stmt_bind_param($delete_team_stmt, 'i', $work_order_id);
    mysqli_stmt_execute($delete_team_stmt);
    
    // Insert team members
    if (!empty($_POST['team_employee']) && is_array($_POST['team_employee'])) {
        $team_query = "INSERT INTO work_order_team (work_order_id, employee_id, role, remarks) VALUES (?, ?, ?, ?)";
        $team_stmt = mysqli_prepare($conn, $team_query);
        
        foreach ($_POST['team_employee'] as $index => $emp_id) {
            if (!empty($emp_id)) {
                $employee_id = intval($emp_id);
                $role = $_POST['team_role'][$index] ?? '';
                $team_remarks = $_POST['team_remarks'][$index] ?? '';
                
                mysqli_stmt_bind_param($team_stmt, 'iiss', $work_order_id, $employee_id, $role, $team_remarks);
                mysqli_stmt_execute($team_stmt);
            }
        }
    }
    
    // Update or insert deliverables
    if (!empty($_POST['deliverable_name']) && is_array($_POST['deliverable_name'])) {
        foreach ($_POST['deliverable_name'] as $index => $name) {
            if (!empty($name)) {
                $assigned_to = intval($_POST['deliverable_assigned'][$index]);
                $deliv_start = $_POST['deliverable_start'][$index];
                $deliv_due = $_POST['deliverable_due'][$index];
                $deliv_desc = $_POST['deliverable_desc'][$index] ?? '';
                $deliv_status = $_POST['delivery_status'][$index] ?? 'Pending';
                $deliv_id = isset($_POST['deliverable_id'][$index]) && !empty($_POST['deliverable_id'][$index]) ? intval($_POST['deliverable_id'][$index]) : 0;
                
                if ($deliv_id > 0) {
                    // Update existing deliverable
                    $update_deliv_query = "UPDATE work_order_deliverables SET 
                        deliverable_name = ?, description = ?, assigned_to = ?, 
                        start_date = ?, due_date = ?, delivery_status = ?
                        WHERE id = ? AND work_order_id = ?";
                    $update_deliv_stmt = mysqli_prepare($conn, $update_deliv_query);
                    mysqli_stmt_bind_param($update_deliv_stmt, 'ssisssii', 
                        $name, $deliv_desc, $assigned_to, $deliv_start, $deliv_due, $deliv_status, $deliv_id, $work_order_id
                    );
                    mysqli_stmt_execute($update_deliv_stmt);
                } else {
                    // Insert new deliverable
                    $insert_deliv_query = "INSERT INTO work_order_deliverables (
                        work_order_id, deliverable_name, description, assigned_to, 
                        start_date, due_date, delivery_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insert_deliv_stmt = mysqli_prepare($conn, $insert_deliv_query);
                    mysqli_stmt_bind_param($insert_deliv_stmt, 'issssss', 
                        $work_order_id, $name, $deliv_desc, $assigned_to, $deliv_start, $deliv_due, $deliv_status
                    );
                    mysqli_stmt_execute($insert_deliv_stmt);
                }
            }
        }
    }
    
    // Handle new file uploads
    if (!empty($_FILES['files']['name'][0])) {
        $upload_dir = __DIR__ . '/../../../uploads/workorders/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Get work order code for file naming
        $code_query = "SELECT work_order_code FROM work_orders WHERE id = ?";
        $code_stmt = mysqli_prepare($conn, $code_query);
        mysqli_stmt_bind_param($code_stmt, 'i', $work_order_id);
        mysqli_stmt_execute($code_stmt);
        $code_result = mysqli_stmt_get_result($code_stmt);
        $code_row = mysqli_fetch_assoc($code_result);
        $work_order_code = $code_row['work_order_code'];
        
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
        $max_file_size = 10 * 1024 * 1024; // 10MB
        
        $file_query = "INSERT INTO work_order_files (work_order_id, file_name, file_path, file_type, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
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
                $unique_name = $work_order_code . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $unique_name;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $db_file_path = 'uploads/workorders/' . $unique_name;
                    $file_type = $_FILES['files']['type'][$index];
                    $uploaded_by = $_SESSION['user_id'];
                    
                    mysqli_stmt_bind_param($file_stmt, 'isssi', 
                        $work_order_id, $file_name, $db_file_path, $file_type, $uploaded_by
                    );
                    mysqli_stmt_execute($file_stmt);
                }
            }
        }
    }
    
    // Log activity
    $activity_query = "INSERT INTO work_order_activity_log (work_order_id, action_type, action_by, description) VALUES (?, 'Update', ?, 'Work order updated')";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    $user_id = $_SESSION['user_id'];
    mysqli_stmt_bind_param($activity_stmt, 'ii', $work_order_id, $user_id);
    mysqli_stmt_execute($activity_stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['flash_message'] = "Work order updated successfully!";
    $_SESSION['flash_type'] = 'success';
    
    header('Location: ../view.php?id=' . $work_order_id);
    exit;
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    
    header('Location: ../edit.php?id=' . $work_order_id);
    exit;
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>
