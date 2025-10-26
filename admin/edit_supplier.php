<?php
// edit_supplier.php (FULL UPDATED — 10-digit phone + strict email)
// Secure headers, CSRF, PDO, PRG flash, sticky form, validation, duplicate guard (excluding self)

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
function posted(string $k, ?string $fallback = null): string {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : (string)($fallback ?? '');
}
function safeDate(?string $ts): string {
    if (!$ts) return '-';
    $t = strtotime($ts);
    return $t ? date('Y-m-d H:i', $t) : '-';
}

// ---- Get ID ----
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die("Invalid Supplier ID.");
}

// ---- Load record ----
$stmt = $pdo->prepare("SELECT * FROM supplier WHERE SupplierID = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();
if (!$record) {
    http_response_code(404);
    die("Supplier not found.");
}

// ---- PRG flash ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- POST: Update Supplier ----
$errors = [];
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_csrf']) &&
    hash_equals($CSRF, (string)$_POST['_csrf'])
) {
    // Collect (raw)
    $name         = posted('NAME', $record['NAME']);
    $contactRaw   = posted('Contact', (string)$record['Contact']);
    $address      = posted('Address', (string)$record['Address']);
    $email        = posted('Email', (string)$record['Email']);
    $companyName  = posted('CompanyName', (string)$record['CompanyName']);
    $category     = posted('Category', (string)$record['Category']);
    $status       = posted('Status', (string)($record['Status'] ?? 'Active'));
    $paymentTerms = posted('PaymentTerms', (string)$record['PaymentTerms']);
    $gstNumber    = posted('GSTNumber', (string)$record['GSTNumber']);
    $notes        = posted('Notes', (string)$record['Notes']);

    // Normalize contact: keep digits only (defense-in-depth)
    $contact = preg_replace('/\D+/', '', $contactRaw ?? '');

    // ---- VALIDATION ----
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = "Supplier Name is required (max 100).";
    }

    // Phone must be exactly 10 digits and start with 0
    if ($contact !== '') {
        if (!preg_match('/^0\d{9}$/', $contact)) {
            $errors[] = "Contact must be exactly 10 digits, starting with 0 (e.g., 0712345678).";
        }
    }

    // Email strict: filter_var + whitelist regex
    if ($email !== '') {
        if (mb_strlen($email) > 100) {
            $errors[] = "Email must be ≤ 100 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email format is invalid.";
        } elseif (!preg_match('/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $email)) {
            $errors[] = "Email contains invalid characters.";
        }
    }

    if (mb_strlen($companyName) > 150)  $errors[] = "Company name must be ≤ 150 characters.";
    if (mb_strlen($category) > 100)     $errors[] = "Category must be ≤ 100 characters.";
    if (!in_array($status, ['Active','Inactive'], true)) $errors[] = "Invalid status.";
    if (mb_strlen($paymentTerms) > 100) $errors[] = "Payment terms must be ≤ 100 characters.";
    if (mb_strlen($gstNumber) > 50)     $errors[] = "GST/VAT number must be ≤ 50 characters.";
    if (mb_strlen($address) > 255)      $errors[] = "Address must be ≤ 255 characters.";

    // Duplicate guard, excluding current supplier:
    // Clash if another row has (NAME+Contact) OR (CompanyName+GSTNumber)
    if (!$errors) {
        $dupSql = "
            SELECT SupplierID FROM supplier
            WHERE SupplierID <> :id
              AND (
                    (NAME = :n AND (:c = '' OR Contact = :c))
                 OR ((:cn <> '' AND CompanyName = :cn) AND (:gst <> '' AND GSTNumber = :gst))
              )
            LIMIT 1
        ";
        $dup = $pdo->prepare($dupSql);
        $dup->execute([
            ':id'  => $id,
            ':n'   => $name,
            ':c'   => $contact,
            ':cn'  => $companyName,
            ':gst' => $gstNumber,
        ]);
        $dupRow = $dup->fetch();
        if ($dupRow) {
            $errors[] = "Another supplier already uses the same Name+Contact or Company+GST.";
        }
    }

    // ---- UPDATE ----
    if (!$errors) {
        $sql = "UPDATE supplier
                SET NAME=:NAME, Contact=:Contact, Address=:Address, Email=:Email,
                    CompanyName=:CompanyName, Category=:Category, Status=:Status,
                    PaymentTerms=:PaymentTerms, GSTNumber=:GSTNumber, Notes=:Notes
                WHERE SupplierID = :id
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([
                ':NAME'         => $name,
                ':Contact'      => $contact !== '' ? $contact : null,
                ':Address'      => $address !== '' ? $address : null,
                ':Email'        => $email   !== '' ? mb_strtolower($email) : null,
                ':CompanyName'  => $companyName !== '' ? $companyName : null,
                ':Category'     => $category !== '' ? $category : null,
                ':Status'       => $status,
                ':PaymentTerms' => $paymentTerms !== '' ? $paymentTerms : null,
                ':GSTNumber'    => $gstNumber !== '' ? $gstNumber : null,
                ':Notes'        => $notes !== '' ? $notes : null,
                ':id'           => $id,
            ]);
            $_SESSION['flash'] = ['ok'=>true, 'msg'=>"Supplier #{$id} updated successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok'=>false, 'msg'=>"Update failed: ".e($e->getMessage())];
        }
        // PRG to same page to reflect DB values cleanly
        header("Location: edit_supplier.php?id=".$id);
        exit();
    } else {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>implode('<br>', array_map('e', $errors))];
        header("Location: edit_supplier.php?id=".$id);
        exit();
    }
}

