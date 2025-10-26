<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode([]); exit; }
$conn = createConnection(true); if(!$conn){ http_response_code(500); echo json_encode([]); exit; }
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }
$rows=[];
if (t_exists($conn,'visitor_logs')){
  $r = mysqli_query($conn, "SELECT visitor_name, purpose, check_in_time, employee_id FROM visitor_logs WHERE DATE(check_in_time) = CURDATE() AND deleted_at IS NULL ORDER BY check_in_time DESC LIMIT 20");
  while($r && $row=mysqli_fetch_assoc($r)){
    $rows[] = ['name'=>$row['visitor_name'],'purpose'=>$row['purpose'],'time'=>$row['check_in_time'],'employee'=>$row['employee_id']];
  }
  if($r) mysqli_free_result($r);
}
echo json_encode($rows);
?>