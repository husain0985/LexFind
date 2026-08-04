<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');

$advisorId = (int)($_GET['advisor_id'] ?? 0);

$db = getDB();
$stmt = $db->prepare('SELECT bar_id_card FROM advisor_profiles WHERE id = ?');
$stmt->execute([$advisorId]);
$row = $stmt->fetch();

if (!$row || empty($row['bar_id_card'])) {
    http_response_code(404);
    exit('ID card not found.');
}

$filename = $row['bar_id_card'];
$path = __DIR__ . '/../uploads/bar_ids/' . $filename;

if (!is_file($path)) {
    http_response_code(404);
    exit('ID card not found.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="bar-id-' . $advisorId . '.' . $ext . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;