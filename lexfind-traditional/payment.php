<?php
require_once __DIR__ . '/includes/auth.php';
$u = requireLogin('client');
$pageTitle = 'Payment';

$bookingId = (int)($_GET['booking_id'] ?? 0);

$db = getDB();
$stmt = $db->prepare(
    'SELECT b.*, au.first_name af, au.last_name al, ap.specialty,
            TIMESTAMPDIFF(SECOND, b.created_at, NOW()) AS elapsed_seconds
     FROM bookings b JOIN advisor_profiles ap ON ap.id = b.advisor_id JOIN users au ON au.id = ap.user_id
     WHERE b.id = ? AND b.client_id = ?'
);
$stmt->execute([$bookingId, $u['id']]);
$b = $stmt->fetch();

if (!$b) {
    setFlash('error', 'Booking not found.');
    header('Location: client/bookings.php'); exit;
}
if ($b['payment_status'] === 'paid') {
    setFlash('success', 'This booking is already paid for.');
    header('Location: client/bookings.php'); exit;
}
if ($b['status'] !== 'pending' || $b['payment_status'] !== 'unpaid') {
    setFlash('error', 'This booking is no longer awaiting payment.');
    header('Location: client/bookings.php'); exit;
}
// Elapsed time is measured entirely on MySQL's own clock (TIMESTAMPDIFF above),
// so this is immune to any timezone difference between the PHP host and the
// database host — it no longer trusts PHP's time() to agree with MySQL's.
$elapsedSeconds = (int)$b['elapsed_seconds'];
$secondsLeft = max(0, 15 * 60 - $elapsedSeconds);
if ($elapsedSeconds > 15 * 60) {
    setFlash('error', 'This booking hold has expired. Please book the slot again.');
    header('Location: advisor.php?id=' . (int)$b['advisor_id']); exit;
}
$payErrors = $_SESSION['pay_errors'] ?? null; unset($_SESSION['pay_errors']);

