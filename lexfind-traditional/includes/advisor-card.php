<?php
/**
 * Reusable advisor card. Expects $a with keys:
 * id, first_name, last_name, specialty, years_exp, fee, rating_avg, rating_count, tags, location
 * Optional $hideBookBtn = true to omit the Book button (e.g. inside an advisor's own dashboard).
 */
$b = basePath();
$name = 'Adv. ' . $a['first_name'] . ' ' . $a['last_name'];
$initials = initialsOf($a['first_name'], $a['last_name']);
$tags = !empty($a['tags']) ? explode(',', $a['tags']) : [];
?>
<div class="advisor-card">
  <a href="<?= $b ?>advisor.php?id=<?= (int)$a['id'] ?>" style="text-decoration:none;color:inherit;display:block">
    <div class="card-top">
      <?= avatarImg($a['avatar'] ?? null, $a['first_name'], $a['last_name'], 'advisor-avatar') ?>
      <div class="card-info">
        <h3><?= h($name) ?></h3>
        <div class="specialty"><?= h($a['specialty']) ?> Law</div>
        <div style="font-size:0.78rem;color:var(--text-dim);margin-top:2px"><?= (int)$a['years_exp'] ?> yrs · <?= h($a['location']) ?></div>
      </div>
    </div>
    <div class="card-tags">
      <?php foreach (array_slice($tags, 0, 3) as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?>
    </div>
    <div class="card-footer">
      <div class="fee"><?= money($a['fee']) ?><span>/hr</span></div>
      <div>
        <div class="stars"><?= renderStars((float)$a['rating_avg']) ?></div>
        <div style="font-size:0.74rem;color:var(--text-muted);text-align:right"><?= (float)$a['rating_avg'] ?> (<?= (int)$a['rating_count'] ?>)</div>
      </div>
    </div>
  </a>
  <?php if (empty($hideBookBtn)): ?>
  <a class="btn btn-outline btn-sm" style="width:100%;justify-content:center;margin-top:14px" href="<?= $b ?>advisor.php?id=<?= (int)$a['id'] ?>#book">Book Appointment</a>
  <?php endif; ?>
</div>
