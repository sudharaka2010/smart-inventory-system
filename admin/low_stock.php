<?php
/* ---------------------------------------------------------
   RB Stores — Low Stock Monitor (BUSINESS STYLE, MATCH add_inventory.php)
   File: low_stock.php
   Includes: header.php, sidebar.php, footer.php
   DB: rb_stores_db (MariaDB 10.4+), PHP 8.2+
   --------------------------------------------------------- */

declare(strict_types=1);
session_start();

/* ---------------- Security headers ---------------- */
if (!headers_sent()) {
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob: https:; img-src 'self' data: https: blob:; frame-ancestors 'self';");
}

date_default_timezone_set('Asia/Colombo');

/* ---------------- Layout includes ---------------- */
include 'header.php';
include 'sidebar.php';

/* ---------------- DB connection (PDO) ---------------- */
$pdo = $pdo ?? null;
if (!$pdo) {
  $dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
  try {
    $pdo = new PDO($dsn, 'root', '', [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo "<div class='container'><div class='card'><h1>Low Stock Monitor</h1><div style='padding:20px'><div class='alert alert-error'>DB failed: ".htmlspecialchars($e->getMessage())."</div></div></div></div>";
    include 'footer.php';
    exit;
  }
}

/* ---------------- Inputs & defaults ---------------- */
$threshold     = max(0, (int)($_GET['threshold'] ?? 50));     // absolute qty threshold
$lookbackDays  = max(7, (int)($_GET['lookback']  ?? 60));     // sales window days
$coverTarget   = max(0, (int)($_GET['cover']     ?? 14));     // minimum days of cover target
$category      = trim((string)($_GET['category'] ?? ''));
$supplierId    = (isset($_GET['supplier']) && $_GET['supplier'] !== '') ? (int)$_GET['supplier'] : null;
$q             = trim((string)($_GET['q'] ?? ''));

$sinceDate = (new DateTimeImmutable("today -$lookbackDays days"))->format('Y-m-d');

/* ---------------- CSV Export (pre-output) ---------------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $stmt = prepareLowStockStmt($pdo, $sinceDate, $lookbackDays, $threshold, $coverTarget, $category, $supplierId, $q);
  $stmt->execute();
  $rows = $stmt->fetchAll();

  $filename = "low_stock_".date('Ymd_His').".csv";
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename='.$filename);
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ItemID','InvoiceID','Name','Category','Supplier','On-hand','Price','Sold('.$lookbackDays.'d)','Returned('.$lookbackDays.'d)','AvgNet/day','DaysCover','Status']);
  foreach ($rows as $r) {
    [$badgeText] = badgeForRow((int)$r['Quantity'], $threshold, $r['days_cover'], $coverTarget);
    fputcsv($out, [
      $r['ItemID'], $r['InvoiceID'], $r['NAME'], $r['Category'],
      $r['SupplierName'], (int)$r['Quantity'], number_format((float)$r['Price'],2,'.',''),
      (int)$r['sold_lookback'], (int)$r['returned_lookback'],
      $r['avg_net_daily'] === null ? '' : number_format((float)$r['avg_net_daily'],2,'.',''),
      $r['days_cover']    === null ? '' : number_format((float)$r['days_cover'],1,'.',''),
      $badgeText
    ]);
  }
  fclose($out);
  exit;
}

/* ---------------- Filter options ---------------- */
$cats = $pdo->query("SELECT DISTINCT Category FROM inventoryitem ORDER BY Category")->fetchAll();
$sups = $pdo->query("SELECT SupplierID, NAME AS SName FROM supplier ORDER BY SName")->fetchAll();

/* ---------------- Main data ---------------- */
$stmt = prepareLowStockStmt($pdo, $sinceDate, $lookbackDays, $threshold, $coverTarget, $category, $supplierId, $q);
$stmt->execute();
$items = $stmt->fetchAll();

/* ---------------- Styles: match add_inventory.php ---------------- */
?>
<style>
:root{
  --brand:#3b5683; --brand-dark:#324a70; --brand-ring:rgba(59,86,131,.25);
  --brand-tint:#e9eff7; --brand-tint-hover:#dde7f6; --border:#dfe6f2;
  --text:#3b5683; --muted:#6b7c97;
}

/* Base layout (match add_inventory) */
body{background:#ffffff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);border:1px solid var(--border);margin-bottom:24px;overflow:hidden;color:var(--text);}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);color:var(--text);display:flex;align-items:center;justify-content:space-between;gap:12px}

/* Controls */
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted);}
.input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;transition:border .2s, box-shadow .2s;color:var(--text);}
.input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-ring);}

