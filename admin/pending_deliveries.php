<?php
// pending_deliveries.php (FIXED)
// - White, professional theme like add_inventory.php
// - CSRF + PRG flash
// - Filters/sort/pagination + CSV export
// - Bulk actions (Dispatched/Delivered/Cancel)
// - KPI bug fixed: $kpi_all now defined and cast-safe

declare(strict_types=1);
session_start();

/* ---------------- Security headers ---------------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

/* ---------------- CSRF token ---------------- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* ---------------- Flash helpers (PRG) ---------------- */
function flash_push(string $type, string $msg): void { $_SESSION['flash'][$type][] = $msg; }
function flash_take(): array { $o = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $o; }

/* ---------------- DB (PDO) ---------------- */
if (!isset($pdo)) {
  $dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
  try {
    $pdo = new PDO($dsn, 'root', '', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    die("DB connection failed: " . htmlspecialchars($e->getMessage()));
  }
}

/* ---------------- Helpers ---------------- */
function build_preserve_qs(array $extra = []): string {
  $base = [
    'status' => $_GET['status'] ?? '',
    'from'   => $_GET['from'] ?? '',
    'to'     => $_GET['to'] ?? '',
    'q'      => $_GET['q'] ?? '',
    'sort'   => $_GET['sort'] ?? 'date_asc',
    'page'   => (int)($_GET['page'] ?? 1),
    'pp'     => (int)($_GET['pp'] ?? 10),
  ];
  return http_build_query(array_merge($base, $extra));
}

/* ---------------- Early CSV export ---------------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  try {
    $q_status = trim($_GET['status'] ?? '');
    $q_from   = trim($_GET['from'] ?? '');
    $q_to     = trim($_GET['to'] ?? '');
    $q_search = trim($_GET['q'] ?? '');

    $params = [];
    $where  = [];

    if ($q_status !== '') {
      $statuses = array_filter(array_map('trim', explode(',', $q_status)));
      if ($statuses) {
        $in = implode(',', array_fill(0, count($statuses), '?'));
        $where[] = "t.STATUS IN ($in)";
        $params = array_merge($params, $statuses);
      }
    } else {
      $where[] = "t.STATUS IN ('Pending','Scheduled','Dispatched')";
    }

    if ($q_from !== '') { $where[] = "t.DeliveryDate >= ?"; $params[] = $q_from; }
    if ($q_to   !== '') { $where[] = "t.DeliveryDate <= ?"; $params[] = $q_to; }

    if ($q_search !== '') {
      $where[] = "(t.Destination LIKE ? OR o.InvoiceID LIKE ? OR c.NAME LIKE ? OR v.VehicleNumber LIKE ?)";
      $like = "%$q_search%";
      array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $sql = "
      SELECT
        t.TransportID, t.DeliveryDate, t.DeliveryTime, t.Destination, t.STATUS,
        o.InvoiceID, c.NAME AS CustomerName, v.VehicleNumber, v.VehicleType, e.Name AS DriverName
      FROM transport t
      LEFT JOIN `order` o  ON o.OrderID = t.OrderID
      LEFT JOIN customer c ON c.CustomerID = o.CustomerID
      LEFT JOIN vehicle v  ON v.VehicleID = t.VehicleID
      LEFT JOIN employee e ON e.EmployeeID = t.EmployeeID
      $whereSql
      ORDER BY t.DeliveryDate ASC, t.DeliveryTime ASC, t.TransportID ASC
    ";
    $st = $pdo->prepare($sql); $st->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pending_deliveries_'.date('Ymd_His').'.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['TransportID','DeliveryDate','DeliveryTime','InvoiceID','Customer','Destination','Vehicle','VehicleType','Driver','Status']);
    while ($r = $st->fetch()) {
      fputcsv($out, [
        $r['TransportID'], $r['DeliveryDate'], $r['DeliveryTime'], $r['InvoiceID'],
        $r['CustomerName'], $r['Destination'], $r['VehicleNumber'], $r['VehicleType'],
        $r['DriverName'], $r['STATUS']
      ]);
    }
    fclose($out);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "CSV export failed: " . $e->getMessage();
    exit;
  }
}

/* ---------------- POST (bulk actions) — PRG ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['_csrf'])) {
    flash_push('error', 'Invalid request. Please refresh and try again.');
    header("Location: pending_deliveries.php"); exit;
  }
  $action = $_POST['action'] ?? '';
  $ids    = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_filter(array_map('intval', $ids), fn($v)=>$v>0));

  $allowed = ['dispatched','delivered','cancel'];
  if ($ids && in_array($action, $allowed, true)) {
    $newStatus = ['dispatched'=>'Dispatched','delivered'=>'Delivered','cancel'=>'Canceled'][$action] ?? null;
    try {
      $pdo->beginTransaction();
      $in = implode(',', array_fill(0, count($ids), '?'));
      $sql = "UPDATE transport SET STATUS=? WHERE TransportID IN ($in)";
      $params = array_merge([$newStatus], $ids);
      $st = $pdo->prepare($sql); $st->execute($params);
      $pdo->commit();
      flash_push('success', "Updated ".count($ids)." record(s) to <b>$newStatus</b>.");
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash_push('error', "Bulk update failed: ".htmlspecialchars($e->getMessage()));
    }
  } else {
    flash_push('warning', 'No records selected or invalid action.');
  }
  $qs = $_POST['preserve_qs'] ?? '';
  header("Location: pending_deliveries.php".($qs?('?'.$qs):'')); exit;
}

/* ---------------- Quick single action — PRG ---------------- */
if (isset($_GET['quick'], $_GET['id'], $_GET['_csrf']) && hash_equals($CSRF, (string)$_GET['_csrf'])) {
  $quick = $_GET['quick']; $id = (int)$_GET['id'];
  if ($id>0 && in_array($quick, ['dispatch','deliver'], true)) {
    $to = $quick==='dispatch' ? 'Dispatched' : 'Delivered';
    try {
      $st = $pdo->prepare("UPDATE transport SET STATUS=? WHERE TransportID=?");
      $st->execute([$to, $id]);
      flash_push('success', "Delivery #$id updated to <b>$to</b>.");
    } catch (Throwable $e) {
      flash_push('error', "Update failed: ".htmlspecialchars($e->getMessage()));
    }
  }
  header("Location: pending_deliveries.php?".build_preserve_qs()); exit;
}

/* ---------------- Filters + data ---------------- */
$q_status = trim($_GET['status'] ?? '');
$q_from   = trim($_GET['from'] ?? '');
$q_to     = trim($_GET['to'] ?? '');
$q_search = trim($_GET['q'] ?? '');
$sort     = trim($_GET['sort'] ?? 'date_asc');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(100, max(5, (int)($_GET['pp'] ?? 10)));

$params = []; $where = [];
if ($q_status !== '') {
  $statuses = array_filter(array_map('trim', explode(',', $q_status)));
  if ($statuses) { $in = implode(',', array_fill(0, count($statuses), '?')); $where[] = "t.STATUS IN ($in)"; $params = array_merge($params, $statuses); }
} else { $where[] = "t.STATUS IN ('Pending','Scheduled','Dispatched')"; }
if ($q_from !== '') { $where[] = "t.DeliveryDate >= ?"; $params[] = $q_from; }
if ($q_to   !== '') { $where[] = "t.DeliveryDate <= ?"; $params[] = $q_to; }
if ($q_search !== '') {
  $where[] = "(t.Destination LIKE ? OR o.InvoiceID LIKE ? OR c.NAME LIKE ? OR v.VehicleNumber LIKE ?)";
  $like = "%$q_search%"; array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

$orderBy = match ($sort) {
  'date_desc' => "t.DeliveryDate DESC, t.DeliveryTime DESC, t.TransportID DESC",
  'status'    => "t.STATUS ASC, t.DeliveryDate ASC, t.DeliveryTime ASC",
  'customer'  => "c.NAME ASC, t.DeliveryDate ASC, t.DeliveryTime ASC",
  'vehicle'   => "v.VehicleNumber ASC, t.DeliveryDate ASC, t.DeliveryTime ASC",
  default     => "t.DeliveryDate ASC, t.DeliveryTime ASC, t.TransportID ASC"
};

$stCount = $pdo->prepare("
  SELECT COUNT(*) FROM transport t
  LEFT JOIN `order` o ON o.OrderID = t.OrderID
  LEFT JOIN customer c ON c.CustomerID = o.CustomerID
  LEFT JOIN vehicle v ON v.VehicleID = t.VehicleID
  $whereSql
");
$stCount->execute($params);
$total  = (int)($stCount->fetchColumn() ?? 0);
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stList = $pdo->prepare("
  SELECT
    t.TransportID, t.DeliveryDate, t.DeliveryTime, t.Destination, t.STATUS,
    o.InvoiceID, c.CustomerID, c.NAME AS CustomerName,
    v.VehicleNumber, v.VehicleType,
    e.Name AS DriverName
  FROM transport t
  LEFT JOIN `order` o  ON o.OrderID = t.OrderID
  LEFT JOIN customer c ON c.CustomerID = o.CustomerID
  LEFT JOIN vehicle v  ON v.VehicleID = t.VehicleID
  LEFT JOIN employee e ON e.EmployeeID = t.EmployeeID
  $whereSql
  ORDER BY $orderBy
  LIMIT $perPage OFFSET $offset
");
$stList->execute($params);
$rows = $stList->fetchAll();

/* ---------------- KPIs (all cast-safe) ---------------- */
$today = date('Y-m-d');

$kpi_today = (int)($pdo->prepare("SELECT COUNT(*) FROM transport WHERE STATUS IN ('Pending','Scheduled','Dispatched') AND DeliveryDate = ?")
                 ->execute([$today]) || true ? $pdo->query("SELECT COUNT(*) FROM transport WHERE STATUS IN ('Pending','Scheduled','Dispatched') AND DeliveryDate = '".$today."'")->fetchColumn() : 0);
/* The above line is verbose; use separate queries for clarity: */
$kpi_today = (int)($pdo->query("SELECT COUNT(*) FROM transport WHERE STATUS IN ('Pending','Scheduled','Dispatched') AND DeliveryDate = '{$today}'")->fetchColumn() ?? 0);

$kpi_overdue = (int)($pdo->query("SELECT COUNT(*) FROM transport WHERE DeliveryDate < '{$today}' AND STATUS NOT IN ('Delivered','Canceled')")->fetchColumn() ?? 0);
$kpi_next    = (int)($pdo->query("SELECT COUNT(*) FROM transport WHERE STATUS IN ('Pending','Scheduled','Dispatched') AND DeliveryDate BETWEEN '{$today}' AND '".date('Y-m-d', strtotime('+7 days'))."'")->fetchColumn() ?? 0);
$kpi_all     = (int)($pdo->query("SELECT COUNT(*) FROM transport WHERE STATUS IN ('Pending','Scheduled','Dispatched')")->fetchColumn() ?? 0); // <-- FIXED

/* ---------------- Flashes & includes ---------------- */
$flashes = flash_take();
include 'header.php';
include 'sidebar.php';
?>
<style>
:root{
  --brand:#3b5683; --brand-dark:#324a70; --brand-ring:rgba(59,86,131,.25);
  --brand-tint:#e9eff7; --brand-tint-hover:#dde7f6; --border:#dfe6f2;
  --text:#3b5683; --muted:#6b7c97; --ok:#10b981; --warn:#f59e0b; --bad:#ef4444; --primary:#3b5683;
}
body{background:#fff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;}}
.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);border:1px solid var(--border);margin-bottom:24px;overflow:hidden;color:var(--text);}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);}
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted);}
.input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;transition:border .2s, box-shadow .2s;color:var(--text);}
.input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-ring);}
.btn{display:inline-flex;align-items:center;justify-content:center;font-weight:600;border-radius:8px;padding:10px 16px;font-size:14px;cursor:pointer;border:1px solid transparent;transition:all .2s;user-select:none;}
.btn-primary{background:var(--primary);color:#fff;border-color:var(--primary);}
.btn-ok{background:var(--ok);color:#fff;border-color:var(--ok);}
.btn-danger{background:var(--bad);color:#fff;border-color:var(--bad);}
.btn-ghost{background:#fff;border:1px solid var(--border);color:var(--text);}
.btn-ghost:hover{background:var(--brand-tint);}
.grid{display:grid;gap:16px;grid-template-columns:repeat(4,1fr)}
@media(max-width:1100px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:620px){.grid{grid-template-columns:1fr}}
.table{width:100%;border-collapse:separate;border-spacing:0 10px;margin-top:10px}
.table thead th{font-size:12px;color:var(--text);text-transform:uppercase;text-align:left;padding:6px;background:var(--brand-tint);}
.table td{background:#fff;border:1px solid var(--border);padding:10px;vertical-align:top;color:var(--text);}
.table .badge{padding:6px 10px;border-radius:20px;font-size:12px;border:1px solid var(--border);display:inline-block}
.badge.pending{background:#fff7ed;color:#b45309;border-color:#fbbf24}
.badge.scheduled{background:#eef2ff;color:#4338ca;border-color:#c7d2fe}
.badge.dispatched{background:#ecfeff;color:#0e7490;border-color:#67e8f9}
.badge.delivered{background:#ecfdf5;color:#065f46;border-color:#34d399}
.badge.canceled{background:#fef2f2;color:#991b1b;border-color:#fca5a5}
.alert{padding:12px 14px;border-radius:8px;margin:10px 0;font-size:14px}
.alert-success{background:#ecf5ff;color:var(--primary);border:1px solid var(--border);}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fde2e2;}
.alert-warning{background:#fff7ed;color:#b45309;border:1px solid #fde68a;}
.sticky-footer{display:flex;justify-content:space-between;align-items:center;margin-top:20px}
.note{font-size:12px;color:var(--muted);}
@media print {
  .sidebar, .card .toolbar, .filters-row, .pagination, .bulk-actions { display:none !important; }
  .container{margin:0;padding:0;}
}
</style>

<div class="container">
  <!-- KPIs -->
  <div class="card">
    <h1>Pending Deliveries</h1>
    <div style="padding:20px">
      <?php if (!empty($flashes['success'])) foreach ($flashes['success'] as $m) echo '<div class="alert alert-success">'.$m.'</div>'; ?>
      <?php if (!empty($flashes['error'])) foreach ($flashes['error'] as $m) echo '<div class="alert alert-error">'.$m.'</div>'; ?>
      <?php if (!empty($flashes['warning'])) foreach ($flashes['warning'] as $m) echo '<div class="alert alert-warning">'.$m.'</div>'; ?>

      <div class="grid">
        <div class="card" style="margin:0;"><div style="padding:12px"><div class="label">Pending Today</div><div style="font-size:22px;font-weight:700;"><?= number_format((int)$kpi_today) ?></div></div></div>
        <div class="card" style="margin:0;"><div style="padding:12px"><div class="label">Overdue</div><div style="font-size:22px;font-weight:700;"><?= number_format((int)$kpi_overdue) ?></div></div></div>
        <div class="card" style="margin:0;"><div style="padding:12px"><div class="label">Next 7 Days</div><div style="font-size:22px;font-weight:700;"><?= number_format((int)$kpi_next) ?></div></div></div>
        <div class="card" style="margin:0;"><div style="padding:12px"><div class="label">All Pending-like</div><div style="font-size:22px;font-weight:700;"><?= number_format((int)$kpi_all) ?></div></div></div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="card filters-row" autocomplete="off" style="overflow:visible;">
    <div style="padding:20px">
      <div style="display:grid;gap:16px;grid-template-columns:repeat(6,1fr)">
        <div>
          <div class="label">Status</div>
          <select name="status" class="input">
            <?php $opts=['','Pending','Scheduled','Dispatched','Delivered','Canceled'];
            foreach($opts as $opt){ $sel=$opt===($q_status) ? 'selected' : ''; $label=$opt===''?'Pending-like (default)':$opt;
              echo "<option value=\"".htmlspecialchars($opt)."\" $sel>".htmlspecialchars($label)."</option>"; } ?>
          </select>
        </div>
        <div><div class="label">From</div><input type="date" name="from" class="input" value="<?= htmlspecialchars($q_from) ?>"></div>
        <div><div class="label">To</div><input type="date" name="to" class="input" value="<?= htmlspecialchars($q_to) ?>"></div>
        <div style="grid-column: span 2;"><div class="label">Search</div><input type="text" name="q" class="input" placeholder="Invoice / Customer / Vehicle / Destination" value="<?= htmlspecialchars($q_search) ?>"></div>
        <div>
          <div class="label">Sort</div>
          <select name="sort" class="input">
            <?php $sorts=['date_asc'=>'Date ↑','date_desc'=>'Date ↓','status'=>'Status','customer'=>'Customer','vehicle'=>'Vehicle'];
            foreach($sorts as $k=>$v){ $sel=$sort===$k?'selected':''; echo "<option value=\"$k\" $sel>".htmlspecialchars($v)."</option>"; } ?>
          </select>
        </div>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px;align-items:center;">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-ghost" href="pending_deliveries.php">Reset</a>
        <a class="btn btn-ghost" href="pending_deliveries.php?<?= htmlspecialchars(build_preserve_qs(['export'=>'csv'])) ?>">Export CSV</a>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center;">
          <span class="label" style="margin:0;">Per page</span>
          <select class="input" onchange="(function(){const u=new URL(location.href);u.searchParams.set('pp',this.value);u.searchParams.set('page','1');location.href=u.toString();}).call(this)">
            <?php foreach([10,20,50,100] as $pp){ $sel=$perPage===$pp?'selected':''; echo "<option value=\"$pp\" $sel>$pp</option>"; } ?>
          </select>
          <span class="label" style="margin:0;">Total: <?= number_format((int)$total) ?></span>
        </div>
      </div>
    </div>
  </form>

  <!-- Table + Bulk actions -->
  <form method="post" class="card" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="preserve_qs" value="<?= htmlspecialchars(build_preserve_qs()) ?>">

    <div class="toolbar bulk-actions" style="padding:14px 20px;display:flex;gap:10px;">
      <button class="btn btn-ok" name="action" value="dispatched" type="submit">Mark Dispatched</button>
      <button class="btn btn-primary" name="action" value="delivered" type="submit">Mark Delivered</button>
      <button class="btn btn-danger" name="action" value="cancel" type="submit" onclick="return confirm('Cancel selected deliveries?')">Cancel</button>
      <div style="margin-left:auto;"><a class="btn btn-ghost" href="add_transport.php">+ Add Transport</a></div>
    </div>

    <div style="padding:0 20px 20px 20px;">
      <table class="table">
        <thead>
          <tr>
            <th><input type="checkbox" id="chk_all"></th>
            <th>Date</th><th>Time</th><th>Invoice</th><th>Customer</th><th>Destination</th><th>Vehicle</th><th>Driver</th><th>Status</th><th style="width:210px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--muted);">No deliveries match the current filters.</td></tr>
          <?php else: foreach($rows as $r):
            $st = strtolower($r['STATUS'] ?? '');
            $badgeClass = match($st){'pending'=>'pending','scheduled'=>'scheduled','dispatched'=>'dispatched','delivered'=>'delivered','canceled'=>'canceled',default=>'pending'};
          ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int)$r['TransportID'] ?>"></td>
              <td><?= htmlspecialchars($r['DeliveryDate'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['DeliveryTime'] ?? '') ?></td>
              <td><?php if (!empty($r['InvoiceID'])): ?><a href="invoice.php?invoice=<?= urlencode($r['InvoiceID']) ?>"><?= htmlspecialchars($r['InvoiceID']) ?></a><?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?></td>
              <td><?= htmlspecialchars($r['CustomerName'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['Destination'] ?? '—') ?></td>
              <td><?= htmlspecialchars(trim(($r['VehicleNumber'] ?? '').($r['VehicleType'] ? ' ('.$r['VehicleType'].')' : ''))) ?></td>
              <td><?= htmlspecialchars($r['DriverName'] ?? '—') ?></td>
              <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($r['STATUS'] ?? '') ?></span></td>
              <td style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-ghost" href="edit_transport.php?id=<?= (int)$r['TransportID'] ?>">Edit</a>
                <?php if (!in_array($r['STATUS'], ['Delivered','Canceled'], true)): ?>
                  <a class="btn btn-ok" href="pending_deliveries.php?<?= htmlspecialchars(build_preserve_qs(['quick'=>'dispatch','id'=>(int)$r['TransportID'],'_csrf'=>$CSRF])) ?>">Dispatch</a>
                  <a class="btn btn-primary" href="pending_deliveries.php?<?= htmlspecialchars(build_preserve_qs(['quick'=>'deliver','id'=>(int)$r['TransportID'],'_csrf'=>$CSRF])) ?>">Deliver</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="sticky-footer" style="margin-top:14px;">
        <div class="note">Showing page <?= (int)$page ?> of <?= (int)$pages ?>.</div>
        <div style="display:flex;gap:8px;">
          <?php
            $mk = function(int $p){ return 'pending_deliveries.php?'.http_build_query([
              'status'=>$GLOBALS['q_status'],'from'=>$GLOBALS['q_from'],'to'=>$GLOBALS['q_to'],'q'=>$GLOBALS['q_search'],
              'sort'=>$GLOBALS['sort'],'page'=>$p,'pp'=>$GLOBALS['perPage']
            ]); };
            if ($page>1) echo '<a class="btn btn-ghost" href="'.$mk(1).'">« First</a><a class="btn btn-ghost" href="'.$mk($page-1).'">‹ Prev</a>';
            echo '<span class="btn btn-primary" style="pointer-events:none;">'.$page.' / '.$pages.'</span>';
            if ($page<$pages) echo '<a class="btn btn-ghost" href="'.$mk($page+1).'">Next ›</a><a class="btn btn-ghost" href="'.$mk($pages).'">Last »</a>';
          ?>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
document.getElementById('chk_all')?.addEventListener('change', function(){
  document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include 'footer.php'; ?>
