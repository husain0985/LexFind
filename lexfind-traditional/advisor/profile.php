<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('advisor');
$pageTitle = 'Profile Management';
$role = 'advisor'; $activePage = 'profile';

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$u['id']]);
$user = $stmt->fetch();
$avatarError = null;
$stmt = $db->prepare('SELECT * FROM advisor_profiles WHERE user_id = ?');
$stmt->execute([$u['id']]);
$ap = $stmt->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $barLicense = trim($_POST['bar_license'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $yearsExp = (int)($_POST['years_exp'] ?? 0);
    $fee = (float)($_POST['fee'] ?? 0);
    $languages = trim($_POST['languages'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $location = trim($_POST['location'] ?? '');

    // handle an optional new profile photo — validate before anything else is saved
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['avatar'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $avatarError = 'There was a problem uploading that file.';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $avatarError = 'Profile picture must be under 2MB.';
        } else {
            $info = @getimagesize($f['tmp_name']);
            $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($allowed[$info[2]])) {
                $avatarError = 'Please upload a JPG, PNG, or WEBP image.';
            } else {
                $dir = __DIR__ . '/../uploads/avatars';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$info[2]];
                if (move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) {
                    if (!empty($user['avatar'])) @unlink($dir . '/' . $user['avatar']);
                    $stmt = $db->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                    $stmt->execute([$filename, $u['id']]);
                    $user['avatar'] = $filename;
                } else {
                    $avatarError = 'Could not save the uploaded file.';
                }
            }
        }
    }

    if ($avatarError) {
        $errors[] = $avatarError;
    } elseif ($specialty === '') {
        $errors[] = 'Please select a specialty.';
    } elseif ($yearsExp < 0 || $fee < 0) {
        $errors[] = 'Experience and fee must be positive numbers.';
    } else {
        if ($fullName !== '') {
            $parts = explode(' ', $fullName, 2);
            $stmt = $db->prepare('UPDATE users SET first_name=?, last_name=?, address=? WHERE id=?');
            $stmt->execute([$parts[0], $parts[1] ?? '', $address, $u['id']]);
        }
        $stmt = $db->prepare('UPDATE advisor_profiles SET bar_license=?, specialty=?, years_exp=?, fee=?, languages=?, bio=?, location=? WHERE user_id=?');
        $stmt->execute([$barLicense, $specialty, $yearsExp, $fee, $languages, $bio, $location, $u['id']]);
        setFlash('success', 'Profile updated successfully.');
        header('Location: profile.php');
        exit;
    }
    $user['first_name'] = explode(' ', $fullName, 2)[0] ?? $user['first_name'];
    $ap['bar_license'] = $barLicense; $ap['specialty'] = $specialty; $ap['years_exp'] = $yearsExp;
    $ap['fee'] = $fee; $ap['languages'] = $languages; $ap['bio'] = $bio; $ap['location'] = $location;
}

$badgeMap = ['approved' => ['badge-green', '<i class="fa-solid fa-check"></i> Approved'], 'pending' => ['badge-gold', '<i class="fa-solid fa-hourglass-half"></i> Pending Approval'], 'rejected' => ['badge-red', '<i class="fa-solid fa-xmark"></i> Rejected']];
[$badgeCls, $badgeLabel] = $badgeMap[$ap['approval_status']] ?? ['badge-gold', 'Pending'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Profile Management</h2></div>
<div class="card-box">
  <div class="profile-header" style="margin-bottom:24px">
    <?= avatarImg($user['avatar'] ?? null, $user['first_name'], $user['last_name'], 'profile-avatar-lg') ?>
    <div class="profile-info">
      <h2>Adv. <?= h($user['first_name'] . ' ' . $user['last_name']) ?></h2>
      <p><?= h($ap['specialty']) ?> Law · <?= (int)$ap['years_exp'] ?> years experience</p>
      <span class="badge <?= $badgeCls ?>"><?= $badgeLabel ?></span>
    </div>
  </div>
  <?php if ($errors): ?><div style="color:var(--red);font-size:0.82rem;margin-bottom:12px"><?= h($errors[0]) ?></div><?php endif; ?>
  <form method="post" action="profile.php" enctype="multipart/form-data">
    <div class="form-group"><label>Profile Picture</label><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"/></div>
    <div class="form-row">
      <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?= h($user['first_name'] . ' ' . $user['last_name']) ?>"/></div>
      <div class="form-group"><label>Email Address</label><input type="email" value="<?= h($user['email']) ?>" disabled style="opacity:0.6;cursor:not-allowed"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Bar License No.</label><input type="text" name="bar_license" value="<?= h($ap['bar_license'] ?? '') ?>"/></div>
      <div class="form-group"><label>City / Location</label><input type="text" name="location" value="<?= h($ap['location'] ?? '') ?>" placeholder="e.g. Moulvibazar"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Primary Specialty</label>
        <select name="specialty">
          <?php foreach (['Criminal','Civil','Family','Corporate','Property','Immigration','Labour','Tax'] as $sp): ?>
            <option value="<?= h($sp) ?>" <?= $ap['specialty'] === $sp ? 'selected' : '' ?>><?= h($sp) ?> Law</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Years of Experience</label><input type="number" name="years_exp" min="0" value="<?= (int)$ap['years_exp'] ?>"/></div>
    </div>
    <div class="form-group"><label>Consultation Fee (BDT / hour)</label><input type="number" name="fee" min="0" value="<?= (float)$ap['fee'] ?>"/></div>
    <div class="form-group"><label>Languages</label><input type="text" name="languages" value="<?= h($ap['languages'] ?? '') ?>"/></div>
    <div class="form-group"><label>Office Address</label><input type="text" name="address" value="<?= h($user['address'] ?? '') ?>" placeholder="e.g. House 12, Road 5, Dhanmondi, Dhaka"/></div>
    <div class="form-group"><label>Bio / About</label><textarea rows="4" name="bio"><?= h($ap['bio'] ?? '') ?></textarea></div>
    <button class="btn btn-gold" type="submit">Update Profile</button>
  </form>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
