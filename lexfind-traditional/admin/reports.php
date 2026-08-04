<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('admin');
$pageTitle = 'Platform Reports';
$role = 'admin'; $activePage = 'reports';

$db = getDB();
$allBookings = $db->query('SELECT booking_date, booking_time, status, fee FROM bookings')->fetchAll();
$totalBookings = count($allBookings);
$completed = 0; $cancelled = 0; $grossRevenue = 0;
foreach ($allBookings as $b) {
    $status = effectiveStatus($b['status'], $b['booking_date'], $b['booking_time']);
    if ($status === 'completed') { $completed++; $grossRevenue += (float)$b['fee']; }
    if (in_array($b['status'], ['cancelled', 'rejected'], true)) $cancelled++;
}
$completionRate = $totalBookings > 0 ? round($completed / $totalBookings * 100, 1) : 0;
$stmt = $db->query(
    'SELECT ap.id, u.first_name, u.last_name, ap.specialty, ap.rating_avg, ap.rating_count
     FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "approved" AND u.status = "active"'
);
$allApproved = $stmt->fetchAll();
$topAdvisors = [];
foreach ($allApproved as $a) {
    $stmt2 = $db->prepare('SELECT booking_date, booking_time, fee, status FROM bookings WHERE advisor_id = ?');
    $stmt2->execute([$a['id']]);
    $sessions = 0; $revenue = 0;
    foreach ($stmt2->fetchAll() as $b) {
        if (effectiveStatus($b['status'], $b['booking_date'], $b['booking_time']) === 'completed') { $sessions++; $revenue += (float)$b['fee']; }
    }
    $topAdvisors[] = ['name' => $a['first_name'] . ' ' . $a['last_name'], 'specialty' => $a['specialty'], 'sessions' => $sessions, 'rating' => (float)$a['rating_avg'], 'revenue' => $revenue];
}
usort($topAdvisors, fn($x, $y) => $y['sessions'] <=> $x['sessions']);
$topAdvisors = array_slice($topAdvisors, 0, 5);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Platform Reports</h2></div>
<div class="widget-row">
  <div class="widget"><div class="w-label">Total Bookings</div><div class="w-val"><?= number_format($totalBookings) ?></div><div class="w-sub">All time</div></div>
  <div class="widget"><div class="w-label">Completed Sessions</div><div class="w-val"><?= number_format($completed) ?></div><div class="w-sub"><?= $completionRate ?>% completion rate</div></div>
  <div class="widget"><div class="w-label">Cancellations</div><div class="w-val"><?= number_format($cancelled) ?></div><div class="w-sub">Cancelled + rejected</div></div>
  <div class="widget"><div class="w-label">Gross Revenue</div><div class="w-val"><?= money($grossRevenue) ?></div><div class="w-sub">All time</div></div>
</div>
<div class="card-box">
  <h3>Top Performing Advisors</h3>
  <table>
    <thead><tr><th>Advisor</th><th>Specialty</th><th>Sessions</th><th>Rating</th><th>Revenue</th></tr></thead>
    <tbody>
      <?php if (!$topAdvisors): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No data yet</td></tr>
      <?php endif; ?>
      <?php foreach ($topAdvisors as $a): ?>
      <tr>
        <td><strong>Adv. <?= h($a['name']) ?></strong></td>
        <td><?= h($a['specialty']) ?></td>
        <td><?= $a['sessions'] ?></td>
        <td><i class="fa-solid fa-star" style="color:var(--gold)"></i> <?= $a['rating'] ?></td>
        <td><?= money($a['revenue']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
