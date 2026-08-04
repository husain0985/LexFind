<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('advisor');
$pageTitle = 'Booking Requests';
$role = 'advisor'; $activePage = 'bookings';

$db = getDB();
$stmt = $db->prepare('SELECT id FROM advisor_profiles WHERE user_id = ?');
$stmt->execute([$u['id']]);
$advisorId = (int)$stmt->fetch()['id'];

$stmt = $db->prepare(
    'SELECT b.*, u.first_name cf, u.last_name cl FROM bookings b JOIN users u ON u.id = b.client_id
     WHERE b.advisor_id = ? AND b.payment_status != "unpaid" ORDER BY b.booking_date DESC, b.created_at DESC'
);
$stmt->execute([$advisorId]);
$bookings = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>All Booking Requests</h2></div>
<div class="card-box">
  <table>
    <thead><tr><th>Client</th><th>Date</th><th class="col-type">Type</th><th>Status</th><th>Case Notes</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
      <?php if (!$bookings): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No bookings yet</td></tr>
      <?php endif; ?>
      <?php foreach ($bookings as $b): $status = effectiveStatus($b['status'], $b['booking_date'], $b['booking_time']); ?>
      <tr>
        <td><?= h($b['cf'] . ' ' . $b['cl']) ?></td>
        <td><?= h($b['booking_date']) ?> · <?= h($b['booking_time']) ?></td>
        <td class="col-type"><?= h($b['meeting_type']) ?><?php if (!empty($b['whatsapp_number'])): ?><br><span style="font-size:0.72rem;color:var(--text-dim)">📱 <?= h($b['whatsapp_number']) ?></span><?php endif; ?></td>
        <td><?= renderBadge($status) ?></td>
        <td style="max-width:220px;font-size:0.8rem"><?= h($b['case_description'] ? mb_substr($b['case_description'], 0, 80) : '–') ?></td>
        <td class="col-actions">
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