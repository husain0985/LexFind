<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('advisor');
$pageTitle = 'Dashboard';
$role = 'advisor'; $activePage = 'overview';

$db = getDB();
$stmt = $db->prepare('SELECT * FROM advisor_profiles WHERE user_id = ?');
$stmt->execute([$u['id']]);
$ap = $stmt->fetch();
$advisorId = (int)$ap['id'];

$stmt = $db->prepare('SELECT COUNT(*) c FROM bookings WHERE advisor_id = ?');
$stmt->execute([$advisorId]);
$totalBookings = (int)$stmt->fetch()['c'];

$stmt = $db->prepare('SELECT COUNT(*) c FROM bookings WHERE advisor_id = ? AND status = "pending" AND payment_status = "paid" AND CONCAT(booking_date," ",booking_time) >= NOW()');
$stmt->execute([$advisorId]);
$pendingCount = (int)$stmt->fetch()['c'];

$stmt = $db->prepare('SELECT booking_date, booking_time, fee FROM bookings WHERE advisor_id = ? AND status = "confirmed"');
$stmt->execute([$advisorId]);
$confirmed = $stmt->fetchAll();
$thisMonth = 0; $curMonth = (int)date('n'); $curYear = (int)date('Y');
foreach ($confirmed as $b) {
    if (isPastDateTime($b['booking_date'], $b['booking_time'])) {
        $ts = strtotime($b['booking_date']);
        if ((int)date('n', $ts) === $curMonth && (int)date('Y', $ts) === $curYear) $thisMonth += (float)$b['fee'];
    }
}

$stmt = $db->prepare(
    'SELECT b.*, u.first_name cf, u.last_name cl FROM bookings b JOIN users u ON u.id = b.client_id
     WHERE b.advisor_id = ? AND b.payment_status != "unpaid" ORDER BY b.booking_date DESC, b.created_at DESC LIMIT 5'
);
$stmt->execute([$advisorId]);
$recent = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Advisor Dashboard</h2><p>Manage your practice on LexFind</p></div>
<?php if ($ap['approval_status'] === 'pending'): ?>
<div style="background:var(--gold-dim);border:1px solid rgba(201,168,76,0.3);border-radius:var(--radius);padding:14px 20px;margin-bottom:20px;font-size:0.85rem;color:var(--gold)">
  Your advisor profile is pending admin approval. You will appear in search once approved.
</div>
<?php endif; ?>
<div class="widget-row">
  <div class="widget"><div class="w-label">Total Bookings</div><div class="w-val"><?= $totalBookings ?></div><div class="w-sub">All time</div></div>
  <div class="widget"><div class="w-label">Pending Requests</div><div class="w-val"><?= $pendingCount ?></div><div class="w-sub">Needs response</div></div>
  <div class="widget"><div class="w-label">Rating</div><div class="w-val"><?= (float)$ap['rating_avg'] ?> <i class="fa-solid fa-star" style="font-size:0.7em"></i></div><div class="w-sub"><?= (int)$ap['rating_count'] ?> reviews</div></div>
  <div class="widget"><div class="w-label">This Month</div><div class="w-val"><?= money($thisMonth) ?></div><div class="w-sub">Completed sessions</div></div>
</div>
<div class="card-box">
  <h3>Recent Booking Requests</h3>
  <table>
    <thead><tr><th>Client</th><th>Date</th><th>Slot</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php if (!$recent): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No booking requests yet</td></tr>
      <?php endif; ?>
      <?php foreach ($recent as $b): $status = effectiveStatus($b['status'], $b['booking_date'], $b['booking_time']); ?>
      <tr>
        <td><strong><?= h($b['cf'] . ' ' . $b['cl']) ?></strong></td>
        <td><?= h($b['booking_date']) ?></td>
        <td><?= h($b['booking_time']) ?></td>
        <td><?= h($b['meeting_type']) ?></td>
        <td><?= renderBadge($status) ?></td>
        <td>
          <?php if ($status === 'pending'): ?>
            <form method="post" action="../actions/respond-booking.php" style="display:inline-block">
            <?= csrfField() ?>  
            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>"/>
              <button class="btn btn-green btn-sm" type="submit" name="action" value="accept">Accept</button>
              <button class="btn btn-red btn-sm" type="submit" name="action" value="reject">Reject</button>
            </form>
          <?php else: ?>
            <span style="font-size:0.75rem;color:var(--text-dim)">–</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
