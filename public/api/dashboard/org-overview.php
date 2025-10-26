<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
$conn = createConnection(true); if(!$conn){ http_response_code(500); echo json_encode(['success'=>false]); exit; }
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }
$labels=[];$emp=[];$tasks=[];
if (t_exists($conn,'departments') && t_exists($conn,'employees')) {
  $qr = mysqli_query($conn, "SELECT d.department_name, COUNT(e.id) as cnt FROM departments d LEFT JOIN employees e ON e.department = d.department_name AND e.status='Active' GROUP BY d.department_name ORDER BY d.department_name");
  while($qr && $row=mysqli_fetch_assoc($qr)){ $labels[]=$row['department_name']; $emp[]=(int)$row['cnt']; }
  if($qr) mysqli_free_result($qr);
}
if (t_exists($conn,'crm_tasks') && t_exists($conn,'employees')) {
  // active tasks by department of assignee
  $qr = mysqli_query($conn, "SELECT e.department AS dept, COUNT(t.id) AS cnt FROM crm_tasks t LEFT JOIN employees e ON e.id = t.assigned_to WHERE t.deleted_at IS NULL AND t.status <> 'Completed' GROUP BY e.department");
  $map=[]; while($qr && $r=mysqli_fetch_assoc($qr)){ $map[$r['dept']??'Unknown']=(int)$r['cnt']; }
  if($qr) mysqli_free_result($qr);
  // align to labels
  foreach($labels as $d){ $tasks[] = $map[$d] ?? 0; }
}

echo json_encode(['success'=>true,'labels'=>$labels,'employees'=>$emp,'active_tasks'=>$tasks]);
?>