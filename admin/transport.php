<?php
// transport.php (FULLY UPDATED — Perfect Print Report)
// - Print shows ONLY the report: header/sidebar/toggles/buttons all hidden
// - Uses a .print-root wrapper + strong @media print rule
// - Hides Actions column when printing; expands truncation; A4 margins
// - PDO, CSRF (for delete), safe filters/sort/pagination, CSV export

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
function cleanLike(string $s): string { return str_replace(['%', '_'], ['\\%','\\_'], $s); }

// ---- Flash (for delete) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Handle delete (POST only, with CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!isset($_POST['_csrf']) || !hash_equals($CSRF, (string)$_POST['_csrf'])) {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>'Security check failed.'];
        header("Location: transport.php"); exit();
    }
    $id = (int)($_POST['TransportID'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->prepare("DELETE FROM transport WHERE TransportID = ?")->execute([$id]);
            $_SESSION['flash'] = ['ok'=>true, 'msg'=>"Transport #".h((string)$id)." deleted."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok'=>false, 'msg'=>"Delete failed: ".h($e->getMessage())];
        }
    }
    header("Location: transport.php"); exit();
}

// ---- Filters (GET) ----
$statuses = ['Pending','Scheduled','Dispatched','Delivered','Canceled'];
$q_status   = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$q_from     = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$q_to       = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$q_vehicle  = isset($_GET['vehicle']) ? (int)$_GET['vehicle'] : 0;
$q_driver   = isset($_GET['driver']) ? (int)$_GET['driver'] : 0;
$q_search   = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($q_status === 'Cancelled') { $q_status = 'Canceled'; }

// Sort
$sortable = ['DeliveryDate','DeliveryTime','STATUS','TransportID'];
$sort = in_array(($_GET['sort'] ?? ''), $sortable, true) ? (string)$_GET['sort'] : 'DeliveryDate';
$dir  = (($_GET['dir'] ?? '') === 'asc') ? 'asc' : 'desc';

// Pagination
$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = (int)($_GET['size'] ?? 10);
if (!in_array($pageSize, [10,25,50,100], true)) $pageSize = 10;
$offset   = ($page - 1) * $pageSize;

// WHERE
$where = []; $args = [];
if ($q_status !== '' && in_array($q_status, $statuses, true)) { $where[] = "t.STATUS = :st"; $args[':st'] = $q_status; }
if ($q_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q_from)) { $where[] = "t.DeliveryDate >= :from"; $args[':from'] = $q_from; }
if ($q_to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q_to))   { $where[] = "t.DeliveryDate <= :to";   $args[':to']   = $q_to; }
if ($q_vehicle > 0) { $where[] = "t.VehicleID = :veh"; $args[':veh'] = $q_vehicle; }
if ($q_driver  > 0) { $where[] = "t.EmployeeID = :drv"; $args[':drv'] = $q_driver; }
if ($q_search !== '') {
  $where[] = "(t.Destination LIKE :kw OR o.InvoiceID LIKE :kw OR c.NAME LIKE :kw)";
  $args[':kw'] = '%'.cleanLike($q_search).'%';
}
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

// Dropdown data
$vehicles = $pdo->query("SELECT VehicleID, VehicleNumber FROM vehicle ORDER BY VehicleNumber ASC")->fetchAll();
$drivers  = $pdo->query("SELECT EmployeeID, Name FROM employee WHERE Role IS NOT NULL AND LOWER(Role) LIKE '%driver%' ORDER BY Name ASC")->fetchAll();

