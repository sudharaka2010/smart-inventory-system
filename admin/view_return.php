<?php
// view_return.php (FULL UPDATED)
// Business/Professional white theme (card, alerts), matches your sample structure
// Features: filters (date/supplier/search), pagination, CSV export, safe delete (stock restore)

declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

// ---- CSRF ----
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

// ---- DB (PDO like your view_billing.php) ----
$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die("DB failed: " . htmlspecialchars($e->getMessage()));
}

/* -------------------------------------------
   Helpers
------------------------------------------- */
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dt(?string $ts): string { return $ts ? date('Y-m-d H:i', strtotime($ts)) : ''; }
function qs(array $a): string {
    return http_build_query($a, '', '&', PHP_QUERY_RFC3986);
}
/* PRG flash */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* -------------------------------------------
   Actions (run BEFORE any output)
   - CSV export (GET export=csv)
   - Delete/Undo (POST action=delete)
------------------------------------------- */

/* Build filter from GET (used by all actions) */
$search     = trim((string)($_GET['q'] ?? ''));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$from       = trim((string)($_GET['from'] ?? ''));
$to         = trim((string)($_GET['to'] ?? ''));

$where = [];
$args  = [];

if ($search !== '') {
    $where[] = "(r.InvoiceID LIKE :q OR COALESCE(r.SupplierName,'') LIKE :q OR COALESCE(i.NAME,'') LIKE :q OR COALESCE(r.ReturnReason,'') LIKE :q)";
    $args[':q'] = "%{$search}%";
}
if ($supplierId > 0) {
    $where[] = "r.SupplierID = :sid";
    $args[':sid'] = $supplierId;
}
if ($from !== '') {
    $where[] = "r.ReturnDate >= :from";
    // Normalize to start of minute if needed
    $args[':from'] = date('Y-m-d H:i:s', strtotime($from));
}
if ($to !== '') {
    $where[] = "r.ReturnDate <= :to";
    $args[':to'] = date('Y-m-d H:i:s', strtotime($to));
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---- CSV Export ---- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $sql = "
            SELECT r.ReturnID, r.ReturnDate, r.InvoiceID, i.ItemID, i.NAME AS ItemName,
                   r.ReturnQuantity, r.ReturnReason, r.SupplierID, r.SupplierName
            FROM returnitem r
            LEFT JOIN inventoryitem i ON i.ItemID = r.ItemID
            $whereSql
            ORDER BY r.ReturnDate DESC, r.ReturnID DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="returns_'.date('Ymd_His').'.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ReturnID','ReturnDate','InvoiceID','ItemID','ItemName','ReturnQuantity','ReturnReason','SupplierID','SupplierName']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['ReturnID'],
                $r['ReturnDate'],
                $r['InvoiceID'],
                $r['ItemID'],
                $r['ItemName'],
                $r['ReturnQuantity'],
                $r['ReturnReason'],
                $r['SupplierID'],
                $r['SupplierName'],
            ]);
        }
        fclose($out);
        exit;
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>"CSV export failed."];
        header('Location: view_return.php?'.qs($_GET));
        exit;
    }
}

/* ---- Delete + Undo stock ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        if (!isset($_POST['_csrf']) || !hash_equals($CSRF, (string)$_POST['_csrf'])) {
            throw new RuntimeException("Security check failed.");
        }
        $rid = (int)($_POST['ReturnID'] ?? 0);
        if ($rid <= 0) throw new InvalidArgumentException("Invalid ReturnID.");

        $pdo->beginTransaction();

        // Fetch return row (lock)
        $ret = $pdo->prepare("SELECT * FROM returnitem WHERE ReturnID = ? FOR UPDATE");
        $ret->execute([$rid]);
        $r = $ret->fetch();
        if (!$r) throw new InvalidArgumentException("Return record not found.");

        $itemId  = (int)$r['ItemID'];
        $qtyBack = (int)$r['ReturnQuantity'];

        // Lock inventory row and add quantity back
        $itm = $pdo->prepare("SELECT Quantity FROM inventoryitem WHERE ItemID = ? FOR UPDATE");
        $itm->execute([$itemId]);
        $i = $itm->fetch();
        if (!$i) throw new InvalidArgumentException("Related inventory item not found.");

        $newQty = (int)$i['Quantity'] + $qtyBack;
        $upd = $pdo->prepare("UPDATE inventoryitem SET Quantity = ? WHERE ItemID = ?");
        $upd->execute([$newQty, $itemId]);

        // Delete return row
        $del = $pdo->prepare("DELETE FROM returnitem WHERE ReturnID = ?");
        $del->execute([$rid]);

        $pdo->commit();
        $_SESSION['flash'] = ['ok'=>true, 'msg'=>"Return #$rid deleted and stock restored (Item #$itemId new qty: $newQty)."];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = $e instanceof InvalidArgumentException ? $e->getMessage() : "Failed to delete return.";
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>h($msg)];
    }
    // PRG back to current filtered page
    header('Location: view_return.php?'.qs($_GET));
    exit;
}

/* -------------------------------------------
   Data (paged)
------------------------------------------- */
$perPage = max(5, (int)($_GET['pp'] ?? 10));
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* Counts and totals for current filter */
$metaSql = "
  SELECT COUNT(*) AS cnt, COALESCE(SUM(r.ReturnQuantity),0) AS qty
  FROM returnitem r
  LEFT JOIN inventoryitem i ON i.ItemID = r.ItemID
  $whereSql
