<?php
// add_supplier.php (FULL UPDATED — 10-digit local phone only)
// Uses PDO, CSRF, PRG flash, strict Contact/Email validation, duplicate guard
// Contact Number must be exactly 10 digits, starting with 0 (e.g., 0712345678)

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

$errors = [];

// ---- POST: Add Supplier ----
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_csrf']) &&
    hash_equals($CSRF, (string)$_POST['_csrf'])
) {
    // Collect & trim
    $name         = trim((string)($_POST['NAME'] ?? ''));          // required
    $contactRaw   = trim((string)($_POST['Contact'] ?? ''));
    $address      = trim((string)($_POST['Address'] ?? ''));
    $email        = trim((string)($_POST['Email'] ?? ''));
    $companyName  = trim((string)($_POST['CompanyName'] ?? ''));
    $category     = trim((string)($_POST['Category'] ?? ''));
    $status       = trim((string)($_POST['Status'] ?? 'Active'));  // enum Active/Inactive
    $paymentTerms = trim((string)($_POST['PaymentTerms'] ?? ''));
    $gstNumber    = trim((string)($_POST['GSTNumber'] ?? ''));
    $notes        = trim((string)($_POST['Notes'] ?? ''));

    // --- Normalize contact: keep digits only (defense-in-depth) ---
    $contact = preg_replace('/\D+/', '', $contactRaw ?? '');

    // ---- VALIDATION ----
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = "Supplier Name is required (max 100).";
    }

    // Contact must be exactly 10 digits and start with 0 -> ^0\d{9}$
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

    // Duplicate check: (NAME + Contact) OR (CompanyName + GSTNumber) if both parts exist
    if (!$errors) {
        $stmt = $pdo->prepare("
            SELECT SupplierID FROM supplier
            WHERE
              (NAME = :n AND (:c = '' OR Contact = :c))
              OR
              ((:cn <> '' AND CompanyName = :cn) AND (:gst <> '' AND GSTNumber = :gst))
            LIMIT 1
        ");
        $stmt->execute([
            ':n'   => $name,
            ':c'   => $contact,
            ':cn'  => $companyName,
            ':gst' => $gstNumber,
        ]);
        if ($stmt->fetch()) {
            $errors[] = "A supplier with same Name+Contact or Company+GST already exists.";
        }
    }

    // ---- INSERT ----
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO supplier
                  (NAME, Contact, Address, Email, CompanyName, Category, Status, PaymentTerms, GSTNumber, Notes)
                VALUES
                  (:NAME, :Contact, :Address, :Email, :CompanyName, :Category, :Status, :PaymentTerms, :GSTNumber, :Notes)
            ");
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
            ]);
            $_SESSION['flash'] = ['ok' => true, 'msg' => "Supplier “".htmlspecialchars($name)."” added successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Insert failed: " . htmlspecialchars($e->getMessage())];
        }
        // PRG
        header("Location: add_supplier.php");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: add_supplier.php");
        exit();
    }
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business style — brand blue (#3b5683) ---- */
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

/* Base */
body{background:#ffffff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:900px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{
  background:#fff;border-radius:14px;
  box-shadow:0 4px 12px rgba(34,54,82,.08);
  border:1px solid var(--border);
  margin-bottom:24px;overflow:hidden;color:var(--text);
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;margin:0;
  border-bottom:1px solid var(--border);color:var(--text);
}

/* Header bar */
.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px}
.headerbar a{text-decoration:none;color:var(--brand);}
.headerbar a:hover{text-decoration:underline;}

/* Alerts */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Form wrapper */
.form-wrap{padding:16px 20px 22px 20px;}

/* Grid */
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

/* Inputs */
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted)}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;
  font-size:14px;background:#fff;color:var(--text);transition:border .2s, box-shadow .2s, background .2s, color .2s;
}
.input:focus,select:focus,textarea:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);
}
.hint{font-size:12px;color:var(--muted);margin-top:6px}
.req{color:#dc2626}
.inline-error{color:#dc2626;font-size:12px;margin-top:6px;display:none}

/* Actions / Buttons */
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{
  padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid var(--border);background:#fff;color:var(--text);
  transition:background .2s, color .2s, box-shadow .2s, filter .2s;
}
.btn:hover{background:var(--tint-hover);}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring);}

/* Primary (brand) */
.btn-primary{background:var(--brand);color:#fff;border-color:var(--brand);}
.btn-primary:hover{background:var(--brand);filter:brightness(1.05);}
.btn-primary:active{background:var(--brand-dark);}
.btn-primary:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring);}
</style>


