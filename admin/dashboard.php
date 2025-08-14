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

  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
  
  <!-- Enhanced Dashboard Styles -->
  <style>
    /* ===================== RB Stores — Enhanced Dashboard CSS ===================== */
    
    /* Root Variables */
    :root {
      --rb-header-h: 68px; /* Set your actual header height */
      --rb-sb-w: 280px;
      --rb-primary-500: #667eea;
      --rb-primary-600: #5a63ff;
      --rb-accent-1: #6d28d9;
      --rb-accent-2: #3b82f6;
      
      /* Theme variables */
      --bs-body-bg: #f8fafc;
      --bs-body-color: #2d3748;
      --brand-primary: var(--bs-primary);
      --brand-success: var(--bs-success);
      --brand-warning: var(--bs-warning);
      --brand-danger: var(--bs-danger);
      --brand-info: var(--bs-info);
      --brand-secondary: var(--bs-secondary);
    }

    /* Enhanced Main Layout */
    main {
      font-size: 0.95rem;
      padding: 1.5rem 2rem !important;
      min-height: calc(100vh - var(--rb-header-h));
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }

    @media (max-width: 1199.98px) {
      main { 
        margin-left: 0 !important; 
        width: 100% !important;
        padding: 1rem !important;
      }
    }

    @media (min-width: 1200px) {
      main {
        margin-left: var(--rb-sb-w) !important;
        width: calc(100% - var(--rb-sb-w)) !important;
      }
    }

    /* Enhanced Hero Section */
    .hero {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      color: white !important;
      padding: 2rem;
      margin-bottom: 2rem;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.12);
      border: none;
    }

    .hero h1 {
      font-size: 2rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: white !important;
    }

    .hero .text-secondary {
      color: rgba(255,255,255,0.8) !important;
      font-size: 0.95rem;
    }

    /* Enhanced KPI Cards */
    .kpi .card {
      background: white;
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      height: 100%;
      overflow: hidden;
    }

    .kpi .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }

    .kpi .card-body {
      padding: 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .kpi .icon {
      font-size: 2.5rem;
      width: 60px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 12px;
      flex-shrink: 0;
    }

    /* KPI Icon Colors */
    .kpi .text-primary .icon { 
      background: linear-gradient(135deg, #667eea, #764ba2); 
      color: white;
    }
    .kpi .text-success .icon { 
      background: linear-gradient(135deg, #56ab2f, #a8e6cf); 
      color: white;
    }
    .kpi .text-warning .icon { 
      background: linear-gradient(135deg, #f093fb, #f5576c); 
      color: white;
    }
    .kpi .text-danger .icon { 
      background: linear-gradient(135deg, #ff416c, #ff4b2b); 
      color: white;
    }
    .kpi .text-info .icon { 
      background: linear-gradient(135deg, #4facfe, #00f2fe); 
      color: white;
    }
    .kpi .text-secondary .icon { 
      background: linear-gradient(135deg, #6c757d, #495057); 
      color: white;
    }
    .kpi .text-dark .icon { 
      background: linear-gradient(135deg, #2c3e50, #34495e); 
      color: white;
    }

    .kpi .fs-4 {
      font-size: 2rem !important;
      font-weight: 700;
      color: #2d3748;
      margin: 0;
    }

    .kpi .text-secondary.small {
      color: #718096 !important;
      font-size: 0.9rem;
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    /* Enhanced Cards */
    .card {
      background: white;
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-bottom: 1.5rem;
      overflow: hidden;
    }

    .card-header {
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
      padding: 1.25rem 1.5rem;
      border-radius: 16px 16px 0 0 !important;
    }

    .card-header h4 {
      font-size: 1.1rem;
      font-weight: 600;
      color: #2d3748;
      margin: 0;
    }

    .card-body {
      padding: 1.5rem;
    }

    .card-footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 1rem 1.5rem;
    }

    /* Enhanced Buttons */
    .btn {
      border-radius: 12px;
      padding: 0.75rem 1.5rem;
      font-weight: 500;
      transition: all 0.3s ease;
      border: none;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-outline-primary:hover {
      background: linear-gradient(135deg, #667eea, #764ba2);
      border-color: transparent;
    }

    /* Enhanced Tables */
    .table-responsive {
      border-radius: 12px;
      max-height: 65vh;
      overflow-y: auto;
      border: 1px solid #e2e8f0;
    }

    .table {
      margin-bottom: 0;
      font-size: 0.9rem;
      color: #4a5568;
    }

    .table thead {
      background: #f7fafc;
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .table th {
      font-size: 0.85rem;
      font-weight: 600;
      padding: 1rem 0.75rem;
      color: #2d3748;
      border-bottom: 2px solid #e2e8f0;
      white-space: nowrap;
    }

    .table td {
      padding: 0.875rem 0.75rem;
      vertical-align: middle;
      border-bottom: 1px solid #f1f5f9;
    }

    .table tbody tr:hover {
      background-color: #f8fafc;
    }

    /* Enhanced Forms */
    .form-control,
    .form-select {
      font-size: 0.9rem;
      padding: 0.6rem 0.9rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      background: white;
      color: #4a5568;
      transition: all 0.2s ease;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
      background: white;
    }

    .form-control-sm,
    .form-select-sm {
      font-size: 0.85rem;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
    }

    /* Enhanced Badges */
    .badge {
      font-size: 0.8rem;
      padding: 0.4rem 0.8rem;
      border-radius: 8px;
      font-weight: 500;
    }

    .badge.text-bg-success {
      background: linear-gradient(135deg, #48bb78, #38a169) !important;
    }

    .badge.text-bg-warning {
      background: linear-gradient(135deg, #ed8936, #dd6b20) !important;
    }

    .badge.text-bg-danger {
      background: linear-gradient(135deg, #f56565, #e53e3e) !important;
    }

    .badge.text-bg-secondary {
      background: linear-gradient(135deg, #a0aec0, #718096) !important;
    }

    /* Enhanced Alerts */
    .alert {
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      border-radius: 12px;
      font-size: 0.9rem;
      border: none;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .alert-warning {
      background: linear-gradient(135deg, #fef5e7, #fed7aa);
      color: #c05621;
    }

    .alert-danger {
      background: linear-gradient(135deg, #fed7d7, #fc8181);
      color: #c53030;
    }

    /* Enhanced Section Headers */
    section h3 {
      font-size: 1.25rem;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 1.5rem;
      position: relative;
      padding-left: 1rem;
    }

    section h3::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 20px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      border-radius: 2px;
    }

    /* Utility Classes */
    .minw-150 { min-width: 150px; }
    .minw-220 { min-width: 200px; }

    /* Enhanced Scrollbars */
    .table-responsive::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, #cbd5e0, #a0aec0);
      border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, #a0aec0, #718096);
    }

    /* Container Fixes */
    .container-fluid {
      padding: 0;
    }

    .container-fluid > .row {
      margin: 0;
      min-height: calc(100vh - var(--rb-header-h));
    }

    html, body { 
      overflow-x: hidden; 
      width: 100%;
      margin: 0;
      padding: 0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      main {
        padding: 1rem !important;
      }
      
      .hero {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
      }
      
      .hero h1 {
        font-size: 1.5rem;
      }
      
      .kpi .card-body {
        padding: 1rem;
      }
      
      .kpi .icon {
        font-size: 2rem;
        width: 50px;
        height: 50px;
      }
      
      .kpi .fs-4 {
        font-size: 1.5rem !important;
      }
      
      .card-body {
        padding: 1rem;
      }
      
      .btn {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
      }
    }

    @media (max-width: 576px) {
      main {
        padding: 0.75rem !important;
      }
      
      .hero {
        padding: 1rem;
      }
      
      .hero h1 {
        font-size: 1.25rem;
      }
      
      .kpi .card-body {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
      }
      
      .card-header,
      .card-body,
      .card-footer {
        padding: 1rem;
      }
    }
  </style>
</head>
<body class="bg-body">

<?php if (file_exists($headerFile)) include $headerFile; ?>
<div class="container-fluid">
  <div class="row">
    <?php if (file_exists($sidebarFile)) include $sidebarFile; ?>

    <main class="col-12 px-3 px-lg-4 py-4">
      <!-- Bar: Title + Theme -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="hero w-100 w-md-auto">
          <h1 class="h4 mb-1">RB Stores Dashboard</h1>
          <div class="text-secondary small">As of <?=date('Y-m-d H:i');?> • <b>Balance = TotalAmount − AmountPaid</b></div>
        </div>

        <!-- Theme controls (live color change for cards/tables/text) -->
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
            <a class="ms-auto btn btn-sm btn-outline-warning" href="/admin/low_stock.php">Review</a>
          </div>
        <?php endif; ?>
        <?php if (($rev['balance_due']??0)>0): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3">
            <i class="fa-solid fa-sack-xmark"></i>
            <div><strong>Balance due:</strong> <?=h(lkr($rev['balance_due']));?> outstanding.</div>
            <a class="ms-auto btn btn-sm btn-outline-danger" href="/billing/view_billing.php">Collect</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick actions -->
      <div class="d-flex flex-wrap gap-2 mb-4" aria-label="Quick actions">
        <a href="/billing/billing.php" class="btn btn-primary btn-action"><i class="fa-solid fa-plus me-2"></i>New Order</a>
        <a href="/inventory/add_inventory.php" class="btn btn-outline-primary btn-action"><i class="fa-solid fa-boxes-packing me-2"></i>Add Stock</a>
        <a href="/transport/add_transport.php" class="btn btn-outline-secondary btn-action"><i class="fa-solid fa-truck me-2"></i>Schedule Delivery</a>
        <a href="/reports/" class="btn btn-outline-dark btn-action"><i class="fa-solid fa-chart-line me-2"></i>Reports</a>
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
            ['Low Stock (≤5)',   number_format($kpi['low_stock_cnt']??0),     'fa-warehouse','warning', '/admin/low_stock.php'],
            ['Items in Stock',   number_format($kpi['stock_qty']??0),         'fa-cubes','dark', null],
            ['Pending Orders',   number_format($kpi['pending_orders']??0),    'fa-hourglass-half','warning', null],
            ['Deliveries Today', number_format($kpi['deliveries_today']??0),  'fa-truck-ramp-box','primary', '/admin/pending_deliveries.php'],
            ['Returns',          number_format($kpi['returns']??0),           'fa-rotate-left','secondary', null],
          ];
          foreach($cards as [$label,$val,$icon,$variant,$href]){
            $inner = '<div class="card h-100 shadow-sm border-0"><div class="card-body d-flex align-items-center gap-3">'
                   . '<div class="icon text-'.$variant.'"><i class="fa '.$icon.'"></i></div>'
                   . '<div><div class="text-secondary small">'.$label.'</div><div class="fs-4 fw-semibold">'.$val.'</div></div>'
                   . '</div></div>';
            echo '<div class="col-12 col-sm-6 col-lg-4">'.($href?'<a class="text-decoration-none" href="'.h($href).'">'.$inner.'</a>':$inner).'</div>';
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
                  <a class="btn btn-sm btn-outline-light" href="/billing/view_billing.php">View all</a>
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
              <a class="btn btn-sm btn-outline-light" href="/admin/low_stock.php">View all</a>
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
  </div>
</div>

<?php if (file_exists($footerFile)) include $footerFile; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Enhanced Dashboard JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  /* ---------- Enhanced Tooltips ---------- */
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
      animation: true,
      delay: { show: 300, hide: 100 }
    });
  });

  /* ---------- Enhanced Table Filtering ---------- */
  function setupTableFilter(searchId, tableSelector, statusFilterId = null) {
    const searchInput = document.getElementById(searchId);
    const statusFilter = statusFilterId ? document.getElementById(statusFilterId) : null;
    const tableRows = document.querySelectorAll(`${tableSelector} tbody tr`);
    
    // Check if we have actual data rows (not just "no data" messages)
    const hasData = Array.from(tableRows).some(row => !row.querySelector('td[colspan]'));
    
    if (searchInput) searchInput.disabled = !hasData;
    if (statusFilter) statusFilter.disabled = !hasData;
    
    function filterTable() {
      const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
      const statusTerm = statusFilter ? statusFilter.value.toLowerCase().trim() : '';
      
      let visibleCount = 0;
      
      tableRows.forEach(row => {
        // Skip "no data" rows
        if (row.querySelector('td[colspan]')) return;
        
        const rowText = row.innerText.toLowerCase();
        const statusBadge = row.querySelector('.badge');
        const rowStatus = statusBadge ? statusBadge.innerText.toLowerCase() : '';
        
        const matchesSearch = !searchTerm || rowText.includes(searchTerm);
        const matchesStatus = !statusTerm || rowStatus === statusTerm;
        
        if (matchesSearch && matchesStatus) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      // Show/hide "no results" message
      updateNoResultsMessage(tableSelector, visibleCount, hasData);
    }
    
    if (searchInput) {
      searchInput.addEventListener('input', debounce(filterTable, 300));
    }
    
    if (statusFilter) {
      statusFilter.addEventListener('change', filterTable);
    }
  }

  /* ---------- No Results Message ---------- */
  function updateNoResultsMessage(tableSelector, visibleCount, hasData) {
    const tbody = document.querySelector(`${tableSelector} tbody`);
    let noResultsRow = tbody.querySelector('.no-results-row');
    
    if (hasData && visibleCount === 0) {
      if (!noResultsRow) {
        const colCount = tbody.closest('table').querySelectorAll('thead th').length;
        noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = `
          <td colspan="${colCount}" class="text-center text-muted py-4">
            <i class="fa-solid fa-search me-2"></i>
            No results match your search criteria
          </td>
        `;
        tbody.appendChild(noResultsRow);
      }
      noResultsRow.style.display = '';
    } else if (noResultsRow) {
      noResultsRow.style.display = 'none';
    }
  }

  /* ---------- Setup Table Filters ---------- */
  setupTableFilter('orderSearch', '.recent-orders', 'orderStatusFilter');
  setupTableFilter('itemSearch', '.top-items');

  /* ---------- Enhanced Theme Switcher ---------- */
  const themeKey = 'rb_theme_preset';
  const themeSelect = document.getElementById('themePreset');
  const resetButton = document.getElementById('themeReset');

  const themePresets = {
    default: {
      name: 'Default (Bootstrap)',
      colors: null
    },
    ocean: {
      name: 'Ocean',
      colors: {
        primary: '#0ea5e9',
        success: '#10b981',
        warning: '#eab308',
        danger: '#ef4444',
        info: '#38bdf8',
        secondary: '#64748b'
      }
    },
    emerald: {
      name: 'Emerald',
      colors: {
        primary: '#059669',
        success: '#16a34a',
        warning: '#f59e0b',
        danger: '#dc2626',
        info: '#14b8a6',
        secondary: '#6b7280'
      }
    },
    crimson: {
      name: 'Crimson',
      colors: {
        primary: '#e11d48',
        success: '#22c55e',
        warning: '#f59e0b',
        danger: '#b91c1c',
        info: '#3b82f6',
        secondary: '#4b5563'
      }
    }
  };

  function getThemeStyleElement() {
    let styleEl = document.getElementById('rb-theme-vars');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'rb-theme-vars';
      document.head.appendChild(styleEl);
    }
    return styleEl;
  }

  function generateThemeCSS(preset) {
    if (!preset || !preset.colors) return '';
    
    const { colors } = preset;
    return `
      :root {
        --bs-primary: ${colors.primary};
        --bs-success: ${colors.success};
        --bs-warning: ${colors.warning};
        --bs-danger: ${colors.danger};
        --bs-info: ${colors.info};
        --bs-secondary: ${colors.secondary};
      }
      
      /* Enhanced button theming */
      .btn-primary {
        --bs-btn-bg: var(--bs-primary);
        --bs-btn-border-color: var(--bs-primary);
        --bs-btn-hover-bg: color-mix(in srgb, var(--bs-primary) 85%, #000 15%);
        --bs-btn-hover-border-color: color-mix(in srgb, var(--bs-primary) 85%, #000 15%);
        --bs-btn-active-bg: color-mix(in srgb, var(--bs-primary) 75%, #000 25%);
      }
      
      .btn-outline-primary {
        --bs-btn-color: var(--bs-primary);
        --bs-btn-border-color: var(--bs-primary);
        --bs-btn-hover-bg: var(--bs-primary);
      }
      
      /* Badge theming */
      .badge.text-bg-primary { background-color: var(--bs-primary) !important; }
      .badge.text-bg-success { background-color: var(--bs-success) !important; }
      .badge.text-bg-warning { background-color: var(--bs-warning) !important; }
      .badge.text-bg-danger { background-color: var(--bs-danger) !important; }
      .badge.text-bg-info { background-color: var(--bs-info) !important; }
      .badge.text-bg-secondary { background-color: var(--bs-secondary) !important; }
      
      /* Link theming */
      a, .link-primary { color: var(--bs-primary) !important; }
      
      /* KPI icon theming */
      .kpi .text-primary .icon { 
        background: linear-gradient(135deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 80%, #000 20%)) !important;
      }
    `;
  }

  function applyTheme(themeName) {
    const styleEl = getThemeStyleElement();
    const preset = themePresets[themeName];
    
    styleEl.textContent = generateThemeCSS(preset);
    
    if (themeName && themeName !== 'default') {
      document.documentElement.setAttribute('data-theme', themeName);
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    
    // Add visual feedback
    showThemeChangeNotification(preset ? preset.name : 'Default');
  }

  function showThemeChangeNotification(themeName) {
    // Remove existing notification
    const existing = document.querySelector('.theme-notification');
    if (existing) existing.remove();
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = 'theme-notification position-fixed top-0 end-0 m-3 p-3 bg-dark text-white rounded shadow';
    notification.style.cssText = 'z-index: 9999; transform: translateX(100%); transition: transform 0.3s ease;';
    notification.innerHTML = `
      <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-palette"></i>
        <span>Theme changed to: <strong>${themeName}</strong></span>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => notification.remove(), 300);
    }, 2000);
  }

  // Load saved theme
  const savedTheme = localStorage.getItem(themeKey) || 'default';
  applyTheme(savedTheme);
  if (themeSelect) themeSelect.value = savedTheme;

  // Theme change events
  if (themeSelect) {
    themeSelect.addEventListener('change', function() {
      const selectedTheme = this.value || 'default';
      applyTheme(selectedTheme);
      localStorage.setItem(themeKey, selectedTheme);
    });
  }

  if (resetButton) {
    resetButton.addEventListener('click', function() {
      applyTheme('default');
      if (themeSelect) themeSelect.value = 'default';
      localStorage.setItem(themeKey, 'default');
    });
  }

  /* ---------- Enhanced Card Animations ---------- */
  function observeCards() {
    const cards = document.querySelectorAll('main .card');
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    });

    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
      observer.observe(card);
    });
  }

  /* ---------- Enhanced Number Animation ---------- */
  function animateNumbers() {
    const numberElements = document.querySelectorAll('.kpi .fs-4');
    
    numberElements.forEach(el => {
      const text = el.textContent.trim();
      const number = parseFloat(text.replace(/[^\d.-]/g, ''));
      
      if (!isNaN(number) && number > 0) {
        animateNumber(el, 0, number, 1000, text);
      }
    });
  }

  function animateNumber(element, start, end, duration, originalText) {
    const startTime = performance.now();
    const isMonetary = originalText.includes('Rs');
    const prefix = isMonetary ? 'Rs ' : '';
    const suffix = originalText.match(/,\d{2}$/) ? '' : '';
    
    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Easing function
      const easeOutQuart = 1 - Math.pow(1 - progress, 4);
      const current = start + (end - start) * easeOutQuart;
      
      let formattedNumber;
      if (isMonetary) {
        formattedNumber = new Intl.NumberFormat('en-LK').format(Math.round(current));
      } else {
        formattedNumber = Math.round(current).toLocaleString();
      }
      
      element.textContent = prefix + formattedNumber + suffix;
      
      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        element.textContent = originalText; // Restore original formatting
      }
    }
    
    requestAnimationFrame(update);
  }

  /* ---------- Keyboard Shortcuts ---------- */
  function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + K to focus search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('orderSearch') || document.getElementById('itemSearch');
        if (searchInput) {
          searchInput.focus();
          searchInput.select();
        }
      }
      
      // Escape to clear search
      if (e.key === 'Escape') {
        const activeSearch = document.activeElement;
        if (activeSearch && (activeSearch.id === 'orderSearch' || activeSearch.id === 'itemSearch')) {
          activeSearch.value = '';
          activeSearch.dispatchEvent(new Event('input'));
          activeSearch.blur();
        }
      }
    });
  }

  /* ---------- Utility Functions ---------- */
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /* ---------- Dark Mode Toggle ---------- */
  function setupDarkModeToggle() {
    const darkModeBtn = document.createElement('button');
    darkModeBtn.className = 'btn btn-sm btn-outline-secondary position-fixed bottom-0 end-0 m-3 rounded-circle';
    darkModeBtn.style.cssText = 'width: 50px; height: 50px; z-index: 1000; transition: all 0.3s ease;';
    darkModeBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
    darkModeBtn.setAttribute('data-bs-toggle', 'tooltip');
    darkModeBtn.setAttribute('title', 'Toggle dark mode');
    
    document.body.appendChild(darkModeBtn);
    
    // Initialize tooltip
    new bootstrap.Tooltip(darkModeBtn);
    
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
      toggleDarkMode(true);
    }
    
    darkModeBtn.addEventListener('click', function() {
      const currentDarkMode = document.documentElement.hasAttribute('data-dark-mode');
      toggleDarkMode(!currentDarkMode);
      localStorage.setItem('darkMode', !currentDarkMode);
    });
  }

  function toggleDarkMode(enable) {
    const icon = document.querySelector('.position-fixed .fa-moon, .position-fixed .fa-sun');
    
    if (enable) {
      document.documentElement.setAttribute('data-dark-mode', '');
      if (icon) {
        icon.className = 'fa-solid fa-sun';
      }
      // Add dark mode styles
      document.documentElement.style.setProperty('--bs-body-bg', '#1a202c');
      document.documentElement.style.setProperty('--bs-body-color', '#e2e8f0');
    } else {
      document.documentElement.removeAttribute('data-dark-mode');
      if (icon) {
        icon.className = 'fa-solid fa-moon';
      }
      // Remove dark mode styles
      document.documentElement.style.removeProperty('--bs-body-bg');
      document.documentElement.style.removeProperty('--bs-body-color');
    }
  }

  /* ---------- Initialize Enhancements ---------- */
  // Only run animations if user hasn't indicated they prefer reduced motion
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    observeCards();
    setTimeout(animateNumbers, 500); // Delay for better visual effect
  }
  
  setupKeyboardShortcuts();
  setupDarkModeToggle();

  /* ---------- Final Initialization Message ---------- */
  console.log('🚀 RB Stores Dashboard Enhanced - All features loaded successfully!');

}); // End DOMContentLoaded

/* ---------- Global Utility Functions ---------- */

// Global function to refresh dashboard data
window.refreshDashboard = function() {
  console.log('Refreshing dashboard...');
  setTimeout(() => {
    location.reload();
  }, 1000);
};

// Global function to print dashboard
window.printDashboard = function() {
  window.print();
};
</script>

</body>
</html>
<?php
$conn->close();
if (ob_get_level()>0) ob_end_flush();
?>