<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Privacy Policy';
include __DIR__ . '/includes/header.php';
?>
<div class="section" style="max-width:820px">
  <div class="hero-badge" style="margin-bottom:20px">Legal</div>
  <div class="section-title" style="font-size:2.6rem">Privacy Policy</div>
  <p style="color:var(--text-dim);font-size:0.85rem;margin-bottom:32px">Last updated: <?= date('F Y') ?></p>

  <div style="color:var(--text-muted);line-height:1.8;font-size:0.92rem">
    <h3 style="color:var(--text);margin:28px 0 10px">1. Information We Collect</h3>
    <p>When you create an account, we collect your name, email address, and, for advisors, professional details such as Bar Council registration number, specialty, and fee information. When you book an appointment, we collect the date, time, meeting type, and, where relevant, a WhatsApp number so your advisor can reach you.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">2. How We Use Your Information</h3>
    <p>We use your information to operate the platform: matching clients with advisors, sending booking confirmations and reminders, verifying advisor credentials, and improving our services. We do not sell your personal information to third parties.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">3. Confidentiality</h3>
    <p>Case details you share with an advisor through LexFind are treated as confidential. We do not share the content of your case description with anyone other than the advisor you booked.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">4. Cookies & Sessions</h3>
    <p>We use session cookies solely to keep you logged in. We do not use third-party advertising or tracking cookies.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">5. Your Rights</h3>
    <p>You may request access to, correction of, or deletion of your personal data at any time by contacting us at the email below.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">6. Contact Us</h3>
    <p>Questions about this policy can be sent to <strong>support@lexfind.com.bd</strong>.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>