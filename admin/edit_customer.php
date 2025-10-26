<?php
// edit_customer.php (FULL UPDATED)
// White business card style, mirrors add_customer/customer list pages
// Uses PDO, CSRF, PRG + flash, strict validation, unique email on update
// Delete action routes to customer.php (which handles secure delete)

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

// ---- Get ID ----
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customerId <= 0) {
    http_response_code(400);
    die("Invalid ID.");
}

// ---- Fetch current record ----
$st = $pdo->prepare("SELECT CustomerID, NAME, Email, Phone, Address FROM customer WHERE CustomerID = ? LIMIT 1");
$st->execute([$customerId]);
$cur = $st->fetch();
if (!$cur) {
    http_response_code(404);
    die("Customer not found.");
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$errors = [];

// ---- POST: Update Customer ----
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

    if ($phone === '') {
        $errors[] = "Phone is required.";
    } elseif (mb_strlen($phone) > 15) {
        $errors[] = "Phone must be ≤ 15 characters (matching database limit).";
    } elseif (!preg_match('/^(0\d{9}|\+94\d{9}|[0-9]{7,15})$/', $phone)) {
        $errors[] = "Phone must be 0XXXXXXXXX, +94XXXXXXXXX, or 7–15 digits.";
    }

    if ($address === '' || mb_strlen($address) > 255) {
        $errors[] = "Address is required (max 255).";
    }

    // Unique Email (exclude current record)
    if (!$errors) {
        $s = $pdo->prepare("SELECT CustomerID FROM customer WHERE Email = ? AND CustomerID <> ? LIMIT 1");
        $s->execute([$email, $customerId]);
        if ($s->fetch()) {
            $errors[] = "Another customer with this Email already exists.";
        }
    }

    // Optional: block duplicate Phone (exclude current)
    if (!$errors) {
        $s = $pdo->prepare("SELECT CustomerID, NAME FROM customer WHERE Phone = ? AND CustomerID <> ? LIMIT 1");
        $s->execute([$phone, $customerId]);
        if ($match = $s->fetch()) {
            $errors[] = "Another customer already uses this Phone (".h((string)$match['NAME']).").";
        }
    }

    // ---- UPDATE ----
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                UPDATE customer
                SET NAME = :NAME,
                    Email = :Email,
                    Phone = :Phone,
                    Address = :Address
                WHERE CustomerID = :id
            ");
            $stmt->execute([
                ':NAME'    => $name,
                ':Email'   => $email,
                ':Phone'   => $phone,
                ':Address' => $address,
                ':id'      => $customerId,
            ]);
            $_SESSION['flash'] = ['ok' => true, 'msg' => "Customer “".h($name)."” updated successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Update failed: " . htmlspecialchars($e->getMessage())];
        }
        // PRG
        header("Location: edit_customer.php?id=".$customerId);
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: edit_customer.php?id=".$customerId);
        exit();
    }
}

// ---- Values for the form (from DB) ----
$val = [
    'NAME'    => $cur['NAME'] ?? '',
    'Email'   => $cur['Email'] ?? '',
    'Phone'   => $cur['Phone'] ?? '',
    'Address' => $cur['Address'] ?? '',
];

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

.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid #cbd5e1;background:#fff;color:#334155;}
.btn:hover{background:#f1f5f9}
.btn-primary{background:#2563eb;color:#fff;border:none;}
.btn-primary:hover{background:#1d4ed8}
.btn-danger{background:#dc2626;color:#fff;border:none;}
.btn-danger:hover{background:#b91c1c}
</style>

<div class="container">
  <div class="card">
    <h1>Edit Customer #<?= (int)$customerId ?></h1>

    <div class="headerbar">
      <div class="hint">Update customer contact details.</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="customer.php">Back to Customers</a>
        <a class="btn" href="add_customer.php">+ Add New</a>
      </div>
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
                 value="<?= h($val['NAME']) ?>">
          <div class="hint">Full name (max 100).</div>
        </div>

        <!-- Email -->
        <div>
          <div class="label">Email <span class="req">*</span></div>
          <input class="input" type="email" name="Email" maxlength="100" required
                 value="<?= h($val['Email']) ?>">
          <div class="hint">Must be unique.</div>
        </div>

        <!-- Phone -->
        <div>
          <div class="label">Phone <span class="req">*</span></div>
          <input class="input" type="text" name="Phone" maxlength="15" required
                 placeholder="0XXXXXXXXX / +94XXXXXXXXX"
                 pattern="^(0\d{9}|\+94\d{9}|[0-9]{7,15})$"
                 value="<?= h($val['Phone']) ?>">
          <div class="hint">Valid: 0712345678, +94712345678, or 7–15 digits.</div>
        </div>

        <!-- Address -->
        <div style="grid-column:1/-1">
          <div class="label">Address <span class="req">*</span></div>
          <textarea class="input" name="Address" rows="3" maxlength="255" required><?= h($val['Address']) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Changes</button>
        <a class="btn" href="customer.php">Cancel</a>

        <!-- Delete routes to customer.php (handles CSRF + delete) -->
        <form method="post" action="customer.php" style="display:inline" onsubmit="return confirm('Delete customer #<?= (int)$customerId ?>? This may fail if they have related orders/feedback.');">
          <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="CustomerID" value="<?= (int)$customerId ?>">
          <button class="btn btn-danger" type="submit">Delete</button>
        </form>
      </div>
    </form>
  </div>
</div>

<script>
// Client guards similar to add_customer
(function(){
  const emailInput = document.querySelector('[name="Email"]');
  emailInput?.addEventListener('input', () => {
    const v = emailInput.value.trim();
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    emailInput.setCustomValidity(ok ? '' : 'Invalid email');
  });

  const phoneInput = document.querySelector('[name="Phone"]');
  phoneInput?.addEventListener('input', () => {
    const v = phoneInput.value.trim();
    const ok = /^(0\d{9}|\+94\d{9}|[0-9]{7,15})$/.test(v) && v.length <= 15;
    phoneInput.setCustomValidity(ok ? '' : 'Invalid phone');
  });

  const saveBtn = document.getElementById('saveBtn');
  saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
  saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
})();
</script>

<?php include 'footer.php'; ?>
