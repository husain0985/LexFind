<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('client');
$pageTitle = 'My Profile';
$role = 'client'; $activePage = 'profile';

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$u['id']]);
$profile = $stmt->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    } else {
        $stmt = $db->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, address=? WHERE id=?');
        $stmt->execute([$firstName, $lastName, $phone, $address, $u['id']]);
        $_SESSION['user']['first_name'] = $firstName;
        $_SESSION['user']['last_name'] = $lastName;
        setFlash('success', 'Profile updated successfully.');
        header('Location: profile.php');
        exit;
    }
    $profile['first_name'] = $firstName; $profile['last_name'] = $lastName;
    $profile['phone'] = $phone; $profile['address'] = $address;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>My Profile</h2><p>Manage your personal information</p></div>
<div class="card-box">
  <div class="profile-header" style="margin-bottom:0">
    <div class="profile-avatar-lg"><?= h(initialsOf($profile['first_name'], $profile['last_name'])) ?></div>
    <div class="profile-info">
      <h2><?= h($profile['first_name'] . ' ' . $profile['last_name']) ?></h2>
      <p>Client · Member since <?= h(date('M Y', strtotime($profile['created_at']))) ?></p>
      <p><?= h($profile['email']) ?><?= $profile['phone'] ? ' · ' . h($profile['phone']) : '' ?></p>
    </div>
  </div>
</div>
<div class="card-box">
  <h3>Edit Profile</h3>
  <?php if ($errors): ?><div style="color:var(--red);font-size:0.82rem;margin-bottom:12px"><?= h($errors[0]) ?></div><?php endif; ?>
  <form method="post" action="profile.php">
    <div class="form-row">
      <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?= h($profile['first_name']) ?>"/></div>
      <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= h($profile['last_name']) ?>"/></div>
    </div>
    <div class="form-group"><label>Email</label><input type="email" value="<?= h($profile['email']) ?>" disabled/></div>
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= h($profile['phone'] ?? '') ?>"/></div>
    <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= h($profile['address'] ?? '') ?>"/></div>
    <button class="btn btn-gold" type="submit">Save Changes</button>
  </form>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
