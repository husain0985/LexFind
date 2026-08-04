<?php
/**
 * LexFind - core helpers for the traditional (multi-page) site.
 * Every page includes this first, before includes/header.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

/** Escape for safe HTML output. */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($n) {
    $n = (float)$n;
    return '৳' . number_format($n, 0);
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Protect a page. Redirects to login.php if not logged in,
 * or to the person's own dashboard if logged in as the wrong role.
 * $roles is a single role string, an array of roles, or null (any logged-in user).
 */
function requireLogin($roles = null) {
    $u = currentUser();
    if (!$u) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . basePath() . 'login.php?redirect=' . $back);
        exit;
    }
    if ($roles !== null) {
        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array($u['role'], $roles, true)) {
            header('Location: ' . basePath() . dashboardUrl($u['role']));
            exit;
        }
    }
    return $u;
}

function dashboardUrl(string $role): string {
    switch ($role) {
        case 'client':  return 'client/dashboard.php';
        case 'advisor': return 'advisor/dashboard.php';
        case 'admin':   return 'admin/dashboard.php';
        default:        return 'index.php';
    }
}

/** Absolute-ish base path prefix so links work whether a page is at the root or one folder deep. */
function basePath(): string {
    // pages inside client/, advisor/, admin/, actions/ need to reach the project root
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $depth = substr_count(trim(dirname($script), '/'), '/');
    // crude but reliable: count how many of our known subfolders are in the path
    foreach (['client/', 'advisor/', 'admin/', 'actions/'] as $sub) {
        if (strpos($script, '/' . rtrim($sub, '/') . '/') !== false) return '../';
    }
    return '';
}

