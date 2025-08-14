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

// Sessions (simple, no CSP/security headers)
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!$IS_DEV,'httponly'=>true,'samesite'=>'Lax']);
session_start(['use_strict_mode'=>true,'cookie_secure'=>!$IS_DEV,'cookie_httponly'=>true,'cookie_samesite'=>'Lax']);
if (!isset($_SESSION['regen_at']) || time()-($_SESSION['regen_at']??0)>600){ session_regenerate_id(true); $_SESSION['regen_at']=time(); }

// Auth (Admin only)
function redirect_to_login(): never { header("Location: /auth/login.php?denied=1"); exit; }
if (empty($_SESSION['username']) || ($_SESSION['role']??'')!=='Admin') redirect_to_login();

// DB
require_once(__DIR__.'/../includes/db_connect.php'); // provides $conn (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo "<h1>DB not connected.</h1>"; exit; }
$conn->set_charset('utf8mb4');

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

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'poppins': ['Poppins', 'sans-serif'],
          },
          colors: {
            'brand': {
              primary: '#3b82f6',
              success: '#10b981',
              warning: '#f59e0b',
              danger: '#ef4444',
              info: '#06b6d4',
              secondary: '#6b7280'
            }
          }
        }
      }
    }
  </script>

  <style>
    body { font-family: 'Poppins', sans-serif; }
    .hero-gradient { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 51, 234, 0.1)); }
    .table-hover tbody tr:hover { background-color: rgba(0, 0, 0, 0.02); }
  </style>
</head>
<body class="bg-gray-50">

