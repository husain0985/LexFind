<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'FAQ';
$activeNav = 'faq';

$faqs = [
  ["How do I find a legal advisor on LexFind?", "Simply use the Search Advisors page, filter by specialty, location, and budget, then browse profiles and book directly."],
  ["Are all advisors verified?", "Yes. Every advisor must provide their Bar Council registration number and supporting documents which are reviewed by our admin team before approval."],
  ["How much does a consultation cost?", "Fees vary by advisor and are clearly displayed on each profile. Our advisors typically charge ৳1,500–৳6,000 per hour."],
  ["Can I cancel or reschedule a booking?", "Clients cannot cancel a booking directly from the dashboard. If you need to cancel or reschedule, please contact your advisor directly using the WhatsApp number or email shown on their profile, and they can adjust or cancel the appointment on their end."],
  ["Is my case information kept confidential?", "Absolutely. All communications and case details are protected under attorney-client privilege. We never share your information without explicit consent."],
  ["How do I register as a legal advisor?", "Sign up with the 'Advisor' role, complete your profile, and our admin team will review and approve your account."],
  ["What meeting formats are available?", "You can choose Video Call, Phone Call, or In-Person meetings based on advisor availability and your preference."],
];

include __DIR__ . '/includes/header.php';
?>
<div class="section" style="max-width:760px">
  <div class="section-title">Frequently Asked Questions</div>
  <div class="divider"></div>
  <?php foreach ($faqs as $f): ?>
    <details class="faq-item">
      <summary class="faq-q" style="list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center">
        <span><?= h($f[0]) ?></span>
        <span style="color:var(--gold);font-size:1.1rem">+</span>
      </summary>
      <div class="faq-a" style="display:block"><?= h($f[1]) ?></div>
    </details>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