/** One-time flash messages stored in the session, shown once then cleared. */
function setFlash(string $type, string $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function getFlash() {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/** A confirmed booking whose date/time has passed displays as "completed" everywhere. */
function isPastDateTime(string $date, string $time): bool {
    $ts = strtotime($date . ' ' . $time);
    if ($ts === false) $ts = strtotime($date);
    return $ts !== false && $ts < time();
}
function effectiveStatus(string $status, string $date, string $time): string {
    if ($status === 'confirmed' && isPastDateTime($date, $time)) return 'completed';
    if ($status === 'pending' && isPastDateTime($date, $time)) return 'expired';
    return $status;
}

function statusBadgeClass(string $status): string {
    $map = ['pending' => 'badge-blue', 'confirmed' => 'badge-gold', 'completed' => 'badge-green', 'cancelled' => 'badge-red', 'rejected' => 'badge-red', 'expired' => 'badge-red', 'approved' => 'badge-green', 'active' => 'badge-green', 'suspended' => 'badge-red', 'paid' => 'badge-green', 'unpaid' => 'badge-red', 'refunded' => 'badge-blue', 'read' => 'badge-green', 'unread' => 'badge-gold'];
    return $map[$status] ?? 'badge-blue';
}
function renderBadge(string $status): string {
    return '<span class="badge ' . statusBadgeClass($status) . '">' . h(ucfirst($status)) . '</span>';
}

/**
 * A booking is created the moment a client submits the form, holding the slot,
 * but it only becomes a real request once payment completes. If a client abandons
 * checkout, auto-cancel that hold after a short grace window so the slot frees up
 * for other clients instead of being stuck forever.
 */
function expireStaleUnpaidBookings(PDO $db, int $advisorId, string $date, string $time): void {
    $stmt = $db->prepare(
        "UPDATE bookings SET status = 'cancelled'
         WHERE advisor_id = ? AND booking_date = ? AND booking_time = ?
           AND status = 'pending' AND payment_status = 'unpaid'
           AND created_at < (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt->execute([$advisorId, $date, $time]);
}
function renderStars(float $rating): string {
    $rating = max(0, min(5, $rating));
    // Round to the nearest half-star (e.g. 4.6 -> 4.5, 4.24 -> 4.0, 4.25 -> 4.5)
    $rounded = round($rating * 2) / 2;
    $full = (int)floor($rounded);
    $half = ($rounded - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    return str_repeat('<i class="fa-solid fa-star"></i>', $full)
        . str_repeat('<i class="fa-solid fa-star-half-stroke"></i>', $half)
        . str_repeat('<i class="fa-regular fa-star"></i>', $empty);
}
function initialsOf(string $first, string $last): string {
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}
/* ---------- CSRF protection ---------- */

/** Returns this session's CSRF token, generating one on first use. */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Echo this inside every <form>. Outputs a hidden field carrying the token. */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

/**
 * Call at the top of every actions/*.php handler, right after requireLogin()
 * and the REQUEST_METHOD check. Kills the request with 403 on mismatch.
 */
function csrfVerify(): void {
    $sent = $_POST['csrf_token'] ?? '';
    $valid = !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
    if (!$valid) {
        http_response_code(403);
        setFlash('error', 'Your session expired or the form was submitted unsafely. Please try again.');
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref !== '' && !headers_sent()) {
            header('Location: ' . $ref);
        } else {
            echo 'Forbidden: invalid CSRF token.';
        }
        exit;
    }
}
/* ---------- Login rate limiting ---------- */

const LOGIN_MAX_ATTEMPTS_PER_EMAIL = 5;
const LOGIN_MAX_ATTEMPTS_PER_IP    = 20;
const LOGIN_LOCKOUT_WINDOW_SECONDS = 900; // 15 minutes

function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Records one failed login attempt, and prunes attempts older than the window. */
function recordFailedLogin(string $email): void {
    $db = getDB();
    $db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL ' . LOGIN_LOCKOUT_WINDOW_SECONDS . ' SECOND)');
    $stmt = $db->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
    $stmt->execute([strtolower($email), clientIp()]);
}

/** Clears this email's attempt history after a successful login. */
function clearFailedLogins(string $email): void {
    $stmt = getDB()->prepare('DELETE FROM login_attempts WHERE email = ?');
    $stmt->execute([strtolower($email)]);
}

/** Returns 0 if login may proceed, or seconds remaining in the lockout if not. */
function loginLockoutSecondsRemaining(string $email): int {
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT COUNT(*) c, MAX(attempted_at) last FROM login_attempts
         WHERE email = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_LOCKOUT_WINDOW_SECONDS . ' SECOND)'
    );
    $stmt->execute([strtolower($email)]);
    $byEmail = $stmt->fetch();

    $stmt = $db->prepare(
        'SELECT COUNT(*) c, MAX(attempted_at) last FROM login_attempts
         WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_LOCKOUT_WINDOW_SECONDS . ' SECOND)'
    );
    $stmt->execute([clientIp()]);
    $byIp = $stmt->fetch();

    $lastHit = null;
    if ($byEmail && (int)$byEmail['c'] >= LOGIN_MAX_ATTEMPTS_PER_EMAIL) $lastHit = $byEmail['last'];
    elseif ($byIp && (int)$byIp['c'] >= LOGIN_MAX_ATTEMPTS_PER_IP) $lastHit = $byIp['last'];

    if (!$lastHit) return 0;
    return max(0, strtotime($lastHit) + LOGIN_LOCKOUT_WINDOW_SECONDS - time());
}
/* ---------- Email verification ---------- */

function generateVerificationToken(): string {
    return bin2hex(random_bytes(32));
}

function appBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // if the currently running script lives inside one of our subfolders,
    // strip that subfolder so we always land on the true PROJECT ROOT —
    // otherwise links built from actions/*.php end up with an extra
    // "/actions" (or "/client", "/advisor", "/admin") baked into them.
    foreach (['/client', '/advisor', '/admin', '/actions'] as $sub) {
        if (substr($dir, -strlen($sub)) === $sub) {
            $dir = substr($dir, 0, -strlen($sub));
            break;
        }
    }
    return $scheme . '://' . $host . $dir;
}

/**
 * Low-level sender: posts one transactional email to the Brevo API over
 * HTTPS using cURL. Returns true on success (HTTP 2xx), false otherwise.
 * This replaces PHPMailer/SMTP everywhere in this file — see config/mail.php.
 */
function sendViaBrevoApi(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody, string $logTag): bool {
    $debugLog = __DIR__ . '/../mail-debug.log';
    $logLine = function (string $msg) use ($debugLog) {
        @file_put_contents($debugLog, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    };

    if ($toEmail === '') {
        $logLine("SKIPPED — empty recipient email. logTag={$logTag}, subject={$subject}");
        error_log('[' . $logTag . '] Skipped send: empty recipient email');
        return false;
    }

    $payload = [
        'sender'      => ['email' => BREVO_SENDER_EMAIL, 'name' => BREVO_SENDER_NAME],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $altBody,
    ];

    $logLine("ATTEMPT — logTag={$logTag}, to={$toEmail}, subject={$subject}");

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $logLine("CURL ERROR — to={$toEmail}: {$curlError}");
        error_log('[' . $logTag . '] Brevo cURL error for ' . $toEmail . ': ' . $curlError);
        return false;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        $logLine("HTTP {$httpCode} — to={$toEmail}: {$response}");
        error_log('[' . $logTag . '] Brevo API send FAILED (HTTP ' . $httpCode . ') for ' . $toEmail . ': ' . $response);
        return false;
    }

    $logLine("SUCCESS — to={$toEmail}, HTTP {$httpCode}");
    error_log('[' . $logTag . '] Sent via Brevo API to ' . $toEmail);
    return true;
}

/** Sends the verification link by real email via the Brevo API. */
function sendVerificationEmail(string $toEmail, string $firstName, string $token): void {
    $link = appBaseUrl() . '/verify-email.php?token=' . urlencode($token);

    $htmlBody = '<p>Hi ' . h($firstName) . ',</p>'
              . '<p>Thanks for signing up with LexFind. Please confirm your email by clicking the button below:</p>'
              . '<p><a href="' . h($link) . '" style="background:#c9a84c;color:#0b0c0f;padding:12px 20px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block">Verify Email</a></p>'
              . '<p>Or paste this link into your browser:<br>' . h($link) . '</p>'
              . '<p>This link expires in 24 hours. If you didn\'t create this account, you can ignore this email.</p>'
              . '<p>— LexFind</p>';
    $altBody = "Hi {$firstName},\n\nPlease verify your email: {$link}\n\nThis link expires in 24 hours.\n\n— LexFind";

    sendViaBrevoApi($toEmail, $firstName, 'Verify your LexFind account', $htmlBody, $altBody, 'LexFind Verify');
}
/* ---------- Password reset ---------- */

function generateResetToken(): string {
    return bin2hex(random_bytes(32));
}

/** Sends the password-reset link by real email via the Brevo API. */
function sendPasswordResetEmail(string $toEmail, string $firstName, string $token): void {
    $link = appBaseUrl() . '/reset-password.php?token=' . urlencode($token);

    $htmlBody = '<p>Hi ' . h($firstName) . ',</p>'
              . '<p>We received a request to reset your LexFind password. Click the button below to choose a new one:</p>'
              . '<p><a href="' . h($link) . '" style="background:#c9a84c;color:#0b0c0f;padding:12px 20px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block">Reset Password</a></p>'
              . '<p>Or paste this link into your browser:<br>' . h($link) . '</p>'
              . '<p>This link expires in 1 hour. If you didn\'t request this, you can safely ignore this email — your password will not change.</p>'
              . '<p>— LexFind</p>';
    $altBody = "Hi {$firstName},\n\nReset your password: {$link}\n\nThis link expires in 1 hour.\n\n— LexFind";

    sendViaBrevoApi($toEmail, $firstName, 'Reset your LexFind password', $htmlBody, $altBody, 'LexFind Reset');
}
/* ---------- General notification emails ---------- */

/** Shared sender used by all notification emails (booking confirmations, etc). */
function sendAppEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody, string $logTag): bool {
    return sendViaBrevoApi($toEmail, $toName, $subject, $htmlBody, $altBody, $logTag);
}

