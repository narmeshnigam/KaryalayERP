<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
$conn = createConnection(true); if(!$conn){ http_response_code(500); echo json_encode(['success'=>false]); exit; }
function t_exists($c,$t){ $t=mysqli_real_escape_string($c,$t); $r=mysqli_query($c,"SHOW TABLES LIKE '$t'"); $ok=$r&&mysqli_num_rows($r)>0; if($r)mysqli_free_result($r); return $ok; }

$startMonth = date('Y-m-01'); $endMonth = date('Y-m-t');
$active = $conv = $drop = 0;
if (t_exists($conn,'crm_leads')){
  $active = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM crm_leads WHERE status IN ('New','Contacted') AND created_at BETWEEN '$startMonth 00:00:00' AND '$endMonth 23:59:59' AND deleted_at IS NULL"))[0] ?? 0);
  $conv   = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM crm_leads WHERE status='Converted' AND created_at BETWEEN '$startMonth 00:00:00' AND '$endMonth 23:59:59' AND deleted_at IS NULL"))[0] ?? 0);
  $drop   = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM crm_leads WHERE status='Dropped' AND created_at BETWEEN '$startMonth 00:00:00' AND '$endMonth 23:59:59' AND deleted_at IS NULL"))[0] ?? 0);
}

echo json_encode(['success'=>true,'data'=>[$active,$conv,$drop]]);
?>