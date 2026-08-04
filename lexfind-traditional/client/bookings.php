<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('client');
$pageTitle = 'My Bookings';
$role = 'client'; $activePage = 'bookings';

$db = getDB();
$stmt = $db->prepare(
    'SELECT b.*, au.first_name af, au.last_name al, ap.specialty
     FROM bookings b JOIN advisor_profiles ap ON ap.id = b.advisor_id JOIN users au ON au.id = ap.user_id
     WHERE b.client_id = ? ORDER BY b.booking_date DESC, b.created_at DESC'
);
$stmt->execute([$u['id']]);
$bookings = $stmt->fetchAll();

$stmt2 = $db->prepare('SELECT booking_id FROM reviews WHERE client_id = ?');
$stmt2->execute([$u['id']]);
$reviewedIds = array_column($stmt2->fetchAll(), 'booking_id');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>My Bookings</h2><p>All your appointment history</p></div>
<div class="card-box">
  <table>
    <thead><tr><th>Advisor</th><th>Specialty</th><th>Date</th><th>Type</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead>
    <tbody>
      <?php if (!$bookings): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted)">No bookings yet</td></tr>
      <?php endif; ?>
      <?php foreach ($bookings as $b):
        $status = effectiveStatus($b['status'], $b['booking_date'], $b['booking_time']);
        $alreadyReviewed = in_array((int)$b['id'], $reviewedIds, true);
        $canReview = $status === 'completed' && !$alreadyReviewed;
        $awaitingPayment = $b['status'] === 'pending' && $b['payment_status'] === 'unpaid' && strtotime($b['created_at']) > time() - 15 * 60;
      ?>
      <tr>
        <td><strong>Adv. <?= h($b['af'] . ' ' . $b['al']) ?></strong></td>
        <td><?= h($b['specialty']) ?></td>
        <td><?= h($b['booking_date']) ?></td>
        <td><?= h($b['meeting_type']) ?></td>
        <td><?= renderBadge($status) ?></td>
        <td><?= renderBadge($b['payment_status']) ?></td>
        <td>
          <?php if ($awaitingPayment): ?>
            <a class="btn btn-gold btn-sm" href="../payment.php?booking_id=<?= (int)$b['id'] ?>" style="text-decoration:none">Pay Now</a>
          <?php elseif ($canReview): ?>
            <a class="btn btn-ghost btn-sm" href="reviews.php?booking_id=<?= (int)$b['id'] ?>#review-form" style="text-decoration:none">Review</a>
          <?php elseif ($alreadyReviewed): ?>
            <span style="font-size:0.75rem;color:var(--text-dim)">Reviewed</span>
          <?php else: ?>
            <span style="font-size:0.75rem;color:var(--text-dim)">–</span>
          <?php endif; ?>
          <?php if ($b['payment_status'] === 'paid'): ?>
            <a class="btn btn-ghost btn-sm" href="../invoice.php?booking_id=<?= (int)$b['id'] ?>" target="_blank" rel="noopener" style="text-decoration:none" title="View invoice"><i class="fa-solid fa-file-invoice"></i> Invoice</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>