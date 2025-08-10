<?php
// =================== RB Stores — Admin Dashboard (mysqli) ===================
// File: /admin/index.php

// ---------- ENV ----------
$IS_DEV = false;
date_default_timezone_set('Asia/Colombo');

// ---------- Errors ----------
if ($IS_DEV) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// ---------- Session + Auth ----------
// Extra-tough session defaults (before session_start)
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
session_name('RBSTORESSESSID');
session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax', // consider 'Strict' if all flows are same-site
  'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Periodic session ID rotation (mitigate fixation)
if (!isset($_SESSION['regen_at']) || time() - ($_SESSION['regen_at'] ?? 0) > 600) {
    session_regenerate_id(true);
    $_SESSION['regen_at'] = time();
}

// Simple role gate (extend as needed)
function requireRole(string|array $roles) {
    $roles = (array)$roles;
    $ok = isset($_SESSION['username']) && in_array($_SESSION['role'] ?? '', $roles, true);
    if (!$ok) {
        header("Location: /auth/dashboard.php");
        exit();
    }
}
requireRole(['Admin']); // Only admins here

// ---------- Security headers ----------
// Avoid caching sensitive dashboard data
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$cspNonce = base64_encode(random_bytes(16));

// Remove any pre-set CSP to avoid duplicates (e.g., from server config)
if (function_exists('header_remove')) {
    header_remove('Content-Security-Policy');
}

header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Frame-Options: SAMEORIGIN"); // legacy; CSP frame-ancestors is primary
header("X-XSS-Protection: 0"); // rely on CSP
// Least-privilege Permissions-Policy
header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), accelerometer=(), gyroscope=(), magnetometer=()");

if (!$IS_DEV) {
    // ONE coherent CSP
    $csp = implode(' ', [
        "default-src 'self';",
        "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'nonce-{$cspNonce}';",
        "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline';",
        "style-src-elem 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline';",
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:;",
        "img-src 'self' https: data: blob:;",
        "connect-src 'self';",
        "frame-ancestors 'self';",
        "base-uri 'self';",
        "form-action 'self';",
        "object-src 'none';",
        "worker-src 'self' blob:;",
        "upgrade-insecure-requests;",
    ]);
    // Optional: add reporting if you have an endpoint
    // header(\"Report-To: {\\\"group\\\":\\\"csp-endpoint\\\",\\\"max_age\\\":10886400,\\\"endpoints\\\":[{\\\"url\\\":\\\"https://your-collector.example/csp\\\"}]} \");
    // $csp .= ' report-to csp-endpoint;';
    header("Content-Security-Policy: $csp");

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        // Once stable on HTTPS, you can raise to 2 years
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }

    header("Cross-Origin-Opener-Policy: same-origin");
    header("Cross-Origin-Resource-Policy: same-site");
}

// ---------- CSRF helpers (use on POST forms in other admin pages) ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(?string $t): bool {
    return hash_equals($_SESSION['csrf'] ?? '', (string)$t);
}

// ---------- DB ----------
require_once(__DIR__ . '/../includes/db_connect.php');
if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    echo "<h1>Database not connected.</h1>";
    exit();
}
$conn->set_charset('utf8mb4');

// ---------- Helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, '.', ','); }
function d($s){ return $s ? date('Y-m-d', strtotime((string)$s)) : '—'; }

function getSafeValue(mysqli $conn, string $query, string $field) {
    // NOTE: Use only with static queries. NEVER pass user input here.
    $res = $conn->query($query);
    if ($res && ($row = $res->fetch_assoc())) { $res->free(); return $row[$field] ?? 0; }
    return 0;
}
function fetchAll(mysqli $conn, string $query): array {
    $rows = [];
    if ($res = $conn->query($query)) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}
function tableExists(mysqli $conn, string $table): bool {
    $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}
function columnExists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}

