<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../admin/approvals.php'); exit; }
csrfVerify();
$advisorId = (int)($_POST['advisor_id'] ?? 0);
$action = $_POST['action'] ?? '';

$db = getDB();
$stmt = $db->prepare('SELECT ap.id, u.email advisor_email, u.first_name advisor_first_name FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id WHERE ap.id = ?');
$stmt->execute([$advisorId]);
$advisor = $stmt->fetch();

if (!$advisor) {
    setFlash('error', 'Advisor application not found.');
} elseif (!in_array($action, ['approve', 'reject'], true)) {
    setFlash('error', 'Invalid action.');
} else {
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $db->prepare('UPDATE advisor_profiles SET approval_status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $advisorId]);
    notifyAdvisorApproval($advisor['advisor_email'], $advisor['advisor_first_name'], $newStatus);
    setFlash('success', 'Advisor ' . $newStatus . '.');
}
header('Location: ../admin/approvals.php');
exit;
