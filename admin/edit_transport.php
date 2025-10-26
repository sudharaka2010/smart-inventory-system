<?php
// edit_transport.php (FULL UPDATED)
// White card theme, PDO, CSRF, PRG + flash
// Driver dropdown only lists employees with Role LIKE '%driver%'. If none, field is hidden (EmployeeID becomes optional).
// Duplicate schedule guard excludes current record.

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
$transportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($transportId <= 0) {
    http_response_code(400);
    die("Invalid ID.");
}

// ---- Fetch current record ----
$curSql = "
  SELECT t.*
  FROM transport t
  WHERE t.TransportID = ?
  LIMIT 1
";
$st = $pdo->prepare($curSql);
$st->execute([$transportId]);
$cur = $st->fetch();
if (!$cur) {
    http_response_code(404);
    die("Transport not found.");
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$errors = [];

// ---- POST: Update ----
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
    $EmployeeID_raw = trim((string)($_POST['EmployeeID'] ?? ''));      // may be optional if no drivers

    // Status whitelist
    $allowedStatuses = ['Pending','Scheduled','Dispatched','Delivered','Cancelled'];
    if (!in_array($STATUS, $allowedStatuses, true)) {
        $errors[] = "Invalid status.";
    }

    // Destination
    if (!notEmpty($Destination)) {
        $errors[] = "Destination is required.";
    } elseif (mb_strlen($Destination) > 255) {
        $errors[] = "Destination must be ≤ 255 characters.";
    }

    // Vehicle
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

    // Past date/time guard
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

    // FKs and driver rules
    if (!$errors && $OrderID !== '') {
        if (!ctype_digit($OrderID)) {
            $errors[] = "Invalid Order.";
        } else {
            $q = $pdo->prepare("SELECT COUNT(*) FROM `order` WHERE OrderID = ?");
            $q->execute([(int)$OrderID]);
            if (!$q->fetchColumn()) $errors[] = "Selected Order doesn’t exist.";
        }
    }
    if (!$errors) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM vehicle WHERE VehicleID = ?");
        $q->execute([(int)$VehicleID]);
        if (!$q->fetchColumn()) $errors[] = "Selected Vehicle doesn’t exist.";
    }

    // Check if there are any drivers in DB
    $drvCount = (int)$pdo->query("SELECT COUNT(*) FROM employee WHERE Role IS NOT NULL AND LOWER(Role) LIKE '%driver%'")->fetchColumn();
    $driversExist = $drvCount > 0;

    $EmployeeID = null;
    if ($driversExist) {
        if (!ctype_digit($EmployeeID_raw) || (int)$EmployeeID_raw <= 0) {
            $errors[] = "Driver is required.";
        } else {
            $q = $pdo->prepare("SELECT COUNT(*) FROM employee WHERE EmployeeID = ? AND Role IS NOT NULL AND LOWER(Role) LIKE '%driver%'");
            $q->execute([(int)$EmployeeID_raw]);
            if (!$q->fetchColumn()) {
                $errors[] = "Selected employee is not a Driver.";
            } else {
                $EmployeeID = (int)$EmployeeID_raw;
            }
        }
    } else {
        // No drivers available, allow NULL
        $EmployeeID = null;
    }

    // Duplicate schedule guard (exclude current record)
    if (!$errors) {
        $dup = $pdo->prepare("SELECT TransportID FROM transport
                              WHERE VehicleID = :v AND DeliveryDate = :d AND DeliveryTime = :t
                                AND TransportID <> :id
                              LIMIT 1");
        $dup->execute([
            ':v'  => (int)$VehicleID,
            ':d'  => $DeliveryDate,
            ':t'  => $DeliveryTime . ':00',
            ':id' => $transportId,
        ]);
        if ($dup->fetch()) {
            $errors[] = "Another delivery is already scheduled for this vehicle at the same date & time.";
        }
    }

    // UPDATE
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                UPDATE transport
                SET OrderID = :OrderID,
                    STATUS = :STATUS,
                    DeliveryDate = :DeliveryDate,
                    Destination = :Destination,
                    VehicleID = :VehicleID,
                    EmployeeID = :EmployeeID,
                    DeliveryTime = :DeliveryTime,
                    Notes = :Notes
                WHERE TransportID = :id
            ");
            $stmt->execute([
                ':OrderID'      => ($OrderID !== '') ? (int)$OrderID : null,
                ':STATUS'       => $STATUS,
                ':DeliveryDate' => $DeliveryDate,
                ':Destination'  => $Destination,
                ':VehicleID'    => (int)$VehicleID,
                ':EmployeeID'   => $EmployeeID, // may be null
                ':DeliveryTime' => $DeliveryTime . ':00',
                ':Notes'        => notEmpty($_POST['Notes'] ?? '') ? trim((string)$_POST['Notes']) : null,
                ':id'           => $transportId,
            ]);

            $_SESSION['flash'] = ['ok' => true, 'msg' => "Transport #".h((string)$transportId)." updated successfully."];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Update failed: " . htmlspecialchars($e->getMessage())];
        }
        header("Location: edit_transport.php?id=".$transportId);
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('htmlspecialchars', $errors))];
        header("Location: edit_transport.php?id=".$transportId);
        exit();
    }
}

// ---- Preload dropdown data (for GET display) ----
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

