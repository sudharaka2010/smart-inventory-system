<?php
/* ---------------------------------------------------------
   RB Stores — Backup Cron Bridge
   Call via CLI: php backup_cron.php
   Or via web:   https://your-host/admin/backup_cron.php?key=YOUR_SECRET
   --------------------------------------------------------- */

declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');

/* Optional simple key (change it!) to prevent public access when run via web */
$CRON_KEY = 'CHANGE_ME_LONG_RANDOM';

if (PHP_SAPI !== 'cli') {
  if (!isset($_GET['key']) || $_GET['key'] !== $CRON_KEY) {
    http_response_code(403); echo "Forbidden"; exit;
  }
}

$dsn = "mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, 'root', '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  echo "DB failed\n"; exit(1);
}

$cfg = $pdo->query("SELECT * FROM backup_settings WHERE id=1")->fetch();
if (!$cfg || !$cfg['is_enabled'] || ($cfg['schedule'] ?? 'none') === 'none') {
  echo "Auto disabled.\n"; exit(0);
}

$now = new DateTime('now', new DateTimeZone('Asia/Colombo'));
$due = !empty($cfg['next_run_at']) ? DateTime::createFromFormat('Y-m-d H:i:s', $cfg['next_run_at'], new DateTimeZone('Asia/Colombo')) : null;

/* If next_run_at missing, compute it now and exit. */
if (!$due) {
  require_once __DIR__.'/backup_worker.php';
  update_next_run($pdo, $cfg);
  echo "Scheduled next run.\n"; exit(0);
}

/* Run if due (with 4-minute tolerance to allow every-5-min cron) */
$diff = $now->getTimestamp() - $due->getTimestamp();
if ($diff >= -60 && $diff <= 240) {
  require_once __DIR__.'/backup_worker.php';
  [$ok, $msg] = run_backup($pdo, mode:'auto');
  echo ($ok ? "OK: " : "FAIL: ") . $msg . "\n";
} else {
  echo "Not due. Next: ".$due->format('Y-m-d H:i:s')."\n";
}
