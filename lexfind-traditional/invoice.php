<?php
/**
 * LexFind - Payment invoice / receipt.
 *
 * Renders a clean, standalone HTML invoice for a single paid booking, with a
 * one-click "Download PDF" button. The PDF itself is generated entirely in
 * the browser (via the html2pdf.js library, loaded from a public CDN) - no
 * server-side PDF library is involved, so nothing needs to be vendored or
 * uploaded besides this one file.
 *
 * Reachable at: invoice.php?booking_id=123
 */
require_once __DIR__ . '/includes/auth.php';

$u = requireLogin('client');

$bookingId = (int)($_GET['booking_id'] ?? 0);

$db = getDB();
$stmt = $db->prepare(
    'SELECT b.*,
            au.first_name AS advisor_first, au.last_name AS advisor_last,
            ap.specialty,
            cu.first_name AS client_first, cu.last_name AS client_last,
            cu.email AS client_email, cu.phone AS client_phone, cu.address AS client_address,
            p.id AS payment_id, p.amount AS paid_amount, p.method AS payment_method,
            p.transaction_id, p.created_at AS paid_at
     FROM bookings b
     JOIN advisor_profiles ap ON ap.id = b.advisor_id
     JOIN users au ON au.id = ap.user_id
     JOIN users cu ON cu.id = b.client_id
     LEFT JOIN payments p ON p.booking_id = b.id AND p.status = "success"
     WHERE b.id = ? AND b.client_id = ?
     ORDER BY p.created_at DESC
     LIMIT 1'
);
$stmt->execute([$bookingId, $u['id']]);
$b = $stmt->fetch();

if (!$b || $b['payment_status'] !== 'paid' || !$b['payment_id']) {
    setFlash('error', 'An invoice is only available once a booking has been paid for.');
    header('Location: client/bookings.php');
    exit;
}

