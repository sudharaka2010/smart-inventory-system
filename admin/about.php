<?php
// about.php (READ-ONLY PAGE)
// White card theme, responsive grid, company profile, mission, vision, team
// No DB. Includes header/sidebar/footer if present.

declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

/* ------------------- CONFIG ------------------- */
$COMPANY = [
  'name'    => 'RB Stores',
  'tagline' => 'Rainwater Solutions Inventory & Order Management',
  'logo'    => '/rbstorsg/assets/images/rb.png',
  'founded' => '2023',
  'address' => 'Liberty Plaza, 2nd Floor, Kollupitiya, Colombo',
  'site'    => 'https://www.example.com',
  'mission' => 'To provide high-quality construction and rainwater solutions with reliable service and modern technology.',
  'vision'  => 'To be Sri Lanka’s most trusted provider of sustainable hardware and rainwater systems.',
  'values'  => [
    'Integrity & Trust',
    'Customer Focus',
    'Innovation & Technology',
    'Sustainability',
    'Teamwork',
  ],
  'team'    => [
    ['name'=>'Kasun', 'role'=>'Shop Owner'],
    ['name'=>'Nihal', 'role'=>'Driver'],
    ['name'=>'Kaveen','role'=>'Accountant'],
  ],
];

/* ------------------- HELPERS ------------------- */
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (file_exists(__DIR__.'/header.php'))  include __DIR__.'/header.php';
if (file_exists(__DIR__.'/sidebar.php')) include __DIR__.'/sidebar.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>About — <?= h($COMPANY['name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ---- White, business, responsive ---- */
body{background:#f4f6f9;color:#1e293b;margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;padding:14px}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:16px;border:1px solid #e5e7eb}
.card h1{font-size:22px;font-weight:600;padding:16px 18px;border-bottom:1px solid #e5e7eb;margin:0}

.section{padding:14px 18px}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
.col-6{grid-column:span 6}
.col-12{grid-column:span 12}
@media(max-width:992px){.col-6{grid-column:span 12}}

.logo{height:70px;width:auto;border-radius:10px;border:1px solid #e5e7eb;background:#fff;object-fit:contain}
.tagline{color:#64748b;font-size:14px;margin-top:4px}
.hr{height:1px;background:#e5e7eb;margin:12px 0}
.small{color:#64748b;font-size:13px}

.list{margin:6px 0;padding-left:18px}
.list li{margin:6px 0}

.team-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.team-card{background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:14px;text-align:center}
.team-card .avatar{width:64px;height:64px;border-radius:50%;margin-bottom:8px;background:#e5e7eb;display:inline-block}
.team-card b{display:block;font-size:15px;margin-bottom:4px}
</style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1>About Us</h1>

      <div class="section">
        <div style="display:flex;gap:14px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
          <img src="<?= h($COMPANY['logo']) ?>" alt="logo" class="logo">
          <div>
            <div style="font-weight:700;font-size:20px"><?= h($COMPANY['name']) ?></div>
            <div class="tagline"><?= h($COMPANY['tagline']) ?></div>
            <div class="small">Founded in <?= h($COMPANY['founded']) ?> • <?= h($COMPANY['address']) ?></div>
          </div>
        </div>

        <p><?= h($COMPANY['name']) ?> is a modern retail and wholesale provider specializing in construction and rainwater solutions. We aim to simplify inventory and billing management with practical tools while serving our customers with trust and reliability.</p>

        <div class="hr"></div>

        <div class="grid">
          <div class="col-6">
            <h3>Our Mission</h3>
            <p><?= h($COMPANY['mission']) ?></p>
          </div>
          <div class="col-6">
            <h3>Our Vision</h3>
            <p><?= h($COMPANY['vision']) ?></p>
          </div>
        </div>

        <div class="hr"></div>

        <h3>Our Core Values</h3>
        <ul class="list">
          <?php foreach ($COMPANY['values'] as $v): ?>
            <li><?= h($v) ?></li>
          <?php endforeach; ?>
        </ul>

        <div class="hr"></div>

        <h3>Our Team</h3>
        <div class="team-grid">
          <?php foreach ($COMPANY['team'] as $t): ?>
            <div class="team-card">
              <div class="avatar"></div>
              <b><?= h($t['name']) ?></b>
              <span class="small"><?= h($t['role']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="hr"></div>

        <div class="small right">Last updated: <?= h(date('Y-m-d H:i')) ?> • <?= h($COMPANY['name']) ?></div>
      </div>
    </div>
  </div>
</body>
</html>
<?php if (file_exists(__DIR__.'/footer.php')) include __DIR__.'/footer.php'; ?>