function emailWrap(string $innerHtml): string {
    return '<div style="font-family:sans-serif">' . $innerHtml . '<p style="margin-top:20px">— LexFind</p></div>';
}

/** Notify an advisor that a new booking request came in. */
function notifyNewBooking(string $advisorEmail, string $advisorName, string $clientName, string $date, string $time, string $type): void {
    $link = appBaseUrl() . '/advisor/dashboard.php';
    $html = emailWrap(
        '<p>Hi ' . h($advisorName) . ',</p>'
        . '<p>' . h($clientName) . ' has requested a consultation with you.</p>'
        . '<p><b>Date:</b> ' . h($date) . '<br><b>Time:</b> ' . h($time) . '<br><b>Type:</b> ' . h($type) . '</p>'
        . '<p><a href="' . h($link) . '" style="background:#c9a84c;color:#0b0c0f;padding:12px 20px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block">View Booking</a></p>'
    );
    $alt = "Hi {$advisorName},\n\n{$clientName} requested a consultation on {$date} at {$time} ({$type}).\nView it: {$link}";
    sendAppEmail($advisorEmail, $advisorName, 'New booking request on LexFind', $html, $alt, 'LexFind Notify');
}

/** Notify a client that their booking was accepted or rejected. */
function notifyBookingResponse(string $clientEmail, string $clientName, string $advisorName, string $date, string $time, string $newStatus): void {
    $link = appBaseUrl() . '/client/bookings.php';
    $verb = $newStatus === 'confirmed' ? 'confirmed' : 'declined';
    $html = emailWrap(
        '<p>Hi ' . h($clientName) . ',</p>'
        . '<p>' . h($advisorName) . ' has <b>' . h($verb) . '</b> your consultation request for ' . h($date) . ' at ' . h($time) . '.</p>'
        . '<p><a href="' . h($link) . '" style="background:#c9a84c;color:#0b0c0f;padding:12px 20px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block">View Booking</a></p>'
    );
    $alt = "Hi {$clientName},\n\n{$advisorName} has {$verb} your booking for {$date} at {$time}.\nView it: {$link}";
    sendAppEmail($clientEmail, $clientName, 'Your LexFind booking was ' . $verb, $html, $alt, 'LexFind Notify');
}

