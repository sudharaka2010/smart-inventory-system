<?php
/* ---------------------------------------------------------
   RB Stores — View Inventory Item (Light Theme)
   File: view_inventory.php
   Requires: header.php, sidebar.php, footer.php
   DB: rb_stores_db (MariaDB 10.4+), PHP 8.2+
   --------------------------------------------------------- */

declare(strict_types=1);
const TZ = 'Asia/Colombo';
date_default_timezone_set(TZ);

/* ---------- 0) SMALL HELPERS ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function _tempDbConnect(): PDO {
    $host = '127.0.0.1';
    $db   = 'rb_stores_db';
    $user = 'root';
    $pass = '';
    $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    return new PDO($dsn, $user, $pass, $opts);
}

/* ---------- 1) SINGLE-ITEM CSV EXPORT (before any output) ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isset($_GET['id'])) {
    $id  = max(0, (int)$_GET['id']);
    if ($id > 0) {
        $pdo = _tempDbConnect();
        $sql = "
            SELECT
              ii.ItemID, ii.InvoiceID, ii.NAME AS ItemName, ii.Description, ii.Category,
              COALESCE(ii.SupplierName, s.NAME) AS Supplier,
              ii.Price, ii.Quantity AS StockIn,
              COALESCE(so.sold_qty, 0)  AS SoldQty,
              COALESCE(r.ret_qty, 0)    AS ReturnedQty,
              (ii.Quantity - COALESCE(so.sold_qty,0) - COALESCE(r.ret_qty,0)) AS StockOnHand,
              ii.ReceiveDate
            FROM inventoryitem ii
            LEFT JOIN supplier s ON s.SupplierID = ii.SupplierID
            LEFT JOIN (
              SELECT od.ItemID, SUM(od.Quantity) AS sold_qty
              FROM orderdetails od GROUP BY od.ItemID
            ) so ON so.ItemID = ii.ItemID
            LEFT JOIN (
              SELECT ri.ItemID, SUM(ri.ReturnQuantity) AS ret_qty
              FROM returnitem ri GROUP BY ri.ItemID
            ) r ON r.ItemID = ii.ItemID
            WHERE ii.ItemID = :id
            LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':id'=>$id]);
        $row = $st->fetch();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=item_'.$id.'_'.date('Ymd_His').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($row?:[]));
        if ($row) fputcsv($out, $row);
        fclose($out);
        exit;
    }
}

/* ---------- 2) INCLUDES ---------- */
session_start();
include 'header.php';
include 'sidebar.php';

/* ---------- 3) DB (reuse $pdo if header.php made it) ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = _tempDbConnect();
}

/* ---------- 4) GET & LOAD ITEM ---------- */
$id = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;
if ($id <= 0) {
    echo "<div class='container-fluid p-4'><div class='alert alert-warning'>Invalid item id.</div></div>";
    include 'footer.php'; exit;
}

$itemSql = "
  SELECT
    ii.ItemID, ii.InvoiceID, ii.NAME AS ItemName, ii.Description, ii.Price,
    ii.Quantity AS StockIn, ii.Category, ii.SupplierID, ii.SupplierName,
    COALESCE(ii.SupplierName, s.NAME) AS Supplier,
    ii.ReceiveDate
  FROM inventoryitem ii
  LEFT JOIN supplier s ON s.SupplierID = ii.SupplierID
  WHERE ii.ItemID = :id
  LIMIT 1";
$st = $pdo->prepare($itemSql);
$st->execute([':id'=>$id]);
$item = $st->fetch();

if (!$item) {
    echo "<div class='container-fluid p-4'><div class='alert alert-info'>Item not found.</div></div>";
    include 'footer.php'; exit;
}

/* Stock aggregates */
$kpiSql = "
  SELECT
    COALESCE(SUM(od.Quantity),0) AS sold_qty
  FROM orderdetails od
  WHERE od.ItemID = :id";
$stK = $pdo->prepare($kpiSql);
$stK->execute([':id'=>$id]);
$kpiSold = (float)$stK->fetchColumn();

$retSql = "
  SELECT COALESCE(SUM(ri.ReturnQuantity),0) AS ret_qty
  FROM returnitem ri
  WHERE ri.ItemID = :id";
$stR = $pdo->prepare($retSql);
$stR->execute([':id'=>$id]);
$kpiRet = (float)$stR->fetchColumn();

$stockIn  = (float)$item['StockIn'];
$onHand   = max(0, $stockIn - $kpiSold - $kpiRet);

/* Recent sales (last 8 order rows for this item) */
$salesSql = "
  SELECT od.OrderDetailID, od.Quantity, od.Subtotal,
         o.OrderID, o.InvoiceID AS OrderInvoice, o.OrderDate, o.CustomerID
  FROM orderdetails od
  LEFT JOIN `order` o ON o.OrderID = od.OrderID
  WHERE od.ItemID = :id
  ORDER BY od.OrderDetailID DESC
  LIMIT 8";
