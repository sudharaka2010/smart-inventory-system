<?php
// view_vehicle.php — PRO BUSINESS UI (matches add_vehicle.php)
// - White card theme, consistent spacing & typography
// - Distinct filled action buttons: View (indigo), Edit (blue), Delete (rose)
// - Filters (number/type/driver/min-max load), sort, pagination
// - CSV export (?export=csv), Print
// - Safe delete (blocks if referenced in transport)
// - Accessible table (caption, sticky head), clearer totals and per-page control

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

// Helpers
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function getq(string $k, ?string $fallback=null): string { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : (string)($fallback ?? ''); }
function qurl(array $overrides=[]): string { $qs = array_merge($_GET, $overrides); return ($_SERVER['PHP_SELF'] ?? 'view_vehicle.php').'?'.http_build_query($qs); }

// ---- POST: Delete (safe) ----
if (
  $_SERVER['REQUEST_METHOD']==='POST' &&
  isset($_POST['action']) && $_POST['action']==='delete' &&
  isset($_POST['_csrf']) && hash_equals($CSRF, (string)$_POST['_csrf'])
) {
  $id = (int)($_POST['VehicleID'] ?? 0);
  if ($id <= 0) {
    $_SESSION['flash'] = ['ok'=>false,'msg'=>'Invalid vehicle.'];
    header('Location: '.qurl()); exit;
  }

  // block if used by transport
  $st = $pdo->prepare("SELECT COUNT(*) FROM transport WHERE VehicleID=?");
  $st->execute([$id]);
  if ((int)$st->fetchColumn() > 0) {
    $_SESSION['flash'] = ['ok'=>false,'msg'=>'Cannot delete — this vehicle is referenced in Transport records.'];
    header('Location: '.qurl()); exit;
  }

  try {
    $del = $pdo->prepare("DELETE FROM vehicle WHERE VehicleID=?");
    $del->execute([$id]);
    $_SESSION['flash'] = ['ok'=>true,'msg'=>'Vehicle deleted successfully.'];
  } catch (Throwable $e) {
    $_SESSION['flash'] = ['ok'=>false,'msg'=>'Delete failed: '.h($e->getMessage())];
  }
  header('Location: '.qurl()); exit;
}

// ---- Filters & Sort ----
$filters = [
  'number' => getq('number'),
  'type'   => getq('type'),
  'driver' => getq('driver'),
  'minload'=> getq('minload'),
  'maxload'=> getq('maxload'),
];

$sortMap = [
  'id'     => 'v.VehicleID',
  'number' => 'v.VehicleNumber',
  'type'   => 'v.VehicleType',
  'load'   => 'v.MaxLoadKg',
  'driver' => 'e.Name',
];
$sKey = strtolower(getq('s','id'));
$sCol = $sortMap[$sKey] ?? $sortMap['id'];
$sDir = strtolower(getq('d','desc')) === 'asc' ? 'ASC' : 'DESC';

$buildWhere = function(array $f): array {
  $w=[]; $p=[];
  if ($f['number']!==''){ $w[]="v.VehicleNumber LIKE ?"; $p[]='%'.$f['number'].'%'; }
  if ($f['type']  !==''){ $w[]="v.VehicleType LIKE ?";   $p[]='%'.$f['type'].'%'; }
  if ($f['driver']!==''){
    // allow search by driver name or exact employee ID
    $w[]="(e.Name LIKE ? OR e.EmployeeID = ?)";
    $p[]='%'.$f['driver'].'%'; $p[]=$f['driver'];
  }
  if ($f['minload']!=='' && is_numeric($f['minload'])){ $w[]="v.MaxLoadKg >= ?"; $p[]=(float)$f['minload']; }
  if ($f['maxload']!=='' && is_numeric($f['maxload'])){ $w[]="v.MaxLoadKg <= ?"; $p[]=(float)$f['maxload']; }
  return [$w?(' WHERE '.implode(' AND ',$w)):'', $p];
};

