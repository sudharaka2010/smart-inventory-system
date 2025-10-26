<?php
// db_backup.php (FULL UPDATED — same structure/style as add_feedback.php)
// White card theme, CSRF (_csrf), PDO, PRG flash, tabs (?tab=logs)
// Requires: backup_worker.php, backup_settings/backup_log tables

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

// ---- helpers ----
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post_str(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function flash_set(bool $ok, string $msg): void { $_SESSION['flash'] = ['ok'=>$ok,'msg'=>$msg]; }

// ---- load settings (defaults if missing) ----
$settings = $pdo->query("SELECT * FROM backup_settings WHERE id=1")->fetch() ?: [
  'schedule'       => 'none',
  'time_of_day'    => '02:00:00',
  'day_of_week'    => 1,
  'day_of_month'   => 1,
  'retention_days' => 14,
  'notify_email'   => null,
  'backup_folder'  => 'C:/xampp/htdocs/backups',
  'compression'    => 'gz',
  'is_enabled'     => 1,
  'last_run_at'    => null,
  'next_run_at'    => null,
];

// ---- actions ----
$tab = (isset($_GET['tab']) && $_GET['tab']==='logs') ? 'logs' : 'settings';

// Save settings
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save']) && hash_equals($CSRF, (string)($_POST['_csrf'] ?? ''))) {
    $schedule       = $_POST['schedule'] ?? 'none';
    $time_of_day    = substr($_POST['time_of_day'] ?? '02:00', 0, 5) . ':00';
    $day_of_week    = max(1, min(7, (int)($_POST['day_of_week'] ?? 1)));
    $day_of_month   = max(1, min(28,(int)($_POST['day_of_month'] ?? 1)));
    $retention_days = max(1,(int)($_POST['retention_days'] ?? 14));
    $notify_email   = post_str('notify_email');
    $backup_folder  = post_str('backup_folder') ?: 'C:/xampp/htdocs/backups';
    $compression    = ($_POST['compression'] ?? 'gz') === 'none' ? 'none' : 'gz';
    $is_enabled     = isset($_POST['is_enabled']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("
          UPDATE backup_settings
             SET schedule=:s, time_of_day=:tod, day_of_week=:dow, day_of_month=:dom,
                 retention_days=:ret, notify_email=:em, backup_folder=:bf,
                 compression=:cmp, is_enabled=:ie
           WHERE id=1
        ");
        $stmt->execute([
          ':s'=>$schedule, ':tod'=>$time_of_day, ':dow'=>$day_of_week, ':dom'=>$day_of_month,
          ':ret'=>$retention_days, ':em'=>($notify_email!==''?$notify_email:null),
          ':bf'=>$backup_folder, ':cmp'=>$compression, ':ie'=>$is_enabled
        ]);
        flash_set(true, 'Backup settings saved.');
    } catch (Throwable $e) {
        flash_set(false, 'Save failed: '.h($e->getMessage()));
    }
    header("Location: db_backup.php");
    exit;
}

// Manual backup
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['backup_now']) && hash_equals($CSRF, (string)($_POST['_csrf'] ?? ''))) {
    require_once __DIR__.'/backup_worker.php';
    [$ok,$msg] = run_backup($pdo, mode:'manual');
    flash_set($ok, $msg);
    header("Location: db_backup.php?tab=logs");
    exit;
}

