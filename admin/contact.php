<?php
// contact_info.php (READ-ONLY VIEW)
// White card theme, clean layout; click-to-call/email/WhatsApp; embedded map
// No DB, no forms. Includes header/sidebar/footer if present.

declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

/* -------------------------------------------
   CONFIG: Update these to your real details
------------------------------------------- */
$COMPANY = [
  'name'    => 'RB Stores',
  'tagline' => 'Construction & Hardware Supplies',
  'logo'    => '/rbstorsg/assets/images/rb.png',               // path to your logo
  'site'    => 'https://www.example.com',           // company website
  'phone'   => '0112345678',                        // primary phone (local)
  'phone_hr'=> '011-234 5678',                      // human-readable format
  'mobile'  => '0712345678',                        // mobile for tel/WhatsApp
  'email'   => 'info@rbstores.local',
  'support' => 'support@rbstores.local',
  'hours'   => 'Mon–Sat 9:00–18:00',
  'address' => 'Liberty Plaza, 2nd Floor, Kollupitiya, Colombo',
  // Google Maps search query
  'maps_q'  => 'Liberty Plaza Kollupitiya Colombo',
];

// Developer / implementer details (optional)
$DEV = [
  'name'   => 'Code Counters',
  'role'   => 'Software Developer',
  'email'  => 'dev@example.com',
  'phone'  => '0771234567',
  'site'   => 'https://portfolio.example.com',
];

/* Handy builders */
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tel_link(string $num): string { return 'tel:+94'.preg_replace('/\D/','', ltrim($num,'0')); } // +94XXXX
function wa_link(string $num): string { return 'https://wa.me/94'.preg_replace('/\D/','', ltrim($num,'0')); }
function map_embed_src(string $q): string {
  $q = urlencode($q);
  return "https://www.google.com/maps?q={$q}&output=embed";
}
function map_dir_link(string $q): string {
  $q = urlencode($q);
  return "https://www.google.com/maps/search/?api=1&query={$q}";
}

