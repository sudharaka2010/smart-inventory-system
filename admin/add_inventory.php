<?php
// add_inventory.php (UPDATED: Past/Today/Future allowed + Status column + Live Totals)
// - Past/current/future ReceiveDate allowed (no min restriction)
// - UI Status auto-fills per row: Past="Arrived item", Today="On Date", Future="Pending"
// - Server computes & saves Status too (writes to inventoryitem.Status if column exists)
// - Live totals bar below the table: Items, Total Qty, Estimated Value (LKR)
// - Supplier prefill (?supplier_id / ?SupplierID / ?sid / ?id)
// - Optional invoice prefill (?invoice=...)
// - CSRF + strict validation; PRG; multiple items under supplier+invoice

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
function flash_push(string $type, string $msg): void {
    $_SESSION['flash'][$type][] = $msg;
}
function flash_take(): array {
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $out;
}

/* ---------------- DB connection (PDO) ---------------- */
if (!isset($pdo)) {
    $dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        die("DB connection failed: " . htmlspecialchars($e->getMessage()));
    }
}

/* ---- helper: does inventoryitem have Status column? ---- */
function inventoryitem_has_status(PDO $pdo): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $row = $pdo->query("SHOW COLUMNS FROM inventoryitem LIKE 'Status'")->fetch();
        $has = (bool)$row;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}
$TABLE_HAS_STATUS = inventoryitem_has_status($pdo);

/* ---------------- Load active suppliers ---------------- */
$suppliers = [];
try {
    $stmt = $pdo->query("SELECT SupplierID, NAME AS SupplierName FROM supplier WHERE Status='Active' ORDER BY NAME ASC");
    $suppliers = $stmt->fetchAll();
} catch (Throwable $e) {
    // silent
}
$supplierMap = [];
foreach ($suppliers as $s) {
    $supplierMap[(int)$s['SupplierID']] = (string)$s['SupplierName'];
}

/* ---------------- Prefill supplier from GET ---------------- */
$prefillSupplierId = 0;
foreach (['supplier_id','SupplierID','sid','id'] as $k) {
    if (isset($_GET[$k]) && is_numeric($_GET[$k])) {
        $prefillSupplierId = (int)$_GET[$k];
        break;
    }
}
$prefillSupplierName = '';
if ($prefillSupplierId > 0) {
    try {
        $chk = $pdo->prepare("SELECT SupplierID, NAME FROM supplier WHERE SupplierID=? AND Status='Active'");
        $chk->execute([$prefillSupplierId]);
        $row = $chk->fetch();
        if ($row) {
            $prefillSupplierId   = (int)$row['SupplierID'];
            $prefillSupplierName = (string)$row['NAME'];
        } else {
            $prefillSupplierId = 0;
        }
    } catch (Throwable $e) {
        $prefillSupplierId = 0;
    }
}

/* ---------------- Optional invoice prefill ---------------- */
$prefillInvoice = '';
if (isset($_GET['invoice'])) {
    $cand = trim((string)$_GET['invoice']);
    if ($cand !== '' && preg_match('/^[A-Za-z0-9\-\/_]{3,32}$/', $cand)) {
        $prefillInvoice = $cand;
    }
}

