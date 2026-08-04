<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('admin');
$pageTitle = 'Advisor Approval';
$role = 'admin'; $activePage = 'approvals';

$db = getDB();
$stmt = $db->query(
    'SELECT ap.id, ap.specialty, ap.bar_license, ap.bar_id_card, ap.created_at, u.first_name, u.last_name
     FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "pending" ORDER BY ap.created_at ASC'
);
$approvals = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Advisor Approval</h2><p>Review and verify advisor applications</p></div>
<div class="card-box">
  <table>
    <thead><tr><th>Advisor</th><th>Specialty</th><th>License</th><th>ID Card</th><th>Applied</th><th>Action</th></tr></thead>
    <tbody>
      <?php if (!$approvals): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No pending applications <i class="fa-solid fa-champagne-glasses"></i></td></tr>
      <?php endif; ?>
      <?php foreach ($approvals as $a): ?>
      <tr>
        <td><strong>Adv. <?= h($a['first_name'] . ' ' . $a['last_name']) ?></strong></td>
        <td><?= h($a['specialty']) ?> Law</td>
        <td><?= h($a['bar_license'] ?: '(not provided)') ?></td>
        <td>
          <?php if (!empty($a['bar_id_card'])): ?>
            <a href="view-bar-id.php?advisor_id=<?= (int)$a['id'] ?>" target="_blank" class="btn btn-sm" style="padding:4px 10px"><i class="fa-solid fa-id-card"></i> View</a>
          <?php else: ?>
            <span style="color:var(--red);font-size:0.8rem">Not uploaded</span>
          <?php endif; ?>
        </td>
        <td><?= h(date('M j, Y', strtotime($a['created_at']))) ?></td>
        <td>
          <form method="post" action="../actions/admin-approval.php" style="display:inline-block">
          <?= csrfField() ?>  
          <input type="hidden" name="advisor_id" value="<?= (int)$a['id'] ?>"/>
            <button class="btn btn-green btn-sm" type="submit" name="action" value="approve">Approve</button>
            <button class="btn btn-red btn-sm" type="submit" name="action" value="reject">Reject</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
