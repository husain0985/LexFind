<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('admin');
$pageTitle = 'Messages';
$role = 'admin'; $activePage = 'messages';

$db = getDB();
$stmt = $db->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 300');
$messages = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Contact Messages</h2><p>Messages submitted through the public Contact page</p></div>
<div class="card-box">
  <table>
    <thead><tr><th>From</th><th>Subject</th><th>Received</th><th>Status</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
      <?php if (!$messages): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No messages yet</td></tr>
      <?php endif; ?>
      <?php foreach ($messages as $m): ?>
      <tr style="<?= !$m['is_read'] ? 'font-weight:600' : '' ?>">
        <td>
          <?= h($m['name']) ?>
          <div style="font-weight:400;font-size:0.76rem;color:var(--text-muted)"><?= h($m['email']) ?></div>
        </td>
        <td><?= h($m['subject'] ?: '(no subject)') ?></td>
        <td style="font-weight:400"><?= h(date('M j, Y g:i A', strtotime($m['created_at']))) ?></td>
        <td><?= $m['is_read'] ? renderBadge('read') : renderBadge('unread') ?></td>
        <td class="col-actions">
          <a class="btn btn-ghost btn-sm" href="message-view.php?id=<?= (int)$m['id'] ?>" style="text-decoration:none">View</a>
          <form method="post" action="../actions/delete-message.php" style="display:inline-block" onsubmit="return confirm('Delete this message permanently?')">
            <?= csrfField() ?>
            <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>"/>
            <button class="btn btn-red btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>