";
$meta = $pdo->prepare($metaSql);
$meta->execute($args);
$tot = $meta->fetch() ?: ['cnt'=>0,'qty'=>0];

$listSql = "
  SELECT r.ReturnID, r.ReturnDate, r.InvoiceID, r.ReturnQuantity, r.ReturnReason,
         r.SupplierID, r.SupplierName,
         i.ItemID, i.NAME AS ItemName
  FROM returnitem r
  LEFT JOIN inventoryitem i ON i.ItemID = r.ItemID
  $whereSql
  ORDER BY r.ReturnDate DESC, r.ReturnID DESC
  LIMIT :limit OFFSET :offset
";
$list = $pdo->prepare($listSql);
foreach ($args as $k=>$v) {
    $list->bindValue($k, $v);
}
$list->bindValue(':limit', $perPage, PDO::PARAM_INT);
$list->bindValue(':offset', $offset, PDO::PARAM_INT);
$list->execute();
$rows = $list->fetchAll();

/* Supplier options for filter */
$suppliers = $pdo->query("SELECT SupplierID, COALESCE(NAME,'(Unnamed)') AS NAME FROM supplier ORDER BY NAME")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business UI (brand blue #3b5683) ---- */
:root{
  --brand:#3b5683;
  --brand-dark:#324a70;
  --ring:rgba(59,86,131,.25);
  --text:#3b5683;
  --muted:#6b7c97;
  --border:#dfe6f2;
  --thead:#eef2f8;
  --row-sep:#f1f5f9;
  --row-hover:#f6f9fc;
}

/* Layout */
body{background:#ffffff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{
  background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;
  border:1px solid var(--border);
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);margin:0;
  color:var(--text);
}

/* Headerbar */
.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px;gap:12px;flex-wrap:wrap}
.headerbar .btn{text-decoration:none}

/* Alerts (semantic colors kept) */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Filters */
.filters{
  padding:16px 20px;display:grid;grid-template-columns:repeat(12,1fr);gap:12px
}
.filters .input, .filters select{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--text);
  transition:border-color .2s, box-shadow .2s, background .2s, color .2s;
}
.filters .input:focus, .filters select:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);
}
.filters .col-3{grid-column:span 3}
.filters .col-2{grid-column:span 2}
.filters .col-4{grid-column:span 4}
.filters .col-12{grid-column:span 12}
@media(max-width:992px){.filters .col-3,.filters .col-2,.filters .col-4{grid-column:span 12}}

/* Table */
.table-wrap{padding:0 20px 12px 20px;overflow:auto}
table{
  width:100%;border-collapse:separate;border-spacing:0;border:1px solid var(--border);
  border-radius:12px;overflow:hidden;background:#fff
}
thead th{
  background:var(--thead);color:var(--text);font-weight:700;font-size:13px;padding:12px;
  border-bottom:1px solid var(--border);text-align:left;white-space:nowrap
}
tbody td{
  padding:12px;border-bottom:1px solid var(--row-sep);font-size:14px;color:var(--text);vertical-align:top;background:#fff
}
tbody tr:hover{background:var(--row-hover)}
.badge{
  display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:12px;background:#fff;color:var(--text)
}
.reason{max-width:440px;color:var(--muted)}
.muted{color:var(--muted)}

