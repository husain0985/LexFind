<?php
require_once __DIR__ . '/includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: search.php'); exit; }

$db = getDB();
$stmt = $db->prepare(
    'SELECT ap.*, u.first_name, u.last_name, u.email, u.address, u.avatar
     FROM advisor_profiles ap JOIN users u ON u.id = ap.user_id
     WHERE ap.id = ? AND ap.approval_status = "approved" AND u.status = "active"'
);
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) { setFlash('error', 'Advisor not found.'); header('Location: search.php'); exit; }

$pageTitle = 'Adv. ' . $a['first_name'] . ' ' . $a['last_name'];

$dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$stmt = $db->prepare('SELECT day_of_week FROM advisor_availability WHERE advisor_id = ? AND is_active = 1 ORDER BY day_of_week');
$stmt->execute([$id]);
$availRows = $stmt->fetchAll();
$availDayNums = array_map(fn($r) => (int)$r['day_of_week'], $availRows); // 0=Mon ... 6=Sun
$availDays = array_map(fn($r) => $dayNames[(int)$r['day_of_week']], $availRows);
$stmt = $db->prepare(
    'SELECT r.rating, r.review_text, r.created_at, u.first_name, u.last_name
     FROM reviews r JOIN users u ON u.id = r.client_id
     WHERE r.advisor_id = ? ORDER BY r.created_at DESC LIMIT 20'
);
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

$stmt = $db->prepare('SELECT COUNT(*) c FROM bookings WHERE advisor_id = ? AND status = "confirmed"');
$stmt->execute([$id]);
$sessions = (int)$stmt->fetch()['c'];

$tags = $a['tags'] ? explode(',', $a['tags']) : [];
$u = currentUser();

