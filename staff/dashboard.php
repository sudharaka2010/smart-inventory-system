<?php
// admin/dashboard.php

/* ---------------- Session & Auth ---------------- */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php"); exit();
}

/* ---------------- Security Headers (safe) ---------------- */
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    // Allows inline CSS below
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob: https:; img-src 'self' data: https: blob:; frame-ancestors 'self';");
}

/* ---------------- Includes ---------------- */
include 'header.php';
include 'sidebar.php';
include('../includes/db_connect.php'); // mysqli $conn

/* ---------------- Helpers ---------------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function scalar(mysqli $conn, string $sql, $fallback = 0){
    try{ $res=$conn->query($sql); if($res){ $row=$res->fetch_row(); if($row && isset($row[0])) return $row[0]; } }
    catch(mysqli_sql_exception $e){ error_log("SQL error: ".$e->getMessage()." | SQL: ".$sql); }
    return $fallback;
}
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function trendPct($current,$previous){ if($previous<=0) return $current>0?100:0; return round((($current-$previous)/$previous)*100,1); }

/* ---------------- KPI Data ---------------- */
$totalItems   = scalar($conn,"SELECT COALESCE(SUM(Quantity),0) FROM inventoryitem");
$distinctSKUs = scalar($conn,"SELECT COUNT(*) FROM inventoryitem");
$lowStock     = scalar($conn,"SELECT COUNT(*) FROM inventoryitem WHERE Quantity <= 5");
$outOfStock   = scalar($conn,"SELECT COUNT(*) FROM inventoryitem WHERE Quantity <= 0");

$supplierCount = scalar($conn,"SELECT COUNT(*) FROM supplier");
$customerCount = scalar($conn,"SELECT COUNT(*) FROM customer");

$pendingDeliveries = scalar($conn,"SELECT COUNT(*) FROM transport WHERE `STATUS`='Pending'");
$inTransit         = scalar($conn,"SELECT COUNT(*) FROM transport WHERE `STATUS`='In Transit'");

$today          = date('Y-m-d');
$monthStart     = date('Y-m-01');
$prevMonthStart = date('Y-m-01', strtotime('-1 month'));
$prevMonthEnd   = date('Y-m-t',  strtotime('-1 month'));

