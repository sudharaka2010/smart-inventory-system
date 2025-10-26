<?php
/* ---------------------------------------------------------
   RB Stores — Sales Report (print-perfect)
   File: sales_report.php
   Requires: header.php, sidebar.php, footer.php
   DB: rb_stores_db (MariaDB 10.4+), PHP 8.2+
   --------------------------------------------------------- */

const TZ = 'Asia/Colombo';
date_default_timezone_set(TZ);

/* ---------- 0) SAFE CSV EXPORT BRANCH (must run before any output) ---------- */
$pendingExport = (isset($_GET['export']) && $_GET['export'] === 'csv');

function _tempDbConnect(): PDO {
    $dsn  = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
    return new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/* Build WHERE from query string (used both in export & normal branch) */
function buildSalesFilters(&$params) {
    $qs = [
        'from'   => $_GET['from']   ?? '',
        'to'     => $_GET['to']     ?? '',
        'cust'   => $_GET['cust']   ?? '',
        'pay'    => $_GET['pay']    ?? '',
        'status' => $_GET['status'] ?? '',
        'q'      => $_GET['q']      ?? '',
    ];
    $params = [];
    $where  = [];

    if ($qs['from'] !== '' && $qs['to'] !== '') {
        $where[] = "o.OrderDate BETWEEN :from AND :to";
        $params[':from'] = $qs['from'];
        $params[':to']   = $qs['to'];
    } elseif ($qs['from'] !== '') {
        $where[] = "o.OrderDate >= :from";
        $params[':from'] = $qs['from'];
    } elseif ($qs['to'] !== '') {
        $where[] = "o.OrderDate <= :to";
        $params[':to'] = $qs['to'];
    }

    if ($qs['cust']   !== '') { $where[] = "o.CustomerID = :cust";   $params[':cust']   = $qs['cust']; }
    if ($qs['pay']    !== '') { $where[] = "o.PaymentMethod = :pay"; $params[':pay']    = $qs['pay']; }
    if ($qs['status'] !== '') { $where[] = "o.Status = :status";     $params[':status'] = $qs['status']; }
    if ($qs['q']      !== '') {
        $where[] = "(o.InvoiceID LIKE :kw OR c.NAME LIKE :kw)";
        $params[':kw'] = '%'.$qs['q'].'%';
    }
    $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
    return [$whereSql, $qs];
}

/* ---------- CSV Export ---------- */
if ($pendingExport) {
    $pdo = _tempDbConnect();
    $params = [];
    [$whereSql, $qs] = buildSalesFilters($params);

    $sql = "
        SELECT
            o.OrderID, o.InvoiceID, o.CustomerID, COALESCE(c.NAME, '(Unknown)') AS CustomerName,
            o.OrderDate, o.SubTotal, o.Discount, o.VAT, o.TotalAmount,
            o.PaymentMethod, o.AmountPaid, o.Balance, o.Status, o.Notes
        FROM `order` o
        LEFT JOIN customer c ON c.CustomerID = o.CustomerID
        $whereSql
        ORDER BY o.OrderDate DESC, o.OrderID DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_report_'.date('Ymd_His').'.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['OrderID','InvoiceID','CustomerID','CustomerName','OrderDate','SubTotal','Discount','VAT','TotalAmount','PaymentMethod','AmountPaid','Balance','Status','Notes']);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $r);
    fclose($out);
    exit;
}

/* ---------- 1) INCLUDES ---------- */
include 'header.php';
include 'sidebar.php';

/* ---------- 2) DB (reuse from header if present) ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = _tempDbConnect();
}

/* ---------- 3) FILTERS & PAGINATION ---------- */
$params = [];
[$whereSql, $qs] = buildSalesFilters($params);

$perPage = max(10, (int)($_GET['per_page'] ?? 25));
$page    = max(1,  (int)($_GET['page']     ?? 1));
$offset  = ($page - 1) * $perPage;

/* ---------- 4) CORE QUERIES ---------- */
$countSql = "SELECT COUNT(*) FROM `order` o LEFT JOIN customer c ON c.CustomerID=o.CustomerID $whereSql";
$stCount = $pdo->prepare($countSql);
$stCount->execute($params);
$totalRows = (int)$stCount->fetchColumn();

