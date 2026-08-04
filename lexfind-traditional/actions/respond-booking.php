<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('advisor');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../advisor/dashboard.php'); exit; }
csrfVerify();
$bookingId = (int)($_POST['booking_id'] ?? 0);
$action = $_POST['action'] ?? '';
if (!in_array($action, ['accept', 'reject'], true)) { header('Location: ../advisor/dashboard.php'); exit; }

$db = getDB();
$stmt = $db->prepare('SELECT b.*, ap.user_id advisor_user_id, u.email client_email, u.first_name client_first_name FROM bookings b JOIN advisor_profiles ap ON ap.id = b.advisor_id JOIN users u ON u.id = b.client_id WHERE b.id = ?');
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking || (int)$booking['advisor_user_id'] !== (int)$u['id']) {
    setFlash('error', 'Booking not found.');
} elseif ($booking['payment_status'] === 'unpaid') {
    setFlash('error', 'This booking has not been paid for yet.');
} elseif ($booking['status'] !== 'pending') {
    setFlash('error', 'This booking has already been responded to.');
} elseif (isPastDateTime($booking['booking_date'], $booking['booking_time'])) {
    setFlash('error', 'This booking request has expired and can no longer be responded to.');
} else {
   $newStatus = $action === 'accept' ? 'confirmed' : 'rejected';
    if ($newStatus === 'rejected' && $booking['payment_status'] === 'paid') {
        $stmt = $db->prepare('UPDATE bookings SET status = ?, payment_status = "refunded" WHERE id = ?');
    } else {
        $stmt = $db->prepare('UPDATE bookings SET status = ? WHERE id = ?');
    }
    $stmt->execute([$newStatus, $bookingId]);
    notifyBookingResponse($booking['client_email'], $booking['client_first_name'], $u['first_name'] . ' ' . $u['last_name'], $booking['booking_date'], $booking['booking_time'], $newStatus);
    setFlash('success', 'Booking ' . $newStatus . '.');
}

$ref = $_SERVER['HTTP_REFERER'] ?? '';
header('Location: ' . (strpos($ref, 'bookings.php') !== false ? '../advisor/bookings.php' : '../advisor/dashboard.php'));
exit;