$todaySales = scalar($conn,"
  SELECT COALESCE(SUM(od.Subtotal),0)
  FROM `order` o JOIN `orderdetails` od ON od.OrderID=o.OrderID
  WHERE DATE(o.OrderDate)='{$conn->real_escape_string($today)}'
");
$thisMonthRevenue = scalar($conn,"
  SELECT COALESCE(SUM(od.Subtotal),0)
  FROM `order` o JOIN `orderdetails` od ON od.OrderID=o.OrderID
  WHERE DATE(o.OrderDate) BETWEEN '{$conn->real_escape_string($monthStart)}' AND CURDATE()
");
$prevMonthRevenue = scalar($conn,"
  SELECT COALESCE(SUM(od.Subtotal),0)
  FROM `order` o JOIN `orderdetails` od ON od.OrderID=o.OrderID
  WHERE DATE(o.OrderDate) BETWEEN '{$conn->real_escape_string($prevMonthStart)}' AND '{$conn->real_escape_string($prevMonthEnd)}'
");
$monthTrend = trendPct($thisMonthRevenue,$prevMonthRevenue);

/* ---------------- Lists ---------------- */
$lowStockList = $conn->query("
  SELECT ItemID, NAME AS Name, Quantity, Category
  FROM inventoryitem
  WHERE Quantity <= 5
  ORDER BY Quantity ASC, NAME ASC
  LIMIT 8
");
$pendingList = $conn->query("
  SELECT TransportID, VehicleID, Destination, `STATUS` AS Status
  FROM transport
  WHERE `STATUS`='Pending'
  ORDER BY TransportID DESC
  LIMIT 8
");
$recentItems = $conn->query("
  SELECT ItemID, NAME AS Name, Quantity, ReceiveDate
  FROM inventoryitem
  ORDER BY ReceiveDate DESC, ItemID DESC
  LIMIT 8
");

/* ---------------- Search ---------------- */
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchRows = null;
if ($search !== '') {
  $q = $conn->real_escape_string($search);
  $searchRows = $conn->query("
    SELECT ItemID, NAME AS Name, Quantity, Category, ReceiveDate
    FROM inventoryitem
    WHERE NAME LIKE '%$q%' OR Category LIKE '%$q%' OR CAST(ItemID AS CHAR) LIKE '%$q%'
    ORDER BY NAME ASC
    LIMIT 25
  ");
}
?>

<style>
/* ============ RB Stores Dashboard (scoped, light-only) ============ */
/* Nothing here touches header/sidebar/footer because it's scoped to #rb-dashboard */

#rb-dashboard{ 
  --sidebar-w: 260px;           /* adjust if your sidebar differs */
  --page-pad: 24px;
  --bg: #f3f4f6;
  --card:#ffffff;
  --text:#0f172a;
  --muted:#64748b;
  --border:#e5e7eb;

  --primary-1:#3b82f6; --primary-2:#8b5cf6;
  --info-1:#0ea5e9; --success:#10b981; --warn:#f59e0b; --danger:#ef4444; --slate:#64748b;

  --r-xs:6px; --r:10px; --r-lg:12px;
  --sh-1:0 4px 10px rgba(0,0,0,.10);
  --sh-2:0 6px 16px rgba(2,6,23,.08);

  background: var(--bg);
  color: var(--text);
  padding: var(--page-pad);
  margin-left: var(--sidebar-w);   /* leave room for sidebar (desktop) */
  min-height: calc(100vh - 80px);  /* keeps content above footer */
}
@media (max-width: 1024px){
  #rb-dashboard{ margin-left: 0; } /* sidebar is stacked/overlay on mobile */
}

/* Heading */
#rb-dashboard .highlight{ color:#6366f1; font-weight:600; }

/* Search */
#rb-dashboard .dash-search{ display:flex; gap:10px; align-items:center; margin:10px 0 18px; }
#rb-dashboard .dash-search input[type="text"]{
  flex:1; padding:10px 12px; border:1px solid var(--border); background:var(--card); color:var(--text);
  border-radius:var(--r); outline:none; transition:box-shadow .18s,border-color .18s;
}
#rb-dashboard .dash-search input::placeholder{ color:var(--muted); }
#rb-dashboard .dash-search input:focus{ border-color:rgba(99,102,241,.6); box-shadow:0 0 0 3px rgba(99,102,241,.15); }
#rb-dashboard .dash-search button{
  padding:10px 14px; border-radius:var(--r); border:none; cursor:pointer;
  background:linear-gradient(145deg,var(--primary-1),var(--primary-2));
  color:#fff; font-weight:600; display:inline-flex; gap:8px; align-items:center;
}
#rb-dashboard .dash-search .btn-clear{ padding:9px 12px; border-radius:var(--r); text-decoration:none; color:#334155; background:var(--border); }
@media (max-width:560px){
  #rb-dashboard .dash-search{ flex-direction:column; align-items:stretch; gap:8px; }
  #rb-dashboard .dash-search .btn-clear{ text-align:center; }
}

/* KPI Grid */
#rb-dashboard .status-grid{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
@media (max-width:1280px){ #rb-dashboard .status-grid{ grid-template-columns:repeat(3,minmax(0,1fr)); } }
@media (max-width:980px){  #rb-dashboard .status-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:560px){  #rb-dashboard .status-grid{ grid-template-columns:1fr; } }

#rb-dashboard .status-card{
  background:var(--card); color:var(--text);
  border-radius:var(--r); box-shadow:var(--sh-1);
  padding:14px; min-height:110px; display:flex; flex-direction:column; justify-content:center; align-items:flex-start;
}
#rb-dashboard .status-card i{ font-size:22px; margin-bottom:8px; }
#rb-dashboard .status-card h4{ margin:4px 0 6px; font-size:15px; font-weight:700; }
#rb-dashboard .status-card p{ margin:0; font-size:18px; font-weight:700; }
#rb-dashboard .status-card-link{ display:block; text-decoration:none; color:inherit; border-radius:var(--r); }

#rb-dashboard .kpi .subtext{ display:block; margin-top:6px; font-size:12px; opacity:.92; }
#rb-dashboard .kpi.info{    background:linear-gradient(145deg,var(--info-1),#6366f1); color:#fff; }
#rb-dashboard .kpi.warn{    background:linear-gradient(145deg,var(--warn),var(--danger)); color:#fff; }
#rb-dashboard .kpi.success{ background:linear-gradient(145deg,var(--success),var(--primary-1)); color:#fff; }
#rb-dashboard .kpi.neutral{ background:linear-gradient(145deg,var(--slate),#94a3b8); color:#fff; }
#rb-dashboard .trend{ display:inline-block; margin-top:6px; padding:4px 8px; border-radius:999px; font-size:12px; background:rgba(255,255,255,.18); }
#rb-dashboard .trend.up{ border:1px solid rgba(16,185,129,.6); } 
#rb-dashboard .trend.down{ border:1px solid rgba(239,68,68,.6); }

/* 3-column blocks */
#rb-dashboard .cards-3col{ margin-top:18px; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
@media (max-width:1100px){ #rb-dashboard .cards-3col{ grid-template-columns:1fr 1fr; } }
@media (max-width:760px){  #rb-dashboard .cards-3col{ grid-template-columns:1fr; } }

#rb-dashboard .card-block{ background:var(--card); color:var(--text); border-radius:var(--r-lg); padding:14px 14px 6px; box-shadow:var(--sh-2); border:1px solid var(--border); }
#rb-dashboard .card-block-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
#rb-dashboard .card-block-head h4{ margin:0; font-size:16px; font-weight:700; color:var(--text); display:inline-flex; gap:8px; align-items:center; }
#rb-dashboard .card-block-head .mini-link{ font-size:12px; color:#4f46e5; text-decoration:none; }
#rb-dashboard .card-block-head .mini-link:hover{ text-decoration:underline; }

/* Tables */
#rb-dashboard .table-wrap{ overflow:auto; border-radius:var(--r); border:1px solid var(--border); background:var(--card); width:100%; -webkit-overflow-scrolling:touch; }
#rb-dashboard .dash-table{ width:100%; min-width:520px; border-collapse:separate; border-spacing:0; }
#rb-dashboard .dash-table thead th{
  background:#f8fafc; color:#334155; text-align:left; padding:10px 12px; font-weight:600; border-bottom:1px solid var(--border);
  position:sticky; top:0; z-index:1;
}
#rb-dashboard .dash-table tbody td{ padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:14px; color:var(--text); }
#rb-dashboard .dash-table tbody tr:last-child td{ border-bottom:none; }
#rb-dashboard .dash-table tbody tr.warn td{ background:#fff7ed; }
#rb-dashboard .dash-table tbody tr.danger td{ background:#fef2f2; }
#rb-dashboard .muted{ color:var(--muted); }
#rb-dashboard .badge{ padding:4px 8px; font-size:12px; border-radius:999px; }
#rb-dashboard .badge-pending{ background:#fff7ed; color:#b45309; border:1px solid #fed7aa; }

#rb-dashboard .mt-16{ margin-top:16px; }

/* Print */
@media print{
  #rb-dashboard .dash-search,
  #rb-dashboard .status-panel,
  #rb-dashboard .kpi,
  #rb-dashboard .cards-3col .card-block:not(:first-child){ display:none !important; }
  #rb-dashboard{ margin-left:0; padding:0; }
  #rb-dashboard .cards-3col{ grid-template-columns:1fr !important; gap:0 !important; }
  #rb-dashboard .card-block{ box-shadow:none; border:1px solid #ddd; }
  #rb-dashboard .dash-table thead th, 
  #rb-dashboard .dash-table tbody td{ padding:6px 8px; font-size:12px; }
}
</style>

<main id="rb-dashboard">
  <h2>Welcome to <span class="highlight">RB Stores Dashboard</span></h2>
  <p>Select a menu item to get started.</p>

  <!-- Search -->
  <form class="dash-search" method="get" action="">
    <input type="text" name="q" value="<?= esc($search) ?>" placeholder="Search items by name, category or ID..." />
    <button type="submit"><i class="fas fa-search"></i> Search</button>
    <?php if ($search !== ''): ?><a class="btn-clear" href="dashboard.php">Clear</a><?php endif; ?>
  </form>

  <!-- KPI -->
  <section class="status-panel">
    <h3 style="margin:8px 0 12px;">Live Inventory Status</h3>
    <div class="status-grid kpi-grid">
      <a href="inventory.php" class="status-card-link">
        <div class="status-card kpi">
          <i class="fas fa-cubes"></i>
          <h4>Total Quantity</h4>
          <p><?= number_format($totalItems) ?></p>
          <span class="subtext">across <?= number_format($distinctSKUs) ?> items</span>
        </div>
      </a>

      <a href="low_stock.php" class="status-card-link">
        <div class="status-card kpi warn">
          <i class="fas fa-warehouse"></i>
          <h4>Low Stock (≤5)</h4>
          <p><?= number_format($lowStock) ?></p>
          <span class="subtext">Out of Stock: <?= number_format($outOfStock) ?></span>
        </div>
      </a>

      <a href="pending_deliveries.php" class="status-card-link">
        <div class="status-card kpi info">
          <i class="fas fa-truck-loading"></i>
          <h4>Pending Deliveries</h4>
          <p><?= number_format($pendingDeliveries) ?></p>
          <span class="subtext">In Transit: <?= number_format($inTransit) ?></span>
        </div>
      </a>

      <!-- Sales Today → sales_report.php -->
      <a href="sales_report.php" class="status-card-link">
        <div class="status-card kpi success">
          <i class="fas fa-coins"></i>
          <h4>Sales Today</h4>
          <p>LKR <?= number_format($todaySales, 2) ?></p>
          <span class="subtext"><?= esc(date('M d')) ?></span>
        </div>
      </a>

      <!-- Revenue (This Month) - static (keep or wire later) -->
      <div class="status-card kpi">
        <i class="fas fa-chart-line"></i>
        <h4>Revenue (This Month)</h4>
        <p>LKR <?= number_format($thisMonthRevenue, 2) ?></p>
        <span class="trend <?= $monthTrend >= 0 ? 'up' : 'down' ?>">
          <?= $monthTrend >= 0 ? '▲' : '▼' ?> <?= abs($monthTrend) ?>% vs last month
        </span>
      </div>

      <!-- Suppliers → supplier.php -->
      <a href="supplier.php" class="status-card-link">
        <div class="status-card kpi neutral">
          <i class="fas fa-address-book"></i>
          <h4>Suppliers</h4>
          <p><?= number_format($supplierCount) ?></p>
          <span class="subtext">Customers: <?= number_format($customerCount) ?></span>
        </div>
      </a>
    </div>
  </section>

  <!-- 3 columns -->
  <section class="cards-3col">
    <!-- Low Stock -->
    <div class="card-block">
      <div class="card-block-head">
        <h4><i class="fas fa-exclamation-triangle"></i> Low Stock</h4>
        <a href="low_stock.php" class="mini-link">View all</a>
      </div>
      <div class="table-wrap">
        <table class="dash-table">
          <thead><tr><th>Item</th><th>Qty</th><th>Category</th></tr></thead>
          <tbody>
          <?php if ($lowStockList && $lowStockList->num_rows): while($r = $lowStockList->fetch_assoc()): ?>
            <tr class="<?= ((int)$r['Quantity']<=0 ? 'danger' : ((int)$r['Quantity']<=3 ? 'warn' : '')) ?>">
              <td>#<?= esc($r['ItemID']) ?> — <?= esc($r['Name']) ?></td>
              <td><?= esc($r['Quantity']) ?></td>
              <td><?= esc($r['Category']) ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="3" class="muted">Great! No low-stock items.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pending Deliveries -->
    <div class="card-block">
      <div class="card-block-head">
        <h4><i class="fas fa-truck"></i> Pending Deliveries</h4>
        <a href="pending_deliveries.php" class="mini-link">View all</a>
      </div>
      <div class="table-wrap">
        <table class="dash-table">
          <thead><tr><th>ID</th><th>Vehicle</th><th>Destination</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($pendingList && $pendingList->num_rows): while($r = $pendingList->fetch_assoc()): ?>
            <tr>
              <td><?= esc($r['TransportID']) ?></td>
              <td><?= esc($r['VehicleID']) ?></td>
              <td><?= esc($r['Destination']) ?></td>
              <td><span class="badge badge-pending"><?= esc($r['Status']) ?></span></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="4" class="muted">No pending deliveries.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recently Received -->
    <div class="card-block">
      <div class="card-block-head">
        <h4><i class="fas fa-clock"></i> Recently Received</h4>
        <a href="inventory.php" class="mini-link">Open inventory</a>
      </div>
      <div class="table-wrap">
        <table class="dash-table">
          <thead><tr><th>Item</th><th>Qty</th><th>Received</th></tr></thead>
          <tbody>
          <?php if ($recentItems && $recentItems->num_rows): while($r = $recentItems->fetch_assoc()): ?>
            <tr>
              <td>#<?= esc($r['ItemID']) ?> — <?= esc($r['Name']) ?></td>
              <td><?= esc($r['Quantity']) ?></td>
              <td><?= esc($r['ReceiveDate']) ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="3" class="muted">No recent entries.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Search results -->
  <?php if ($search !== ''): ?>
  <section class="card-block mt-16">
    <div class="card-block-head"><h4><i class="fas fa-search"></i> Search Results</h4></div>
    <div class="table-wrap">
      <table class="dash-table">
        <thead><tr><th>Item</th><th>Qty</th><th>Category</th><th>Received</th></tr></thead>
        <tbody>
          <?php if ($searchRows && $searchRows->num_rows): while($r = $searchRows->fetch_assoc()): ?>
            <tr>
              <td>#<?= esc($r['ItemID']) ?> — <?= esc($r['Name']) ?></td>
              <td><?= esc($r['Quantity']) ?></td>
              <td><?= esc($r['Category']) ?></td>
              <td><?= esc($r['ReceiveDate']) ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="4" class="muted">No matches for “<?= esc($search) ?>”.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
