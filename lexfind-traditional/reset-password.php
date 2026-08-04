<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Reset Password';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$done = false;
$validToken = false;
$userId = null;
$userEmail = null;

if ($token !== '') {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, first_name, email, reset_expires FROM users WHERE reset_token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row && strtotime($row['reset_expires']) >= time()) {
        $validToken = true;
        $userId = (int)$row['id'];
        $userEmail = $row['email'];
    }
}

if ($validToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long.';
    } elseif (strlen($password) > 128) {
        $errors[] = 'Password is too long (128 characters max).';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
        $stmt->execute([$hash, $userId]);
        clearFailedLogins($userEmail);
        $done = true;
    }
}

include __DIR__ . '/includes/header.php';
?>
<div style="min-height:calc(100vh - 64px);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="auth-box fade-up">
    <?php if (!$validToken && !$done): ?>
      <h2>Invalid or Expired Link</h2>
      <p class="sub">This password reset link is invalid or has expired.</p>
      <a class="btn btn-gold" style="margin-top:8px;display:inline-flex" href="forgot-password.php">Request a New Link</a>
    <?php elseif ($done): ?>
      <h2>Password Updated</h2>
      <p class="sub">Your password has been changed. You can now log in with your new password.</p>
      <a class="btn btn-gold" style="margin-top:8px;display:inline-flex" href="login.php">Go to Login</a>
    <?php else: ?>
      <h2>Choose a New Password</h2>
      <p class="sub">At least 12 characters. Longer is better than "clever."</p>
      <?php if ($errors): ?>
        <div style="color:var(--red);font-size:0.82rem;margin-bottom:14px"><?= h($errors[0]) ?></div>
      <?php endif; ?>
      <form method="post" action="reset-password.php">
        <input type="hidden" name="token" value="<?= h($token) ?>"/>
        <div class="form-group"><label>New Password</label><input type="password" name="password" placeholder="••••••••" minlength="12" required/></div>
        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="••••••••" minlength="12" required/></div>
        <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px" type="submit">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/dash-footer.php'; ?>