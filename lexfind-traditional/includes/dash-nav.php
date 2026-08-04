<?php
/**
 * Shared dashboard shell opener. Include after header.php on any dashboard page.
 * Expects: $role ('client'|'advisor'|'admin'), $activePage (slug matching one of the hrefs below)
 */
$b = basePath();
?>
<div class="dash-layout">
  <div class="sidebar">
    <?php if ($role === 'client'): ?>
      <div class="sidebar-section">
        <div class="sidebar-label">Main</div>
        <a href="<?= $b ?>client/dashboard.php" class="<?= $activePage === 'overview' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-house"></i></span>Overview</a>
        <a href="<?= $b ?>search.php"><span class="sidebar-icon"><i class="fa-solid fa-magnifying-glass"></i></span>Search Advisors</a>
        <a href="<?= $b ?>client/bookings.php" class="<?= $activePage === 'bookings' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-calendar-days"></i></span>My Bookings</a>
        <a href="<?= $b ?>client/reviews.php" class="<?= $activePage === 'reviews' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-star"></i></span>My Reviews</a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-label">Account</div>
        <a href="<?= $b ?>client/profile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>"><span class="sidebar-icon">👤</span>Profile</a>
        <a href="<?= $b ?>logout.php" style="color:var(--red)"><span class="sidebar-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Log Out</a>
      </div>
    <?php elseif ($role === 'advisor'): ?>
      <div class="sidebar-section">
        <div class="sidebar-label">Dashboard</div>
        <a href="<?= $b ?>advisor/dashboard.php" class="<?= $activePage === 'overview' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-house"></i></span>Overview</a>
        <a href="<?= $b ?>advisor/profile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-user"></i></span>Profile</a>
        <a href="<?= $b ?>advisor/availability.php" class="<?= $activePage === 'availability' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-calendar-days"></i></span>Availability</a>
        <a href="<?= $b ?>advisor/bookings.php" class="<?= $activePage === 'bookings' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-clipboard-list"></i></span>Booking Requests</a>
        <a href="<?= $b ?>advisor/earnings.php" class="<?= $activePage === 'earnings' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-sack-dollar"></i></span>Earnings</a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-label">Account</div>
       <a href="<?= $b ?>logout.php" style="color:var(--red)"><span class="sidebar-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Log Out</a>
      </div>
    <?php elseif ($role === 'admin'): ?>
      <div class="sidebar-section">
        <div class="sidebar-label">Administration</div>
       <a href="<?= $b ?>admin/dashboard.php" class="<?= $activePage === 'overview' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-house"></i></span>Overview</a>
        <a href="<?= $b ?>admin/users.php" class="<?= $activePage === 'users' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-users"></i></span>User Management</a>
        <a href="<?= $b ?>admin/approvals.php" class="<?= $activePage === 'approvals' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-circle-check"></i></span>Advisor Approval</a>
        <a href="<?= $b ?>admin/messages.php" class="<?= $activePage === 'messages' ? 'active' : '' ?>" style="display:flex;align-items:center;justify-content:space-between">
          <span style="display:flex;align-items:center;gap:10px"><span class="sidebar-icon"><i class="fa-solid fa-envelope"></i></span>Messages</span>
          <?php $unreadCount = (int)getDB()->query('SELECT COUNT(*) c FROM contact_messages WHERE is_read = 0')->fetch()['c']; ?>
          <?php if ($unreadCount > 0): ?><span style="background:var(--red);color:#fff;font-size:0.68rem;font-weight:700;padding:1px 7px;border-radius:10px"><?= $unreadCount ?></span><?php endif; ?>
        </a>
        <a href="<?= $b ?>admin/reports.php" class="<?= $activePage === 'reports' ? 'active' : '' ?>"><span class="sidebar-icon"><i class="fa-solid fa-chart-column"></i></span>Reports</a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-label">System</div>
        <a href="<?= $b ?>logout.php" style="color:var(--red)"><span class="sidebar-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Log Out</a>
      </div>
    <?php endif; ?>
  </div>
  <div class="dash-main">