$stS = $pdo->prepare($salesSql);
$stS->execute([':id'=>$id]);
$sales = $stS->fetchAll();

/* Formatters */
$priceFmt = number_format((float)$item['Price'], 2);
$stockInFmt = number_format($stockIn);
$soldFmt = number_format($kpiSold);
$retFmt = number_format($kpiRet);
$onHandFmt = number_format($onHand);
$received = $item['ReceiveDate'] ? date('Y-m-d H:i', strtotime($item['ReceiveDate'])) : '—';

?>
<style>
/* ------- Light (white) page-local styles to match your inventory page ------- */
:root{
  --rb-sidebar-w: 260px;
  --rb-bg:#f7f8fb; --rb-card:#ffffff; --rb-text:#0f172a; --rb-muted:#64748b;
  --rb-accent:#2563eb; --rb-good:#16a34a; --rb-warn:#d97706; --rb-danger:#dc2626; --rb-border:#e5e7eb;
  --rb-table-head:#f1f5f9; --rb-table-row:#ffffff; --rb-table-row-alt:#f9fafb; --rb-link:#1d4ed8;
}
.rb-main{ margin-left:var(--rb-sidebar-w); padding:24px; background:var(--rb-bg); min-height:calc(100vh - 60px) }
@media (max-width:1024px){ .rb-main{ margin-left:0 } }

