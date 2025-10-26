<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode([]); exit; }
$conn = createConnection(true); if(!$conn){ http_response_code(500); echo json_encode([]); exit; }
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }
$limit = max(1,(int)($_GET['limit'] ?? 5));
$out = [];
if (t_exists($conn,'reimbursements')){
  $r = mysqli_query($conn, "SELECT 'Reimbursement' AS type, date_submitted AS date, amount, status, employee_id FROM reimbursements ORDER BY date_submitted DESC LIMIT $limit");
  while($r && $row=mysqli_fetch_assoc($r)){ $out[]=['type'=>'Reimbursement','date'=>$row['date'],'employee'=>$row['employee_id'],'amount'=>(float)$row['amount'],'status'=>$row['status']]; }
  if($r) mysqli_free_result($r);
}
if (t_exists($conn,'office_expenses')){
  $r = mysqli_query($conn, "SELECT 'Expense' AS type, date AS date, amount, 'Booked' AS status, added_by AS employee FROM office_expenses ORDER BY date DESC LIMIT $limit");
  while($r && $row=mysqli_fetch_assoc($r)){ $out[]=['type'=>'Expense','date'=>$row['date'],'employee'=>$row['employee'],'amount'=>(float)$row['amount'],'status'=>$row['status']]; }
  if($r) mysqli_free_result($r);
}
usort($out, function($a,$b){ return strcmp($b['date'],$a['date']); });
$out = array_slice($out, 0, $limit);
echo json_encode($out);
?>