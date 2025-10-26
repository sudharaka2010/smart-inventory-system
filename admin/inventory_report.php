<?php
/* ---------------------------------------------------------
   RB Stores — Inventory Report (with header/sidebar/footer includes)
   File: inventory_report.php
   Requires: header.php, sidebar.php, footer.php
   DB: rb_stores_db (MariaDB 10.4+), PHP 8.2+
   --------------------------------------------------------- */

const TZ = 'Asia/Colombo';
date_default_timezone_set(TZ);

/* ---------- 0) SAFE CSV EXPORT BRANCH (must run before any output) ---------- */
function get_db_pdo_if_any() {
    // If your header.php sets $pdo globally, we will use it after includes.
    // For CSV export (pre-output), we may need a temporary connection
    // if $pdo isn’t available yet.
    return null;
}

$pendingExport = (isset($_GET['export']) && $_GET['export'] === 'csv');

/* Small helper for connecting only if needed (for export branch) */
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

/* Shared WHERE/HAVING builders (used in both export branch and normal branch) */
/* We keep your big SQL blocks; we only provide context-specific HAVING strings. */
function buildFilters(&$params) {
    $qs = [
        'from'     => $_GET['from']    ?? '',
        'to'       => $_GET['to']      ?? '',
        'supplier' => $_GET['supplier']?? '',
        'category' => $_GET['category']?? '',
        'q'        => $_GET['q']       ?? '',
        'low'      => $_GET['low']     ?? '',
    ];
    $where = [];
    $params = [];

    if ($qs['from'] !== '' && $qs['to'] !== '') {
        $where[] = "ii.ReceiveDate BETWEEN :from AND :to";
        $params[':from'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $qs['from']) ? $qs['from'].' 00:00:00' : $qs['from'];
        $params[':to']   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $qs['to'])   ? $qs['to'].' 23:59:59' : $qs['to'];
    } elseif ($qs['from'] !== '') {
        $where[] = "ii.ReceiveDate >= :from";
        $params[':from'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $qs['from']) ? $qs['from'].' 00:00:00' : $qs['from'];
    } elseif ($qs['to'] !== '') {
        $where[] = "ii.ReceiveDate <= :to";
        $params[':to'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $qs['to']) ? $qs['to'].' 23:59:59' : $qs['to'];
    }

    if ($qs['supplier'] !== '') {
        $where[] = "ii.SupplierID = :supplier";
        $params[':supplier'] = $qs['supplier'];
    }
    if ($qs['category'] !== '') {
        $where[] = "ii.Category = :category";
        $params[':category'] = $qs['category'];
    }
    if ($qs['q'] !== '') {
        $where[] = "(ii.NAME LIKE :kw OR ii.InvoiceID LIKE :kw)";
        $params[':kw'] = '%'.$qs['q'].'%';
    }

    // Context-specific HAVING snippets:
    $havingRows   = ''; // for table rows SELECT (uses alias: stock_on_hand)
    $havingExport = ''; // for CSV export SELECT (uses alias: StockOnHand)
    $havingCountKPI = ''; // for COUNT/KPI subqueries (alias: stock_on_hand we will project)

    if ($qs['low'] !== '' && is_numeric($qs['low']) && (int)$qs['low'] >= 0) {
        $params[':lowth'] = (int)$qs['low'];
        $havingRows       = "HAVING stock_on_hand <= :lowth";
        $havingExport     = "HAVING StockOnHand <= :lowth";
        $havingCountKPI   = "HAVING stock_on_hand <= :lowth"; // we will project stock_on_hand in those subqueries
    }

    $whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";
    return [$whereSql, $havingRows, $havingExport, $havingCountKPI];
}

