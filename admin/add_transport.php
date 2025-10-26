<?php
// add_transport.php (UPDATED: driver-only dropdown + add vehicle shortcut)
// Style/structure mirrors your add_supplier.php sample (white theme, card, alerts)
// Uses PDO, CSRF, PRG flash, strict validation, duplicate schedule guard
// NEW: Driver dropdown only includes employees whose Role contains "driver" (case-insensitive).
//      If no drivers exist, the field is hidden and EmployeeID becomes optional (NULL).
//      Vehicle label includes a "+ Add Vehicle" shortcut.

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

// ---- POST: Add Transport ----
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_csrf']) &&
    hash_equals($CSRF, (string)$_POST['_csrf'])
) {
    // Collect & trim
    $OrderID      = trim((string)($_POST['OrderID'] ?? ''));           // optional
    $STATUS       = trim((string)($_POST['STATUS'] ?? 'Scheduled'));   // enum
    $DeliveryDate = trim((string)($_POST['DeliveryDate'] ?? ''));      // YYYY-MM-DD
    $DeliveryTime = trim((string)($_POST['DeliveryTime'] ?? ''));      // HH:MM
    $Destination  = trim((string)($_POST['Destination'] ?? ''));       // required
    $VehicleID    = trim((string)($_POST['VehicleID'] ?? ''));         // required
    // EmployeeID may be optional if no drivers exist
    $EmployeeID_raw = trim((string)($_POST['EmployeeID'] ?? ''));

    // Status whitelist
    $allowedStatuses = ['Pending','Scheduled','Dispatched','Delivered','Cancelled'];
    if (!in_array($STATUS, $allowedStatuses, true)) {
        $errors[] = "Invalid status.";
    }

    // Required fields
    if (!notEmpty($Destination)) {
        $errors[] = "Destination is required.";
    } elseif (mb_strlen($Destination) > 255) {
        $errors[] = "Destination must be ≤ 255 characters.";
    }

    if (!ctype_digit($VehicleID) || (int)$VehicleID <= 0) {
        $errors[] = "Vehicle is required.";
    }

    // Date/time formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $DeliveryDate)) {
        $errors[] = "Delivery date is invalid.";
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $DeliveryTime)) {
        $errors[] = "Delivery time is invalid.";
    }

    // Past date/time guard (server-side)
    if (!$errors) {
        try {
            $now = new DateTime('now', new DateTimeZone('Asia/Colombo'));
            $dt  = new DateTime("{$DeliveryDate} {$DeliveryTime}:00", new DateTimeZone('Asia/Colombo'));
            if ($dt < $now) {
                $errors[] = "Delivery date & time can’t be in the past.";
            }
        } catch (Throwable $e) {
            $errors[] = "Invalid delivery date/time.";
        }
    }

    // FK existence checks
    if (!$errors && $OrderID !== '') {
        if (!ctype_digit($OrderID)) {
            $errors[] = "Invalid Order.";
        } else {
            $q = $pdo->prepare("SELECT COUNT(*) FROM `order` WHERE OrderID = ?");
            $q->execute([(int)$OrderID]);
            if (!$q->fetchColumn()) $errors[] = "Selected Order doesn’t exist.";
        }
    }

    // Check Vehicle exists
    if (!$errors) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM vehicle WHERE VehicleID = ?");
        $q->execute([(int)$VehicleID]);
        if (!$q->fetchColumn()) $errors[] = "Selected Vehicle doesn’t exist.";
    }

    // Is there at least one driver in DB? (case-insensitive Role LIKE '%driver%')
    $drvCount = (int)$pdo->query("SELECT COUNT(*) AS c FROM employee WHERE Role IS NOT NULL AND LOWER(Role) LIKE '%driver%'")->fetchColumn();
    $driversExist = $drvCount > 0;

    // EmployeeID rules:
    // - If drivers exist, EmployeeID is required and must be one of the driver IDs.
    // - If no drivers exist, EmployeeID becomes optional (NULL allowed by schema).
    $EmployeeID = null;
    if ($driversExist) {
        if (!ctype_digit($EmployeeID_raw) || (int)$EmployeeID_raw <= 0) {
            $errors[] = "Driver is required.";
        } else {
            // Verify the chosen employee is actually a driver
            $q = $pdo->prepare("SELECT COUNT(*) FROM employee WHERE EmployeeID = ? AND Role IS NOT NULL AND LOWER(Role) LIKE '%driver%'");
            $q->execute([(int)$EmployeeID_raw]);
            if (!$q->fetchColumn()) {
                $errors[] = "Selected employee is not a Driver.";
            } else {
                $EmployeeID = (int)$EmployeeID_raw;
            }
        }
    } else {
        // no drivers in DB: allow NULL employee id
        $EmployeeID = null;
    }

    // Duplicate-schedule guard: same Vehicle + same DeliveryDate + same DeliveryTime
    if (!$errors) {
        $dup = $pdo->prepare("SELECT TransportID FROM transport
                              WHERE VehicleID = :v AND DeliveryDate = :d AND DeliveryTime = :t
                              LIMIT 1");
        $dup->execute([
            ':v' => (int)$VehicleID,
            ':d' => $DeliveryDate,
            ':t' => $DeliveryTime . ':00',
        ]);
        if ($dup->fetch()) {
            $errors[] = "Another delivery is already scheduled for this vehicle at the same date & time.";
        }
    }

    // ---- INSERT ----
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO transport
                    (OrderID, STATUS, DeliveryDate, Destination, VehicleID, EmployeeID, DeliveryTime, Notes)
                VALUES
                    (:OrderID, :STATUS, :DeliveryDate, :Destination, :VehicleID, :EmployeeID, :DeliveryTime, :Notes)
            ");
            $stmt->execute([
                ':OrderID'      => ($OrderID !== '') ? (int)$OrderID : null,
                ':STATUS'       => $STATUS,
                ':DeliveryDate' => $DeliveryDate,
                ':Destination'  => $Destination,
                ':VehicleID'    => (int)$VehicleID,
                ':EmployeeID'   => $EmployeeID, // may be null if no drivers exist
                ':DeliveryTime' => $DeliveryTime . ':00',
                ':Notes'        => notEmpty($_POST['Notes'] ?? '') ? trim((string)$_POST['Notes']) : null,
            ]);

            $newId = (int)$pdo->lastInsertId();
            $_SESSION['flash'] = ['ok' => true, 'msg' => "Transport #".h((string)$newId)." added successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Insert failed: " . htmlspecialchars($e->getMessage())];
        }
        // PRG
        header("Location: add_transport.php");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: add_transport.php");
        exit();
    }
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Preload dropdowns ----
$orders = $pdo->query("
    SELECT o.OrderID, o.InvoiceID, c.NAME AS CustomerName
    FROM `order` o
    LEFT JOIN customer c ON c.CustomerID = o.CustomerID
    ORDER BY o.OrderID DESC
")->fetchAll();

$vehicles = $pdo->query("
    SELECT VehicleID, VehicleNumber, VehicleType, MaxLoadKg
    FROM vehicle
    ORDER BY VehicleNumber ASC
")->fetchAll();

// ONLY drivers (Role contains 'driver', case-insensitive)
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
/* ---- White, business style (same structure as your sample) — brand blue (#3b5683) ---- */
body{background:#ffffff;color:#3b5683;margin:0;}
.container{margin-left:260px;padding:20px;max-width:900px;}
@media(max-width:992px){.container{margin-left:0;}}

.card{
  background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;
  border:1px solid #dfe6f2;
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid #dfe6f2;margin:0;
  color:#3b5683;
}

.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px}
.headerbar a{text-decoration:none;color:#3b5683;}
.headerbar a:hover{text-decoration:underline}

.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
/* Info aligned to brand */
.alert-info{background:#e9eff7;color:#3b5683;border:1px solid #dfe6f2;margin:10px 20px}

.form-wrap{padding:16px 20px 22px 20px;}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

.label{
  font-weight:600;font-size:14px;margin-bottom:6px;color:#6b7c97;
  display:flex;align-items:center;justify-content:space-between;gap:8px
}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid #dfe6f2;border-radius:8px;font-size:14px;background:#fff;color:#3b5683;
  transition:border .2s, box-shadow .2s, background .2s, color .2s;
}
.input:focus,select:focus,textarea:focus{
  outline:none;border-color:#3b5683;box-shadow:0 0 0 3px rgba(59,86,131,.25);
}
.hint{font-size:12px;color:#6b7c97;margin-top:6px}
.req{color:#dc2626}
.inline-error{color:#dc2626;font-size:12px;margin-top:6px;display:none}

.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{
  padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid #dfe6f2;background:#fff;color:#3b5683;transition:all .2s;
}
.btn:hover{background:#e9eff7}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(59,86,131,.25)}
.btn-primary{
  background:#3b5683;color:#fff;border:1px solid #3b5683;
}
.btn-primary:hover{background:#3b5683;filter:brightness(1.05)}
.btn-primary:active{background:#324a70}
.btn-link{border:none;background:none;color:#3b5683;cursor:pointer;padding:0;font-size:12px}
.btn-link:hover{text-decoration:underline}
</style>


<div class="container">
  <div class="card">
    <h1>Add Transport</h1>

    <div class="headerbar">
      <div class="hint">Schedule and assign vehicles & drivers for customer deliveries.</div>
      <a class="btn" href="transport.php">View Transports</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <?php if (!$driversExist): ?>
      <div class="alert-info">
        <strong>Note:</strong> No employees with Role “Driver” were found. You can still create the transport without a driver, or add a driver via <em>Employee</em> module and assign later.
      </div>
    <?php endif; ?>

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">

      <div class="grid">
        <!-- Related Order (optional) -->
        <div>
          <div class="label">Related Order</div>
          <select class="input" name="OrderID">
            <option value="">— No Order / Manual Delivery —</option>
            <?php foreach ($orders as $o): ?>
              <?php
                $label = "Order #{$o['OrderID']}";
                if (!empty($o['InvoiceID']))    $label .= " • {$o['InvoiceID']}";
                if (!empty($o['CustomerName'])) $label .= " • {$o['CustomerName']}";
              ?>
              <option value="<?= (int)$o['OrderID'] ?>">
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Link to invoice/customer if this transport serves a specific order.</div>
        </div>

        <!-- Status -->
        <div>
          <div class="label">Status <span class="req">*</span></div>
          <?php $curSt = (string)($_POST['STATUS'] ?? 'Scheduled'); ?>
          <select class="input" name="STATUS" required>
            <option value="Pending"    <?= $curSt==='Pending'?'selected':''; ?>>Pending</option>
            <option value="Scheduled"  <?= $curSt==='Scheduled'?'selected':''; ?>>Scheduled</option>
            <option value="Dispatched" <?= $curSt==='Dispatched'?'selected':''; ?>>Dispatched</option>
            <option value="Delivered"  <?= $curSt==='Delivered'?'selected':''; ?>>Delivered</option>
            <option value="Cancelled"  <?= $curSt==='Cancelled'?'selected':''; ?>>Cancelled</option>
          </select>
        </div>

        <!-- Delivery Date -->
        <div>
          <div class="label">Delivery Date <span class="req">*</span></div>
          <input class="input" type="date" name="DeliveryDate" required
                 value="<?= h((string)($_POST['DeliveryDate'] ?? '')) ?>">
          <div class="hint">Must be today or later.</div>
          <div id="dateErr" class="inline-error">Invalid or past date.</div>
        </div>

        <!-- Delivery Time -->
        <div>
          <div class="label">Delivery Time <span class="req">*</span></div>
          <input class="input" type="time" name="DeliveryTime" required
                 value="<?= h((string)($_POST['DeliveryTime'] ?? '')) ?>">
          <div class="hint">24-hour format (HH:MM).</div>
          <div id="timeErr" class="inline-error">Invalid time.</div>
        </div>

        <!-- Vehicle -->
        <div>
          <div class="label">
            <span>Vehicle <span class="req">*</span></span>
            <a class="btn-link" href="add_vehicle.php">+ Add Vehicle</a>
          </div>
          <select class="input" name="VehicleID" required>
            <option value="">— Select Vehicle —</option>
            <?php foreach ($vehicles as $v): ?>
              <?php
                $vehLbl = "{$v['VehicleNumber']} • {$v['VehicleType']} • Max ".number_format((float)$v['MaxLoadKg'],0)." kg";
              ?>
              <option value="<?= (int)$v['VehicleID'] ?>"><?= h($vehLbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Driver (ONLY shown if drivers exist) -->
        <?php if ($driversExist): ?>
          <div>
            <div class="label">Driver <span class="req">*</span></div>
            <select class="input" name="EmployeeID" required>
              <option value="">— Select Driver —</option>
              <?php foreach ($drivers as $d): ?>
                <?php $role = $d['Role'] ?: 'Driver'; ?>
                <option value="<?= (int)$d['EmployeeID'] ?>"><?= h($d['Name'].' • '.$role) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Only employees with Role containing “Driver” are listed.</div>
          </div>
        <?php endif; ?>

        <!-- Destination -->
        <div style="grid-column:1/-1">
          <div class="label">Destination <span class="req">*</span></div>
          <input class="input" type="text" name="Destination" maxlength="255" required
                 placeholder="e.g., 45/3, Sri Jayewardenepura, Kotte"
                 value="<?= h((string)($_POST['Destination'] ?? '')) ?>">
        </div>

        <!-- Notes -->
        <div style="grid-column:1/-1">
          <div class="label">Notes</div>
          <textarea class="input" name="Notes" rows="3" placeholder="Special instructions, contact windows, unloading notes..."><?= h((string)($_POST['Notes'] ?? '')) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Transport</button>
        <a class="btn" href="view_transport.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
// Client guards: date >= today (local), basic presence
(function(){
  const dateEl = document.querySelector('[name="DeliveryDate"]');
  const timeEl = document.querySelector('[name="DeliveryTime"]');
  const dateErr= document.getElementById('dateErr');
  const timeErr= document.getElementById('timeErr');

  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth()+1).padStart(2,'0');
  const dd = String(now.getDate()).padStart(2,'0');
  const today = `${yyyy}-${mm}-${dd}`;
  if (dateEl) dateEl.min = today;

  function validateDate(){
    if (!dateEl?.value) return;
    if (dateEl.value < today){
      dateEl.setCustomValidity('Past date');
      dateErr.style.display = 'block';
    } else {
      dateEl.setCustomValidity('');
      dateErr.style.display = 'none';
    }
  }
  function validateTime(){
    if (!timeEl?.value) return;
    const ok = /^\d{2}:\d{2}$/.test(timeEl.value);
    if (!ok){
      timeEl.setCustomValidity('Invalid time');
      timeErr.style.display = 'block';
    } else {
      timeEl.setCustomValidity('');
      timeErr.style.display = 'none';
    }
  }

  dateEl?.addEventListener('change', validateDate);
  timeEl?.addEventListener('input', validateTime);

  // Save button tactile feedback
  const saveBtn = document.getElementById('saveBtn');
  saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
  saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
})();
</script>

<?php include 'footer.php'; ?>
