<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
$conn = createConnection(true); if(!$conn){ http_response_code(500); echo json_encode(['success'=>false]); exit; }
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }
$labels=['W1','W2','W3','W4']; $assigned=[0,0,0,0]; $completed=[0,0,0,0];
if (t_exists($conn,'crm_tasks')){
  $sql = "SELECT WEEK(created_at,1) wk, COUNT(*) cnt FROM crm_tasks WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 28 DAY) GROUP BY wk ORDER BY wk";
  $res = mysqli_query($conn,$sql); $mapA=[]; while($res && $r=mysqli_fetch_assoc($res)){ $mapA[]=(int)$r['cnt']; } if($res) mysqli_free_result($res);
  $sql = "SELECT WEEK(completed_at,1) wk, COUNT(*) cnt FROM crm_tasks WHERE completed_at IS NOT NULL AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 28 DAY) GROUP BY wk ORDER BY wk";
  $res = mysqli_query($conn,$sql); $mapC=[]; while($res && $r=mysqli_fetch_assoc($res)){ $mapC[]=(int)$r['cnt']; } if($res) mysqli_free_result($res);
  // Just map in order; detailed labels aren't critical here
  $assigned = array_pad($mapA, 4, 0);
  $completed = array_pad($mapC, 4, 0);
}

echo json_encode(['success'=>true,'labels'=>$labels,'assigned'=>$assigned,'completed'=>$completed]);
?>