include __DIR__ . '/includes/header.php';
?>
<div class="section">
  <div class="profile-header">
    <?= avatarImg($a['avatar'] ?? null, $a['first_name'], $a['last_name'], 'profile-avatar-lg') ?>
    <div class="profile-info" style="flex:1">
      <h2><?= h($pageTitle) ?></h2>
      <p style="color:var(--gold);font-weight:600;margin-bottom:6px"><?= h($a['specialty']) ?> Law</p>
      <p><?= (int)$a['years_exp'] ?> years experience · <?= h($a['location']) ?></p>
      <p style="color:var(--text-muted);font-size:0.85rem;margin-top:6px">✉️ <?= h($a['email']) ?></p>
      <?php if (!empty($a['address'])): ?>
      <p style="color:var(--text-muted);font-size:0.85rem">📍 <?= h($a['address']) ?></p>
      <?php endif; ?>
      <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
        <?php foreach ($tags as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?>
      </div>
    </div>
    <div class="profile-fee-box">
      <div class="fee"><?= money($a['fee']) ?><span>/hr</span></div>
      <div class="stars"><?= renderStars((float)$a['rating_avg']) ?></div>
      <?php if (!$u): ?>
        <a class="btn btn-gold" style="margin-top:12px" href="login.php?redirect=<?= urlencode('advisor.php?id=' . $id . '#book') ?>">Log In to Book</a>
      <?php elseif ($u['role'] === 'client'): ?>
        <a class="btn btn-gold" style="margin-top:12px" href="#book">Book Appointment</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="advisor-layout">
    <div class="advisor-col-main">
      <div class="card-box card-about"><h3>About</h3><p style="color:var(--text-muted);line-height:1.7;font-size:0.9rem;margin-top:12px"><?= h($a['bio'] ?: 'No bio provided yet.') ?></p></div>

      <?php if ($u && $u['role'] === 'client'): ?>
      <div class="card-box card-booking" id="book">
        <h3>Book an Appointment</h3>
        <?php $bookErrors = $_SESSION['book_errors'] ?? null; unset($_SESSION['book_errors']); ?>
        <?php if ($bookErrors): ?><div style="color:var(--red);font-size:0.82rem;margin:12px 0"><?= h($bookErrors) ?></div><?php endif; ?>
        <form method="post" action="actions/book.php" style="margin-top:12px" id="bookingForm">
        <?= csrfField() ?>  
        <input type="hidden" name="advisor_id" value="<?= (int)$a['id'] ?>"/>
          <div class="form-group">
            <label>Date</label>
            <div class="lf-datepicker" id="lfDatePicker" data-available="<?= h(implode(',', $availDayNums)) ?>">
              <button type="button" class="lf-date-trigger" id="lfDateTrigger" aria-haspopup="true" aria-expanded="false" <?= empty($availDayNums) ? 'disabled' : '' ?>>
                <span id="lfDateLabel"><?= empty($availDayNums) ? 'No availability set' : 'Select a date' ?></span>
                <span style="opacity:.6"><i class="fa-solid fa-chevron-down"></i></span>
              </button>
              <input type="hidden" name="booking_date" id="lfDateValue"/>
              <div class="lf-calendar" id="lfCalendar" hidden></div>
            </div>
          </div>
          <div class="form-group"><label>Time Slot</label>
            <select name="booking_time">
              <option>09:00 AM</option><option>10:00 AM</option><option>11:00 AM</option>
              <option>02:00 PM</option><option>03:00 PM</option><option>04:00 PM</option>
            </select>
          </div>
          <div class="form-group"><label>Type</label>
            <select name="meeting_type" id="meetingType"><option>Video Call</option><option>Phone Call</option><option>In-Person</option></select>
          </div>
          <div class="form-group" id="whatsappGroup">
            <label>WhatsApp Number</label>
            <input type="text" name="whatsapp_number" id="whatsappInput" placeholder="e.g. +880 1XXX XXXXXX"/>
          </div>
          <div class="form-group"><label>Brief Description of Your Case</label>
            <textarea rows="3" name="case_description" placeholder="Briefly describe your legal matter..." style="resize:vertical"></textarea>
          </div>
          <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px" type="submit">Continue to Payment</button>
        </form>
      </div>
      <?php endif; ?>

      <div class="card-box card-reviews">
        <h3>Client Reviews</h3>
        <div style="margin-top:12px">
          <?php if (!$reviews): ?>
            <p style="color:var(--text-muted);font-size:0.85rem">No reviews yet.</p>
          <?php endif; ?>
          <?php foreach ($reviews as $r): ?>
          <div style="padding:14px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
              <strong style="font-size:0.88rem"><?= h($r['first_name'] . ' ' . substr($r['last_name'], 0, 1) . '.') ?></strong>
              <span style="font-size:0.75rem;color:var(--text-dim)"><?= h(date('M Y', strtotime($r['created_at']))) ?></span>
            </div>
            <div style="color:var(--gold);font-size:0.8rem;margin-bottom:6px"><?= str_repeat('<i class="fa-solid fa-star"></i>', (int)$r['rating']) ?></div>
            <p style="font-size:0.85rem;color:var(--text-muted);line-height:1.6"><?= h($r['review_text']) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="advisor-col-side">
      <div class="card-box card-avail"><h3>Availability</h3>
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
          <?php if (!$availDays): ?><p style="color:var(--text-muted);font-size:0.85rem">No availability set yet.</p><?php endif; ?>
          <?php foreach ($availDays as $d): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:var(--surface2);border-radius:var(--radius-sm)">
              <span style="font-size:0.85rem;font-weight:600"><?= h($d) ?></span><span style="font-size:0.78rem;color:var(--green)">Available</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-box card-stats"><h3>Quick Stats</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
          <div style="text-align:center;padding:12px;background:var(--surface2);border-radius:var(--radius-sm)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.6rem;color:var(--gold)"><?= $sessions ?></div>
            <div style="font-size:0.74rem;color:var(--text-muted)">Sessions</div>
          </div>
          <div style="text-align:center;padding:12px;background:var(--surface2);border-radius:var(--radius-sm)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.6rem;color:var(--gold)"><?= (float)$a['rating_avg'] ?></div>
            <div style="font-size:0.74rem;color:var(--text-muted)">Rating</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const meetingType = document.getElementById('meetingType');
  const whatsappGroup = document.getElementById('whatsappGroup');
  const whatsappInput = document.getElementById('whatsappInput');
  if (meetingType && whatsappGroup) {
    function toggleWhatsapp() {
      const needsIt = meetingType.value === 'Video Call' || meetingType.value === 'Phone Call';
      whatsappGroup.style.display = needsIt ? 'block' : 'none';
    }
    meetingType.addEventListener('change', toggleWhatsapp);
    toggleWhatsapp();
  }

  const trigger = document.getElementById('lfDateTrigger');
  if (!trigger) return;

  const wrap = document.getElementById('lfDatePicker');
  const calendar = document.getElementById('lfCalendar');
  const label = document.getElementById('lfDateLabel');
  const hiddenInput = document.getElementById('lfDateValue');
  const form = document.getElementById('bookingForm');

  const availableDays = (wrap.dataset.available || '')
    .split(',').filter(function (s) { return s !== ''; }).map(Number);

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  let viewYear = today.getFullYear();
  let viewMonth = today.getMonth();
  let selected = null;

  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const dowLabels = ['Mo','Tu','We','Th','Fr','Sa','Su'];

  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function fmt(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function mondayIndex(d) { return (d.getDay() + 6) % 7; }

  function render() {
    const firstOfMonth = new Date(viewYear, viewMonth, 1);
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    const leadingEmpty = mondayIndex(firstOfMonth);
    const isCurrentMonth = viewYear === today.getFullYear() && viewMonth === today.getMonth();

    let html = '<div class="lf-cal-header">'
      + '<button type="button" id="lfPrev"' + (isCurrentMonth ? ' disabled' : '') + '>&lsaquo;</button>'
      + '<span class="lf-cal-title">' + monthNames[viewMonth] + ' ' + viewYear + '</span>'
      + '<button type="button" id="lfNext">&rsaquo;</button>'
      + '</div><div class="lf-cal-grid">';

    dowLabels.forEach(function (l) { html += '<div class="lf-cal-dow">' + l + '</div>'; });
    for (let i = 0; i < leadingEmpty; i++) html += '<div class="lf-cal-day lf-cal-empty"></div>';

    for (let day = 1; day <= daysInMonth; day++) {
      const cellDate = new Date(viewYear, viewMonth, day);
      const isPast = cellDate < today;
      const isAvailable = availableDays.indexOf(mondayIndex(cellDate)) !== -1;
      const disabled = isPast || !isAvailable;
      const isSelected = selected && fmt(selected) === fmt(cellDate);
      html += '<div class="lf-cal-day'
        + (disabled ? ' lf-cal-disabled' : '')
        + (isSelected ? ' lf-cal-selected' : '')
        + '" data-date="' + fmt(cellDate) + '"' + (disabled ? '' : ' role="button" tabindex="0"') + '>'
        + day + '</div>';
    }
    html += '</div><div class="lf-cal-legend">Greyed-out days are outside this advisor&rsquo;s availability.</div>';
    calendar.innerHTML = html;

    document.getElementById('lfPrev').addEventListener('click', function (e) {
      e.stopPropagation();
      viewMonth--; if (viewMonth < 0) { viewMonth = 11; viewYear--; } render();
    });
    document.getElementById('lfNext').addEventListener('click', function (e) {
      e.stopPropagation();
      viewMonth++; if (viewMonth > 11) { viewMonth = 0; viewYear++; } render();
    });
    calendar.querySelectorAll('.lf-cal-day[data-date]:not(.lf-cal-disabled):not(.lf-cal-empty)').forEach(function (cell) {
      cell.addEventListener('click', function () { pick(cell.dataset.date); });
      cell.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(cell.dataset.date); }
      });
    });
  }

  function pick(dateStr) {
    selected = new Date(dateStr + 'T00:00:00');
    hiddenInput.value = dateStr;
    label.textContent = selected.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    wrap.classList.remove('lf-date-error');
    close();
  }

  function open() { calendar.hidden = false; trigger.setAttribute('aria-expanded', 'true'); render(); }
  function close() { calendar.hidden = true; trigger.setAttribute('aria-expanded', 'false'); }

  trigger.addEventListener('click', function () { calendar.hidden ? open() : close(); });
  document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  if (form) {
    form.addEventListener('submit', function (e) {
      if (!hiddenInput.value) {
        e.preventDefault();
        label.textContent = 'Please pick a date';
        wrap.classList.add('lf-date-error');
        open();
      }
    });
  }
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
