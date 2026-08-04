<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Search Advisors';

$name = trim($_GET['name'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');
$location = trim($_GET['location'] ?? '');
$sort = trim($_GET['sort'] ?? 'rating');

$db = getDB();
$where = ['ap.approval_status = "approved"', 'u.status = "active"'];
$params = [];

// generic terms for "advisor" itself shouldn't narrow anything down —
// they should surface every advisor, since that's what a person means by them
$genericTerms = ['adv', 'adv.', 'advisor', 'advisors', 'advocate', 'advocates', 'lawyer', 'lawyers', 'attorney', 'attorneys'];

// strip a leading "Adv.", "Advocate", "Attorney", etc. off searches like "Adv. Maya Chowdhury"
// so the rest of the query can match against the actual name
$name = preg_replace('/^(adv\.?|advocate|advisor|advocates|advisors|lawyer|lawyers|attorney|attorneys)\s+/i', '', $name);
$name = trim($name);

if ($name !== '' && !in_array(strtolower($name), $genericTerms, true)) {
    $where[] = '(CONCAT(u.first_name," ",u.last_name) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR ap.specialty LIKE ? OR ap.tags LIKE ?)';
    $like = '%' . $name . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($specialty !== '') {
    $where[] = 'ap.specialty = ?';
    $params[] = $specialty;
}
if ($location !== '') {
    $where[] = 'ap.location LIKE ?';
    $params[] = '%' . $location . '%';
}

$orderBy = 'ap.rating_avg DESC';
if ($sort === 'fee') $orderBy = 'ap.fee ASC';
if ($sort === 'reviews') $orderBy = 'ap.rating_count DESC';

$stmt = $db->prepare(
    'SELECT ap.id, u.first_name, u.last_name, u.avatar, ap.specialty, ap.years_exp, ap.fee, ap.rating_avg, ap.rating_count, ap.tags, ap.location
     FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy
);
$stmt->execute($params);
$results = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="section">
  <div class="section-title">Find a Legal Advisor</div>
  <div class="divider"></div>

  <form class="search-bar-full" method="get" action="search.php">
    <input type="text" name="name" placeholder="Name or keyword…" value="<?= h($name) ?>"/>
    <select name="specialty">
      <option value="">All Specialties</option>
      <?php foreach (['Criminal','Civil','Family','Corporate','Property','Immigration','Labour','Tax'] as $sp): ?>
        <option value="<?= h($sp) ?>" <?= $specialty === $sp ? 'selected' : '' ?>><?= h($sp) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="location" class="input-location" placeholder="City / Location…" value="<?= h($location) ?>"/>
    <select name="sort">
      <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Sort: Top Rated</option>
      <option value="fee" <?= $sort === 'fee' ? 'selected' : '' ?>>Sort: Lowest Fee</option>
      <option value="reviews" <?= $sort === 'reviews' ? 'selected' : '' ?>>Sort: Most Reviews</option>
    </select>
    <button class="btn btn-gold" type="submit">Search</button>
  </form>

  <div class="grid-3">
    <?php if (!$results): ?>
      <p style="color:var(--text-muted);padding:20px">No advisors found. Try a different search.</p>
    <?php endif; ?>
    <?php foreach ($results as $a): ?>
      <?php include __DIR__ . '/includes/advisor-card.php'; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
