<?php
// =================== RB Stores — Admin Dashboard (mysqli) ===================
// File: /admin/dashboard.php

// ---------- ENV ----------
$IS_DEV = false; // set true for local development
date_default_timezone_set('Asia/Colombo');

// ---------- BOOTSTRAP ----------
ob_start();

/* ---- HTTPS ENFORCEMENT (Heroku / proxies) ----
   In production force HTTPS; in dev don't redirect so local HTTP works. */
if (!$IS_DEV) {
    $xfp   = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $https = $_SERVER['HTTPS'] ?? '';
    $port  = (int)($_SERVER['SERVER_PORT'] ?? 0);
    $isHttps = ($xfp === 'https') || ($https === 'on') || ($port === 443);

    if (!$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

/* ---- Cache controls ---- */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* ---- Errors ---- */
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

/* ---- Strong session config (align with login.php) ----
   Use secure cookies in prod; allow non-secure in local dev to avoid breakage. */
$cookieSecure = !$IS_DEV;

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start([
    'use_strict_mode' => true,
    'cookie_secure'   => $cookieSecure,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

// Periodic session ID rotation
if (!isset($_SESSION['regen_at']) || time() - ($_SESSION['regen_at'] ?? 0) > 600) {
    session_regenerate_id(true);
    $_SESSION['regen_at'] = time();
}

/* ---- CSP nonce + security headers (consistent) ---- */
$cspNonce = base64_encode(random_bytes(16));

// Remove any pre-set CSP to avoid duplicates (e.g., from server config)
if (function_exists('header_remove')) {
    @header_remove('Content-Security-Policy');
}

/* Tight, compatible CSP:
   - Allows Bootstrap/FA/Icons/Google Fonts via cdnjs + jsDelivr + fonts domains
   - Nonce for ALL inline <script> and <style> blocks here
   - style-src-attr 'self' allows style *attributes* without unsafe-inline
*/
/* --- Content Security Policy (CSP) --- */
$cspNonce = $cspNonce ?? base64_encode(random_bytes(16)); // ensure it's set once

$CSP = implode(' ', [
  "default-src 'self';",


  "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'nonce-{$cspNonce}';",
  "script-src-elem 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;",

  // External CSS (Bootstrap, FA, Google Fonts). Inline <style> needs the nonce.
  "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'nonce-{$cspNonce}';",
  "style-src-elem 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'nonce-{$cspNonce}';",
  "style-src-attr 'self';",  // allow style="..." attributes without unsafe-inline

  // Fonts (Bootstrap Icons via jsDelivr, FA via cdnjs, Google Fonts, plus data: for embeds)
  "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com data:;",

  // Usual media + connections
  "img-src 'self' https: data: blob:;",
  "connect-src 'self';",

  // Lockdown
  "frame-ancestors 'none';",
  "base-uri 'self';",
  "form-action 'self';",
  "object-src 'none';",
  "worker-src 'self' blob:;",

  "upgrade-insecure-requests;"
]);
header('Content-Security-Policy: ' . $CSP);

/* --- Additional Security Headers --- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');              // redundant with frame-ancestors but fine
header('X-XSS-Protection: 0');                // rely on CSP
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), usb=(), accelerometer=(), gyroscope=(), magnetometer=()');




// HSTS only when actually on HTTPS
$hstsHttps = (
    (($_SERVER['HTTPS'] ?? '') === 'on') ||
    (($_SERVER['SERVER_PORT'] ?? null) == 443) ||
    (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
);
if ($hstsHttps) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// ---------- Auth helpers ----------
function redirect_to_login(): never {
    header("Location: /auth/login.php?denied=1");
    exit();
}
function requireLogin(): void {
    if (empty($_SESSION['username']) || empty($_SESSION['role'])) redirect_to_login();
}
function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) redirect_to_login();
}
requireRole(['Admin']); // Only Admins

// ---------- DB ----------
require_once(__DIR__ . '/../includes/db_connect.php'); // should set $conn (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) {
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
    // NOTE: For static queries only. NEVER pass user input here.
    $res = $conn->query($query);
    if ($res) {
        $row = $res->fetch_assoc();
        $val = $row[$field] ?? 0;
        $res->free();
        return $val;
    }
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
    if (!$stmt = $conn->prepare($sql)) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : ['c'=>0];
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}
function columnExists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    if (!$stmt = $conn->prepare($sql)) { return false; }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : ['c'=>0];
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
    // Safe for DATE or DATETIME column:
    $kpi['deliveries_today'] = (int)getSafeValue($conn,
        "SELECT COUNT(*) AS c FROM `transport` WHERE DATE(`DeliveryDate`) = CURDATE()", 'c');
} catch (Throwable $e) {
    error_log("Dashboard KPI error: ".$e->getMessage());
}

// ---------- Revenue (adaptive) ----------
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
    // Fallback if order table doesn't carry totals
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

// Prepare adaptive Total expression for Recent Orders list:
$hasODSubtotal  = tableExists($conn, 'orderdetails') && columnExists($conn, 'orderdetails', 'Subtotal');
$hasODUnitPrice = tableExists($conn, 'orderdetails') && columnExists($conn, 'orderdetails', 'UnitPrice');
$hasODQty       = tableExists($conn, 'orderdetails') && columnExists($conn, 'orderdetails', 'Quantity');

$totalExpr = '0.00';
if ($hasTotalAmount) {
  $totalExpr = 'o.`TotalAmount`';
} elseif ($hasODSubtotal) {
  $totalExpr = '(SELECT ROUND(COALESCE(SUM(od.`Subtotal`),0),2) FROM `orderdetails` od WHERE od.`OrderID` = o.`OrderID`)';
} elseif ($hasODUnitPrice && $hasODQty) {
  $totalExpr = '(SELECT ROUND(COALESCE(SUM(od.`UnitPrice` * od.`Quantity`),0),2) FROM `orderdetails` od WHERE od.`OrderID` = o.`OrderID`)';
}

$recentOrders = fetchAll($conn, "
  SELECT o.`OrderID`, o.`InvoiceID`, o.`OrderDate`, {$totalExpr} AS TotalAmount, o.`Status`,
         c.`NAME` AS CustomerName
  FROM `order` o
  LEFT JOIN `customer` c ON c.`CustomerID` = o.`CustomerID`
  ORDER BY o.`OrderDate` DESC, o.`OrderID` DESC
  LIMIT 50
");

// Optional aggregation only if orderdetails table exists
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

// Low-stock list (threshold ≤5)
$lowStock = fetchAll($conn, "
  SELECT `ItemID`, `InvoiceID`, `NAME`, `Quantity`
  FROM `inventoryitem`
  WHERE `Quantity` <= 5
  ORDER BY `Quantity` ASC
  LIMIT 25
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">

  <!-- Bootstrap Icons (jsDelivr) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">

  <!-- Font Awesome 6 (cdnjs) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" crossorigin="anonymous" />

  <!-- Custom -->
  <link rel="stylesheet" href="/assets/css/dashboard.css" />

  <!-- Tiny utility styles to replace inline attributes -->
  <style nonce="<?= $cspNonce ?>">
    .minw-130{min-width:130px}.minw-220{min-width:220px}
    .hero-gradient{background:linear-gradient(135deg,#0d6efd1a,#6610f21a);border-radius:1rem;padding:1.25rem;margin-bottom:1rem}
    .hero-inner{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap}
    .kpi-icon{font-size:1.75rem}
    .kpi-accent{position:absolute;inset:auto 0 0 0;height:4px;background:linear-gradient(90deg,#0d6efd,#20c997,#ffc107,#dc3545);opacity:.15;border-radius:0 0 .5rem .5rem}
    .table--soft tbody tr:hover{background-color:#00000006}
    .table--stack td .text-secondary{font-size:.85rem}
    .btn-action{box-shadow:0 1px 2px rgba(0,0,0,.05)}
    .main-content{min-height:100vh}
  </style>
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
          <h1 class="m-0">RB Stores Dashboard</h1>
          <span class="lead">As of <?=date('Y-m-d H:i');?> • Balance = <b>TotalAmount − AmountPaid</b></span>
          <?php if ($rev['path'] !== 'none' && $IS_DEV): ?>
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
            <div><strong>Low stock:</strong> <?=number_format($kpi['low_stock_cnt']);?> item(s) at or below threshold (≤5).</div>
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
                <div class="d-flex gap-2 flex-wrap">
                  <select id="orderStatusFilter" class="form-select form-select-sm minw-130" data-bs-toggle="tooltip" title="Filter by status">
                    <option value="">All statuses</option>
                    <option>Paid</option>
                    <option>Pending</option>
                    <option>Cancelled</option>
                    <option>Refunded</option>
                  </select>
                  <input id="orderSearch" class="form-control form-control-sm minw-220" placeholder="Search invoice / customer" />
                  <a class="btn btn-sm btn-outline-light" href="/billing/view_billing.php">View all</a>
                </div>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover align-middle table--soft table--stack recent-orders">
                  <thead class="table-light">
                    <tr>
                      <th scope="col">Order #</th>
                      <th scope="col">Invoice</th>
                      <th scope="col">Customer</th>
                      <th scope="col">Date</th>
                      <th scope="col" class="text-end">Total</th>
                      <th scope="col">Status</th>
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
                      <td class="text-end"><?= h(lkr($o['TotalAmount'] ?? 0)); ?></td>
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
            <div class="card-footer border-0 pt-0 small text-secondary">
              Status legend:
              <span class="badge text-bg-success">Paid</span>
              <span class="badge text-bg-warning">Pending</span>
              <span class="badge text-bg-danger">Cancelled</span>
              <span class="badge text-bg-secondary">Refunded</span>
            </div>
          </div>
        </div>

        <!-- Right column -->
        <div class="col-12 col-xl-5">
          <div class="card shadow-sm border-0 mb-3">
            <div class="card-header border-0 pb-0">
              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <h4 class="h6 m-0">Top Items (by Qty Sold)</h4>
                <div class="d-flex gap-2">
                  <input id="itemSearch" class="form-control form-control-sm minw-220" placeholder="Search item…" />
                </div>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle table--soft table--stack top-items">
                  <thead class="table-light"><tr><th scope="col">Item</th><th scope="col" class="text-end">Qty Sold</th></tr></thead>
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
              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <h4 class="h6 m-0">Low Stock (≤ 5)</h4>
                <a class="btn btn-sm btn-outline-light" href="/admin/low_stock.php" aria-label="All low stock">View all</a>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle table--soft table--stack low-stock">
                  <thead class="table-light"><tr><th scope="col">Item</th><th scope="col" class="text-end">Qty</th><th scope="col">Invoice</th></tr></thead>
                  <tbody>
                  <?php if (!$lowStock): ?>
                    <tr><td colspan="3" class="text-secondary">All good.</td></tr>
                  <?php else: foreach ($lowStock as $ls): ?>
                    <?php $q = (int)$ls['Quantity'];
                          $qCls = ($q <= 3) ? 'text-danger fw-semibold' : (($q <= 5) ? 'text-warning' : ''); ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<!-- UX Scripts: tooltips + client-side filters (nonce-protected) -->
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
if (ob_get_level() > 0) { ob_end_flush(); }
