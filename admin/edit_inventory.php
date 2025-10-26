<?php
/* ---------------------------------------------------------
   RB Stores — Edit Inventory Item (MATCHES add_inventory.php)
   - Security headers + PDO
   - CSRF + PRG + flash helpers
   - Supplier (Active only) + auto SupplierName
   - InvoiceID pattern 3–32 (A-Z,a-z,0-9,-,/,_)
   - ReceiveDate must be now/future (10-min grace)
   - Same visual style: card, buttons, alerts
   --------------------------------------------------------- */

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

/* ---------------- Helpers ---------------- */
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---------------- DB (PDO) ---------------- */
$pdo = null;
if (!isset($pdo)) {
  $dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
  try {
    $pdo = new PDO($dsn, 'root', '', [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    die("DB connection failed: " . h($e->getMessage()));
  }
}

/* ---------------- Resolve & load item ---------------- */
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  include 'header.php'; include 'sidebar.php';
  echo '<div class="container"><div class="alert alert-error">Invalid item ID.</div></div>';
  include 'footer.php'; exit;
}

$find = $pdo->prepare("SELECT * FROM inventoryitem WHERE ItemID = ? LIMIT 1");
$find->execute([$id]);
$item = $find->fetch();
if (!$item) {
  include 'header.php'; include 'sidebar.php';
  echo '<div class="container"><div class="alert alert-error">Item not found.</div></div>';
  include 'footer.php'; exit;
}

/* ---------------- Load ACTIVE suppliers ---------------- */
$suppliers = [];
try {
  $stmt = $pdo->query("SELECT SupplierID, NAME AS SupplierName FROM supplier WHERE Status='Active' ORDER BY NAME ASC");
  $suppliers = $stmt->fetchAll();
} catch (Throwable $e) { /* silent */ }
$supplierMap = [];
foreach ($suppliers as $s) {
  $supplierMap[(int)$s['SupplierID']] = (string)$s['SupplierName'];
}

/* ---------------- Allowed categories (same as add page) ---------------- */
$allowedCats = ['Unknown','Construction','Steel','Plumbing','Tools','Other'];

/* ---------------- Handle POST (UPDATE) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $errors = [];

  // CSRF
  if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['_csrf'])) {
    $errors[] = "Invalid request. Please refresh and try again.";
  }

  // Fields
  $invoiceId = trim((string)($_POST['InvoiceID'] ?? ''));
  $name      = trim((string)($_POST['NAME'] ?? ''));
  $desc      = trim((string)($_POST['Description'] ?? ''));
  $qty       = (int)($_POST['Quantity'] ?? 0);
  $priceRaw  = (string)($_POST['Price'] ?? '');
  $supplierId = (int)($_POST['SupplierID'] ?? 0);
  $cat       = trim((string)($_POST['Category'] ?? 'Unknown'));
  $rdRaw     = trim((string)($_POST['ReceiveDate'] ?? ''));

  // Validate supplier (must be ACTIVE)
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
  if ($invoiceId === '') {
    $errors[] = "Invoice ID is required.";
  } elseif (!preg_match('/^[A-Za-z0-9\-\/_]{3,32}$/', $invoiceId)) {
    $errors[] = "Invoice ID must be 3–32 characters (letters, numbers, -, /, _).";
  }

  // Name
  if ($name === '') {
    $errors[] = "Item name is required.";
  }

  // Quantity
  if ($qty <= 0) {
    $errors[] = "Quantity must be greater than 0.";
  }

  // Price
  $price = 0.0;
  if ($priceRaw !== '') {
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $priceRaw)) {
      $errors[] = "Price must be a non-negative number (max 2 decimals).";
    } else {
      $price = (float)$priceRaw;
    }
  }

  // Category normalize
  if (!in_array($cat, $allowedCats, true)) {
    $cat = 'Unknown';
  }

  // ReceiveDate — must be current/future with 10-min grace
  if ($rdRaw === '') {
    $errors[] = "Receive date is required.";
  } else {
    try {
      $now   = new DateTime('now', new DateTimeZone('Asia/Colombo'));
      $grace = clone $now; $grace->modify('-10 minutes');
      $rdObj = new DateTime($rdRaw, new DateTimeZone('Asia/Colombo'));
      if ($rdObj < $grace) {
        $errors[] = "Receive date must be current or future (last 10 minutes allowed).";
      }
    } catch (Throwable $e) {
      $errors[] = "Invalid receive date.";
    }
  }

  if (!$errors) {
    try {
      $upd = $pdo->prepare(
        "UPDATE inventoryitem SET
            InvoiceID    = :inv,
            NAME         = :nm,
            Description  = :ds,
            Quantity     = :qt,
            Price        = :pr,
            SupplierID   = :sid,
            SupplierName = :sname,
            Category     = :cat,
            ReceiveDate  = :rd
         WHERE ItemID = :id"
      );
      $ok = $upd->execute([
        ':inv'   => $invoiceId,
        ':nm'    => $name,
        ':ds'    => $desc,
        ':qt'    => $qty,
        ':pr'    => $price,
        ':sid'   => $supplierId,
        ':sname' => $supplierName,
        ':cat'   => $cat,
        ':rd'    => $rdRaw,
        ':id'    => $id,
      ]);

      if ($ok && $upd->rowCount() > 0) {
        flash_push('success', "✅ Item #".(int)$id." updated successfully.");
      } else {
        // same data or nothing changed
        flash_push('error', "No changes were made or nothing to update.");
      }
    } catch (Throwable $e) {
      flash_push('error', "Update failed: " . h($e->getMessage()));
    }

    // PRG
    header("Location: edit_inventory.php?id=".$id);
    exit;
  } else {
    foreach ($errors as $er) flash_push('error', h($er));
    header("Location: edit_inventory.php?id=".$id);
    exit;
  }
}

/* ---------------- Take flashes for display ---------------- */
$flashes = flash_take();

/* ---------------- Page ---------------- */
include 'header.php';
include 'sidebar.php';
?>
<style>
/* === Same Professional, eye-comfort theme used in add_inventory.php === */
:root{
  --brand:#3b5683; --brand-dark:#324a70; --brand-ring:rgba(59,86,131,.25);
  --brand-tint:#e9eff7; --brand-tint-hover:#dde7f6; --border:#dfe6f2;
  --text:#2b3e5a; --muted:#6b7c97;
}

/* Base */
body{background:#ffffff;color:var(--text);margin:0;font-family:'Poppins',sans-serif;}
.container{margin-left:260px;padding:20px;max-width:900px;}
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
textarea{min-height:90px;resize:vertical}

/* Grid */
.grid{display:grid;gap:16px;grid-template-columns:1fr 1fr;}
@media(max-width:900px){.grid{grid-template-columns:1fr;}}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;justify-content:center;
  font-weight:600;border-radius:8px;padding:10px 18px;font-size:14px;
  cursor:pointer;border:1px solid transparent;transition:all .2s;user-select:none;min-width:120px;
}
.btn-primary{background:var(--brand);color:#fff;border-color:var(--brand);}
.btn-primary:hover{filter:brightness(1.05);}
.btn-primary:active{background:var(--brand-dark);}
.btn-primary:focus-visible{outline:none;box-shadow:0 0 0 3px var(--brand-ring);}
.btn-ghost{background:#fff;border:1px solid var(--border);color:var(--text);}
.btn-ghost:hover{background:var(--brand-tint);}
.btn-danger{background:#dc2626;color:#fff;}
.btn-danger:hover{background:#b91c1c;}

/* Alerts */
.alert{padding:12px 14px;border-radius:8px;margin:10px 0;font-size:14px}
.alert-success{background:#ecf5ff;color:var(--brand);border:1px solid var(--border);}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fde2e2;}
.alert-info{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}

/* Footer note */
.footer-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:8px;flex-wrap:wrap}
.small-note{font-size:12px;color:var(--muted);margin-top:2px}
</style>

<div class="container">
  <form method="post" autocomplete="off" novalidate>
    <input type="hidden" name="_csrf" value="<?php echo h($CSRF); ?>">

    <div class="card">
      <h1>Edit Inventory Item #<?php echo (int)$item['ItemID']; ?></h1>
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
                  <?= ((int)$item['SupplierID'] === (int)$s['SupplierID']) ? 'selected' : '' ?>
                ><?= h($s['SupplierName']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="small-note">Only ACTIVE suppliers are shown.</div>
          </div>

          <div>
            <div class="label">Supplier Name (auto)</div>
            <input type="text" id="SupplierName" class="input" readonly
                   value="<?= h($supplierMap[(int)$item['SupplierID']] ?? $item['SupplierName'] ?? '') ?>"
                   placeholder="Auto-filled">
          </div>

          <div>
            <div class="label">Invoice ID</div>
            <input type="text"
                   name="InvoiceID"
                   id="InvoiceID"
                   class="input"
                   placeholder="e.g., IN2025-00123"
                   minlength="3" maxlength="32"
                   pattern="[A-Za-z0-9\-/_]{3,32}"
                   value="<?= h($item['InvoiceID']) ?>"
                   required
                   title="3–32 chars: letters, numbers, -, /, _">
          </div>

          <div>
            <div class="label">Item Name</div>
            <input type="text" name="NAME" class="input" required
                   value="<?= h($item['NAME']) ?>" placeholder="Item name">
          </div>

          <div style="grid-column:1/-1">
            <div class="label">Description</div>
            <textarea name="Description" class="input" placeholder="Optional"><?= h($item['Description']) ?></textarea>
          </div>

          <div>
            <div class="label">Quantity</div>
            <input type="number" name="Quantity" class="input" min="1" step="1" required
                   value="<?= (int)$item['Quantity'] ?>">
          </div>

          <div>
            <div class="label">Price (LKR)</div>
            <input type="number" name="Price" class="input" min="0" step="0.01"
                   value="<?= number_format((float)$item['Price'], 2, '.', '') ?>">
          </div>

          <div>
            <div class="label">Category</div>
            <select name="Category" class="input" required>
              <?php foreach ($allowedCats as $opt): ?>
                <option value="<?= h($opt) ?>" <?= ($item['Category'] === $opt ? 'selected' : '') ?>>
                  <?= h($opt) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <div class="label">Receive Date</div>
            <input type="datetime-local" name="ReceiveDate" class="input" id="ReceiveDate"
                   value="<?= $item['ReceiveDate'] ? date('Y-m-d\TH:i', strtotime($item['ReceiveDate'])) : '' ?>"
                   required>
            <div class="small-note">Must be current or future (10-minute grace allowed).</div>
          </div>
        </div>

        <div class="footer-actions">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <a href="inventory.php" class="btn btn-ghost">Cancel</a>
        </div>

      </div>
    </div>
  </form>
</div>

<script>
/* --- Match add_inventory.js behaviours --- */
const supplierSelect = document.getElementById('SupplierID');
const supplierNameEl = document.getElementById('SupplierName');
const receiveDateEl  = document.getElementById('ReceiveDate');

function nowLocalForMin(){
  const d=new Date(); d.setSeconds(0,0);
  const p=n=>String(n).padStart(2,'0');
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

// Set min for datetime-local (client hint; server also validates)
if (receiveDateEl) {
  receiveDateEl.min = nowLocalForMin();
}

if (supplierSelect) {
  supplierSelect.addEventListener('change', ()=>{
    supplierNameEl.value = supplierSelect.options[supplierSelect.selectedIndex]?.text || '';
  });
}
</script>

<?php include 'footer.php'; ?>
