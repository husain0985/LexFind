<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Terms of Service';
include __DIR__ . '/includes/header.php';
?>
<div class="section" style="max-width:820px">
  <div class="hero-badge" style="margin-bottom:20px">Legal</div>
  <div class="section-title" style="font-size:2.6rem">Terms of Service</div>
  <p style="color:var(--text-dim);font-size:0.85rem;margin-bottom:32px">Last updated: <?= date('F Y') ?></p>

  <div style="color:var(--text-muted);line-height:1.8;font-size:0.92rem">
    <h3 style="color:var(--text);margin:28px 0 10px">1. Acceptance of Terms</h3>
    <p>By creating an account or using LexFind, you agree to these Terms of Service.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">2. Nature of the Platform</h3>
    <p>LexFind is a directory and booking platform that connects clients with independent legal advisors. LexFind is not a law firm, does not provide legal advice itself, and is not a party to any advisor-client relationship formed through the platform.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">3. Advisor Verification</h3>
    <p>Advisors must submit a valid Bar Council registration and supporting documents, reviewed by our admin team before approval. LexFind does not guarantee the outcome of any legal matter handled by an advisor.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">4. Bookings & Fees</h3>
    <p>Fees are set individually by each advisor and displayed on their profile before booking. Once an advisor confirms a booking, the client is expected to attend at the scheduled time or contact the advisor directly to reschedule.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">5. Account Responsibilities</h3>
    <p>You are responsible for maintaining the confidentiality of your login credentials and for all activity under your account.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">6. Limitation of Liability</h3>
    <p>LexFind is not liable for the quality, accuracy, or outcome of legal advice provided by advisors listed on the platform.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">7. Changes to These Terms</h3>
    <p>We may update these Terms from time to time. Continued use of LexFind after changes constitutes acceptance of the revised Terms.</p>

    <h3 style="color:var(--text);margin:28px 0 10px">8. Contact Us</h3>
    <p>Questions about these Terms can be sent to <strong>support@lexfind.com.bd</strong>.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>