$invoiceNo   = 'INV-' . str_pad((string)$b['payment_id'], 6, '0', STR_PAD_LEFT);
$bookingRef  = 'LF-' . str_pad((string)$b['id'], 6, '0', STR_PAD_LEFT);
$clientName  = trim($b['client_first'] . ' ' . $b['client_last']);
$advisorName = 'Adv. ' . trim($b['advisor_first'] . ' ' . $b['advisor_last']);
$paidAt      = $b['paid_at'] ? date('d M Y, h:i A', strtotime($b['paid_at'])) : '-';
$generatedAt = date('d M Y, h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= h($invoiceNo) ?> - LexFind Invoice</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root {
    --gold: #c9a84c;
    --dark: #1c1f27;
    --muted: #6e7280;
    --rule: #e6e6e6;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: #f2f0ea;
    font-family: Helvetica, Arial, sans-serif;
    color: var(--dark);
  }
  .toolbar {
    max-width: 760px;
    margin: 20px auto 0;
    padding: 0 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .toolbar a { color: var(--muted); text-decoration: none; font-size: 0.9rem; }
  .toolbar a:hover { color: var(--dark); }
  #downloadBtn {
    background: var(--dark);
    color: var(--gold);
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
  }
  #downloadBtn:hover { background: #2a2e3a; }
  #downloadBtn:disabled { opacity: 0.6; cursor: default; }

  #invoice {
    max-width: 760px;
    margin: 16px auto 40px;
    background: #ffffff;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    border-radius: 8px;
    overflow: hidden;
  }
  .inv-header {
    background: var(--dark);
    color: #fff;
    padding: 26px 32px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
  }
  .inv-header h1 { margin: 0; color: var(--gold); font-size: 1.6rem; }
  .inv-header .tagline { font-size: 0.82rem; color: #cfcfd4; margin-top: 4px; }
  .inv-header .title { text-align: right; }
  .inv-header .title .big { color: var(--gold); font-weight: 700; font-size: 1.15rem; }
  .inv-header .title .small { font-size: 0.85rem; color: #d8d8dc; margin-top: 4px; }

  .inv-body { padding: 28px 32px 8px; }
  .inv-cols { display: flex; gap: 24px; flex-wrap: wrap; }
  .inv-col { flex: 1; min-width: 220px; }
  .inv-label { text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em; color: var(--muted); margin-bottom: 6px; font-weight: 700; }
  .inv-name { font-weight: 700; font-size: 1rem; margin-bottom: 3px; }
  .inv-line { font-size: 0.87rem; color: #3a3d47; margin-bottom: 2px; }
  .meta-row { display: flex; justify-content: space-between; font-size: 0.87rem; padding: 3px 0; }
  .meta-row .k { color: var(--muted); }
  .meta-row .v { font-weight: 700; }

  hr.rule { border: none; border-top: 1px solid var(--rule); margin: 20px 0; }

  .section-label { text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em; color: var(--muted); font-weight: 700; margin-bottom: 10px; }
  .detail-row { display: flex; font-size: 0.87rem; padding: 4px 0; }
  .detail-row .k { width: 160px; color: var(--muted); }
  .detail-row .v { font-weight: 700; }

  table.charges { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 0.88rem; }
  table.charges thead th {
    background: var(--dark); color: #fff; text-align: left; padding: 10px 14px; font-size: 0.82rem;
  }
  table.charges thead th.amt { text-align: right; }
  table.charges tbody td { padding: 11px 14px; border-bottom: 1px solid var(--rule); }
  table.charges tbody td.amt { text-align: right; }
  table.charges tbody tr.muted td { color: var(--muted); font-weight: 700; border-bottom: none; }
  table.charges tfoot td {
    padding: 13px 14px; background: var(--gold); font-weight: 700; font-size: 1.02rem;
  }
  table.charges tfoot td.amt { text-align: right; }

  .status-paid { color: #148252; font-weight: 700; font-size: 0.85rem; margin: 14px 0 4px; }

  .inv-footer {
    padding: 16px 32px 26px;
    border-top: 1px solid var(--rule);
    font-size: 0.72rem;
    color: var(--muted);
    margin-top: 10px;
  }
  .inv-footer p { margin: 3px 0; }

  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    #invoice { box-shadow: none; margin: 0 auto; }
  }
</style>
</head>
<body>

  <div class="toolbar">
    <a href="client/bookings.php">&larr; Back to My Bookings</a>
    <button id="downloadBtn" type="button">Download PDF</button>
  </div>

  <div id="invoice">
    <div class="inv-header">
      <div>
        <h1>LexFind</h1>
        <div class="tagline">Legal Advisor Finder &amp; Booking Platform</div>
      </div>
      <div class="title">
        <div class="big">PAYMENT INVOICE</div>
        <div class="small"><?= h($invoiceNo) ?></div>
      </div>
    </div>

    <div class="inv-body">
      <div class="inv-cols">
        <div class="inv-col">
          <div class="inv-label">Billed To</div>
          <div class="inv-name"><?= h($clientName) ?></div>
          <div class="inv-line"><?= h($b['client_email']) ?></div>
          <?php if (!empty($b['client_phone'])): ?><div class="inv-line"><?= h($b['client_phone']) ?></div><?php endif; ?>
          <?php if (!empty($b['client_address'])): ?><div class="inv-line"><?= h($b['client_address']) ?></div><?php endif; ?>
        </div>
        <div class="inv-col">
          <div class="inv-label">Invoice Details</div>
          <div class="meta-row"><span class="k">Invoice No.</span><span class="v"><?= h($invoiceNo) ?></span></div>
          <div class="meta-row"><span class="k">Booking Ref.</span><span class="v"><?= h($bookingRef) ?></span></div>
          <div class="meta-row"><span class="k">Payment Date</span><span class="v"><?= h($paidAt) ?></span></div>
          <div class="meta-row"><span class="k">Transaction ID</span><span class="v" style="font-size:0.78rem"><?= h($b['transaction_id']) ?></span></div>
        </div>
      </div>

      <hr class="rule">

      <div class="section-label">Consultation Details</div>
      <div class="detail-row"><div class="k">Advisor</div><div class="v"><?= h($advisorName) ?></div></div>
      <div class="detail-row"><div class="k">Specialty</div><div class="v"><?= h($b['specialty'] ?: '-') ?></div></div>
      <div class="detail-row"><div class="k">Appointment Date</div><div class="v"><?= h(date('d M Y', strtotime($b['booking_date']))) ?></div></div>
      <div class="detail-row"><div class="k">Appointment Time</div><div class="v"><?= h($b['booking_time']) ?></div></div>
      <div class="detail-row"><div class="k">Meeting Type</div><div class="v"><?= h($b['meeting_type']) ?></div></div>

      <table class="charges">
        <thead>
          <tr><th>Description</th><th class="amt">Amount</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>Legal consultation fee (<?= h($b['meeting_type']) ?>)</td>
            <td class="amt"><?= money($b['fee']) ?></td>
          </tr>
          <tr class="muted">
            <td>Payment Method</td>
            <td class="amt"><?= h(ucfirst($b['payment_method'])) ?></td>
          </tr>
        </tbody>
        <tfoot>
          <tr><td>TOTAL PAID</td><td class="amt"><?= money($b['paid_amount'] ?: $b['fee']) ?></td></tr>
        </tfoot>
      </table>

      <div class="status-paid">STATUS: PAID</div>
    </div>

    <div class="inv-footer">
      <p>This is a system-generated invoice from LexFind and does not require a signature.</p>
      <p>Generated on <?= h($generatedAt) ?> - Questions about this payment? Contact support via the LexFind contact page.</p>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    document.getElementById('downloadBtn').addEventListener('click', function () {
      var btn = this;
      var original = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Generating...';

      var element = document.getElementById('invoice');
      var opt = {
        margin: 0,
        filename: '<?= h($invoiceNo) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'pt', format: 'a4', orientation: 'portrait' }
      };

      html2pdf().set(opt).from(element).save().then(function () {
        btn.disabled = false;
        btn.textContent = original;
      }).catch(function () {
        btn.disabled = false;
        btn.textContent = original;
        alert('Could not generate the PDF. Please try again, or use your browser\'s Print > Save as PDF instead.');
      });
    });
  </script>
</body>
</html>
