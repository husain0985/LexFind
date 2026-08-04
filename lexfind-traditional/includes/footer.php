<?php $base = basePath(); ?>
<footer>
  <div class="footer-grid">
    <div class="footer-col">
      <div class="logo">LexFind</div>
      <p>Connecting clients with trusted, verified legal advisors across Bangladesh — transparent pricing, real reviews.</p>
      <div class="footer-social">
        <a href="#" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="#" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
        <a href="#" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
        <a href="https://wa.me/8801XXXXXXXXX" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
      </div>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <a href="<?= $base ?>index.php">Home</a>
      <a href="<?= $base ?>search.php">Search Advisors</a>
      <a href="<?= $base ?>about.php">About</a>
      <a href="<?= $base ?>faq.php">FAQ</a>
      <a href="<?= $base ?>contact.php">Contact</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <a href="<?= $base ?>privacy-policy.php">Privacy Policy</a>
      <a href="<?= $base ?>terms.php">Terms of Service</a>
    </div>
    <div class="footer-col">
      <h4>Get in Touch</h4>
      <p><i class="fa-solid fa-location-dot"></i> Kotbari, Comilla University, Cumilla 3500</p>
      <p><i class="fa-solid fa-envelope"></i> support@lexfind.com.bd</p>
      <p><i class="fa-solid fa-phone"></i> +880 2 9999 0000</p>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> LexFind. All rights reserved.</p>
  </div>
</footer>
<?php require __DIR__ . '/ai-widget.php'; ?>
</body>
</html>
