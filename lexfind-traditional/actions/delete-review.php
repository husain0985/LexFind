<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('client');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../client/reviews.php'); exit; }
csrfVerify();
$reviewId = (int)($_POST['review_id'] ?? 0);

$db = getDB();
$stmt = $db->prepare('SELECT * FROM reviews WHERE id = ? AND client_id = ?');
$stmt->execute([$reviewId, $u['id']]);
$review = $stmt->fetch();

if (!$review) {
    setFlash('error', 'Review not found.');
} else {
    $advisorId = $review['advisor_id'];
    $stmt = $db->prepare('DELETE FROM reviews WHERE id = ?');
    $stmt->execute([$reviewId]);

    $stmt = $db->prepare('SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE advisor_id = ?');
    $stmt->execute([$advisorId]);
    $agg = $stmt->fetch();
    $stmt = $db->prepare('UPDATE advisor_profiles SET rating_avg = ?, rating_count = ? WHERE id = ?');
    $stmt->execute([$agg['c'] ? round((float)$agg['a'], 2) : 0, (int)$agg['c'], $advisorId]);

    setFlash('success', 'Review deleted.');
}
header('Location: ../client/reviews.php');
exit;