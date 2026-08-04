<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Admin Log In';

// already logged in? go to the dashboard for whatever role they are
if (currentUser()) { header('Location: ' . dashboardUrl(currentUser()['role'])); exit; }

$errors = [];
$oldEmail = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($oldEmail === '' || $password === '') {
        $errors[] = 'Please enter your email and password.';
    } else {
        $wait = loginLockoutSecondsRemaining($oldEmail);
        if ($wait > 0) {
            $errors[] = 'Too many failed attempts. Please try again in ' . ceil($wait / 60) . ' minute(s).';
        } else {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([strtolower($oldEmail)]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                recordFailedLogin($oldEmail);
                $errors[] = 'Incorrect email or password.';
            } elseif ($user['role'] !== 'admin') {
                recordFailedLogin($oldEmail);
                $errors[] = 'Incorrect email or password.';
            } elseif ($user['status'] === 'suspended') {
                $errors[] = 'This account has been suspended.';
            } elseif ((int)$user['is_verified'] === 0) {
                $errors[] = 'Please verify your email before logging in.';
            } else {
                clearFailedLogins($oldEmail);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int)$user['id'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name'],
                    'email' => $user['email'], 'role' => $user['role'],
                    'advisor_id' => null, 'approval_status' => null,
                ];
                $target = ($redirect && strpos($redirect, '//') === false && strpos($redirect, 'http') !== 0)
                    ? $redirect : dashboardUrl('admin');
                header('Location: ' . $target);
                exit;
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div style="min-height:calc(100vh - 64px);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="auth-box fade-up">
    <h2>Admin Log In</h2>
    <p class="sub">Restricted access</p>

    <?php if ($errors): ?>
      <div style="color:var(--red);font-size:0.82rem;margin-bottom:14px"><?= h($errors[0]) ?></div>
    <?php endif; ?>

    <form method="post" action="admin-login.php">
      <input type="hidden" name="redirect" value="<?= h($redirect) ?>"/>
      <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="you@example.com" value="<?= h($oldEmail) ?>"/></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="••••••••"/></div>
      <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px" type="submit">Log In</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/dash-footer.php'; ?>