<?php
// add_employee.php (FULL UPDATED)
// Style matches your view_billing.php (white theme, card, alerts)
// Uses PDO, CSRF, strict Salary/Contact validation, secure image upload

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

$messages = [];
$errors   = [];

$salaryTypes = ['Monthly','Hourly','Contract'];

// ---- POST: Add Employee ----
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['_csrf'])
    && hash_equals($CSRF, (string)$_POST['_csrf'])) {

    // Collect & trim
    $name       = trim((string)($_POST['Name'] ?? ''));
    $role       = trim((string)($_POST['Role'] ?? ''));
    $salaryRaw  = trim((string)($_POST['Salary'] ?? ''));        // keep raw for regex check
    $salaryType = trim((string)($_POST['SalaryType'] ?? ''));
    $contact    = trim((string)($_POST['Contact'] ?? ''));
    $address    = trim((string)($_POST['Address'] ?? ''));

    // ---- VALIDATION ----

    // Name
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = "Name is required (max 100).";
    }

    // Role
    if ($role === '' || mb_strlen($role) > 50) {
        $errors[] = "Role is required (max 50).";
    }

    // Salary: number with up to 2 decimals, range 500..2,000,000
    if ($salaryRaw === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $salaryRaw)) {
        $errors[] = "Salary must be a valid number (e.g., 125000 or 125000.50).";
    } else {
        $salaryVal = (float)$salaryRaw;
        if ($salaryVal < 500) {
            $errors[] = "Salary must be at least LKR 500.";
        } elseif ($salaryVal > 2000000) {
            $errors[] = "Salary must be ≤ LKR 2,000,000.";
        }
    }

    // Salary Type
    if (!in_array($salaryType, $salaryTypes, true)) {
        $errors[] = "Invalid salary type.";
    }

    // Contact: strictly 0XXXXXXXXX or +94XXXXXXXXX (Sri Lanka)
    if ($contact === '' || mb_strlen($contact) > 15) {
        $errors[] = "Contact is required (max 15).";
    } elseif (!preg_match('/^(0\d{9}|\+94\d{9})$/', $contact)) {
        $errors[] = "Contact must be 0XXXXXXXXX or +94XXXXXXXXX.";
    }

    // Address (optional)
    if (mb_strlen($address) > 255) {
        $errors[] = "Address must be ≤ 255 characters.";
    }

    // Image (optional): JPG/PNG/WEBP ≤ 2MB
    $savedImageName = null;
    if (!empty($_FILES['Image']['name'])) {
        $f = $_FILES['Image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Image upload failed.";
        } else {
            if ($f['size'] > 2 * 1024 * 1024) {
                $errors[] = "Image too large (max 2 MB).";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($f['tmp_name']) ?: '';
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (!isset($allowed[$mime])) {
                    $errors[] = "Only JPG, PNG or WEBP allowed.";
                } elseif (!@getimagesize($f['tmp_name'])) {
                    $errors[] = "Invalid image file.";
                } else {
                    $ext  = $allowed[$mime];
                    $slug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($name)) ?: 'employee';
                    $savedImageName = time() . '_' . $slug . '.' . $ext;

                    $uploadBase = __DIR__ . '/../assets/uploads/employees';
                    if (!is_dir($uploadBase)) {
                        @mkdir($uploadBase, 0775, true);
                    }
                    if (!is_writable($uploadBase)) {
                        $errors[] = "Upload folder not writable: /assets/uploads/employees";
                    } else {
                        $dest = rtrim($uploadBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $savedImageName;
                        if (!move_uploaded_file($f['tmp_name'], $dest)) {
                            $errors[] = "Failed to save uploaded image.";
                        }
                    }
                }
            }
        }
    }

    // ---- INSERT ----
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO employee (`Name`,`Role`,`Salary`,`SalaryType`,`Contact`,`Address`,`Image`)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $name,
                $role,
                (float)$salaryRaw,
                $salaryType,
                $contact,
                $address !== '' ? $address : null,
                $savedImageName
            ]);
            $_SESSION['flash'] = ['ok' => true, 'msg' => "Employee “".htmlspecialchars($name)."” added successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Insert failed: " . htmlspecialchars($e->getMessage())];
        }
        // PRG
        header("Location: add_employee.php");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: add_employee.php");
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
/* ---- White, business style to match view_billing.php — brand blue (#3b5683) ---- */
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
  font-size:22px;font-weight:600;padding:18px 20px;margin:0;
  border-bottom:1px solid var(--border); color:var(--text);
}

