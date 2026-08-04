<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../admin/messages.php'); exit; }
csrfVerify();

$id = (int)($_POST['message_id'] ?? 0);
$markAs = $_POST['mark_as'] ?? 'read';
$isRead = $markAs === 'read' ? 1 : 0;

$db = getDB();
$stmt = $db->prepare('UPDATE contact_messages SET is_read = ? WHERE id = ?');
$stmt->execute([$isRead, $id]);

$redirect = ($_POST['redirect'] ?? '') === 'view' ? '../admin/message-view.php?id=' . $id : '../admin/messages.php';
header('Location: ' . $redirect);
exit;