// Currency formatter (LKR) with safe fallback
$fmt = class_exists('NumberFormatter') ? new NumberFormatter('en_LK', NumberFormatter::CURRENCY) : null;
function lkr($v){
    global $fmt;
    $v = (float)$v;
    if ($fmt) { return $fmt->formatCurrency($v, 'LKR'); }
    return 'Rs '.number_format($v, 2, '.', ',');
}

// ---------- KPIs ----------
$kpi = [];
try {
    $kpi['customers']        = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM `customer`", 'c');
    $kpi['orders']           = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM `order`", 'c');
    $kpi['stock_qty']        = (int)getSafeValue($conn, "SELECT COALESCE(SUM(`Quantity`),0) AS s FROM `inventoryitem`", 's');
    $kpi['low_stock_cnt']    = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM `inventoryitem` WHERE `Quantity` <= 5", 'c');
    $kpi['returns']          = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM `returnitem`", 'c');
    $kpi['pending_orders']   = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM `order` WHERE `Status`='Pending'", 'c');
    $kpi['deliveries_today'] = (int)getSafeValue($conn, "SELECT COUNT(*) AS c FROM `transport` WHERE `DeliveryDate` = CURDATE()", 'c');
} catch (Throwable $e) {
    error_log("Dashboard KPI error: ".$e->getMessage());
}

// ---------- Revenue ----------
$hasTotalAmount = columnExists($conn, 'order', 'TotalAmount');
$hasAmountPaid  = columnExists($conn, 'order', 'AmountPaid');
$hasSubTotal    = columnExists($conn, 'order', 'SubTotal');
$hasDiscount    = columnExists($conn, 'order', 'Discount');
$hasVAT         = columnExists($conn, 'order', 'VAT');

$rev_total_amount = 0.00;
$rev_amount_paid  = 0.00;
$rev_path = 'none';