/* Header bar */
.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px}
.headerbar a{text-decoration:none;color:var(--brand);}
.headerbar a:hover{text-decoration:underline;}

/* Alerts */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Form layout */
.form-wrap{padding:16px 20px 22px 20px;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

/* Inputs */
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted)}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;color:var(--text);
  transition:border .2s, box-shadow .2s, background .2s, color .2s;
}
.input:focus,select:focus,textarea:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);
}
.hint{font-size:12px;color:var(--muted);margin-top:6px}

/* Buttons */
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{
  padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid var(--border);background:#fff;color:var(--text);
  transition:background .2s, color .2s, box-shadow .2s, filter .2s;
}
.btn:hover{background:var(--tint-hover)}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring);}

/* Primary (brand) */
.btn-primary{background:var(--brand);color:#fff;border-color:var(--brand);}
.btn-primary:hover{background:var(--brand);filter:brightness(1.05)}
.btn-primary:active{background:var(--brand-dark)}
.btn-primary:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring);}

/* Media / preview */
.preview{
  width:110px;height:110px;border-radius:10px;border:1px solid var(--border);
  object-fit:cover;display:none;margin-top:8px;
}

/* Badges & inline validation */
.badge-note{font-size:12px;color:var(--muted)}
.req{color:#dc2626}
.inline-error{color:#dc2626;font-size:12px;margin-top:6px;display:none}
</style>


<div class="container">
  <div class="card">
    <h1>Add Employee</h1>

    <div class="headerbar">
      <div class="badge-note">Create a new employee record for RB Stores.</div>
      <a class="btn" href="employee.php">View Employees</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <form class="form-wrap" method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

      <div class="grid">
        <div>
          <div class="label">Full Name <span class="req">*</span></div>
          <input class="input" type="text" name="Name" maxlength="100" required placeholder="e.g., Kasun Perera"
                 value="<?= htmlspecialchars((string)($_POST['Name'] ?? '')) ?>">
          <div class="hint">Max 100 characters.</div>
        </div>

        <div>
          <div class="label">Role <span class="req">*</span></div>
          <input class="input" type="text" name="Role" maxlength="50" required placeholder="e.g., Accountant, Driver"
                 value="<?= htmlspecialchars((string)($_POST['Role'] ?? '')) ?>">
          <div class="hint">Max 50 characters.</div>
        </div>

        <div>
          <div class="label">Salary (LKR) <span class="req">*</span></div>
          <input class="input" type="number" name="Salary" step="0.01" min="500" max="2000000" inputmode="decimal" required
                 placeholder="e.g., 125000"
                 value="<?= htmlspecialchars((string)($_POST['Salary'] ?? '')) ?>">
          <div class="hint">Valid range: 500 – 2,000,000 (up to 2 decimals)</div>
          <div id="salaryErr" class="inline-error">Please enter a valid salary between 500 and 2,000,000.</div>
        </div>

        <div>
          <div class="label">Salary Type <span class="req">*</span></div>
          <select name="SalaryType" required>
            <option value="">Select…</option>
            <?php $curr = (string)($_POST['SalaryType'] ?? '');
              foreach ($salaryTypes as $st) {
                $sel = $curr === $st ? 'selected' : '';
                echo "<option $sel>".htmlspecialchars($st)."</option>";
              }
            ?>
          </select>
        </div>

        <div>
          <div class="label">Contact Number <span class="req">*</span></div>
          <input class="input" type="text" name="Contact" maxlength="15" required
                 pattern="^(0\d{9}|\+94\d{9})$"
                 placeholder="0XXXXXXXXX or +94XXXXXXXXX"
                 value="<?= htmlspecialchars((string)($_POST['Contact'] ?? '')) ?>">
          <div class="hint">Valid: 0712345678 or +94712345678</div>
          <div id="contactErr" class="inline-error">Invalid format. Use 0XXXXXXXXX or +94XXXXXXXXX.</div>
        </div>

        <div>
          <div class="label">Address</div>
          <textarea class="input" name="Address" rows="3" maxlength="255" placeholder="Street, City"><?= htmlspecialchars((string)($_POST['Address'] ?? '')) ?></textarea>
          <div class="hint">Optional, max 255 characters.</div>
        </div>

        <div>
          <div class="label">Profile Image</div>
          <input class="input" type="file" name="Image" accept=".jpg,.jpeg,.png,.webp">
          <img id="imgPreview" class="preview" alt="Preview">
          <div class="hint">JPG/PNG/WEBP, ≤ 2 MB. Square ~512×512 works best.</div>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Employee</button>
        <button class="btn" type="reset" id="resetBtn">Clear</button>
      </div>
    </form>
  </div>
</div>

<script>
// ---- Client-side validation ----

// Salary: 500..2,000,000 and max 2 decimals
const salaryInput = document.querySelector('[name="Salary"]');
const salaryErr   = document.getElementById('salaryErr');
salaryInput?.addEventListener('input', () => {
  const raw = salaryInput.value.trim();
  const re  = /^\d+(\.\d{1,2})?$/;
  let valid = re.test(raw);
  if (valid) {
    const val = parseFloat(raw);
    valid = !(isNaN(val) || val < 500 || val > 2000000);
  }
  if (!valid) {
    salaryInput.setCustomValidity('Invalid salary');
    salaryErr.style.display = 'block';
  } else {
    salaryInput.setCustomValidity('');
    salaryErr.style.display = 'none';
  }
});

// Contact: 0XXXXXXXXX or +94XXXXXXXXX
const contactInput = document.querySelector('[name="Contact"]');
const contactErr   = document.getElementById('contactErr');
contactInput?.addEventListener('input', () => {
  const re = /^(0\d{9}|\+94\d{9})$/;
  if (!re.test(contactInput.value.trim())) {
    contactInput.setCustomValidity('Invalid contact');
    contactErr.style.display = 'block';
  } else {
    contactInput.setCustomValidity('');
    contactErr.style.display = 'none';
  }
});

// Image preview with basic guards
const imgInput = document.querySelector('input[type="file"][name="Image"]');
const prev     = document.getElementById('imgPreview');
imgInput?.addEventListener('change', (e) => {
  const f = e.target.files?.[0];
  if (!f) { prev.style.display='none'; prev.removeAttribute('src'); return; }
  if (f.size > 2*1024*1024) { alert('Image too large (max 2 MB).'); imgInput.value=''; prev.style.display='none'; return; }
  if (!['image/jpeg','image/png','image/webp'].includes(f.type)) { alert('Only JPG, PNG or WEBP allowed.'); imgInput.value=''; prev.style.display='none'; return; }
  const r = new FileReader();
  r.onload = () => { prev.src = r.result; prev.style.display='block'; };
  r.readAsDataURL(f);
});

// Extra front-end required guard
document.querySelector('form')?.addEventListener('submit', (e) => {
  const required = ['Name','Role','Salary','SalaryType','Contact'];
  for (const n of required) {
    const el = document.querySelector(`[name="${n}"]`);
    if (!el || !el.value.trim()) {
      e.preventDefault();
      alert('Please fill all required fields.');
      return;
    }
  }
});

// Save button tactile (prevents “disappear” feel)
const saveBtn = document.getElementById('saveBtn');
saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
</script>

<?php include 'footer.php'; ?>
