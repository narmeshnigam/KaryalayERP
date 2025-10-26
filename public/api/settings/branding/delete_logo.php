<?php
require_once __DIR__ . '/../../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../config/db_connect.php';

header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
$role = strtolower($_SESSION['role'] ?? 'employee');
if ($role !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

$type = $_POST['type'] ?? '';
if (!in_array($type, ['light','dark','square'], true)) { http_response_code(422); echo json_encode(['error'=>'Invalid type']); exit; }

$conn = createConnection(true);
if (!$conn) { http_response_code(500); echo json_encode(['error'=>'DB connection failed']); exit; }

$col = $type === 'light' ? 'logo_light' : ($type === 'dark' ? 'logo_dark' : 'logo_square');
$ok = mysqli_query($conn, "UPDATE branding_settings SET $col=NULL, updated_at=NOW() WHERE id = (SELECT id FROM (SELECT id FROM branding_settings ORDER BY id ASC LIMIT 1) t)");
if (!$ok) { http_response_code(500); echo json_encode(['error'=>'Failed to delete logo']); }
else { echo json_encode(['success'=>true]); }

closeConnection($conn);
