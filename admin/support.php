<?php
// support.php (READ-ONLY SUPPORT CENTER)
// White card theme, quick actions, channels, FAQ accordion, troubleshooting checklist
// No DB. Includes header/sidebar/footer if present.

declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

/* ------------------- CONFIG: EDIT THESE ------------------- */
$COMPANY = [
  'name'       => 'RB Stores',
  'logo'       => '/rbstorsg/assets/images/rb.png',             // path to your logo
  'site'       => 'https://www.example.com',
  'hours'      => 'Mon–Sat 9:00–18:00',
  'address'    => 'Liberty Plaza, 2nd Floor, Kollupitiya, Colombo',
  'phone'      => '0112345678',                      // local format
  'phone_hr'   => '011-234 5678',                    // human readable
  'mobile'     => '0712345678',                      // for WhatsApp/calls
  'email'      => 'support@rbstores.local',          // primary support
  'alt_email'  => 'info@rbstores.local',             // optional
  'whatsapp'   => '0712345678',                      // same as mobile if you like
  'sla'        => [
    ['P1 - Critical', '4 business hours'],
    ['P2 - High',     '1 business day'],
    ['P3 - Normal',   '2–3 business days'],
  ],
  'remote'     => [
    ['AnyDesk',   'Provide your address on call'],
    ['TeamViewer','Provide your ID & password securely'],
  ],
  // If you have a ticket form, set it here:
  'ticket_url' => 'add_support_ticket.php',          // or '#'
];

/* ------------------- HELPERS ------------------- */
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tel_link(string $num): string { return 'tel:+94'.preg_replace('/\D/','', ltrim($num,'0')); }
function wa_link(string $num): string  { return 'https://wa.me/94'.preg_replace('/\D/','', ltrim($num,'0')); }
function mailto_link(string $email, string $subject='Support Request'): string {
  $s = rawurlencode($subject);
  return "mailto:{$email}?subject={$s}";
}

