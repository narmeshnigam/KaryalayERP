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
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { http_response_code(422); echo json_encode(['error'=>'No file uploaded']); exit; }

$allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/svg+xml'=>'svg'];
$mime = mime_content_type($_FILES['file']['tmp_name']);
if (!isset($allowed[$mime])) { http_response_code(422); echo json_encode(['error'=>'Invalid file type']); exit; }
if ($_FILES['file']['size'] > 2*1024*1024) { http_response_code(422); echo json_encode(['error'=>'File too large (max 2MB)']); exit; }

$ext = $allowed[$mime];
$dir = __DIR__ . '/../../../../uploads/branding';
if (!is_dir($dir) && !mkdir($dir, 0777, true)) { http_response_code(500); echo json_encode(['error'=>'Failed to prepare upload directory']); exit; }

$fname = 'logo_' . $type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $dir . '/' . $fname;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) { http_response_code(500); echo json_encode(['error'=>'Failed to move file']); exit; }

$relPath = '/uploads/branding/' . $fname;

$conn = createConnection(true);
if (!$conn) { http_response_code(500); echo json_encode(['error'=>'DB connection failed']); exit; }

$col = $type === 'light' ? 'logo_light' : ($type === 'dark' ? 'logo_dark' : 'logo_square');
$res = mysqli_query($conn, 'SELECT id FROM branding_settings ORDER BY id ASC LIMIT 1');
$row = $res ? mysqli_fetch_assoc($res) : null; if ($res) mysqli_free_result($res);
if ($row) {
  $id = (int)$row['id'];
  $stmt = mysqli_prepare($conn, "UPDATE branding_settings SET $col=?, updated_at=NOW() WHERE id=?");
  mysqli_stmt_bind_param($stmt, 'si', $relPath, $id);
  $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
} else {
  $stmt = mysqli_prepare($conn, "INSERT INTO branding_settings ($col, created_by, updated_at) VALUES (?,?,NOW())");
  mysqli_stmt_bind_param($stmt, 'si', $relPath, $_SESSION['user_id']);
  $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
}

if (!$ok) { http_response_code(500); echo json_encode(['error'=>'Failed to save logo path']); }
else { echo json_encode(['success'=>true, 'path'=>$relPath]); }

closeConnection($conn);
