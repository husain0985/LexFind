<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Sign Up';

if (currentUser()) { header('Location: ' . dashboardUrl(currentUser()['role'])); exit; }

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'role' => 'client', 'specialty' => 'Criminal', 'bar_license' => ''];
$barIdCardFilename = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['first_name'] = trim($_POST['first_name'] ?? '');
    $old['last_name'] = trim($_POST['last_name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['role'] = in_array($_POST['role'] ?? '', ['client', 'advisor'], true) ? $_POST['role'] : 'client';
    $old['specialty'] = trim($_POST['specialty'] ?? 'Criminal');
    $old['bar_license'] = trim($_POST['bar_license'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $agreed = isset($_POST['terms']);

    // handle the Bar Council ID card upload for advisor sign-ups — validate before anything is saved
    if ($old['role'] === 'advisor' && !empty($_FILES['bar_id_card']['name']) && $_FILES['bar_id_card']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['bar_id_card'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading your Bar Council ID card.';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Bar Council ID card must be under 5MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $f['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            if (!isset($allowed[$mime])) {
                $errors[] = 'Please upload your ID card as a JPG, PNG, WEBP, or PDF file.';
            } else {
                $dir = __DIR__ . '/uploads/bar_ids';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $barIdCardFilename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $barIdCardFilename)) {
                    $errors[] = 'Could not save the uploaded ID card. Please try again.';
                    $barIdCardFilename = null;
                }
            }
        }
    }

    if ($errors) {
        // an upload error was already recorded above — fall through to display it
    } elseif ($old['first_name'] === '' || $old['last_name'] === '' || $old['email'] === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long.';
    } elseif (strlen($password) > 128) {
        $errors[] = 'Password is too long (128 characters max).';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } elseif ($old['role'] === 'advisor' && $old['specialty'] === '') {
        $errors[] = 'Please select your primary specialty.';
    } elseif ($old['role'] === 'advisor' && $old['bar_license'] === '') {
        $errors[] = 'Please enter your Bar Council enrollment / license number so we can verify you.';
    } elseif ($old['role'] === 'advisor' && $barIdCardFilename === null) {
        $errors[] = 'Please upload a photo or scan of your Bar Council ID card so we can verify you.';
    } elseif (!$agreed) {
        $errors[] = 'Please agree to the Terms of Service to continue.';
    }

    if (!$errors) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([strtolower($old['email'])]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
            if ($barIdCardFilename) { @unlink(__DIR__ . '/uploads/bar_ids/' . $barIdCardFilename); }
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->beginTransaction();
            try {
                $verifyToken = generateVerificationToken();
                $verifyExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt = $db->prepare('INSERT INTO users (first_name,last_name,email,phone,password_hash,role,status,is_verified,verification_token,verification_expires) VALUES (?,?,?,?,?,?,"active",0,?,?)');
                $stmt->execute([$old['first_name'], $old['last_name'], strtolower($old['email']), $old['phone'], $hash, $old['role'], $verifyToken, $verifyExpires]);
                $userId = (int)$db->lastInsertId();

                if ($old['role'] === 'advisor') {
                    $stmt = $db->prepare('INSERT INTO advisor_profiles (user_id, specialty, bar_license, bar_id_card, approval_status, bio, fee) VALUES (?,?,?,?,"pending","",0)');
                    $stmt->execute([$userId, $old['specialty'], $old['bar_license'], $barIdCardFilename]);
                    $advisorId = (int)$db->lastInsertId();
                    $stmt = $db->prepare('INSERT INTO advisor_availability (advisor_id, day_of_week, is_active) VALUES (?,?,0)');
                    for ($d = 0; $d < 7; $d++) $stmt->execute([$advisorId, $d]);
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Registration failed. Please try again.';
                if ($barIdCardFilename) { @unlink(__DIR__ . '/uploads/bar_ids/' . $barIdCardFilename); }
            }

            if (!$errors) {
                sendVerificationEmail(strtolower($old['email']), $old['first_name'], $verifyToken);
                setFlash('success', 'Account created! We sent a confirmation link to ' . h($old['email']) . '. Please verify your email before logging in.');
                header('Location: login.php');
                exit;
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div style="min-height:calc(100vh - 64px);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="auth-box fade-up" style="max-width:560px">
    <h2>Create Account</h2>
    <p class="sub">Join LexFind to connect with legal experts</p>

    <?php if ($errors): ?>
      <div style="color:var(--red);font-size:0.82rem;margin-bottom:14px"><?= h($errors[0]) ?></div>
    <?php endif; ?>

    <form method="post" action="signup.php" enctype="multipart/form-data">
      <div class="role-tabs">
        <input type="radio" id="signup-role-client" name="role" value="client" <?= $old['role'] === 'client' ? 'checked' : '' ?> style="display:none"/>
        <label for="signup-role-client" class="role-tab"><i class="fa-solid fa-user"></i> Client </label>
        <input type="radio" id="signup-role-advisor" name="role" value="advisor" <?= $old['role'] === 'advisor' ? 'checked' : '' ?> style="display:none"/>
        <label for="signup-role-advisor" class="role-tab"><i class="fa-solid fa-scale-balanced"></i> Advisor </label>
      </div>

      <div class="form-row">
        <div class="form-group"><label>First Name</label><input type="text" name="first_name" placeholder="Muzammil" value="<?= h($old['first_name']) ?>"/></div>
        <div class="form-group"><label>Last Name</label><input type="text" name="last_name" placeholder="Husain" value="<?= h($old['last_name']) ?>"/></div>
      </div>
      <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="you@example.com" value="<?= h($old['email']) ?>"/></div>
      <div class="form-group"><label>Phone Number</label><input type="tel" name="phone" placeholder="+880 1700 000000" value="<?= h($old['phone']) ?>"/></div>

      <div id="signup-advisor-fields" style="display:<?= $old['role'] === 'advisor' ? 'block' : 'none' ?>">
        <div class="form-group" id="signup-specialty-group">
          <label>Primary Specialty</label>
          <select name="specialty">
            <?php foreach (['Criminal','Civil','Family','Corporate','Property','Immigration','Labour','Tax'] as $sp): ?>
              <option value="<?= h($sp) ?>" <?= $old['specialty'] === $sp ? 'selected' : '' ?>><?= h($sp) ?> Law</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="signup-license-group">
          <label>Bar Council Enrollment / License No.</label>
          <input type="text" name="bar_license" placeholder="e.g. BAR-2018-0122" value="<?= h($old['bar_license']) ?>"/>
        </div>
        <div class="form-group" id="signup-idcard-group">
          <label>Bar Council ID Card</label>
          <input type="file" name="bar_id_card" accept=".jpg,.jpeg,.png,.webp,.pdf"/>
          <small style="color:var(--text-muted)">Upload a clear photo or scan (JPG, PNG, WEBP, or PDF, max 5MB) so we can verify your enrollment.</small>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="••••••••" minlength="12"/><small style="color:var(--text-muted)">At least 12 characters.</small></div>
        <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" placeholder="••••••••"/></div>
      </div>
      <div style="margin-bottom:20px">
        <label style="display:flex;gap:8px;align-items:flex-start;font-size:0.82rem;color:var(--text-muted);cursor:pointer;line-height:1.5">
          <input type="checkbox" name="terms" style="width:auto;margin-top:2px;flex-shrink:0"/>
          <span>I agree to LexFind's <a href="<?= $base ?>terms.php" target="_blank" style="color:var(--gold);text-decoration:none">Terms of Service</a> and <a href="<?= $base ?>privacy-policy.php" target="_blank" style="color:var(--gold);text-decoration:none">Privacy Policy</a></span>
        </label>
      </div>
      <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px" type="submit">Create Account</button>
    </form>
    <div class="auth-link">Already have an account? <a href="login.php">Log In</a></div>
  </div>
</div>
<script>
  (function () {
    var advisorFields = document.getElementById('signup-advisor-fields');
    var clientTab = document.getElementById('signup-role-client');
    var advisorTab = document.getElementById('signup-role-advisor');
    function sync() {
      advisorFields.style.display = advisorTab.checked ? 'block' : 'none';
    }
    clientTab.addEventListener('change', sync);
    advisorTab.addEventListener('change', sync);
    sync();
  })();
</script>
<?php include __DIR__ . '/includes/dash-footer.php'; ?>