include __DIR__ . '/includes/header.php';
?>
<style>
  .ssl-page { min-height:calc(100vh - 64px); background:#f2f4f6; padding:36px 16px; display:flex; justify-content:center; }
  .ssl-wrap { width:100%; max-width:480px; }
  .ssl-topbar { display:flex; align-items:center; justify-content:space-between; background:#0b3d3a; color:#fff; padding:10px 16px; border-radius:8px 8px 0 0; font-size:0.78rem; }
  .ssl-topbar .ssl-secure { display:flex; align-items:center; gap:6px; font-weight:600; }
  .ssl-topbar .ssl-timer { font-variant-numeric:tabular-nums; opacity:0.9; }
  .ssl-topbar .ssl-timer.ssl-timer-low { color:#ffb4b4; }
  .ssl-card { background:#fff; border-radius:0 0 10px 10px; box-shadow:0 6px 24px rgba(0,0,0,0.08); overflow:hidden; }
  .ssl-merchant-row { display:flex; align-items:center; justify-content:space-between; padding:18px 20px 4px; }
  .ssl-merchant-name { font-weight:700; font-size:1rem; color:#0b3d3a; letter-spacing:0.2px; }
  .ssl-amount { font-weight:700; font-size:1.3rem; color:#0b3d3a; }
  .ssl-subrow { padding:0 20px 14px; font-size:0.76rem; color:#7a8a88; border-bottom:1px solid #edf1f0; }
  .ssl-brandbar { display:flex; align-items:center; gap:8px; padding:10px 20px; font-size:0.72rem; color:#8b9c9a; background:#f7faf9; }
  .ssl-brandbar b { color:#0b3d3a; }
  .ssl-tabs { display:flex; border-bottom:1px solid #edf1f0; }
  .ssl-tab { flex:1; padding:12px 8px; text-align:center; font-size:0.82rem; font-weight:600; background:none; border:none; cursor:pointer; color:#8b9c9a; border-bottom:3px solid transparent; }
  .ssl-tab.active { color:#0b3d3a; border-bottom-color:#0b8f66; }
  .ssl-panel { padding:18px 20px 20px; }
  .ssl-method-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-bottom:16px; }
  .ssl-method-btn { position:relative; display:flex; flex-direction:column; align-items:center; gap:6px; padding:10px 4px; border:1.5px solid #e3e8e7; border-radius:8px; cursor:pointer; transition:border-color .15s, box-shadow .15s; }
  .ssl-method-btn input { position:absolute; opacity:0; }
  .ssl-method-btn.selected { border-color:var(--brand); box-shadow:0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent); }
  .ssl-method-badge { width:100%; padding:7px 0; border-radius:5px; background:var(--brand); color:#fff; font-weight:700; font-size:0.7rem; text-align:center; letter-spacing:0.2px; }
  .ssl-card-badges { display:flex; gap:8px; margin-bottom:16px; }
  .ssl-card-badges span { padding:6px 12px; border-radius:5px; font-size:0.68rem; font-weight:700; color:#fff; letter-spacing:0.3px; }
  .ssl-card-badges span:nth-child(1) { background:#1a1f71; }
  .ssl-card-badges span:nth-child(2) { background:#eb001b; }
  .ssl-card-badges span:nth-child(3) { background:#006fcf; }
  .ssl-wallet-note { font-size:0.76rem; color:#7a8a88; margin-bottom:14px; display:flex; align-items:center; gap:6px; }
  .ssl-pay-btn { width:100%; padding:13px; border:none; border-radius:7px; background:#0b8f66; color:#fff; font-weight:700; font-size:0.92rem; cursor:pointer; margin-top:6px; }
  .ssl-pay-btn:disabled { background:#a9c9c1; cursor:not-allowed; }
  .ssl-pay-btn:not(:disabled):hover { background:#0a7a57; }
  .ssl-footer-note { padding:12px 20px 18px; font-size:0.72rem; color:#8b9c9a; display:flex; gap:6px; align-items:flex-start; border-top:1px solid #edf1f0; }
  .ssl-back { display:block; text-align:center; margin-top:14px; font-size:0.8rem; color:#5c6b69; text-decoration:none; }
  .ssl-error { background:#fff2f2; color:#c0392b; border:1px solid #f5c6c6; padding:10px 14px; border-radius:6px; font-size:0.8rem; margin:0 20px 14px; }
  /* override the site's dark-theme form styling inside this light checkout card */
  .ssl-panel .form-group label { color:#5c6b69; }
  .ssl-panel .form-group input { background:#f7faf9; border:1px solid #dbe4e2; color:#0b3d3a; }
  .ssl-panel .form-group input:focus { border-color:#0b8f66; }
</style>
<div class="ssl-page">
  <div class="ssl-wrap">
    <div class="ssl-topbar">
      <span class="ssl-secure"><i class="fa-solid fa-lock"></i> Secure Checkout</span>
      <span class="ssl-timer" id="sslTimer">15:00</span>
    </div>
    <div class="ssl-card">
      <div class="ssl-merchant-row">
        <div class="ssl-merchant-name">LexFind</div>
        <div class="ssl-amount"><?= money($b['fee']) ?></div>
      </div>
      <div class="ssl-subrow">Order #LF-<?= str_pad((string)$b['id'], 6, '0', STR_PAD_LEFT) ?> · Consultation with Adv. <?= h($b['af'] . ' ' . $b['al']) ?> · <?= h($b['booking_date']) ?> <?= h($b['booking_time']) ?></div>
      <div class="ssl-brandbar"><i class="fa-solid fa-shield-halved"></i> Payment processed via <b>SSLCommerz-style Checkout</b> (demo)</div>

      <?php if ($payErrors): ?>
        <div class="ssl-error"><?= h($payErrors) ?></div>
      <?php endif; ?>

      <div class="ssl-tabs">
        <button type="button" class="ssl-tab active" data-tab="mobile">Mobile Banking</button>
        <button type="button" class="ssl-tab" data-tab="cards">Cards</button>
      </div>

      <form method="post" action="actions/process-payment.php" id="sslForm">
        <?= csrfField() ?>
        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>"/>
        <input type="hidden" name="payment_method" id="sslPaymentMethod" value="mobile"/>

        <div class="ssl-panel" data-panel="mobile">
          <div class="ssl-method-grid">
            <label class="ssl-method-btn" data-provider="bKash" style="--brand:#E2136E">
              <input type="radio" name="wallet_provider" value="bKash"/>
              <span class="ssl-method-badge">bKash</span>
            </label>
            <label class="ssl-method-btn" data-provider="Nagad" style="--brand:#F6921E">
              <input type="radio" name="wallet_provider" value="Nagad"/>
              <span class="ssl-method-badge">Nagad</span>
            </label>
            <label class="ssl-method-btn" data-provider="Rocket" style="--brand:#8C3494">
              <input type="radio" name="wallet_provider" value="Rocket"/>
              <span class="ssl-method-badge">Rocket</span>
            </label>
            <label class="ssl-method-btn" data-provider="Upay" style="--brand:#00A99D">
              <input type="radio" name="wallet_provider" value="Upay"/>
              <span class="ssl-method-badge">Upay</span>
            </label>
          </div>
          <div id="sslWalletForm" hidden>
            <div class="ssl-wallet-note"><i class="fa-solid fa-mobile-screen"></i> <span id="sslWalletLabel">Enter your wallet number and PIN</span></div>
            <div class="form-group"><label>Mobile Number</label><input type="text" name="wallet_number" placeholder="01XXXXXXXXX" maxlength="11" inputmode="numeric"/></div>
            <div class="form-group"><label>PIN</label><input type="password" name="wallet_pin" placeholder="Enter PIN" maxlength="5"/></div>
          </div>
          <button class="ssl-pay-btn" type="submit" id="sslPayBtnMobile" disabled>Select a payment method</button>
        </div>

        <div class="ssl-panel" data-panel="cards" hidden>
          <div class="ssl-card-badges"><span>VISA</span><span>MASTERCARD</span><span>AMEX</span></div>
          <div class="form-group"><label>Cardholder Name</label><input type="text" name="card_name" placeholder="Name on card" value="<?= h($u['first_name'] . ' ' . $u['last_name']) ?>"/></div>
          <div class="form-group"><label>Card Number</label><input type="text" name="card_number" placeholder="4242 4242 4242 4242" maxlength="19"/></div>
          <div class="form-row">
            <div class="form-group"><label>Expiry</label><input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5"/></div>
            <div class="form-group"><label>CVV</label><input type="text" name="card_cvv" placeholder="123" maxlength="4"/></div>
          </div>
          <button class="ssl-pay-btn" type="submit" id="sslPayBtnCard">Pay <?= money($b['fee']) ?></button>
        </div>
      </form>
      <div class="ssl-footer-note"><i class="fa-solid fa-circle-info"></i> This checkout is styled after SSLCommerz for interface testing only — no real gateway is connected and no money is charged.</div>
    </div>
    <a class="ssl-back" href="advisor.php?id=<?= (int)$b['advisor_id'] ?>">Cancel and go back</a>
  </div>
</div>
<script>
(function () {
  var tabs = document.querySelectorAll('.ssl-tab');
  var panels = document.querySelectorAll('.ssl-panel');
  var methodInput = document.getElementById('sslPaymentMethod');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabs.forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      var target = tab.dataset.tab;
      panels.forEach(function (p) { p.hidden = p.dataset.panel !== target; });
      methodInput.value = target;
    });
  });

  var providerBtns = document.querySelectorAll('.ssl-method-btn');
  var walletForm = document.getElementById('sslWalletForm');
  var walletLabel = document.getElementById('sslWalletLabel');
  var payBtnMobile = document.getElementById('sslPayBtnMobile');
  providerBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      providerBtns.forEach(function (b) { b.classList.remove('selected'); });
      btn.classList.add('selected');
      btn.querySelector('input').checked = true;
      walletForm.hidden = false;
      var provider = btn.dataset.provider;
      walletLabel.textContent = 'Enter your ' + provider + ' number and PIN';
      payBtnMobile.disabled = false;
      payBtnMobile.textContent = 'Pay <?= money($b['fee']) ?> with ' + provider;
    });
  });

  // session countdown, mirrors the 15-minute server-side hold
  var secondsLeft = <?= (int)$secondsLeft ?>;
  var timerEl = document.getElementById('sslTimer');
  function tick() {
    if (secondsLeft <= 0) {
      timerEl.textContent = '00:00';
      timerEl.classList.add('ssl-timer-low');
      return;
    }
    var m = Math.floor(secondsLeft / 60);
    var s = secondsLeft % 60;
    timerEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    if (secondsLeft <= 60) timerEl.classList.add('ssl-timer-low');
    secondsLeft--;
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>