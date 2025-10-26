<?php
// edit_vehicle.php — Professional edit form (matches add/view vehicle pages)
// - PDO + CSRF + PRG flash
// - Driver dropdown shows only employees with Role containing "driver" (case-insensitive)
// - Strict validation: required VehicleNumber & VehicleType; MaxLoadKg >= 0; unique VehicleNumber
// - Business UI: white card, filled buttons, responsive grid
// - Back link to Vehicles

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
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function posted(string $k, ?string $fallback=null): string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : (string)($fallback ?? ''); }
function qurl(array $overrides=[]): string { $qs = array_merge($_GET, $overrides); return ($_SERVER['PHP_SELF'] ?? 'edit_vehicle.php').'?'.http_build_query($qs); }

// ---- Load ID ----
$VehicleID = (int)($_GET['id'] ?? ($_POST['VehicleID'] ?? 0));
if ($VehicleID <= 0) {
  $_SESSION['flash'] = ['ok'=>false,'msg'=>'Missing or invalid vehicle ID.'];
  header('Location: view_vehicle.php'); exit;
}

// ---- Fetch current record ----
$cur = $pdo->prepare("SELECT VehicleID, VehicleNumber, VehicleType, MaxLoadKg, DriverID FROM vehicle WHERE VehicleID=?");
$cur->execute([$VehicleID]);
$row = $cur->fetch();
if (!$row) {
  $_SESSION['flash'] = ['ok'=>false,'msg'=>'Vehicle not found.'];
  header('Location: view_vehicle.php'); exit;
}

