<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('admin');
$pageTitle = 'User Management';
$role = 'admin'; $activePage = 'users';

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? 'All Roles');

$db = getDB();
$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($roleFilter !== '' && $roleFilter !== 'All Roles') {
    $roleMap = ['Clients' => 'client', 'Advisors' => 'advisor'];
    $where[] = 'role = ?';
    $params[] = $roleMap[$roleFilter] ?? strtolower($roleFilter);
}
$stmt = $db->prepare('SELECT id, first_name, last_name, email, role, status, created_at FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200');
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>User Management</h2></div>
<div class="card-box">
  <form method="get" action="users.php" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <input type="text" name="search" placeholder="Search users…" value="<?= h($search) ?>" style="flex:1;min-width:200px;padding:9px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:0.86rem;font-family:'DM Sans',sans-serif;outline:none"/>
    <select name="role" style="padding:9px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.86rem;outline:none">
      <?php foreach (['All Roles','Clients','Advisors'] as $opt): ?>
        <option <?= $roleFilter === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-gold btn-sm" type="submit">Filter</button>
  </form>
  <table>
    <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Joined</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php if (!$users): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No users found</td></tr>
      <?php endif; ?>
      <?php foreach ($users as $row): ?>
      <tr>
        <td><strong><?= $row['role'] === 'advisor' ? 'Adv. ' : '' ?><?= h($row['first_name'] . ' ' . $row['last_name']) ?></strong></td>
        <td><?= h(ucfirst($row['role'])) ?></td>
        <td><?= h($row['email']) ?></td>
        <td><?= h(date('M Y', strtotime($row['created_at']))) ?></td>
        <td><span class="badge <?= $row['status'] === 'active' ? 'badge-green' : 'badge-red' ?>"><?= h(ucfirst($row['status'])) ?></span></td>
        <td>
          <?php if ($row['role'] !== 'admin'): ?>
          <form method="post" action="../actions/admin-user-status.php" onsubmit="return confirm('<?= $row['status'] === 'active' ? 'Suspend' : 'Restore' ?> this user?')">
          <?= csrfField() ?>  
          <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"/>
            <input type="hidden" name="search" value="<?= h($search) ?>"/>
            <input type="hidden" name="role_filter" value="<?= h($roleFilter) ?>"/>
            <?php if ($row['status'] === 'active'): ?>
              <button class="btn btn-red btn-sm" type="submit" name="action" value="suspend">Suspend</button>
            <?php else: ?>
              <button class="btn btn-green btn-sm" type="submit" name="action" value="restore">Restore</button>
            <?php endif; ?>
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
