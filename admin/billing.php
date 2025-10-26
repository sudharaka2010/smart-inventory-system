<?php
// billing.php — Live-calculating invoice (Qty/Price editable; items from inventory)
// - Instant UI math: per-row line totals, Subtotal, Discount Amt, VAT Amt, Grand Total, Balance
// - Live Status badge (Paid/Pending) and Save blocked until at least one valid row
// - Server-side calculations/CSRF remain authoritative
declare(strict_types=1);
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

// DB connection
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

/* ---------- Data loads ---------- */
$customers = $pdo->query("SELECT CustomerID, NAME, Email, Phone, Address FROM customer ORDER BY NAME ASC")->fetchAll();
$inventoryItems = $pdo->query("SELECT ItemID, NAME, Price FROM inventoryitem ORDER BY NAME ASC")->fetchAll();

// Next invoice id
function nextInvoiceId(PDO $pdo): string {
    $row = $pdo->query("SELECT MAX(OrderID) AS maxid FROM `order`")->fetch();
    $next = (int)($row['maxid'] ?? 0) + 1;
    return sprintf("INV-%05d", $next);
}
$nextInvoice = nextInvoiceId($pdo);

$messages = [];
$errors   = [];
$invoiceOpenUrl = null;

/* ---------- Handle Save (server-side source of truth) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf'], $_POST['_csrf'])) {
        $errors[] = "Invalid request. Please refresh.";
    } else {
        try {
            // Customer
            $custType = $_POST['CustomerType'] ?? 'existing';
            $customerId = 0;

            $postedEmail   = trim($_POST['Email']   ?? '');
            $postedPhone   = trim($_POST['Phone']   ?? '');
            $postedAddress = trim($_POST['Address'] ?? '');

            if ($custType === 'new') {
                $name = trim($_POST['NewName'] ?? '');
                if ($name === '') $errors[] = "Customer name required.";
                if (!$errors) {
                    $stmt = $pdo->prepare("INSERT INTO customer (NAME, Email, Phone, Address) VALUES (?,?,?,?)");
                    $stmt->execute([$name, $postedEmail, $postedPhone, $postedAddress]);
                    $customerId = (int)$pdo->lastInsertId();
                }
            } else {
                $customerId = (int)($_POST['CustomerID'] ?? 0);
                if ($customerId <= 0) $errors[] = "Select a valid customer.";
            }

            // Invoice / totals
            $invoiceId = $nextInvoice;
            $discPct   = (float)($_POST['Discount'] ?? 0);
            $vatPct    = (float)($_POST['VAT'] ?? 0);
            $paid      = (float)($_POST['AmountPaid'] ?? 0);
            $method    = trim($_POST['PaymentMethod'] ?? 'Cash');
            $notes     = trim($_POST['Notes'] ?? '');

            // Items
            $ids  = $_POST['ItemID'] ?? [];
            $qtys = $_POST['Qty'] ?? [];
            $prs  = $_POST['Price'] ?? [];

            $subtotal = 0; $lines = [];
            $rowCount = max(count($qtys), count($prs), count($ids));
            for ($i = 0; $i < $rowCount; $i++) {
                $iid = (int)($ids[$i] ?? 0);
                $qty = (int)($qtys[$i] ?? 0);
                $pr  = (float)($prs[$i] ?? 0);

                if ($iid <= 0 || $qty <= 0 || $pr < 0) continue;

                $line = $qty * $pr;
                $subtotal += $line;
                $lines[] = ['ItemID' => $iid, 'Qty' => $qty, 'Subtotal' => $line];
            }
            if ($subtotal <= 0) $errors[] = "Please add at least one valid item.";

            $discAmt   = $subtotal * ($discPct / 100);
            $afterDisc = $subtotal - $discAmt;
            $vatAmt    = $afterDisc * ($vatPct / 100);
            $total     = $afterDisc + $vatAmt;
            $balance   = $total - $paid;
            $status    = ($balance <= 0 ? 'Paid' : 'Pending');

            if (!$errors) {
                $pdo->beginTransaction();
                $ins = $pdo->prepare("INSERT INTO `order`
                  (InvoiceID, CustomerID, OrderDate, SubTotal, Discount, VAT, TotalAmount, PaymentMethod, AmountPaid, Balance, Status, Notes)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $ins->execute([
                    $invoiceId, $customerId, date('Y-m-d'),
                    $subtotal, $discPct, $vatPct, $total, $method, $paid, $balance, $status, $notes
                ]);
                $orderId = (int)$pdo->lastInsertId();

                $insd = $pdo->prepare("INSERT INTO orderdetails (OrderID, ItemID, Quantity, Subtotal) VALUES (?,?,?,?)");
                foreach ($lines as $ln) {
                    $insd->execute([$orderId, $ln['ItemID'], $ln['Qty'], $ln['Subtotal']]);
                }
                $pdo->commit();

                $messages[]      = "✅ Invoice <b>$invoiceId</b> saved successfully.";
                $invoiceOpenUrl  = "invoice.php?id=" . urlencode((string)$orderId);
                $nextInvoice     = nextInvoiceId($pdo);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Save failed: " . htmlspecialchars($e->getMessage());
        }
    }
}

/* ---- Today invoices (inline recent) ---- */
$today = date('Y-m-d');
$recentStmt = $pdo->prepare("
    SELECT o.OrderID, o.InvoiceID, o.TotalAmount, o.Status, c.NAME AS CustomerName
    FROM `order` o
    LEFT JOIN customer c ON c.CustomerID = o.CustomerID
    WHERE o.OrderDate = ?
    ORDER BY o.OrderID DESC
    LIMIT 50
");
$recentStmt->execute([$today]);
$todayInvoices = $recentStmt->fetchAll();

?>
<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<style>
/* ---- White, business style — brand blue (#3b5683) ---- */
body{background:#ffffff;color:#3b5683;margin:0;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{
  background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);
  border:1px solid #dfe6f2; margin-bottom:24px;
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;margin:0;
  border-bottom:1px solid #dfe6f2; color:#3b5683;
}

