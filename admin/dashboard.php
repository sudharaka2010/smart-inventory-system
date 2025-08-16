<?php
// =================== RB Stores — Admin Dashboard (mysqli, minimal) ===================
// File: /admin/dashboard.php

$IS_DEV = false;
date_default_timezone_set('Asia/Colombo');
ob_start();

// Errors
if ($IS_DEV) {
  ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);
  if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
  ini_set('display_errors','0'); ini_set('display_startup_errors','0'); error_reporting(0);
}

// Sessions (simple)
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!$IS_DEV,'httponly'=>true,'samesite'=>'Lax']);
session_start(['use_strict_mode'=>true,'cookie_secure'=>!$IS_DEV,'cookie_httponly'=>true,'cookie_samesite'=>'Lax']);
if (!isset($_SESSION['regen_at']) || time()-($_SESSION['regen_at']??0)>600){ session_regenerate_id(true); $_SESSION['regen_at']=time(); }

// Auth (Admin only)
function redirect_to_login(): never { header("Location: /auth/login.php?denied=1"); exit; }
if (empty($_SESSION['username']) || (($_SESSION['role']??'')!=='Admin')) redirect_to_login();

// DB
require_once(__DIR__.'/../includes/db_connect.php'); // provides $conn (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo "<h1>DB not connected.</h1>"; exit; }
$conn->set_charset('utf8mb4');

// ---------- Routing / Paths ----------
$APP_BASE = '/admin'; // <-- change if needed
$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function n2($v){ return number_format((float)$v,2,'.',','); }
function d($s){ return $s?date('Y-m-d', strtotime((string)$s)):'—'; }
function getSafeValue(mysqli $c,string $q,string $f){ $r=$c->query($q); $v=0; if($r){$row=$r->fetch_assoc(); $v=$row[$f]??0; $r->free();} return $v; }
function fetchAll(mysqli $c,string $q){ $rows=[]; if($r=$c->query($q)){ while($x=$r->fetch_assoc()) $rows[]=$x; $r->free(); } return $rows; }
function tableExists(mysqli $c,string $t){ $s="SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"; if(!$st=$c->prepare($s))return false; $st->bind_param('s',$t); $st->execute(); $g=$st->get_result(); $row=$g?$g->fetch_assoc():['c'=>0]; $st->close(); return (int)($row['c']??0)>0; }
function columnExists(mysqli $c,string $t,string $col){ $s="SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?"; if(!$st=$c->prepare($s))return false; $st->bind_param('ss',$t,$col); $st->execute(); $g=$st->get_result(); $row=$g?$g->fetch_assoc():['c'=>0]; $st->close(); return (int)($row['c']??0)>0; }
$fmt = class_exists('NumberFormatter') ? new NumberFormatter('en_LK', NumberFormatter::CURRENCY) : null;
function lkr($v){ global $fmt; $v=(float)$v; return $fmt? $fmt->formatCurrency($v,'LKR') : 'Rs '.number_format($v,2,'.',','); }

// KPIs
$kpi = [
  'customers'        => (int)getSafeValue($conn,"SELECT COUNT(*) c FROM `customer`",'c'),
  'orders'           => (int)getSafeValue($conn,"SELECT COUNT(*) c FROM `order`",'c'),
  'stock_qty'        => (int)getSafeValue($conn,"SELECT COALESCE(SUM(`Quantity`),0) s FROM `inventoryitem`",'s'),
  'low_stock_cnt'    => (int)getSafeValue($conn,"SELECT COUNT(*) c FROM `inventoryitem` WHERE `Quantity`<=5",'c'),
  'returns'          => (int)getSafeValue($conn,"SELECT COUNT(*) c FROM `returnitem`",'c'),
  'pending_orders'   => (int)getSafeValue($conn,"SELECT COUNT(*) c FROM `order` WHERE `Status`='Pending'",'c'),
  'deliveries_today' => (int)getSafeValue($conn,"SELECT COUNT(*) c FROM `transport` WHERE DATE(`DeliveryDate`)=CURDATE()", 'c'),
];

// Revenue (adaptive)
$hasTA = columnExists($conn,'order','TotalAmount');
$hasAP = columnExists($conn,'order','AmountPaid');
$hasST = columnExists($conn,'order','SubTotal');
$hasDC = columnExists($conn,'order','Discount');
$hasVT = columnExists($conn,'order','VAT');
$rev_total=0.00; $rev_paid=0.00;

