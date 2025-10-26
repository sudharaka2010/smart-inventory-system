<?php
// add_return.php (FULL UPDATED)
// Style/structure mirrors your add_supplier.php sample (white theme, card, alerts)
// Uses PDO, CSRF (_csrf), PRG flash, strict validation, row lock, atomic stock update

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

// ---- DB (PDO like your view_billing.php) ----
$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die("DB failed: " . htmlspecialchars($e->getMessage()));
}

$errors = [];

// ---- POST: Add Return ----
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_csrf']) &&
    hash_equals($CSRF, (string)$_POST['_csrf'])
) {
    // Collect & trim (server-trust only IDs from DB)
    $ItemID        = (int)($_POST['ItemID'] ?? 0);          // required
    $ReturnQty     = (int)($_POST['ReturnQuantity'] ?? 0);  // required
    $ReturnReason  = trim((string)($_POST['ReturnReason'] ?? '')); // required >= 3 chars
    $ReturnDateRaw = trim((string)($_POST['ReturnDate'] ?? ''));   // optional

    // ---- VALIDATION ----
    if ($ItemID <= 0) {
        $errors[] = "Please select a valid item.";
    }
    if ($ReturnQty <= 0) {
        $errors[] = "Return quantity must be a positive whole number.";
    }
    if ($ReturnReason === '' || mb_strlen($ReturnReason) < 3) {
        $errors[] = "Please provide a meaningful reason (min 3 characters).";
    }
    $ReturnDate = null;
    if ($ReturnDateRaw !== '') {
        $ts = strtotime($ReturnDateRaw);
        if ($ts === false) {
            $errors[] = "Invalid return date/time.";
        } elseif ($ts > time()) {
            $errors[] = "Return date cannot be in the future.";
        } else {
            $ReturnDate = date('Y-m-d H:i:s', $ts);
        }
    } else {
        $ReturnDate = date('Y-m-d H:i:s');
    }

    // ---- INSERT + UPDATE (transaction) ----
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock item row to read current stock and supplier/invoice info
            $lock = $pdo->prepare("
                SELECT i.ItemID, i.InvoiceID, i.Quantity, i.SupplierID,
                       COALESCE(i.SupplierName, s.NAME) AS SupplierName
                FROM inventoryitem i
                LEFT JOIN supplier s ON s.SupplierID = i.SupplierID
                WHERE i.ItemID = ?
                FOR UPDATE
            ");
            $lock->execute([$ItemID]);
            $row = $lock->fetch();

            if (!$row) {
                throw new InvalidArgumentException("Selected item not found.");
            }

            $currentQty   = (int)$row['Quantity'];
            $invoiceId    = (string)$row['InvoiceID'];
            $supplierId   = (int)$row['SupplierID'];
            $supplierName = (string)($row['SupplierName'] ?? '');

            if ($ReturnQty > $currentQty) {
                throw new InvalidArgumentException("Return quantity ($ReturnQty) cannot exceed current stock ($currentQty).");
            }

            // Insert return record
            $ins = $pdo->prepare("
                INSERT INTO returnitem
                  (ItemID, InvoiceID, ReturnQuantity, ReturnReason, ReturnDate, SupplierID, SupplierName)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $ItemID,
                $invoiceId,
                $ReturnQty,
                $ReturnReason,
                $ReturnDate,
                $supplierId,
                $supplierName
            ]);

            // Update stock
            $newQty = $currentQty - $ReturnQty;
            $upd = $pdo->prepare("UPDATE inventoryitem SET Quantity = ? WHERE ItemID = ?");
            $upd->execute([$newQty, $ItemID]);

            $pdo->commit();
            $_SESSION['flash'] = ['ok' => true, 'msg' => "Return recorded. New stock for Item #{$ItemID}: {$newQty}"];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = $e instanceof InvalidArgumentException ? $e->getMessage() : "Unexpected error while saving return.";
            $_SESSION['flash'] = ['ok' => false, 'msg' => htmlspecialchars($msg)];
        }
        // PRG
        header("Location: add_return.php");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: add_return.php");
        exit();
    }
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Items for dropdown (show items with stock > 0) ----
$items = [];
try {
    $stmt = $pdo->query("
        SELECT i.ItemID, i.NAME AS ItemName, i.InvoiceID, i.Quantity, i.SupplierID,
               COALESCE(i.SupplierName, s.NAME) AS SupplierName, i.Category
        FROM inventoryitem i
        LEFT JOIN supplier s ON s.SupplierID = i.SupplierID
        WHERE i.Quantity > 0
        ORDER BY i.ReceiveDate DESC, i.ItemID DESC
    ");
    $items = $stmt->fetchAll();
} catch (Throwable $e) {
    $items = [];
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business style (mirrors add_supplier.php) — brand blue (#3b5683) ---- */
:root{
  --brand:#3b5683;
  --brand-dark:#324a70;
  --ring:rgba(59,86,131,.25);
  --text:#3b5683;
  --muted:#6b7c97;
  --border:#dfe6f2;
  --tint:#e9eff7;
  --tint-hover:#dde7f6;
}

body{background:#ffffff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:900px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{
  background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;
  border:1px solid var(--border); color:var(--text);
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);margin:0;
  color:var(--text);
}

/* Header */
.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px}
.headerbar a{text-decoration:none;color:var(--brand);}
.headerbar a:hover{text-decoration:underline}

/* Alerts (semantic colors preserved) */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Form */
.form-wrap{padding:16px 20px 22px 20px;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted)}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;color:var(--text);
  transition:border .2s, box-shadow .2s, background .2s, color .2s;
}
.input:focus,select:focus,textarea:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);
}
.hint{font-size:12px;color:var(--muted);margin-top:6px}
.req{color:#dc2626}
.inline-error{color:#dc2626;font-size:12px;margin-top:6px;display:none}

/* Buttons */
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{
  padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid var(--border);background:#fff;color:var(--text);
  transition:background .2s, color .2s, box-shadow .2s, filter .2s;
}
.btn:hover{background:var(--tint)}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring)}