/* If user requested CSV export, do it now and exit (no HTML output) */
if ($pendingExport) {
    $pdo = _tempDbConnect();
    $params = [];
    [$whereSql, $havingRows, $havingExport, $havingCountKPI] = buildFilters($params);

    $baseSql = "
        FROM inventoryitem ii
        LEFT JOIN supplier s ON s.SupplierID = ii.SupplierID
        LEFT JOIN (
            SELECT od.ItemID, SUM(od.Quantity) AS sold_qty, SUM(od.Subtotal) AS sold_amount
            FROM orderdetails od
            GROUP BY od.ItemID
        ) so ON so.ItemID = ii.ItemID
        LEFT JOIN (
            SELECT ri.ItemID, SUM(ri.ReturnQuantity) AS ret_qty
            FROM returnitem ri
            GROUP BY ri.ItemID
        ) r ON r.ItemID = ii.ItemID
        $whereSql
    ";

    $exportSql = "
        SELECT
            ii.ItemID,
            ii.InvoiceID,
            ii.NAME AS ItemName,
            ii.Description,
            ii.Category,
            COALESCE(ii.SupplierName, s.NAME) AS Supplier,
            ii.Price,
            ii.Quantity AS StockIn,
            COALESCE(so.sold_qty, 0) AS SoldQty,
            COALESCE(r.ret_qty, 0) AS ReturnedQty,
            (ii.Quantity - COALESCE(so.sold_qty,0) - COALESCE(r.ret_qty,0)) AS StockOnHand,
            COALESCE(so.sold_amount,0) AS Revenue,
            ii.ReceiveDate
        $baseSql
        GROUP BY ii.ItemID
        $havingExport
        ORDER BY ii.ReceiveDate DESC, ii.ItemID DESC
    ";

    $st = $pdo->prepare($exportSql);
    $st->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inventory_report_'.date('Ymd_His').'.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ItemID','InvoiceID','ItemName','Description','Category','Supplier','Price','StockIn','SoldQty','ReturnedQty','StockOnHand','Revenue','ReceiveDate']);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $r);
    fclose($out);
    exit;
}

/* ---------- 1) PAGE INCLUDES (no output happened yet) ---------- */
include 'header.php';
include 'sidebar.php';

/* ---------- 2) DB CONNECTION (reuse $pdo from header if present) ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $host = '127.0.0.1';
    $db   = 'rb_stores_db';
    $user = 'root';
    $pass = '';
    $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opts);
}

/* ---------- 3) READ FILTERS/PAGINATION ---------- */
$qs = [
    'from'     => $_GET['from']    ?? '',
    'to'       => $_GET['to']      ?? '',
    'supplier' => $_GET['supplier']?? '',
    'category' => $_GET['category']?? '',
    'q'        => $_GET['q']       ?? '',
    'low'      => $_GET['low']     ?? '',
    'per_page' => max(10, (int)($_GET['per_page'] ?? 25)),
    'page'     => max(1,  (int)($_GET['page']     ?? 1)),
];

$params = [];
[$whereSql, $havingRows, $havingExport, $havingCountKPI] = buildFilters($params);

/* ---------- 4) CORE SQL (same logic as earlier version) ---------- */
$baseSql = "
    FROM inventoryitem ii
    LEFT JOIN supplier s ON s.SupplierID = ii.SupplierID
    LEFT JOIN (
        SELECT od.ItemID, SUM(od.Quantity) AS sold_qty, SUM(od.Subtotal) AS sold_amount
        FROM orderdetails od
        GROUP BY od.ItemID
    ) so ON so.ItemID = ii.ItemID
    LEFT JOIN (
        SELECT ri.ItemID, SUM(ri.ReturnQuantity) AS ret_qty
        FROM returnitem ri
        GROUP BY ri.ItemID
    ) r ON r.ItemID = ii.ItemID
    $whereSql
";

/* Count (project stock_on_hand for HAVING to avoid table-column HAVING issues) */
$countSql = "SELECT COUNT(*) AS c FROM (
    SELECT
        ii.ItemID,
        (ii.Quantity - COALESCE(so.sold_qty,0) - COALESCE(r.ret_qty,0)) AS stock_on_hand
    $baseSql
    GROUP BY ii.ItemID
    $havingCountKPI
) t";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();

