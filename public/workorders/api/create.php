<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = createConnection();
if (!$conn) {
    flash_add('error', 'Database connection failed. Please try again later.');
    header('Location: ../create.php');
    exit;
}

/**
 * Work Orders API - Create New Work Order
 */

// Removed auth_check.php include

// Permission checks removed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../create.php');
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Generate work order code: WO-YY-MM-XXX
    $year = date('y');
    $month = date('m');
    $prefix = "WO-{$year}-{$month}-";
    
    $code_query = "SELECT work_order_code FROM work_orders WHERE work_order_code LIKE ? ORDER BY work_order_code DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $code_query);
    $like_pattern = $prefix . '%';
    mysqli_stmt_bind_param($stmt, 's', $like_pattern);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $last_code = $row['work_order_code'];
        $last_num = intval(substr($last_code, -3));
        $new_num = $last_num + 1;
    } else {
        $new_num = 1;
    }
    
    $work_order_code = $prefix . str_pad($new_num, 3, '0', STR_PAD_LEFT);
    
    // Determine linked_id based on linked_type
    $linked_type = $_POST['linked_type'] ?? '';
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
    
    // Validate deliverables
    if (empty($_POST['deliverable_name']) || !is_array($_POST['deliverable_name']) || count($_POST['deliverable_name']) === 0) {
        throw new Exception("At least one deliverable is required");
    }
    
    // Insert work order
    $insert_query = "INSERT INTO work_orders (
        work_order_code, order_date, linked_type, linked_id, service_type, 
        priority, status, start_date, due_date, description, 
        dependencies, exceptions, remarks, internal_approver, 
        created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    
    $order_date = $_POST['order_date'];
    $service_type = $_POST['service_type'];
    $priority = $_POST['priority'];
    $status = $_POST['status'] ?? 'Draft';
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $description = $_POST['description'];
    $dependencies = $_POST['dependencies'] ?? null;
    $exceptions = $_POST['exceptions'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $internal_approver = !empty($_POST['internal_approver']) ? intval($_POST['internal_approver']) : 0;
    $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    mysqli_stmt_bind_param($stmt, 'sssisssssssssii', 
        $work_order_code, $order_date, $linked_type, $linked_id, $service_type,
        $priority, $status, $start_date, $due_date, $description,
        $dependencies, $exceptions, $remarks, $internal_approver, $created_by
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to create work order: " . mysqli_error($conn));
    }
    
    $work_order_id = mysqli_insert_id($conn);
    
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
    
    // Insert deliverables
    $deliv_query = "INSERT INTO work_order_deliverables (
        work_order_id, deliverable_name, description, assigned_to, 
        start_date, due_date, delivery_status
    ) VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
    $deliv_stmt = mysqli_prepare($conn, $deliv_query);
    
    foreach ($_POST['deliverable_name'] as $index => $name) {
        if (!empty($name)) {
            $assigned_to = intval($_POST['deliverable_assigned'][$index]);
            $deliv_start = $_POST['deliverable_start'][$index];
            $deliv_due = $_POST['deliverable_due'][$index];
            $deliv_desc = $_POST['deliverable_desc'][$index] ?? '';
            
            mysqli_stmt_bind_param($deliv_stmt, 'ississ', 
                $work_order_id, $name, $deliv_desc, $assigned_to, $deliv_start, $deliv_due
            );
            
            if (!mysqli_stmt_execute($deliv_stmt)) {
                throw new Exception("Failed to add deliverable: " . mysqli_error($conn));
            }
        }
    }
    
    // Handle file uploads
    if (!empty($_FILES['files']['name'][0])) {
        $upload_dir = __DIR__ . '/../../../uploads/workorders/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
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
                    
                    mysqli_stmt_bind_param($file_stmt, 'isssi', 
                        $work_order_id, $file_name, $db_file_path, $file_type, $created_by
                    );
                    mysqli_stmt_execute($file_stmt);
                }
            }
        }
    }
    
    // Log activity
    $activity_query = "INSERT INTO work_order_activity_log (work_order_id, action_type, action_by, description) VALUES (?, 'Create', ?, 'Work order created')";
    $activity_stmt = mysqli_prepare($conn, $activity_query);
    mysqli_stmt_bind_param($activity_stmt, 'ii', $work_order_id, $created_by);
    mysqli_stmt_execute($activity_stmt);
    
    // Commit transaction
    mysqli_commit($conn);

    flash_add('success', "Work order {$work_order_code} created successfully!");
    
    header('Location: ../view.php?id=' . $work_order_id);
    exit;
    
} catch (Exception $e) {
    mysqli_rollback($conn);

    flash_add('error', 'Error: ' . $e->getMessage());

    header('Location: ../create.php');
    exit;
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>
