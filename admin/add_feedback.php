<?php
// add_feedback.php (FULL UPDATED)
// Style/structure mirrors your add_supplier.php sample (white theme, card, alerts)
// Uses PDO, CSRF (_csrf), PRG flash, strict validation, optional duplicate warning

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

// ---- Helpers ----
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post_str(string $k): string { return trim((string)($_POST[$k] ?? '')); }

// ---- POST: Add Feedback ----
$errors = [];
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['_csrf']) &&
    hash_equals($CSRF, (string)$_POST['_csrf'])
) {
    $CustomerID    = (int)($_POST['CustomerID'] ?? 0);
    $Comments      = post_str('Comments');
    $Rating        = (int)($_POST['Rating'] ?? 0);
    $DateSubmitted = post_str('DateSubmitted'); // optional (YYYY-MM-DD)

    // ---- VALIDATION ----
    if ($CustomerID <= 0) {
        $errors[] = "Please select a customer.";
    }
    if ($Rating < 1 || $Rating > 5) {
        $errors[] = "Rating must be between 1 and 5.";
    }
    if ($Comments === '' || mb_strlen($Comments) < 3) {
        $errors[] = "Please enter a brief comment (at least 3 characters).";
    }
    // DateSubmitted: default today; if provided, must be a valid date and not in the future
    if ($DateSubmitted !== '') {
        $ts = strtotime($DateSubmitted);
        if ($ts === false) {
            $errors[] = "Invalid date format.";
        } else {
            $day = date('Y-m-d', $ts);
            if ($day > date('Y-m-d')) {
                $errors[] = "Date cannot be in the future.";
            }
            $DateSubmitted = $day;
        }
    } else {
        $DateSubmitted = date('Y-m-d');
    }

    // Soft duplicate warning (same customer + same date); do not block, just warn
    $dupWarn = '';
    if (!$errors) {
        $chk = $pdo->prepare("SELECT COUNT(*) AS c FROM feedback WHERE CustomerID = ? AND DateSubmitted = ?");
        $chk->execute([$CustomerID, $DateSubmitted]);
        $row = $chk->fetch();
        if (($row['c'] ?? 0) > 0) {
            $dupWarn = "Note: This customer already has feedback on {$DateSubmitted}.";
        }
    }

    // ---- INSERT ----
    if (!$errors) {
        try {
            $ins = $pdo->prepare("
               INSERT INTO feedback (CustomerID, Comments, Rating, DateSubmitted)
               VALUES (?, ?, ?, ?)
            ");
            $ins->execute([$CustomerID, $Comments, $Rating, $DateSubmitted]);

            $msg = "Feedback added successfully.";
            if ($dupWarn !== '') $msg .= " " . h($dupWarn);

            $_SESSION['flash'] = ['ok' => true, 'msg' => $msg];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['ok' => false, 'msg' => "Insert failed: " . h($e->getMessage())];
        }
        // PRG
        header("Location: add_feedback.php");
        exit();
    } else {
        $_SESSION['flash'] = ['ok' => false, 'msg' => implode('<br>', array_map('h', $errors))];
        header("Location: add_feedback.php");
        exit();
    }
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Customers for dropdown ----
$customers = [];
try {
    $q = $pdo->query("SELECT CustomerID, COALESCE(NULLIF(TRIM(NAME),''),'(Unnamed)') AS NAME FROM customer ORDER BY NAME");
    $customers = $q->fetchAll();
} catch (Throwable $e) {
    $customers = [];
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- White, business style (same structure) — brand blue (#3b5683) ---- */
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
  border:1px solid var(--border);
}
.card h1{
  font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);margin:0;
  color:var(--text);
}

/* Headerbar */
.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px}
.headerbar a{text-decoration:none;color:var(--brand);}
.headerbar a:hover{text-decoration:underline}

/* Alerts (semantic colors preserved) */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Form layout */
.form-wrap{padding:16px 20px 22px 20px;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}

.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted)}
.input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;color:var(--text);
  transition:border .2s, box-shadow .2s, background .2s, color .2s;
}
.input:focus,select:focus,textarea:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);
}
.hint{font-size:12px;color:var(--muted);margin-top:6px}
.req{color:#dc2626}
.inline-error{color:#dc2626;font-size:12px;margin-top:6px;display:none}

/* Buttons */
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{
  padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid var(--border);background:#fff;color:var(--text);
  transition:background .2s, color .2s, box-shadow .2s, filter .2s;
}
.btn:hover{background:var(--tint)}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring)}
.btn-primary{
  background:var(--brand);color:#fff;border:1px solid var(--brand);
}
.btn-primary:hover{background:var(--brand);filter:brightness(1.05)}
.btn-primary:active{background:var(--brand-dark)}
</style>


<div class="container">
  <div class="card">
    <h1>Add Feedback</h1>

    <div class="headerbar">
      <div class="hint">Record customer feedback with rating (1–5) and date.</div>
      <a class="btn" href="feedback.php">View Feedback</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>">
        <?= $flash['msg'] ?>
      </div>
    <?php endif; ?>

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">

      <div class="grid">
        <!-- Customer -->
        <div>
          <div class="label">Customer <span class="req">*</span></div>
          <select class="input" name="CustomerID" required>
            <option value="">— Select Customer —</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int)$c['CustomerID'] ?>" <?= (isset($_POST['CustomerID']) && (int)$_POST['CustomerID']===(int)$c['CustomerID'])?'selected':''; ?>>
                #<?= (int)$c['CustomerID'] ?> — <?= h($c['NAME']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Choose the customer who provided the feedback.</div>
        </div>

        <!-- Rating -->
        <div>
          <div class="label">Rating (1–5) <span class="req">*</span></div>
          <select class="input" name="Rating" required>
            <?php $cur = (int)($_POST['Rating'] ?? 5); ?>
            <?php for ($i=1; $i<=5; $i++): ?>
              <option value="<?= $i ?>" <?= $cur===$i?'selected':''; ?>><?= $i ?></option>
            <?php endfor; ?>
          </select>
          <div class="hint">1 = Very Poor, 5 = Excellent.</div>
        </div>

        <!-- Date -->
        <div>
          <div class="label">Date (optional)</div>
          <input class="input" type="date" name="DateSubmitted"
                 max="<?= h(date('Y-m-d')) ?>"
                 value="<?= h((string)($_POST['DateSubmitted'] ?? '')) ?>">
          <div class="hint">Defaults to today if empty.</div>
        </div>

        <!-- Comments -->
        <div style="grid-column:1 / -1">
          <div class="label">Comments <span class="req">*</span></div>
          <textarea class="input" name="Comments" rows="4" placeholder="Write the feedback here (min 3 characters)"><?= h((string)($_POST['Comments'] ?? '')) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" id="saveBtn">Save Feedback</button>
        <button class="btn" type="reset">Clear</button>
      </div>
    </form>
  </div>
</div>

<script>
// Save button tactile feedback
const saveBtn = document.getElementById('saveBtn');
saveBtn?.addEventListener('mousedown', ()=> saveBtn.style.transform='translateY(1px)');
saveBtn?.addEventListener('mouseup',   ()=> saveBtn.style.transform='');
</script>

<?php include 'footer.php'; ?>
