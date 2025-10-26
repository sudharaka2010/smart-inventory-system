<?php
// edit_billing.php (FULL UPDATED — Paid-only editing, keep Paid, mark Changed)
// - Only orders with Status in ['Paid','Paid-Changed'] are editable
// - After save, status becomes 'Paid-Changed' (Paid + Changed badges in list)
// - Safe rounding for monetary values

declare(strict_types=1);
session_start();

/* ---------- Security headers ---------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

date_default_timezone_set('Asia/Colombo');

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* ---------- DB (PDO) ---------- */
$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die("DB connection failed: " . htmlspecialchars($e->getMessage()));
}

/* ---------- Helpers ---------- */
function clean($v): string { return trim((string)$v); }

/* ---------- Required param ---------- */
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    die("Missing or invalid order_id.");
}

$messages = [];
$errors   = [];

/* ---------- Load dropdown data ---------- */
$customers = $pdo->query("SELECT CustomerID, NAME, Phone FROM customer ORDER BY NAME ASC")->fetchAll();
$inventory = $pdo->query("SELECT ItemID, NAME, Price FROM inventoryitem ORDER BY NAME ASC")->fetchAll();

/* ---------- Fetch existing order ---------- */
$ord = $pdo->prepare("
    SELECT o.*, c.NAME AS CustomerName
    FROM `order` o
    LEFT JOIN customer c ON c.CustomerID = o.CustomerID
    WHERE o.OrderID = ?
");
$ord->execute([$orderId]);
$order = $ord->fetch();

if (!$order) {
    die("Order not found.");
}

/* ---------- Only allow editing for Paid (or Paid-Changed) ---------- */
$editablePaid = in_array((string)$order['Status'], ['Paid', 'Paid-Changed'], true);
if (!$editablePaid) {
    die("Only PAID bills can be edited.");
}

/* ---------- Fetch existing order details with inventory names ---------- */
$det = $pdo->prepare("
    SELECT d.OrderDetailID, d.ItemID, d.Quantity, d.Subtotal,
           ii.NAME AS InvName, ii.Price AS InvUnitPrice
    FROM orderdetails d
    LEFT JOIN inventoryitem ii ON ii.ItemID = d.ItemID
    WHERE d.OrderID = ?
");
$det->execute([$orderId]);
$orderRows = $det->fetchAll();

/* ---------- Handle POST (save changes) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['_csrf']) || !hash_equals($CSRF, (string)$_POST['_csrf'])) {
            throw new RuntimeException('Invalid request token.');
        }

        // Customer
        $custType   = $_POST['CustomerType'] ?? 'existing';
        $customerId = 0;

        if ($custType === 'new') {
            $newName  = clean($_POST['NewName'] ?? '');
            $newEmail = clean($_POST['NewEmail'] ?? '');
            $newPhone = clean($_POST['NewPhone'] ?? '');
            $newAddr  = clean($_POST['NewAddress'] ?? '');

            if ($newName === '') throw new RuntimeException('New customer name is required.');

            if ($newEmail !== '') {
                $chk = $pdo->prepare("SELECT CustomerID FROM customer WHERE Email=?");
                $chk->execute([$newEmail]);
                if ($chk->fetch()) $newEmail = '';
            }
            $ins = $pdo->prepare("INSERT INTO customer (NAME, Email, Phone, Address) VALUES (?,?,?,?)");
            $ins->execute([$newName, $newEmail, $newPhone, $newAddr]);
            $customerId = (int)$pdo->lastInsertId();
        } else {
            $customerId = (int)($_POST['CustomerID'] ?? 0);
            if ($customerId <= 0) throw new RuntimeException('Please select a customer.');
        }

        // Discounts / VAT / payment
        $discPct = (float)($_POST['Discount'] ?? 0);
        $vatPct  = (float)($_POST['VAT'] ?? 0);
        $paid    = (float)($_POST['AmountPaid'] ?? 0); // will be forced below for paid edits
        $method  = clean($_POST['PaymentMethod'] ?? 'Cash');
        $notes   = clean($_POST['Notes'] ?? '');

        // Items
        $ids  = $_POST['ItemID'] ?? [];
        $qtys = $_POST['Qty'] ?? [];
        $prs  = $_POST['Price'] ?? [];
        $nms  = $_POST['ItemName'] ?? [];

        $subTotal = 0.0;
        $lines = [];
        $n = max(count($qtys), count($prs), count($ids));
        for ($i=0; $i<$n; $i++) {
            $iid = isset($ids[$i]) ? (int)$ids[$i] : 0;
            $qty = isset($qtys[$i]) ? (int)$qtys[$i] : 0;
            $prc = isset($prs[$i]) ? (float)$prs[$i] : 0.0;
            $nm  = clean($nms[$i] ?? '');

            if ($qty <= 0 || $prc < 0) continue;
            if ($iid <= 0 && $nm === '') continue;

            $ln  = round($qty * $prc, 2);
            $subTotal = round($subTotal + $ln, 2);
            $lines[] = ['ItemID' => ($iid ?: null), 'Qty' => $qty, 'Subtotal' => $ln];
        }
        if ($subTotal <= 0) throw new RuntimeException('Please add at least one valid item.');

        // -------- Totals (safe rounding) --------
        $subTotal    = round($subTotal, 2);
        $discountAmt = round($subTotal * ($discPct / 100), 2);
        $afterDisc   = round($subTotal - $discountAmt, 2);
        $vatAmt      = round($afterDisc * ($vatPct / 100), 2);
        $total       = round($afterDisc + $vatAmt, 2);

        // Because ONLY paid bills are editable, force them to remain fully paid.
        // Also mark status as Paid-Changed so the list can show both "Paid" and "Changed".
        $paid    = round($total, 2);
        $balance = 0.00;
        $status  = 'Paid-Changed';

        // -------- Save (transaction) --------
        $pdo->beginTransaction();

        // Update order
        $upd = $pdo->prepare("
            UPDATE `order`
            SET CustomerID=?, SubTotal=?, Discount=?, VAT=?, TotalAmount=?,
                PaymentMethod=?, AmountPaid=?, Balance=?, Status=?, Notes=?
            WHERE OrderID=?
        ");
        $upd->execute([
            $customerId,
            $subTotal, $discPct, $vatPct, $total,
            $method, $paid, $balance, $status, $notes,
            $orderId
        ]);

        // Replace details
        $pdo->prepare("DELETE FROM orderdetails WHERE OrderID=?")->execute([$orderId]);
        $insd = $pdo->prepare("INSERT INTO orderdetails (OrderID, ItemID, Quantity, Subtotal) VALUES (?,?,?,?)");
        foreach ($lines as $ln) {
            $insd->execute([$orderId, $ln['ItemID'], $ln['Qty'], $ln['Subtotal']]);
        }

        $pdo->commit();

        // Reload for display
        $ord->execute([$orderId]);
        $order = $ord->fetch();
        $det->execute([$orderId]);
        $orderRows = $det->fetchAll();

        $messages[] = "✅ Order #{$orderId} updated. Status: PAID (Changed).";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = "Save failed: " . htmlspecialchars($e->getMessage());
    }
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* === Same visual language as billing/view === */
body { background:#f4f6f9; color:#1e293b; margin:0; }
.container { margin-left:260px; padding:20px; max-width:1200px; }
@media(max-width:992px){ .container{margin-left:0;} }

.card{ background:#fff; border-radius:14px; box-shadow:0 4px 12px rgba(0,0,0,.06); margin-bottom:24px; }
.card h1{ font-size:22px; font-weight:600; padding:18px 20px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

.grid{ display:grid; gap:16px; }
.grid.two{ grid-template-columns:1fr 1fr; }
.grid.three{ grid-template-columns:repeat(3,1fr); }
@media(max-width:920px){ .grid.two, .grid.three{ grid-template-columns:1fr; } }

.label{ font-weight:600; font-size:14px; margin-bottom:6px; color:#334155 }
.input,select,textarea{
  width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; background:#fff;
}
.input:focus,select:focus,textarea:focus{ outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.2); }
.small-note{ font-size:12px; color:#64748b; }

.row-head{ display:flex; align-items:center; justify-content:space-between; margin-top:10px; }
.btn{
  display:inline-flex; align-items:center; justify-content:center; font-weight:600; border-radius:8px;
  padding:10px 16px; font-size:14px; cursor:pointer; border:1px solid transparent; transition:all .2s;
}
.btn-primary{ background:#2563eb; color:#fff; }
.btn-primary:hover{ background:#1d4ed8; }
.btn-ghost{ background:#fff; border:1px solid #cbd5e1; color:#334155; }
.btn-ghost:hover{ background:#f1f5f9; }
.btn-danger{ background:#dc2626; color:#fff; }
.btn-danger:hover{ background:#b91c1c; }

.table-wrap{ overflow:auto; border:1px solid #e5e7eb; border-radius:12px; }
.table{ width:100%; border-collapse:separate; border-spacing:0; min-width:780px; }
.table thead th{ text-align:left; padding:10px 12px; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-size:12px; text-transform:uppercase; color:#64748b }
.table td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; vertical-align:middle; }
.table tfoot td.right{ text-align:right; }
.table tr:hover td{ background:#f9fafc; }

.item-picker{ display:flex; gap:8px; align-items:center; }
.item-picker .itemName{ flex:1; }
.item-picker select{ min-width:260px; }

.actions-row{ display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
.alert{ padding:12px 14px; border-radius:8px; margin:10px 0; font-size:14px }
.alert-success{ background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
.alert-error{ background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

/* Status badge styles (shared with view page) */
.badge{padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;display:inline-block}
.badge.paid{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.badge.changed{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}
</style>

<div class="container">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

    <div class="card">
      <h1>
        Edit Invoice — <?= htmlspecialchars((string)$order['InvoiceID']) ?> (Order #<?= (int)$orderId ?>)
        <?php $st = (string)$order['Status']; ?>
        <?php if (str_starts_with($st, 'Paid')): ?>
          <span class="badge paid">Paid</span>
          <?php if (stripos($st, 'Changed') !== false): ?>
            <span class="badge changed">Changed</span>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge changed"><?= htmlspecialchars($st) ?></span>
        <?php endif; ?>
      </h1>

      <div style="padding:20px">

        <?php foreach($messages as $m): ?><div class="alert alert-success"><?= $m ?></div><?php endforeach; ?>
        <?php foreach($errors as $e): ?><div class="alert alert-error"><?= $e ?></div><?php endforeach; ?>

        <!-- Customer -->
        <div class="grid three">
          <div>
            <div class="label">Customer Type</div>
            <select name="CustomerType" id="CustomerType" class="input">
              <option value="existing" selected>Existing</option>
              <option value="new">New</option>
            </select>
          </div>
          <div id="existingBox">
            <div class="label">Select Customer</div>
            <select name="CustomerID" class="input">
              <?php foreach($customers as $c): ?>
                <option value="<?= (int)$c['CustomerID'] ?>" <?= ((int)$c['CustomerID'] === (int)$order['CustomerID'])?'selected':'' ?>>
                  <?= htmlspecialchars($c['NAME']) ?> • <?= htmlspecialchars($c['Phone']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="newBox" style="display:none">
            <div class="label">New Customer Name</div>
            <input type="text" name="NewName" class="input" placeholder="Full name">
          </div>
        </div>
        <div id="newDetails" style="display:none;margin-top:12px" class="grid three">
          <input type="email" name="NewEmail" class="input" placeholder="Email">
          <input type="text"  name="NewPhone" class="input" placeholder="Phone">
          <input type="text"  name="NewAddress" class="input" placeholder="Address">
        </div>

        <!-- Invoice fields -->
        <div class="grid three" style="margin-top:16px">
          <div>
            <div class="label">Invoice ID</div>
            <input type="text" class="input" value="<?= htmlspecialchars((string)$order['InvoiceID']) ?>" disabled>
          </div>
          <div>
            <div class="label">Discount (%)</div>
            <input type="number" step="0.01" name="Discount" class="input" value="<?= htmlspecialchars((string)$order['Discount']) ?>">
          </div>
          <div>
            <div class="label">VAT (%)</div>
            <input type="number" step="0.01" name="VAT" class="input" value="<?= htmlspecialchars((string)$order['VAT']) ?>">
          </div>
        </div>

        <!-- Items table -->
        <div class="row-head" style="margin-top:18px">
          <div class="label">Items</div>
          <div>
            <button type="button" class="btn btn-ghost" id="btnAddRow">+ Add Item</button>
            <button type="button" class="btn btn-danger" id="btnClearRows">Clear All</button>
          </div>
        </div>

        <div class="table-wrap" style="margin-top:8px">
          <table class="table" id="itemsTable">
            <thead>
              <tr>
                <th>Item (inventory or custom)</th>
                <th style="width:120px">Qty</th>
                <th style="width:160px">Unit Price (LKR)</th>
                <th style="width:160px">Line Total</th>
                <th style="width:110px">Action</th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              <?php
                foreach ($orderRows as $r):
                  $iid   = $r['ItemID'] ? (int)$r['ItemID'] : 0;
                  $qtty  = (int)$r['Quantity'];
                  $line  = (float)$r['Subtotal'];
                  $unit  = $qtty>0 ? ($line / $qtty) : (float)$r['InvUnitPrice'];
                  $name  = $r['InvName'] ?? '';
              ?>
              <tr>
                <td>
                  <div class="item-picker">
                    <select name="ItemID[]" class="input itemSelect">
                      <option value="">— Custom —</option>
                      <?php foreach ($inventory as $it): ?>
                        <option value="<?= (int)$it['ItemID'] ?>"
                                data-price="<?= (float)$it['Price'] ?>"
                                <?= $iid===(int)$it['ItemID']?'selected':'' ?>>
                          <?= htmlspecialchars($it['NAME']) ?> — Rs.<?= number_format((float)$it['Price'],2) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" name="ItemName[]" class="input itemName"
                           placeholder="Custom item name" value="<?= htmlspecialchars($name) ?>">
                  </div>
                </td>
                <td><input type="number" name="Qty[]"   class="input qty"   min="1" step="1"    value="<?= $qtty ?>"></td>
                <td><input type="number" name="Price[]" class="input price" min="0" step="0.01" value="<?= number_format((float)$unit,2,'.','') ?>"></td>
                <td><input type="text" class="input lineTotal" value="<?= number_format((float)$line,2) ?>" readonly></td>
                <td><button type="button" class="btn btn-ghost removeRow">Remove</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="small-note" style="text-align:right">Sub Total</td>
                <td><input type="text" id="sub_total" class="input" value="0.00" readonly></td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="small-note" style="text-align:right">Discount (calc)</td>
                <td><input type="text" id="discount_amt" class="input" value="0.00" readonly></td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="small-note" style="text-align:right">VAT (calc)</td>
                <td><input type="text" id="vat_amt" class="input" value="0.00" readonly></td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="small-note" style="text-align:right"><b>Total</b></td>
                <td><input type="text" id="total_amt" class="input" value="0.00" readonly style="font-weight:700"></td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="small-note" style="text-align:right">Amount Paid</td>
                <td><input type="number" name="AmountPaid" id="amount_paid" class="input" step="0.01"
                           value="<?= number_format((float)$order['AmountPaid'],2,'.','') ?>"></td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="small-note" style="text-align:right"><b>Balance</b></td>
                <td><input type="text" id="balance" class="input" value="0.00" readonly style="font-weight:700"></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Payment + Notes -->
        <div class="grid two" style="margin-top:16px">
          <div>
            <div class="label">Payment Method</div>
            <select name="PaymentMethod" class="input">
              <?php
                $methods = ['Cash','Card','Bank Transfer','Credit'];
                $cur = (string)$order['PaymentMethod'];
                foreach ($methods as $m) {
                    $sel = $m===$cur ? 'selected' : '';
                    echo "<option $sel>".htmlspecialchars($m)."</option>";
                }
              ?>
            </select>
          </div>
          <div>
            <div class="label">Notes</div>
            <input type="text" name="Notes" class="input" value="<?= htmlspecialchars((string)$order['Notes']) ?>">
          </div>
        </div>

        <div class="actions-row">
          <a href="view_billing.php" class="btn btn-ghost">← Back to Orders</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>

      </div>
    </div>
  </form>
</div>

<script>
/* ---------- JS helpers ---------- */
const INVENTORY = <?= json_encode($inventory, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const q  = s => document.querySelector(s);
const qa = s => Array.from(document.querySelectorAll(s));
const fmt = n => Number.isFinite(n) ? n.toFixed(2) : '0.00';

/* Customer switch */
const custType = q('#CustomerType');
const existingBox = q('#existingBox');
const newBox = q('#newBox');
const newDetails = q('#newDetails');
function toggleCustomer(){
  if (custType.value==='new'){ existingBox.style.display='none'; newBox.style.display='block'; newDetails.style.display='grid'; }
  else { existingBox.style.display='block'; newBox.style.display='none'; newDetails.style.display='none'; }
}
custType.addEventListener('change', toggleCustomer); toggleCustomer();

/* Items table */
const body = q('#itemsBody');
const addBtn = q('#btnAddRow');
const clearBtn = q('#btnClearRows');

function invSelectHtml(){
  const opts = ['<option value="">— Custom —</option>']
    .concat(INVENTORY.map(it => `<option value="${it.ItemID}" data-price="${Number(it.Price)||0}">${escapeHtml(it.NAME)} — Rs.${fmt(Number(it.Price)||0)}</option>`));
  return `<select name="ItemID[]" class="input itemSelect">${opts.join('')}</select>`;
}
function escapeHtml(t){const d=document.createElement('div'); d.textContent=t??''; return d.innerHTML;}

function makeRow(prefill={}){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <div class="item-picker">
        ${invSelectHtml()}
        <input type="text" name="ItemName[]" class="input itemName" placeholder="Custom item name" value="${prefill.name||''}">
      </div>
    </td>
    <td><input type="number" name="Qty[]" class="input qty" min="1" step="1" value="${prefill.qty||1}"></td>
    <td><input type="number" name="Price[]" class="input price" min="0" step="0.01" value="${prefill.price||0}"></td>
    <td><input type="text" class="input lineTotal" value="0.00" readonly></td>
    <td><button type="button" class="btn btn-ghost removeRow">Remove</button></td>
  `;
  body.appendChild(tr);
  bindRow(tr);
  recalc();
}
function bindExistingRows(){ qa('#itemsBody tr').forEach(tr => bindRow(tr)); }
function bindRow(tr){
  const sel = tr.querySelector('.itemSelect');
  const qty = tr.querySelector('.qty');
  const price = tr.querySelector('.price');
  const name = tr.querySelector('.itemName');

  sel.addEventListener('change', () => {
    const opt = sel.options[sel.selectedIndex];
    const p = parseFloat(opt.getAttribute('data-price') || '0');
    if (sel.value) {
      price.value = p;
      const text = opt.textContent;
      const base = text.includes('—') ? text.split('—')[0].trim() : text;
      name.value = base;
    }
    recalc();
  });
  [qty, price].forEach(x => x.addEventListener('input', recalc));
  tr.querySelector('.removeRow').addEventListener('click', ()=>{ tr.remove(); recalc(); });
}

addBtn.addEventListener('click', () => makeRow());
clearBtn.addEventListener('click', () => { body.innerHTML=''; recalc(); });
bindExistingRows();

/* Totals preview */
const subEl = q('#sub_total');
const discPctEl = q('input[name="Discount"]');
const vatPctEl  = q('input[name="VAT"]');
const discAmtEl = q('#discount_amt');
const vatAmtEl  = q('#vat_amt');
const totalEl   = q('#total_amt');
const paidEl    = q('#amount_paid');
const balEl     = q('#balance');

[discPctEl, vatPctEl, paidEl].forEach(el => el.addEventListener('input', recalc));

function recalc(){
  let sub = 0;
  qa('#itemsBody tr').forEach(tr => {
    const qv = parseFloat(tr.querySelector('.qty')?.value || '0');
    const pv = parseFloat(tr.querySelector('.price')?.value || '0');
    const ln = Math.round((qv * pv + Number.EPSILON) * 100) / 100;
    tr.querySelector('.lineTotal').value = fmt(ln);
    sub = Math.round((sub + ln) * 100) / 100;
  });
  subEl.value = fmt(sub);

  const dPct = parseFloat(discPctEl.value || '0');
  const vPct = parseFloat(vatPctEl.value  || '0');
  const paid = parseFloat(paidEl.value    || '0');

  const dAmt = Math.round((sub * (dPct/100)) * 100) / 100;
  discAmtEl.value = fmt(dAmt);

  const afterDisc = Math.round((sub - dAmt) * 100) / 100;
  const vAmt = Math.round((afterDisc * (vPct/100)) * 100) / 100;
  vatAmtEl.value = fmt(vAmt);

  const total = Math.round((afterDisc + vAmt) * 100) / 100;
  totalEl.value = fmt(total);

  const bal = Math.round((total - (isNaN(paid)?0:paid)) * 100) / 100;
  balEl.value = fmt(bal);
}
recalc();
</script>

<?php include 'footer.php'; ?>