/* Sections */
.section{padding:20px}

/* Inputs */
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:#6b7c97}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid #dfe6f2;border-radius:8px;font-size:14px;
  background:#fff;color:#3b5683;transition:border .2s, box-shadow .2s, background .2s, color .2s;
}
.input[readonly]{background:#f6f9fc;color:#5b6b86}
.input:focus,select:focus,textarea:focus{
  outline:none;border-color:#3b5683;box-shadow:0 0 0 3px rgba(59,86,131,.25);
}

/* Table */
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th{
  font-size:12px;color:#3b5683;text-transform:uppercase;text-align:left;padding:8px;
  background:#eef2f8;border-bottom:1px solid #dfe6f2;
}
.table td{
  border:1px solid #dfe6f2;padding:10px;vertical-align:middle;color:#3b5683;background:#fff;
}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;justify-content:center;font-weight:600;border-radius:8px;
  padding:10px 16px;font-size:14px;cursor:pointer;border:1px solid transparent;transition:all .2s;
}
.btn-primary{ background:#3b5683;color:#fff;border-color:#3b5683;}
.btn-primary:hover{ filter:brightness(1.05);}
.btn-primary:active{ background:#324a70;}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;}
.btn-ghost{ background:#fff;border:1px solid #dfe6f2;color:#3b5683;}
.btn-ghost:hover{background:#e9eff7}
.btn-danger{background:#dc2626;color:#fff}
.btn-danger:hover{background:#b91c1c}

/* Alerts */
.alert{padding:12px;border-radius:8px;margin:10px 0;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Misc */
.note{font-size:12px;color:#6b7c97}
.sticky-footer{display:flex;justify-content:space-between;align-items:center;margin-top:20px}
.hr{height:1px;background:#dfe6f2;margin:18px 0}

/* Badges */
.badge{display:inline-block;font-size:12px;line-height:1;border-radius:999px;padding:6px 10px;border:1px solid;white-space:nowrap}
.badge-paid{color:#0f5132;border-color:#a3e4c5;background:#ecfdf5}
.badge-pending{color:#92400e;border-color:#fbd38d;background:#fff7ed}

/* Totals grid */
.totals-grid{
  display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-top:16px
}
@media(max-width:1100px){.totals-grid{grid-template-columns:repeat(3,1fr)}}
.total-box{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px}
.total-box .lbl{font-size:12px;color:#6b7c97}
.total-box .val{font-size:18px;font-weight:700;margin-top:2px;color:#0f172a}
.total-box input{background:#fff}

/* Live status display */
.status-row{display:flex;gap:12px;align-items:center;margin-top:10px}
</style>

<div class="container">
  <div class="card">
    <h1>New Invoice</h1>
    <div class="section">

      <?php foreach($messages as $m): ?>
        <div class="alert alert-success"><?= $m ?></div>
      <?php endforeach; ?>
      <?php foreach($errors as $e): ?>
        <div class="alert alert-error"><?= $e ?></div>
      <?php endforeach; ?>

      <form method="post" id="invoiceForm" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

        <!-- Row 1: Type + Customer + Invoice ID -->
        <div style="display:grid;gap:16px;grid-template-columns:1fr 1fr 1fr">
          <div>
            <div class="label">Customer Type</div>
            <select name="CustomerType" id="CustomerType" class="input">
              <option value="existing">Existing</option>
              <option value="new">New</option>
            </select>
          </div>
          <div id="existingBox">
            <div class="label">Select Customer</div>
            <select name="CustomerID" id="CustomerID" class="input">
              <option value="">— Select —</option>
              <?php foreach($customers as $c): ?>
                <option value="<?= $c['CustomerID'] ?>">
                  <?= htmlspecialchars($c['NAME']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <div class="label">Invoice ID</div>
            <input type="text" class="input" value="<?= $nextInvoice ?>" disabled>
          </div>
        </div>

        <!-- Row 2: New name (only for 'new'), Email, Phone, Address -->
        <div style="display:grid;gap:16px;grid-template-columns:1fr 1fr 1fr; margin-top:16px">
          <div id="newNameBox" style="display:none">
            <div class="label">New Customer Name</div>
            <input type="text" name="NewName" id="NewName" class="input" placeholder="Full name">
          </div>
          <div>
            <div class="label">Email</div>
            <input type="text" name="Email" id="Email" class="input" placeholder="Email">
          </div>
          <div>
            <div class="label">Phone</div>
            <input type="text" name="Phone" id="Phone" class="input" placeholder="Phone">
          </div>
          <div style="grid-column: 1 / -1;">
            <div class="label">Address</div>
            <input type="text" name="Address" id="Address" class="input" placeholder="Address">
          </div>
        </div>

        <!-- Row 3: Discount / VAT -->
        <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div>
            <div class="label">Discount %</div>
            <input type="number" name="Discount" id="Discount" class="input" value="0" min="0" step="0.01">
          </div>
          <div>
            <div class="label">VAT %</div>
            <input type="number" name="VAT" id="VAT" class="input" value="0" min="0" step="0.01">
          </div>
        </div>

        <!-- Items (from inventory list) -->
        <div style="margin:16px 0;display:flex;justify-content:space-between;align-items:center">
          <small>Select items from inventory. Edit only Qty & Price if needed.</small>
          <div>
            <button type="button" class="btn btn-ghost" id="btnAddRow">+ Add Item</button>
            <button type="button" class="btn btn-danger" id="btnClearRows">Clear All</button>
          </div>
        </div>

        <table class="table" id="itemsTable">
          <thead>
            <tr>
              <th style="width:42%">Item</th>
              <th style="width:12%">Qty</th>
              <th style="width:18%">Price (LKR)</th>
              <th style="width:18%">Line Total (LKR)</th>
              <th style="width:10%">Action</th>
            </tr>
          </thead>
          <tbody id="itemsBody"></tbody>
        </table>

        <!-- Payment + Live totals -->
        <div class="totals-grid">
          <div class="total-box">
            <div class="lbl">Subtotal</div>
            <div class="val" id="uSub">LKR 0.00</div>
          </div>
          <div class="total-box">
            <div class="lbl">Discount Amount</div>
            <div class="val" id="uDisc">LKR 0.00</div>
          </div>
          <div class="total-box">
            <div class="lbl">After Discount</div>
            <div class="val" id="uAfterDisc">LKR 0.00</div>
          </div>
          <div class="total-box">
            <div class="lbl">VAT Amount</div>
            <div class="val" id="uVAT">LKR 0.00</div>
          </div>
          <div class="total-box">
            <div class="lbl">Grand Total</div>
            <div class="val" id="uTotal">LKR 0.00</div>
          </div>
          <div class="total-box">
            <div class="lbl">Amount Paid</div>
            <input type="number" name="AmountPaid" id="AmountPaid" class="input" placeholder="0.00" min="0" step="0.01" value="0">
          </div>
        </div>

        <div class="totals-grid" style="margin-top:8px">
          <div class="total-box" style="grid-column: span 3;">
            <div class="lbl">Payment Method</div>
            <select name="PaymentMethod" class="input">
              <option>Cash</option><option>Card</option><option>Bank Transfer</option>
            </select>
          </div>
          <div class="total-box" style="grid-column: span 3;">
            <div class="lbl">Notes</div>
            <input type="text" name="Notes" class="input" placeholder="Notes">
          </div>
        </div>

        <div class="status-row">
          <div id="liveStatusBadge" class="badge badge-pending">Status: Pending</div>
          <div class="note">* All values update live. Server will re-validate on Save.</div>
        </div>

        <div class="sticky-footer">
          <div class="note">Balance: <b id="uBalance">LKR 0.00</b></div>
          <div>
            <button type="submit" id="btnSave" class="btn btn-primary" disabled>Save Invoice</button>
            <button type="reset" class="btn btn-ghost" id="btnReset">Reset</button>
          </div>
        </div>
      </form>

      <!-- Divider then inline Recent -->
      <div class="hr"></div>
      <h3 style="margin:0 0 10px 0;font-size:18px;font-weight:600;color:#0f172a">Recent (Today)</h3>

      <?php if (!$todayInvoices): ?>
        <div class="note">No invoices created today (<?= htmlspecialchars($today) ?>).</div>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Total (LKR)</th>
              <th>Status</th>
              <th style="width:120px">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($todayInvoices as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['InvoiceID']) ?></td>
                <td><?= htmlspecialchars($r['CustomerName'] ?? '—') ?></td>
                <td><?= number_format((float)$r['TotalAmount'], 2) ?></td>
                <td>
                  <?php if (strcasecmp($r['Status'],'Paid')===0): ?>
                    <span class="badge badge-paid">Paid</span>
                  <?php else: ?>
                    <span class="badge badge-pending">Pending</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a class="btn btn-ghost" target="_blank" href="invoice.php?id=<?= urlencode((string)$r['OrderID']) ?>">Open</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
// -------- Helpers --------
const fmt = (n) => 'LKR ' + (Number.isFinite(n) ? n.toFixed(2) : '0.00');

// -------- Customer auto-fill --------
const CUSTOMER_MAP = <?php
  $map = [];
  foreach ($customers as $c) {
    $map[$c['CustomerID']] = [
      'email'   => $c['Email']   ?? '',
      'phone'   => $c['Phone']   ?? '',
      'address' => $c['Address'] ?? '',
    ];
  }
  echo json_encode($map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>;

const custType   = document.getElementById('CustomerType');
const customerId = document.getElementById('CustomerID');
const newNameBox = document.getElementById('newNameBox');
const newName    = document.getElementById('NewName');
const emailEl    = document.getElementById('Email');
const phoneEl    = document.getElementById('Phone');
const addrEl     = document.getElementById('Address');

function setReadOnlyFields(isReadOnly){
  [emailEl, phoneEl, addrEl].forEach(el => { el.readOnly = isReadOnly; });
  if (isReadOnly) { emailEl.title = phoneEl.title = addrEl.title = "Loaded from selected customer"; }
  else            { emailEl.title = phoneEl.title = addrEl.title = ""; }
}
function clearContactFields(){ emailEl.value = ''; phoneEl.value = ''; addrEl.value = ''; }
function fillFromCustomer(id){
  const data = CUSTOMER_MAP[id];
  if (!data) { clearContactFields(); return; }
  emailEl.value = data.email || '';
  phoneEl.value = data.phone || '';
  addrEl.value  = data.address || '';
}
function onTypeChange(){
  if (custType.value === 'new') {
    newNameBox.style.display = 'block';
    if (newName) newName.value = '';
    clearContactFields();
    setReadOnlyFields(false);
  } else {
    newNameBox.style.display = 'none';
    const id = customerId.value;
    if (id) { fillFromCustomer(id); }
    setReadOnlyFields(true);
  }
}
custType.addEventListener('change', onTypeChange);
customerId.addEventListener('change', () => {
  if (custType.value === 'existing') { fillFromCustomer(customerId.value); setReadOnlyFields(true); }
});
onTypeChange();

// -------- Inventory items & prices --------
const itemsBody = document.getElementById('itemsBody');

const ITEM_PRICE = <?php
  $priceMap = [];
  foreach ($inventoryItems as $it) {
    $priceMap[(int)$it['ItemID']] = (float)($it['Price'] ?? 0);
  }
  echo json_encode($priceMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>;

// HTML <option> list once
const ITEM_OPTIONS_HTML = `<?php
  $opts = '<option value="">— Select item —</option>';
  foreach ($inventoryItems as $it) {
      $label = htmlspecialchars($it['NAME'] . ' (ID: ' . $it['ItemID'] . ')');
      $opts .= '<option value="'.(int)$it['ItemID'].'">'.$label.'</option>';
  }
  echo $opts;
?>`;

// Totals DOM
const elSub       = document.getElementById('uSub');
const elDisc      = document.getElementById('uDisc');
const elAfterDisc = document.getElementById('uAfterDisc');
const elVAT       = document.getElementById('uVAT');
const elTotal     = document.getElementById('uTotal');
const elBalance   = document.getElementById('uBalance');
const elPaid      = document.getElementById('AmountPaid');
const elDiscPct   = document.getElementById('Discount');
const elVatPct    = document.getElementById('VAT');
const badge       = document.getElementById('liveStatusBadge');
const btnSave     = document.getElementById('btnSave');

function rowTemplate(){
  return `<tr>
    <td>
      <select name="ItemID[]" class="input item-select" required>
        ${ITEM_OPTIONS_HTML}
      </select>
    </td>
    <td>
      <input type="number" name="Qty[]" class="input qty-input" min="1" value="1" placeholder="1" required>
    </td>
    <td>
      <input type="number" name="Price[]" class="input price-input" min="0" step="0.01" placeholder="0.00" required>
    </td>
    <td>
      <input type="text" class="input line-total" value="0.00" readonly>
    </td>
    <td>
      <button type="button" class="btn btn-ghost btn-remove">Remove</button>
    </td>
  </tr>`;
}

function attachRowEvents(tr){
  const sel   = tr.querySelector('.item-select');
  const qty   = tr.querySelector('.qty-input');
  const price = tr.querySelector('.price-input');
  const line  = tr.querySelector('.line-total');
  const btnRm = tr.querySelector('.btn-remove');

  // Default-fill price on select
  sel.addEventListener('change', () => {
    const id = sel.value;
    if (ITEM_PRICE[id] !== undefined) {
      if (!price.value || parseFloat(price.value) === 0) price.value = ITEM_PRICE[id];
    }
    computeLine();
    computeTotals();
  });

  const computeLine = () => {
    const q = Math.max(0, parseInt(qty.value||'0',10));
    const p = Math.max(0, parseFloat(price.value||'0'));
    const lt = q * p;
    line.value = lt.toFixed(2);
  };

  [qty, price].forEach(el => {
    el.addEventListener('input', () => { computeLine(); computeTotals(); });
    el.addEventListener('change', () => { computeLine(); computeTotals(); });
  });

  btnRm.addEventListener('click', () => {
    tr.remove();
    computeTotals();
  });

  // First compute
  computeLine();
}

function addRow(){
  itemsBody.insertAdjacentHTML('beforeend', rowTemplate());
  attachRowEvents(itemsBody.lastElementChild);
  computeTotals();
}

document.getElementById('btnAddRow').onclick = addRow;
document.getElementById('btnClearRows').onclick = () => { itemsBody.innerHTML = ''; computeTotals(); };
if (itemsBody.children.length === 0) { addRow(); }

// ---- Live totals engine ----
function readNumber(el){ const n = parseFloat((el?.value||'').trim()); return Number.isFinite(n) ? n : 0; }

function getSubtotal(){
  let sum = 0;
  itemsBody.querySelectorAll('tr').forEach(tr => {
    const lt = tr.querySelector('.line-total');
    sum += readNumber(lt);
  });
  return sum;
}

function computeTotals(){
  const hasAnyRow = itemsBody.querySelectorAll('tr').length > 0;

  const sub = getSubtotal();
  const dPct = Math.max(0, readNumber(elDiscPct));
  const vPct = Math.max(0, readNumber(elVatPct));
  const discAmt = sub * (dPct/100);
  const afterDisc = sub - discAmt;
  const vatAmt = afterDisc * (vPct/100);
  const total = afterDisc + vatAmt;
  const paid  = Math.max(0, readNumber(elPaid));
  const balance = total - paid;

  elSub.textContent       = fmt(sub);
  elDisc.textContent      = fmt(discAmt);
  elAfterDisc.textContent = fmt(afterDisc);
  elVAT.textContent       = fmt(vatAmt);
  elTotal.textContent     = fmt(total);
  elBalance.textContent   = fmt(balance);

  // Status badge
  if (total > 0 && balance <= 0) {
    badge.classList.remove('badge-pending'); badge.classList.add('badge-paid');
    badge.textContent = 'Status: Paid';
  } else {
    badge.classList.remove('badge-paid'); badge.classList.add('badge-pending');
    badge.textContent = 'Status: Pending';
  }

  // Enable Save only if at least one valid line exists (qty>=1, price>=0, item chosen)
  let validLines = 0;
  itemsBody.querySelectorAll('tr').forEach(tr => {
    const sel   = tr.querySelector('.item-select');
    const qty   = tr.querySelector('.qty-input');
    const price = tr.querySelector('.price-input');
    const iid   = parseInt(sel.value||'0',10);
    const q     = parseInt(qty.value||'0',10);
    const p     = parseFloat(price.value||'0');
    if (iid>0 && q>0 && p>=0) validLines++;
  });

  btnSave.disabled = !(hasAnyRow && validLines>0);
}

// Recompute on % and paid change
[elDiscPct, elVatPct, elPaid].forEach(el => {
  el.addEventListener('input', computeTotals);
  el.addEventListener('change', computeTotals);
});

// Reset behavior
document.getElementById('btnReset').addEventListener('click', () => {
  setTimeout(()=>{ // wait for form reset
    itemsBody.innerHTML = '';
    addRow();
    elDiscPct.value = 0;
    elVatPct.value  = 0;
    elPaid.value    = 0;
    computeTotals();
  }, 0);
});

computeTotals();
</script>

<?php if ($invoiceOpenUrl): ?>
  <!-- Auto-open the just-saved invoice in a new tab -->
  <script>
    (function(){
      try { window.open("<?= $invoiceOpenUrl ?>","_blank"); } catch(e) {}
    })();
  </script>
<?php endif; ?>

<?php include 'footer.php'; ?>
