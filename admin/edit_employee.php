<?php
// edit_employee.php — Edit Employee (RB Stores)
// PHP 8.2+, PDO, CSRF, white business theme
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

// ---- DB (PDO like your other pages) ----
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

// ---- Get employee_id ----
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($employeeId <= 0) {
    $errors[] = "Invalid request: missing employee_id.";
}

// ---- Fetch current record ----
$employee = null;
if (!$errors) {
    $st = $pdo->prepare("SELECT * FROM employee WHERE EmployeeID=?");
    $st->execute([$employeeId]);
    $employee = $st->fetch();
    if (!$employee) {
        $errors[] = "Employee not found (ID: {$employeeId}).";
    }
}

// ---- Handle POST (update) ----
if (!$errors && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['_csrf']) && hash_equals($CSRF, (string)$_POST['_csrf'])) {

    // Collect
    $name       = trim((string)($_POST['Name'] ?? ''));
    $role       = trim((string)($_POST['Role'] ?? ''));
    $salaryRaw  = trim((string)($_POST['Salary'] ?? ''));
    $salaryType = trim((string)($_POST['SalaryType'] ?? ''));
    $contact    = trim((string)($_POST['Contact'] ?? ''));
    $address    = trim((string)($_POST['Address'] ?? ''));
    $removeImg  = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    // Validate
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = "Name is required (max 100).";
    }
    if ($role === '' || mb_strlen($role) > 50) {
        $errors[] = "Role is required (max 50).";
    }
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
    if (!in_array($salaryType, $salaryTypes, true)) {
        $errors[] = "Invalid salary type.";
    }
    if ($contact === '' || mb_strlen($contact) > 15) {
        $errors[] = "Contact is required (max 15).";
    } elseif (!preg_match('/^(0\d{9}|\+94\d{9})$/', $contact)) {
        $errors[] = "Contact must be 0XXXXXXXXX or +94XXXXXXXXX.";
    }
    if (mb_strlen($address) > 255) {
        $errors[] = "Address must be ≤ 255 characters.";
    }

    // Handle image: optional new file OR remove flag
    $newImageName = null;
    $wantRemove   = $removeImg;
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
                    $slug = preg_replace('/[^a-z0-9_-]+/i','-', strtolower($name)) ?: 'employee';
                    $newImageName = time() . '_' . $slug . '.' . $ext;

                    $uploadBase = __DIR__ . '/../assets/uploads/employees';
                    if (!is_dir($uploadBase)) @mkdir($uploadBase, 0775, true);
                    if (!is_writable($uploadBase)) {
                        $errors[] = "Upload folder not writable: /assets/uploads/employees";
                    } else {
                        $dest = rtrim($uploadBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newImageName;
                        if (!move_uploaded_file($f['tmp_name'], $dest)) {
                            $errors[] = "Failed to save uploaded image.";
                        } else {
                            // If user uploaded a new image, we will remove old one later.
                            $wantRemove = true;
                        }
                    }
                }
            }
        }
    }

    if (!$errors) {
        // Remove old image file if needed
        $oldImage = $employee['Image'] ?? null;
        if ($wantRemove && $oldImage) {
            $oldPath = __DIR__ . '/../assets/uploads/employees/' . $oldImage;
            if (is_file($oldPath)) @unlink($oldPath);
        }

        // Determine final image value to save
        $finalImage = $newImageName ?? ($removeImg ? null : ($employee['Image'] ?? null));

        // Update DB
        try {
            $stmt = $pdo->prepare("
                UPDATE employee
                   SET `Name`=?, `Role`=?, `Salary`=?, `SalaryType`=?, `Contact`=?, `Address`=?, `Image`=?
                 WHERE EmployeeID=?
            ");
            $stmt->execute([
                $name,
                $role,
                (float)$salaryRaw,
                $salaryType,
                $contact,
                $address !== '' ? $address : null,
                $finalImage,
                $employeeId
            ]);

            $_SESSION['flash'] = ['ok' => true, 'msg' => "Employee updated successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Update failed: " . htmlspecialchars($e->getMessage())];
        }

        // PRG
        header("Location: edit_employee.php?employee_id={$employeeId}");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: edit_employee.php?employee_id={$employeeId}");
        exit();
    }
}

// ---- Flash (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// For form values (after load)
function fval($key, $fallback) {
    return htmlspecialchars((string)($_POST[$key] ?? $fallback ?? ''));
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business style (match your pages) ---- */
body{background:#f4f6f9;color:#1e293b;margin:0;}
.container{margin-left:260px;padding:20px;max-width:900px;}
@media(max-width:992px){.container{margin-left:0;}}

.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.06);margin-bottom:24px;}
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

