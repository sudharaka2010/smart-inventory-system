<?php
// add_vehicle.php (FULL UPDATED)
// Style/structure mirrors your add_supplier.php sample (white theme, card, alerts)
// Uses PDO, CSRF, PRG flash, strict validation, duplicate guard (VehicleNumber unique)
// DriverID optional; if given, Role must contain "driver" (case-insensitive)

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

// Small helpers
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function notEmpty($v): bool { return isset($v) && trim((string)$v) !== ''; }

$errors = [];

// ---- POST: Add Vehicle ----
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_csrf']) &&
    hash_equals($CSRF, (string)$_POST['_csrf'])
) {
    // Collect & trim
    $VehicleNumber = strtoupper(trim((string)($_POST['VehicleNumber'] ?? ''))); // normalize to upper
    $VehicleType   = trim((string)($_POST['VehicleType'] ?? ''));
    $MaxLoadKg_raw = trim((string)($_POST['MaxLoadKg'] ?? ''));
    $DriverID_raw  = trim((string)($_POST['DriverID'] ?? ''));

    // ---- VALIDATION ----
    if ($VehicleNumber === '' || mb_strlen($VehicleNumber) > 50) {
        $errors[] = "Vehicle Number is required (max 50).";
    } else {
        // SL plates are varied; allow alnum, space, dash. e.g., "PB-2398", "WP CAA-1234", "AAA-1234"
        if (!preg_match('/^[A-Z0-9\- ]{3,50}$/', $VehicleNumber)) {
            $errors[] = "Vehicle Number may contain A–Z, 0–9, spaces, and dashes.";
        }
    }

    if ($VehicleType === '' || mb_strlen($VehicleType) > 50) {
        $errors[] = "Vehicle Type is required (max 50).";
    }

    if ($MaxLoadKg_raw === '') {
        $errors[] = "Max Load (kg) is required.";
    } else {
        // accept integers or decimals
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $MaxLoadKg_raw)) {
            $errors[] = "Max Load must be a number (up to 2 decimals).";
        } else {
            $MaxLoadKg = (float)$MaxLoadKg_raw;
            if ($MaxLoadKg <= 0 || $MaxLoadKg > 200000) {
                $errors[] = "Max Load must be between 1 and 200,000 kg.";
            }
        }
    }

    // Driver optional; if provided, must be a driver
    $DriverID = null;
    if ($DriverID_raw !== '') {
        if (!ctype_digit($DriverID_raw) || (int)$DriverID_raw <= 0) {
            $errors[] = "Invalid Driver.";
        } else {
            $q = $pdo->prepare("SELECT COUNT(*) FROM employee WHERE EmployeeID = ? AND Role IS NOT NULL AND LOWER(Role) LIKE '%driver%'");
            $q->execute([(int)$DriverID_raw]);
            if (!$q->fetchColumn()) {
                $errors[] = "Selected employee is not a Driver.";
            } else {
                $DriverID = (int)$DriverID_raw;
            }
        }
    }

    // Duplicate guard by VehicleNumber (your table has UNIQUE KEY VehicleNumber)
    if (!$errors) {
        $q = $pdo->prepare("SELECT VehicleID FROM vehicle WHERE VehicleNumber = ?");
        $q->execute([$VehicleNumber]);
        if ($q->fetch()) {
            $errors[] = "A vehicle with this number already exists.";
        }
    }

    // ---- INSERT ----
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vehicle (VehicleNumber, VehicleType, MaxLoadKg, DriverID)
                VALUES (:VehicleNumber, :VehicleType, :MaxLoadKg, :DriverID)
            ");
            $stmt->execute([
                ':VehicleNumber' => $VehicleNumber,
                ':VehicleType'   => $VehicleType,
                ':MaxLoadKg'     => number_format((float)$MaxLoadKg_raw, 2, '.', ''),
                ':DriverID'      => $DriverID, // may be null
            ]);

            $_SESSION['flash'] = ['ok' => true, 'msg' => "Vehicle “".h($VehicleNumber)."” added successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Insert failed: " . htmlspecialchars($e->getMessage())];
        }
        // PRG
        header("Location: add_vehicle.php");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: add_vehicle.php");
        exit();
    }
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Preload drivers (Role contains 'driver', case-insensitive) ----
$drivers = $pdo->query("
    SELECT EmployeeID, Name, Role
    FROM employee
    WHERE Role IS NOT NULL AND LOWER(Role) LIKE '%driver%'
    ORDER BY Name ASC
")->fetchAll();
$driversExist = count($drivers) > 0;

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business style (same structure as your sample) ---- */
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
.alert-info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;margin:10px 20px}