// ---- Drivers (Role contains 'driver') ----
$drivers = $pdo->query("
  SELECT EmployeeID, Name 
  FROM employee 
  WHERE Role LIKE '%driver%' OR Role LIKE '%Driver%' OR Role LIKE '%DRIVER%'
  ORDER BY Name ASC
")->fetchAll();

// ---- Handle POST (Update) ----
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_csrf']) && hash_equals($CSRF, (string)$_POST['_csrf'])) {

  $VehicleNumber = posted('VehicleNumber', $row['VehicleNumber']);
  $VehicleType   = posted('VehicleType',   $row['VehicleType']);
  $MaxLoadKg_in  = posted('MaxLoadKg',     (string)$row['MaxLoadKg']);
  $DriverID_in   = posted('DriverID',      $row['DriverID'] !== null ? (string)$row['DriverID'] : '');

  // --- Validation ---
  if ($VehicleNumber === '') {
    $errors['VehicleNumber'] = 'Vehicle number is required.';
  } else {
    // Simple plate sanity check (letters/numbers/dash/space)
    if (!preg_match('/^[A-Za-z0-9\- ]{3,20}$/', $VehicleNumber)) {
      $errors['VehicleNumber'] = 'Use 3–20 chars: letters, numbers, dash, space.';
    }
  }

  if ($VehicleType === '') {
    $errors['VehicleType'] = 'Vehicle type is required.';
  } else {
    if (mb_strlen($VehicleType) < 3 || mb_strlen($VehicleType) > 30) {
      $errors['VehicleType'] = 'Type must be 3–30 characters.';
    }
  }

  $MaxLoadKg = null;
  if ($MaxLoadKg_in === '' || !is_numeric($MaxLoadKg_in)) {
    $errors['MaxLoadKg'] = 'Max load must be a number (kg).';
  } else {
    $MaxLoadKg = (float)$MaxLoadKg_in;
    if ($MaxLoadKg < 0) {
      $errors['MaxLoadKg'] = 'Max load cannot be negative.';
    }
  }

  $DriverID = null;
  if ($DriverID_in !== '') {
    if (!ctype_digit($DriverID_in)) {
      $errors['DriverID'] = 'Invalid driver ID.';
    } else {
      $DriverID = (int)$DriverID_in;
      // Optional: ensure given driver exists in the filtered set
      $chk = $pdo->prepare("SELECT COUNT(*) FROM employee WHERE EmployeeID=? AND (Role LIKE '%driver%' OR Role LIKE '%Driver%' OR Role LIKE '%DRIVER%')");
      $chk->execute([$DriverID]);
      if ((int)$chk->fetchColumn() === 0) {
        $errors['DriverID'] = 'Selected driver not found or not a driver.';
      }
    }
  }

  // Unique plate for other vehicles
  if (!isset($errors['VehicleNumber'])) {
    $q = $pdo->prepare("SELECT VehicleID FROM vehicle WHERE VehicleNumber = ? AND VehicleID <> ? LIMIT 1");
    $q->execute([$VehicleNumber, $VehicleID]);
    if ($q->fetch()) {
      $errors['VehicleNumber'] = 'This vehicle number is already used by another record.';
    }
  }

  // --- Update if valid ---
  if (!$errors) {
    try {
      $upd = $pdo->prepare("
        UPDATE vehicle 
        SET VehicleNumber=?, VehicleType=?, MaxLoadKg=?, DriverID=?
        WHERE VehicleID=?
      ");
      $upd->execute([$VehicleNumber, $VehicleType, $MaxLoadKg, $DriverID, $VehicleID]);

      $_SESSION['flash'] = ['ok'=>true,'msg'=>'Vehicle updated successfully.'];
      header('Location: '.qurl()); // PRG: stay on same edit page
      exit;
    } catch (Throwable $e) {
      $_SESSION['flash'] = ['ok'=>false,'msg'=>'Update failed: '.h($e->getMessage())];
      header('Location: '.qurl()); exit;
    }
  }
}

// If there are validation errors from POST, show posted values; else show DB values
$val_VehicleNumber = $_SERVER['REQUEST_METHOD']==='POST' ? posted('VehicleNumber', $row['VehicleNumber']) : (string)$row['VehicleNumber'];
$val_VehicleType   = $_SERVER['REQUEST_METHOD']==='POST' ? posted('VehicleType',   $row['VehicleType'])   : (string)$row['VehicleType'];
$val_MaxLoadKg     = $_SERVER['REQUEST_METHOD']==='POST' ? posted('MaxLoadKg',     (string)$row['MaxLoadKg']) : (string)$row['MaxLoadKg'];
$val_DriverID      = $_SERVER['REQUEST_METHOD']==='POST' ? posted('DriverID',      $row['DriverID'] !== null ? (string)$row['DriverID'] : '') : ($row['DriverID'] !== null ? (string)$row['DriverID'] : '');

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

include 'header.php';
include 'sidebar.php';
?>
<style>
:root{
  --bg:#f4f6f9; --card:#ffffff; --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb;
  --brand:#2563eb; --brand-d:#1d4ed8;
  --indigo:#4f46e5; --indigo-d:#4338ca;
  --blue:#2563eb; --blue-d:#1d4ed8;
  --rose:#e11d48; --rose-d:#be123c;
  --ok-bg:#dcfce7; --ok-ink:#166534;
  --err-bg:#fee2e2; --err-ink:#991b1b;
  --info-bg:#eff6ff; --info-ink:#1e40af;
  --shadow:0 4px 14px rgba(0,0,0,.08);
}
body{background:var(--bg);color:var(--ink);margin:0;}
.container{margin-left:260px;padding:20px;max-width:900px;}
@media(max-width:992px){.container{margin-left:0;padding:16px;}}

.card{background:var(--card);border-radius:16px;box-shadow:var(--shadow);margin-bottom:24px;overflow:hidden;}
.card h1{font-size:22px;font-weight:700;padding:18px 20px;border-bottom:1px solid var(--line);margin:0;}

.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px;gap:12px;flex-wrap:wrap}
.hint{font-size:13px;color:var(--muted)}