// ---- CSV export ----
if (isset($_GET['export']) && $_GET['export']==='csv') {
  [$where,$params] = $buildWhere($filters);
  $sql = "SELECT v.VehicleID, v.VehicleNumber, v.VehicleType, v.MaxLoadKg, v.DriverID,
                 e.Name AS DriverName, e.Role AS DriverRole, e.Contact AS DriverContact
          FROM vehicle v
          LEFT JOIN employee e ON e.EmployeeID = v.DriverID
          $where
          ORDER BY $sCol $sDir";
  $st = $pdo->prepare($sql); $st->execute($params);
  $rows = $st->fetchAll();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="vehicles_'.date('Ymd_His').'.csv"');
  $out=fopen('php://output','w');
  fputcsv($out,['VehicleID','VehicleNumber','VehicleType','MaxLoadKg','DriverID','DriverName','DriverRole','DriverContact']);
  foreach($rows as $r){
    fputcsv($out,[
      $r['VehicleID'],$r['VehicleNumber'],$r['VehicleType'],$r['MaxLoadKg'],
      $r['DriverID'],$r['DriverName'],$r['DriverRole'],$r['DriverContact']
    ]);
  }
  fclose($out); exit;
}

// ---- Pagination + Totals + List ----
$page = max(1,(int)getq('page','1'));
$per  = (int)getq('per','10'); if(!in_array($per,[10,25,50,100],true)) $per=10;
$offset = ($page-1)*$per;

[$where,$params] = $buildWhere($filters);