if (file_exists(__DIR__.'/header.php'))  include __DIR__.'/header.php';
if (file_exists(__DIR__.'/sidebar.php')) include __DIR__.'/sidebar.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Support — <?= h($COMPANY['name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ---- White, business, responsive (aligns with your transport.php) ---- */
body{background:#f4f6f9;color:#1e293b;margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;padding:14px}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:16px;border:1px solid #e5e7eb}
.card h1{font-size:22px;font-weight:600;padding:16px 18px;border-bottom:1px solid #e5e7eb;margin:0}

.toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:10px 16px;flex-wrap:wrap}
.stat{font-size:13px;color:#64748b}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{padding:8px 12px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;text-decoration:none;font-size:13px}
.btn:hover{background:#f1f5f9}
.btn-primary{background:#2563eb;color:#fff;border:none}
.btn-primary:hover{background:#1d4ed8}
.btn-ghost{background:#fff;border:1px solid #e5e7eb}

.section{padding:12px 16px}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
.col-7{grid-column:span 7}
.col-5{grid-column:span 5}
.col-6{grid-column:span 6}
.col-4{grid-column:span 4}
.col-8{grid-column:span 8}
.col-12{grid-column:span 12}
@media(max-width:1100px){.col-7,.col-5,.col-6,.col-8,.col-4{grid-column:span 12}}

.card-sub{background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:12px}
.logo{height:58px;width:auto;border-radius:10px;border:1px solid #e5e7eb;background:#fff;object-fit:contain}

.kv{display:flex;gap:10px;margin:8px 0;align-items:flex-start}
.kv b{min-width:120px;color:#475569}
.kv a{color:#2563eb;text-decoration:none}
.kv a:hover{text-decoration:underline}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;color:#334155;font-size:12px;margin-right:6px}
.hr{height:1px;background:#e5e7eb;margin:12px 0}
.small{color:#64748b;font-size:12px}

.list{margin:6px 0 0 0;padding-left:18px}
.list li{margin:6px 0}

.faq .q{cursor:pointer;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;font-weight:600}
.faq .a{display:none;padding:10px 12px;border-left:3px solid #2563eb;background:#f8fafc;border-radius:8px;margin-top:6px}
.faq .item{margin-bottom:10px}

@media print{
  .actions,.btn,.toolbar{display:none!important}
  .container{margin:0;padding:0}
  .card{box-shadow:none;border:none}
}
</style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1>Support Center</h1>

      <div class="toolbar">
        <div class="stat">
          <img src="<?= h($COMPANY['logo']) ?>" alt="<?= h($COMPANY['name']) ?> logo" class="logo" style="vertical-align:middle;margin-right:8px">
          <strong><?= h($COMPANY['name']) ?></strong> • Hours: <?= h($COMPANY['hours']) ?>
        </div>
        <div class="actions">
          <a class="btn btn-primary" href="<?= h($COMPANY['ticket_url']) ?>">Create Ticket</a>
          <a class="btn" href="<?= h(mailto_link($COMPANY['email'])) ?>">Email Support</a>
          <a class="btn" href="<?= h(tel_link($COMPANY['phone'])) ?>">Call Support</a>
          <a class="btn" href="<?= h(wa_link($COMPANY['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp</a>
          <button class="btn" onclick="window.print()">Print</button>
        </div>
      </div>

      <div class="section">
        <div class="grid">
          <!-- Channels -->
          <div class="col-7">
            <div class="card-sub">
              <div style="display:flex;gap:12px;align-items:center;margin-bottom:6px">
                <img src="<?= h($COMPANY['logo']) ?>" class="logo" alt="logo">
                <div>
                  <div style="font-weight:700">How to reach us</div>
                  <div class="small">Fastest response: call or WhatsApp during business hours.</div>
                </div>
              </div>
              <div class="hr"></div>

              <div class="kv">
                <b>Phone</b>
                <div>
                  <a href="<?= h(tel_link($COMPANY['phone'])) ?>"><?= h($COMPANY['phone_hr']) ?></a>
                  <div class="small">Mon–Sat <?= h($COMPANY['hours']) ?></div>
                </div>
              </div>

              <div class="kv">
                <b>WhatsApp</b>
                <div><a href="<?= h(wa_link($COMPANY['whatsapp'])) ?>" target="_blank" rel="noopener"><?= h($COMPANY['whatsapp']) ?></a></div>
              </div>

              <div class="kv">
                <b>Email</b>
                <div>
                  <a href="<?= h(mailto_link($COMPANY['email'])) ?>"><?= h($COMPANY['email']) ?></a>
                  <?php if (!empty($COMPANY['alt_email'])): ?>
                    <div class="small">Alt: <a href="<?= h(mailto_link($COMPANY['alt_email'])) ?>"><?= h($COMPANY['alt_email']) ?></a></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="kv">
                <b>Location</b>
                <div><?= h($COMPANY['address']) ?></div>
              </div>

              <div class="kv">
                <b>SLA</b>
                <div>
                  <?php foreach ($COMPANY['sla'] as $s): ?>
                    <span class="badge"><?= h($s[0]) ?>: <?= h($s[1]) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="kv">
                <b>Remote</b>
                <div>
                  <?php foreach ($COMPANY['remote'] as $r): ?>
                    <div class="small">• <?= h($r[0]) ?> — <?= h($r[1]) ?></div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="hr"></div>

              <div class="small">Tip: include screenshots, invoice IDs, and steps to reproduce issues for faster resolution.</div>
            </div>
          </div>

          <!-- Quick help / FAQ -->
          <div class="col-5">
            <div class="card-sub">
              <div style="font-weight:700;margin-bottom:6px">Quick Help (FAQ)</div>
              <div class="faq" id="faq">
                <div class="item">
                  <div class="q">1) I can’t log in</div>
                  <div class="a">
                    • Check your username/password (case-sensitive).<br>
                    • If you forgot the password, contact support to reset.<br>
                    • Ensure your role (Admin/Staff) is active in the system.
                  </div>
                </div>
                <div class="item">
                  <div class="q">2) Stock/return numbers look wrong</div>
                  <div class="a">
                    • Review recent returns in <b>Returns</b> → <i>View</i> for deletions/edits.<br>
                    • Confirm no parallel edits were happening; returns adjust stock instantly.<br>
                    • Provide the <b>ItemID</b> & <b>InvoiceID</b> when you contact us.
                  </div>
                </div>
                <div class="item">
                  <div class="q">3) Can I export reports?</div>
                  <div class="a">
                    • Yes — most lists (transport, feedback, returns) have an <b>Export CSV</b> button.<br>
                    • Open CSV in Excel/Google Sheets for further analysis.
                  </div>
                </div>
                <div class="item">
                  <div class="q">4) Browser troubleshooting</div>
                  <div class="a">
                    • Use latest Chrome/Edge/Firefox.<br>
                    • Clear cache (Ctrl+Shift+R) and try again.<br>
                    • If the issue persists, send us a screenshot and the page URL.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Troubleshooting checklist -->
          <div class="col-12">
            <div class="card-sub">
              <div style="font-weight:700;margin-bottom:6px">Before you create a ticket</div>
              <ul class="list">
                <li>Describe the problem and the exact steps to reproduce it.</li>
                <li>Note the page name (e.g., <i>add_return.php</i>) and any IDs involved (InvoiceID/ItemID).</li>
                <li>Attach screenshots, error messages, and the time it happened.</li>
                <li>Tell us if this blocks billing, inventory, or transport workflows.</li>
              </ul>
              <div class="actions" style="margin-top:8px">
                <a class="btn btn-primary" href="<?= h($COMPANY['ticket_url']) ?>">Create Ticket</a>
                <a class="btn" href="<?= h(mailto_link($COMPANY['email'], 'New Support Ticket')) ?>">Email Ticket</a>
              </div>
            </div>
          </div>

          <div class="col-12" style="text-align:right">
            <span class="small">Last updated: <?= h(date('Y-m-d H:i')) ?> • <?= h($COMPANY['name']) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
// FAQ accordion
document.querySelectorAll('#faq .q').forEach(q => {
  q.addEventListener('click', () => {
    const a = q.nextElementSibling;
    const open = a.style.display === 'block';
    document.querySelectorAll('#faq .a').forEach(el => el.style.display = 'none');
    a.style.display = open ? 'none' : 'block';
  });
});
</script>

</body>
</html>
<?php if (file_exists(__DIR__.'/footer.php')) include __DIR__.'/footer.php'; ?>
