<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'About';
$activeNav = 'about';
include __DIR__ . '/includes/header.php';
?>
<div class="section">
  <div class="hero-badge" style="margin-bottom:20px">About LexFind</div>
  <div class="section-title" style="font-size:3rem">Bridging Clients<br/>with Justice</div>
  <div class="divider"></div>
  <p style="color:var(--text-muted);font-size:1rem;line-height:1.8;max-width:700px;margin-bottom:48px">
    LexFind was founded in 2026 with a single mission: make quality legal help accessible to every Bangladeshi citizen. We connect clients with verified, experienced legal advisors across all practice areas — with transparent pricing and real reviews.
  </p>
  <div class="steps">
    <div class="step"><div class="step-num"><i class="fa-solid fa-bullseye"></i></div><h3>Our Mission</h3><p>Democratising access to legal expertise across Bangladesh through technology and transparency.</p></div>
    <div class="step"><div class="step-num"><i class="fa-solid fa-eye"></i></div><h3>Our Vision</h3><p>A Bangladesh where no citizen is denied justice due to lack of legal representation or guidance.</p></div>
    <div class="step"><div class="step-num"><i class="fa-solid fa-circle-check"></i></div><h3>Verified Advisors</h3><p>Every advisor is verified against the Bangladesh Bar Council registry before listing.</p></div>
    <div class="step"><div class="step-num"><i class="fa-solid fa-lock"></i></div><h3>Privacy First</h3><p>Your case details are confidential. We never share your information without consent.</p></div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