// Refresh record after possible PRG (so latest values show)
$stmt = $pdo->prepare("SELECT * FROM supplier WHERE SupplierID = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business style consistent with your other pages ---- */
body{background:#f4f6f9;color:#1e293b;margin:0;}
.container{margin-left:260px;padding:20px;max-width:900px;}
@media(max-width:992px){.container{margin-left:0;padding:16px;}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:24px;border:1px solid #e5e7eb;}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid #e5e7eb;margin:0;}

.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px;flex-wrap:wrap}
.headerbar a{text-decoration:none}

.meta{padding:0 20px 8px 20px;color:#64748b;font-size:12px}

.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

.form-wrap{padding:16px 20px 22px 20px;}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

.label{font-weight:600;font-size:14px;margin-bottom:6px;color:#334155}
.input,select,textarea{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;}
.input:focus,select:focus,textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.2);}
.hint{font-size:12px;color:#64748b;margin-top:6px}
.req{color:#dc2626}
.inline-error{color:#dc2626;font-size:12px;margin-top:6px;display:none}

.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
.btn{padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid #cbd5e1;background:#fff;color:#334155;}
.btn:hover{background:#f1f5f9}
.btn-primary{background:#2563eb;color:#fff;border:none;}
.btn-primary:hover{background:#1d4ed8}
.btn-outline{background:#fff;border-color:#cbd5e1}
</style>

<div class="container">
  <div class="card">
    <h1>Edit Supplier</h1>

    <div class="headerbar">
      <div class="hint">Update supplier details. Fields marked <span class="req">*</span> are required.</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="supplier.php">← Back to Suppliers</a>
        <a class="btn btn-outline" href="add_supplier.php">+ New Supplier</a>
      </div>
    </div>

    <div class="meta">
      Created: <?= e(safeDate($record['CreatedAt'] ?? null)) ?> &nbsp;•&nbsp;
      Last updated: <?= e(safeDate($record['UpdatedAt'] ?? null)) ?> &nbsp;•&nbsp;
      ID: #<?= (int)$record['SupplierID'] ?>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= e($CSRF) ?>">

      <div class="grid">
        <div>
          <div class="label">Supplier Name <span class="req">*</span></div>
          <input class="input" type="text" name="NAME" maxlength="100" required
                 value="<?= e($_POST ? posted('NAME') : (string)$record['NAME']) ?>"
                 placeholder="e.g., Janaka Traders">
          <div class="hint">Legal or trading name (max 100).</div>
        </div>

        <div>
          <div class="label">Company Name</div>
          <input class="input" type="text" name="CompanyName" maxlength="150"
                 value="<?= e($_POST ? posted('CompanyName') : (string)$record['CompanyName']) ?>"
                 placeholder="e.g., Janaka Holdings (Pvt) Ltd">
        </div>

        <div>
          <div class="label">Contact Number</div>
          <input class="input" type="text" name="Contact" maxlength="10"
                 value="<?= e($_POST ? preg_replace('/\D+/', '', posted('Contact')) : preg_replace('/\D+/', '', (string)$record['Contact'])) ?>"
                 placeholder="0712345678"
                 inputmode="numeric"
                 pattern="^0\d{9}$">
          <div id="contactErr" class="inline-error">Invalid contact number.</div>
          <div class="hint">Valid: exactly 10 digits, starting with 0 (e.g., 0712345678).</div>
        </div>

        <div>
          <div class="label">Email</div>
          <input class="input" type="email" name="Email" maxlength="100"
                 value="<?= e($_POST ? posted('Email') : (string)$record['Email']) ?>"
                 placeholder="name@company.com">
          <div id="emailErr" class="inline-error">Invalid email.</div>
        </div>

        <div>
          <div class="label">Category</div>
          <input class="input" type="text" name="Category" maxlength="100"
                 value="<?= e($_POST ? posted('Category') : (string)$record['Category']) ?>"
                 list="catlist" placeholder="e.g., Construction">
          <datalist id="catlist">
            <option value="Construction">
            <option value="Steel">
            <option value="Plumbing">
            <option value="Electrical">
            <option value="Other">
          </datalist>
        </div>

        <div>
          <div class="label">GST / VAT Number</div>
          <input class="input" type="text" name="GSTNumber" maxlength="50"
                 value="<?= e($_POST ? posted('GSTNumber') : (string)$record['GSTNumber']) ?>"
                 placeholder="e.g., 123456789">
        </div>

        <div>
          <div class="label">Payment Terms</div>
          <input class="input" type="text" name="PaymentTerms" maxlength="100"
                 value="<?= e($_POST ? posted('PaymentTerms') : (string)$record['PaymentTerms']) ?>"
                 placeholder="e.g., Net 30 / only 6 months">
        </div>

        <div>
          <div class="label">Status <span class="req">*</span></div>
          <?php $curStatus = $_POST ? posted('Status', 'Active') : ((string)($record['Status'] ?? 'Active')); ?>
          <select class="input" name="Status" required>
            <option value="Active"   <?= $curStatus==='Active'?'selected':''; ?>>Active</option>
            <option value="Inactive" <?= $curStatus==='Inactive'?'selected':''; ?>>Inactive</option>
          </select>
        </div>

        <div style="grid-column:1/-1">
          <div class="label">Address</div>
          <textarea class="input" name="Address" rows="2" maxlength="255" placeholder="Street, City"><?= e($_POST ? posted('Address') : (string)$record['Address']) ?></textarea>
        </div>

        <div style="grid-column:1/-1">
          <div class="label">Notes</div>
          <textarea class="input" name="Notes" rows="3" placeholder="Optional notes (lead times, quality remarks, etc.)"><?= e($_POST ? posted('Notes') : (string)$record['Notes']) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Changes</button>
        <a class="btn" href="supplier.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
// Contact: force digits only and exactly 10, starting with 0
const contactInput = document.querySelector('[name="Contact"]');
const contactErr   = document.getElementById('contactErr');
contactInput?.addEventListener('input', () => {
  let v = contactInput.value.replace(/\D/g, ''); // keep digits only
  if (v.length > 10) v = v.slice(0, 10);
  contactInput.value = v;

  const re = /^0\d{9}$/; // exactly 10 digits, starting with 0
  if (v && !re.test(v)) {
    contactInput.setCustomValidity('Invalid contact');
    contactErr.style.display = 'block';
  } else {
    contactInput.setCustomValidity('');
    contactErr.style.display = 'none';
  }
});

// Email guard (UX). Server does strict validation again.
const emailInput = document.querySelector('[name="Email"]');
const emailErr   = document.getElementById('emailErr');
emailInput?.addEventListener('input', () => {
  const v = emailInput.value.trim();
  const re = /^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i;
  if (v && !re.test(v)) {
    emailInput.setCustomValidity('Invalid email');
    emailErr.style.display = 'block';
  } else {
    emailInput.setCustomValidity('');
    emailErr.style.display = 'none';
  }
});

// Required guard
document.querySelector('form')?.addEventListener('submit', (e) => {
  const req = ['NAME','Status'];
  for (const n of req) {
    const el = document.querySelector(`[name="${n}"]`);
    if (!el || !el.value.trim()) {
      e.preventDefault();
      alert('Please fill all required fields.');
      return;
    }
  }
});

// Save button tactile feel
const saveBtn = document.getElementById('saveBtn');
saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
</script>

<?php include 'footer.php'; ?>
