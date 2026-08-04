<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Home';
$activeNav = 'home';

$db = getDB();

// live stats
$advisorCount = (int)$db->query(
    'SELECT COUNT(*) c FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "approved" AND u.status = "active"'
)->fetch()['c'];
$caseCount = (int)$db->query('SELECT COUNT(*) c FROM bookings WHERE status IN ("confirmed","completed")')->fetch()['c'];
$avgRatingRow = $db->query(
    'SELECT AVG(ap.rating_avg) a FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "approved" AND u.status = "active" AND ap.rating_count > 0'
)->fetch();
$avgRating = $avgRatingRow['a'] ? round((float)$avgRatingRow['a'], 1) : 0;

// practice area counts
$areaCounts = [];
$stmt = $db->query(
    'SELECT ap.specialty, COUNT(*) c FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "approved" AND u.status = "active" GROUP BY ap.specialty'
);
foreach ($stmt->fetchAll() as $row) $areaCounts[$row['specialty']] = (int)$row['c'];

// featured advisors (top 3 rated)
$stmt = $db->query(
    'SELECT ap.id, u.first_name, u.last_name, u.avatar, ap.specialty, ap.years_exp, ap.fee, ap.rating_avg, ap.rating_count, ap.tags, ap.location
     FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "approved" AND u.status = "active"
     ORDER BY ap.rating_avg DESC LIMIT 3'
);
$featured = $stmt->fetchAll();

$areas = [
    'Criminal' => 'fa-scale-balanced', 'Civil' => 'fa-landmark', 'Family' => 'fa-people-roof', 'Corporate' => 'fa-building',
    'Property' => 'fa-house', 'Immigration' => 'fa-plane', 'Labour' => 'fa-helmet-safety', 'Tax' => 'fa-chart-column',
];

include __DIR__ . '/includes/header.php';
?>

<div class="hero">
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-badge"><i class="fa-solid fa-scale-balanced"></i>Bangladesh's Premier Legal Network</div>
    <h1 class="fade-up">Find Your Perfect<br/>Legal Advisor</h1>
    <p class="fade-up-2">Connect with verified legal experts across Criminal, Civil, Family, Corporate law and more. Transparent fees, real reviews, instant booking.</p>
    <form class="hero-search fade-up-3" action="search.php" method="get">
      <input type="text" name="name" placeholder="Search by name, specialty, location…"/>
      <button type="submit">Search</button>
    </form>
    <div class="hero-cta fade-up-3">
      <a class="btn btn-gold" href="signup.php">Get Started Free</a>
      <a class="btn btn-ghost" href="search.php">Browse Advisors</a>
    </div>
  </div>
</div>

<div class="stats-row">
  <div class="stat"><div class="num"><?= $advisorCount ?>+</div><div class="lbl">Verified Advisors</div></div>
  <div class="stat"><div class="num"><?= $caseCount ?>+</div><div class="lbl">Cases Handled</div></div>
 <div class="stat"><div class="num"><?= $avgRating ?> <i class="fa-solid fa-star" style="font-size:0.6em"></i></div><div class="lbl">Average Rating</div></div>
  <div class="stat"><div class="num">24/7</div><div class="lbl">Support Available</div></div>
</div>

<div class="section">
  <div class="section-title">Practice Areas</div>
  <div class="divider"></div>
  <p class="section-sub">Find specialists across all major areas of law</p>
  <div class="area-grid">
    <?php foreach ($areas as $name => $icon): ?>
    <a href="search.php?specialty=<?= urlencode($name) ?>" class="area-card" style="text-decoration:none;color:inherit;display:block">
      <div class="area-icon"><i class="fa-solid <?= $icon ?>"></i></div>
      <h4><?= h($name) ?><?= $name === 'Tax' ? ' &amp; Finance' : '' ?></h4>
      <p><?= (int)($areaCounts[$name] ?? 0) ?> advisors</p>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="section" style="padding-top:0">
  <div class="section-title">Featured Advisors</div>
  <div class="divider"></div>
  <p class="section-sub">Top-rated legal professionals available now</p>
  <div class="grid-3">
    <?php if (!$featured): ?>
      <p style="color:var(--text-muted)">No advisors yet.</p>
    <?php endif; ?>
    <?php foreach ($featured as $a): ?>
      <?php include __DIR__ . '/includes/advisor-card.php'; ?>
    <?php endforeach; ?>
  </div>
  <div style="text-align:center;margin-top:32px">
    <a class="btn btn-outline" href="search.php">View All Advisors <i class="fa-solid fa-arrow-right"></i></a>
  </div>
</div>

<div class="section" style="padding-top:0; background:var(--surface);max-width:100%;padding-left:40px;padding-right:40px;border-top:1px solid var(--border)">
  <div style="max-width:1200px;margin:0 auto">
    <div class="section-title">How It Works</div>
    <div class="divider"></div>
    <div class="steps">
      <div class="step"><div class="step-num">01</div><h3>Create Account</h3><p>Sign up as a client or legal advisor in under 2 minutes.</p></div>
      <div class="step"><div class="step-num">02</div><h3>Find Your Expert</h3><p>Filter by specialty, location, availability, and budget.</p></div>
      <div class="step"><div class="step-num">03</div><h3>Book a Session</h3><p>Choose a time slot and preferred meeting format.</p></div>
      <div class="step"><div class="step-num">04</div><h3>Get Legal Help</h3><p>Connect with your advisor and resolve your legal matters.</p></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