/* Pagination */
$perPage = $qs['per_page'];
$page    = $qs['page'];
$offset  = ($page - 1) * $perPage;

/* Rows (unchanged; alias stock_on_hand already in SELECT) */
$rowsSql = "
    SELECT
        ii.ItemID,
        ii.InvoiceID,
        ii.NAME AS item_name,
        ii.Description,
        ii.Price,
        ii.Quantity AS stock_in,
        COALESCE(so.sold_qty, 0)   AS sold_qty,
        COALESCE(so.sold_amount,0) AS revenue,
        COALESCE(r.ret_qty, 0)     AS returned_qty,
        (ii.Quantity - COALESCE(so.sold_qty,0) - COALESCE(r.ret_qty,0)) AS stock_on_hand,
        ii.Category,
        s.SupplierID,
        COALESCE(ii.SupplierName, s.NAME) AS supplier_name,
        ii.ReceiveDate
    $baseSql
    GROUP BY ii.ItemID
    $havingRows
    ORDER BY ii.ReceiveDate DESC, ii.ItemID DESC
    LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($rowsSql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

/* KPIs — compute from a per-item subquery so low-stock HAVING uses alias safely */
$kpiSql = "
    SELECT
        COUNT(*) AS items,
        SUM(GREATEST(t.stock_on_hand, 0)) AS total_on_hand,
        SUM(GREATEST(t.stock_on_hand, 0) * t.Price) AS stock_value,
        SUM(t.sold_qty) AS total_sold_units,
        SUM(t.revenue)  AS total_revenue
    FROM (
        SELECT
            ii.ItemID,
            ii.Price,
            COALESCE(so.sold_qty,0)   AS sold_qty,
            COALESCE(so.sold_amount,0) AS revenue,
            (ii.Quantity - COALESCE(so.sold_qty,0) - COALESCE(r.ret_qty,0)) AS stock_on_hand
        $baseSql
        GROUP BY ii.ItemID
        $havingCountKPI
    ) t
";
$stmtK = $pdo->prepare($kpiSql);
$stmtK->execute($params);
$kpi = $stmtK->fetch() ?: [
    'items'=>0,'total_on_hand'=>0,'stock_value'=>0,'total_sold_units'=>0,'total_revenue'=>0
];

/* Dropdown helpers */
$suppliers  = $pdo->query("SELECT SupplierID, NAME FROM supplier ORDER BY NAME")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT Category FROM inventoryitem ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $extra=[]): string {
    $base = $_GET;
    foreach ($extra as $k=>$v) $base[$k] = $v;
    return http_build_query($base);
}
?>
<!-- CONTENT-ONLY (header.php already opened <body>, sidebar.php rendered the sidebar) -->
<style>
:root{
  --rb-sidebar-w: 260px;

  /* Keep white background */
  --rb-bg:#ffffff;
  --rb-card:#ffffff;

  /* Brand theming */
  --rb-accent:#3b5683;     /* requested color */
  --rb-text:#3b5683;       /* make card/table text match brand */
  --rb-muted:#6b7c97;      /* muted brand-friendly */
  --rb-border:#dfe6f2;     /* soft bluish border */

  /* Status (tuned to brand) */
  --rb-good:#3b5683;       /* OK badge uses brand */
  --rb-warn:#f59e0b;
  --rb-danger:#dc2626;
}

