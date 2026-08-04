<?php
/**
 * Shared header. Include after includes/auth.php.
 * Expects (all optional): $pageTitle, $activeNav ('home'|'about'|'faq'|'contact')
 */
$base = basePath();
$u = currentUser();
$userAvatar = null;
if ($u) {
    $stmt = getDB()->prepare('SELECT avatar FROM users WHERE id = ?');
    $stmt->execute([$u['id']]);
    $userAvatar = $stmt->fetchColumn();
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= isset($pageTitle) ? h($pageTitle) . ' – LexFind' : 'LexFind – Legal Advisor Finder' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?= $base ?>assets/css/style.css"/>
</head>
<body>

<nav>
  <a href="<?= $base ?>index.php" class="nav-logo" style="text-decoration:none">Lex<span>Find</span></a>
  <input type="checkbox" id="nav-toggle" class="nav-toggle-checkbox"/>
  <div class="nav-links">
    <a href="<?= $base ?>index.php" class="<?= ($activeNav ?? '') === 'home' ? 'active-link' : '' ?>">Home</a>
    <a href="<?= $base ?>about.php" class="<?= ($activeNav ?? '') === 'about' ? 'active-link' : '' ?>">About</a>
    <a href="<?= $base ?>faq.php" class="<?= ($activeNav ?? '') === 'faq' ? 'active-link' : '' ?>">FAQ</a>
    <a href="<?= $base ?>contact.php" class="<?= ($activeNav ?? '') === 'contact' ? 'active-link' : '' ?>">Contact</a>
  </div>
  <label for="nav-toggle" class="nav-toggle-btn" aria-label="Menu"><i class="fa-solid fa-bars"></i></label>
  <?php if (!$u): ?>
  <div class="nav-right">
    <a class="btn btn-ghost" href="<?= $base ?>login.php">Log In</a>
    <a class="btn btn-gold" href="<?= $base ?>signup.php">Sign Up</a>
  </div>
  <?php else:
    $profileUrl = null;
    if ($u['role'] === 'client') $profileUrl = $base . 'client/profile.php';
    elseif ($u['role'] === 'advisor') $profileUrl = $base . 'advisor/profile.php';
    elseif ($u['role'] === 'admin') $profileUrl = $base . 'admin/dashboard.php';
  ?>
  <div class="nav-right" style="display:flex;align-items:center;gap:16px">
    <?php if ($profileUrl): ?>
    <a href="<?= $profileUrl ?>" style="text-decoration:none" title="<?= $u['role'] === 'admin' ? 'Go to Admin Panel' : 'View Profile' ?>">
      <?= avatarImg($userAvatar, $u['first_name'], $u['last_name'], 'user-avatar') ?>
    </a>
    <?php else: ?>
    <?= avatarImg($userAvatar, $u['first_name'], $u['last_name'], 'user-avatar') ?>
    <?php endif; ?>
    <a href="<?= $base . dashboardUrl($u['role']) ?>" class="nav-icon-link" style="color:var(--text-muted);text-decoration:none;font-size:0.84rem;white-space:nowrap" title="Dashboard"><i class="fa-solid fa-bolt"></i><span class="nav-link-label"> Dashboard</span></a>
    <a href="<?= $base ?>logout.php" class="nav-icon-link" style="color:var(--red);text-decoration:none;font-size:0.84rem;white-space:nowrap" title="Log Out"><i class="fa-solid fa-right-from-bracket"></i><span class="nav-link-label"> Log Out</span></a>
  </div>
  <?php endif; ?>
</nav>

<?php $flash = getFlash(); if ($flash): ?>
<input type="checkbox" id="flashDismiss" class="flash-dismiss-toggle"/>
<div class="flash-toast" style="position:fixed;top:74px;left:50%;transform:translateX(-50%);z-index:150;max-width:560px;width:90%;padding:14px 44px 14px 20px;border-radius:var(--radius-sm);font-size:0.86rem;
  background:<?= $flash['type'] === 'error' ? 'rgba(224,85,85,0.15)' : 'rgba(76,175,130,0.15)' ?>;
  color:<?= $flash['type'] === 'error' ? 'var(--red)' : 'var(--green)' ?>;
  border:1px solid <?= $flash['type'] === 'error' ? 'var(--red)' : 'var(--green)' ?>;">
  <?= h($flash['message']) ?>
  <label for="flashDismiss" class="flash-close" title="Dismiss">&times;</label>
</div>
<?php endif; ?>