/* ---------------- Handle POST (insert) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // CSRF
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['_csrf'])) {
        $errors[] = "Invalid request. Please refresh and try again.";
    }

    // Supplier (validate again)
    $supplierId = (int)($_POST['SupplierID'] ?? 0);
    $supplierRow = null;
    if ($supplierId > 0) {
        $chk = $pdo->prepare("SELECT SupplierID, NAME FROM supplier WHERE SupplierID=? AND Status='Active'");
        $chk->execute([$supplierId]);
        $supplierRow = $chk->fetch();
    }
    if (!$supplierRow) {
        $errors[] = "Please select a valid active supplier.";
    }
    $supplierName = $supplierRow['NAME'] ?? '';

    // Invoice ID
    $invoiceId = trim((string)($_POST['InvoiceID'] ?? ''));
    if ($invoiceId === '') {
        $errors[] = "Invoice ID is required.";
    } elseif (!preg_match('/^[A-Za-z0-9\-\/_]{3,32}$/', $invoiceId)) {
        $errors[] = "Invoice ID must be 3–32 characters (letters, numbers, -, /, _).";
    }

    // Item arrays
    $names  = $_POST['ItemName']     ?? [];
    $descs  = $_POST['Description']  ?? [];
    $qtys   = $_POST['Quantity']     ?? [];
    $prices = $_POST['Price']        ?? [];
    $cats   = $_POST['Category']     ?? [];
    $dates  = $_POST['ReceiveDate']  ?? [];

    $items = [];
    $now = new DateTime('now', new DateTimeZone('Asia/Colombo'));
    $todayYmd = $now->format('Y-m-d');

    $allowedCats = ['Unknown','Construction','Steel','Plumbing','Tools','Other'];

    $rowCount = max(count($names), count($descs), count($qtys), count($prices), count($cats), count($dates));
    for ($i = 0; $i < $rowCount; $i++) {
        $name  = trim((string)($names[$i]  ?? ''));
        $desc  = trim((string)($descs[$i]  ?? ''));
        $qty   = (int)($qtys[$i]          ?? 0);
        $price = (string)($prices[$i]     ?? '');
        $cat   = trim((string)($cats[$i]  ?? 'Unknown'));
        $rdRaw = trim((string)($dates[$i] ?? ''));

        // Skip blank rows
        if ($name === '' && $qty === 0 && $price === '' && $rdRaw === '') {
            continue;
        }

        // Validate row
        if ($name === '') {
            $errors[] = "Row ".($i+1).": Item name is required.";
        }
        if ($qty <= 0) {
            $errors[] = "Row ".($i+1).": Quantity must be greater than 0.";
        }

        // Price
        $priceVal = 0.0;
        if ($price !== '') {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
                $errors[] = "Row ".($i+1).": Price must be a non-negative number (max 2 decimals).";
            } else {
                $priceVal = (float)$price;
            }
        }

        // Category
        if (!in_array($cat, $allowedCats, true)) {
            $cat = 'Unknown';
        }

        // Receive date (required) — allows past/current/future
        if ($rdRaw === '') {
            $errors[] = "Row ".($i+1).": Receive date is required.";
        } else {
            try {
                $rdObj = new DateTime($rdRaw, new DateTimeZone('Asia/Colombo'));
            } catch (Throwable $e) {
                $errors[] = "Row ".($i+1).": Invalid receive date.";
                $rdObj = null;
            }
        }

        // Compute Status based on DATE (ignoring time)
        $status = '';
        if (isset($rdObj)) {
            $rdYmd = $rdObj->format('Y-m-d');
            if ($rdYmd < $todayYmd)       $status = 'Arrived item';
            elseif ($rdYmd > $todayYmd)   $status = 'Pending';
            else                          $status = 'On Date';
        }

        $items[] = [
            'NAME'        => $name,
            'Description' => $desc,
            'Quantity'    => $qty,
            'Price'       => $priceVal,
            'Category'    => $cat,
            'ReceiveDate' => $rdRaw,
            'Status'      => $status,
        ];
    }

    if (!$items) {
        $errors[] = "Add at least one valid item row.";
    }

    // Insert if no errors
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($TABLE_HAS_STATUS) {
                $sql = "INSERT INTO inventoryitem
                        (InvoiceID, NAME, Description, Quantity, Price, SupplierID, SupplierName, Category, ReceiveDate, Status)
                        VALUES (:InvoiceID, :NAME, :Description, :Quantity, :Price, :SupplierID, :SupplierName, :Category, :ReceiveDate, :Status)";
            } else {
                $sql = "INSERT INTO inventoryitem
                        (InvoiceID, NAME, Description, Quantity, Price, SupplierID, SupplierName, Category, ReceiveDate)
                        VALUES (:InvoiceID, :NAME, :Description, :Quantity, :Price, :SupplierID, :SupplierName, :Category, :ReceiveDate)";
            }
            $ins = $pdo->prepare($sql);

            foreach ($items as $it) {
                $params = [
                    ':InvoiceID'    => $invoiceId,
                    ':NAME'         => $it['NAME'],
                    ':Description'  => $it['Description'],
                    ':Quantity'     => $it['Quantity'],
                    ':Price'        => $it['Price'],
                    ':SupplierID'   => $supplierId,
                    ':SupplierName' => $supplierName,
                    ':Category'     => $it['Category'],
                    ':ReceiveDate'  => $it['ReceiveDate'],
                ];
                if ($TABLE_HAS_STATUS) {
                    $params[':Status'] = $it['Status'];
                }
                $ins->execute($params);
            }
            $pdo->commit();

            flash_push('success', "✅ Added ".count($items)." item(s) under invoice <b>".htmlspecialchars($invoiceId)."</b>.");
            header("Location: add_inventory.php"); // PRG
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash_push('error', "Insert failed: " . htmlspecialchars($e->getMessage()));
            header("Location: add_inventory.php");
            exit;
        }
    } else {
        foreach ($errors as $er) flash_push('error', htmlspecialchars($er));
        header("Location: add_inventory.php");
        exit;
    }
}

/* ---------------- Take flashes for display ---------------- */
$flashes = flash_take();