if ($hasTA) {
  $rev_total = (float)getSafeValue($conn,"SELECT ROUND(COALESCE(SUM(`TotalAmount`),0),2) v FROM `order`",'v');
} elseif ($hasST && $hasDC && $hasVT) {
  $rev_total = (float)getSafeValue($conn,"SELECT ROUND(COALESCE(SUM(`SubTotal`*(1-`Discount`/100)*(1+`VAT`/100)),0),2) v FROM `order`",'v');
} elseif (tableExists($conn,'orderdetails')) {
  $rev_total = (float)getSafeValue($conn,"SELECT ROUND(COALESCE(SUM(od.`Subtotal`),0),2) v FROM `orderdetails` od JOIN `order` o ON o.`OrderID`=od.`OrderID`",'v');
}
if ($hasAP) $rev_paid = (float)getSafeValue($conn,"SELECT ROUND(COALESCE(SUM(`AmountPaid`),0),2) v FROM `order`",'v');
$rev = ['total_amount'=>$rev_total,'amount_paid'=>$rev_paid,'balance_due'=>round($rev_total-$rev_paid,2)];

// Lists
$hasODSub = tableExists($conn,'orderdetails') && columnExists($conn,'orderdetails','Subtotal');
$hasODP   = tableExists($conn,'orderdetails') && columnExists($conn,'orderdetails','UnitPrice');
$hasODQ   = tableExists($conn,'orderdetails') && columnExists($conn,'orderdetails','Quantity');
$totalExpr = $hasTA ? 'o.`TotalAmount`' : ($hasODSub
  ? '(SELECT ROUND(COALESCE(SUM(od.`Subtotal`),0),2) FROM `orderdetails` od WHERE od.`OrderID`=o.`OrderID`)'
  : ($hasODP && $hasODQ ? '(SELECT ROUND(COALESCE(SUM(od.`UnitPrice`*od.`Quantity`),0),2) FROM `orderdetails` od WHERE od.`OrderID`=o.`OrderID`)' : '0.00')
);
$recentOrders = fetchAll($conn,"
  SELECT o.`OrderID`,o.`InvoiceID`,o.`OrderDate`,{$totalExpr} TotalAmount,o.`Status`,c.`NAME` CustomerName
  FROM `order` o LEFT JOIN `customer` c ON c.`CustomerID`=o.`CustomerID`
  ORDER BY o.`OrderDate` DESC,o.`OrderID` DESC LIMIT 50
");
$topItems = tableExists($conn,'orderdetails') ? fetchAll($conn,"
  SELECT ii.`ItemID`,ii.`NAME` ItemName,COALESCE(SUM(od.`Quantity`),0) QtySold
  FROM `inventoryitem` ii JOIN `orderdetails` od ON od.`ItemID`=ii.`ItemID`
  GROUP BY ii.`ItemID`,ii.`NAME` ORDER BY QtySold DESC LIMIT 5
") : [];
$lowStock = fetchAll($conn,"SELECT `ItemID`,`InvoiceID`,`NAME`,`Quantity` FROM `inventoryitem` WHERE `Quantity`<=5 ORDER BY `Quantity` ASC LIMIT 25");

// Includes
$headerFile=__DIR__.'/header.php'; $sidebarFile=__DIR__.'/sidebar.php'; $footerFile=__DIR__.'/footer.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Dashboard - RB Stores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Vendor CSS (load ONCE globally) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />

  <!-- App bundle (tokens, header, sidebar, main, dashboard, …) -->
  <link rel="stylesheet" href="/assets/css/app.css?v=2025-08-16">



  <!-- Fonts (optional) -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
</head>
<body class="bg-body">

  <!-- Skip link for a11y -->
  <a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 p-2 bg-light rounded" href="#rb-main">
    Skip to main content
  </a>

  <?php if (file_exists($headerFile)) include $headerFile; ?>

  <!-- Shell wrapper: places sidebar + main in a 2‑col grid on desktop -->
  <div id="rbShell">
    <!-- Sidebar include (offcanvas on mobile; fixed rail on ≥lg via CSS) -->
    <?php
      // Ensure sidebar include does not try to load its own CSS/JS
      $RB_SIDEBAR_LOAD_VENDOR = false;
      $RB_SIDEBAR_LOAD_CSS    = false;
      $RB_SIDEBAR_SHOW_BRAND  = false;
      if (file_exists($sidebarFile)) include $sidebarFile;
    ?>

    <!-- Main content (scoped via data-rb-scope, page key for page CSS) -->
    <main id="rb-main" data-rb-scope="main" data-page="dashboard" class="container-fluid px-3 px-lg-4 py-4" role="main">
      <!-- Title + Theme -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="hero w-100 w-md-auto">
          <h1 class="h4 mb-1">RB Stores Dashboard</h1>
          <div class="text-secondary small">As of <?=date('Y-m-d H:i');?> • <b>Balance = TotalAmount − AmountPaid</b></div>
        </div>

        <!-- Theme controls -->
        <div class="d-flex flex-wrap gap-2">
          <select id="themePreset" class="form-select form-select-sm minw-150" title="Theme preset">
            <option value="default">Default (Bootstrap)</option>
            <option value="ocean">Ocean</option>
            <option value="emerald">Emerald</option>
            <option value="crimson">Crimson</option>
          </select>
          <button id="themeReset" class="btn btn-sm btn-outline-secondary">Reset</button>
        </div>
      </div>

      <!-- Alerts -->
      <div class="mb-3" aria-live="polite">
        <?php if (($kpi['low_stock_cnt']??0)>0): ?>
          <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><strong>Low stock:</strong> <?=number_format($kpi['low_stock_cnt']);?> item(s) at or below ≤5.</div>
            <a class="ms-auto btn btn-sm btn-outline-warning" href="<?= h($href('low_stock.php')); ?>">Review</a>
          </div>
        <?php endif; ?>
        <?php if (($rev['balance_due']??0)>0): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3">
            <i class="fa-solid fa-sack-xmark"></i>
            <div><strong>Balance due:</strong> <?=h(lkr($rev['balance_due']));?> outstanding.</div>
            <a class="ms-auto btn btn-sm btn-outline-danger" href="<?= h($href('view_billing.php')); ?>">Collect</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick actions -->
      <div class="d-flex flex-wrap gap-2 mb-4" aria-label="Quick actions">
        <a href="<?= h($href('billing.php')); ?>" class="btn btn-primary btn-action"><i class="fa-solid fa-plus me-2"></i>New Order</a>
        <a href="<?= h($href('add_inventory.php')); ?>" class="btn btn-outline-primary btn-action"><i class="fa-solid fa-boxes-packing me-2"></i>Add Stock</a>
        <a href="<?= h($href('add_transport.php')); ?>" class="btn btn-outline-secondary btn-action"><i class="fa-solid fa-truck me-2"></i>Schedule Delivery</a>
        <a href="<?= h($href('reports/')); ?>" class="btn btn-outline-dark btn-action"><i class="fa-solid fa-chart-line me-2"></i>Reports</a>
      </div>

      <!-- KPI Grid -->
      <section class="mb-4">
        <h3 class="h6 mb-3">Business Overview</h3>
        <div class="row g-3 kpi">
          <?php
          $cards = [
            ['Customers',        number_format($kpi['customers']??0),         'fa-users','primary', null],
            ['Orders',           number_format($kpi['orders']??0),            'fa-receipt','secondary', null],
            ['Revenue (Total)',  h(lkr($rev['total_amount']??0)),             'fa-coins','success', null],
            ['Amount Paid',      h(lkr($rev['amount_paid']??0)),              'fa-hand-holding-dollar','info', null],
            ['Balance Due',      h(lkr($rev['balance_due']??0)),              'fa-scale-balanced','danger', null],
            ['Low Stock (≤5)',   number_format($kpi['low_stock_cnt']??0),     'fa-warehouse','warning', $href('low_stock.php')],
            ['Items in Stock',   number_format($kpi['stock_qty']??0),         'fa-cubes','dark', null],
            ['Pending Orders',   number_format($kpi['pending_orders']??0),    'fa-hourglass-half','warning', null],
            ['Deliveries Today', number_format($kpi['deliveries_today']??0),  'fa-truck-ramp-box','primary', $href('pending_deliveries.php')],
            ['Returns',          number_format($kpi['returns']??0),           'fa-rotate-left','secondary', null],
          ];
          foreach($cards as [$label,$val,$icon,$variant,$goto]){
            $inner = '<div class="card h-100 shadow-sm border-0"><div class="card-body d-flex align-items-center gap-3">'
                   . '<div class="icon text-'.$variant.'"><i class="fa '.$icon.'"></i></div>'
                   . '<div><div class="text-secondary small">'.$label.'</div><div class="fs-4 fw-semibold">'.$val.'</div></div>'
                   . '</div></div>';
            echo '<div class="col-12 col-sm-6 col-lg-4">'.($goto?'<a class="text-decoration-none" href="'.h($goto).'">'.$inner.'</a>':$inner).'</div>';
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
                  <select id="orderStatusFilter" class="form-select form-select-sm minw-150" title="Filter by status">
                    <option value="">All statuses</option>
                    <option>Paid</option><option>Pending</option><option>Cancelled</option><option>Refunded</option>
                  </select>
                  <input id="orderSearch" class="form-control form-control-sm minw-220" placeholder="Search invoice / customer" />
                  <a class="btn btn-sm btn-outline-light" href="<?= h($href('view_billing.php')); ?>">View all</a>
                </div>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover align-middle table-striped table-soft recent-orders">
                  <thead class="table-light">
                  <tr><th>Order #</th><th>Invoice</th><th>Customer</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr>
                  </thead>
                  <tbody>
                  <?php if (!$recentOrders): ?>
                    <tr><td colspan="6" class="text-secondary">No orders yet.</td></tr>
                  <?php else: foreach($recentOrders as $o): $status=$o['Status']??'Pending';
                    $map=['Paid'=>'success','Pending'=>'warning','Cancelled'=>'danger','Refunded'=>'secondary'];
                    $cls=$map[$status]??'secondary'; ?>
                    <tr>
                      <td>#<?= (int)$o['OrderID']; ?></td>
                      <td class="text-primary fw-semibold"><?= h($o['InvoiceID']?:'—'); ?></td>
                      <td><?= h($o['CustomerName']??'Guest'); ?></td>
                      <td><?= h(d($o['OrderDate'])); ?></td>
                      <td class="text-end"><?= h(lkr($o['TotalAmount']??0)); ?></td>
                      <td><span class="badge text-bg-<?=$cls;?>"><?= h($status); ?></span></td>
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer border-0 pt-0 small text-secondary">
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
                <input id="itemSearch" class="form-control form-control-sm minw-220" placeholder="Search item…" />
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle table-striped table-soft top-items">
                  <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty Sold</th></tr></thead>
                  <tbody>
                  <?php if (!$topItems): ?>
                    <tr><td colspan="2" class="text-secondary">No sales yet.</td></tr>
                  <?php else: foreach($topItems as $t): ?>
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
            <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between">
              <h4 class="h6 m-0">Low Stock (≤ 5)</h4>
              <a class="btn btn-sm btn-outline-light" href="<?= h($href('low_stock.php')); ?>">View all</a>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle table-striped table-soft low-stock">
                  <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th>Invoice</th></tr></thead>
                  <tbody>
                  <?php if (!$lowStock): ?>
                    <tr><td colspan="3" class="text-secondary">All good.</td></tr>
                  <?php else: foreach($lowStock as $ls): $q=(int)$ls['Quantity']; $qCls=$q<=3?'text-danger fw-semibold':($q<=5?'text-warning':''); ?>
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
  </div><!-- /#rbShell -->

  <?php if (file_exists($footerFile)) include $footerFile; ?>

  <!-- JS (Bootstrap bundle once) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <script>
  /* ---------- Tooltips (only if used) ---------- */
  (()=>{ const els = document.querySelectorAll('[data-bs-toggle="tooltip"]'); els.forEach(el => new bootstrap.Tooltip(el)); })();

  /* ---------- Recent Orders filter/search ---------- */
  (()=> {
    const q=document.getElementById('orderSearch'), s=document.getElementById('orderStatusFilter');
    const rows=[...document.querySelectorAll('.recent-orders tbody tr')];
    const has=rows.some(tr=>!tr.querySelector('td[colspan]'));
    if(q) q.disabled=!has; if(s) s.disabled=!has;
    function apply(){
      const qq=(q?.value||'').trim().toLowerCase(), ss=(s?.value||'').trim().toLowerCase();
      rows.forEach(tr=>{
        if(tr.querySelector('td[colspan]')) return;
        const text=tr.innerText.toLowerCase(), badge=tr.querySelector('.badge')?.innerText.toLowerCase()||'';
        const okQ=!qq||text.includes(qq), okS=!ss||badge===ss;
        tr.style.display=(okQ&&okS)?'':'none';
      });
    }
    q&&q.addEventListener('input',apply); s&&s.addEventListener('change',apply);
  })();

  /* ---------- Top Items search ---------- */
  (()=> {
    const q=document.getElementById('itemSearch');
    const rows=[...document.querySelectorAll('.top-items tbody tr')];
    const has=rows.some(tr=>!tr.querySelector('td[colspan]')); if(q) q.disabled=!has;
    function apply(){
      const qq=(q?.value||'').trim().toLowerCase();
      rows.forEach(tr=>{
        if(tr.querySelector('td[colspan]')) return;
        tr.style.display=!qq||tr.innerText.toLowerCase().includes(qq)?'':'none';
      });
    }
    q&&q.addEventListener('input',apply);
  })();

  /* ---------- Theme Switcher (updates Bootstrap component vars) ---------- */
  (()=> {
    const key  = 'rb_theme_preset';
    const sel  = document.getElementById('themePreset');
    const reset= document.getElementById('themeReset');

    const presets = {
      default: null,
      ocean:   {primary:'#0ea5e9', success:'#10b981', warning:'#eab308', danger:'#ef4444', info:'#38bdf8', secondary:'#64748b'},
      emerald: {primary:'#059669', success:'#16a34a', warning:'#f59e0b', danger:'#dc2626', info:'#14b8a6', secondary:'#6b7280'},
      crimson: {primary:'#e11d48', success:'#22c55e', warning:'#f59e0b', danger:'#b91c1c', info:'#3b82f6', secondary:'#4b5563'}
    };

    function ensureStyleEl(){
      let el = document.getElementById('rb-theme-vars');
      if (!el) { el = document.createElement('style'); el.id='rb-theme-vars'; document.head.appendChild(el); }
      return el;
    }

    function cssFor(p){
      if (!p) return '';
      return `
:root{
  --bs-primary:${p.primary}; --bs-success:${p.success}; --bs-warning:${p.warning};
  --bs-danger:${p.danger}; --bs-info:${p.info}; --bs-secondary:${p.secondary};
}
/* Buttons */
.btn-primary{
  --bs-btn-bg: var(--bs-primary);
  --bs-btn-border-color: var(--bs-primary);
  --bs-btn-hover-bg: color-mix(in srgb, var(--bs-primary) 90%, #000 10%);
  --bs-btn-hover-border-color: color-mix(in srgb, var(--bs-primary) 90%, #000 10%);
  --bs-btn-active-bg: color-mix(in srgb, var(--bs-primary) 80%, #000 20%);
  --bs-btn-active-border-color: color-mix(in srgb, var(--bs-primary) 80%, #000 20%);
}
.btn-outline-primary{
  --bs-btn-color: var(--bs-primary);
  --bs-btn-border-color: var(--bs-primary);
  --bs-btn-hover-bg: var(--bs-primary);
  --bs-btn-hover-border-color: var(--bs-primary);
}
/* Links & badges */
a, .link-primary { color: var(--bs-primary) !important; }
.badge.text-bg-primary { background-color: var(--bs-primary) !important; }
`;
    }

    function applyPreset(name){
      const styleEl = ensureStyleEl();
      const p = presets[name] || null;
      styleEl.textContent = cssFor(p);
      if (name && name !== 'default') document.documentElement.setAttribute('data-theme', name);
      else document.documentElement.removeAttribute('data-theme');
    }

    const saved = localStorage.getItem(key) || 'default';
    applyPreset(saved);
    if (sel) sel.value = saved;

    sel && sel.addEventListener('change', ()=>{
      const v = sel.value || 'default';
      applyPreset(v);
      localStorage.setItem(key, v);
    });

    reset && reset.addEventListener('click', ()=>{
      sel.value = 'default';
      applyPreset('default');
      localStorage.setItem(key, 'default');
    });
  })();
  </script>
</body>
</html>
<?php
$conn->close();
if (ob_get_level()>0) ob_end_flush();
