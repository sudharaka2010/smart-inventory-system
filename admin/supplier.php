<?php
// supplier.php (VIEW SUPPLIERS - PROFESSIONAL / BUSINESS STYLE)
// Secure headers, CSRF, PDO, PRG flash, search+filter, sorting, pagination, CSV export

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
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---- PRG Flash ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- CSV Export (must run before output) ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    [$where, $params] = (function() {
        $clauses = [];
        $params  = [];
        $q       = trim((string)($_GET['q'] ?? ''));
        $status  = trim((string)($_GET['status'] ?? ''));
        $cat     = trim((string)($_GET['cat'] ?? ''));

        if ($q !== '') {
            $clauses[] = "(s.NAME LIKE :q OR s.CompanyName LIKE :q OR s.Email LIKE :q OR s.Contact LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        if ($status !== '' && in_array($status, ['Active','Inactive'], true)) {
            $clauses[] = "s.Status = :status";
            $params[':status'] = $status;
        }
        if ($cat !== '') {
            $clauses[] = "s.Category LIKE :cat";
            $params[':cat'] = "%{$cat}%";
        }
        $where = $clauses ? ('WHERE '.implode(' AND ', $clauses)) : '';
        return [$where, $params];
    })();

    $sql = "SELECT s.SupplierID, s.NAME, s.CompanyName, s.Email, s.Contact, s.Category, s.Status, s.PaymentTerms, s.GSTNumber, s.CreatedAt
            FROM supplier s
            {$where}
            ORDER BY s.CreatedAt DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="suppliers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SupplierID','Name','Company','Email','Contact','Category','Status','PaymentTerms','GST','CreatedAt']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['SupplierID'], $r['NAME'], $r['CompanyName'], $r['Email'], $r['Contact'],
            $r['Category'], $r['Status'], $r['PaymentTerms'], $r['GSTNumber'], $r['CreatedAt']
        ]);
    }
    fclose($out);
    exit;
}

