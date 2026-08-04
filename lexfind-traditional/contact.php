<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Contact';
$activeNav = 'contact';

$errors = [];
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if ($old['name'] === '' || $old['email'] === '' || $old['message'] === '') {
        $errors[] = 'Please fill in all required fields.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?,?,?,?)');
        $stmt->execute([$old['name'], $old['email'], $old['subject'], $old['message']]);
        setFlash('success', "Thanks, {$old['name']}! We'll get back to you within 24 hours.");
        header('Location: contact.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="section">
  <div class="section-title">Contact Us</div>
  <div class="divider"></div>
  <div class="contact-grid">
    <div class="contact-info">
      <h3>Get in Touch</h3>
      <p>Have questions? Our team is here to help. Reach out and we'll respond within 24 hours.</p>
      <div class="contact-item"><div class="ci"><i class="fa-solid fa-location-dot"></i></div><div><strong>Address</strong><p style="color:var(--text-muted);font-size:0.85rem">Kotbari, Comilla University, Cumilla 3500</p></div></div>
      <div class="contact-item"><div class="ci"><i class="fa-solid fa-envelope"></i></div><div><strong>Email</strong><p style="color:var(--text-muted);font-size:0.85rem">support@lexfind.com.bd</p></div></div>
      <div class="contact-item"><div class="ci"><i class="fa-solid fa-phone"></i></div><div><strong>Phone</strong><p style="color:var(--text-muted);font-size:0.85rem">+880 2 9999 0000</p></div></div>
      <div class="contact-item"><div class="ci"><i class="fa-solid fa-clock"></i></div><div><strong>Hours</strong><p style="color:var(--text-muted);font-size:0.85rem">Sat–Thu, 9:00 AM – 8:00 PM</p></div></div>
    </div>
    <div class="card-box" style="margin-bottom:0">
      <h3 style="margin-bottom:20px">Send a Message</h3>
      <?php if ($errors): ?>
        <div style="color:var(--red);font-size:0.82rem;margin-bottom:12px"><?= h($errors[0]) ?></div>
      <?php endif; ?>
      <form method="post" action="contact.php">
        <div class="form-row">
          <div class="form-group"><label>Your Name</label><input type="text" name="name" placeholder="Full name" value="<?= h($old['name']) ?>"/></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="you@example.com" value="<?= h($old['email']) ?>"/></div>
        </div>
        <div class="form-group"><label>Subject</label><input type="text" name="subject" placeholder="How can we help?" value="<?= h($old['subject']) ?>"/></div>
        <div class="form-group"><label>Message</label><textarea rows="5" name="message" placeholder="Your message…" style="resize:vertical"><?= h($old['message']) ?></textarea></div>
        <button class="btn btn-gold" type="submit">Send Message</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
