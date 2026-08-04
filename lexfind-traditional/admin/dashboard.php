<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('admin');
$pageTitle = 'Admin Panel';
$role = 'admin'; $activePage = 'overview';

$db = getDB();
$totalUsers = (int)$db->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
$activeAdvisors = (int)$db->query(
    'SELECT COUNT(*) c FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.approval_status = "approved" AND u.status = "active"'
)->fetch()['c'];
$pendingApprovals = (int)$db->query('SELECT COUNT(*) c FROM advisor_profiles WHERE approval_status="pending"')->fetch()['c'];

$allBookings = $db->query('SELECT booking_date, booking_time, status, fee FROM bookings')->fetchAll();
$monthRevenue = 0; $curMonth = (int)date('n'); $curYear = (int)date('Y');
foreach ($allBookings as $b) {
    if (effectiveStatus($b['status'], $b['booking_date'], $b['booking_time']) === 'completed') {
        $ts = strtotime($b['booking_date']);
        if ((int)date('n', $ts) === $curMonth && (int)date('Y', $ts) === $curYear) $monthRevenue += (float)$b['fee'];
    }
}

$recentUsers = $db->query(
    'SELECT u.role, CONCAT(u.first_name," ",u.last_name) label, u.created_at, u.status account_status, ap.approval_status
     FROM users u LEFT JOIN advisor_profiles ap ON ap.user_id = u.id
     ORDER BY u.created_at DESC LIMIT 8'
)->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Admin Panel</h2><p>Platform overview and management</p></div>
<div class="widget-row">
  <div class="widget"><div class="w-label">Total Users</div><div class="w-val"><?= number_format($totalUsers) ?></div><div class="w-sub">All roles</div></div>
  <div class="widget"><div class="w-label">Active Advisors</div><div class="w-val"><?= number_format($activeAdvisors) ?></div><div class="w-sub">Approved</div></div>
  <div class="widget"><div class="w-label">Pending Approvals</div><div class="w-val"><?= $pendingApprovals ?></div><div class="w-sub">Needs review</div></div>
  <div class="widget"><div class="w-label">Platform Revenue</div><div class="w-val"><?= money($monthRevenue) ?></div><div class="w-sub">This month</div></div>
</div>
<div class="card-box">
  <h3>Recent Activity</h3>
  <table>
    <thead><tr><th>Event</th><th>User</th><th>Time</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$recentUsers): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No recent activity</td></tr>
      <?php endif; ?>
      <?php foreach ($recentUsers as $r):
        $eventLabel = $r['role'] === 'advisor' ? 'New advisor registered' : ($r['role'] === 'admin' ? 'Admin account' : 'New client signup');
        if ($r['role'] === 'advisor') {
            $badge = $r['approval_status'] ?: 'pending';
        } else {
            $badge = $r['account_status'] === 'suspended' ? 'suspended' : 'active';
        }
      ?>
      <tr>
        <td><?= h($eventLabel) ?></td>
        <td><?= $r['role'] === 'advisor' ? 'Adv. ' : '' ?><?= h($r['label']) ?></td>
        <td><?= h(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td>
        <td><?= renderBadge($badge) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