.btn-primary{
  background:var(--brand);color:#fff;border-color:var(--brand);
}
.btn-primary:hover{background:var(--brand);filter:brightness(1.05)}
.btn-primary:active{background:var(--brand-dark)}

/* small pills row */
.pills{display:flex;gap:8px;flex-wrap:wrap;margin:0 20px 8px 20px}
.pill{
  font-size:12px;border:1px solid var(--border);border-radius:999px;padding:4px 10px;background:#fff;color:var(--text);
}
</style>


<div class="container">
  <div class="card">
    <h1>Add Return</h1>

    <div class="headerbar">
      <div class="hint">Record returned items and auto-adjust current stock safely.</div>
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

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

      <div class="grid">
        <!-- Item select -->
        <div>
          <div class="label">Item (Invoice • Name) <span class="req">*</span></div>
          <select class="input" name="ItemID" id="ItemID" required>
            <option value="">— Select item —</option>
            <?php foreach ($items as $it): ?>
              <option value="<?= (int)$it['ItemID'] ?>"
                      data-invoice="<?= htmlspecialchars($it['InvoiceID']) ?>"
                      data-supplier="<?= htmlspecialchars((string)$it['SupplierName']) ?>"
                      data-qty="<?= (int)$it['Quantity'] ?>">
                <?= htmlspecialchars($it['InvoiceID']) ?> — <?= htmlspecialchars($it['ItemName']) ?>
                (Stock: <?= (int)$it['Quantity'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Only items with stock &gt; 0 are listed. Restock from Inventory if needed.</div>
        </div>

        <!-- Current stock (read-only) -->
        <div>
          <div class="label">Current Stock</div>
          <input class="input" id="StockNow" type="number" placeholder="—" readonly>
          <div class="hint">Auto-filled when you select an item.</div>
        </div>

        <!-- Invoice (read-only) -->
        <div>
          <div class="label">Invoice ID</div>
          <input class="input" id="InvoiceID" type="text" placeholder="—" readonly>
        </div>

        <!-- Supplier (read-only) -->
        <div>
          <div class="label">Supplier</div>
          <input class="input" id="SupplierName" type="text" placeholder="—" readonly>
        </div>

        <!-- Return quantity -->
        <div>
          <div class="label">Return Quantity <span class="req">*</span></div>
          <input class="input" type="number" name="ReturnQuantity" id="ReturnQuantity"
                 min="1" step="1" inputmode="numeric" placeholder="e.g., 5" required>
          <div class="hint">Must be ≤ Current Stock.</div>
          <div id="qtyErr" class="inline-error">Return quantity cannot exceed current stock.</div>
        </div>

        <!-- Return date/time (optional) -->
        <div>
          <div class="label">Return Date/Time</div>
          <input class="input" type="datetime-local" name="ReturnDate"
                 max="<?= htmlspecialchars(date('Y-m-d\TH:i')) ?>">
          <div class="hint">Leave empty to use current time.</div>
        </div>

        <!-- Reason -->
        <div style="grid-column:1/-1">
          <div class="label">Reason <span class="req">*</span></div>
          <textarea class="input" name="ReturnReason" id="ReturnReason" rows="3"
            placeholder="Damaged / Defective / Wrong spec ... (min 3 chars)" required></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Return</button>
        <button class="btn" type="reset" id="resetBtn">Clear</button>
      </div>
    </form>
  </div>
</div>

<script>
// Hook up dependent read-only fields
const sel = document.getElementById('ItemID');
const fInv= document.getElementById('InvoiceID');
const fSup= document.getElementById('SupplierName');
const fQty= document.getElementById('StockNow');
const inQty= document.getElementById('ReturnQuantity');
const qtyErr= document.getElementById('qtyErr');

function fill(){
  const opt = sel?.options[sel.selectedIndex];
  if(!opt || !opt.value){ fInv.value=''; fSup.value=''; fQty.value=''; return; }
  fInv.value = opt.dataset.invoice || '';
  fSup.value = opt.dataset.supplier || '';
  fQty.value = opt.dataset.qty || '';
  guardQty();
}
function guardQty(){
  const stock = parseInt(fQty.value || '0', 10);
  const rq    = parseInt(inQty.value || '0', 10);
  if (rq > stock){
    inQty.setCustomValidity('Return qty exceeds stock');
    qtyErr.style.display = 'block';
  }else{
    inQty.setCustomValidity('');
    qtyErr.style.display = 'none';
  }
}

sel?.addEventListener('change', fill);
inQty?.addEventListener('input', guardQty);

// Required fields guard (like your sample)
document.querySelector('form')?.addEventListener('submit', (e) => {
  const required = ['ItemID','ReturnQuantity','ReturnReason'];
  for (const n of required) {
    const el = document.querySelector(`[name="${n}"]`);
    if (!el || !el.value.trim()) {
      e.preventDefault();
      alert('Please fill all required fields.');
      return;
    }
  }
  guardQty();
  if (!inQty.checkValidity()){
    e.preventDefault();
    alert('Return quantity cannot exceed current stock.');
  }
});

// Save button tactile feedback
const saveBtn = document.getElementById('saveBtn');
saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
</script>

<?php include 'footer.php'; ?>
