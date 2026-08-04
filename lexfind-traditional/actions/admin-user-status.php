<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../admin/users.php'); exit; }
csrfVerify();
$userId = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$search = $_POST['search'] ?? '';
$roleFilter = $_POST['role_filter'] ?? '';

$db = getDB();
$stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    setFlash('error', 'User not found.');
} elseif ($row['role'] === 'admin') {
    setFlash('error', 'Admin accounts cannot be suspended.');
} elseif (!in_array($action, ['suspend', 'restore'], true)) {
    setFlash('error', 'Invalid action.');
} else {
    $newStatus = $action === 'suspend' ? 'suspended' : 'active';
    $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $userId]);
    setFlash('success', 'User ' . ($action === 'suspend' ? 'suspended' : 'restored') . '.');
}

$qs = http_build_query(['search' => $search, 'role' => $roleFilter]);
header('Location: ../admin/users.php' . ($qs ? '?' . $qs : ''));
exit;
