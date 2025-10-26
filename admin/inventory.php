<?php
/* ---------------------------------------------------------
   RB Stores — Inventory (Light Theme, header/sidebar/footer)
   File: inventory.php
   DB: rb_stores_db (MariaDB 10.4+), PHP 8.2+
   --------------------------------------------------------- */

declare(strict_types=1);
const TZ = 'Asia/Colombo';
date_default_timezone_set(TZ);

/* ---------- 0) DB helper ---------- */
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

/* ---------- Filter builder (WHERE + HAVING) ---------- */
function buildFiltersInventory(&$params): array {
    $qs = [
        'from'     => $_GET['from']     ?? '',
        'to'       => $_GET['to']       ?? '',
        'supplier' => $_GET['supplier'] ?? '',
        'category' => $_GET['category'] ?? '',
        'q'        => $_GET['q']        ?? '',
        'low'      => $_GET['low']      ?? '',
        'status'   => $_GET['status']   ?? '', // NEW
    ];
    $where  = [];
    $params = [];

    // ReceiveDate range
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

    // Supplier, Category, Keyword
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

    // Status filter (based on DATE(ReceiveDate) vs CURDATE())
    // CASE computed in SELECT, but here we emulate it in WHERE to keep index use minimal.
    // We’ll bind desired comparisons.
    if ($qs['status'] !== '') {
        if ($qs['status'] === 'Arrived item') {
            $where[] = "DATE(ii.ReceiveDate) < CURDATE()";
        } elseif ($qs['status'] === 'On Date') {
            $where[] = "DATE(ii.ReceiveDate) = CURDATE()";
        } elseif ($qs['status'] === 'Pending') {
            $where[] = "DATE(ii.ReceiveDate) > CURDATE()";
        }
    }

    $whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

    // Low stock HAVING (uses full expression so it works in count & rows)
    $onHandExpr = "GREATEST(0, (ii.Quantity - COALESCE(so.sold_qty,0) - COALESCE(r.ret_qty,0)))";
    $havingSql  = '';
    if ($qs['low'] !== '' && is_numeric($qs['low']) && (int)$qs['low'] >= 0) {
        $havingSql = "HAVING {$onHandExpr} <= :lowth";
        $params[':lowth'] = (int)$qs['low'];
    }

    return [$whereSql, $havingSql, $onHandExpr];
}

/* ---------- CSV Export ---------- */
$pendingExport = (isset($_GET['export']) && $_GET['export'] === 'csv');

