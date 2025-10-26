<?php
// feedback.php (VIEW FEEDBACK - FULL UPDATED)
// White card business UI, matches your add_* pages
// Filters: search/customer/rating/date | Pagination | CSV export | Delete (CSRF, PRG)

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
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $a): string { return http_build_query($a, '', '&', PHP_QUERY_RFC3986); }

// ---- Flash (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Filters ----
$search     = trim((string)($_GET['q'] ?? ''));
$customerId = (int)($_GET['customer_id'] ?? 0);
$rating     = (int)($_GET['rating'] ?? 0); // 0 = all
$from       = trim((string)($_GET['from'] ?? ''));
$to         = trim((string)($_GET['to'] ?? ''));

$where = [];
$args  = [];

if ($search !== '') {
    $where[] = "(COALESCE(c.NAME,'') LIKE :q OR COALESCE(c.Email,'') LIKE :q OR COALESCE(f.Comments,'') LIKE :q)";
    $args[':q'] = "%{$search}%";
}
if ($customerId > 0) {
    $where[] = "f.CustomerID = :cid";
    $args[':cid'] = $customerId;
}
if ($rating >= 1 && $rating <= 5) {
    $where[] = "f.Rating = :rating";
    $args[':rating'] = $rating;
}
if ($from !== '') {
    $where[] = "f.DateSubmitted >= :from";
    $args[':from'] = date('Y-m-d', strtotime($from));
}
if ($to !== '') {
    $where[] = "f.DateSubmitted <= :to";
    $args[':to'] = date('Y-m-d', strtotime($to));
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Actions: CSV export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $sql = "
          SELECT f.FeedbackID, f.DateSubmitted, f.Rating, f.Comments,
                 c.CustomerID, c.NAME AS CustomerName, c.Email, c.Phone
          FROM feedback f
          LEFT JOIN customer c ON c.CustomerID = f.CustomerID
          $whereSql
          ORDER BY f.DateSubmitted DESC, f.FeedbackID DESC
        ";
        $st = $pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="feedback_'.date('Ymd_His').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['FeedbackID','Date','Rating','Comments','CustomerID','CustomerName','Email','Phone']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['FeedbackID'],
                $r['DateSubmitted'],
                $r['Rating'],
                $r['Comments'],
                $r['CustomerID'],
                $r['CustomerName'],
                $r['Email'],
                $r['Phone'],
            ]);
        }
        fclose($out);
        exit;
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>"CSV export failed."];
        header('Location: feedback.php?'.qs($_GET));
        exit;
    }
}

// ---- Actions: Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        if (!isset($_POST['_csrf']) || !hash_equals($CSRF, (string)$_POST['_csrf'])) {
            throw new RuntimeException("Security check failed.");
        }
        $fid = (int)($_POST['FeedbackID'] ?? 0);
        if ($fid <= 0) throw new InvalidArgumentException("Invalid FeedbackID.");

        $del = $pdo->prepare("DELETE FROM feedback WHERE FeedbackID = ?");
        $del->execute([$fid]);

        $_SESSION['flash'] = ['ok'=>true, 'msg'=>"Feedback #$fid deleted."];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>h($e->getMessage())];
    }
    header('Location: feedback.php?'.qs($_GET));
    exit;
}

// ---- Pagination ----
$perPage = max(5, (int)($_GET['pp'] ?? 10));
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// ---- Stats (count, avg rating) ----
$metaSql = "
  SELECT COUNT(*) AS cnt, ROUND(AVG(f.Rating),2) AS avg_rating
  FROM feedback f
  LEFT JOIN customer c ON c.CustomerID = f.CustomerID
  $whereSql
";
$meta = $pdo->prepare($metaSql);
$meta->execute($args);
$tot = $meta->fetch() ?: ['cnt'=>0,'avg_rating'=>null];

// ---- Rows ----
$listSql = "
  SELECT f.FeedbackID, f.DateSubmitted, f.Rating, f.Comments,
         c.CustomerID, c.NAME AS CustomerName, c.Email, c.Phone
  FROM feedback f
  LEFT JOIN customer c ON c.CustomerID = f.CustomerID
  $whereSql
  ORDER BY f.DateSubmitted DESC, f.FeedbackID DESC
  LIMIT :limit OFFSET :offset