.rb-wrap{max-width:1100px; margin:0 auto; color:var(--rb-text); font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
.rb-row{display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap}
.rb-h1{font-size:22px; margin:0; font-weight:700}
.rb-breadcrumb{font-size:12px; color:var(--rb-muted)}
.rb-breadcrumb a{ color:var(--rb-link); text-decoration:none }
.rb-breadcrumb a:hover{ text-decoration:underline }

.rb-card{background:var(--rb-card); border:1px solid var(--rb-border); border-radius:16px; padding:16px; box-shadow:0 6px 16px rgba(15,23,42,.06)}
.rb-grid{display:grid; gap:16px}
.rb-grid-2{grid-template-columns: 1.2fr .8fr}
@media (max-width:900px){ .rb-grid-2{grid-template-columns: 1fr} }

.rb-kpis{display:grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap:12px}
@media (max-width:1000px){ .rb-kpis{grid-template-columns: repeat(3, minmax(0,1fr))} }
@media (max-width:560px){ .rb-kpis{grid-template-columns: repeat(2, minmax(0,1fr))} }
.rb-kpi{border:1px solid var(--rb-border); border-radius:12px; padding:12px; background:#fff}
.rb-kpi-t{font-size:12px; color:var(--rb-muted)}
.rb-kpi-v{font-weight:700; font-size:18px; margin-top:4px}

.rb-def{display:grid; grid-template-columns: 160px 1fr; gap:6px 12px; font-size:14px}
.rb-def dt{color:var(--rb-muted)}
.rb-def dd{margin:0}

.rb-actions{display:flex; gap:8px; flex-wrap:wrap}
.rb-btn{display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid var(--rb-border); background:#ffffff; color:var(--rb-text); text-decoration:none; cursor:pointer}
.rb-btn:hover{background:#f8fafc}
.rb-btn-primary{ background:var(--rb-accent); border-color:transparent; color:#fff }
.rb-btn-danger{ background:#fff; border-color:#fecaca; color:#b91c1c }
.rb-btn-danger:hover{ background:#fff0f0 }

.rb-table-wrap{overflow:auto; border-radius:14px; border:1px solid var(--rb-border); background:#fff}
.rb-table{width:100%; border-collapse:separate; border-spacing:0; min-width:760px}
.rb-th,.rb-td{padding:10px 12px; border-bottom:1px solid var(--rb-border); text-align:left}
.rb-th{position:sticky; top:0; background:var(--rb-table-head); z-index:1; font-size:12px; color:var(--rb-muted)}
.rb-tr:nth-child(even) .rb-td{background:var(--rb-table-row-alt)}
.rb-tr:nth-child(odd)  .rb-td{background:var(--rb-table-row)}
.rb-tr:hover .rb-td{background:#eef2ff}
.rb-num{text-align:right; font-variant-numeric: tabular-nums}

/* Print */
@media print{
  .rb-main{ margin-left:0; padding:0; background:#fff }
  .rb-card{ box-shadow:none }
  .no-print{ display:none !important }
}
</style>

<main class="rb-main">
  <div class="rb-wrap">
    <div class="rb-breadcrumb">
      <a href="inventory.php">Inventory</a> › Item #<?= (int)$item['ItemID'] ?>
    </div>
    <div class="rb-row" style="margin-top:8px">
      <h1 class="rb-h1"><?= h($item['ItemName']) ?></h1>
      <div class="rb-actions">
        <a class="rb-btn" href="?id=<?= (int)$item['ItemID'] ?>&export=csv">Export CSV</a>
        <button class="rb-btn" onclick="window.print()">Print</button>
        <a class="rb-btn" href="edit_inventory.php?id=<?= (int)$item['ItemID'] ?>">Edit</a>
        <a class="rb-btn rb-btn-danger" href="delete_inventory.php?id=<?= (int)$item['ItemID'] ?>" onclick="return confirm('Delete Item #<?= (int)$item['ItemID'] ?> ?');">Delete</a>
      </div>
    </div>

    <div class="rb-grid rb-grid-2" style="margin-top:16px">
      <!-- Left: Details + KPIs -->
      <div class="rb-card">
        <div class="rb-kpis">
          <div class="rb-kpi">
            <div class="rb-kpi-t">Price (Rs.)</div>
            <div class="rb-kpi-v"><?= $priceFmt ?></div>
          </div>
          <div class="rb-kpi">
            <div class="rb-kpi-t">Stock In</div>
            <div class="rb-kpi-v"><?= $stockInFmt ?></div>
          </div>
          <div class="rb-kpi">
            <div class="rb-kpi-t">Sold</div>
            <div class="rb-kpi-v"><?= $soldFmt ?></div>
          </div>
          <div class="rb-kpi">
            <div class="rb-kpi-t">Returned</div>
            <div class="rb-kpi-v"><?= $retFmt ?></div>
          </div>
          <div class="rb-kpi">
            <div class="rb-kpi-t">On Hand</div>
            <div class="rb-kpi-v"><?= $onHandFmt ?></div>
          </div>
        </div>

        <hr style="margin:16px 0; border-top:1px solid var(--rb-border)">

        <dl class="rb-def">
          <dt>Item ID</dt><dd>#<?= (int)$item['ItemID'] ?></dd>
          <dt>Invoice ID</dt><dd><?= h($item['InvoiceID']) ?></dd>
          <dt>Category</dt><dd><?= h($item['Category'] ?: 'Unknown') ?></dd>
          <dt>Supplier</dt><dd><?= h($item['Supplier'] ?: '—') ?></dd>
          <dt>Receive Date</dt><dd><?= h($received) ?></dd>
          <dt>Description</dt><dd><?= nl2br(h($item['Description'] ?: '—')) ?></dd>
        </dl>
      </div>

      <!-- Right: Meta/Supplier quick view -->
      <div class="rb-card">
        <h3 style="margin:0 0 12px; font-size:16px">Supplier & Meta</h3>
        <dl class="rb-def">
          <dt>Supplier ID</dt><dd><?= $item['SupplierID'] ? (int)$item['SupplierID'] : '—' ?></dd>
          <dt>Supplier Name</dt><dd><?= h($item['Supplier']) ?></dd>
          <dt>Invoice</dt><dd><?= h($item['InvoiceID']) ?></dd>
          <dt>Receive Date</dt><dd><?= h($received) ?></dd>
          <dt>Unit Price</dt><dd>Rs. <?= $priceFmt ?></dd>
        </dl>
        <div class="rb-actions" style="margin-top:12px">
          <a class="rb-btn" href="inventory.php">Back to Inventory</a>
          <a class="rb-btn" href="edit_inventory.php?id=<?= (int)$item['ItemID'] ?>">Edit Item</a>
        </div>
      </div>
    </div>

    <!-- Recent Sales for this item -->
    <div class="rb-card" style="margin-top:16px">
      <div class="rb-row" style="margin-bottom:8px">
        <h3 style="margin:0; font-size:16px">Recent Sales (This Item)</h3>
        <span style="color:var(--rb-muted); font-size:12px">Last 8 entries</span>
      </div>
      <div class="rb-table-wrap">
        <table class="rb-table">
          <thead>
            <tr class="rb-tr">
              <th class="rb-th">Order ID</th>
              <th class="rb-th">Order Invoice</th>
              <th class="rb-th">Order Date</th>
              <th class="rb-th rb-num">Qty</th>
              <th class="rb-th rb-num">Subtotal (Rs.)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$sales): ?>
              <tr class="rb-tr"><td class="rb-td" colspan="5" style="text-align:center; color:var(--rb-muted)">No sales recorded for this item.</td></tr>
            <?php else: foreach ($sales as $s): ?>
              <tr class="rb-tr">
                <td class="rb-td"><?= (int)$s['OrderID'] ?></td>
                <td class="rb-td"><?= h($s['OrderInvoice'] ?: '—') ?></td>
                <td class="rb-td"><?= h($s['OrderDate'] ?: '—') ?></td>
                <td class="rb-td rb-num"><?= number_format((float)$s['Quantity']) ?></td>
                <td class="rb-td rb-num"><?= number_format((float)$s['Subtotal'], 2) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<?php include 'footer.php'; ?>