if ($pendingExport) {
    $pdo = _tempDbConnect();
    $params = [];
    [$whereSql, $havingSql, $onHandExpr] = buildFiltersInventory($params);

    $baseSql = "
        FROM inventoryitem ii
        LEFT JOIN supplier s ON s.SupplierID = ii.SupplierID
        LEFT JOIN (
            SELECT od.ItemID, SUM(od.Quantity) AS sold_qty
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

    // Status computed like Add page rules
    $statusExpr = "CASE
        WHEN DATE(ii.ReceiveDate) < CURDATE() THEN 'Arrived item'
        WHEN DATE(ii.ReceiveDate) = CURDATE() THEN 'On Date'
        ELSE 'Pending'
    END";

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
            ($onHandExpr) AS StockOnHand,
            ii.ReceiveDate,
            $statusExpr AS Status
        $baseSql
        GROUP BY ii.ItemID
        $havingSql
        ORDER BY ii.ReceiveDate DESC, ii.ItemID DESC
    ";

    $st = $pdo->prepare($exportSql);
    $st->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inventory_'.date('Ymd_His').'.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ItemID','InvoiceID','ItemName','Description','Category','Supplier','Price','StockIn','SoldQty','ReturnedQty','StockOnHand','ReceiveDate','Status']);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $r);
    fclose($out);
    exit;
}

/* ---------- 1) INCLUDES & CSRF ---------- */
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

include 'header.php';
include 'sidebar.php';

/* ---------- 2) DB ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = _tempDbConnect();
}

/* ---------- 3) Filters & Pagination ---------- */
$qs = [
    'from'     => $_GET['from']     ?? '',
    'to'       => $_GET['to']       ?? '',
    'supplier' => $_GET['supplier'] ?? '',
    'category' => $_GET['category'] ?? '',
    'q'        => $_GET['q']        ?? '',
    'low'      => $_GET['low']      ?? '',
    'status'   => $_GET['status']   ?? '',
    'per_page' => max(10, (int)($_GET['per_page'] ?? 25)),
    'page'     => max(1,  (int)($_GET['page']     ?? 1)),
];

$params = [];
[$whereSql, $havingSql, $onHandExpr] = buildFiltersInventory($params);

$baseSql = "
    FROM inventoryitem ii
    LEFT JOIN supplier s ON s.SupplierID = ii.SupplierID
    LEFT JOIN (
        SELECT od.ItemID, SUM(od.Quantity) AS sold_qty
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
$statusExpr = "CASE
    WHEN DATE(ii.ReceiveDate) < CURDATE() THEN 'Arrived item'
    WHEN DATE(ii.ReceiveDate) = CURDATE() THEN 'On Date'
    ELSE 'Pending'
END";

/* Count (with HAVING) */
$countSql = "SELECT COUNT(*) FROM (
    SELECT ii.ItemID
    $baseSql
    GROUP BY ii.ItemID
    $havingSql
) t";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();

/* KPI chips */
$aggSql = "
    SELECT
      COUNT(*) AS item_count,
      SUM(on_hand) AS total_on_hand,
      SUM(on_hand * Price) AS total_value
    FROM (
      SELECT
        ii.ItemID,
        ii.Price,
        ($onHandExpr) AS on_hand
      $baseSql
      GROUP BY ii.ItemID
      $havingSql
    ) z
";
$stmtAgg = $pdo->prepare($aggSql);
$stmtAgg->execute($params);
$agg = $stmtAgg->fetch() ?: ['item_count'=>0,'total_on_hand'=>0,'total_value'=>0];

/* Pagination */
$perPage = $qs['per_page'];
$page    = $qs['page'];
$offset  = ($page - 1) * $perPage;

/* Data rows (include Status) */
$rowsSql = "
    SELECT
        ii.ItemID,
        ii.InvoiceID,
        ii.NAME  AS item_name,
        ii.Description,
        ii.Price,
        ii.Quantity AS stock_in,
        COALESCE(so.sold_qty, 0)   AS sold_qty,
        COALESCE(r.ret_qty, 0)     AS returned_qty,
        ($onHandExpr)              AS stock_on_hand,
        ii.Category,
        COALESCE(ii.SupplierName, s.NAME) AS supplier_name,
        ii.ReceiveDate,
        $statusExpr                AS Status
    $baseSql
    GROUP BY ii.ItemID
    $havingSql
    ORDER BY ii.ReceiveDate DESC, ii.ItemID DESC
    LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($rowsSql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

/* Dropdown data */
$suppliers  = $pdo->query("SELECT SupplierID, NAME FROM supplier ORDER BY NAME")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT Category FROM inventoryitem ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $extra=[]): string {
    $base = $_GET;
    foreach ($extra as $k=>$v) $base[$k] = $v;
    return http_build_query($base);
}
?>
<style>
/* ------- Light (white) page-local styles — brand blue ------- */
:root{
  --rb-sidebar-w: 260px;

  --rb-bg:#ffffff; --rb-card:#ffffff;
  --rb-text:#2b3e5a; --rb-muted:#6b7c97;
  --rb-accent:#3b5683; --rb-border:#dfe6f2;

  --rb-good:#10b981;   /* green for Arrived */
  --rb-warn:#d97706;   /* amber for Pending */
  --rb-info:#3b5683;   /* brand for On Date */
  --rb-danger:#dc2626; /* red for LOW */

  --rb-table-head:#e9eff7;
  --rb-table-row:#ffffff;
  --rb-table-row-alt:#f7f9fc;
  --rb-link:#3b5683;
}

.rb-main{ margin-left:260px; padding:24px; background:var(--rb-bg); min-height:calc(100vh - 60px) }
@media (max-width:1024px){ .rb-main{ margin-left:0 } }

.rb-wrap{max-width:1200px; margin:0 auto; color:var(--rb-text); font:14px/1.5 'Poppins',system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
.rb-row{display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap}
.rb-h1{font-size:22px; margin:0; font-weight:700; color:var(--rb-text)}
.rb-badges{display:flex; gap:8px; flex-wrap:wrap}
.rb-chip{
  display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px;
  background:#fff; border:1px solid var(--rb-border); font-size:12px; color:var(--rb-muted)
}
.rb-chip b{color:var(--rb-text); font-weight:700}

.rb-card{
  background:var(--rb-card); border:1px solid var(--rb-border); border-radius:16px; padding:16px;
  box-shadow:0 6px 16px rgba(34,54,82,.06); color:var(--rb-text)
}
.rb-grid{display:grid; gap:16px}

.rb-actions{display:flex; gap:8px; flex-wrap:wrap}

/* Buttons — filled, no outline */
.rb-btn{
  display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid transparent;
  background:#ffffff; color:var(--rb-text); text-decoration:none; cursor:pointer; min-width:92px; text-align:center;
  transition:transform .04s, filter .2s, box-shadow .2s;
}
.rb-btn:active{ transform:scale(0.99) }

.rb-btn-primary{ background:var(--rb-accent); color:#fff }
.rb-btn-info{ background:#64748b; color:#fff }
.rb-btn-warning{ background:#f59e0b; color:#111827 }
.rb-btn-danger{ background:#ef4444; color:#fff }

.rb-btn:hover{ filter:brightness(1.05) }

/* Filters */
.rb-filters{display:grid; grid-template-columns: repeat(7, minmax(0,1fr)); gap:10px; align-items:end}
@media (max-width:1000px){ .rb-filters{grid-template-columns: repeat(3, minmax(0,1fr))} }
@media (max-width:560px){ .rb-filters{grid-template-columns: repeat(2, minmax(0,1fr))} }
.rb-label{display:block; font-size:12px; color:var(--rb-muted); margin-bottom:6px}
.rb-input,.rb-select{
  width:100%; background:#fff; color:var(--rb-text); border:1px solid var(--rb-border);
  border-radius:10px; padding:10px 12px; outline:none; transition:border-color .2s, box-shadow .2s
}
.rb-input:focus,.rb-select:focus{ border-color:var(--rb-accent); box-shadow:0 0 0 2px rgba(59,86,131,.25) }

/* Table */
.rb-table-wrap{overflow:auto; border-radius:14px; border:1px solid var(--rb-border); background:#fff}
.rb-table{width:100%; border-collapse:separate; border-spacing:0; min-width:1080px}
.rb-th,.rb-td{padding:12px 14px; border-bottom:1px solid var(--rb-border); text-align:left; color:var(--rb-text)}
.rb-th{position:sticky; top:0; background:var(--rb-table-head); z-index:1; font-size:12px; color:var(--rb-text); text-transform:uppercase; letter-spacing:.02em}
.rb-tr:nth-child(even) .rb-td{background:var(--rb-table-row-alt)}
.rb-tr:nth-child(odd)  .rb-td{background:var(--rb-table-row)}
.rb-tr:hover .rb-td{background:#f6f9fc}
.rb-num{text-align:right; font-variant-numeric: tabular-nums}

/* Badges */
.rb-badge-low{color:#fff; background:var(--rb-danger); padding:2px 8px; border-radius:999px; font-size:12px}
.rb-badge-ok{color:#fff; background:var(--rb-info); padding:2px 8px; border-radius:999px; font-size:12px}
.rb-badge-status{color:#fff; padding:4px 10px; border-radius:999px; font-size:12px}
.rb-status-arrived{ background:var(--rb-good) }
.rb-status-today{   background:var(--rb-info) }
.rb-status-pending{ background:var(--rb-warn) }

.rb-note{color:var(--rb-muted); font-size:12px; margin-top:10px}

a.rb-link{ color:var(--rb-link); text-decoration:none }
a.rb-link:hover{ text-decoration:underline }

@media print{
  .rb-main{ margin-left:0; padding:0; background:#fff }
  .rb-card{ box-shadow:none }
  .rb-th{ background:#e7edf7; color:#111827 }
  .no-print{ display:none !important }
}
</style>

<!-- Hidden POST form for secure deletion -->
<form id="delForm" class="no-print" action="delete_inventory.php" method="post" style="display:none">
  <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
  <input type="hidden" name="id" id="delFormId" value="">
</form>

<main class="rb-main">
  <div class="rb-wrap">
    <div class="rb-row">
      <h1 class="rb-h1">Inventory (Stock)</h1>
      <div class="rb-badges">
        <span class="rb-chip"><b><?= number_format((float)$agg['item_count']) ?></b> Items</span>
        <span class="rb-chip"><b><?= number_format((float)$agg['total_on_hand']) ?></b> On-hand</span>
        <span class="rb-chip"><b>LKR <?= number_format((float)$agg['total_value'],2) ?></b> Value</span>
        <span class="rb-chip"><?=h(date('Y-m-d H:i'))?> • Asia/Colombo</span>
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
          <input class="rb-input" type="text" name="q" placeholder="e.g., Roofing / IN2025..." value="<?=h($qs['q'])?>">
        </div>
        <div>
          <label class="rb-label">Low Stock ≤</label>
          <input class="rb-input" type="number" min="0" step="1" name="low" placeholder="e.g., 10" value="<?=h($qs['low'])?>">
        </div>
        <div>
          <label class="rb-label">Status</label>
          <select class="rb-select" name="status">
            <?php
              $opts = [''=>'All','Arrived item'=>'Arrived item','On Date'=>'On Date','Pending'=>'Pending'];
              foreach ($opts as $val=>$label):
            ?>
              <option value="<?=h($val)?>" <?= ($qs['status']===$val?'selected':'') ?>><?=h($label)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="rb-actions" style="grid-column: 1 / -1; margin-top:4px">
          <button class="rb-btn rb-btn-primary" type="submit">Apply Filters</button>
          <a class="rb-btn rb-btn-info" href="?">Reset</a>
          <a class="rb-btn" style="background:#e2e8f0;color:#0f172a" href="?<?=h(qs(['export'=>'csv','page'=>1]))?>">Export CSV</a>

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
            <th class="rb-th">Received</th>
            <th class="rb-th">Status</th> <!-- NEW -->
            <th class="rb-th">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr class="rb-tr"><td class="rb-td" colspan="13" style="text-align:center; color:var(--rb-muted)">No items match your filter.</td></tr>
          <?php else: ?>
            <?php
            $lowTh = ($qs['low'] !== '' && is_numeric($qs['low'])) ? (int)$qs['low'] : null;
            foreach ($rows as $r):
              $onHand = max(0, (float)$r['stock_on_hand']);
              $isLow  = ($lowTh !== null && $onHand <= $lowTh);

              $status = (string)$r['Status'];
              $statusClass = $status === 'Arrived item' ? 'rb-status-arrived'
                           : ($status === 'On Date' ? 'rb-status-today' : 'rb-status-pending');
            ?>
              <tr class="rb-tr">
                <td class="rb-td"><?= (int)$r['ItemID'] ?></td>
                <td class="rb-td"><?= h($r['InvoiceID']) ?></td>
                <td class="rb-td" title="<?=h($r['Description'])?>">
                  <?= h($r['item_name']) ?>
                  <?php if ($isLow): ?>
                    <span class="rb-badge-low" style="margin-left:6px">LOW</span>
                  <?php else: ?>
                    <span class="rb-badge-ok" style="margin-left:6px">OK</span>
                  <?php endif; ?>
                </td>
                <td class="rb-td"><?= h($r['Category'] ?: 'Unknown') ?></td>
                <td class="rb-td"><?= h($r['supplier_name'] ?: '—') ?></td>
                <td class="rb-td rb-num"><?= number_format((float)$r['Price'], 2) ?></td>
                <td class="rb-td rb-num"><?= number_format((float)$r['stock_in']) ?></td>
                <td class="rb-td rb-num"><?= number_format((float)$r['sold_qty']) ?></td>
                <td class="rb-td rb-num"><?= number_format((float)$r['returned_qty']) ?></td>
                <td class="rb-td rb-num"><?= number_format($onHand) ?></td>
                <td class="rb-td"><?= h($r['ReceiveDate'] ?: '—') ?></td>
                <td class="rb-td">
                  <span class="rb-badge-status <?= $statusClass ?>"><?= h($status) ?></span>
                </td>
                <td class="rb-td">
                  <div class="rb-actions">
                    <a class="rb-btn rb-btn-info"    href="view_inventory.php?id=<?= (int)$r['ItemID'] ?>">View</a>
                    <a class="rb-btn rb-btn-warning" href="edit_inventory.php?id=<?= (int)$r['ItemID'] ?>">Edit</a>
                    <button
                      type="button"
                      class="rb-btn rb-btn-danger btnDelete"
                      data-id="<?= (int)$r['ItemID'] ?>"
                      data-name="<?= h($r['item_name']) ?>"
                    >Delete</button>
                  </div>
                </td>
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
      <div class="rb-card" style="margin-top:12px">
        <div class="rb-actions" style="justify-content:flex-end">
          <?php if ($page > 1): ?>
            <a class="rb-btn" style="background:#e2e8f0" href="?<?=h(qs(['page'=>1]))?>">« First</a>
            <a class="rb-btn" style="background:#e2e8f0" href="?<?=h(qs(['page'=>$page-1]))?>">‹ Prev</a>
          <?php endif; ?>

          <?php for ($p=$start; $p<=$end; $p++): ?>
            <?php if ($p === $page): ?>
              <span class="rb-btn rb-btn-primary"><?= $p ?></span>
            <?php else: ?>
              <a class="rb-btn" style="background:#e2e8f0" href="?<?=h(qs(['page'=>$p]))?>"><?= $p ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a class="rb-btn" style="background:#e2e8f0" href="?<?=h(qs(['page'=>$page+1]))?>">Next ›</a>
            <a class="rb-btn" style="background:#e2e8f0" href="?<?=h(qs(['page'=>$totalPages]))?>">Last »</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="rb-note no-print" style="margin-bottom:20px">
      Note: “On Hand” = StockIn − Sold − Returned-to-supplier. Negative results are shown as 0. Status: Past = Arrived item, Today = On Date, Future = Pending.
    </div>
  </div>
</main>

<script>
  // Secure POST deletion
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btnDelete');
    if (!btn) return;

    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name') || '';
    const msg = `Delete Item #${id}${name ? ' ('+name+')' : ''}?\nThis action cannot be undone.`;

    if (confirm(msg)) {
      const form = document.getElementById('delForm');
      document.getElementById('delFormId').value = String(id);
      form.submit();
    }
  });
</script>

<?php include 'footer.php'; ?>