/* Layout */
.rb-main{ margin-left: var(--rb-sidebar-w); padding: 24px; background: var(--rb-bg); min-height: calc(100vh - 60px); }
@media (max-width: 1024px){ .rb-main{ margin-left: 0; } }
.rb-wrap{max-width: 1200px; margin: 0 auto; color: var(--rb-text); font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;}
.rb-row{display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap}
.rb-h1{font-size:20px; margin:0}
.rb-badge{padding:4px 8px; border:1px solid var(--rb-border); border-radius:999px; color:var(--rb-text); background:#eef2f8}
.rb-card{background:var(--rb-card); border:1px solid var(--rb-border); border-radius:12px; padding:16px; box-shadow:0 2px 6px rgba(34,54,82,.08)}
.rb-grid{display:grid; gap:16px}
.rb-kpis{grid-template-columns: repeat(4, minmax(0,1fr));}
@media (max-width: 900px){ .rb-kpis{grid-template-columns: repeat(2, minmax(0,1fr));} }
@media (max-width: 520px){ .rb-kpis{grid-template-columns: 1fr;} }
.rb-kpi-t{font-size:12px; color:var(--rb-muted); margin-bottom:6px}
.rb-kpi-v{font-weight:700; font-size:22px; color:var(--rb-text)}

/* Filters */
.rb-filters{display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap:10px; align-items:end}
@media (max-width: 1000px){ .rb-filters{grid-template-columns: repeat(3, minmax(0,1fr));} }
@media (max-width: 560px){ .rb-filters{grid-template-columns: repeat(2, minmax(0,1fr));} }
.rb-label{display:block; font-size:12px; color:var(--rb-muted); margin-bottom:6px}
.rb-input, .rb-select{ width:100%; background:#ffffff; color:var(--rb-text); border:1px solid var(--rb-border); border-radius:8px; padding:10px 12px; outline:none; }
.rb-input:focus, .rb-select:focus{border-color: var(--rb-accent); box-shadow:0 0 0 2px rgba(59,86,131,.25)}
.rb-actions{display:flex; gap:8px; flex-wrap:wrap}

/* Buttons */
.rb-btn{
  display:inline-block; padding:10px 14px; border-radius:8px; border:1px solid var(--rb-border);
  background:#e9eff7; color:var(--rb-text); text-decoration:none; cursor:pointer; transition:.2s;
}
/* Only apply gray/brand-tint hover to non-primary */
.rb-btn:not(.rb-btn-primary):hover{ background:#dde7f6; }

/* Primary button stays brand on hover */
.rb-btn-primary{
  background:var(--rb-accent);
  border-color:transparent;
  color:#fff;
}
.rb-btn-primary:hover,
.rb-btn-primary:focus-visible{
  background:var(--rb-accent);
  color:#fff;
  filter:brightness(1.05);
  box-shadow:0 0 0 2px rgba(59,86,131,.25);
}
.rb-btn-primary:active{ filter:brightness(0.95); }

.rb-btn-outline{ background:transparent; }
.rb-btn-print{ background:#e9eff7; }

/* Optional: disabled states */
.rb-btn:disabled,
.rb-btn[disabled]{
  opacity:.6; cursor:not-allowed; filter:none;
}

/* Table */
.rb-table-wrap{overflow:auto; border-radius:10px; border:1px solid var(--rb-border)}
.rb-table{width:100%; border-collapse:collapse; min-width:980px}
.rb-th, .rb-td{padding:10px 12px; border-bottom:1px solid var(--rb-border); text-align:left; color:var(--rb-text)}
.rb-th{position:sticky; top:0; background:#eef2f8; z-index:1; font-size:12px; color:var(--rb-text)}
.rb-tr:hover .rb-td{background:#f6f9fc}
.rb-num{text-align:right; font-variant-numeric: tabular-nums}
.rb-badge-low{color:#fff; background:var(--rb-danger); padding:2px 8px; border-radius:999px; font-size:12px}
.rb-badge-ok{color:#fff; background:var(--rb-good); padding:2px 8px; border-radius:999px; font-size:12px}

/* Pagination */
.rb-pagination{display:flex; gap:8px; justify-content:flex-end; margin-top:12px}
.rb-pagination a,.rb-pagination span{padding:8px 12px; border-radius:8px; background:#e9eff7; color:var(--rb-text); border:1px solid var(--rb-border); text-decoration:none}
.rb-pagination a:hover{ background:#dde7f6; }
.rb-pagination .current{background:var(--rb-accent); border-color:transparent; color:#fff}

/* Notes */
.rb-note{color:var(--rb-muted); font-size:12px; margin-top:10px}

/* Print */
@media print{
  .rb-main{ margin-left:0; padding:0; background:#fff; }
  .rb-card{box-shadow:none; border:1px solid #cbd5e1}
  .rb-th{background:#e7edf7; color:#000}
  .rb-wrap{color:#000}
  .no-print{display:none !important}
}
</style>



<main class="rb-main">
  <div class="rb-wrap">
    <div class="rb-row">
      <h1 class="rb-h1">Inventory Report</h1>
      <span class="rb-badge"><?=h(date('Y-m-d H:i'))?> • Asia/Colombo</span>
    </div>

    <!-- KPIs -->
    <div class="rb-grid rb-kpis" style="margin-top:16px">
      <div class="rb-card">
        <div class="rb-kpi-t">Unique Items</div>
        <div class="rb-kpi-v"><?= number_format((float)$kpi['items']) ?></div>
      </div>
      <div class="rb-card">
        <div class="rb-kpi-t">Total On Hand (Units)</div>
        <div class="rb-kpi-v"><?= number_format((float)$kpi['total_on_hand']) ?></div>
      </div>
      <div class="rb-card">
        <div class="rb-kpi-t">Stock Value (Rs.)</div>
        <div class="rb-kpi-v">Rs. <?= number_format((float)$kpi['stock_value'], 2) ?></div>
      </div>
      <div class="rb-card">
        <div class="rb-kpi-t">Revenue (from Sales)</div>
        <div class="rb-kpi-v">Rs. <?= number_format((float)$kpi['total_revenue'], 2) ?></div>
      </div>
    </div>

    <!-- Filters -->
    <div class="rb-card" style="margin-top:16px">
      <form class="rb-filters" method="get" action="">
        <div>
          <label class="rb-label">From (Receive Date)</label>
          <input class="rb-input" type="datetime-local" name="from" value="<?=h($qs['from'])?>">
        </div>
        <div>
          <label class="rb-label">To (Receive Date)</label>
          <input class="rb-input" type="datetime-local" name="to" value="<?=h($qs['to'])?>">
        </div>
        <div>
          <label class="rb-label">Supplier</label>
          <select class="rb-select" name="supplier">
            <option value="">All</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?=$s['SupplierID']?>" <?= ($qs['supplier']==(string)$s['SupplierID']?'selected':'') ?>>
                <?=h($s['NAME'])?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="rb-label">Category</label>
          <select class="rb-select" name="category">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?=h($c)?>" <?= ($qs['category']===$c?'selected':'') ?>><?=h($c?:'Unknown')?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="rb-label">Search (Item/Invoice)</label>
          <input class="rb-input" type="text" name="q" placeholder="e.g., Water peel or INV-00001" value="<?=h($qs['q'])?>">
        </div>
        <div>
          <label class="rb-label">Low Stock ≤</label>
          <input class="rb-input" type="number" min="0" step="1" name="low" placeholder="e.g., 10" value="<?=h($qs['low'])?>">
        </div>

        <div class="rb-actions" style="grid-column: 1 / -1; margin-top:4px">
          <button class="rb-btn rb-btn-primary" type="submit">Apply Filters</button>
          <a class="rb-btn rb-btn-outline" href="?">Reset</a>
          <a class="rb-btn" href="?<?=h(qs(['export'=>'csv','page'=>1]))?>">Export CSV</a>
          <button class="rb-btn rb-btn-print no-print" type="button" onclick="window.print()">Print</button>
          <div style="margin-left:auto"></div>
          <label class="rb-label" style="align-self:center">Per Page</label>
          <select class="rb-select" name="per_page" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $pp): ?>
              <option value="<?=$pp?>" <?= $pp===$qs['per_page']?'selected':'' ?>><?=$pp?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>

    <!-- Table -->
    <div class="rb-table-wrap rb-card" style="margin-top:16px">
      <table class="rb-table">
        <thead>
          <tr class="rb-tr">
            <th class="rb-th">Item ID</th>
            <th class="rb-th">Invoice</th>
            <th class="rb-th">Item</th>
            <th class="rb-th">Category</th>
            <th class="rb-th">Supplier</th>
            <th class="rb-th rb-num">Price (Rs.)</th>
            <th class="rb-th rb-num">Stock In</th>
            <th class="rb-th rb-num">Sold</th>
            <th class="rb-th rb-num">Returned</th>
            <th class="rb-th rb-num">On Hand</th>
            <th class="rb-th rb-num">Stock Value (Rs.)</th>
            <th class="rb-th rb-num">Revenue (Rs.)</th>
            <th class="rb-th">Received</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr class="rb-tr"><td class="rb-td" colspan="13" style="text-align:center; color:var(--rb-muted)">No items match your filter.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r):
              $onHand = max(0, (float)$r['stock_on_hand']);
              $stockValue = $onHand * (float)$r['Price'];
              $low = ($qs['low'] !== '' && is_numeric($qs['low']) && $onHand <= (int)$qs['low']);
            ?>
              <tr class="rb-tr">
                <td class="rb-td"><?=h($r['ItemID'])?></td>
                <td class="rb-td"><?=h($r['InvoiceID'])?></td>
                <td class="rb-td" title="<?=h($r['Description'])?>">
                  <?=h($r['item_name'])?>
                  <?php if ($low): ?>
                    <span class="rb-badge-low" style="margin-left:6px">LOW</span>
                  <?php else: ?>
                    <span class="rb-badge-ok" style="margin-left:6px">OK</span>
                  <?php endif; ?>
                </td>
                <td class="rb-td"><?=h($r['Category'] ?: 'Unknown')?></td>
                <td class="rb-td"><?=h($r['supplier_name'] ?: '—')?></td>
                <td class="rb-td rb-num"><?=number_format((float)$r['Price'],2)?></td>
                <td class="rb-td rb-num"><?=number_format((float)$r['stock_in'])?></td>
                <td class="rb-td rb-num"><?=number_format((float)$r['sold_qty'])?></td>
                <td class="rb-td rb-num"><?=number_format((float)$r['returned_qty'])?></td>
                <td class="rb-td rb-num"><?=number_format($onHand)?></td>
                <td class="rb-td rb-num"><?=number_format($stockValue,2)?></td>
                <td class="rb-td rb-num"><?=number_format((float)$r['revenue'],2)?></td>
                <td class="rb-td"><?=h($r['ReceiveDate'])?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php
      $totalPages = max(1, (int)ceil($totalRows / $perPage));
      if ($totalPages > 1):
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
    ?>
      <div class="rb-pagination no-print">
        <?php if ($page > 1): ?>
          <a href="?<?=h(qs(['page'=>1]))?>">« First</a>
          <a href="?<?=h(qs(['page'=>$page-1]))?>">‹ Prev</a>
        <?php endif; ?>
        <?php for ($p=$start; $p<=$end; $p++): ?>
          <?php if ($p === $page): ?>
            <span class="current"><?= $p ?></span>
          <?php else: ?>
            <a href="?<?=h(qs(['page'=>$p]))?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?=h(qs(['page'=>$page+1]))?>">Next ›</a>
          <a href="?<?=h(qs(['page'=>$totalPages]))?>">Last »</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="rb-note no-print">
      Note: “On Hand” = StockIn − Sold − Returned-to-supplier. Negative results are shown as 0.
    </div>
  </div>
</main>

<?php include 'footer.php'; ?>
