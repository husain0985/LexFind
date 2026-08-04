<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('client');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../client/bookings.php'); exit; }
csrfVerify();

$bookingId = (int)($_POST['booking_id'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? 'cards';

$db = getDB();
$stmt = $db->prepare(
    'SELECT b.*, ap.user_id advisor_user_id, au.email advisor_email, au.first_name advisor_first_name,
            TIMESTAMPDIFF(SECOND, b.created_at, NOW()) AS elapsed_seconds
     FROM bookings b JOIN advisor_profiles ap ON ap.id = b.advisor_id JOIN users au ON au.id = ap.user_id
     WHERE b.id = ? AND b.client_id = ?'
);
$stmt->execute([$bookingId, $u['id']]);
$b = $stmt->fetch();

if (!$b) {
    setFlash('error', 'Booking not found.');
    header('Location: ../client/bookings.php'); exit;
}
if ($b['status'] !== 'pending' || $b['payment_status'] !== 'unpaid') {
    setFlash('error', 'This booking is no longer awaiting payment.');
    header('Location: ../client/bookings.php'); exit;
}
// Elapsed time measured on MySQL's own clock — see payment.php for why.
if ((int)$b['elapsed_seconds'] > 15 * 60) {
    setFlash('error', 'This booking hold has expired. Please book the slot again.');
    header('Location: ../advisor.php?id=' . (int)$b['advisor_id']); exit;
}

// --- Demo "gateway" validation: just enough to look real, no actual charge is made. ---
$methodLabel = 'demo';
if ($paymentMethod === 'mobile') {
    $walletProvider = trim($_POST['wallet_provider'] ?? '');
    $walletNumber = preg_replace('/\s+/', '', $_POST['wallet_number'] ?? '');
    $walletPin = trim($_POST['wallet_pin'] ?? '');
    $allowedProviders = ['bKash', 'Nagad', 'Rocket', 'Upay'];

    if (!in_array($walletProvider, $allowedProviders, true)) {
        $_SESSION['pay_errors'] = 'Please select a mobile banking provider.';
        header('Location: ../payment.php?booking_id=' . $bookingId); exit;
    }
    if ($walletNumber === '' || $walletPin === '') {
        $_SESSION['pay_errors'] = 'Please enter your ' . $walletProvider . ' number and PIN.';
        header('Location: ../payment.php?booking_id=' . $bookingId); exit;
    }
    if (!preg_match('/^01[0-9]{9}$/', $walletNumber)) {
        $_SESSION['pay_errors'] = 'Please enter a valid 11-digit mobile number (e.g. 01XXXXXXXXX).';
        header('Location: ../payment.php?booking_id=' . $bookingId); exit;
    }
    if (!ctype_digit($walletPin) || strlen($walletPin) < 4 || strlen($walletPin) > 5) {
        $_SESSION['pay_errors'] = 'Please enter a valid PIN.';
        header('Location: ../payment.php?booking_id=' . $bookingId); exit;
    }
    $methodLabel = strtolower($walletProvider);
} else {
    $cardName = trim($_POST['card_name'] ?? '');
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $cardExpiry = trim($_POST['card_expiry'] ?? '');
    $cardCvv = trim($_POST['card_cvv'] ?? '');

    if ($cardName === '' || $cardNumber === '' || $cardExpiry === '' || $cardCvv === '') {
        $_SESSION['pay_errors'] = 'Please fill in all card details.';
        header('Location: ../payment.php?booking_id=' . $bookingId); exit;
    }
    if (!ctype_digit($cardNumber) || strlen($cardNumber) < 12 || strlen($cardNumber) > 19) {
        $_SESSION['pay_errors'] = 'Please enter a valid card number.';
        header('Location: ../payment.php?booking_id=' . $bookingId); exit;
    }
    if (!ctype_digit($cardCvv) || strlen($cardCvv) < 3 || strlen($cardCvv) > 4) {
        $_SESSION['pay_errors'] = 'Please enter a valid CVV.';
        header('Location: ../payment.php?booking_id=' . $bookingId); exit;
    }
    $methodLabel = 'card';
}

// This demo "gateway" always succeeds. Replace this block with a real provider call
// (SSLCommerz/bKash/Stripe) and branch on its response when you're ready to go live.
$transactionId = 'DEMO-' . strtoupper(bin2hex(random_bytes(8)));

$db->beginTransaction();
try {
    $stmt = $db->prepare('INSERT INTO payments (booking_id, amount, method, transaction_id, status) VALUES (?,?,?,?,"success")');
    $stmt->execute([$bookingId, $b['fee'], $methodLabel, $transactionId]);

    $stmt = $db->prepare('UPDATE bookings SET payment_status = "paid" WHERE id = ?');
    $stmt->execute([$bookingId]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['pay_errors'] = 'Payment could not be processed. Please try again.';
    header('Location: ../payment.php?booking_id=' . $bookingId); exit;
}

notifyNewBooking($b['advisor_email'], $b['advisor_first_name'], $u['first_name'] . ' ' . $u['last_name'], $b['booking_date'], $b['booking_time'], $b['meeting_type']);
setFlash('success', '✅ Payment received and booking request sent! The advisor will confirm shortly.');
header('Location: ../client/bookings.php');
exit;