<?php
// customer.php (FINAL POLISH — No header underlines + Perfect Print + Excel CSV)
// - Normal view: filters/sort/pagination + clean header (no underline on sort links)
// - Print view (?view=print): bare report page (A4), border-collapse grid, repeat header, no rows split
// - CSV export (?export=csv): Excel-friendly (UTF-8 BOM), respects filters/sort

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

// ---- DB (PDO) ----
$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die("DB failed: " . htmlspecialchars($e->getMessage()));
}

// ---- Helpers ----
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cleanLike(string $s): string { return str_replace(['%','_'], ['\\%','\\_'], $s); }

$isPrintView = (($_GET['view'] ?? '') === 'print');

// ---- FLASH (from deletes/other PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Handle delete (POST only, CSRF) ---- (disabled in print view)
if (!$isPrintView && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!isset($_POST['_csrf']) || !hash_equals($CSRF, (string)$_POST['_csrf'])) {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>'Security check failed.'];
        header("Location: customer.php");
        exit();
    }
    $id = (int)($_POST['CustomerID'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM customer WHERE CustomerID = ?");
            $stmt->execute([$id]);
            $_SESSION['flash'] = ['ok'=>true, 'msg'=>"Customer #".h((string)$id)." deleted."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok'=>false, 'msg'=>"Delete failed: ".h($e->getMessage())];
        }
    }
    header("Location: customer.php");
    exit();
}

// ---- Filters (GET) ----
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Sort
$sortable = ['CustomerID','NAME','Email','Phone'];
$sort = in_array(($_GET['sort'] ?? ''), $sortable, true) ? (string)$_GET['sort'] : 'CustomerID';
$dir  = (($_GET['dir'] ?? '') === 'asc') ? 'asc' : 'desc';

// Pagination (disabled in print view → fetch ALL)
$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = $isPrintView ? 1000000 : (int)($_GET['size'] ?? 10);
if (!$isPrintView && !in_array($pageSize, [10,25,50,100], true)) $pageSize = 10;
$offset   = $isPrintView ? 0 : (($page - 1) * $pageSize);

// WHERE
$where = [];
$args  = [];
if ($q !== '') {
    $where[] = "(NAME LIKE :kw OR Email LIKE :kw OR Phone LIKE :kw OR Address LIKE :kw)";
    $args[':kw'] = '%'.cleanLike($q).'%';
}
$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Count
$cntSql = "SELECT COUNT(*) FROM customer $wsql";
$st = $pdo->prepare($cntSql);
$st->execute($args);
$total = (int)$st->fetchColumn();

// ---- CSV Export (Excel-friendly UTF-8 BOM) ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvSql = "
        SELECT CustomerID, NAME, Email, Phone, Address
        FROM customer
        $wsql
        ORDER BY $sort $dir, CustomerID DESC
    ";
    $st = $pdo->prepare($csvSql);
    $st->execute($args);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=customers_'.date('Ymd_His').'.csv');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','Email','Phone','Address']);
    while ($row = $st->fetch()) {
        fputcsv($out, [
            $row['CustomerID'],
            $row['NAME'],
            $row['Email'],
            $row['Phone'],
            preg_replace('/\R+/', ' ', (string)$row['Address']),
        ]);
    }
    fclose($out);
    exit();
}

// ---- Page data ----
$sql = "
  SELECT CustomerID, NAME, Email, Phone, Address
  FROM customer
  $wsql
  ORDER BY $sort $dir, CustomerID DESC
  LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sql);
