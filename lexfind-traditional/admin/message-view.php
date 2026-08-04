<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('admin');
$pageTitle = 'View Message';
$role = 'admin'; $activePage = 'messages';

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare('SELECT * FROM contact_messages WHERE id = ?');
$stmt->execute([$id]);
$m = $stmt->fetch();

if (!$m) {
    setFlash('error', 'Message not found.');
    header('Location: messages.php'); exit;
}

// opening a message marks it read
if (!$m['is_read']) {
    $db->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$id]);
    $m['is_read'] = 1;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2><?= h($m['subject'] ?: '(no subject)') ?></h2><p>From <?= h($m['name']) ?> · <?= h($m['email']) ?> · <?= h(date('M j, Y g:i A', strtotime($m['created_at']))) ?></p></div>
<div class="card-box" style="max-width:720px">
  <div style="white-space:pre-wrap;line-height:1.7;font-size:0.9rem;color:var(--text);margin-bottom:24px"><?= h($m['message']) ?></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid var(--border);padding-top:18px">
    <a class="btn btn-gold btn-sm" href="mailto:<?= h($m['email']) ?>?subject=<?= urlencode('Re: ' . ($m['subject'] ?: 'Your message to LexFind')) ?>" style="text-decoration:none">Reply via Email</a>
    <form method="post" action="../actions/toggle-message-read.php" style="display:inline-block">
      <?= csrfField() ?>
      <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>"/>
      <input type="hidden" name="redirect" value="view"/>
      <button class="btn btn-ghost btn-sm" type="submit" name="mark_as" value="<?= $m['is_read'] ? 'unread' : 'read' ?>">Mark as <?= $m['is_read'] ? 'Unread' : 'Read' ?></button>
    </form>
    <form method="post" action="../actions/delete-message.php" style="display:inline-block" onsubmit="return confirm('Delete this message permanently?')">
      <?= csrfField() ?>
      <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>"/>
      <button class="btn btn-red btn-sm" type="submit">Delete</button>
    </form>
    <a class="btn btn-ghost btn-sm" href="messages.php" style="text-decoration:none;margin-left:auto">Back to Messages</a>
  </div>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>