.alert{padding:12px 14px;border-radius:10px;margin:12px 20px;font-size:14px;border:1px solid}
.alert-success{background:var(--ok-bg);color:var(--ok-ink);border-color:#bbf7d0}
.alert-error{background:var(--err-bg);color:var(--err-ink);border-color:#fecaca}
.alert-info{background:var(--info-bg);color:var(--info-ink);border-color:#bfdbfe}

.form-wrap{padding:16px 20px 22px 20px;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

.label{font-weight:600;font-size:13px;margin-bottom:6px;color:#334155;display:flex;align-items:center;justify-content:space-between;gap:8px}
.input,select,textarea{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;background:#fff;}
.input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.18);}

.inline-error{color:var(--rose-d);font-size:12px;margin-top:6px}
.hint-small{font-size:12px;color:var(--muted);margin-top:6px}

.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.btn{padding:10px 16px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:8px;line-height:1;}
.btn-ghost{background:#ffffff;border:1px solid #cbd5e1;color:#334155;}
.btn-ghost:hover{background:#f8fafc}
.btn-primary{background:var(--blue);color:#fff;}
.btn-primary:hover{background:var(--blue-d)}
.btn-view{background:var(--indigo);color:#fff;}
.btn-view:hover{background:var(--indigo-d)}
.btn-delete{background:var(--rose);color:#fff;}
.btn-delete:hover{background:var(--rose-d)}
</style>

<div class="container">
  <div class="card">
    <h1>Edit Vehicle</h1>

    <div class="headerbar">
      <div class="hint">Update vehicle details. Ensure the plate number is unique.</div>
      <div class="actions">
        <a class="btn btn-ghost" href="view_vehicle.php"><i class="fa fa-arrow-left"></i> Back to Vehicles</a>
        <a class="btn btn-view" href="vehicle_details.php?id=<?= (int)$VehicleID ?>"><i class="fa fa-eye"></i> View</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <div class="form-wrap">
      <form method="post" autocomplete="off" novalidate>
        <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="VehicleID" value="<?= (int)$VehicleID ?>">

        <div class="grid">
          <!-- Vehicle Number -->
          <div>
            <div class="label">Vehicle Number <span class="req" style="color:#dc2626">*</span></div>
            <input class="input" type="text" name="VehicleNumber" maxlength="20"
                   value="<?= h($val_VehicleNumber) ?>" placeholder="e.g., CAD-2398">
            <?php if (!empty($errors['VehicleNumber'])): ?>
              <div class="inline-error"><?= h($errors['VehicleNumber']) ?></div>
            <?php else: ?>
              <div class="hint-small">3–20 chars: letters, numbers, dash, space</div>
            <?php endif; ?>
          </div>

          <!-- Vehicle Type -->
          <div>
            <div class="label">Vehicle Type <span class="req" style="color:#dc2626">*</span></div>
            <input class="input" type="text" name="VehicleType" maxlength="30"
                   value="<?= h($val_VehicleType) ?>" placeholder="e.g., Truck, Van, Lorry">
            <?php if (!empty($errors['VehicleType'])): ?>
              <div class="inline-error"><?= h($errors['VehicleType']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Max Load -->
          <div>
            <div class="label">Max Load (kg) <span class="req" style="color:#dc2626">*</span></div>
            <input class="input" type="number" step="0.01" min="0" name="MaxLoadKg"
                   value="<?= h($val_MaxLoadKg) ?>" placeholder="e.g., 1500">
            <?php if (!empty($errors['MaxLoadKg'])): ?>
              <div class="inline-error"><?= h($errors['MaxLoadKg']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Driver -->
          <div>
            <div class="label">Driver (optional)</div>
            <select class="input" name="DriverID">
              <option value="">— None —</option>
              <?php foreach ($drivers as $d): 
                $sel = ($val_DriverID !== '' && (string)$d['EmployeeID'] === (string)$val_DriverID) ? 'selected' : ''; ?>
                <option value="<?= (int)$d['EmployeeID'] ?>" <?= $sel ?>>
                  <?= h($d['Name'].' (ID '.$d['EmployeeID'].')') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['DriverID'])): ?>
              <div class="inline-error"><?= h($errors['DriverID']) ?></div>
            <?php else: ?>
              <div class="hint-small">Only employees with a “Driver” role are listed.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="actions" style="margin-top:12px">
          <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save Changes</button>
          <a class="btn btn-ghost" href="<?= h(qurl()) ?>"><i class="fa fa-rotate-left"></i> Reset</a>
          <a class="btn btn-ghost" href="view_vehicle.php"><i class="fa fa-table"></i> View All</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