<?php if (file_exists($headerFile)) include $headerFile; ?>
<div class="min-h-screen">
  <div class="flex">
    <?php if (file_exists($sidebarFile)) include $sidebarFile; ?>

    <main class="flex-1 px-4 lg:px-6 py-6">
      <!-- Header Section -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="hero-gradient w-full md:w-auto rounded-2xl p-6">
          <h1 class="text-2xl font-semibold text-gray-900 mb-2">RB Stores Dashboard</h1>
          <div class="text-gray-600 text-sm">As of <?=date('Y-m-d H:i');?> • <span class="font-medium">Balance = TotalAmount − AmountPaid</span></div>
        </div>

        <!-- Theme controls -->
        <div class="flex flex-wrap gap-2">
          <select id="themePreset" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[150px]" title="Theme preset">
            <option value="default">Default</option>
            <option value="ocean">Ocean</option>
            <option value="emerald">Emerald</option>
            <option value="crimson">Crimson</option>
          </select>
          <button id="themeReset" class="px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 focus:ring-2 focus:ring-gray-500">Reset</button>
        </div>
      </div>

      <!-- Alerts -->
      <div class="mb-6">
        <?php if (($kpi['low_stock_cnt']??0)>0): ?>
          <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg flex items-center gap-3 mb-3">
            <i class="fa-solid fa-triangle-exclamation text-yellow-600"></i>
            <div class="flex-1">
              <strong>Low stock:</strong> <?=number_format($kpi['low_stock_cnt']);?> item(s) at or below ≤5.
            </div>
            <a class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded-md text-sm hover:bg-yellow-300 transition-colors" href="/admin/low_stock.php">Review</a>
          </div>
        <?php endif; ?>
        <?php if (($rev['balance_due']??0)>0): ?>
          <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-3">
            <i class="fa-solid fa-sack-xmark text-red-600"></i>
            <div class="flex-1">
              <strong>Balance due:</strong> <?=h(lkr($rev['balance_due']));?> outstanding.
            </div>
            <a class="bg-red-200 text-red-800 px-3 py-1 rounded-md text-sm hover:bg-red-300 transition-colors" href="/billing/view_billing.php">Collect</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick actions -->
      <div class="flex flex-wrap gap-3 mb-6">
        <a href="/billing/billing.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm inline-flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>New Order
        </a>
        <a href="/inventory/add_inventory.php" class="border border-blue-600 text-blue-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-50 transition-colors shadow-sm inline-flex items-center gap-2">
          <i class="fa-solid fa-boxes-packing"></i>Add Stock
        </a>
        <a href="/transport/add_transport.php" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors shadow-sm inline-flex items-center gap-2">
          <i class="fa-solid fa-truck"></i>Schedule Delivery
        </a>
        <a href="/reports/" class="border border-gray-800 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors shadow-sm inline-flex items-center gap-2">
          <i class="fa-solid fa-chart-line"></i>Reports
        </a>
      </div>

      <!-- KPI Grid -->
      <section class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Business Overview</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
          <?php
          $cards = [
            ['Customers',        number_format($kpi['customers']??0),         'fa-users','blue', null],
            ['Orders',           number_format($kpi['orders']??0),            'fa-receipt','gray', null],
            ['Revenue (Total)',  h(lkr($rev['total_amount']??0)),             'fa-coins','green', null],
            ['Amount Paid',      h(lkr($rev['amount_paid']??0)),              'fa-hand-holding-dollar','cyan', null],
            ['Balance Due',      h(lkr($rev['balance_due']??0)),              'fa-scale-balanced','red', null],
            ['Low Stock (≤5)',   number_format($kpi['low_stock_cnt']??0),     'fa-warehouse','yellow', '/admin/low_stock.php'],
            ['Items in Stock',   number_format($kpi['stock_qty']??0),         'fa-cubes','gray', null],
            ['Pending Orders',   number_format($kpi['pending_orders']??0),    'fa-hourglass-half','yellow', null],
            ['Deliveries Today', number_format($kpi['deliveries_today']??0),  'fa-truck-ramp-box','blue', '/admin/pending_deliveries.php'],
            ['Returns',          number_format($kpi['returns']??0),           'fa-rotate-left','gray', null],
          ];
          $colorMap = [
            'blue' => 'text-blue-600',
            'gray' => 'text-gray-600',
            'green' => 'text-green-600',
            'cyan' => 'text-cyan-600',
            'red' => 'text-red-600',
            'yellow' => 'text-yellow-600'
          ];
          foreach($cards as [$label,$val,$icon,$color,$href]){
            $iconColor = $colorMap[$color] ?? 'text-gray-600';
            $inner = '<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-full hover:shadow-md transition-shadow">'
                   . '<div class="flex items-center gap-4">'
                   . '<div class="'.$iconColor.' text-2xl"><i class="fa '.$icon.'"></i></div>'
                   . '<div><div class="text-gray-600 text-sm">'.$label.'</div><div class="text-2xl font-semibold text-gray-900">'.$val.'</div></div>'
                   . '</div></div>';
            echo '<div class="col-span-1">'.($href?'<a class="block" href="'.h($href).'">'.$inner.'</a>':$inner).'</div>';
          }
          ?>
        </div>
      </section>

      <!-- Tables Section -->
      <section class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <!-- Recent Orders -->
        <div class="xl:col-span-7">
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 h-full">
            <div class="p-6 border-b border-gray-200">
              <div class="flex items-center justify-between gap-4 flex-wrap">
                <h4 class="text-lg font-semibold text-gray-900">Recent Orders</h4>
                <div class="flex gap-2 flex-wrap">
                  <select id="orderStatusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[150px]" title="Filter by status">
                    <option value="">All statuses</option>
                    <option>Paid</option><option>Pending</option><option>Cancelled</option><option>Refunded</option>
                  </select>
                  <input id="orderSearch" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[220px]" placeholder="Search invoice / customer" />
                  <a class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors" href="/billing/view_billing.php">View all</a>
                </div>
              </div>
            </div>
            <div class="p-6">
              <div class="overflow-x-auto">
                <table class="w-full table-hover recent-orders">
                  <thead>
                    <tr class="border-b border-gray-200 text-left">
                      <th class="py-3 text-sm font-medium text-gray-900">Order #</th>
                      <th class="py-3 text-sm font-medium text-gray-900">Invoice</th>
                      <th class="py-3 text-sm font-medium text-gray-900">Customer</th>
                      <th class="py-3 text-sm font-medium text-gray-900">Date</th>
                      <th class="py-3 text-sm font-medium text-gray-900 text-right">Total</th>
                      <th class="py-3 text-sm font-medium text-gray-900">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                  <?php if (!$recentOrders): ?>
                    <tr><td colspan="6" class="py-4 text-gray-500 text-center">No orders yet.</td></tr>
                  <?php else: foreach($recentOrders as $o): $status=$o['Status']??'Pending';
                    $statusColors = [
                      'Paid' => 'bg-green-100 text-green-800',
                      'Pending' => 'bg-yellow-100 text-yellow-800',
                      'Cancelled' => 'bg-red-100 text-red-800',
                      'Refunded' => 'bg-gray-100 text-gray-800'
                    ];
                    $statusClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800'; ?>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-sm text-gray-900">#<?= (int)$o['OrderID']; ?></td>
                      <td class="py-3 text-sm text-blue-600 font-medium"><?= h($o['InvoiceID']?:'—'); ?></td>
                      <td class="py-3 text-sm text-gray-900"><?= h($o['CustomerName']??'Guest'); ?></td>
                      <td class="py-3 text-sm text-gray-900"><?= h(d($o['OrderDate'])); ?></td>
                      <td class="py-3 text-sm text-gray-900 text-right"><?= h(lkr($o['TotalAmount']??0)); ?></td>
                      <td class="py-3"><span class="px-2 py-1 rounded-full text-xs font-medium <?=$statusClass;?>"><?= h($status); ?></span></td>
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200">
              <div class="flex gap-2 text-xs">
                <span class="px-2 py-1 rounded-full bg-green-100 text-green-800">Paid</span>
                <span class="px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                <span class="px-2 py-1 rounded-full bg-red-100 text-red-800">Cancelled</span>
                <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-800">Refunded</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right column -->
        <div class="xl:col-span-5 space-y-6">
          <!-- Top Items -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
              <div class="flex items-center justify-between gap-4 flex-wrap">
                <h4 class="text-lg font-semibold text-gray-900">Top Items (by Qty Sold)</h4>
                <input id="itemSearch" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[220px]" placeholder="Search item…" />
              </div>
            </div>
            <div class="p-6">
              <div class="overflow-x-auto">
                <table class="w-full top-items">
                  <thead>
                    <tr class="border-b border-gray-200 text-left">
                      <th class="py-2 text-sm font-medium text-gray-900">Item</th>
                      <th class="py-2 text-sm font-medium text-gray-900 text-right">Qty Sold</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                  <?php if (!$topItems): ?>
                    <tr><td colspan="2" class="py-3 text-gray-500 text-center">No sales yet.</td></tr>
                  <?php else: foreach($topItems as $t): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-sm text-gray-900">
                        <?= h($t['ItemName']); ?> 
                        <span class="text-gray-500">(ID: <?= (int)$t['ItemID']; ?>)</span>
                      </td>
                      <td class="py-3 text-sm text-gray-900 text-right"><?= number_format((int)$t['QtySold']); ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Low Stock -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-900">Low Stock (≤ 5)</h4>
                <a class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors" href="/admin/low_stock.php">View all</a>
              </div>
            </div>
            <div class="p-6">
              <div class="overflow-x-auto">
                <table class="w-full low-stock">
                  <thead>
                    <tr class="border-b border-gray-200 text-left">
                      <th class="py-2 text-sm font-medium text-gray-900">Item</th>
                      <th class="py-2 text-sm font-medium text-gray-900 text-right">Qty</th>
                      <th class="py-2 text-sm font-medium text-gray-900">Invoice</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                  <?php if (!$lowStock): ?>
                    <tr><td colspan="3" class="py-3 text-gray-500 text-center">All good.</td></tr>
                  <?php else: foreach($lowStock as $ls): 
                    $q=(int)$ls['Quantity']; 
                    $qClass=$q<=3?'text-red-600 font-semibold':($q<=5?'text-yellow-600':'text-gray-900'); ?>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-sm text-gray-900">
                        <?= h($ls['NAME']); ?> 
                        <span class="text-gray-500">(ID: <?= (int)$ls['ItemID']; ?>)</span>
                      </td>
                      <td class="py-3 text-sm text-right <?=$qClass;?>"><?= number_format($q); ?></td>
                      <td class="py-3 text-sm text-gray-900 font-mono"><?= h($ls['InvoiceID']); ?></td>
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