// ---- FLASH (PRG) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- logs (only if logs tab) ----
$logs = [];
if ($tab==='logs') {
    $logs = $pdo->query("SELECT * FROM backup_log ORDER BY LogID DESC LIMIT 100")->fetchAll();
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* ---- same white business style as add_feedback.php ---- */
:root{
  --brand:#3b5683; --brand-dark:#324a70; --ring:rgba(59,86,131,.25);
  --text:#3b5683; --muted:#6b7c97; --border:#dfe6f2; --tint:#e9eff7; --tint-hover:#dde7f6;
}
body{background:#fff;color:var(--text);margin:0;}
.container{margin-left:260px;padding:20px;max-width:980px;}
@media(max-width:992px){.container{margin-left:0;}}

/* Card */
.card{background:#fff;border-radius:14px;box-shadow:0 4px 12px rgba(34,54,82,.08);margin-bottom:24px;border:1px solid var(--border);}
.card h1{font-size:22px;font-weight:600;padding:18px 20px;border-bottom:1px solid var(--border);margin:0;color:var(--text);}

/* Headerbar */
.headerbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 0 20px;gap:12px;flex-wrap:wrap}
.headerbar .hint{color:var(--muted);font-size:14px}
.headerbar a.btn{text-decoration:none}

/* Alerts */
.alert{padding:12px 14px;border-radius:8px;margin:10px 20px;font-size:14px}
.alert-success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

/* Form */
.form-wrap{padding:16px 20px 22px 20px;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:820px){.grid{grid-template-columns:1fr;}}
.label{font-weight:600;font-size:14px;margin-bottom:6px;color:var(--muted)}
.input,select,textarea,input[type="time"],input[type="number"],input[type="email"],input[type="text"]{
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff;color:var(--text);
  transition:border .2s, box-shadow .2s;
}
.input:focus,select:focus,textarea:focus,input[type="time"]:focus,input[type="number"]:focus,input[type="email"]:focus,input[type="text"]:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--ring);
}
.hint{font-size:12px;color:var(--muted);margin-top:6px}
.req{color:#dc2626}

/* Buttons */
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.btn{
  padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid var(--border);background:#fff;color:var(--text);
  transition:background .2s, color .2s, box-shadow .2s, filter .2s;
}
.btn:hover{background:var(--tint)}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--ring)}
.btn-primary{background:var(--brand);color:#fff;border:1px solid var(--brand);}
.btn-primary:hover{filter:brightness(1.05)}
.btn-primary:active{background:var(--brand-dark)}
.btn-soft{background:#f3f6fb}
.pill{padding:6px 10px;border:1px solid var(--border);border-radius:999px;font-size:12px;background:#f8fbff}

/* Table (logs) */
.table-wrap{padding:16px 20px 22px 20px;overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:14px}
th{text-align:left;color:#475569;background:#f8fafc}
.status-success{color:#16a34a;font-weight:600}
.status-failed{color:#dc2626;font-weight:600}
.status-warning{color:#d97706;font-weight:600}
</style>

<div class="container">

  <?php if ($tab === 'settings'): ?>
  <!-- SETTINGS CARD -->
  <div class="card">
    <h1>Data Backup</h1>

    <div class="headerbar">
      <div class="hint">Configure auto-backup, retention & notifications. Run a manual backup anytime.</div>
      <div style="display:flex; gap:8px; flex-wrap:wrap">
        <a class="btn" href="db_backup.php?tab=logs"><i class="fas fa-clipboard-list"></i> View Logs</a>
        <form method="post" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
          <button class="btn btn-primary" name="backup_now" value="1" type="submit"><i class="fas fa-play-circle"></i> Backup Now</button>
        </form>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <form class="form-wrap" method="post" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="<?= h($CSRF) ?>">
      <div class="grid">
        <div>
          <div class="label">Enable</div>
          <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="is_enabled" <?= !empty($settings['is_enabled']) ? 'checked' : '' ?>> <span>Turn on automatic backups</span>
          </label>
          <div class="hint">Manual backups work even if auto is off.</div>
        </div>

        <div>
          <div class="label">Schedule</div>
          <?php $S = $settings['schedule'] ?? 'none'; ?>
          <select class="input" name="schedule" id="schedule">
            <option value="none"   <?= $S==='none'?'selected':''; ?>>— Disabled —</option>
            <option value="daily"  <?= $S==='daily'?'selected':''; ?>>Daily</option>
            <option value="weekly" <?= $S==='weekly'?'selected':''; ?>>Weekly</option>
            <option value="monthly"<?= $S==='monthly'?'selected':''; ?>>Monthly</option>
          </select>
          <div class="hint">How often should it run?</div>
        </div>

        <div>
          <div class="label">Time of day</div>
          <input class="input" type="time" name="time_of_day" value="<?= h(substr($settings['time_of_day'] ?? '02:00:00',0,5)) ?>">
          <div class="hint">Server local time (Asia/Colombo).</div>
        </div>

        <div id="wrapWeekly">
          <div class="label">Day of week (weekly)</div>
          <?php $dow = (int)($settings['day_of_week'] ?? 1); $days=[1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun']; ?>
          <select class="input" name="day_of_week">
            <?php foreach($days as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $dow===$k?'selected':''; ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="wrapMonthly">
          <div class="label">Day of month (monthly)</div>
          <input class="input" type="number" min="1" max="28" name="day_of_month" value="<?= (int)($settings['day_of_month'] ?? 1) ?>">
          <div class="hint">Use 1–28 to avoid Feb edge cases.</div>
        </div>

        <div>
          <div class="label">Retention (days)</div>
          <input class="input" type="number" min="1" name="retention_days" value="<?= (int)($settings['retention_days'] ?? 14) ?>">
          <div class="hint">Old backups will be deleted automatically.</div>
        </div>

        <div>
          <div class="label">Backup folder</div>
          <input class="input" type="text" name="backup_folder" value="<?= h($settings['backup_folder'] ?? 'C:/xampp/htdocs/backups') ?>">
          <div class="hint">Ensure PHP can write here. e.g., <code>C:\backups</code> or <code>/var/backups/rb_stores</code></div>
        </div>

        <div>
          <div class="label">Compression</div>
          <?php $cmp = $settings['compression'] ?? 'gz'; ?>
          <select class="input" name="compression">
            <option value="gz"   <?= $cmp==='gz'?'selected':''; ?>>Gzip (.gz)</option>
            <option value="none" <?= $cmp==='none'?'selected':''; ?>>None</option>
          </select>
        </div>

        <div>
          <div class="label">Notify email (optional)</div>
          <input class="input" type="email" name="notify_email" placeholder="admin@example.com" value="<?= h($settings['notify_email'] ?? '') ?>">
          <div class="hint">We’ll email the result after each run.</div>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit" name="save" value="1"><i class="fas fa-save"></i> Save Settings</button>
        <a class="btn" href="db_backup.php?tab=logs"><i class="fas fa-clipboard-list"></i> View Logs</a>
        <span class="pill"><strong>Last:</strong> <?= $settings['last_run_at'] ?: '—' ?></span>
        <span class="pill"><strong>Next:</strong> <?= $settings['next_run_at'] ?: '—' ?></span>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'logs'): ?>
  <!-- LOGS CARD -->
  <div class="card">
    <h1>Backup Logs</h1>

    <div class="headerbar">
      <div class="hint">Recent runs with status, file path, size and duration.</div>
      <a class="btn" href="db_backup.php"><i class="fas fa-cog"></i> Settings</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['ok'] ? 'alert-success' : 'alert-error' ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Run At</th><th>Mode</th><th>Status</th><th>Message</th><th>File</th><th>Size</th><th>Duration</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$logs): ?>
          <tr><td colspan="8" style="text-align:center;color:#6b7c97">No logs yet.</td></tr>
        <?php else: foreach($logs as $r): ?>
          <tr>
            <td><?= (int)$r['LogID'] ?></td>
            <td><?= h($r['RunAt']) ?></td>
            <td><?= h($r['Mode']) ?></td>
            <td class="status-<?= h($r['Status']) ?>"><?= h($r['Status']) ?></td>
            <td><?= h($r['Message'] ?? '') ?></td>
            <td><?= h($r['FilePath'] ?? '') ?></td>
            <td><?= $r['SizeBytes']!==null ? number_format((float)$r['SizeBytes']/1024,1).' KB' : '—' ?></td>
            <td><?= $r['DurationMs']!==null ? (int)$r['DurationMs'].' ms' : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
function syncVisibility(){
  const s = document.getElementById('schedule');
  if(!s) return;
  const v = s.value;
  const W = document.getElementById('wrapWeekly');
  const M = document.getElementById('wrapMonthly');
  if (W) W.style.display = (v==='weekly') ? '' : 'none';
  if (M) M.style.display = (v==='monthly') ? '' : 'none';
}
document.getElementById('schedule')?.addEventListener('change', syncVisibility);
syncVisibility();

// Small tactile for primary buttons
document.querySelectorAll('.btn-primary').forEach(b=>{
  b.addEventListener('mousedown', ()=> b.style.transform='translateY(1px)');
  b.addEventListener('mouseup',   ()=> b.style.transform='');
});
</script>

<?php include 'footer.php'; ?>