// ---- Filters / Sorting / Pagination ----
$q       = trim((string)($_GET['q'] ?? ''));
$status  = trim((string)($_GET['status'] ?? ''));
$cat     = trim((string)($_GET['cat'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(5, (int)($_GET['per'] ?? 10)));

$whitelistSort = [
    'SupplierID'  => 's.SupplierID',
    'NAME'        => 's.NAME',
    'CompanyName' => 's.CompanyName',
    'Email'       => 's.Email',
    'Contact'     => 's.Contact',
    'Category'    => 's.Category',
    'Status'      => 's.Status',
    'CreatedAt'   => 's.CreatedAt',
];
$sort  = $_GET['sort'] ?? 'CreatedAt';
$dir   = strtoupper($_GET['dir'] ?? 'DESC');
$dir   = in_array($dir, ['ASC','DESC'], true) ? $dir : 'DESC';
$orderBy = $whitelistSort[$sort] ?? 's.CreatedAt';

$clauses = [];
$params  = [];

if ($q !== '') {
    $clauses[] = "(s.NAME LIKE :q OR s.CompanyName LIKE :q OR s.Email LIKE :q OR s.Contact LIKE :q)";
    $params[':q'] = "%{$q}%";
}
if ($status !== '' && in_array($status, ['Active','Inactive'], true)) {
    $clauses[] = "s.Status = :status";
    $params[':status'] = $status;
}
if ($cat !== '') {
    $clauses[] = "s.Category LIKE :cat";
    $params[':cat'] = "%{$cat}%";
}
$where = $clauses ? ('WHERE '.implode(' AND ', $clauses)) : '';

// total counts
$countSql = "SELECT COUNT(*) AS c FROM supplier s {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// active/inactive counters for header
$activeCountSql = "SELECT SUM(CASE WHEN Status='Active' THEN 1 ELSE 0 END) AS a,
                          SUM(CASE WHEN Status='Inactive' THEN 1 ELSE 0 END) AS i
                   FROM supplier s {$where}";
$aiStmt = $pdo->prepare($activeCountSql);
$aiStmt->execute($params);
$ai = $aiStmt->fetch() ?: ['a'=>0,'i'=>0];

$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

// query list
$listSql = "SELECT s.SupplierID, s.NAME, s.CompanyName, s.Email, s.Contact, s.Category, s.Status, s.GSTNumber, s.PaymentTerms, s.CreatedAt
            FROM supplier s
            {$where}
            ORDER BY {$orderBy} {$dir}
            LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($listSql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>
<style>
:root{
  --brand:#3b5683;
  --brand-dark:#2f4767;
  --ring:rgba(59,86,131,.25);
  --border:#dfe6f2;
  --text:#3b5683;
  --muted:#6b7c97;

  /* unified buttons */
  --btn-h:38px;
  --btn-radius:10px;
  --btn-min:120px;     /* toolbar buttons same width */
  --btn-min-row:100px; /* row action buttons same width */
}

*{box-sizing:border-box}
body{background:#ffffff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;padding:16px;}}

/* Remove underlines globally for UI links */
a{ text-decoration:none; color:var(--text); }

/* Card */
.card{
  background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);
  margin-bottom:20px;border:1px solid var(--border);
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;margin:0;
  border-bottom:1px solid var(--border);color:var(--text);
}

/* Toolbar & KPIs */
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 20px 0 20px;flex-wrap:wrap}
.kpis{display:flex;gap:10px;flex-wrap:wrap}
.kpi{background:#fff;border:1px solid var(--border);border-radius:12px;padding:10px 12px;font-size:13px;color:var(--text)}
.kpi b{font-size:16px;color:#223652}

/* Alerts */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Filters */
.filters{padding:14px 20px 0 20px}
.filters form{display:grid;grid-template-columns:2fr 1fr 1fr auto auto;gap:10px}
@media(max-width:900px){.filters form{grid-template-columns:1fr 1fr;}}

.input,select{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:#fff;font-size:14px;
  color:var(--text);transition:border-color .2s, box-shadow .2s, background .2s, color .2s;
}
.input:focus,select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);}

/* ===== Buttons (unified) ===== */
.btn, .action{
  display:inline-flex;align-items:center;justify-content:center;
  height:var(--btn-h); padding:0 14px; border-radius:var(--btn-radius);
  font-weight:600; cursor:pointer; user-select:none; white-space:nowrap;
  border:1px solid var(--border); background:#fff; color:var(--text);
  transition:background .18s ease, border-color .18s ease, filter .18s ease, color .18s ease;
}

/* Only non-primary/non-danger buttons get the light hover */
.btn:not(.btn-primary):not(.btn-danger):hover,
.action:not(.primary):not(.danger):hover{
  background:#e9eff7;
}
.btn:focus-visible, .action:focus-visible{ outline:none; box-shadow:0 0 0 3px var(--ring); }

/* Primary (keeps dark background on hover so the white text never disappears) */
.btn-primary, .action.primary{
  background:var(--brand); color:#fff; border-color:var(--brand);
}
.btn-primary:hover, .action.primary:hover{
  background:var(--brand); /* keep same tone */
  filter:brightness(1.05); /* subtle lift */
}
.btn-primary:active, .action.primary:active{ background:var(--brand-dark); }

/* Danger */
.btn-danger, .action.danger{
  background:#ef4444; border-color:#ef4444; color:#fff;
}
.btn-danger:hover, .action.danger:hover{ background:#dc2626; }

/* Toolbar specific: same width for both buttons */
.toolbar .btn{ min-width:var(--btn-min); }

/* Table */
.table-wrap{padding:12px 20px 20px 20px}
.table{width:100%;border-collapse:separate;border-spacing:0}
.table th,.table td{
  padding:10px 12px;border-bottom:1px solid var(--border);text-align:left;font-size:14px;vertical-align:middle;color:var(--text);
}
.table th{font-weight:700;color:var(--text);white-space:nowrap;background:#eef2f8}
.table tr:hover td{background:#f6f9fc}

/* Make mailto/tel links look like normal text */
.table td a{ text-decoration:none; color:var(--text); }
.table td a:hover{ text-decoration:none; }

/* Badges */
.badge{padding:4px 8px;border-radius:999px;font-size:12px;border:1px solid var(--border);display:inline-block;color:var(--text);background:#fff}
.badge.active{background:#ecfdf5;color:#166534;border-color:#bbf7d0}
.badge.inactive{background:#fef2f2;color:#991b1b;border-color:#fecaca}

/* Row actions: same width & height for all three */
.actions{display:flex;gap:8px;flex-wrap:wrap}
.actions .action{ min-width:var(--btn-min-row); }

/* Pagination */
.pagination{display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap;margin-top:12px}
.page-btn{
  padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--text);text-decoration:none;
  transition:background .2s, color .2s, border-color .2s;
}
.page-btn:hover{background:#e9eff7}
.page-btn.active{background:var(--brand);border-color:var(--brand);color:#fff}
.page-info{margin-right:auto;color:var(--muted);padding:8px 0 0 2px;font-size:13px}

/* Sorting links (no underline) */
.sort-link{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.sort-caret{font-size:12px;color:#8ea1bf}
</style>


<div class="container">
  <div class="card">
    <h1>Suppliers</h1>

    <div class="toolbar">
      <div class="kpis">
        <div class="kpi">Total: <b><?= (int)$total ?></b></div>
        <div class="kpi">Active: <b><?= (int)($ai['a'] ?? 0) ?></b></div>
        <div class="kpi">Inactive: <b><?= (int)($ai['i'] ?? 0) ?></b></div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-primary" href="add_supplier.php">+ Add Supplier</a>
        <a class="btn" href="?export=csv<?= $q!==''?'&q='.urlencode($q):'' ?><?= $status!==''?'&status='.urlencode($status):'' ?><?= $cat!==''?'&cat='.urlencode($cat):'' ?>">Export CSV</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <div class="filters">
      <form method="get" autocomplete="off">
        <input class="input" type="text" name="q" placeholder="Search name, company, email, contact…" value="<?= e($q) ?>">
        <select name="status">
          <option value="">All Status</option>
          <option value="Active"   <?= $status==='Active'?'selected':''; ?>>Active</option>
          <option value="Inactive" <?= $status==='Inactive'?'selected':''; ?>>Inactive</option>
        </select>
        <input class="input" type="text" name="cat" placeholder="Category…" value="<?= e($cat) ?>">
        <select name="per" title="Per Page">
          <?php foreach ([10,15,20,30,50] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':''; ?>><?= $pp ?>/page</option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Apply</button>
      </form>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <?php
            function sortL($label,$col,$curSort,$curDir){
              $nextDir = ($curSort===$col && $curDir==='ASC') ? 'DESC' : 'ASC';
              $qs = $_GET;
              $qs['sort'] = $col;
              $qs['dir']  = $nextDir;
              $href = '?'.http_build_query($qs);
              $caret = '';
              if ($curSort === $col) {
                $caret = $curDir==='ASC' ? '▲' : '▼';
              }
              return '<a class="sort-link" href="'.e($href).'">'.e($label).' <span class="sort-caret">'.$caret.'</span></a>';
            }
          ?>
          <tr>
            <th><?= sortL('ID','SupplierID',$sort,$dir) ?></th>
            <th><?= sortL('Name','NAME',$sort,$dir) ?></th>
            <th><?= sortL('Company','CompanyName',$sort,$dir) ?></th>
            <th><?= sortL('Email','Email',$sort,$dir) ?></th>
            <th><?= sortL('Contact','Contact',$sort,$dir) ?></th>
            <th><?= sortL('Category','Category',$sort,$dir) ?></th>
            <th><?= sortL('Status','Status',$sort,$dir) ?></th>
            <th>GST</th>
            <th><?= sortL('Created','CreatedAt',$sort,$dir) ?></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="10" style="text-align:center;color:#64748b">No suppliers found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <?php
              $qsAdd = [
                'supplier_id'   => (int)$r['SupplierID'],
                'SupplierID'    => (int)$r['SupplierID'],
                'sid'           => (int)$r['SupplierID'],
                'id'            => (int)$r['SupplierID'],
                'supplier_name' => (string)$r['NAME'],
                'SupplierName'  => (string)$r['NAME'],
                'name'          => (string)$r['NAME'],
                'company'       => (string)$r['CompanyName'],
                'email'         => (string)$r['Email'],
                'contact'       => (string)$r['Contact'],
                'category'      => (string)$r['Category'],
              ];
              $addUrl = 'add_inventory.php?'.http_build_query($qsAdd);
            ?>
            <tr>
              <td>#<?= (int)$r['SupplierID'] ?></td>
              <td><?= e($r['NAME']) ?></td>
              <td><?= e($r['CompanyName']) ?></td>
              <td><a href="mailto:<?= e($r['Email']) ?>"><?= e($r['Email']) ?></a></td>
              <td><a href="tel:<?= e($r['Contact']) ?>"><?= e($r['Contact']) ?></a></td>
              <td><?= e($r['Category']) ?></td>
              <td>
                <?php if ($r['Status']==='Active'): ?>
                  <span class="badge active">Active</span>
                <?php else: ?>
                  <span class="badge inactive">Inactive</span>
                <?php endif; ?>
              </td>
              <td><?= e($r['GSTNumber']) ?></td>
              <td><?= e(date('Y-m-d H:i', strtotime($r['CreatedAt']))) ?></td>
              <td class="actions">
                <a class="action" href="edit_supplier.php?id=<?= (int)$r['SupplierID'] ?>">Edit</a>
                <a class="action primary" href="<?= e($addUrl) ?>">Add Items</a>
                <a class="action danger" href="?delete=<?= (int)$r['SupplierID'] ?>&_csrf=<?= e($CSRF) ?>" onclick="return confirmDelete(<?= (int)$r['SupplierID'] ?>)">Delete</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div class="pagination">
        <div class="page-info">
          <?php
            $start = $total ? ($offset+1) : 0;
            $end   = min($offset + $perPage, $total);
            echo "Showing {$start}–{$end} of {$total}";
          ?>
        </div>
        <?php
          if ($pages > 1) {
            $qs = $_GET;
            for ($p=1; $p<=$pages; $p++) {
              $qs['page'] = $p;
              $href = '?'.http_build_query($qs);
              $cls  = 'page-btn'.($p===$page?' active':'');
              echo '<a class="'.$cls.'" href="'.e($href).'">'.$p.'</a>';
            }
          }
        ?>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDelete(id){
  return confirm('Delete supplier #'+id+'? This cannot be undone.');
}
</script>

<?php include 'footer.php'; ?>
