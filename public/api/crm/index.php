<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'unauthorized']); exit; }

$conn = createConnection(true);
if (!$conn) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'db']); exit; }

// Avoid SQL errors when module is not set up yet
require_once __DIR__ . '/../../crm/helpers.php';
if (!crm_tables_exist($conn)) {
    http_response_code(503);
    echo json_encode(['success'=>false,'error'=>'crm_not_installed']);
    exit;
}

$type = strtolower($_GET['type'] ?? $_POST['type'] ?? '');
$valid = ['tasks','calls','meetings','visits','leads'];
if (!in_array($type, $valid, true)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'invalid_type']); exit; }
$table = 'crm_' . $type;
$method = $_SERVER['REQUEST_METHOD'];

function param($k, $default=null) { return $_POST[$k] ?? $_GET[$k] ?? $default; }

if ($method === 'GET') {
    // List
    $where = ['deleted_at IS NULL'];
    $params = [];$types='';
    $employee_id = (int)(param('employee_id', 0));
    if ($employee_id) {
        if ($type==='tasks' || $type==='leads') { $where[]='assigned_to = ?'; $types.='i'; $params[]=$employee_id; }
        else { $where[]='employee_id = ?'; $types.='i'; $params[]=$employee_id; }
    }
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 500';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt && $types!=='') { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    if ($stmt) { mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); }
    else { $res = mysqli_query($conn, $sql); }
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } if ($stmt) mysqli_stmt_close($stmt); else mysqli_free_result($res); }
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit;
}

if ($method === 'POST') {
    $action = strtolower(param('action','add'));
    if ($action === 'add') {
        // For brevity, only tasks add via API; others via pages. Extend later.
        if ($type === 'tasks') {
            $title = trim(param('title',''));
            $assigned_to = (int)param('assigned_to',0);
            $due_date = param('due_date','');
            $created_by = (int)($_SESSION['employee_id'] ?? 0);
            if ($title==='') { echo json_encode(['success'=>false,'error'=>'title_required']); exit; }
            $stmt = mysqli_prepare($conn, 'INSERT INTO crm_tasks (title, assigned_to, status, due_date, created_by) VALUES (?,?,?,?,?)');
            if (!$stmt) { echo json_encode(['success'=>false,'error'=>'stmt']); exit; }
            $status = 'Pending'; $due = $due_date!==''?$due_date:null;
            mysqli_stmt_bind_param($stmt, 'sisis', $title, $assigned_to, $status, $due, $created_by);
            $ok = mysqli_stmt_execute($stmt);
            $id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            echo json_encode(['success'=>$ok,'id'=>$id]);
            exit;
        }
        echo json_encode(['success'=>false,'error'=>'unsupported_add']);
        exit;
    } elseif ($action === 'update') {
        $id = (int)param('id',0);
        if ($type==='tasks' && $id) {
            $status = param('status','');
            if (!in_array($status,['Pending','In Progress','Completed'],true)) { echo json_encode(['success'=>false,'error'=>'bad_status']); exit; }
            $stmt = mysqli_prepare($conn, 'UPDATE crm_tasks SET status = ? WHERE id = ? AND deleted_at IS NULL');
            if ($stmt) { mysqli_stmt_bind_param($stmt, 'si', $status, $id); $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); echo json_encode(['success'=>$ok]); exit; }
        }
        echo json_encode(['success'=>false]); exit;
    }
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'id_required']); exit; }
    $stmt = mysqli_prepare($conn, 'UPDATE ' . $table . ' SET deleted_at = NOW() WHERE id = ?');
    if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $id); $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); echo json_encode(['success'=>$ok]); exit; }
    echo json_encode(['success'=>false]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'unsupported_method']);
