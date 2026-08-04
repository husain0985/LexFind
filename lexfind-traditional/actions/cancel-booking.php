<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin('client');
// Clients are not permitted to cancel bookings — this endpoint is intentionally disabled.
setFlash('error', 'Cancelling a booking is not permitted. Please contact the advisor if you need to reschedule.');
header('Location: ../client/bookings.php');
exit;