foreach ($args as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

// Only include layout for normal view
if (!$isPrintView) {
    include 'header.php';
    include 'sidebar.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Customers<?= $isPrintView ? ' — Print' : '' ?></title>
<style>
/* ===== Base ===== */
:root { --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --bg:#ffffff; }
html,body{margin:0;padding:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;}

/* ===== Normal (screen) view ===== */
<?php if (!$isPrintView): ?>
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;padding:14px}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:16px;border:1px solid var(--line)}
.card h1{font-size:22px;font-weight:600;padding:16px 18px;border-bottom:1px solid var(--line);margin:0;color:#3b5683}

.toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:10px 16px;flex-wrap:wrap}
.stat{font-size:13px;color:#6b7c97}
.actions{display:flex;gap:8px;flex-wrap:wrap}

.btn{padding:8px 12px;border-radius:8px;border:1px solid var(--line);background:#fff;color:#3b5683;cursor:pointer;text-decoration:none;font-size:13px}
.btn:hover{background:#e9eff7}
.btn-primary{background:#3b5683;color:#fff;border:1px solid #3b5683}
.btn-danger{background:#dc2626;color:#fff;border:none}

.filters{padding:12px 16px 2px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
@media(max-width:760px){.grid{grid-template-columns:1fr}}
.label{font-size:12px;color:#5b6b86;margin-bottom:4px}
.input,select{width:100%;height:38px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:#3b5683}
.input:focus,select:focus{outline:none;border-color:#3b5683;box-shadow:0 0 0 3px rgba(59,86,131,.25)}

.table-wrap{overflow:auto}
.table{width:100%;border-collapse:separate;border-spacing:0}
.table th,.table td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap;background:#fff;color:#3b5683}
.table th{background:#eef2f8;font-weight:700;border-bottom:1px solid var(--line)}
/* —— Remove any link underline in table headers (topics) —— */
.table th a{color:#3b5683;text-decoration:none;border:none}
.table th a:hover,
.table th a:focus,
.table th a:active{color:#1f2e4a;text-decoration:none;border:none;outline:none}

.table td.addr{white-space:normal;max-width:480px}

.pager{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 16px;flex-wrap:wrap}
.pager .info{font-size:12px;color:#6b7c97}

.alert{padding:10px 12px;border-radius:8px;margin:10px 16px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Hide chrome when printing from normal page (fallback) */
@media print{
  .sidebar,.header,.footer,.toolbar,.filters,.pager,.btn,form[action]{display:none!important}
  .container{margin:0;padding:0}
  .card{box-shadow:none;border:none}
}
<?php endif; ?>

/* ===== Print view ===== */
<?php if ($isPrintView): ?>
/* Clean sheet */
html,body{background:#fff;color:#000;font-size:11pt;}
.container{padding:0;max-width:none}
.headerline{display:flex;justify-content:space-between;align-items:flex-end;margin:0 0 10px 0}
.headerline .title{font-size:14pt;font-weight:700}
.headerline .when{font-size:10pt;color:#111}
<?php endif; ?>

/* ===== Strong print rules for the report (apply to print view content) ===== */
@media print {
  @page { size: A4 portrait; margin: 12mm; }
  html,body{background:#fff !important;color:#000 !important}
}
/* Report block */
.report { padding:0 0 0 0; }

/* Table: border-collapse for crisp grid; fixed layout keeps columns steady */
.report .table {
  width:100%;
  border-collapse:collapse;
  table-layout:fixed;
  font-size:11pt;
  border:1px solid #000;
}

/* Header row (repeats on every page) */
.report thead { display: table-header-group; }
.report thead th{
  background:#fff;
  color:#000;
  border:1px solid #000;
  padding:6px 8px;
  text-align:left;
  font-weight:700;
  /* ensure no underline even if there are accidental links */
}
.report thead th a{color:#000;text-decoration:none;border:none}

/* Body rows */
.report tbody td{
  color:#000;
  border:1px solid #000;
  padding:6px 8px;
  vertical-align:top;
  word-wrap:break-word;
  overflow-wrap:break-word;
}

/* Avoid splitting a row between pages */
.report tr{break-inside:avoid-page}

/* Column widths to keep the full table aligned */
.col-id{width:60px}
.col-name{width:200px}
.col-email{width:240px}
.col-phone{width:140px}
.col-address{width:auto}
</style>
</head>
<body>

<?php if (!$isPrintView): ?>
<div class="container">
  <div class="card">
    <h1>Customers</h1>

    <div class="toolbar">
      <div class="stat">
        Total: <strong><?= number_format($total) ?></strong><?= $q!=='' ? ' • Filter: “'.h($q).'”' : '' ?>
      </div>
      <div class="actions">
        <a class="btn" href="add_customer.php">+ Add Customer</a>
        <a class="btn" href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">Export CSV</a>
        <!-- Dedicated clean print view -->
        <a class="btn btn-primary" target="_blank" href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['view'=>'print']))) ?>">Print</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div class="filters">
      <form method="get">
        <div class="grid">
          <div>
            <div class="label">Search</div>
            <input class="input" type="text" name="q" placeholder="Name / Email / Phone / Address" value="<?= h($q) ?>">
          </div>
          <div>
            <div class="label">Sort by</div>
            <?php $sortMap = ['CustomerID'=>'ID','NAME'=>'Name','Email'=>'Email','Phone'=>'Phone']; ?>
            <select name="sort" class="input">
              <?php foreach ($sortMap as $k=>$label): ?>
                <option value="<?= h($k) ?>" <?= $sort===$k?'selected':''; ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <div class="label">Direction</div>
            <select name="dir" class="input">
              <option value="desc" <?= $dir==='desc'?'selected':''; ?>>Desc</option>
              <option value="asc"  <?= $dir==='asc'?'selected':''; ?>>Asc</option>
            </select>
          </div>
        </div>

        <div class="toolbar" style="padding:10px 0 6px">
          <div class="stat">Page <?= (int)$page ?> of <?= max(1, (int)ceil($total / $pageSize)) ?></div>
          <div class="actions">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn" href="customer.php">Reset</a>
          </div>
        </div>
      </form>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><a href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['sort'=>'CustomerID','dir'=>($sort==='CustomerID' && $dir==='asc')?'desc':'asc']))) ?>">ID</a></th>
            <th><a href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['sort'=>'NAME','dir'=>($sort==='NAME' && $dir==='asc')?'desc':'asc']))) ?>">Name</a></th>
            <th><a href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['sort'=>'Email','dir'=>($sort==='Email' && $dir==='asc')?'desc':'asc']))) ?>">Email</a></th>
            <th><a href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['sort'=>'Phone','dir'=>($sort==='Phone' && $dir==='asc')?'desc':'asc']))) ?>">Phone</a></th>
            <th>Address</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" style="color:#64748b">No records found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td>#<?= (int)$r['CustomerID'] ?></td>
              <td><?= h($r['NAME']) ?></td>
              <td><?= h($r['Email']) ?></td>
              <td><?= h($r['Phone']) ?></td>
              <td class="addr"><?= nl2br(h($r['Address'])) ?></td>
              <td style="text-align:right">
                <a class="btn" href="edit_customer.php?id=<?= (int)$r['CustomerID'] ?>">Edit</a>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete customer #<?= (int)$r['CustomerID'] ?>? This may fail if they have orders/feedback.');">
                  <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="CustomerID" value="<?= (int)$r['CustomerID'] ?>">
                  <button class="btn btn-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="pager">
      <div class="info">
        <?php
          $from = $total ? ($offset + 1) : 0;
          $to   = min($offset + $pageSize, $total);
        ?>
        Showing <?= number_format($from) ?>–<?= number_format($to) ?> of <?= number_format($total) ?>
      </div>
      <div class="actions">
        <?php
          $pages = max(1, (int)ceil($total / $pageSize));
          $keepQ = $_GET;
          $keepQ['page'] = max(1, $page - 1);
          $prevUrl = $_SERVER['PHP_SELF'].'?'.http_build_query($keepQ);
          $keepQ['page'] = min($pages, $page + 1);
          $nextUrl = $_SERVER['PHP_SELF'].'?'.http_build_query($keepQ);
        ?>
        <a class="btn" href="<?= h($prevUrl) ?>" <?= $page<=1?'style="pointer-events:none;opacity:.5"':''; ?>>Prev</a>
        <a class="btn" href="<?= h($nextUrl) ?>" <?= $page>=$pages?'style="pointer-events:none;opacity:.5"':''; ?>>Next</a>

        <form method="get" style="display:inline">
          <?php foreach ($_GET as $k=>$v): if ($k==='size') continue; ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h(is_array($v)?'':$v) ?>">
          <?php endforeach; ?>
          <select name="size" class="input" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $s): ?>
              <option value="<?= $s ?>" <?= $pageSize===$s?'selected':''; ?>><?= $s ?>/page</option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

  </div>
</div>
<?php else: ?>
<!-- ===== DEDICATED PRINT VIEW (fetches ALL rows) ===== -->
<div class="report" id="report">
  <div class="headerline">
    <div class="title">RB Stores — Customers Report</div>
    <div class="when"><?= h(date('Y-m-d H:i')) ?></div>
  </div>

  <table class="table report">
    <thead>
      <tr>
        <th class="col-id">ID</th>
        <th class="col-name">Name</th>
        <th class="col-email">Email</th>
        <th class="col-phone">Phone</th>
        <th class="col-address">Address</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5">No records found.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['CustomerID'] ?></td>
          <td><?= h($r['NAME']) ?></td>
          <td><?= h($r['Email']) ?></td>
          <td><?= h($r['Phone']) ?></td>
          <td><?= h($r['Address']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
// Auto-open print dialog for the dedicated print view
window.addEventListener('load', () => { setTimeout(() => window.print(), 50); });
</script>
<?php endif; ?>

<?php if (!$isPrintView) include 'footer.php'; ?>
</body>
</html>