include 'header.php';
include 'sidebar.php';
?>
<style>
/* === Professional, eye-comfort theme (brand blue) === */
:root{
  --brand:#3b5683; --brand-dark:#324a70; --brand-ring:rgba(59,86,131,.25);
  --brand-tint:#e9eff7; --brand-tint-hover:#dde7f6; --border:#dfe6f2;
  --text:#2b3e5a; --muted:#6b7c97;
}

/* Base */
body{background:#ffffff;color:var(--text);margin:0;font-family:'Poppins',sans-serif;}
.container{margin-left:260px;padding:20px;max-width:1200px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);
  border:1px solid var(--border);margin-bottom:24px;overflow:hidden;color:var(--text);}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);color:var(--text);}

/* Controls */
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted);}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;
  font-size:14px;background:#fff;transition:border .2s, box-shadow .2s;color:var(--text);
}
.input:focus,select:focus,textarea:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-ring);
}

/* Grid */
.grid{display:grid;gap:16px;grid-template-columns:1fr 1fr 1fr;max-width:100%}
@media(max-width:900px){.grid{grid-template-columns:1fr;}}

/* Table */
.table{width:100%;border-collapse:separate;border-spacing:0 10px;margin-top:10px}
.table th{font-size:12px;color:var(--text);text-transform:uppercase;text-align:left;padding:6px;background:var(--brand-tint);}
.table td{background:#fff;border:1px solid var(--border);padding:10px;vertical-align:top;color:var(--text);}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;justify-content:center;
  font-weight:600;border-radius:8px;padding:10px 18px;font-size:14px;
  cursor:pointer;border:1px solid transparent;transition:all .2s;user-select:none;
  min-width:120px;
}
.btn-primary{background:var(--brand);color:#fff;border-color:var(--brand);}
.btn-primary:hover{background:var(--brand);filter:brightness(1.05);}
.btn-primary:active{background:var(--brand-dark);}
.btn-primary:focus-visible{outline:none;box-shadow:0 0 0 3px var(--brand-ring);}
.btn-ghost{background:#fff;border:1px solid var(--border);color:var(--text);}
.btn-ghost:hover{background:var(--brand-tint);}
.btn-danger{background:#dc2626;color:#fff;}
.btn-danger:hover{background:#b91c1c;}

/* Button group */
.btn-group{
  display:flex; gap:16px; align-items:center; justify-content:flex-end; margin-top:6px; flex-wrap:wrap;
}

/* Alerts */
.alert{padding:12px 14px;border-radius:8px;margin:10px 0;font-size:14px}
.alert-success{background:#ecf5ff;color:var(--brand);border:1px solid var(--border);}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fde2e2;}

/* Footer note */
.sticky-footer{display:flex;justify-content:space-between;align-items:center;margin-top:20px;flex-wrap:wrap;gap:12px}
.note{font-size:12px;color:var(--muted);}

/* --- Totals strip --- */
.stats{
  display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0 6px 0;
}
.stat{
  background:var(--brand-tint);border:1px solid var(--border);border-radius:12px;
  padding:12px 14px;display:flex;flex-direction:column;gap:4px;
}
.stat .k{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}
.stat .v{font-size:18px;font-weight:700;color:var(--text);}
@media(max-width:700px){.stats{grid-template-columns:1fr;}}
</style>

<div class="container"
     data-prefill-id="<?= (int)$prefillSupplierId ?>"
     data-prefill-name="<?= htmlspecialchars($prefillSupplierName, ENT_QUOTES, 'UTF-8') ?>"
     data-prefill-invoice="<?= htmlspecialchars($prefillInvoice, ENT_QUOTES, 'UTF-8') ?>">
  <form method="post" autocomplete="off" novalidate>
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($CSRF); ?>">

    <div class="card">
      <h1>Add Inventory Items</h1>
      <div style="padding:20px">

        <?php if (!empty($flashes['success'])): ?>
          <?php foreach ($flashes['success'] as $m): ?>
            <div class="alert alert-success"><?php echo $m; ?></div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($flashes['error'])): ?>
          <?php foreach ($flashes['error'] as $e): ?>
            <div class="alert alert-error"><?php echo $e; ?></div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="grid">
          <div>
            <div class="label">Supplier</div>
            <select name="SupplierID" id="SupplierID" class="input" required>
              <option value="">— Select —</option>
              <?php foreach($suppliers as $s): ?>
                <option
                  value="<?= (int)$s['SupplierID'] ?>"
                  <?= ($prefillSupplierId === (int)$s['SupplierID']) ? 'selected' : '' ?>
                ><?= htmlspecialchars($s['SupplierName']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($prefillSupplierId > 0): ?>
              <small style="color:#64748b;display:block;margin-top:6px">
                Loaded from Suppliers: #<?= (int)$prefillSupplierId ?> — <?= htmlspecialchars($prefillSupplierName) ?>
              </small>
            <?php endif; ?>
          </div>
          <div>
            <div class="label">Supplier Name (auto)</div>
            <input type="text" id="SupplierName" class="input" readonly placeholder="Auto-filled"
                   value="<?= htmlspecialchars($prefillSupplierName) ?>">
          </div>
          <div>
            <div class="label">Invoice ID</div>
            <input
              type="text"
              name="InvoiceID"
              id="InvoiceID"
              class="input"
              placeholder="e.g., IN2025-00123"
              minlength="3" maxlength="32"
              pattern="[A-Za-z0-9\-/_]{3,32}"
              value="<?= htmlspecialchars($prefillInvoice) ?>"
              required
              title="3–32 chars: letters, numbers, -, /, _"
            >
          </div>
        </div>

        <div style="margin:16px 0;display:flex;justify-content:space-between;align-items:center">
          <small>* You can add multiple items under a single supplier & invoice. You may choose past, today, or future dates; status and totals update automatically.</small>
          <div class="btn-group">
            <button type="button" class="btn btn-ghost" id="btnAddRow">+ Add Item</button>
            <button type="button" class="btn btn-danger" id="btnClearRows">Clear All</button>
          </div>
        </div>

        <table class="table" id="itemsTable">
          <thead>
            <tr>
              <th>Item*</th>
              <th>Description</th>
              <th>Qty*</th>
              <th>Price (LKR)</th>
              <th>Category</th>
              <th>Receive Date*</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="itemsBody"></tbody>
        </table>

        <!-- Live Totals -->
        <div class="stats" id="totalsBar" aria-live="polite">
          <div class="stat">
            <span class="k">Items</span>
            <span class="v" id="tItems">0</span>
          </div>
          <div class="stat">
            <span class="k">Total Qty</span>
            <span class="v" id="tQty">0</span>
          </div>
          <div class="stat">
            <span class="k">Estimated Value (LKR)</span>
            <span class="v" id="tValue">0.00</span>
          </div>
        </div>

        <div class="sticky-footer">
          <div class="note">* Required.</div>
          <div>
            <button type="submit" class="btn btn-primary">Save Items</button>
            <button type="reset" class="btn btn-ghost" id="btnReset">Reset</button>
          </div>
        </div>

      </div>
    </div>
  </form>
</div>

<script>
/* --- Status calculator (based on DATE, not time) --- */
function statusFor(dateStr){
  if(!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '';
  const today = new Date();
  const toYmd = (x)=>`${x.getFullYear()}-${String(x.getMonth()+1).padStart(2,'0')}-${String(x.getDate()).padStart(2,'0')}`;
  const dY = toYmd(d), tY = toYmd(today);
  if (dY < tY) return 'Arrived item';
  if (dY > tY) return 'Pending';
  return 'On Date';
}

const container=document.querySelector('.container');
const itemsBody=document.getElementById('itemsBody');
const supplierSelect=document.getElementById('SupplierID');
const supplierName=document.getElementById('SupplierName');
const invoiceInput=document.getElementById('InvoiceID');

function itemRowTemplate(){
  return `
  <tr>
    <td><input name="ItemName[]" class="input item-name" required placeholder="Item name"></td>
    <td><textarea name="Description[]" class="input" rows="1" placeholder="Optional"></textarea></td>
    <td><input type="number" name="Quantity[]" class="input qty" min="1" step="1" required></td>
    <td><input type="number" name="Price[]" class="input price" min="0" step="0.01" placeholder="0.00"></td>
    <td>
      <select name="Category[]" class="input">
        <option>Unknown</option>
        <option>Construction</option>
        <option>Steel</option>
        <option>Plumbing</option>
        <option>Tools</option>
        <option>Other</option>
      </select>
    </td>
    <td>
      <input type="datetime-local" name="ReceiveDate[]" class="input receive-date" required>
    </td>
    <td>
      <input type="text" name="Status[]" class="input status-cell" readonly placeholder="Auto">
    </td>
    <td><button type="button" class="btn btn-ghost remove-btn">Remove</button></td>
  </tr>`;
}

function wireRow(row){
  const dateInput = row.querySelector('.receive-date');
  const statusInput = row.querySelector('.status-cell');
  const updateStatus = ()=>{ statusInput.value = statusFor(dateInput.value); };
  dateInput.addEventListener('change', updateStatus);
  updateStatus();

  // Any change in this row should recalc totals
  row.addEventListener('input', recalcTotals);
}

function ensureOneRow(){
  if(!itemsBody.children.length){
    const tr=document.createElement('tr'); tr.innerHTML=itemRowTemplate(); itemsBody.appendChild(tr);
    wireRow(tr);
    recalcTotals();
  }
}

/* ---- Totals ---- */
const tItems = document.getElementById('tItems');
const tQty   = document.getElementById('tQty');
const tValue = document.getElementById('tValue');

function recalcTotals(){
  let items = 0;
  let qty   = 0;
  let val   = 0;

  [...itemsBody.querySelectorAll('tr')].forEach(tr=>{
    const name = tr.querySelector('.item-name')?.value.trim() || '';
    const q    = parseFloat(tr.querySelector('.qty')?.value || '0');
    const p    = parseFloat(tr.querySelector('.price')?.value || '0');

    if (name !== '') items += 1;
    if (!Number.isNaN(q)) qty += q;
    if (!Number.isNaN(q) && !Number.isNaN(p)) val += q * p;
  });

  tItems.textContent = String(items);
  tQty.textContent   = String(qty);
  tValue.textContent = (Math.round(val * 100) / 100).toFixed(2);
}

/* ---- Add/Clear/Reset ---- */
document.getElementById('btnAddRow').onclick=()=>{
  const tr=document.createElement('tr'); tr.innerHTML=itemRowTemplate(); itemsBody.appendChild(tr);
  wireRow(tr);
  recalcTotals();
};

document.getElementById('btnClearRows').onclick=()=>{
  itemsBody.innerHTML=''; ensureOneRow();
  recalcTotals();
};

document.getElementById('btnReset').onclick=()=>{
  setTimeout(()=>{
    itemsBody.innerHTML=''; ensureOneRow(); supplierName.value='';
    recalcTotals();
  },0);
};

/* ---- Remove buttons (event delegation) ---- */
itemsBody.addEventListener('click', (e)=>{
  const btn = e.target.closest('.remove-btn');
  if (!btn) return;
  const tr = btn.closest('tr');
  tr?.remove();
  ensureOneRow();
  recalcTotals();
});

/* ---- Supplier select → name ---- */
supplierSelect.onchange=()=>{
  supplierName.value = supplierSelect.options[supplierSelect.selectedIndex]?.text || '';
};

/* ---- Prefill ---- */
(function applyPrefill(){
  const id  = parseInt(container.dataset.prefillId || '0', 10);
  const nm  = container.dataset.prefillName || '';
  const inv = container.dataset.prefillInvoice || '';

  if (id > 0) {
    for (const opt of supplierSelect.options) {
      if (parseInt(opt.value || '0',10) === id) {
        opt.selected = true;
        break;
      }
    }
    if (!supplierName.value) supplierName.value = nm || supplierSelect.options[supplierSelect.selectedIndex]?.text || '';
  }
  if (inv && !invoiceInput.value) {
    invoiceInput.value = inv;
  }
})();

/* ---- Boot ---- */
ensureOneRow();
recalcTotals();
</script>

<?php include 'footer.php'; ?>
