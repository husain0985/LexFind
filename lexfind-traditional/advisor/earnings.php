<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('advisor');
$pageTitle = 'Earnings';
$role = 'advisor'; $activePage = 'earnings';

$db = getDB();
$stmt = $db->prepare('SELECT id, rating_avg, rating_count FROM advisor_profiles WHERE user_id = ?');
$stmt->execute([$u['id']]);
$ap = $stmt->fetch();
$advisorId = (int)$ap['id'];

$stmt = $db->prepare('SELECT id, booking_date, booking_time, fee FROM bookings WHERE advisor_id = ? AND status = "confirmed"');
$stmt->execute([$advisorId]);
$confirmed = $stmt->fetchAll();
$completed = array_filter($confirmed, fn($b) => isPastDateTime($b['booking_date'], $b['booking_time']));

$thisMonth = 0; $totalEarned = 0; $sessionsThisMonth = 0;
$monthly = array_fill(1, 12, 0);
$curMonth = (int)date('n'); $curYear = (int)date('Y');
foreach ($completed as $b) {
    $totalEarned += (float)$b['fee'];
    $ts = strtotime($b['booking_date']);
    $m = (int)date('n', $ts); $y = (int)date('Y', $ts);
    if ($y === $curYear) $monthly[$m] += (float)$b['fee'];
    if ($m === $curMonth && $y === $curYear) { $thisMonth += (float)$b['fee']; $sessionsThisMonth++; }
}
$avgPerSession = $sessionsThisMonth > 0 ? round($thisMonth / $sessionsThisMonth, 2) : 0;
$maxMonthly = max(1, ...array_values($monthly));

usort($completed, fn($a, $b) => strcmp($b['booking_date'], $a['booking_date']));
$recentIds = array_slice($completed, 0, 10);
$transactions = [];
if ($recentIds) {
    $stmt = $db->prepare('SELECT b.*, u.first_name, u.last_name FROM bookings b JOIN users u ON u.id = b.client_id WHERE b.id = ?');
    foreach ($recentIds as $b) {
        $stmt->execute([$b['id']]);
        $row = $stmt->fetch();
        if ($row) $transactions[] = $row;
    }
}
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Earnings Overview</h2></div>
<div class="widget-row">
  <div class="widget"><div class="w-label">This Month</div><div class="w-val"><?= money($thisMonth) ?></div><div class="w-sub">Completed sessions</div></div>
  <div class="widget"><div class="w-label">Total Earned</div><div class="w-val"><?= money($totalEarned) ?></div><div class="w-sub">All time</div></div>
  <div class="widget"><div class="w-label">Sessions</div><div class="w-val"><?= $sessionsThisMonth ?></div><div class="w-sub">This month</div></div>
  <div class="widget"><div class="w-label">Avg per Session</div><div class="w-val"><?= money($avgPerSession) ?></div><div class="w-sub">This month</div></div>
</div>
<div class="card-box">
  <h3>Monthly Earnings (<?= date('Y') ?>)</h3>
  <div class="earnings-bar">
    <?php foreach ($months as $i => $m): $v = $monthly[$i + 1]; ?>
      <div class="e-bar" style="height:<?= $v ? max(10, round($v / $maxMonthly * 100)) . '%' : '4px' ?>;<?= $v == 0 ? 'opacity:0.2' : '' ?>" title="<?= h($m) ?>: <?= money($v) ?>">
        <?php if ($v): ?><span><?= money($v) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="e-label"><?php foreach ($months as $m): ?><span><?= h($m) ?></span><?php endforeach; ?></div>
</div>
<div class="card-box">
  <h3>Recent Transactions</h3>
  <table>
    <thead><tr><th>Client</th><th>Date</th><th>Session</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$transactions): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No completed sessions yet</td></tr>
      <?php endif; ?>
      <?php foreach ($transactions as $t): ?>
      <tr>
        <td><?= h($t['first_name'] . ' ' . $t['last_name']) ?></td>
        <td><?= h($t['booking_date']) ?></td>
        <td><?= h($t['meeting_type']) ?></td>
        <td><?= money($t['fee']) ?></td>
        <td><span class="badge badge-green">Paid</span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
