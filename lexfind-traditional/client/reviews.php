<?php
require_once __DIR__ . '/../includes/auth.php';
$u = requireLogin('client');
$pageTitle = 'My Reviews';
$role = 'client'; $activePage = 'reviews';
$preselect = (int)($_GET['booking_id'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);

$db = getDB();
$stmt = $db->prepare(
    'SELECT r.*, au.first_name af, au.last_name al
     FROM reviews r JOIN advisor_profiles ap ON ap.id = r.advisor_id JOIN users au ON au.id = ap.user_id
     WHERE r.client_id = ? ORDER BY r.created_at DESC'
);
$stmt->execute([$u['id']]);
$myReviews = $stmt->fetchAll();

// completed bookings without a review yet
$stmt = $db->prepare(
    'SELECT b.id, b.booking_date, b.booking_time, b.status, au.first_name af, au.last_name al
     FROM bookings b JOIN advisor_profiles ap ON ap.id = b.advisor_id JOIN users au ON au.id = ap.user_id
     LEFT JOIN reviews r ON r.booking_id = b.id
     WHERE b.client_id = ? AND r.id IS NULL ORDER BY b.booking_date DESC'
);
$stmt->execute([$u['id']]);
$reviewOptions = [];
foreach ($stmt->fetchAll() as $r) {
    if (effectiveStatus($r['status'], $r['booking_date'], $r['booking_time']) === 'completed') {
        $reviewOptions[] = ['id' => (int)$r['id'], 'name' => 'Adv. ' . $r['af'] . ' ' . $r['al']];
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_review'])) {
    csrfVerify();
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $text = trim($_POST['review_text'] ?? '');

    if ($rating < 1 || $rating > 5) $errors[] = 'Please choose a star rating.';
    elseif ($text === '') $errors[] = 'Please write a short review.';

    if (!$errors) {
        $stmt = $db->prepare('SELECT * FROM reviews WHERE id = ? AND client_id = ?');
        $stmt->execute([$reviewId, $u['id']]);
        $review = $stmt->fetch();
        if (!$review) {
            $errors[] = 'Review not found.';
        } else {
            $stmt = $db->prepare('UPDATE reviews SET rating = ?, review_text = ? WHERE id = ?');
            $stmt->execute([$rating, $text, $reviewId]);
            $stmt = $db->prepare('SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE advisor_id = ?');
            $stmt->execute([$review['advisor_id']]);
            $agg = $stmt->fetch();
            $stmt = $db->prepare('UPDATE advisor_profiles SET rating_avg = ?, rating_count = ? WHERE id = ?');
            $stmt->execute([round((float)$agg['a'], 2), (int)$agg['c'], $review['advisor_id']]);
            setFlash('success', 'Your review has been updated.');
            header('Location: reviews.php');
            exit;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    csrfVerify();
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $text = trim($_POST['review_text'] ?? '');

    if ($bookingId <= 0) $errors[] = 'Please select a completed booking to review.';
    elseif ($rating < 1 || $rating > 5) $errors[] = 'Please choose a star rating.';
    elseif ($text === '') $errors[] = 'Please write a short review.';

    if (!$errors) {
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = ? AND client_id = ?');
        $stmt->execute([$bookingId, $u['id']]);
        $booking = $stmt->fetch();
        if (!$booking || effectiveStatus($booking['status'], $booking['booking_date'], $booking['booking_time']) !== 'completed') {
            $errors[] = 'You can only review completed sessions.';
        } else {
            $stmt = $db->prepare('SELECT id FROM reviews WHERE booking_id = ?');
            $stmt->execute([$bookingId]);
            if ($stmt->fetch()) {
                $errors[] = 'You have already reviewed this booking.';
            } else {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare('INSERT INTO reviews (booking_id, client_id, advisor_id, rating, review_text) VALUES (?,?,?,?,?)');
                    $stmt->execute([$bookingId, $u['id'], $booking['advisor_id'], $rating, $text]);
                    $stmt = $db->prepare('SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE advisor_id = ?');
                    $stmt->execute([$booking['advisor_id']]);
                    $agg = $stmt->fetch();
                    $stmt = $db->prepare('UPDATE advisor_profiles SET rating_avg = ?, rating_count = ? WHERE id = ?');
                    $stmt->execute([round((float)$agg['a'], 2), (int)$agg['c'], $booking['advisor_id']]);
                    $db->commit();
                    setFlash('success', 'Thank you! Your review has been submitted.');
                    header('Location: reviews.php');
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = 'Could not save review. Please try again.';
                }
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/dash-nav.php';
?>
<div class="dash-header"><h2>My Reviews</h2><p>Reviews you've left for advisors</p></div>
<div class="card-box">
  <?php if (!$myReviews): ?>
    <p style="color:var(--text-muted)">You have not left any reviews yet.</p>
  <?php endif; ?>
  <?php foreach ($myReviews as $r): ?>
  <div id="review-<?= (int)$r['id'] ?>" style="padding:14px;background:var(--surface2);border-radius:var(--radius-sm);margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
      <strong style="font-size:0.9rem">Adv. <?= h($r['af'] . ' ' . $r['al']) ?></strong>
      <span style="font-size:0.75rem;color:var(--text-dim)"><?= h(date('M Y', strtotime($r['created_at']))) ?></span>
    </div>

    <?php if ($editId === (int)$r['id']): ?>
      <form method="post" action="reviews.php">
        <?= csrfField() ?>
        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"/>
        <div class="form-group">
          <label>Rating</label>
          <div class="star-input-native">
            <input type="radio" id="edit-star5-<?= (int)$r['id'] ?>" name="rating" value="5" <?= (int)$r['rating']===5?'checked':'' ?>/><label for="edit-star5-<?= (int)$r['id'] ?>"><i class="fa-solid fa-star"></i></label>
            <input type="radio" id="edit-star4-<?= (int)$r['id'] ?>" name="rating" value="4" <?= (int)$r['rating']===4?'checked':'' ?>/><label for="edit-star4-<?= (int)$r['id'] ?>"><i class="fa-solid fa-star"></i></label>
            <input type="radio" id="edit-star3-<?= (int)$r['id'] ?>" name="rating" value="3" <?= (int)$r['rating']===3?'checked':'' ?>/><label for="edit-star3-<?= (int)$r['id'] ?>"><i class="fa-solid fa-star"></i></label>
            <input type="radio" id="edit-star2-<?= (int)$r['id'] ?>" name="rating" value="2" <?= (int)$r['rating']===2?'checked':'' ?>/><label for="edit-star2-<?= (int)$r['id'] ?>"><i class="fa-solid fa-star"></i></label>
            <input type="radio" id="edit-star1-<?= (int)$r['id'] ?>" name="rating" value="1" <?= (int)$r['rating']===1?'checked':'' ?>/><label for="edit-star1-<?= (int)$r['id'] ?>"><i class="fa-solid fa-star"></i></label>
          </div>
        </div>
        <div class="form-group"><label>Your Review</label><textarea rows="3" name="review_text"><?= h($r['review_text']) ?></textarea></div>
        <button class="btn btn-gold btn-sm" type="submit" name="update_review" value="1">Save Changes</button>
        <a class="btn btn-ghost btn-sm" href="reviews.php" style="text-decoration:none">Cancel</a>
      </form>
    <?php else: ?>
      <div style="color:var(--gold);font-size:0.85rem;margin-bottom:6px"><?= str_repeat('<i class="fa-solid fa-star"></i>', (int)$r['rating']) ?></div>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px"><?= h($r['review_text']) ?></p>
      <div style="display:flex;gap:8px">
        <a class="btn btn-ghost btn-sm" href="reviews.php?edit=<?= (int)$r['id'] ?>#review-<?= (int)$r['id'] ?>" style="text-decoration:none">Edit</a>
        <form method="post" action="../actions/delete-review.php" onsubmit="return confirm('Delete this review? This cannot be undone.')" style="display:inline-block">
          <?= csrfField() ?>
          <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"/>
          <button class="btn btn-red btn-sm" type="submit">Delete</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div id="review-form" style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
    <h3 style="margin-bottom:16px">Leave a New Review</h3>
    <?php if ($errors): ?><div style="color:var(--red);font-size:0.82rem;margin-bottom:12px"><?= h($errors[0]) ?></div><?php endif; ?>

    <?php if (!$reviewOptions): ?>
      <p style="color:var(--text-muted);font-size:0.86rem">No completed sessions awaiting review right now.</p>
    <?php else: ?>
    <form method="post" action="reviews.php">
      <?= csrfField() ?>
      <div class="form-group">
        <label>Select Booking</label>
        <select name="booking_id">
          <?php foreach ($reviewOptions as $opt): ?>
            <option value="<?= $opt['id'] ?>" <?= $preselect === $opt['id'] ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Rating</label>
        <div class="star-input-native">
          <input type="radio" id="star5" name="rating" value="5"/><label for="star5"><i class="fa-solid fa-star"></i></label>
          <input type="radio" id="star4" name="rating" value="4"/><label for="star4"><i class="fa-solid fa-star"></i></label>
          <input type="radio" id="star3" name="rating" value="3"/><label for="star3"><i class="fa-solid fa-star"></i></label>
          <input type="radio" id="star2" name="rating" value="2"/><label for="star2"><i class="fa-solid fa-star"></i></label>
          <input type="radio" id="star1" name="rating" value="1"/><label for="star1"><i class="fa-solid fa-star"></i></label>
        </div>
      </div>
      <div class="form-group"><label>Your Review</label><textarea rows="3" name="review_text" placeholder="Share your experience..."></textarea></div>
      <button class="btn btn-gold" type="submit" name="submit_review" value="1">Submit Review</button>
    </form>
    <?php endif; ?>
  </div>
</div>
</div></div>
<?php include __DIR__ . '/../includes/dash-footer.php'; ?>
