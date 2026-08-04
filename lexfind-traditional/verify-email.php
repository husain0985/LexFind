<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Verify Email';

$token = trim($_GET['token'] ?? '');
$status = 'invalid';

if ($token !== '') {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, verification_expires FROM users WHERE verification_token = ? AND is_verified = 0');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user && strtotime($user['verification_expires']) >= time()) {
        $stmt = $db->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?');
        $stmt->execute([$user['id']]);
        $status = 'success';
    } elseif ($user) {
        $status = 'expired';
    }
}

include __DIR__ . '/includes/header.php';
?>
<div style="min-height:calc(100vh - 64px);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="auth-box fade-up" style="text-align:center">
    <?php if ($status === 'success'): ?>
      <h2>Email Verified</h2>
      <p class="sub">Your email has been confirmed. You can now log in.</p>
      <a class="btn btn-gold" style="margin-top:16px;display:inline-flex" href="login.php">Go to Login</a>
    <?php elseif ($status === 'expired'): ?>
      <h2>Link Expired</h2>
      <p class="sub">This verification link has expired. Request a new one below.</p>
      <a class="btn btn-gold" style="margin-top:16px;display:inline-flex" href="resend-verification.php">Resend Verification Email</a>
    <?php else: ?>
      <h2>Invalid Link</h2>
      <p class="sub">This verification link is invalid or has already been used.</p>
      <a class="btn btn-gold" style="margin-top:16px;display:inline-flex" href="login.php">Back to Login</a>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/dash-footer.php'; ?>