<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode([]); exit; }
$conn = createConnection(true); if(!$conn){ http_response_code(500); echo json_encode([]); exit; }
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }
$limit = max(1, (int)($_GET['limit'] ?? 5));
$rows = [];
$today = date('Y-m-d');
if (t_exists($conn,'crm_leads')){
  $q = "SELECT 'Lead' AS type, follow_up_date AS date, name AS lead, assigned_to, status FROM crm_leads WHERE follow_up_date >= '$today' AND deleted_at IS NULL ORDER BY follow_up_date ASC LIMIT $limit";
  $r = mysqli_query($conn,$q); while($r && $row=mysqli_fetch_assoc($r)){ $rows[] = ['type'=>'Lead','date'=>$row['date'],'lead'=>$row['lead'],'employee'=>'','status'=>$row['status']]; } if($r) mysqli_free_result($r);
}
if (t_exists($conn,'crm_tasks')){
  $q = "SELECT 'Task' AS type, follow_up_date AS date, lead_id, assigned_to, status FROM crm_tasks WHERE follow_up_date IS NOT NULL AND follow_up_date >= '$today' AND deleted_at IS NULL ORDER BY follow_up_date ASC LIMIT $limit";
  $r = mysqli_query($conn,$q); while($r && $row=mysqli_fetch_assoc($r)){ $rows[] = ['type'=>'Task','date'=>$row['date'],'lead'=>$row['lead_id'],'employee'=>'','status'=>$row['status']]; } if($r) mysqli_free_result($r);
}
if (t_exists($conn,'crm_calls')){
  $q = "SELECT 'Call' AS type, follow_up_date AS date, lead_id, assigned_to, outcome FROM crm_calls WHERE follow_up_date IS NOT NULL AND follow_up_date >= '$today' AND deleted_at IS NULL ORDER BY follow_up_date ASC LIMIT $limit";
  $r = mysqli_query($conn,$q); while($r && $row=mysqli_fetch_assoc($r)){ $rows[] = ['type'=>'Call','date'=>$row['date'],'lead'=>$row['lead_id'],'employee'=>'','status'=>$row['outcome']]; } if($r) mysqli_free_result($r);
}
if (t_exists($conn,'crm_meetings')){
  $q = "SELECT 'Meeting' AS type, follow_up_date AS date, lead_id, assigned_to, outcome FROM crm_meetings WHERE follow_up_date IS NOT NULL AND follow_up_date >= '$today' AND deleted_at IS NULL ORDER BY follow_up_date ASC LIMIT $limit";
  $r = mysqli_query($conn,$q); while($r && $row=mysqli_fetch_assoc($r)){ $rows[] = ['type'=>'Meeting','date'=>$row['date'],'lead'=>$row['lead_id'],'employee'=>'','status'=>$row['outcome']]; } if($r) mysqli_free_result($r);
}
if (t_exists($conn,'crm_visits')){
  $q = "SELECT 'Visit' AS type, follow_up_date AS date, lead_id, assigned_to, outcome FROM crm_visits WHERE follow_up_date IS NOT NULL AND follow_up_date >= '$today' AND deleted_at IS NULL ORDER BY follow_up_date ASC LIMIT $limit";
  $r = mysqli_query($conn,$q); while($r && $row=mysqli_fetch_assoc($r)){ $rows[] = ['type'=>'Visit','date'=>$row['date'],'lead'=>$row['lead_id'],'employee'=>'','status'=>$row['outcome']]; } if($r) mysqli_free_result($r);
}

usort($rows, function($a,$b){ return strcmp($a['date'],$b['date']); });
$rows = array_slice($rows, 0, $limit);
echo json_encode($rows);
?>