/* Toolbar / Actions */
.toolbar{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;gap:10px;flex-wrap:wrap}
.stat{font-size:14px;color:var(--text)}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{
  padding:8px 12px;border-radius:8px;font-weight:600;font-size:14px;border:1px solid var(--border);
  background:#fff;color:var(--text);cursor:pointer;transition:background .2s, color .2s, box-shadow .2s, filter .2s
}
.btn:hover{background:#e9eff7}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring)}
.btn-primary{background:var(--brand);color:#fff;border:1px solid var(--brand)}
.btn-primary:hover{background:var(--brand);filter:brightness(1.05)}
.btn-primary:active{background:var(--brand-dark)}
.btn-danger{background:#ef4444;color:#fff;border:none}
.btn-danger:hover{background:#dc2626}
.btn-ghost{background:#fff;border:1px solid var(--border);color:var(--text)}

/* Pager */
.pager{display:flex;gap:6px;align-items:center}
.pager a,.pager span{
  padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:#fff;text-decoration:none;color:var(--text)
}
.pager .active{background:var(--brand);border-color:var(--brand);color:#fff}
</style>


<div class="container">
  <div class="card">
    <h1>Returns</h1>

    <div class="headerbar">
      <div class="stat muted">Review, filter, export, and manage returned items.</div>
      <div class="actions">
        <a class="btn" href="add_return.php">Add Return</a>
        <a class="btn btn-ghost" href="view_return.php">Reset Filters</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form class="filters" method="get">
      <div class="col-4">
        <input class="input" type="text" name="q" value="<?= h($search) ?>" placeholder="Search invoice / item / reason / supplier">
      </div>
      <div class="col-3">
        <select name="supplier_id" class="input">
          <option value="0">All Suppliers</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['SupplierID'] ?>" <?= $supplierId===(int)$s['SupplierID']?'selected':''; ?>>
              <?= h($s['NAME']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-2">
        <input class="input" type="date" name="from" value="<?= h($from) ?>" max="<?= h(date('Y-m-d')) ?>" placeholder="From">
      </div>
      <div class="col-2">
        <input class="input" type="date" name="to" value="<?= h($to) ?>" max="<?= h(date('Y-m-d')) ?>" placeholder="To">
      </div>
      <div class="col-1">
        <select class="input" name="pp">
          <?php foreach ([10,20,50] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':''; ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12" style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn" href="view_return.php?<?= h(qs(array_merge($_GET,['export'=>'csv']))) ?>">Export CSV</a>
      </div>
    </form>

    <!-- Toolbar: totals + pagination -->
    <div class="toolbar">
      <div class="stat">
        <?= (int)$tot['cnt'] ?> returns · Total quantity: <strong><?= (int)$tot['qty'] ?></strong>
      </div>
      <div class="pager">
        <?php
          $pages = max(1, (int)ceil(($tot['cnt'] ?: 0) / $perPage));
          $base = $_GET; unset($base['page']);
          if ($page > 1){
              $prev = $page - 1;
              echo '<a href="view_return.php?'.h(qs($base+['page'=>$prev])).'">Prev</a>';
          } else {
              echo '<span>Prev</span>';
          }
          echo '<span class="active">'.(int)$page.' / '.(int)$pages.'</span>';
          if ($page < $pages){
              $next = $page + 1;
              echo '<a href="view_return.php?'.h(qs($base+['page'=>$next])).'">Next</a>';
          } else {
              echo '<span>Next</span>';
          }
        ?>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Return Date</th>
            <th>Invoice</th>
            <th>Item</th>
            <th>Supplier</th>
            <th>Qty</th>
            <th>Reason</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="muted" style="text-align:center;padding:28px">No returns found for the selected filters.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>#<?= (int)$r['ReturnID'] ?></td>
                <td><span class="badge"><?= h(dt($r['ReturnDate'])) ?></span></td>
                <td><?= h($r['InvoiceID']) ?></td>
                <td>
                  <?= h($r['ItemName'] ?? '(Deleted item)') ?>
                  <span class="muted">#<?= (int)($r['ItemID'] ?? 0) ?></span>
                </td>
                <td><?= h($r['SupplierName'] ?? '') ?> <span class="muted">#<?= (int)($r['SupplierID'] ?? 0) ?></span></td>
                <td><strong><?= (int)$r['ReturnQuantity'] ?></strong></td>
                <td class="reason"><?= nl2br(h($r['ReturnReason'] ?? '')) ?></td>
                <td style="text-align:right">
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this return and restore stock?');">
                    <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="ReturnID" value="<?= (int)$r['ReturnID'] ?>">
                    <?php
                      // preserve filters on PRG redirect
                      foreach ($_GET as $k=>$v) {
                        if ($k==='page') continue;
                        echo '<input type="hidden" name="'.h($k).'" value="'.h((string)$v).'">';
                      }
                    ?>
                    <button type="submit" class="btn btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Bottom pager duplicate (optional) -->
    <div class="toolbar" style="border-top:1px solid #e5e7eb">
      <div class="stat muted">Showing page <?= (int)$page ?> of <?= (int)max(1, (int)ceil(($tot['cnt'] ?: 0) / $perPage)) ?></div>
      <div class="pager">
        <?php
          $pages = max(1, (int)ceil(($tot['cnt'] ?: 0) / $perPage));
          $base = $_GET; unset($base['page']);
          if ($page > 1){
              echo '<a href="view_return.php?'.h(qs($base+['page'=>$page-1])).'">Prev</a>';
          } else { echo '<span>Prev</span>'; }
          echo '<span class="active">'.(int)$page.' / '.(int)$pages.'</span>';
          if ($page < $pages){
              echo '<a href="view_return.php?'.h(qs($base+['page'=>$page+1])).'">Next</a>';
          } else { echo '<span>Next</span>'; }
        ?>
      </div>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>
