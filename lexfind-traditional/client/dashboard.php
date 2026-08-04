<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('client');
$pageTitle = 'Dashboard';
$role = 'client'; $activePage = 'overview';

$db = getDB();

// widget stats
$stmt = $db->prepare('SELECT * FROM bookings WHERE client_id = ?');
$stmt->execute([$u['id']]);
$allBookings = $stmt->fetchAll();

$upcoming = 0; $active = 0; $advisorIds = [];
foreach ($allBookings as $r) {
    $status = effectiveStatus($r['status'], $r['booking_date'], $r['booking_time']);
    if (in_array($r['status'], ['pending', 'confirmed'], true)) $advisorIds[$r['advisor_id']] = true;
    if ($status === 'confirmed' && !isPastDateTime($r['booking_date'], $r['booking_time'])) $upcoming++;
    if ($status === 'confirmed') $active++;
}
$stmt = $db->prepare('SELECT COUNT(*) c, AVG(rating) a FROM reviews WHERE client_id = ?');
$stmt->execute([$u['id']]);
$rev = $stmt->fetch();

// upcoming appointments (confirmed, future) with advisor info
// upcoming appointments (confirmed, future) with advisor info — used by the widget stat above
$stmt = $db->prepare(
    'SELECT b.*, au.first_name af, au.last_name al, ap.specialty
     FROM bookings b JOIN advisor_profiles ap ON ap.id = b.advisor_id JOIN users au ON au.id = ap.user_id
     WHERE b.client_id = ? AND b.status = "confirmed" ORDER BY b.booking_date ASC LIMIT 5'
);
$stmt->execute([$u['id']]);
$upcomingRows = array_filter($stmt->fetchAll(), fn($b) => !isPastDateTime($b['booking_date'], $b['booking_time']));

// all pending + confirmed appointments (what actually shows in the table below)
$stmt = $db->prepare(
    'SELECT b.*, au.first_name af, au.last_name al, ap.specialty
     FROM bookings b JOIN advisor_profiles ap ON ap.id = b.advisor_id JOIN users au ON au.id = ap.user_id
     WHERE b.client_id = ? AND b.status IN ("pending","confirmed") ORDER BY b.booking_date ASC LIMIT 5'
);
$stmt->execute([$u['id']]);
$appointmentRows = $stmt->fetchAll();

// recommended advisors (most reviewed, approved & active)
$stmt = $db->query(
    'SELECT ap.id, u.first_name, u.last_name, u.avatar, ap.specialty, ap.years_exp, ap.fee, ap.rating_avg, ap.rating_count, ap.tags, ap.location
     FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "approved" AND u.status = "active" ORDER BY ap.rating_count DESC LIMIT 3'
);
$recommended = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Welcome back, <?= h($u['first_name']) ?> <i class="fa-solid fa-hand"></i></h2><p>Here's what's happening with your account</p></div>
<div class="widget-row">
  <div class="widget"><div class="w-label">Upcoming Bookings</div><div class="w-val"><?= count($upcomingRows) ?></div><div class="w-sub">Confirmed & future</div></div>
  <div class="widget"><div class="w-label">Advisors Consulted</div><div class="w-val"><?= count($advisorIds) ?></div><div class="w-sub">All time</div></div>
  <div class="widget"><div class="w-label">Active Cases</div><div class="w-val"><?= $active ?></div><div class="w-sub">In progress</div></div>
  <div class="widget"><div class="w-label">Reviews Given</div><div class="w-val"><?= (int)$rev['c'] ?></div><div class="w-sub"><?= $rev['c'] ? '<i class="fa-solid fa-star"></i> ' . round((float)$rev['a'], 1) . ' avg' : 'No reviews yet' ?></div></div>
</div>

<div class="card-box">
  <h3>Upcoming Appointments</h3>
  <table>
    <thead><tr><th>Advisor</th><th>Specialty</th><th>Date & Time</th><th>Type</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$appointmentRows): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No upcoming appointments</td></tr>
      <?php endif; ?>
      <?php foreach ($appointmentRows as $b): ?>
      <tr>
        <td><strong>Adv. <?= h($b['af'] . ' ' . $b['al']) ?></strong></td>
        <td><?= h($b['specialty']) ?></td>
        <td><?= h($b['booking_date']) ?> · <?= h($b['booking_time']) ?></td>
        <td><?= h($b['meeting_type']) ?></td>
        <td><?= renderBadge(effectiveStatus($b['status'], $b['booking_date'], $b['booking_time'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card-box">
  <h3>Recommended Advisors</h3>
  <div class="grid-3">
    <?php if (!$recommended): ?><p style="color:var(--text-muted)">No recommendations yet.</p><?php endif; ?>
    <?php foreach ($recommended as $a): $hideBookBtn = true; include __DIR__ . '/../includes/advisor-card.php'; ?>
    <?php endforeach; ?>
  </div>
</div>

</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
