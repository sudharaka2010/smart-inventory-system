<?php
// customer_return.php (UPDATED to match your rb_stores_db schema)
// - Only allows returns for invoices with Status IN ('Paid','Paid-Changed')
// - Refund = (orderdetails.Subtotal / Quantity) * ReturnQty (rounded 2dp)
// - Atomic stock increase on return (transaction + row lock)
// - PRG with flash, CSRF, PDO (utf8mb4)

declare(strict_types=1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
date_default_timezone_set('Asia/Colombo');

// ---------- CSRF ----------
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

// ---------- DB ----------
$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, 'root', '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  die("DB failed: " . htmlspecialchars($e->getMessage()));
}

/* ---------- Helper: recent invoices (Paid/Paid-Changed) ---------- */
function load_recent_paid(PDO $pdo): array {
  $st = $pdo->prepare("
    SELECT o.InvoiceID, o.OrderID, o.OrderDate, o.Status,
           c.NAME AS CustomerName
    FROM `order` o
    LEFT JOIN customer c ON c.CustomerID = o.CustomerID
    WHERE o.Status IN ('Paid','Paid-Changed')
    ORDER BY o.OrderDate DESC, o.OrderID DESC
    LIMIT 25
  ");
  $st->execute();
  return $st->fetchAll() ?: [];
}

/* ---------- Helper: full invoice bundle ---------- */
function load_invoice_bundle(PDO $pdo, string $invoice): ?array {
  // Header
  $s1 = $pdo->prepare("
    SELECT o.*, c.NAME AS CustomerName, c.Email, c.Phone, c.Address
    FROM `order` o
    LEFT JOIN customer c ON c.CustomerID = o.CustomerID
    WHERE o.InvoiceID = ?
    LIMIT 1
  ");
  $s1->execute([$invoice]);
  $order = $s1->fetch();
  if (!$order) return null;

  // Lines
  $s2 = $pdo->prepare("
    SELECT od.OrderDetailID,
           od.ItemID,
           od.Quantity      AS PurchasedQty,
           od.Subtotal      AS LineSubtotal,
           ii.NAME          AS ItemName,
           ii.Description
    FROM orderdetails od
    LEFT JOIN inventoryitem ii ON ii.ItemID = od.ItemID
    WHERE od.OrderID = ?
    ORDER BY od.OrderDetailID ASC
  ");
  $s2->execute([ (int)$order['OrderID'] ]);
  $lines = $s2->fetchAll() ?: [];

  // Already returned per OD line
  $returnedMap = [];
  if ($lines) {
    $ids = array_column($lines, 'OrderDetailID');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $s3 = $pdo->prepare("
      SELECT OrderDetailID, COALESCE(SUM(ReturnQty),0) AS ReturnedQty
      FROM customer_return
      WHERE OrderDetailID IN ($in)
      GROUP BY OrderDetailID
    ");
    $s3->execute($ids);
    foreach ($s3->fetchAll() as $r) {
      $returnedMap[(int)$r['OrderDetailID']] = (int)$r['ReturnedQty'];
    }
  }

  return ['order'=>$order, 'lines'=>$lines, 'returned'=>$returnedMap];
}

/* ---------- FLASH (PRG) ---------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$currentInvoice = trim((string)($_GET['invoice'] ?? $_POST['invoice'] ?? ''));

/* ---------- POST: Save return ---------- */
if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  isset($_POST['_csrf']) &&
  hash_equals($CSRF, (string)$_POST['_csrf'])
) {
  $invoice = trim((string)($_POST['invoice'] ?? ''));
  $ret     = $_POST['ret'] ?? [];
  $errors  = [];

  if ($invoice === '') $errors[] = "Invoice is required.";
  if (!is_array($ret) || empty($ret)) $errors[] = "Please enter at least one return line.";

  $bundle = $invoice ? load_invoice_bundle($pdo, $invoice) : null;
  if (!$bundle) $errors[] = "Invoice not found.";

  // Only Paid / Paid-Changed allowed
  if ($bundle && !in_array((string)$bundle['order']['Status'], ['Paid','Paid-Changed'], true)) {
    $errors[] = "Returns are allowed only for invoices with Status 'Paid' or 'Paid-Changed'.";
  }

  // Client-side guard is there, but validate server-side too
  $hasPositive = false;
  if (!$errors && is_array($ret)) {
    foreach ($ret as $pl) {
      if ((int)($pl['qty'] ?? 0) > 0) { $hasPositive = true; break; }
    }
    if (!$hasPositive) $errors[] = "All return quantities are zero. Nothing to save.";
  }

  if (!$errors) {
    try {
      $order    = $bundle['order'];
      $lines    = $bundle['lines'];
      $returned = $bundle['returned'];

      $lineById = [];
      foreach ($lines as $ln) $lineById[(int)$ln['OrderDetailID']] = $ln;

      $pdo->beginTransaction();

      $ins = $pdo->prepare("
        INSERT INTO customer_return
          (OrderID, OrderDetailID, ItemID, CustomerID, InvoiceID, ReturnQty, RefundAmount, Reason)
        VALUES (?,?,?,?,?,?,?,?)
      ");

      $lockItm = $pdo->prepare("SELECT ItemID FROM inventoryitem WHERE ItemID = ? FOR UPDATE");
      $upStock = $pdo->prepare("UPDATE inventoryitem SET Quantity = Quantity + ? WHERE ItemID = ?");

      $totalRefund = 0.00;

      foreach ($ret as $odidStr => $payload) {
        $odid   = (int)$odidStr;
        $qty    = (int)($payload['qty'] ?? 0);
        $reason = trim((string)($payload['reason'] ?? ''));

        if ($qty <= 0) continue; // skip zero/negative

        if (!isset($lineById[$odid])) {
          throw new InvalidArgumentException("Invalid line reference (OrderDetailID $odid).");
        }

        $ln        = $lineById[$odid];
        $purchased = (int)$ln['PurchasedQty'];
        $priorRet  = (int)($returned[$odid] ?? 0);
        $available = max(0, $purchased - $priorRet);

        if ($qty > $available) {
          throw new InvalidArgumentException("Return qty exceeds available for OD#$odid. Purchased: $purchased, Returned: $priorRet, Trying: $qty");
        }

        // Lock item row to avoid concurrent stock updates
        $lockItm->execute([ (int)$ln['ItemID'] ]);

        // Unit price derived from original line
        $unit   = ($purchased > 0) ? ((float)$ln['LineSubtotal'] / (float)$purchased) : 0.0;
        $refund = round($unit * $qty, 2);

        // Insert return record
        $ins->execute([
          (int)$order['OrderID'],
          $odid,
          (int)$ln['ItemID'],
          (int)$order['CustomerID'],
          $invoice,
          $qty,
          $refund,
          ($reason === '' ? null : $reason),
        ]);

        // Restock inventory
        $upStock->execute([ $qty, (int)$ln['ItemID'] ]);

        $totalRefund += $refund;
      }

      $pdo->commit();
      $_SESSION['flash'] = ['ok'=>true, 'msg'=>"Customer return saved. Estimated refund total: Rs. " . number_format($totalRefund, 2)];
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash'] = ['ok'=>false, 'msg'=>"Error: " . htmlspecialchars($e->getMessage())];
    }
  } else {
    $_SESSION['flash'] = ['ok'=>false, 'msg'=>implode('<br>', array_map('htmlspecialchars', $errors))];
  }

  // PRG
  $redir = 'customer_return.php' . ($invoice ? ('?invoice=' . urlencode($invoice)) : '');
  header("Location: $redir");
  exit();
}

/* ---------- GET data ---------- */
$recentPaid = load_recent_paid($pdo);
$bundle     = $currentInvoice !== '' ? load_invoice_bundle($pdo, $currentInvoice) : null;

/* ---------- View ---------- */
include 'header.php';
include 'sidebar.php';
?>
<style>
:root{
  --brand:#3b5683; --brand-dark:#324a70; --ring:rgba(59,86,131,.25);
  --text:#3b5683; --muted:#6b7c97; --border:#dfe6f2; --tint:#e9eff7; --panel:#fff;
}
body{background:#ffffff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:1100px;}
@media(max-width:992px){.container{margin-left:0;}}

.card{background:var(--panel);border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;border:1px solid var(--border)}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);margin:0}

.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px}
.headerbar a{text-decoration:none;color:var(--brand);}
.headerbar a:hover{text-decoration:underline}

.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

.pills{display:flex;gap:8px;flex-wrap:wrap;margin:0 20px 8px 20px}
.pill{font-size:12px;border:1px solid var(--border);border-radius:999px;padding:4px 10px;background:#fff;color:var(--text)}

.form-wrap{padding:16px 20px 22px 20px;}
.grid{display:grid;grid-template-columns:1fr 260px;gap:18px;align-items:end}
@media(max-width:820px){.grid{grid-template-columns:1fr}}

.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted)}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;color:var(--text);
  transition:border .2s, box-shadow .2s;
}
.input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring)}

