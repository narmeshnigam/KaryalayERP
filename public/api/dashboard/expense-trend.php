<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
$conn = createConnection(true); if(!$conn){ http_response_code(500); echo json_encode(['success'=>false]); exit; }
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }

// last 6 months including current
$labels=[]; $months=[]; $now = new DateTime('first day of this month');
for($i=5;$i>=0;$i--){ $m=(clone $now)->modify("-$i month"); $labels[]=$m->format('M'); $months[]=$m->format('Y-m'); }
$exp = []; $sal=[]; $reimb=[];
foreach($months as $m){ $exp[] = 0; $sal[] = 0; $reimb[] = 0; }

if (t_exists($conn,'office_expenses')){
  $sql = "SELECT DATE_FORMAT(date,'%Y-%m') m, COALESCE(SUM(amount),0) s FROM office_expenses WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY m";
  $r = mysqli_query($conn,$sql); $map=[]; while($r && $row=mysqli_fetch_assoc($r)){ $map[$row['m']] = (float)$row['s']; } if($r) mysqli_free_result($r);
  foreach($months as $i=>$m){ $exp[$i] = $map[$m] ?? 0; }
}
if (t_exists($conn,'salary_records')){
  $sql = "SELECT month m, COALESCE(SUM(net_pay),0) s FROM salary_records WHERE month >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'%Y-%m') GROUP BY month";
  $r = mysqli_query($conn,$sql); $map=[]; while($r && $row=mysqli_fetch_assoc($r)){ $map[$row['m']] = (float)$row['s']; } if($r) mysqli_free_result($r);
  foreach($months as $i=>$m){ $sal[$i] = $map[$m] ?? 0; }
}
if (t_exists($conn,'reimbursements')){
  $sql = "SELECT DATE_FORMAT(expense_date,'%Y-%m') m, COALESCE(SUM(amount),0) s FROM reimbursements WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status='Approved' GROUP BY m";
  $r = mysqli_query($conn,$sql); $map=[]; while($r && $row=mysqli_fetch_assoc($r)){ $map[$row['m']] = (float)$row['s']; } if($r) mysqli_free_result($r);
  foreach($months as $i=>$m){ $reimb[$i] = $map[$m] ?? 0; }
}

echo json_encode(['success'=>true,'labels'=>$labels,'expenses'=>$exp,'salary'=>$sal,'reimb'=>$reimb]);
?>