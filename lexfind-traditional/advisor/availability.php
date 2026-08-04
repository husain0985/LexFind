<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('advisor');
$pageTitle = 'Availability Setup';
$role = 'advisor'; $activePage = 'availability';

$db = getDB();
$stmt = $db->prepare('SELECT id, session_duration, buffer_minutes FROM advisor_profiles WHERE user_id = ?');
$stmt->execute([$u['id']]);
$ap = $stmt->fetch();
$advisorId = (int)$ap['id'];

$dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionDuration = (int)($_POST['session_duration'] ?? 60);
    $bufferMinutes = (int)($_POST['buffer_minutes'] ?? 15);
    $days = $_POST['days'] ?? []; // array of day indices that were checked

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE advisor_profiles SET session_duration=?, buffer_minutes=? WHERE id=?');
        $stmt->execute([$sessionDuration, $bufferMinutes, $advisorId]);
        $upd = $db->prepare('UPDATE advisor_availability SET is_active=? WHERE advisor_id=? AND day_of_week=?');
        for ($d = 0; $d < 7; $d++) {
            $upd->execute([in_array((string)$d, $days, true) ? 1 : 0, $advisorId, $d]);
        }
        $db->commit();
        setFlash('success', 'Availability saved.');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Could not save availability.');
    }
    header('Location: availability.php');
    exit;
}

$stmt = $db->prepare('SELECT day_of_week, is_active FROM advisor_availability WHERE advisor_id = ? ORDER BY day_of_week');
$stmt->execute([$advisorId]);
$activeDays = array_fill(0, 7, 0);
foreach ($stmt->fetchAll() as $r) $activeDays[(int)$r['day_of_week']] = (int)$r['is_active'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>Availability Setup</h2><p>Check the days you're available</p></div>
<div class="card-box">
  <h3 style="margin-bottom:20px">Weekly Schedule</h3>
  <form method="post" action="availability.php">
    <div class="avail-grid">
      <?php foreach ($dayNames as $i => $d): ?>
        <input type="checkbox" id="day<?= $i ?>" name="days[]" value="<?= $i ?>" <?= $activeDays[$i] ? 'checked' : '' ?>/>
        <label for="day<?= $i ?>" class="avail-day">
          <h4><?= h($d) ?></h4>
          <p><?= $activeDays[$i] ? 'Active' : 'Off' ?></p>
        </label>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:24px;border-top:1px solid var(--border);padding-top:20px">
      <h3 style="margin-bottom:16px">Session Settings</h3>
      <div class="form-row">
        <div class="form-group"><label>Session Duration</label>
          <select name="session_duration">
            <?php foreach ([30,60,90] as $m): ?><option value="<?= $m ?>" <?= (int)$ap['session_duration'] === $m ? 'selected' : '' ?>><?= $m ?> minutes</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Buffer Between Sessions</label>
          <select name="buffer_minutes">
            <option value="0" <?= (int)$ap['buffer_minutes'] === 0 ? 'selected' : '' ?>>None</option>
            <option value="15" <?= (int)$ap['buffer_minutes'] === 15 ? 'selected' : '' ?>>15 min</option>
            <option value="30" <?= (int)$ap['buffer_minutes'] === 30 ? 'selected' : '' ?>>30 min</option>
          </select>
        </div>
      </div>
    </div>
    <p style="font-size:0.78rem;color:var(--text-dim);margin:16px 0">Tip: check/uncheck a day, then click Save — the label updates once saved.</p>
    <button class="btn btn-gold" type="submit">Save Availability</button>
  </form>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
