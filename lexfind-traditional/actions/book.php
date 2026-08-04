<?php
require_once __DIR__ . '/../includes/auth.php';

$u = requireLogin('client');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../search.php'); exit; }
csrfVerify();
$advisorId = (int)($_POST['advisor_id'] ?? 0);
$date = trim($_POST['booking_date'] ?? '');
$time = trim($_POST['booking_time'] ?? '');
$type = trim($_POST['meeting_type'] ?? 'Video Call');
$desc = trim($_POST['case_description'] ?? '');
$whatsapp = trim($_POST['whatsapp_number'] ?? '');
$backUrl = '../advisor.php?id=' . $advisorId;

if ($advisorId <= 0 || $date === '' || $time === '') {
    $_SESSION['book_errors'] = 'Please choose a date and time slot.';
    header('Location: ' . $backUrl); exit;
}
if (!in_array($type, ['Video Call', 'Phone Call', 'In-Person'], true)) $type = 'Video Call';
if (in_array($type, ['Video Call', 'Phone Call'], true) && $whatsapp === '') {
    $_SESSION['book_errors'] = 'Please provide a WhatsApp number for a ' . $type . '.';
    header('Location: ' . $backUrl); exit;
}

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') < date('Y-m-d')) {
    $_SESSION['book_errors'] = 'Please choose a valid date in the future.';
    header('Location: ' . $backUrl); exit;
}

$db = getDB();
$stmt = $db->prepare(
    'SELECT ap.id, ap.fee, u.email advisor_email, u.first_name advisor_first_name FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.id = ? AND ap.approval_status = "approved" AND u.status = "active"'
);
$stmt->execute([$advisorId]);
$advisor = $stmt->fetch();
if (!$advisor) {
    setFlash('error', 'Advisor not found.');
    header('Location: ../search.php'); exit;
}
$dayOfWeek = (((int)date('N', strtotime($date))) - 1 + 7) % 7; // 0=Mon ... 6=Sun
$stmt = $db->prepare('SELECT 1 FROM advisor_availability WHERE advisor_id = ? AND day_of_week = ? AND is_active = 1');
$stmt->execute([$advisorId, $dayOfWeek]);
if (!$stmt->fetch()) {
    setFlash('error', 'This advisor is not available on that day. Please choose one of their available days.');
    header('Location: ' . $backUrl); exit;
}

expireStaleUnpaidBookings($db, $advisorId, $date, $time);

$stmt = $db->prepare(
    'SELECT id FROM bookings WHERE advisor_id = ? AND booking_date = ? AND booking_time = ? AND status IN ("pending","confirmed")'
);
$stmt->execute([$advisorId, $date, $time]);
if ($stmt->fetch()) {
    setFlash('error', 'Sorry, that time slot was just booked by someone else. Please choose another slot.');
    header('Location: ' . $backUrl); exit;
}

try {
    $stmt = $db->prepare(
        'INSERT INTO bookings (client_id, advisor_id, booking_date, booking_time, meeting_type, case_description, whatsapp_number, status, fee, payment_status)
         VALUES (?,?,?,?,?,?,?,"pending",?,"unpaid")'
    );
    $stmt->execute([$u['id'], $advisorId, $date, $time, $type, $desc, $whatsapp ?: null, $advisor['fee']]);
    $bookingId = (int)$db->lastInsertId();
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        setFlash('error', 'Sorry, that time slot was just booked by someone else. Please choose another slot.');
        header('Location: ' . $backUrl); exit;
    }
    throw $e;
}
// Payment happens next — the advisor is only notified once payment.php / process-payment.php confirms it.
header('Location: ../payment.php?booking_id=' . $bookingId);
exit;
