<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Log In';

// already logged in? go to their dashboard
if (currentUser()) { header('Location: ' . dashboardUrl(currentUser()['role'])); exit; }

$errors = [];
$oldEmail = '';
$oldRole = 'client';
$unverifiedEmail = null;
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $oldRole = in_array($_POST['role'] ?? '', ['client', 'advisor'], true) ? $_POST['role'] : 'client';

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
            } elseif ($user['role'] !== $oldRole) {
                $errors[] = 'That account is not registered as a ' . $oldRole . '. Try a different role tab.';
            } elseif ($user['status'] === 'suspended') {
                $errors[] = 'This account has been suspended. Please contact support.';
            } elseif ((int)$user['is_verified'] === 0) {
                $errors[] = 'Please verify your email before logging in.';
                $unverifiedEmail = $oldEmail;
            } else {
                clearFailedLogins($oldEmail);
                $advisorId = null; $approvalStatus = null;
                if ($user['role'] === 'advisor') {
                    $s = $db->prepare('SELECT id, approval_status FROM advisor_profiles WHERE user_id = ?');
                    $s->execute([$user['id']]);
                    $ap = $s->fetch();
                    if ($ap) { $advisorId = (int)$ap['id']; $approvalStatus = $ap['approval_status']; }
                }
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int)$user['id'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name'],
                    'email' => $user['email'], 'role' => $user['role'],
                    'advisor_id' => $advisorId, 'approval_status' => $approvalStatus,
                ];
                $target = ($redirect && strpos($redirect, '//') === false && strpos($redirect, 'http') !== 0)
                    ? $redirect : dashboardUrl($user['role']);
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
    <h2>Welcome Back</h2>
    <p class="sub">Log in to your LexFind account</p>

    <?php if ($errors): ?>
      <div style="color:var(--red);font-size:0.82rem;margin-bottom:14px"><?= h($errors[0]) ?></div>
    <?php endif; ?>
    <?php if ($unverifiedEmail): ?>
      <div class="auth-link" style="margin-bottom:14px"><a href="resend-verification.php">Resend verification email</a></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="hidden" name="redirect" value="<?= h($redirect) ?>"/>
      <div class="role-tabs">
        <input type="radio" id="role-client" name="role" value="client" <?= $oldRole === 'client' ? 'checked' : '' ?> style="display:none"/>
        <label for="role-client" class="role-tab"><i class="fa-solid fa-user"></i> Client </label>
        <input type="radio" id="role-advisor" name="role" value="advisor" <?= $oldRole === 'advisor' ? 'checked' : '' ?> style="display:none"/>
        <label for="role-advisor" class="role-tab"><i class="fa-solid fa-scale-balanced"></i> Advisor </label>
      </div>
      <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="you@example.com" value="<?= h($oldEmail) ?>"/></div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••"/>
        <div style="text-align:right;margin-top:6px"><a href="forgot-password.php" style="color:var(--gold);font-size:0.78rem;text-decoration:none">Forgot password?</a></div>
      </div>
      <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px" type="submit">Log In</button>
    </form>
    <div class="auth-link">Don't have an account? <a href="signup.php">Sign Up</a></div>
  </div>
</div>
<?php include __DIR__ . '/includes/dash-footer.php'; ?>
