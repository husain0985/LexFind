<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Resend Verification';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if ($email !== '') {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, first_name FROM users WHERE email = ? AND is_verified = 0');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = generateVerificationToken();
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $db->prepare('UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?');
            $stmt->execute([$token, $expires, $user['id']]);
            sendVerificationEmail($email, $user['first_name'], $token);
        }
    }
    $sent = true;
}

include __DIR__ . '/includes/header.php';
?>
<div style="min-height:calc(100vh - 64px);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="auth-box fade-up">
    <h2>Resend Verification Email</h2>
    <?php if ($sent): ?>
      <p class="sub">If an unverified account exists for that email, we've sent a new confirmation link.</p>
      <div class="auth-link"><a href="login.php">Back to Login</a></div>
    <?php else: ?>
      <p class="sub">Enter your email and we'll send a new verification link.</p>
      <form method="post" action="resend-verification.php">
        <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="you@example.com" required/></div>
        <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px" type="submit">Send Link</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/dash-footer.php'; ?>