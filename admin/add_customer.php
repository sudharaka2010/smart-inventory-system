<?php
// add_customer.php (FULL UPDATED)
// White business card style, mirrors your other pages (add_supplier/add_vehicle)
// Uses PDO, CSRF, PRG + flash, strict validation, duplicate guard on Email
// Phone validation: either 0XXXXXXXXX (10 digits) OR +94XXXXXXXXX (12 chars total)

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

// Helpers
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function notEmpty($v): bool { return isset($v) && trim((string)$v) !== ''; }

$errors = [];
$phoneWarn = null;

// ---- POST: Add Customer ----
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_csrf']) &&
    hash_equals($CSRF, (string)$_POST['_csrf'])
) {
    // Collect & trim
    $name    = trim((string)($_POST['NAME'] ?? ''));
    $email   = trim((string)($_POST['Email'] ?? ''));
    $phone   = trim((string)($_POST['Phone'] ?? ''));
    $address = trim((string)($_POST['Address'] ?? ''));

    // ---- VALIDATION ----
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = "Customer Name is required (max 100).";
    }

    if ($email === '') {
        $errors[] = "Email is required.";
    } elseif (mb_strlen($email) > 100) {
        $errors[] = "Email must be ≤ 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email format is invalid.";
    }

    // Phone: only two valid forms: 0XXXXXXXXX (10) OR +94XXXXXXXXX (12)
    // Regex enforces both pattern and length
    $phonePattern = '/^(0\d{9}|\+94\d{9})$/';
    if ($phone === '') {
        $errors[] = "Phone is required.";
    } elseif (!preg_match($phonePattern, $phone)) {
        $errors[] = "Phone must be either 0XXXXXXXXX (10 digits) or +94XXXXXXXXX (12 chars).";
    }

    if ($address === '' || mb_strlen($address) > 255) {
        $errors[] = "Address is required (max 255).";
    }

    // Duplicate guard: unique Email (hard). Phone: soft warning only.
    if (!$errors) {
        // Email unique
        $s = $pdo->prepare("SELECT CustomerID FROM customer WHERE Email = ? LIMIT 1");
        $s->execute([$email]);
        if ($s->fetch()) {
            $errors[] = "A customer with this Email already exists.";
        }

        // Soft phone check (does NOT block)
        if (!$errors) {
            $s = $pdo->prepare("SELECT CustomerID, NAME FROM customer WHERE Phone = ? LIMIT 1");
            $s->execute([$phone]);
            if ($match = $s->fetch()) {
                $phoneWarn = "Note: This phone number already exists under “".(string)$match['NAME']."”.";
            }
        }
    }

    // ---- INSERT ----
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO customer (NAME, Email, Phone, Address)
                VALUES (:NAME, :Email, :Phone, :Address)
            ");
            $stmt->execute([
                ':NAME'    => $name,
                ':Email'   => $email,
                ':Phone'   => $phone,
                ':Address' => $address,
            ]);

            $okMsg = "Customer “".h($name)."” added successfully.";
            if ($phoneWarn) {
                $okMsg .= " <br><small>".h($phoneWarn)."</small>";
            }
            $_SESSION['flash'] = ['ok' => true, 'msg' => $okMsg];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Insert failed: " . htmlspecialchars($e->getMessage())];
        }
        // PRG
        header("Location: add_customer.php");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: add_customer.php");
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
/* ---- White, business style (same structure as your sample pages) ---- */
body{background:#f4f6f9;color:#1e293b;margin:0;}
.container{margin-left:260px;padding:20px;max-width:900px;}
@media(max-width:992px){.container{margin-left:0;}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:24px;border:1px solid #e5e7eb}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid #e5e7eb;margin:0;}

.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px}
.headerbar a{text-decoration:none}

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

.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid #cbd5e1;background:#fff;color:#334155;}
.btn:hover{background:#f1f5f9}
.btn-primary{background:#2563eb;color:#fff;border:none;}
.btn-primary:hover{background:#1d4ed8}
</style>

<div class="container">
  <div class="card">
    <h1>Add Customer</h1>

    <div class="headerbar">
      <div class="hint">Create a new customer record for RB Stores.</div>
      <a class="btn" href="customer.php">View Customers</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">

      <div class="grid">
        <!-- Name -->
        <div>
          <div class="label">Customer Name <span class="req">*</span></div>
          <input class="input" type="text" name="NAME" maxlength="100" required
                 placeholder="e.g., Kasun Perera"
                 value="<?= h((string)($_POST['NAME'] ?? '')) ?>">
          <div class="hint">Full name (max 100).</div>
        </div>

        <!-- Email -->
        <div>
          <div class="label">Email <span class="req">*</span></div>
          <input class="input" type="email" name="Email" maxlength="100" required
                 placeholder="name@example.com"
                 value="<?= h((string)($_POST['Email'] ?? '')) ?>">
          <div class="hint">Must be unique.</div>
          <div id="emailErr" class="inline-error">Invalid or duplicate email.</div>
        </div>

        <!-- Phone -->
        <div>
          <div class="label">Phone <span class="req">*</span></div>
          <input
            class="input"
            type="text"
            name="Phone"
            inputmode="tel"
            required
            maxlength="12"
            placeholder="0XXXXXXXXX or +94XXXXXXXXX"
            pattern="^(0\d{9}|\+94\d{9})$"
            value="<?= h((string)($_POST['Phone'] ?? '')) ?>">
          <div class="hint">Allowed: <b>0XXXXXXXXX</b> (10 digits) or <b>+94XXXXXXXXX</b> (12 chars). No other formats.</div>
          <div id="phoneErr" class="inline-error">Phone must be 0XXXXXXXXX (10) or +94XXXXXXXXX (12).</div>
        </div>

        <!-- Address -->
        <div style="grid-column:1/-1">
          <div class="label">Address <span class="req">*</span></div>
          <textarea class="input" name="Address" rows="3" maxlength="255" required
                    placeholder="Street, City, District"><?= h((string)($_POST['Address'] ?? '')) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Customer</button>
        <button class="btn" type="reset" id="resetBtn">Clear</button>
      </div>
    </form>
  </div>
</div>

<script>
// Client guards: email + phone format
(function(){
  const emailInput = document.querySelector('[name="Email"]');
  const emailErr   = document.getElementById('emailErr');
  emailInput?.addEventListener('input', () => {
    const v = emailInput.value.trim();
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    emailInput.setCustomValidity(ok ? '' : 'Invalid email');
    emailErr.style.display = ok ? 'none' : 'block';
  });

  const phoneInput = document.querySelector('[name="Phone"]');
  const phoneErr   = document.getElementById('phoneErr');
  phoneInput?.addEventListener('input', () => {
    const v = phoneInput.value.trim();
    const ok = /^(0\d{9}|\+94\d{9})$/.test(v);
    phoneInput.setCustomValidity(ok ? '' : 'Invalid phone');
    phoneErr.style.display = ok ? 'none' : 'block';
  });

  // Save button tactile feedback
  const saveBtn = document.getElementById('saveBtn');
  saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
  saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
})();
</script>

<?php include 'footer.php'; ?>