$kpiSql = "
  SELECT
    COUNT(*)                                  AS orders_count,
    COALESCE(SUM(o.SubTotal),0)               AS sum_subtotal,
    COALESCE(SUM(o.Discount),0)               AS sum_discount,
    COALESCE(SUM(o.VAT),0)                    AS sum_vat,
    COALESCE(SUM(o.TotalAmount),0)            AS sum_total,
    COALESCE(SUM(o.AmountPaid),0)             AS sum_paid,
    COALESCE(SUM(o.Balance),0)                AS sum_balance,
    COALESCE(AVG(o.TotalAmount),0)            AS avg_order
  FROM `order` o
  LEFT JOIN customer c ON c.CustomerID=o.CustomerID
  $whereSql
";
$stK = $pdo->prepare($kpiSql);
$stK->execute($params);
$kpi = $stK->fetch() ?: [
  'orders_count'=>0,'sum_subtotal'=>0,'sum_discount'=>0,'sum_vat'=>0,
  'sum_total'=>0,'sum_paid'=>0,'sum_balance'=>0,'avg_order'=>0
];

$lineSql = "
  SELECT o.OrderDate AS d, COALESCE(SUM(o.TotalAmount),0) AS total
  FROM `order` o
  LEFT JOIN customer c ON c.CustomerID=o.CustomerID
  $whereSql
  GROUP BY o.OrderDate
  ORDER BY o.OrderDate
";
$stL = $pdo->prepare($lineSql); $stL->execute($params);
$lineRows = $stL->fetchAll();

$paySql = "
  SELECT COALESCE(o.PaymentMethod, 'Unknown') AS m, COUNT(*) AS orders, COALESCE(SUM(o.TotalAmount),0) AS total
  FROM `order` o
  LEFT JOIN customer c ON c.CustomerID=o.CustomerID
  $whereSql
  GROUP BY m
  ORDER BY total DESC
";
$stP = $pdo->prepare($paySql); $stP->execute($params);
$payRows = $stP->fetchAll();

$topCustSql = "
  SELECT COALESCE(c.NAME,'(Unknown)') AS customer_name,
         COUNT(o.OrderID) AS orders,
         COALESCE(SUM(o.TotalAmount),0) AS revenue
  FROM `order` o
  LEFT JOIN customer c ON c.CustomerID=o.CustomerID
  $whereSql
  GROUP BY o.CustomerID
  ORDER BY revenue DESC
  LIMIT 5
";
$stTC = $pdo->prepare($topCustSql); $stTC->execute($params);
$topCustRows = $stTC->fetchAll();

$rowsSql = "
  SELECT
    o.OrderID, o.InvoiceID, o.CustomerID, COALESCE(c.NAME,'(Unknown)') AS CustomerName,
    o.OrderDate, o.SubTotal, o.Discount, o.VAT, o.TotalAmount,
    o.PaymentMethod, o.AmountPaid, o.Balance, o.Status
  FROM `order` o
  LEFT JOIN customer c ON c.CustomerID=o.CustomerID
  $whereSql
  ORDER BY o.OrderDate DESC, o.OrderID DESC
  LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($rowsSql);
foreach ($params as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':lim', $perPage, PDO::PARAM_INT);
$st->bindValue(':off', $offset,  PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

/* Dropdown helpers */
$customers = $pdo->query("SELECT CustomerID, NAME FROM customer ORDER BY NAME")->fetchAll();
$pays      = $pdo->query("SELECT DISTINCT PaymentMethod FROM `order` ORDER BY PaymentMethod")->fetchAll(PDO::FETCH_COLUMN);
$statuses  = $pdo->query("SELECT DISTINCT Status FROM `order` ORDER BY Status")->fetchAll(PDO::FETCH_COLUMN);

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $extra=[]): string {
    $base = $_GET;
    foreach ($extra as $k=>$v) $base[$k] = $v;
    return http_build_query($base);
}

