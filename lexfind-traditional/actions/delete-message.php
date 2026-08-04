<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../admin/messages.php'); exit; }
csrfVerify();

$id = (int)($_POST['message_id'] ?? 0);
$db = getDB();
$stmt = $db->prepare('DELETE FROM contact_messages WHERE id = ?');
$stmt->execute([$id]);

setFlash('success', 'Message deleted.');
header('Location: ../admin/messages.php');
exit;