if ($hasTotalAmount) {
    $rev_total_amount = (float)getSafeValue($conn, "SELECT ROUND(COALESCE(SUM(`TotalAmount`),0),2) AS v FROM `order`", 'v');
    $rev_path = 'order.TotalAmount';
} elseif ($hasSubTotal && $hasDiscount && $hasVAT) {
    $rev_total_amount = (float)getSafeValue($conn, "
        SELECT ROUND(COALESCE(SUM(`SubTotal` * (1 - `Discount`/100) * (1 + `VAT`/100)),0),2) AS v
        FROM `order`", 'v');
    $rev_path = 'order (computed)';
} elseif (tableExists($conn, 'orderdetails')) {
    $rev_total_amount = (float)getSafeValue($conn, "
        SELECT ROUND(COALESCE(SUM(od.`Subtotal`),0),2) AS v
        FROM `orderdetails` od
        JOIN `order` o ON o.`OrderID` = od.`OrderID`", 'v');
    $rev_path = 'orderdetails.Subtotal';
}

if ($hasAmountPaid) {
    $rev_amount_paid = (float)getSafeValue($conn, "SELECT ROUND(COALESCE(SUM(`AmountPaid`),0),2) AS v FROM `order`", 'v');
}

$rev = [
    'total_amount' => $rev_total_amount,
    'amount_paid'  => $rev_amount_paid,
    'balance_due'  => round($rev_total_amount - $rev_amount_paid, 2),
    'path'         => $rev_path,
];

// ---------- Tables (server-limited) ----------
$recentOrders = fetchAll($conn, "
  SELECT o.`OrderID`, o.`InvoiceID`, o.`OrderDate`, o.`TotalAmount`, o.`Status`,
         c.`NAME` AS CustomerName
  FROM `order` o
  LEFT JOIN `customer` c ON c.`CustomerID` = o.`CustomerID`
  ORDER BY o.`OrderDate` DESC, o.`OrderID` DESC
  LIMIT 50
");

$topItems = [];
if (tableExists($conn, 'orderdetails')) {
    $topItems = fetchAll($conn, "
      SELECT ii.`ItemID`, ii.`NAME` AS ItemName, COALESCE(SUM(od.`Quantity`),0) AS QtySold
      FROM `inventoryitem` ii
      JOIN `orderdetails` od ON od.`ItemID` = ii.`ItemID`
      GROUP BY ii.`ItemID`, ii.`NAME`
      ORDER BY QtySold DESC
      LIMIT 5
    ");
}

$lowStock = fetchAll($conn, "
  SELECT `ItemID`, `InvoiceID`, `NAME`, `Quantity`
  FROM `inventoryitem`
  WHERE `Quantity` <= 10
  ORDER BY `Quantity` ASC
  LIMIT 10
");

// ---------- Layout includes ----------
$headerFile  = __DIR__ . '/header.php';
$sidebarFile = __DIR__ . '/sidebar.php';
$footerFile  = __DIR__ . '/footer.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Dashboard - RB Stores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
  <!-- Custom -->
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
</head>
<body class="bg-body-tertiary">

<?php if (file_exists($headerFile)) include $headerFile; ?>
<div class="container-fluid">
  <div class="row">
    <?php if (file_exists($sidebarFile)) include $sidebarFile; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-4 py-4 main-content">

      <!-- Hero -->
      <div class="hero-gradient">
        <div class="hero-inner">
          <h2 class="m-0">RB Stores Dashboard</h2>
          <span class="lead">As of <?=date('Y-m-d');?> • Balance = <b>TotalAmount − AmountPaid</b></span>
          <?php if ($rev['path'] !== 'none'): ?>
            <span class="badge text-bg-secondary ms-2">Revenue via: <?=h($rev['path']);?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php
        $hasLowStock  = ($kpi['low_stock_cnt'] ?? 0) > 0;
        $hasBalance   = ($rev['balance_due'] ?? 0) > 0;
      ?>
      <!-- Alerts -->
      <div class="mb-3" aria-live="polite">
        <?php if ($hasLowStock): ?>
          <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-2" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div><strong>Low stock:</strong> <?=number_format($kpi['low_stock_cnt']);?> item(s) at or below threshold.</div>
            <a class="ms-auto btn btn-sm btn-outline-warning" href="/admin/low_stock.php" aria-label="Review low stock">Review</a>
          </div>
        <?php endif; ?>
        <?php if ($hasBalance): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3" role="alert">
            <i class="fa-solid fa-sack-xmark" aria-hidden="true"></i>
            <div><strong>Balance due:</strong> <?=h(lkr($rev['balance_due']));?> outstanding.</div>
            <a class="ms-auto btn btn-sm btn-outline-danger" href="/billing/view_billing.php" aria-label="Collect payments">Collect</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick actions -->
      <div class="d-flex flex-wrap gap-2 mb-4" aria-label="Quick actions">
        <a href="/billing/billing.php" class="btn btn-primary btn-action">
          <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>New Order
        </a>
        <a href="/inventory/add_inventory.php" class="btn btn-outline-primary btn-action">
          <i class="fa-solid fa-boxes-packing me-2" aria-hidden="true"></i>Add Stock
        </a>
        <a href="/transport/add_transport.php" class="btn btn-outline-secondary btn-action">
          <i class="fa-solid fa-truck me-2" aria-hidden="true"></i>Schedule Delivery
        </a>
        <a href="/reports/" class="btn btn-outline-dark btn-action">
          <i class="fa-solid fa-chart-line me-2" aria-hidden="true"></i>Reports
        </a>
      </div>

      <!-- KPI Grid -->
      <section class="mb-4">
        <h3 class="h5 mb-3">Business Overview</h3>
        <div class="row g-3">
          <?php
          $cards = [
            ['Customers',$kpi['customers'] ?? 0, 'fa-users', 'primary'],
            ['Orders',$kpi['orders'] ?? 0, 'fa-receipt','secondary'],
            ['Revenue (TotalAmount)', h(lkr($rev['total_amount'] ?? 0)), 'fa-coins','success'],
            ['Amount Paid', h(lkr($rev['amount_paid'] ?? 0)), 'fa-hand-holding-dollar','info'],
            ['Balance Due', h(lkr($rev['balance_due'] ?? 0)), 'fa-scale-balanced','danger'],
            ['Low Stock (≤5)', number_format($kpi['low_stock_cnt'] ?? 0), 'fa-warehouse','warning', '/admin/low_stock.php'],
            ['Items in Stock', number_format($kpi['stock_qty'] ?? 0), 'fa-cubes','dark'],
            ['Pending Orders', number_format($kpi['pending_orders'] ?? 0), 'fa-hourglass-half','warning'],
            ['Deliveries Today', number_format($kpi['deliveries_today'] ?? 0), 'fa-truck-ramp-box','primary', '/admin/pending_deliveries.php'],
            ['Returns', number_format($kpi['returns'] ?? 0), 'fa-rotate-left','secondary'],
          ];
          foreach ($cards as $c) {
            [$label,$val,$icon,$variant,$href] = [$c[0],$c[1],$c[2],$c[3],$c[4] ?? null];
            $inner = '
              <div class="card h-100 shadow-sm border-0 position-relative">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="kpi-icon text-'.$variant.'">
                    <i class="fa '.$icon.'" aria-hidden="true"></i>
                  </div>
                  <div>
                    <div class="text-secondary small">'.$label.'</div>
                    <div class="fs-4 fw-semibold">'.$val.'</div>
                  </div>
                </div>
                <span class="kpi-accent"></span>
              </div>';
            echo '<div class="col-12 col-sm-6 col-lg-4">';
            echo $href ? '<a class="text-decoration-none" href="'.h($href).'" aria-label="'.h($label).'">'.$inner.'</a>' : $inner;
            echo '</div>';
          }
          ?>
        </div>
      </section>

      <!-- Tables -->
      <section class="row g-3">
        <!-- Recent Orders -->
        <div class="col-12 col-xl-7">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header border-0 pb-0">
              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <h4 class="h6 m-0">Recent Orders</h4>
                <div class="d-flex gap-2">
                  <select id="orderStatusFilter" class="form-select form-select-sm" style="min-width:130px" data-bs-toggle="tooltip" title="Filter by status">
                    <option value="">All statuses</option>
                    <option>Paid</option>
                    <option>Pending</option>
                    <option>Cancelled</option>
                    <option>Refunded</option>
                  </select>
                  <input id="orderSearch" class="form-control form-control-sm" placeholder="Search invoice / customer" style="min-width:220px" />
                </div>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover align-middle table--soft recent-orders">
                  <thead class="table-light">
                    <tr>
                      <th>Order #</th>
                      <th>Invoice</th>
                      <th>Customer</th>
                      <th>Date</th>
                      <th class="text-end">Total (Rs)</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php if (!$recentOrders): ?>
                    <tr><td colspan="6" class="text-secondary">No orders yet.</td></tr>
                  <?php else: foreach ($recentOrders as $o): ?>
                    <tr>
                      <td>#<?= (int)$o['OrderID']; ?></td>
                      <td class="text-primary fw-semibold"><?= h($o['InvoiceID'] ?: '—'); ?></td>
                      <td><?= h($o['CustomerName'] ?? 'Guest'); ?></td>
                      <td><?= h(d($o['OrderDate'])); ?></td>
                      <td class="text-end"><?= n2($o['TotalAmount'] ?? 0); ?></td>
                      <td>
                        <?php
                          $status = $o['Status'] ?? 'Pending';
                          $map = ['Paid'=>'success','Pending'=>'warning','Cancelled'=>'danger','Refunded'=>'secondary'];
                          $cls = $map[$status] ?? 'secondary';
                        ?>
                        <span class="badge text-bg-<?=$cls;?>"><?= h($status); ?></span>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Right column -->
        <div class="col-12 col-xl-5">
          <div class="card shadow-sm border-0 mb-3">
            <div class="card-header border-0 pb-0">
              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <h4 class="h6 m-0">Top Items (by Qty Sold)</h4>
                <input id="itemSearch" class="form-control form-control-sm" placeholder="Search item…" style="min-width:220px" />
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle table--soft top-items">
                  <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty Sold</th></tr></thead>
                  <tbody>
                  <?php if (!$topItems): ?>
                    <tr><td colspan="2" class="text-secondary">No sales yet.</td></tr>
                  <?php else: foreach ($topItems as $t): ?>
                    <tr>
                      <td><?= h($t['ItemName']); ?> <span class="text-secondary">(ID: <?= (int)$t['ItemID']; ?>)</span></td>
                      <td class="text-end"><?= number_format((int)$t['QtySold']); ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card shadow-sm border-0">
            <div class="card-header border-0 pb-0">
              <h4 class="h6 m-0">Low Stock (≤ 10)</h4>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle table--soft low-stock">
                  <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th>Invoice</th></tr></thead>
                  <tbody>
                  <?php if (!$lowStock): ?>
                    <tr><td colspan="3" class="text-secondary">All good.</td></tr>
                  <?php else: foreach ($lowStock as $ls): ?>
                    <?php $q = (int)$ls['Quantity'];
                          $qCls = ($q <= 3) ? 'text-danger fw-semibold' : (($q <= 7) ? 'text-warning' : ''); ?>
                    <tr>
                      <td><?= h($ls['NAME']); ?> <span class="text-secondary">(ID: <?= (int)$ls['ItemID']; ?>)</span></td>
                      <td class="text-end <?=$qCls;?>"><?= number_format($q); ?></td>
                      <td><code><?= h($ls['InvoiceID']); ?></code></td>
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </section>
    </main>
  </div>
</div>

<?php if (file_exists($footerFile)) include $footerFile; ?>

<!-- JS (Bootstrap external) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- UX Scripts: tooltips + client-side filters (inline, protected by CSP nonce) -->
<script nonce="<?= $cspNonce ?>">
  // Enable Bootstrap tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{
    new bootstrap.Tooltip(el);
  });

  // ----- Recent Orders filter/search -----
  (function(){
    const search   = document.getElementById('orderSearch');
    const status   = document.getElementById('orderStatusFilter');
    const rows     = Array.from(document.querySelectorAll('.recent-orders tbody tr'));
    const hasRows  = rows.some(tr => !tr.querySelector('td[colspan]'));

    if (search) search.disabled = !hasRows;
    if (status) status.disabled = !hasRows;

    function apply(){
      const q = (search?.value || '').trim().toLowerCase();
      const s = (status?.value || '').trim().toLowerCase();
      rows.forEach(tr=>{
        if (tr.querySelector('td[colspan]')) return; // skip "No orders yet."
        const text = tr.innerText.toLowerCase();
        const badge = tr.querySelector('.badge')?.innerText.toLowerCase() || '';
        const okQ = !q || text.includes(q);
        const okS = !s || badge === s;
        tr.style.display = (okQ && okS) ? '' : 'none';
      });
    }
    search && search.addEventListener('input', apply);
    status && status.addEventListener('change', apply);
  })();

  // ----- Top Items search -----
  (function(){
    const search = document.getElementById('itemSearch');
    const rows   = Array.from(document.querySelectorAll('.top-items tbody tr'));
    const hasRows = rows.some(tr => !tr.querySelector('td[colspan]'));
    if (search) search.disabled = !hasRows;

    function apply(){
      const q = (search?.value || '').trim().toLowerCase();
      rows.forEach(tr=>{
        if (tr.querySelector('td[colspan]')) return; // skip "No sales yet."
        const t = tr.innerText.toLowerCase();
        tr.style.display = !q || t.includes(q) ? '' : 'none';
      });
    }
    search && search.addEventListener('input', apply);
  })();
</script>
</body>
</html>
<?php
// ---------- Tidy up ----------
$conn->close();