// Totals
$stT = $pdo->prepare("SELECT COUNT(*) c,
                             SUM(v.MaxLoadKg) sumLoad,
                             SUM(CASE WHEN v.DriverID IS NOT NULL THEN 1 ELSE 0 END) assigned
                      FROM vehicle v
                      LEFT JOIN employee e ON e.EmployeeID = v.DriverID
                      $where");
$stT->execute($params);
$tot = $stT->fetch() ?: ['c'=>0,'sumLoad'=>0,'assigned'=>0];
$totalRows=(int)($tot['c']??0); $sumLoad=(float)($tot['sumLoad']??0); $assigned=(int)($tot['assigned']??0);

// Page rows
$listSql = "SELECT v.VehicleID, v.VehicleNumber, v.VehicleType, v.MaxLoadKg, v.DriverID,
                   e.Name AS DriverName, e.Role AS DriverRole, e.Contact AS DriverContact
            FROM vehicle v
            LEFT JOIN employee e ON e.EmployeeID = v.DriverID
            $where
            ORDER BY $sCol $sDir
            LIMIT $per OFFSET $offset";
$st = $pdo->prepare($listSql); $st->execute($params);
$rows = $st->fetchAll();

// Driver dropdown (prefer drivers first)
$driverOpts = $pdo->query("
  SELECT EmployeeID, Name 
  FROM employee 
  WHERE Role LIKE '%driver%' OR Role LIKE '%Driver%' OR Role LIKE '%DRIVER%'
  ORDER BY Name ASC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

include 'header.php';
include 'sidebar.php';
?>
<!-- ================== STYLES (matches add_vehicle.php; upgraded buttons) ================== -->
<style>
:root{
  --bg:#f4f6f9;
  --card:#ffffff;
  --ink:#1f2937;         /* slate-800 */
  --muted:#6b7280;       /* slate-500 */
  --line:#e5e7eb;        /* gray-200 */
  --brand:#2563eb;       /* blue-600 */
  --brand-d:#1d4ed8;     /* blue-700 */

  --indigo:#4f46e5;      /* indigo-600 */
  --indigo-d:#4338ca;

  --blue:#2563eb;        /* blue-600 */
  --blue-d:#1d4ed8;

  --rose:#e11d48;        /* rose-600 */
  --rose-d:#be123c;

  --ok-bg:#dcfce7;       --ok-ink:#166534;
  --warn-bg:#fef3c7;     --warn-ink:#92400e;
  --info-bg:#eff6ff;     --info-ink:#1e40af;
  --shadow:0 4px 14px rgba(0,0,0,.08);
}

body{background:var(--bg);color:var(--ink);margin:0;}
.container{margin-left:260px;padding:20px;max-width:1100px;}
@media(max-width:992px){.container{margin-left:0;padding:16px;}}

.card{background:var(--card);border-radius:16px;box-shadow:var(--shadow);margin-bottom:24px;overflow:hidden;}
.card h1{font-size:22px;font-weight:700;padding:18px 20px;border-bottom:1px solid var(--line);margin:0;}

.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px;gap:12px;flex-wrap:wrap}
.hint{font-size:13px;color:var(--muted)}

.alert{padding:12px 14px;border-radius:10px;margin:12px 20px;font-size:14px;border:1px solid}
.alert-success{background:var(--ok-bg);color:var(--ok-ink);border-color:#bbf7d0}
.alert-error{background:#fee2e2;color:#991b1b;border-color:#fecaca}
.alert-info{background:var(--info-bg);color:var(--info-ink);border-color:#bfdbfe}

.form-wrap{padding:16px 20px 22px 20px;}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
@media(max-width:1020px){.grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:720px){.grid{grid-template-columns:1fr;}}

.label{font-weight:600;font-size:13px;margin-bottom:6px;color:#334155;display:flex;align-items:center;justify-content:space-between;gap:8px}
.input,select,textarea{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;background:#fff;}
.input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.18);}

.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.btn{padding:10px 16px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:8px;line-height:1;}
.btn:disabled{opacity:.6;cursor:not-allowed}
.btn-ghost{background:#ffffff;border:1px solid #cbd5e1;color:#334155;}
.btn-ghost:hover{background:#f8fafc}
.btn-primary{background:var(--brand);color:#fff;}
.btn-primary:hover{background:var(--brand-d)}
.btn-view{background:var(--indigo);color:#fff;}
.btn-view:hover{background:var(--indigo-d)}
.btn-edit{background:var(--blue);color:#fff;}
.btn-edit:hover{background:var(--blue-d)}
.btn-delete{background:var(--rose);color:#fff;}
.btn-delete:hover{background:var(--rose-d)}

.table-wrap{padding:0 20px 20px 20px;}
table{width:100%;border-collapse:separate;border-spacing:0}
caption{caption-side:top;text-align:left;padding:12px 0 8px 0;font-weight:700;color:#334155}
thead th{position:sticky;top:0;background:#fbfcff;z-index:1;font-size:12px;text-align:left;
  padding:12px;border-bottom:1px solid var(--line);color:#64748b;letter-spacing:.02em}
tbody td{padding:14px 12px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
tbody tr:hover{background:#f9fbff}
.badge{padding:4px 10px;border-radius:999px;font-weight:700;font-size:11px}
.badge-ok{background:var(--ok-bg);color:var(--ok-ink)}
.badge-warn{background:var(--warn-bg);color:var(--warn-ink)}
.muted{color:var(--muted)}
.page-btn{padding:8px 12px;border:1px solid #cbd5e1;background:#fff;border-radius:10px;font-size:13px;text-decoration:none;color:#0f172a}
.page-btn.active{background:var(--brand);color:#fff;border-color:var(--brand)}
.sort-link{color:inherit;text-decoration:none}
.sort-link i{margin-left:6px;color:#9aa1af}

/* Compact “Per page” control */
.perpage{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
.perpage a{margin-left:0}

/* Print cleanup */
@media print{
  .headerbar,.form-wrap .actions,.actions .btn,.sidebar-toggle-btn{display:none !important;}
  .container{margin:0;max-width:none;padding:0}
  .card{box-shadow:none;border-radius:0}
  thead th{position:static;}
}
</style>

<div class="container">
  <div class="card">
    <h1>Vehicles</h1>

    <div class="headerbar">
      <div class="hint">Search, sort, and manage your fleet. Export or print as needed.</div>
      <div class="actions">
        <a class="btn btn-ghost" href="<?= h(qurl(['export'=>'csv'])) ?>"><i class="fa fa-file-csv"></i> Export CSV</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
        <a class="btn btn-primary" href="add_vehicle.php"><i class="fa fa-plus"></i> Add Vehicle</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="form-wrap">
      <form class="grid" method="get" autocomplete="off">
        <div>
          <div class="label">Vehicle Number</div>
          <input class="input" type="text" name="number" value="<?= h($filters['number']) ?>" placeholder="e.g., CAD-2398">
          <div class="hint">Search by plate number</div>
        </div>
        <div>
          <div class="label">Vehicle Type</div>
          <input class="input" type="text" name="type" value="<?= h($filters['type']) ?>" placeholder="e.g., Truck, Van">
        </div>
        <div>
          <div class="label">Driver (Name or ID)</div>
          <select class="input" name="driver">
            <option value="">— All —</option>
            <?php
              $cur = $filters['driver'];
              foreach ($driverOpts as $d) {
                $val = (string)$d['EmployeeID'];
                $sel = ($cur !== '' && $cur == $val) ? 'selected' : '';
                echo '<option value="'.h($val).'" '.$sel.'>'.h($d['Name'].' (ID '.$d['EmployeeID'].')').'</option>';
              }
            ?>
          </select>
          <div class="hint">Tip: You can also type an EmployeeID in the URL (?driver=12)</div>
        </div>
        <div>
          <div class="label">Min Load (kg)</div>
          <input class="input" type="number" step="0.01" name="minload" value="<?= h($filters['minload']) ?>">
        </div>
        <div>
          <div class="label">Max Load (kg)</div>
          <input class="input" type="number" step="0.01" name="maxload" value="<?= h($filters['maxload']) ?>">
        </div>
        <div class="actions" style="align-items:flex-end">
          <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Filter</button>
          <a class="btn btn-ghost" href="<?= h($_SERVER['PHP_SELF'] ?? 'view_vehicle.php') ?>"><i class="fa fa-rotate-left"></i> Reset</a>
          <div class="perpage">
            Per page:
            <?php foreach ([10,25,50,100] as $opt): ?>
              <?php $url = qurl(['per'=>$opt,'page'=>1]); ?>
              <a class="page-btn <?= $per===$opt?'active':'' ?>" href="<?= h($url) ?>"><?= $opt ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      </form>
    </div>

    <div class="table-wrap">
      <table role="table" aria-describedby="vehicle_table_caption">
        <caption id="vehicle_table_caption">Vehicle registry — totals and assignments</caption>
        <thead>
          <tr>
            <?php
              $curS=strtolower(getq('s','id')); $curD=strtolower(getq('d','desc'));
              $mk=function(string $key,string $label) use($curS,$curD){
                $nextD = ($curS===$key && $curD==='asc') ? 'desc' : 'asc';
                $icon = ($curS===$key) ? ($curD==='asc'?'fa-arrow-up-wide-short':'fa-arrow-down-wide-short') : 'fa-up-down';
                return '<a class="sort-link" href="'.h(qurl(['s'=>$key,'d'=>$nextD])).'">'.h($label).' <i class="fa '.$icon.'"></i></a>';
              };
            ?>
            <th><?= $mk('id','ID') ?></th>
            <th><?= $mk('number','Vehicle Number') ?></th>
            <th><?= $mk('type','Type') ?></th>
            <th style="min-width:140px"><?= $mk('load','Max Load (kg)') ?></th>
            <th><?= $mk('driver','Driver') ?></th>
            <th style="min-width:220px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="muted">No vehicles found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['VehicleID'] ?></td>
              <td><?= h($r['VehicleNumber']) ?></td>
              <td><?= h($r['VehicleType']) ?></td>
              <td><?= number_format((float)$r['MaxLoadKg'],2) ?></td>
              <td>
                <?php if ($r['DriverID']): ?>
                  <span class="badge badge-ok" title="Assigned driver"><?= h($r['DriverName'] ?? 'Unknown') ?></span>
                <?php else: ?>
                  <span class="badge badge-warn" title="No driver assigned">Unassigned</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions" style="margin:0">
                  <a class="btn btn-view" href="vehicle_details.php?id=<?= (int)$r['VehicleID'] ?>"><i class="fa fa-eye"></i> View</a>
                  <a class="btn btn-edit" href="edit_vehicle.php?id=<?= (int)$r['VehicleID'] ?>"><i class="fa fa-pen"></i> Edit</a>
                  <form method="post" onsubmit="return confirm('Delete this vehicle? This action cannot be undone.');" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="VehicleID" value="<?= (int)$r['VehicleID'] ?>">
                    <button class="btn btn-delete" type="submit"><i class="fa fa-trash"></i> Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php
      $totalPages = max(1,(int)ceil($totalRows/$per));
      $first=1; $prev=max(1,$page-1); $next=min($totalPages,$page+1); $last=$totalPages;
      $u = fn(int $p)=> qurl(['page'=>$p]);
    ?>
    <div class="form-wrap" style="padding-top:8px">
      <div class="actions" style="justify-content:space-between;width:100%;gap:14px;flex-wrap:wrap">
        <div class="hint">
          <strong>Total:</strong> <?= number_format($totalRows) ?> vehicles ·
          <strong>Assigned:</strong> <?= number_format($assigned) ?> ·
          <strong>Sum Load:</strong> <?= number_format($sumLoad,2) ?> kg
        </div>
        <div>
          <a class="page-btn" href="<?= h($u($first)) ?>">&laquo; First</a>
          <a class="page-btn" href="<?= h($u($prev)) ?>">&lsaquo; Prev</a>
          <?php for($i=max(1,$page-2); $i<=min($last,$page+2); $i++): ?>
            <a class="page-btn <?= $i===$page?'active':'' ?>" href="<?= h($u($i)) ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a class="page-btn" href="<?= h($u($next)) ?>">Next &rsaquo;</a>
          <a class="page-btn" href="<?= h($u($last)) ?>">Last &raquo;</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