/** Notify an advisor that a client cancelled a booking. */
function notifyBookingCancelled(string $advisorEmail, string $advisorName, string $clientName, string $date, string $time): void {
    $link = appBaseUrl() . '/advisor/bookings.php';
    $html = emailWrap(
        '<p>Hi ' . h($advisorName) . ',</p>'
        . '<p>' . h($clientName) . ' has cancelled their consultation scheduled for ' . h($date) . ' at ' . h($time) . '.</p>'
        . '<p><a href="' . h($link) . '" style="background:#c9a84c;color:#0b0c0f;padding:12px 20px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block">View Bookings</a></p>'
    );
    $alt = "Hi {$advisorName},\n\n{$clientName} cancelled their booking for {$date} at {$time}.\nView it: {$link}";
    sendAppEmail($advisorEmail, $advisorName, 'A LexFind booking was cancelled', $html, $alt, 'LexFind Notify');
}

/** Notify an advisor their profile application was approved or rejected. */
function notifyAdvisorApproval(string $advisorEmail, string $advisorName, string $newStatus): void {
    $link = appBaseUrl() . '/login.php';
    $approved = $newStatus === 'approved';
    $subject = $approved ? 'Your LexFind advisor profile was approved' : 'Update on your LexFind advisor application';
    $html = $approved
        ? emailWrap('<p>Hi ' . h($advisorName) . ',</p><p>Great news — your advisor profile has been <b>approved</b> and is now visible to clients on LexFind.</p><p><a href="' . h($link) . '" style="background:#c9a84c;color:#0b0c0f;padding:12px 20px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block">Log In</a></p>')
        : emailWrap('<p>Hi ' . h($advisorName) . ',</p><p>Your advisor application was not approved at this time. If you believe this is a mistake, please contact support.</p>');
    $alt = $approved
        ? "Hi {$advisorName},\n\nYour advisor profile has been approved and is now visible to clients.\nLog in: {$link}"
        : "Hi {$advisorName},\n\nYour advisor application was not approved at this time.";
    sendAppEmail($advisorEmail, $advisorName, $subject, $html, $alt, 'LexFind Notify');
}
/** Renders either the user's uploaded photo or their initials, using the given CSS class for sizing. */
/** Renders either the user's uploaded photo or their initials, using the given CSS class for sizing.
 *  For the large profile-page avatar, clicking the photo opens a full-size lightbox (CSS-only, no JS). */
function avatarImg(?string $avatar, string $firstName, string $lastName, string $class): string {
    if ($avatar) {
        $src = basePath() . 'uploads/avatars/' . $avatar;
        if ($class === 'profile-avatar-lg') {
            $uid = 'av_' . bin2hex(random_bytes(4));
            return '<input type="checkbox" id="' . $uid . '" class="avatar-lightbox-toggle"/>'
                 . '<label for="' . $uid . '" class="avatar-clickable" title="Click to enlarge">'
                 . '<img src="' . h($src) . '" class="' . h($class) . '" alt="Profile picture" style="object-fit:cover"/>'
                 . '</label>'
                 . '<label for="' . $uid . '" class="avatar-lightbox-overlay">'
                 . '<img src="' . h($src) . '" class="avatar-lightbox-img" alt="Profile picture"/>'
                 . '<span class="avatar-lightbox-hint">Tap anywhere to close</span>'
                 . '</label>';
        }
        return '<img src="' . h($src) . '" class="' . h($class) . '" alt="Profile picture" style="object-fit:cover"/>';
    }
    return '<div class="' . h($class) . '">' . h(initialsOf($firstName, $lastName)) . '</div>';
}