/* Table (match look) */
.table{width:100%;border-collapse:separate;border-spacing:0 10px;margin-top:10px}
.table thead th{font-size:12px;color:var(--text);text-transform:uppercase;text-align:left;padding:6px;background:var(--brand-tint);}
.table tbody td{background:#fff;border:1px solid var(--border);padding:10px;vertical-align:top;color:var(--text);}

/* Buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;font-weight:600;border-radius:8px;padding:10px 16px;font-size:14px;cursor:pointer;border:1px solid transparent;transition:all .2s;user-select:none;}
.btn-primary{background:var(--brand);color:#fff;border-color:var(--brand);}
.btn-primary:hover{background:var(--brand);filter:brightness(1.05);}
.btn-primary:active{background:var(--brand-dark);}
.btn-primary:focus-visible{outline:none;box-shadow:0 0 0 3px var(--brand-ring);}
.btn-ghost{background:#fff;border:1px solid var(--border);color:var(--text);}
.btn-ghost:hover{background:var(--brand-tint);}
.btn-danger{background:#dc2626;color:#fff;}
.btn-danger:hover{background:#b91c1c;}

/* Badge */
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
.badge-low{background:#fef3c7;color:#92400e;}
.badge-critical{background:#fee2e2;color:#991b1b;}
.badge-ok{background:#dcfce7;color:#065f46;}

/* Print */
@media print{
  .sidebar, nav, .no-print { display:none !important; }
  .container{ margin:0; padding:0; max-width:100%; }
  .card{ box-shadow:none; border:1px solid #ddd; }
}
</style>

<div class="container">
  <div class="card">
    <h1>
      <span>Low Stock Monitor</span>
      <span class="no-print" style="display:flex;gap:8px">
        <button class="btn btn-ghost" onclick="window.print()">Print</button>
        <a class="btn btn-ghost" href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>">Export CSV</a>
        <a class="btn btn-primary" href="add_inventory.php">Add Stock</a>
      </span>
    </h1>

    <div style="padding:20px">
      <!-- Filters (grid 3x2 like add_inventory) -->
      <form method="get" class="no-print">
        <div class="grid" style="display:grid;gap:16px;grid-template-columns:1fr 1fr 1fr;max-width:100%">
          <div>
            <div class="label">Search</div>
            <input class="input" type="text" name="q" placeholder="Search item / invoice / supplier…" value="<?php echo htmlspecialchars($q); ?>">
          </div>
          <div>
            <div class="label">Category</div>
            <select class="input" name="category">
              <option value="">All Categories</option>
              <?php foreach ($cats as $c): $val = (string)$c['Category']; ?>
                <option value="<?php echo htmlspecialchars($val); ?>" <?php if ($category===$val) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($val); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <div class="label">Supplier</div>
            <select class="input" name="supplier">
              <option value="">All Suppliers</option>
              <?php foreach ($sups as $s): ?>
                <option value="<?php echo (int)$s['SupplierID']; ?>" <?php if ($supplierId===(int)$s['SupplierID']) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($s['SName']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <div class="label">Threshold (qty)</div>
            <input class="input" type="number" name="threshold" min="0" value="<?php echo $threshold; ?>">
          </div>
          <div>
            <div class="label">Lookback (days)</div>
            <input class="input" type="number" name="lookback" min="7" value="<?php echo $lookbackDays; ?>">
          </div>
          <div>
            <div class="label">Min Days of Cover</div>
            <input class="input" type="number" name="cover" min="0" value="<?php echo $coverTarget; ?>">
          </div>
        </div>

        <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap">
          <button class="btn btn-primary" type="submit">Apply</button>
          <a class="btn btn-ghost" href="low_stock.php">Reset</a>
        </div>
      </form>

      <!-- Table -->
      <table class="table" style="margin-top:18px">
        <thead>
          <tr>
            <th>Item</th>
            <th>Supplier</th>
            <th>Invoice</th>
            <th>Category</th>
            <th>On-hand</th>
            <th>Price</th>
            <th>Sold (<?php echo (int)$lookbackDays; ?>d)</th>
            <th>Returned (<?php echo (int)$lookbackDays; ?>d)</th>
            <th>Avg Net / day</th>
            <th>Days Cover</th>
            <th>Status</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr>
            <td colspan="12" style="text-align:center;color:#6b7c97">No items match your low-stock filters.</td>
          </tr>
        <?php else: foreach ($items as $r):
          $qty   = (int)$r['Quantity'];
          $avg   = $r['avg_net_daily'] !== null ? (float)$r['avg_net_daily'] : null;
          $cover = $r['days_cover']    !== null ? (float)$r['days_cover']    : null;
          [$badgeText, $badgeClass] = badgeForRow($qty, $threshold, $cover, $coverTarget);
        ?>
          <tr>
            <td>
              <div style="font-weight:700"><?php echo htmlspecialchars($r['NAME']); ?></div>
              <div style="font-size:12px;color:#6b7c97">ID: <?php echo (int)$r['ItemID']; ?></div>
            </td>
            <td><?php echo htmlspecialchars($r['SupplierName'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($r['InvoiceID']); ?></td>
            <td><?php echo htmlspecialchars($r['Category']); ?></td>
            <td><?php echo number_format($qty); ?></td>
            <td><?php echo number_format((float)$r['Price'],2); ?></td>
            <td><?php echo number_format((int)$r['sold_lookback']); ?></td>
            <td><?php echo number_format((int)$r['returned_lookback']); ?></td>
            <td><?php echo $avg===null ? '—' : number_format($avg,2); ?></td>
            <td><?php echo $cover===null ? '—' : number_format($cover,1); ?></td>
            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span></td>
            <td style="text-align:right; white-space:nowrap">
              <a class="btn btn-ghost" href="edit_inventory.php?item_id=<?php echo (int)$r['ItemID']; ?>">View / Edit</a>
              <a class="btn btn-primary" href="add_inventory.php?supplier_id=<?php echo (int)$r['SupplierID']; ?>&supplier_name=<?php echo urlencode((string)$r['SupplierName']); ?>">Add Stock</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div class="sticky-footer no-print" style="margin-top:14px; display:flex; justify-content:space-between; align-items:center">
        <div class="note">Tip: Lower the threshold or increase lookback days to adjust sensitivity.</div>
        <div>
          <button class="btn btn-ghost" onclick="window.scrollTo({top:0, behavior:'smooth'})">Back to top</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<?php
/* ======================= Helpers & Query ======================= */
function badgeForRow(int $qty, int $threshold, ?float $cover, int $coverTarget): array {
  // returns [text, cssClass]
  $text = 'OK'; $cls = 'badge-ok';
  $isLowByQty   = ($qty <= $threshold);
  $isLowByCover = ($cover !== null && $cover <= $coverTarget);

  if ($isLowByQty || $isLowByCover) {
    // Critical if very low quantity or very low cover
    $criticalQty   = $qty <= max(1, (int)round($threshold * 0.4));
    $criticalCover = ($cover !== null && $cover <= max(3, (int)round($coverTarget * 0.3)));
    if ($criticalQty || $criticalCover) { $text='CRITICAL'; $cls='badge-critical'; }
    else { $text='LOW'; $cls='badge-low'; }
  }
  return [$text, $cls];
}

function prepareLowStockStmt(PDO $pdo, string $sinceDate, int $lookbackDays, int $threshold, int $coverTarget, string $category, ?int $supplierId, string $q): PDOStatement {
  $w = ["1=1"];
  $p = [
    ':since' => $sinceDate,
    ':days'  => $lookbackDays,
    ':thr'   => $threshold,
    ':cover' => $coverTarget,
  ];
  if ($category !== '') { $w[] = "i.Category = :cat"; $p[':cat'] = $category; }
  if (!is_null($supplierId)) { $w[] = "i.SupplierID = :sid"; $p[':sid'] = $supplierId; }
  if ($q !== '') {
    $w[] = "(i.NAME LIKE :q OR i.InvoiceID LIKE :q OR COALESCE(i.SupplierName,'') LIKE :q)";
    $p[':q'] = '%'.$q.'%';
  }
  $where = implode(' AND ', $w);

  $sql = "
    WITH sales AS (
      SELECT od.ItemID, COALESCE(SUM(od.Quantity),0) AS sold_lookback
      FROM orderdetails od
      JOIN `order` o ON o.OrderID = od.OrderID
      WHERE o.OrderDate >= :since
      GROUP BY od.ItemID
    ),
    returns AS (
      SELECT cr.ItemID, COALESCE(SUM(cr.ReturnQty),0) AS returned_lookback
      FROM customer_return cr
      WHERE cr.ReturnDate >= :since
      GROUP BY cr.ItemID
    )
    SELECT
      i.ItemID, i.InvoiceID, i.NAME, i.Category, i.Quantity, i.Price,
      i.SupplierID, COALESCE(i.SupplierName, s.NAME) AS SupplierName,
      COALESCE(sa.sold_lookback,0)     AS sold_lookback,
      COALESCE(rt.returned_lookback,0) AS returned_lookback,
      NULLIF(ROUND(GREATEST(COALESCE(sa.sold_lookback,0) - COALESCE(rt.returned_lookback,0), 0) / NULLIF(:days,0), 2),0) AS avg_net_daily,
      CASE
        WHEN GREATEST(COALESCE(sa.sold_lookback,0) - COALESCE(rt.returned_lookback,0), 0) > 0
        THEN ROUND(i.Quantity / ((COALESCE(sa.sold_lookback,0) - COALESCE(rt.returned_lookback,0)) / :days), 1)
        ELSE NULL
      END AS days_cover
    FROM inventoryitem i
    LEFT JOIN sales   sa ON sa.ItemID = i.ItemID
    LEFT JOIN returns rt ON rt.ItemID = i.ItemID
    LEFT JOIN supplier s ON s.SupplierID = i.SupplierID
    WHERE $where
      AND (
        i.Quantity <= :thr
        OR (
          (COALESCE(sa.sold_lookback,0) - COALESCE(rt.returned_lookback,0)) > 0
          AND (i.Quantity / ((COALESCE(sa.sold_lookback,0) - COALESCE(rt.returned_lookback,0)) / :days)) <= :cover
        )
      )
    ORDER BY
      (i.Quantity <= :thr) DESC,
      CASE
        WHEN (COALESCE(sa.sold_lookback,0) - COALESCE(rt.returned_lookback,0)) > 0
        THEN (i.Quantity / ((COALESCE(sa.sold_lookback,0) - COALESCE(rt.returned_lookback,0)) / :days))
        ELSE 999999
      END ASC,
      i.Quantity ASC, i.NAME ASC
  ";

  $stmt = $pdo->prepare($sql);
  foreach ($p as $k => $v) $stmt->bindValue($k, $v);
  return $stmt;
}