/* Chart data */
$labelsDays = array_map(fn($r)=>$r['d'], $lineRows);
$valuesDays = array_map(fn($r)=>(float)$r['total'], $lineRows);
$labelsPay  = array_map(fn($r)=>$r['m'] ?? 'Unknown', $payRows);
$valuesPay  = array_map(fn($r)=>(float)$r['total'], $payRows);
$labelsTopC = array_map(fn($r)=>$r['customer_name'], $topCustRows);
$valuesTopC = array_map(fn($r)=>(float)$r['revenue'], $topCustRows);
?>
<style>
:root{
  --rb-sidebar-w: 260px;
  --rb-footer-h: 72px;

  --rb-bg:#ffffff; --rb-card:#ffffff;
  --rb-accent:#3b5683; --rb-text:#3b5683; --rb-muted:#6b7c97; --rb-border:#dfe6f2;
}

/* ===== FIXED FOOTER (screen only) ===== */
.site-footer, footer, .app-footer, footer.footer, .footer {
  position: fixed !important;
  bottom: 0; left: 0; right: 0;
  width: 100%;
  z-index: 1000;
}

/* content area */
.rb-main{
  margin-left: var(--rb-sidebar-w);
  padding: 24px;
  padding-bottom: calc(24px + var(--rb-footer-h));
  background: var(--rb-bg);
  min-height: 100vh;
}
@media (max-width:1024px){ .rb-main{ margin-left:0; } }

.rb-wrap{max-width:1200px;margin:0 auto;color:var(--rb-text);
  font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;}