.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid #cbd5e1;background:#fff;color:#334155;}
.btn:hover{background:#f1f5f9}
.btn-primary{background:#2563eb;color:#fff;border:none;}
.btn-primary:hover{background:#1d4ed8}
.btn-danger{background:#dc2626;color:#fff;border:none;}
.btn-danger:hover{background:#b91c1c}

.preview{width:110px;height:110px;border-radius:10px;border:1px solid #e2e8f0;object-fit:cover;display:block;margin-top:8px}
.preview-fallback{width:110px;height:110px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#e0f2fe;color:#075985;font-size:28px;font-weight:800;border:1px solid #bae6fd;margin-top:8px}
.badge-note{font-size:12px;color:#64748b}
.req{color:#dc2626}
.inline-error{color:#dc2626;font-size:12px;margin-top:6px;display:none}
</style>

<div class="container">
  <div class="card">
    <h1>Edit Employee</h1>

    <div class="headerbar">
      <div class="badge-note">Update an existing employee record.</div>
      <div style="display:flex; gap:8px; flex-wrap:wrap">
        <a class="btn" href="employee.php">← Back to Employees</a>
        <a class="btn" href="add_employee.php">+ Add New</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <?php if ($errors && !$employee): ?>
      <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php elseif ($employee): ?>
    <form class="form-wrap" method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

      <div class="grid">
        <div>
          <div class="label">Full Name <span class="req">*</span></div>
          <input class="input" type="text" name="Name" maxlength="100" required
                 placeholder="e.g., Kasun Perera"
                 value="<?= fval('Name', $employee['Name']) ?>">
          <div class="hint">Max 100 characters.</div>
        </div>

        <div>
          <div class="label">Role <span class="req">*</span></div>
          <input class="input" type="text" name="Role" maxlength="50" required
                 placeholder="e.g., Accountant, Driver"
                 value="<?= fval('Role', $employee['Role']) ?>">
          <div class="hint">Max 50 characters.</div>
        </div>

        <div>
          <div class="label">Salary (LKR) <span class="req">*</span></div>
          <input class="input" type="number" name="Salary" step="0.01" min="500" max="2000000" inputmode="decimal" required
                 placeholder="e.g., 125000"
                 value="<?= fval('Salary', $employee['Salary']) ?>">
          <div class="hint">Valid range: 500 – 2,000,000 (up to 2 decimals)</div>
          <div id="salaryErr" class="inline-error">Please enter a valid salary between 500 and 2,000,000.</div>
        </div>

        <div>
          <div class="label">Salary Type <span class="req">*</span></div>
          <select name="SalaryType" required>
            <option value="">Select…</option>
            <?php
              $curr = (string)($_POST['SalaryType'] ?? $employee['SalaryType'] ?? '');
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
                 value="<?= fval('Contact', $employee['Contact']) ?>">
          <div class="hint">Valid: 0712345678 or +94712345678</div>
          <div id="contactErr" class="inline-error">Invalid format. Use 0XXXXXXXXX or +94XXXXXXXXX.</div>
        </div>

        <div>
          <div class="label">Address</div>
          <textarea class="input" name="Address" rows="3" maxlength="255"
                    placeholder="Street, City"><?= fval('Address', $employee['Address']) ?></textarea>
          <div class="hint">Optional, max 255 characters.</div>
        </div>

        <div>
          <div class="label">Profile Image</div>
          <input class="input" type="file" name="Image" accept=".jpg,.jpeg,.png,.webp">
          <?php
            $img = $employee['Image'] ?? null;
            $imgPath = $img ? "../assets/uploads/employees/{$img}" : null;
            $imgExists = $img && is_file(__DIR__ . '/../assets/uploads/employees/' . $img);
          ?>
          <?php if ($imgExists): ?>
            <img class="preview" src="<?= htmlspecialchars($imgPath) ?>" alt="Current Image">
            <label style="display:flex;align-items:center;gap:8px;margin-top:8px">
              <input type="checkbox" name="remove_image" value="1">
              <span>Remove current image</span>
            </label>
          <?php else: ?>
            <div class="preview-fallback">E</div>
          <?php endif; ?>
          <div class="hint">JPG/PNG/WEBP, ≤ 2 MB. Uploading a new image will replace the current one.</div>
        </div>

        <div>
          <div class="label">Created At</div>
          <input class="input" type="text" value="<?= htmlspecialchars((string)$employee['CreatedAt']) ?>" disabled>
        </div>

        <div>
          <div class="label">Updated At</div>
          <input class="input" type="text" value="<?= htmlspecialchars((string)$employee['UpdatedAt']) ?>" disabled>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Changes</button>
        <a class="btn" href="employee.php">Cancel</a>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<script>
// ---- Client-side validation (same rules as add) ----

// Salary
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
    if (salaryErr) salaryErr.style.display = 'block';
  } else {
    salaryInput.setCustomValidity('');
    if (salaryErr) salaryErr.style.display = 'none';
  }
});

// Contact
const contactInput = document.querySelector('[name="Contact"]');
const contactErr   = document.getElementById('contactErr');
contactInput?.addEventListener('input', () => {
  const re = /^(0\d{9}|\+94\d{9})$/;
  if (!re.test(contactInput.value.trim())) {
    contactInput.setCustomValidity('Invalid contact');
    if (contactErr) contactErr.style.display = 'block';
  } else {
    contactInput.setCustomValidity('');
    if (contactErr) contactErr.style.display = 'none';
  }
});

// Basic required guard
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

// Save button tactile feel
const saveBtn = document.getElementById('saveBtn');
saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
</script>

<?php include 'footer.php'; ?>