// Count
$stmt = $pdo->prepare("
  SELECT COUNT(*) FROM transport t
  LEFT JOIN `order` o ON o.OrderID = t.OrderID
  LEFT JOIN customer c ON c.CustomerID = o.CustomerID
  LEFT JOIN vehicle v ON v.VehicleID = t.VehicleID
  LEFT JOIN employee e ON e.EmployeeID = t.EmployeeID
  $wsql
");
$stmt->execute($args);
$total = (int)$stmt->fetchColumn();

// Pending deliveries link
$pendingDeliveriesUrl = 'pending_deliveries.php?' . http_build_query([
    'from'=>$q_from,'to'=>$q_to,'q'=>$q_search,'sort'=>'date_asc','page'=>1,'pp'=>10,
]);

// CSV
if (($_GET['export'] ?? '') === 'csv') {
  $st = $pdo->prepare("
    SELECT t.TransportID,t.DeliveryDate,t.DeliveryTime,t.STATUS,t.Destination,
           o.InvoiceID,c.NAME AS Customer,v.VehicleNumber,e.Name AS Driver,t.Notes
    FROM transport t
    LEFT JOIN `order` o ON o.OrderID = t.OrderID
    LEFT JOIN customer c ON c.CustomerID = o.CustomerID
    LEFT JOIN vehicle v ON v.VehicleID = t.VehicleID
    LEFT JOIN employee e ON e.EmployeeID = t.EmployeeID
    $wsql
    ORDER BY $sort $dir, t.TransportID DESC
  ");
  $st->execute($args);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=transport_report_'.date('Ymd_His').'.csv');
  $out = fopen('php://output','w');
  fputcsv($out, ['ID','Date','Time','Status','Destination','Invoice','Customer','Vehicle','Driver','Notes']);
  while ($row = $st->fetch()) {
    fputcsv($out, [
      $row['TransportID'],$row['DeliveryDate'],$row['DeliveryTime'],$row['STATUS'],$row['Destination'],
      $row['InvoiceID'],$row['Customer'],$row['VehicleNumber'],$row['Driver'],$row['Notes']
    ]);
  }
  fclose($out); exit();
}

// Data
$stmt = $pdo->prepare("
  SELECT t.TransportID,t.DeliveryDate,t.DeliveryTime,t.STATUS,t.Destination,t.OrderID,
         o.InvoiceID,c.NAME AS Customer,v.VehicleNumber,v.VehicleType,e.Name AS Driver
  FROM transport t
  LEFT JOIN `order` o ON o.OrderID = t.OrderID
  LEFT JOIN customer c ON c.CustomerID = o.CustomerID
  LEFT JOIN vehicle v ON v.VehicleID = t.VehicleID
  LEFT JOIN employee e ON e.EmployeeID = t.EmployeeID
  $wsql
  ORDER BY $sort $dir, t.TransportID DESC
  LIMIT :lim OFFSET :off
");
foreach ($args as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$pageSize,PDO::PARAM_INT);
$stmt->bindValue(':off',$offset,PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// Filters summary for print
$applied=[]; if($q_from) $applied[]="From: $q_from"; if($q_to) $applied[]="To: $q_to";
if($q_status) $applied[]="Status: $q_status";
if($q_vehicle){ foreach($vehicles as $vv) if((int)$vv['VehicleID']===$q_vehicle){ $applied[]="Vehicle: ".$vv['VehicleNumber']; break; } }
if($q_driver){ foreach($drivers as $dd) if((int)$dd['EmployeeID']===$q_driver){ $applied[]="Driver: ".$dd['Name']; break; } }
if($q_search) $applied[]="Search: $q_search";
$filtersSummary = $applied ? implode(' | ', array_map('h',$applied)) : 'All records';

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business, responsive --- */
body{background:#ffffff;color:#3b5683;margin:0;}
.print-root.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.print-root.container{margin-left:0;padding:14px}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:16px;border:1px solid #dfe6f2;}
.card h1{font-size:22px;font-weight:600;padding:16px 18px;border-bottom:1px solid #dfe6f2;margin:0;color:#3b5683;}
.toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:10px 16px;flex-wrap:wrap}
.stat{font-size:13px;color:#6b7c97}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{padding:8px 12px;border-radius:8px;border:1px solid #dfe6f2;background:#fff;color:#3b5683;cursor:pointer;text-decoration:none;font-size:13px;transition:background .2s,filter .2s}
.btn:hover{background:#e9eff7}
.btn-primary{background:#3b5683;color:#fff;border:1px solid #3b5683}
.btn-danger{background:#dc2626;color:#fff;border:none}
.btn-ghost{background:#fff;border:1px solid #dfe6f2;color:#3b5683}

.filters{padding:12px 16px 2px}
.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
@media(max-width:1100px){.grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.label{font-size:12px;color:#5b6b86;margin-bottom:4px}
.input,select{width:100%;height:38px;padding:8px 10px;border:1px solid #dfe6f2;border-radius:8px;background:#fff;color:#3b5683}
.input:focus,select:focus{outline:none;border-color:#3b5683;box-shadow:0 0 0 3px rgba(59,86,131,.25)}

.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;border-bottom:1px solid #dfe6f2;text-align:left;white-space:nowrap;color:#3b5683;background:#fff}
th{background:#eef2f8;font-weight:600}
th a{color:#3b5683;text-decoration:none}
th a:hover{text-decoration:underline}
td .truncate{display:inline-block;max-width:260px;overflow:hidden;text-overflow:ellipsis;vertical-align:bottom}

/* Badges */
.badge{padding:4px 8px;border-radius:999px;font-size:12px;display:inline-block;border:1px solid transparent}
.badge.Pending{background:#fff7ed;color:#9a3412;border-color:#fed7aa}
.badge.Scheduled{background:#eff6ff;color:#1e40af;border-color:#bfdbfe}
.badge.Dispatched{background:#ecfeff;color:#0e7490;border-color:#a5f3fc}
.badge.Delivered{background:#ecfdf5;color:#065f46;border-color:#bbf7d0}
.badge.Canceled,.badge.Cancelled{background:#fef2f2;color:#7f1d1d;border-color:#fecaca}

/* Pager */
.pager{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 16px;flex-wrap:wrap}
.pager .info{font-size:12px;color:#6b7c97}
.select{height:34px;padding:6px 10px;border:1px solid #dfe6f2;border-radius:8px;background:#fff;color:#3b5683}

/* ---------- PRINT: show ONLY this report ---------- */
@page { size: A4 portrait; margin: 14mm; }

/* Ensure only this block prints, regardless of what header/sidebar render */
@media print{
  body > :not(.print-root){ display:none !important; }       /* <-- critical line */
  .print-root{ display:block !important; margin:0 !important; padding:0 !important; max-width:100% !important; }

  /* Hide common layout chrome and toggles just in case */
  header,.header,nav,.nav,aside,.sidebar,.topbar,.app-header,.appbar,.navbar,.breadcrumbs,.breadcrumb,.brand,.logo{ display:none !important; }
  .menu-toggle,.sidebar-toggle,.hamburger,.toggle,.fa-bars,.fas.fa-bars,.mdi-menu,.lucide-menu{ display:none !important; }

  .actions,.filters,.pager,.btn,.toolbar .actions{ display:none !important; }
  .card{ box-shadow:none; border:none }
  .card h1 i, .card h1 svg { display:none !important; }      /* hide any icon inside title */

  thead{ display:table-header-group; }
  tfoot{ display:table-row-group; }
  th,td{ white-space:normal !important; font-size:12px; }
  td .truncate{ max-width:none; overflow:visible; text-overflow:clip }
  .col-actions, .col-actions *{ display:none !important; }

  /* Cleaner colors on paper */
  .badge{ background:#fff !important; color:#000 !important; border:1px solid #999 !important; }
}

/* Alerts */
.alert{padding:10px 12px;border-radius:8px;margin:10px 16px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
</style>

<div class="print-root container">
  <div class="card">
    <h1>Transport Report</h1>

    <!-- PRINT HEADER -->
    <div class="print-only" style="padding:0 18px 10px">
      <div style="font-size:18px;font-weight:700">RB Stores</div>
      <div style="font-size:12px;color:#6b7280;margin-top:2px">Rainwater Solutions Inventory &amp; Order Management</div>
      <div style="margin-top:10px;font-size:12px"><strong>Filters:</strong> <?= $filtersSummary ?></div>
      <div style="margin-top:2px;font-size:12px;color:#6b7280">Printed on <?= h(date('Y-m-d H:i')) ?> (Asia/Colombo)</div>
      <hr style="border:none;border-top:1px solid #dfe6f2;margin:10px 0 0">
    </div>

    <div class="toolbar">
      <div class="stat">Total: <strong><?= number_format($total) ?></strong></div>
      <div class="actions">
        <a class="btn" href="add_transport.php">+ Add Transport</a>
        <a class="btn btn-primary" href="<?= h($pendingDeliveriesUrl) ?>">Pending Deliveries</a>
        <a class="btn" href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">Export CSV</a>
        <button class="btn" onclick="window.print()">Print</button>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <!-- Filters (screen only) -->
    <div class="filters">
      <form method="get">
        <div class="grid">
          <div><div class="label">From</div><input class="input" type="date" name="from" value="<?= h($q_from) ?>"></div>
          <div><div class="label">To</div><input class="input" type="date" name="to" value="<?= h($q_to) ?>"></div>
          <div><div class="label">Status</div>
            <select name="status">
              <option value="">— Any —</option>
              <?php foreach ($statuses as $s): ?>
                <option value="<?= h($s) ?>" <?= $q_status===$s?'selected':''; ?>><?= h($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><div class="label">Vehicle</div>
            <select name="vehicle">
              <option value="0">— Any —</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= (int)$v['VehicleID'] ?>" <?= $q_vehicle===(int)$v['VehicleID']?'selected':''; ?>><?= h($v['VehicleNumber']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><div class="label">Driver</div>
            <select name="driver">
              <option value="0">— Any —</option>
              <?php foreach ($drivers as $d): ?>
                <option value="<?= (int)$d['EmployeeID'] ?>" <?= $q_driver===(int)$d['EmployeeID']?'selected':''; ?>><?= h($d['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><div class="label">Search</div><input class="input" type="text" name="q" placeholder="Destination / Invoice / Customer" value="<?= h($q_search) ?>"></div>
        </div>

        <div class="toolbar" style="padding:10px 0 6px">
          <div class="stat">Showing page <?= (int)$page ?> of <?= max(1, (int)ceil($total / $pageSize)) ?></div>
          <div class="actions">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-ghost" href="transport.php">Reset</a>
          </div>
        </div>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['sort'=>'TransportID','dir'=>($sort==='TransportID' && $dir==='asc')?'desc':'asc']))) ?>">ID</a></th>
            <th><a href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['sort'=>'DeliveryDate','dir'=>($sort==='DeliveryDate' && $dir==='asc')?'desc':'asc']))) ?>">Date</a></th>
            <th><a href="<?= h($_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['sort'=>'DeliveryTime','dir'=>($sort==='DeliveryTime' && $dir==='asc')?'desc':'asc']))) ?>">Time</a></th>
            <th>Status</th>
            <th>Destination</th>
            <th>Invoice / Customer</th>
            <th>Vehicle</th>
            <th>Driver</th>
            <th class="col-actions" style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="9" style="color:#64748b">No records found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td>#<?= (int)$r['TransportID'] ?></td>
              <td><?= h($r['DeliveryDate']) ?></td>
              <td><?= h(substr((string)$r['DeliveryTime'],0,5)) ?></td>
              <td><span class="badge <?= h($r['STATUS']) ?>"><?= h($r['STATUS']) ?></span></td>
              <td title="<?= h($r['Destination']) ?>"><span class="truncate"><?= h($r['Destination']) ?></span></td>
              <td>
                <?= $r['InvoiceID'] ? h($r['InvoiceID']) : '—' ?><?php if ($r['Customer']): ?> • <?= h($r['Customer']) ?><?php endif; ?>
              </td>
              <td>
                <?= $r['VehicleNumber'] ? h($r['VehicleNumber']) : '—' ?><?php if ($r['VehicleType']): ?> <small style="color:#64748b"> (<?= h($r['VehicleType']) ?>)</small><?php endif; ?>
              </td>
              <td><?= $r['Driver'] ? h($r['Driver']) : '—' ?></td>
              <td class="col-actions" style="text-align:right">
                <a class="btn" href="edit_transport.php?id=<?= (int)$r['TransportID'] ?>">Edit</a>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete transport #<?= (int)$r['TransportID'] ?>?');">
                  <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="TransportID" value="<?= (int)$r['TransportID'] ?>">
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
        <?php $from = $total ? ($offset + 1) : 0; $to = min($offset + $pageSize, $total); ?>
        Showing <?= number_format($from) ?>–<?= number_format($to) ?> of <?= number_format($total) ?>
      </div>
      <div class="actions">
        <?php
          $pages = max(1, (int)ceil($total / $pageSize));
          $q = $_GET; $q['page'] = max(1, $page-1); $prevUrl = $_SERVER['PHP_SELF'].'?'.http_build_query($q);
          $q['page'] = min($pages, $page+1); $nextUrl = $_SERVER['PHP_SELF'].'?'.http_build_query($q);
        ?>
        <a class="btn" href="<?= h($prevUrl) ?>" <?= $page<=1?'style="pointer-events:none;opacity:.5"':''; ?>>Prev</a>
        <a class="btn" href="<?= h($nextUrl) ?>" <?= $page>=$pages?'style="pointer-events:none;opacity:.5"':''; ?>>Next</a>
        <form method="get" style="display:inline">
          <?php foreach ($_GET as $k=>$v): if ($k==='size') continue; ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h(is_array($v)?'':$v) ?>">
          <?php endforeach; ?>
          <select name="size" class="select" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $s): ?>
              <option value="<?= $s ?>" <?= $pageSize===$s?'selected':''; ?>><?= $s ?>/page</option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

  </div>
</div>

<?php include 'footer.php'; ?>