";
$list = $pdo->prepare($listSql);
foreach ($args as $k=>$v) { $list->bindValue($k, $v); }
$list->bindValue(':limit', $perPage, PDO::PARAM_INT);
$list->bindValue(':offset', $offset, PDO::PARAM_INT);
$list->execute();
$rows = $list->fetchAll();

// ---- Customer options ----
$customers = $pdo->query("SELECT CustomerID, COALESCE(NULLIF(TRIM(NAME),''),'(Unnamed)') AS NAME FROM customer ORDER BY NAME")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White Business Theme — brand blue (#3b5683) ---- */
body{background:#ffffff;color:#3b5683;margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{
  background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;
  border:1px solid #dfe6f2;
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid #dfe6f2;margin:0;
  color:#3b5683;
}

/* Headerbar */
.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px;gap:12px;flex-wrap:wrap}

/* Alerts (semantic colors preserved) */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Filters */
.filters{padding:16px 20px;display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
.filters .input, .filters select{
  width:100%;padding:10px 12px;border:1px solid #dfe6f2;border-radius:8px;background:#fff;color:#3b5683;
  transition:border-color .2s, box-shadow .2s, background .2s, color .2s;
}
.filters .input:focus, .filters select:focus{
  outline:none;border-color:#3b5683;box-shadow:0 0 0 3px rgba(59,86,131,.25);
}
.filters .col-4{grid-column:span 4}
.filters .col-3{grid-column:span 3}
.filters .col-2{grid-column:span 2}
.filters .col-1{grid-column:span 1}
.filters .col-12{grid-column:span 12}
@media(max-width:992px){.filters .col-4,.filters .col-3,.filters .col-2,.filters .col-1{grid-column:span 12}}

/* Table */
.table-wrap{padding:0 20px 12px 20px;overflow:auto}
table{
  width:100%;border-collapse:separate;border-spacing:0;border:1px solid #dfe6f2;border-radius:12px;
  overflow:hidden;background:#fff
}
thead th{
  background:#eef2f8;color:#3b5683;font-weight:700;font-size:13px;padding:12px;
  border-bottom:1px solid #dfe6f2;text-align:left;white-space:nowrap
}
tbody td{
  padding:12px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#3b5683;vertical-align:top
}
tbody tr:hover{background:#f6f9fc}
.badge{
  display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #dfe6f2;font-size:12px;background:#fff;color:#3b5683
}
.muted{color:#6b7c97}
.reason{max-width:520px;color:#5b6b86}

/* Toolbar & Actions */
.toolbar{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;gap:10px;flex-wrap:wrap}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{
  padding:8px 12px;border-radius:8px;font-weight:600;font-size:14px;border:1px solid #dfe6f2;background:#fff;color:#3b5683;cursor:pointer;
  transition:background .2s, color .2s, box-shadow .2s, filter .2s, border-color .2s;
}
.btn:hover{background:#e9eff7}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(59,86,131,.25)}
.btn-primary{
  background:#3b5683;color:#fff;border:1px solid #3b5683;
}
.btn-primary:hover{background:#3b5683;filter:brightness(1.05)}
.btn-primary:active{background:#324a70}
.btn-danger{background:#ef4444;color:#fff;border:none}
.btn-danger:hover{background:#dc2626}
.btn-ghost{background:#fff;border:1px solid #dfe6f2;color:#3b5683}

/* Pager */
.pager{display:flex;gap:6px;align-items:center}
.pager a,.pager span{
  padding:6px 10px;border-radius:8px;border:1px solid #dfe6f2;background:#fff;text-decoration:none;color:#3b5683
}
.pager .active{background:#3b5683;border-color:#3b5683;color:#fff}
</style>


<div class="container">
  <div class="card">
    <h1>Feedback</h1>

    <div class="headerbar">
      <div class="muted">Browse, filter, export and manage customer feedback.</div>
      <div class="actions">
        <a class="btn" href="add_feedback.php">Add Feedback</a>
        <a class="btn btn-ghost" href="feedback.php">Reset Filters</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form class="filters" method="get">
      <div class="col-4">
        <input class="input" type="text" name="q" value="<?= h($search) ?>" placeholder="Search name / email / comments">
      </div>
      <div class="col-3">
        <select class="input" name="customer_id">
          <option value="0">All Customers</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['CustomerID'] ?>" <?= $customerId===(int)$c['CustomerID']?'selected':''; ?>>
              #<?= (int)$c['CustomerID'] ?> — <?= h($c['NAME']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-2">
        <select class="input" name="rating">
          <option value="0" <?= $rating===0?'selected':''; ?>>All Ratings</option>
          <?php for ($i=5; $i>=1; $i--): ?>
            <option value="<?= $i ?>" <?= $rating===$i?'selected':''; ?>><?= $i ?> star<?= $i>1?'s':'' ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-2">
        <input class="input" type="date" name="from" value="<?= h($from) ?>" max="<?= h(date('Y-m-d')) ?>">
      </div>
      <div class="col-2">
        <input class="input" type="date" name="to" value="<?= h($to) ?>" max="<?= h(date('Y-m-d')) ?>">
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
        <a class="btn" href="feedback.php?<?= h(qs(array_merge($_GET,['export'=>'csv']))) ?>">Export CSV</a>
      </div>
    </form>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="muted">
        <?= (int)$tot['cnt'] ?> feedback entr<?= ((int)$tot['cnt']===1)?'y':'ies' ?> ·
        Avg rating: <strong><?= $tot['avg_rating'] !== null ? h((string)$tot['avg_rating']) : '—' ?></strong>
      </div>
      <div class="pager">
        <?php
          $pages = max(1, (int)ceil(($tot['cnt'] ?: 0) / $perPage));
          $base = $_GET; unset($base['page']);
          if ($page > 1) echo '<a href="feedback.php?'.h(qs($base+['page'=>$page-1])).'">Prev</a>'; else echo '<span>Prev</span>';
          echo '<span class="active">'.(int)$page.' / '.$pages.'</span>';
          if ($page < $pages) echo '<a href="feedback.php?'.h(qs($base+['page'=>$page+1])).'">Next</a>'; else echo '<span>Next</span>';
        ?>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Rating</th>
            <th>Comments</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">No feedback found for the selected filters.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>#<?= (int)$r['FeedbackID'] ?></td>
                <td><span class="badge"><?= h($r['DateSubmitted']) ?></span></td>
                <td><?= h($r['CustomerName'] ?? '(Unknown)') ?> <span class="muted">#<?= (int)($r['CustomerID'] ?? 0) ?></span></td>
                <td>
                  <div><?= h($r['Email'] ?? '—') ?></div>
                  <div class="muted"><?= h($r['Phone'] ?? '—') ?></div>
                </td>
                <td><strong><?= (int)$r['Rating'] ?></strong></td>
                <td class="reason"><?= nl2br(h($r['Comments'] ?? '')) ?></td>
                <td style="text-align:right">
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this feedback entry?');">
                    <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="FeedbackID" value="<?= (int)$r['FeedbackID'] ?>">
                    <?php
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

    <!-- Bottom pager -->
    <div class="toolbar" style="border-top:1px solid #e5e7eb">
      <div class="muted">Showing page <?= (int)$page ?> of <?= (int)max(1, (int)ceil(($tot['cnt'] ?: 0) / $perPage)) ?></div>
      <div class="pager">
        <?php
          $pages = max(1, (int)ceil(($tot['cnt'] ?: 0) / $perPage));
          $base = $_GET; unset($base['page']);
          if ($page > 1) echo '<a href="feedback.php?'.h(qs($base+['page'=>$page-1])).'">Prev</a>'; else echo '<span>Prev</span>';
          echo '<span class="active">'.(int)$page.' / '.$pages.'</span>';
          if ($page < $pages) echo '<a href="feedback.php?'.h(qs($base+['page'=>$page+1])).'">Next</a>'; else echo '<span>Next</span>';
        ?>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