if (file_exists(__DIR__.'/header.php')) include __DIR__.'/header.php';
if (file_exists(__DIR__.'/sidebar.php')) include __DIR__.'/sidebar.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Contact — <?= h($COMPANY['name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ---- White, business, responsive (matches your transport.php vibe) ---- */
body{background:#f4f6f9;color:#1e293b;margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;padding:14px}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:16px;border:1px solid #e5e7eb}
.card h1{font-size:22px;font-weight:600;padding:16px 18px;border-bottom:1px solid #e5e7eb;margin:0}

.section{padding:14px 18px}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
.col-7{grid-column:span 7}
.col-5{grid-column:span 5}
.col-6{grid-column:span 6}
.col-4{grid-column:span 4}
.col-8{grid-column:span 8}
.col-12{grid-column:span 12}
@media(max-width:1100px){.col-7,.col-5,.col-6,.col-8,.col-4{grid-column:span 12}}

.logo-wrap{display:flex;align-items:center;gap:14px}
.logo{height:58px;width:auto;border-radius:10px;border:1px solid #e5e7eb;background:#fff;object-fit:contain}
.title{font-weight:700;font-size:20px}
.tagline{color:#64748b;font-size:13px;margin-top:4px}

.kv{display:flex;align-items:flex-start;gap:10px;margin:8px 0}
.kv b{min-width:120px;color:#475569}
.kv a{color:#2563eb;text-decoration:none;word-break:break-word}
.kv a:hover{text-decoration:underline}

.badge{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;color:#334155;font-size:12px;margin-right:6px}
.btnrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.btn{padding:8px 12px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-size:13px;text-decoration:none;cursor:pointer}
.btn:hover{background:#f1f5f9}
.btn-primary{background:#2563eb;color:#fff;border:none}
.btn-primary:hover{background:#1d4ed8}

.card-sub{background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:12px}
.map{width:100%;height:320px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.hr{height:1px;background:#e5e7eb;margin:12px 0}
.small{color:#64748b;font-size:12px}
.right{text-align:right}
.center{text-align:center}
</style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1>Contact</h1>

      <div class="section">
        <div class="grid">
          <!-- Company & quick actions -->
          <div class="col-7">
            <div class="card-sub">
              <div class="logo-wrap">
                <a href="<?= h($COMPANY['site']) ?>" target="_blank" rel="noopener">
                  <img class="logo" src="<?= h($COMPANY['logo']) ?>" alt="<?= h($COMPANY['name']) ?> logo">
                </a>
                <div>
                  <div class="title"><?= h($COMPANY['name']) ?></div>
                  <div class="tagline"><?= h($COMPANY['tagline']) ?></div>
                  <div style="margin-top:6px">
                    <span class="badge">Today: <?= h(date('Y-m-d')) ?></span>
                    <span class="badge">Hours: <?= h($COMPANY['hours']) ?></span>
                  </div>
                </div>
              </div>

              <div class="hr"></div>

              <div class="kv">
                <b>Phone</b>
                <div>
                  <a href="<?= h(tel_link($COMPANY['phone'])) ?>"><?= h($COMPANY['phone_hr']) ?></a>
                  <?php if (!empty($COMPANY['mobile'])): ?>
                    <div class="small">Mobile: <a href="<?= h(tel_link($COMPANY['mobile'])) ?>"><?= h($COMPANY['mobile']) ?></a> · <a href="<?= h(wa_link($COMPANY['mobile'])) ?>" target="_blank" rel="noopener">WhatsApp</a></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="kv">
                <b>Email</b>
                <div><a href="mailto:<?= h($COMPANY['email']) ?>"><?= h($COMPANY['email']) ?></a><?php if (!empty($COMPANY['support'])): ?> · <a href="mailto:<?= h($COMPANY['support']) ?>">Support</a><?php endif; ?></div>
              </div>

              <div class="kv">
                <b>Address</b>
                <div><?= h($COMPANY['address']) ?></div>
              </div>

              <div class="btnrow">
                <a class="btn btn-primary" href="<?= h($COMPANY['site']) ?>" target="_blank" rel="noopener">Visit Website</a>
                <a class="btn" href="<?= h(map_dir_link($COMPANY['maps_q'])) ?>" target="_blank" rel="noopener">Get Directions</a>
                <a class="btn" href="<?= h(tel_link($COMPANY['phone'])) ?>">Call Now</a>
                <?php if (!empty($COMPANY['mobile'])): ?>
                  <a class="btn" href="<?= h(wa_link($COMPANY['mobile'])) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
                <?php endif; ?>
                <a class="btn" href="mailto:<?= h($COMPANY['email']) ?>">Email Us</a>
              </div>
            </div>
          </div>

          <!-- Map -->
          <div class="col-5">
            <div class="card-sub">
              <div class="center" style="margin-bottom:8px"><b>Location Map</b></div>
              <div class="map">
                <iframe
                  src="<?= h(map_embed_src($COMPANY['maps_q'])) ?>"
                  width="100%" height="100%" style="border:0" loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade">
                </iframe>
              </div>
              <div class="small" style="margin-top:8px">Tip: use “Get Directions” for Google Maps app.</div>
            </div>
          </div>

          <!-- Developer details -->
          <div class="col-6">
            <div class="card-sub">
              <div><b>Developer / Maintainer</b></div>
              <div class="kv"><b>Name</b> <div><?= h($DEV['name']) ?> <span class="small">— <?= h($DEV['role']) ?></span></div></div>
              <div class="kv"><b>Email</b> <div><a href="mailto:<?= h($DEV['email']) ?>"><?= h($DEV['email']) ?></a></div></div>
              <div class="kv"><b>Phone</b> <div><a href="<?= h(tel_link($DEV['phone'])) ?>"><?= h($DEV['phone']) ?></a> · <a href="<?= h(wa_link($DEV['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a></div></div>
              <div class="kv"><b>Website</b> <div><a href="<?= h($DEV['site']) ?>" target="_blank" rel="noopener"><?= h($DEV['site']) ?></a></div></div>
            </div>
          </div>

          <!-- Quick links / socials (optional) -->
          <div class="col-6">
            <div class="card-sub">
              <div><b>Quick Links</b></div>
              <div class="kv"><b>Company</b> <div><a href="<?= h($COMPANY['site']) ?>" target="_blank" rel="noopener"><?= h($COMPANY['site']) ?></a></div></div>
              <div class="kv"><b>Email</b> <div><a href="mailto:<?= h($COMPANY['email']) ?>"><?= h($COMPANY['email']) ?></a></div></div>
              <div class="kv"><b>Support</b> <div><a href="mailto:<?= h($COMPANY['support']) ?>"><?= h($COMPANY['support']) ?></a></div></div>
              <div class="kv"><b>Call</b> <div><a href="<?= h(tel_link($COMPANY['phone'])) ?>"><?= h($COMPANY['phone_hr']) ?></a></div></div>
              <?php if (!empty($COMPANY['mobile'])): ?>
              <div class="kv"><b>WhatsApp</b> <div><a href="<?= h(wa_link($COMPANY['mobile'])) ?>" target="_blank" rel="noopener"><?= h($COMPANY['mobile']) ?></a></div></div>
              <?php endif; ?>
              <div class="small">All links are click-to-open on mobile (tel:, mailto:, WhatsApp).</div>
            </div>
          </div>

          <div class="col-12 right small">
            Last updated: <?= h(date('Y-m-d H:i')) ?> • <?= h($COMPANY['name']) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
<?php if (file_exists(__DIR__.'/footer.php')) include __DIR__.'/footer.php'; ?>