<script>
/* ---------- Recent Orders filter/search ---------- */
(()=>{
  const q=document.getElementById('orderSearch'), s=document.getElementById('orderStatusFilter');
  const rows=[...document.querySelectorAll('.recent-orders tbody tr')];
  const has=rows.some(tr=>!tr.querySelector('td[colspan]'));
  if(q) q.disabled=!has; if(s) s.disabled=!has;
  function apply(){
    const qq=(q?.value||'').trim().toLowerCase(), ss=(s?.value||'').trim().toLowerCase();
    rows.forEach(tr=>{
      if(tr.querySelector('td[colspan]')) return;
      const text=tr.innerText.toLowerCase(), badge=tr.querySelector('span')?.innerText.toLowerCase()||'';
      const okQ=!qq||text.includes(qq), okS=!ss||badge===ss;
      tr.style.display=(okQ&&okS)?'':'none';
    });
  }
  q&&q.addEventListener('input',apply); s&&s.addEventListener('change',apply);
})();

/* ---------- Top Items search ---------- */
(()=>{
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

/* ---------- Theme Switcher ---------- */
(()=>{
  const key = 'rb_theme_preset';
  const sel = document.getElementById('themePreset');
  const reset = document.getElementById('themeReset');

  const presets = {
    default: {
      primary: '#3b82f6',
      success: '#10b981',
      warning: '#f59e0b',
      danger: '#ef4444',
      info: '#06b6d4',
      secondary: '#6b7280'
    },
    ocean: {
      primary: '#0ea5e9',
      success: '#10b981',
      warning: '#eab308',
      danger: '#ef4444',
      info: '#38bdf8',
      secondary: '#64748b'
    },
    emerald: {
      primary: '#059669',
      success: '#16a34a',
      warning: '#f59e0b',
      danger: '#dc2626',
      info: '#14b8a6',
      secondary: '#6b7280'
    },
    crimson: {
      primary: '#e11d48',
      success: '#22c55e',
      warning: '#f59e0b',
      danger: '#b91c1c',
      info: '#3b82f6',
      secondary: '#4b5563'
    }
  };

  function ensureStyleEl(){
    let el = document.getElementById('rb-theme-vars');
    if (!el) { 
      el = document.createElement('style'); 
      el.id='rb-theme-vars'; 
      document.head.appendChild(el); 
    }
    return el;
  }

  function rgbToHex(rgb) {
    return rgb.replace(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/, function(match, r, g, b) {
      return "#" + ((1 << 24) + (parseInt(r) << 16) + (parseInt(g) << 8) + parseInt(b)).toString(16).slice(1);
    });
  }

  function applyPreset(name){
    const styleEl = ensureStyleEl();
    const p = presets[name] || presets.default;
    
    // Update CSS custom properties and classes
    styleEl.textContent = `
      :root {
        --theme-primary: ${p.primary};
        --theme-success: ${p.success};
        --theme-warning: ${p.warning};
        --theme-danger: ${p.danger};
        --theme-info: ${p.info};
        --theme-secondary: ${p.secondary};
      }
      
      /* Primary buttons */
      .bg-blue-600 { background-color: ${p.primary} !important; }
      .hover\\:bg-blue-700:hover { background-color: color-mix(in srgb, ${p.primary} 90%, #000 10%) !important; }
      .border-blue-600 { border-color: ${p.primary} !important; }
      .text-blue-600 { color: ${p.primary} !important; }
      .hover\\:bg-blue-50:hover { background-color: color-mix(in srgb, ${p.primary} 10%, #fff 90%) !important; }
      .focus\\:ring-blue-500:focus { --tw-ring-color: color-mix(in srgb, ${p.primary} 80%, #fff 20%) !important; }
      .focus\\:border-blue-500:focus { border-color: color-mix(in srgb, ${p.primary} 80%, #fff 20%) !important; }
      
      /* Status badges */
      .bg-green-100 { background-color: color-mix(in srgb, ${p.success} 15%, #fff 85%) !important; }
      .text-green-800 { color: color-mix(in srgb, ${p.success} 90%, #000 10%) !important; }
      .text-green-600 { color: ${p.success} !important; }
      
      .bg-yellow-100 { background-color: color-mix(in srgb, ${p.warning} 15%, #fff 85%) !important; }
      .text-yellow-800 { color: color-mix(in srgb, ${p.warning} 90%, #000 10%) !important; }
      .text-yellow-600 { color: ${p.warning} !important; }
      .bg-yellow-50 { background-color: color-mix(in srgb, ${p.warning} 5%, #fff 95%) !important; }
      .border-yellow-200 { border-color: color-mix(in srgb, ${p.warning} 30%, #fff 70%) !important; }
      .bg-yellow-200 { background-color: color-mix(in srgb, ${p.warning} 30%, #fff 70%) !important; }
      .hover\\:bg-yellow-300:hover { background-color: color-mix(in srgb, ${p.warning} 40%, #fff 60%) !important; }
      
      .bg-red-100 { background-color: color-mix(in srgb, ${p.danger} 15%, #fff 85%) !important; }
      .text-red-800 { color: color-mix(in srgb, ${p.danger} 90%, #000 10%) !important; }
      .text-red-600 { color: ${p.danger} !important; }
      .bg-red-50 { background-color: color-mix(in srgb, ${p.danger} 5%, #fff 95%) !important; }
      .border-red-200 { border-color: color-mix(in srgb, ${p.danger} 30%, #fff 70%) !important; }
      .bg-red-200 { background-color: color-mix(in srgb, ${p.danger} 30%, #fff 70%) !important; }
      .hover\\:bg-red-300:hover { background-color: color-mix(in srgb, ${p.danger} 40%, #fff 60%) !important; }
      
      .bg-gray-100 { background-color: color-mix(in srgb, ${p.secondary} 15%, #fff 85%) !important; }
      .text-gray-800 { color: color-mix(in srgb, ${p.secondary} 90%, #000 10%) !important; }
      .text-gray-600 { color: ${p.secondary} !important; }
      
      .text-cyan-600 { color: ${p.info} !important; }
    `;
    
    if (name && name !== 'default') {
      document.documentElement.setAttribute('data-theme', name);
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
  }

  // Load saved theme
  const saved = localStorage.getItem(key) || 'default';
  applyPreset(saved);
  if (sel) sel.value = saved;

  // Event listeners
  sel && sel.addEventListener('change', ()=>{
    const v = sel.value || 'default';
    applyPreset(v);
    localStorage.setItem(key, v);
  });

  reset && reset.addEventListener('click', ()=>{
    if (sel) sel.value = 'default';
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
?>