.form-wrap{padding:16px 20px 22px 20px;}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

.label{font-weight:600;font-size:14px;margin-bottom:6px;color:#334155;display:flex;align-items:center;justify-content:space-between;gap:8px}
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
.btn-link{border:none;background:none;color:#2563eb;cursor:pointer;padding:0;font-size:12px}
.btn-link:hover{text-decoration:underline}
</style>

<div class="container">
  <div class="card">
    <h1>Add Vehicle</h1>

    <div class="headerbar">
      <div class="hint">Register a vehicle and (optionally) assign a driver.</div>
      <a class="btn" href="view_vehicle.php">View Vehicles</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <?php if (!$driversExist): ?>
      <div class="alert-info">
        <strong>Tip:</strong> No employees with Role “Driver” found. You can add a driver via the Employee module and assign later.
      </div>
    <?php endif; ?>

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">

      <div class="grid">
        <!-- Vehicle Number -->
        <div>
          <div class="label">Vehicle Number <span class="req">*</span></div>
          <input class="input" type="text" name="VehicleNumber" maxlength="50" required
                 placeholder="e.g., PB-2398 / WP CAA-1234"
                 value="<?= h((string)($_POST['VehicleNumber'] ?? '')) ?>">
          <div class="hint">A–Z, 0–9, spaces, dashes. (Unique)</div>
        </div>

        <!-- Vehicle Type -->
        <div>
          <div class="label">Vehicle Type <span class="req">*</span></div>
          <input class="input" type="text" name="VehicleType" maxlength="50" list="vtlist" required
                 placeholder="e.g., Truck Long"
                 value="<?= h((string)($_POST['VehicleType'] ?? '')) ?>">
          <datalist id="vtlist">
            <option value="Truck Long">
            <option value="Truck Medium">
            <option value="Lorry">
            <option value="Pickup">
            <option value="Van">
            <option value="Three-Wheeler">
            <option value="Mini Truck">
          </datalist>
        </div>

        <!-- Max Load -->
        <div>
          <div class="label">Max Load (kg) <span class="req">*</span></div>
          <input class="input" type="number" step="0.01" min="1" max="200000" name="MaxLoadKg" required
                 placeholder="e.g., 12000"
                 value="<?= h((string)($_POST['MaxLoadKg'] ?? '')) ?>">
          <div class="hint">Positive number (up to 2 decimals).</div>
        </div>

        <!-- Driver (optional) -->
        <div>
          <div class="label">
            <span>Assign Driver</span>
            <a class="btn-link" href="add_employee.php">+ Add Driver</a>
          </div>
          <select class="input" name="DriverID">
            <option value="">— No Driver —</option>
            <?php foreach ($drivers as $d): ?>
              <?php $role = $d['Role'] ?: 'Driver'; ?>
              <option value="<?= (int)$d['EmployeeID'] ?>"><?= h($d['Name'].' • '.$role) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Only employees with Role containing “Driver” are listed.</div>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Vehicle</button>
        <a class="btn" href="view_vehicle.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-uppercase vehicle number & gentle validation hints
(function(){
  const plate = document.querySelector('[name="VehicleNumber"]');
  plate?.addEventListener('input', () => {
    plate.value = plate.value.toUpperCase();
    const ok = /^[A-Z0-9\- ]{3,50}$/.test(plate.value);
    plate.setCustomValidity(ok ? '' : 'Invalid plate');
  });

  const load = document.querySelector('[name="MaxLoadKg"]');
  load?.addEventListener('input', () => {
    const v = parseFloat(load.value);
    if (isNaN(v) || v <= 0 || v > 200000) {
      load.setCustomValidity('Invalid load');
    } else {
      load.setCustomValidity('');
    }
  });

  const saveBtn = document.getElementById('saveBtn');
  saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
  saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
})();
</script>

<?php include 'footer.php'; ?>