<div class="container">
  <div class="card">
    <h1>Add Supplier</h1>

    <div class="headerbar">
      <div class="hint">Create a new supplier record for RB Stores.</div>
      <a class="btn" href="supplier.php">View Suppliers</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

      <div class="grid">
        <div>
          <div class="label">Supplier Name <span class="req">*</span></div>
          <input class="input" type="text" name="NAME" maxlength="100" required
                 placeholder="e.g., Janaka Traders"
                 value="<?= htmlspecialchars((string)($_POST['NAME'] ?? '')) ?>">
          <div class="hint">Legal or trading name (max 100).</div>
        </div>

        <div>
          <div class="label">Company Name</div>
          <input class="input" type="text" name="CompanyName" maxlength="150"
                 placeholder="e.g., Janaka Holdings (Pvt) Ltd"
                 value="<?= htmlspecialchars((string)($_POST['CompanyName'] ?? '')) ?>">
          <div class="hint">Optional.</div>
        </div>

        <div>
          <div class="label">Contact Number</div>
          <input class="input" type="text" name="Contact" maxlength="10"
                 placeholder="0712345678"
                 pattern="^0\d{9}$"
                 inputmode="numeric"
                 value="<?= htmlspecialchars((string)($_POST['Contact'] ?? '')) ?>">
          <div class="hint">Valid: exactly 10 digits, starting with 0 (e.g., 0712345678).</div>
          <div id="contactErr" class="inline-error">Invalid contact number.</div>
        </div>

        <div>
          <div class="label">Email</div>
          <input class="input" type="email" name="Email" maxlength="100"
                 placeholder="name@company.com"
                 value="<?= htmlspecialchars((string)($_POST['Email'] ?? '')) ?>">
          <div class="hint">Optional.</div>
          <div id="emailErr" class="inline-error">Invalid email.</div>
        </div>

        <div>
          <div class="label">Category</div>
          <input class="input" type="text" name="Category" maxlength="100"
                 list="catlist" placeholder="e.g., Construction"
                 value="<?= htmlspecialchars((string)($_POST['Category'] ?? '')) ?>">
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
                 placeholder="e.g., 123456789"
                 value="<?= htmlspecialchars((string)($_POST['GSTNumber'] ?? '')) ?>">
          <div class="hint">Optional.</div>
        </div>

        <div>
          <div class="label">Payment Terms</div>
          <input class="input" type="text" name="PaymentTerms" maxlength="100"
                 placeholder="e.g., Net 30 / only 6 months"
                 value="<?= htmlspecialchars((string)($_POST['PaymentTerms'] ?? '')) ?>">
          <div class="hint">Optional.</div>
        </div>

        <div>
          <div class="label">Status <span class="req">*</span></div>
          <select class="input" name="Status" required>
            <?php $cur = (string)($_POST['Status'] ?? 'Active'); ?>
            <option value="Active"   <?= $cur==='Active'?'selected':''; ?>>Active</option>
            <option value="Inactive" <?= $cur==='Inactive'?'selected':''; ?>>Inactive</option>
          </select>
        </div>

        <div style="grid-column:1/-1">
          <div class="label">Address</div>
          <textarea class="input" name="Address" rows="2" maxlength="255" placeholder="Street, City"><?= htmlspecialchars((string)($_POST['Address'] ?? '')) ?></textarea>
        </div>

        <div style="grid-column:1/-1">
          <div class="label">Notes</div>
          <textarea class="input" name="Notes" rows="3" placeholder="Optional notes (lead times, quality remarks, etc.)"
          ><?= htmlspecialchars((string)($_POST['Notes'] ?? '')) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Supplier</button>
        <button class="btn" type="reset" id="resetBtn">Clear</button>
      </div>
    </form>
  </div>
</div>

<script>
// Contact: force digits only and exactly 10, starting with 0
const contactInput = document.querySelector('[name="Contact"]');
const contactErr   = document.getElementById('contactErr');
contactInput?.addEventListener('input', () => {
  // Keep digits only as user types
  let v = contactInput.value.replace(/\D/g, '');
  // Limit to max 10 digits
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

// Email guard: simple UX check; server still validates strictly
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

// Required fields guard
document.querySelector('form')?.addEventListener('submit', (e) => {
  const required = ['NAME','Status'];
  for (const n of required) {
    const el = document.querySelector(`[name="${n}"]`);
    if (!el || !el.value.trim()) {
      e.preventDefault();
      alert('Please fill all required fields.');
      return;
    }
  }
});

// Save button tactile feedback
const saveBtn = document.getElementById('saveBtn');
saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
</script>

<?php include 'footer.php'; ?>