$drivers = $pdo->query("
    SELECT EmployeeID, Name, Role
    FROM employee
    WHERE Role IS NOT NULL AND LOWER(Role) LIKE '%driver%'
    ORDER BY Name ASC
")->fetchAll();
$driversExist = count($drivers) > 0;

// ---- Derive initial values for the form (from DB) ----
$val = [
    'OrderID'      => $cur['OrderID'],
    'STATUS'       => $cur['STATUS'] ?? 'Scheduled',
    'DeliveryDate' => $cur['DeliveryDate'] ? (string)$cur['DeliveryDate'] : '',
    'DeliveryTime' => $cur['DeliveryTime'] ? substr((string)$cur['DeliveryTime'], 0, 5) : '',
    'Destination'  => $cur['Destination'] ?? '',
    'VehicleID'    => $cur['VehicleID'] ?? '',
    'EmployeeID'   => $cur['EmployeeID'] ?? '',
    'Notes'        => $cur['Notes'] ?? '',
];

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business style ---- */
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
.btn-danger{background:#dc2626;color:#fff;border:none;}
.btn-danger:hover{background:#b91c1c}
.btn-link{border:none;background:none;color:#2563eb;cursor:pointer;padding:0;font-size:12px}
.btn-link:hover{text-decoration:underline}
</style>

<div class="container">
  <div class="card">
    <h1>Edit Transport #<?= (int)$transportId ?></h1>

    <div class="headerbar">
      <div class="hint">Update schedule, vehicle, and driver assignment.</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="transport.php">Back to Report</a>
        <a class="btn" href="add_transport.php">+ Add New</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <?php if (!$driversExist): ?>
      <div class="alert-info">
        <strong>Note:</strong> No employees with Role “Driver” were found. You can update this transport without a driver, or add a driver via the Employee module and assign later.
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
                $sel = ($val['OrderID'] !== null && (int)$val['OrderID'] === (int)$o['OrderID']) ? 'selected' : '';
              ?>
              <option value="<?= (int)$o['OrderID'] ?>" <?= $sel ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Link to invoice/customer if this transport serves a specific order.</div>
        </div>

        <!-- Status -->
        <div>
          <div class="label">Status <span class="req">*</span></div>
          <select class="input" name="STATUS" required>
            <?php foreach (['Pending','Scheduled','Dispatched','Delivered','Cancelled'] as $st): ?>
              <option value="<?= h($st) ?>" <?= ($val['STATUS'] === $st ? 'selected' : '') ?>><?= h($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Delivery Date -->
        <div>
          <div class="label">Delivery Date <span class="req">*</span></div>
          <input class="input" type="date" name="DeliveryDate" required
                 value="<?= h($val['DeliveryDate']) ?>">
          <div class="hint">Must be today or later.</div>
          <div id="dateErr" class="inline-error">Invalid or past date.</div>
        </div>

        <!-- Delivery Time -->
        <div>
          <div class="label">Delivery Time <span class="req">*</span></div>
          <input class="input" type="time" name="DeliveryTime" required
                 value="<?= h($val['DeliveryTime']) ?>">
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
                $sel = ((int)$val['VehicleID'] === (int)$v['VehicleID']) ? 'selected' : '';
              ?>
              <option value="<?= (int)$v['VehicleID'] ?>" <?= $sel ?>><?= h($vehLbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Driver (shown only if drivers exist) -->
        <?php if ($driversExist): ?>
          <div>
            <div class="label">Driver <span class="req">*</span></div>
            <select class="input" name="EmployeeID" required>
              <option value="">— Select Driver —</option>
              <?php foreach ($drivers as $d): ?>
                <?php
                  $role = $d['Role'] ?: 'Driver';
                  $sel = ((int)$val['EmployeeID'] === (int)$d['EmployeeID']) ? 'selected' : '';
                ?>
                <option value="<?= (int)$d['EmployeeID'] ?>" <?= $sel ?>><?= h($d['Name'].' • '.$role) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Only employees with Role containing “Driver” are listed.</div>
          </div>
        <?php endif; ?>

        <!-- Destination -->
        <div style="grid-column:1/-1">
          <div class="label">Destination <span class="req">*</span></div>
          <input class="input" type="text" name="Destination" maxlength="255" required
                 value="<?= h($val['Destination']) ?>"
                 placeholder="e.g., 45/3, Sri Jayewardenepura, Kotte">
        </div>

        <!-- Notes -->
        <div style="grid-column:1/-1">
          <div class="label">Notes</div>
          <textarea class="input" name="Notes" rows="3" placeholder="Special instructions, contact windows, unloading notes..."><?= h($val['Notes']) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Changes</button>
        <a class="btn" href="transport.php">Cancel</a>
        <form method="post" action="transport.php" style="display:inline" onsubmit="return confirm('Delete transport #<?= (int)$transportId ?>?');">
          <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="TransportID" value="<?= (int)$transportId ?>">
          <button class="btn btn-danger" type="submit">Delete</button>
        </form>
      </div>
    </form>
  </div>
</div>

<script>
// Client guards: date >= today, time format
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

  // Button tactile feedback
  const saveBtn = document.getElementById('saveBtn');
  saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
  saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
})();
</script>

<?php include 'footer.php'; ?>