.rb-row{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.rb-h1{font-size:20px;margin:0}
.rb-badge{padding:4px 8px;border:1px solid var(--rb-border);border-radius:999px;color:var(--rb-text);background:#eef2f8}

/* cards/grid */
.rb-card{background:var(--rb-card);border:1px solid var(--rb-border);border-radius:12px;padding:16px;box-shadow:0 2px 6px rgba(34,54,82,.08)}
.rb-grid{display:grid;gap:16px}
.rb-kpis{grid-template-columns:repeat(4,minmax(0,1fr));}
@media (max-width:900px){.rb-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width:520px){.rb-kpis{grid-template-columns:1fr;}}
.rb-kpi-t{font-size:12px;color:var(--rb-muted);margin-bottom:6px}
.rb-kpi-v{font-weight:700;font-size:22px;color:var(--rb-text)}

/* filters */
.rb-filters{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;align-items:end}
@media (max-width:1100px){.rb-filters{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media (max-width:560px){.rb-filters{grid-template-columns:repeat(2,minmax(0,1fr));}}
.rb-label{display:block;font-size:12px;color:var(--rb-muted);margin-bottom:6px}
.rb-input,.rb-select{width:100%;background:#fff;color:var(--rb-text);border:1px solid var(--rb-border);border-radius:8px;padding:10px 12px;outline:none}
.rb-input:focus,.rb-select:focus{border-color:var(--rb-accent);box-shadow:0 0 0 2px rgba(59,86,131,.25)}
.rb-actions{display:flex;gap:8px;flex-wrap:wrap}

/* buttons */
.rb-btn{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid var(--rb-border);background:#e9eff7;color:var(--rb-text);text-decoration:none;cursor:pointer;transition:.2s}
.rb-btn:not(.rb-btn-primary):hover{background:#dde7f6}
.rb-btn-primary{background:var(--rb-accent);border-color:transparent;color:#fff}
.rb-btn-primary:hover,.rb-btn-primary:focus-visible{background:var(--rb-accent);color:#fff;filter:brightness(1.05);box-shadow:0 0 0 2px rgba(59,86,131,.25)}
.rb-btn-print{background:#e9eff7}

/* charts (screen) */
.rb-charts{grid-template-columns:repeat(2,minmax(0,1fr));}
@media (max-width:900px){.rb-charts{grid-template-columns:1fr;}}
.chart-wrap{position:relative;height:360px}      /* fixed screen height */
.chart-wrap canvas{position:absolute;inset:0;width:100% !important;height:100% !important;border-radius:10px}
.chart-wrap img.print-chart{display:none;max-width:100%;height:auto;border-radius:10px}

/* table */
.rb-table-wrap{overflow:auto;border-radius:10px;border:1px solid var(--rb-border)}
.rb-table{width:100%;border-collapse:collapse;min-width:980px}
.rb-th,.rb-td{padding:10px 12px;border-bottom:1px solid var(--rb-border);text-align:left;color:var(--rb-text)}
.rb-th{position:sticky;top:0;background:#eef2f8;z-index:5;font-size:12px}
.rb-tr:hover .rb-td{background:#f6f9fc}
.rb-num{text-align:right;font-variant-numeric:tabular-nums}

/* pagination */
.rb-pagination{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
.rb-pagination a,.rb-pagination span{padding:8px 12px;border-radius:8px;background:#e9eff7;color:var(--rb-text);border:1px solid var(--rb-border);text-decoration:none}
.rb-pagination a:hover{background:#dde7f6}
.rb-pagination .current{background:var(--rb-accent);border-color:transparent;color:#fff}

/* ===================== PRINT ===================== */
@page { size: A4 portrait; margin: 12mm; }

@media print{
  html, body { background:#fff !important; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  /* Remove app chrome */
  .rb-main{ margin-left:0 !important; padding:0 !important; background:#fff !important; }
  .site-footer, footer, .app-footer, footer.footer, .footer{ display:none !important; }
  .sidebar, .rb-sidebar, .app-sidebar, nav, header, .site-header,
  .no-print, .rb-actions, .rb-pagination { display:none !important; }

  /* Layout */
  .rb-wrap{ max-width:100% !important; padding:0 !important; color:#000; }
  .rb-card{
    box-shadow:none !important;
    border:1px solid #cbd5e1 !important;
    break-inside: avoid !important;
    page-break-inside: avoid !important;
    margin: 0 0 8mm 0 !important;
  }

  /* Charts -> single column + fixed height on paper */
  .rb-charts{ grid-template-columns:1fr !important; }
  .chart-wrap{
    height: 65mm !important;
    max-height: 65mm !important;
    position: relative !important;
    overflow: hidden !important;
  }

  /* Hide canvases; show snapshot images */
  .chart-wrap canvas{
    display: none !important;
    width: 0 !important; height: 0 !important;
    visibility: hidden !important;
  }
  .chart-wrap img.print-chart{
    display: block !important;
    width: 100% !important;
    height: auto !important;
    max-height: 65mm !important;
    object-fit: contain !important;
    border-radius: 10px;
  }

  /* Tables */
  .rb-table-wrap{ overflow:visible !important; border:none !important; }
  .rb-table{ min-width:0 !important; table-layout:auto !important; }
  .rb-th{ position:sticky; top:auto; background:#e7edf7 !important; color:#000 !important; }
}
</style>

<main class="rb-main">
  <div class="rb-wrap">
    <div class="rb-row">
      <h1 class="rb-h1">Sales Report</h1>
      <span class="rb-badge"><?=h(date('Y-m-d H:i'))?> • Asia/Colombo</span>
    </div>

    <!-- KPIs -->
    <div class="rb-grid rb-kpis" style="margin-top:16px">
      <div class="rb-card"><div class="rb-kpi-t">Orders</div><div class="rb-kpi-v"><?= number_format((float)$kpi['orders_count']) ?></div></div>
      <div class="rb-card"><div class="rb-kpi-t">Revenue (Total)</div><div class="rb-kpi-v">Rs. <?= number_format((float)$kpi['sum_total'], 2) ?></div></div>
      <div class="rb-card"><div class="rb-kpi-t">Paid vs Balance</div><div class="rb-kpi-v">Paid: Rs. <?= number_format((float)$kpi['sum_paid'],2) ?> • Bal: Rs. <?= number_format((float)$kpi['sum_balance'],2) ?></div></div>
      <div class="rb-card"><div class="rb-kpi-t">Avg Order Value</div><div class="rb-kpi-v">Rs. <?= number_format((float)$kpi['avg_order'],2) ?></div></div>
    </div>

    <!-- Filters -->
    <div class="rb-card no-print" style="margin-top:16px">
      <form class="rb-filters" method="get" action="">
        <div><label class="rb-label">From (Order Date)</label><input class="rb-input" type="date" name="from" value="<?=h($qs['from'])?>"></div>
        <div><label class="rb-label">To (Order Date)</label><input class="rb-input" type="date" name="to" value="<?=h($qs['to'])?>"></div>
        <div>
          <label class="rb-label">Customer</label>
          <select class="rb-select" name="cust">
            <option value="">All</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?=$c['CustomerID']?>" <?= ($qs['cust']==(string)$c['CustomerID']?'selected':'') ?>><?=h($c['NAME'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="rb-label">Payment Method</label>
          <select class="rb-select" name="pay">
            <option value="">All</option>
            <?php foreach ($pays as $p): ?>
              <option value="<?=h($p)?>" <?= ($qs['pay']===$p?'selected':'') ?>><?=h($p ?: 'Unknown')?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="rb-label">Status</label>
          <select class="rb-select" name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?=h($s)?>" <?= ($qs['status']===$s?'selected':'') ?>><?=h($s ?: 'Unknown')?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="rb-label">Search (Invoice/Customer)</label>
          <input class="rb-input" type="text" name="q" placeholder="e.g., INV-00001 or Nimal" value="<?=h($qs['q'])?>">
        </div>

        <div class="rb-actions" style="grid-column: 1 / -1; margin-top:4px">
          <button class="rb-btn rb-btn-primary" type="submit">Apply Filters</button>
          <a class="rb-btn" href="?">Reset</a>
          <a class="rb-btn" href="?<?=h(qs(['export'=>'csv','page'=>1]))?>">Export CSV</a>
          <button class="rb-btn rb-btn-print" type="button" onclick="prepareAndPrint()">Print</button>
          <div style="margin-left:auto"></div>
          <label class="rb-label" style="align-self:center">Per Page</label>
          <select class="rb-select" name="per_page" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $pp): ?>
              <option value="<?=$pp?>" <?= $pp===$perPage?'selected':'' ?>><?=$pp?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>

    <!-- Charts -->
    <div class="rb-grid rb-charts" style="margin-top:16px">
      <div class="rb-card">
        <div class="rb-kpi-t" style="margin-bottom:8px">Sales by Day</div>
        <div class="chart-wrap"><canvas id="salesLine" aria-label="Sales by day"></canvas></div>
      </div>
      <div class="rb-card">
        <div class="rb-kpi-t" style="margin-bottom:8px">Revenue by Payment Method</div>
        <div class="chart-wrap"><canvas id="payPie" aria-label="Revenue by payment method"></canvas></div>
      </div>
      <div class="rb-card" style="grid-column:1 / -1">
        <div class="rb-kpi-t" style="margin-bottom:8px">Top Customers (Revenue)</div>
        <div class="chart-wrap"><canvas id="topCust" aria-label="Top customers"></canvas></div>
      </div>
    </div>

    <!-- Table -->
    <div class="rb-table-wrap rb-card" style="margin-top:16px">
      <table class="rb-table">
        <thead>
          <tr class="rb-tr">
            <th class="rb-th">Order ID</th>
            <th class="rb-th">Invoice</th>
            <th class="rb-th">Customer</th>
            <th class="rb-th">Date</th>
            <th class="rb-th rb-num">Subtotal (Rs.)</th>
            <th class="rb-th rb-num">Discount (Rs.)</th>
            <th class="rb-th rb-num">VAT (Rs.)</th>
            <th class="rb-th rb-num">Total (Rs.)</th>
            <th class="rb-th">Payment</th>
            <th class="rb-th rb-num">Paid (Rs.)</th>
            <th class="rb-th rb-num">Balance (Rs.)</th>
            <th class="rb-th">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr class="rb-tr"><td class="rb-td" colspan="12" style="text-align:center; color:var(--rb-muted)">No orders match your filter.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr class="rb-tr">
              <td class="rb-td"><?=h($r['OrderID'])?></td>
              <td class="rb-td"><?=h($r['InvoiceID'])?></td>
              <td class="rb-td"><?=h($r['CustomerName'])?></td>
              <td class="rb-td"><?=h($r['OrderDate'])?></td>
              <td class="rb-td rb-num"><?=number_format((float)$r['SubTotal'],2)?></td>
              <td class="rb-td rb-num"><?=number_format((float)$r['Discount'],2)?></td>
              <td class="rb-td rb-num"><?=number_format((float)$r['VAT'],2)?></td>
              <td class="rb-td rb-num"><?=number_format((float)$r['TotalAmount'],2)?></td>
              <td class="rb-td"><?=h($r['PaymentMethod'] ?: '—')?></td>
              <td class="rb-td rb-num"><?=number_format((float)$r['AmountPaid'],2)?></td>
              <td class="rb-td rb-num"><?=number_format((float)$r['Balance'],2)?></td>
              <td class="rb-td"><?=h($r['Status'] ?: '—')?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

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

    <div class="rb-badge no-print" style="margin-top:10px; background:#eef2f8">
      Tip: Use “Export CSV” for Excel; Print → “Save as PDF” for a clean PDF.
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = true;
Chart.defaults.animation = false;

const SCREEN_DPR = Math.max(1, Math.floor(window.devicePixelRatio || 1));
Chart.defaults.devicePixelRatio = SCREEN_DPR;

const labelsDays = <?=json_encode($labelsDays)?>;
const valuesDays = <?=json_encode($valuesDays)?>;
const labelsPay  = <?=json_encode($labelsPay)?>;
const valuesPay  = <?=json_encode($valuesPay)?>;
const labelsTopC = <?=json_encode($labelsTopC)?>;
const valuesTopC = <?=json_encode($valuesTopC)?>;

const charts = [];

function makeChart(ctx, type, data, options = {}){
  return new Chart(ctx, {
    type,
    data,
    options: Object.assign({
      animation: false,
      layout: { padding: 8 },
      plugins: { legend: { display: true, position: (type === 'pie' ? 'bottom' : 'top') } },
      scales: (type === 'pie') ? {} : { y: { beginAtZero:true, ticks: { precision:0 } } }
    }, options)
  });
}

function initCharts(){
  const elLine = document.getElementById('salesLine');
  if (elLine){
    charts.push(makeChart(elLine.getContext('2d'), 'line', {
      labels: labelsDays,
      datasets: [{ label:'Revenue (Rs.)', data: valuesDays, tension:0.35, pointRadius:2 }]
    }));
  }
  const elPie = document.getElementById('payPie');
  if (elPie){
    charts.push(makeChart(elPie.getContext('2d'), 'pie', {
      labels: labelsPay,
      datasets: [{ data: valuesPay }]
    }));
  }
  const elBar = document.getElementById('topCust');
  if (elBar){
    charts.push(makeChart(elBar.getContext('2d'), 'bar', {
      labels: labelsTopC,
      datasets: [{ label:'Revenue (Rs.)', data: valuesTopC }]
    }, { plugins:{ legend:{ display:false } } }));
  }
}

/* --- Build hi-DPI snapshots for print (do NOT hide/destroy canvases) --- */
const PRINT_SCALE = 2;
function buildSnapshots(){
  charts.forEach(chart => {
    try{
      const wrap = chart.canvas.closest('.chart-wrap');
      const oldDPR = chart.options.devicePixelRatio || Chart.defaults.devicePixelRatio;
      chart.options.devicePixelRatio = PRINT_SCALE * SCREEN_DPR;
      chart.resize(); chart.update();

      const url = chart.toBase64Image('image/png', 1.0);
      let img = wrap.querySelector('img.print-chart');
      if (!img) { img = new Image(); img.className = 'print-chart'; wrap.appendChild(img); }
      img.src = url;

      chart.options.devicePixelRatio = oldDPR;
      chart.resize(); chart.update();
    }catch(e){}
  });
}

/* Print hooks */
function prepareAndPrint(){ buildSnapshots(); window.print(); }
window.prepareAndPrint = prepareAndPrint;
window.addEventListener('beforeprint', buildSnapshots);

/* Init */
initCharts();
/* Build once so Ctrl+P preview has snapshots ready */
window.addEventListener('load', () => { setTimeout(buildSnapshots, 80); });
</script>


<?php include 'footer.php'; ?>