.btn{
  padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid var(--border);background:#fff;color:var(--text);
}
.btn:hover{background:var(--tint)}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring)}
.btn-primary{background:var(--brand);color:#fff;border-color:var(--brand)}
.btn-primary:hover{filter:brightness(1.05)}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}

.table-wrap{overflow:auto;margin:0 20px 20px 20px;border:1px solid var(--border);border-radius:12px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid var(--border);vertical-align:top;font-size:14px}
th{background:#f8fafc;color:#334155;font-weight:600;position:sticky;top:0}
.muted{color:#6b7280}
.qty{width:100px}
.ta{min-width:240px}
.no{width:52px}
.badge{
  display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid var(--border);
  background:#fff;color:#334155
}
.badge.ok{color:#166534;background:#ecfdf5;border-color:#bbf7d0}
.badge.warn{color:#92400e;background:#fffbeb;border-color:#fde68a}
</style>

<div class="container">
  <div class="card">
    <h1>Customer Return</h1>

    <div class="headerbar">
      <div class="hint">Record returns against a paid invoice. Returned quantities are added back to stock.</div>
      <a class="btn" href="view_return.php">View Returns</a>
    </div>

    <div class="pills">
      <span class="pill">Today: <?= htmlspecialchars(date('Y-m-d')) ?></span>
      <span class="pill">Time: <?= htmlspecialchars(date('H:i')) ?></span>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <!-- Invoice selector -->
    <form class="form-wrap" method="get" autocomplete="off">
      <div class="grid">
        <div>
          <div class="label">Search by Invoice ID</div>
          <input class="input" type="text" name="invoice" placeholder="e.g., INV-00019"
                 value="<?= htmlspecialchars($currentInvoice) ?>">
        </div>
        <div class="actions" style="justify-content:flex-end">
          <button class="btn btn-primary" type="submit">Load Invoice</button>
          <a class="btn" href="customer_return.php">Clear</a>
        </div>
      </div>

      <div style="margin-top:14px;display:grid;grid-template-columns:1fr auto;gap:12px">
        <div>
          <div class="label">Or pick a recent Paid invoice</div>
          <select class="input" name="invoice" onchange="this.form.submit()">
            <option value="">— Select —</option>
            <?php foreach ($recentPaid as $r): ?>
              <option value="<?= htmlspecialchars($r['InvoiceID']) ?>" <?= ($currentInvoice===$r['InvoiceID']?'selected':'') ?>>
                <?= htmlspecialchars($r['InvoiceID']) ?>
                — <?= htmlspecialchars($r['CustomerName'] ?? 'Unknown') ?>
                (<?= htmlspecialchars($r['OrderDate']) ?>)
                [<?= htmlspecialchars($r['Status']) ?>]
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div></div>
      </div>
    </form>
  </div>

  <?php if ($bundle): ?>
    <?php
      $o = $bundle['order']; $lines = $bundle['lines']; $returned = $bundle['returned'];
      $kpis = [
        'Invoice'  => $o['InvoiceID'],
        'Date'     => $o['OrderDate'],
        'Customer' => $o['CustomerName'] ?: '—',
        'Status'   => $o['Status'] ?: '—',
        'Total'    => 'Rs. ' . number_format((float)$o['TotalAmount'],2),
        'Paid'     => 'Rs. ' . number_format((float)$o['AmountPaid'],2),
      ];
      $statusOk = in_array((string)$o['Status'], ['Paid','Paid-Changed'], true);
    ?>
    <div class="card">
      <h1>Invoice Summary</h1>
      <div class="pills">
        <?php foreach ($kpis as $k=>$v): ?>
          <span class="pill"><strong><?= htmlspecialchars($k) ?>:</strong> <?= htmlspecialchars($v) ?></span>
        <?php endforeach; ?>
        <span class="pill">
          <span class="badge <?= $statusOk ? 'ok' : 'warn' ?>">
            <?= $statusOk ? 'Return Allowed' : 'Return Not Allowed' ?>
          </span>
        </span>
      </div>

      <?php if (!$statusOk): ?>
        <div class="alert alert-error" style="margin-top:10px">
          This invoice has status <strong><?= htmlspecialchars((string)$o['Status']) ?></strong>.
          Returns are allowed only for <strong>Paid</strong> or <strong>Paid-Changed</strong>.
        </div>
      <?php endif; ?>

      <form method="post" class="form-wrap" autocomplete="off" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">
        <input type="hidden" name="invoice" value="<?= htmlspecialchars($o['InvoiceID']) ?>">

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th class="no">No</th>
                <th>Item</th>
                <th>Purchased</th>
                <th>Already Returned</th>
                <th>Available</th>
                <th class="qty">Return Qty</th>
                <th class="ta">Reason (optional)</th>
              </tr>
            </thead>
            <tbody>
              <?php $i=1; foreach ($lines as $ln):
                $odid = (int)$ln['OrderDetailID'];
                $purchased = (int)$ln['PurchasedQty'];
                $prior = (int)($returned[$odid] ?? 0);
                $avail = max(0, $purchased - $prior);
              ?>
              <tr>
                <td><?= $i++ ?></td>
                <td>
                  <div><strong><?= htmlspecialchars($ln['ItemName'] ?? ('Item #'.$ln['ItemID'])) ?></strong></div>
                  <div class="muted">OD#<?= $odid ?> • ItemID: <?= (int)$ln['ItemID'] ?></div>
                  <?php if (!empty($ln['Description'])): ?>
                    <div class="muted"><?= htmlspecialchars($ln['Description']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= $purchased ?></td>
                <td><?= $prior ?></td>
                <td><strong><?= $avail ?></strong></td>
                <td>
                  <?php if ($statusOk && $avail > 0): ?>
                    <input class="input qty" type="number" min="0" max="<?= $avail ?>" step="1"
                           name="ret[<?= $odid ?>][qty]" value="0">
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($statusOk && $avail > 0): ?>
                    <textarea class="input" name="ret[<?= $odid ?>][reason]" rows="2" placeholder="Damaged / Wrong size ..."></textarea>
                  <?php else: ?>
                    <span class="muted"><?= $avail === 0 ? 'Fully returned' : 'Locked by status' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="actions" style="justify-content:flex-end">
          <button class="btn btn-primary" type="submit" id="saveBtn" <?= $statusOk ? '' : 'disabled' ?>>Save Return</button>
          <a class="btn" href="customer_return.php?invoice=<?= urlencode($o['InvoiceID']) ?>">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>

<script>
// Tiny UX niceties + client guard for "all zero" qty
const saveBtn = document.getElementById('saveBtn');
saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');

document.querySelectorAll('form').forEach(f=>{
  if (f.method?.toLowerCase() !== 'post') return;
  f.addEventListener('submit', (e)=>{
    const qtyInputs = f.querySelectorAll('input[type="number"][name^="ret["]');
    let hasPos = false;
    qtyInputs.forEach(inp => { if (parseInt(inp.value||'0',10) > 0) hasPos = true; });
    if (!hasPos) {
      e.preventDefault();
      alert('Please enter at least one positive return quantity.');
    }
  });
});
</script>

<?php include 'footer.php'; ?>
