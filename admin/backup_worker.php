<?php
/* ---------------------------------------------------------
   RB Stores — Backup Worker (Hardened)
   Usage:
     require_once 'backup_worker.php';
     [$ok, $msg] = run_backup($pdo, mode:'manual'|'auto');
   --------------------------------------------------------- */

declare(strict_types=1);

/* ------------ SETTINGS LOADER (never returns bool) ------------ */
function load_settings(PDO $pdo): array {
  $row = $pdo->query("SELECT * FROM backup_settings WHERE id=1")->fetch();
  if (!$row) {
    $row = [
      'is_enabled'     => 1,
      'schedule'       => 'none',      // none|daily|weekly|monthly
      'time_of_day'    => '02:00:00',
      'day_of_week'    => 1,           // 1=Mon..7=Sun
      'day_of_month'   => 1,           // 1..28
      'retention_days' => 14,
      'notify_email'   => null,
      'backup_folder'  => (stripos(PHP_OS, 'WIN') === 0) ? 'C:\backups' : '/var/backups/rb_stores',
      'compression'    => 'gz',        // gz|none
    ];
  }
  // normalize
  $row['is_enabled']     = (int)($row['is_enabled']     ?? 1);
  $row['schedule']       = (string)($row['schedule']    ?? 'none');
  $row['time_of_day']    = substr((string)($row['time_of_day'] ?? '02:00:00'), 0, 8);
  $row['day_of_week']    = max(1, min(7, (int)($row['day_of_week']  ?? 1)));
  $row['day_of_month']   = max(1, min(28,(int)($row['day_of_month'] ?? 1)));
  $row['retention_days'] = max(1, (int)($row['retention_days'] ?? 14));
  $row['compression']    = in_array(($row['compression'] ?? 'gz'), ['gz','none'], true) ? $row['compression'] : 'gz';
  $row['backup_folder']  = (string)($row['backup_folder'] ?? '');
  return $row;
}

/* --------------------------- MAIN ---------------------------- */
function run_backup(PDO $pdo, string $mode='manual'): array {
  $t0 = hrtime(true);

  // Always get an ARRAY (never bool)
  $cfg = load_settings($pdo);

  // Skip auto if disabled; allow manual regardless
  if ($mode === 'auto' && (empty($cfg['is_enabled']) || ($cfg['schedule'] ?? 'none') === 'none')) {
    log_row($pdo, $mode, 'warning', 'Auto backup skipped (disabled).', null, null, dur($t0));
    update_next_run($pdo, $cfg); // keep next_run_at in sync
    return [true, 'Auto backup skipped (disabled).'];
  }

  // Destination folder
  $folder = rtrim($cfg['backup_folder'] ?: ((stripos(PHP_OS, 'WIN') === 0) ? 'C:\backups' : '/var/backups/rb_stores'), "/\\");
  if (!is_dir($folder)) { @mkdir($folder, 0775, true); }
  if (!is_dir($folder)) {
    $msg = "Backup folder not found / not writable: {$folder}";
    log_row($pdo, $mode, 'failed', $msg, null, null, dur($t0));
    update_next_run($pdo, $cfg);
    return [false, $msg];
  }

  $dateTag   = date('Ymd_His');
  $basePath  = $folder . DIRECTORY_SEPARATOR . "rb_stores_db_{$dateTag}.sql";
  $compress  = (($cfg['compression'] ?? 'gz') === 'gz');

  // DB creds — align to your project conn
  $db_host = '127.0.0.1';
  $db_user = 'root';
  $db_pass = '';
  $db_name = 'rb_stores_db';

  // Try mysqldump first
  $isWin = stripos(PHP_OS, 'WIN') === 0;
  $mysqldumpPaths = $isWin
    ? ['C:\xampp\mysql\bin\mysqldump.exe', 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe', 'mysqldump.exe']
    : ['mysqldump'];

  $dumpBin = null;
  foreach ($mysqldumpPaths as $p) {
    if (!$isWin || file_exists($p)) { $dumpBin = $p; break; }
  }

  $filePath = $basePath;
  $usedDump = false;

  try {
    if ($dumpBin) {
      // Build a safe command
      if ($isWin) {
        $passPart = $db_pass !== '' ? " -p\"{$db_pass}\"" : "";
        $cmd = "\"{$dumpBin}\" -h {$db_host} -u \"{$db_user}\"{$passPart} --routines --events --triggers --single-transaction --skip-lock-tables --databases {$db_name} > \"{$filePath}\"";
        @exec($cmd, $o, $ret);
      } else {
        $passPart = $db_pass !== '' ? " -p" . escapeshellarg($db_pass) : "";
        $cmd = escapeshellcmd($dumpBin)
             . " -h " . escapeshellarg($db_host)
             . " -u " . escapeshellarg($db_user)
             . $passPart
             . " --routines --events --triggers --single-transaction --skip-lock-tables"
             . " --databases " . escapeshellarg($db_name)
             . " > " . escapeshellarg($filePath);
        @exec($cmd, $o, $ret);
      }
      if (file_exists($filePath) && filesize($filePath) > 0) { $usedDump = true; }
    }

    // Fallback: simple export via PDO
    if (!$usedDump) {
      $fh = @fopen($filePath, 'w');
      if (!$fh) throw new RuntimeException("Cannot create file: {$filePath}");
      fwrite($fh, "-- RB Stores simple export " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n");

      $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
      foreach ($tables as $tbl) {
        // Structure
        $row = $pdo->query("SHOW CREATE TABLE `$tbl`")->fetch(PDO::FETCH_ASSOC);
        if (!isset($row['Create Table'])) continue;
        fwrite($fh, "\n-- Structure for `$tbl`\nDROP TABLE IF EXISTS `$tbl`;\n".$row['Create Table'].";\n");

        // Data
        $stmt = $pdo->query("SELECT * FROM `$tbl`");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $cols = array_map(fn($c)=>"`$c`", array_keys($r));
          $vals = array_map(fn($v)=> $v===null ? "NULL" : $pdo->quote((string)$v), array_values($r));
          fwrite($fh, "INSERT INTO `$tbl`(".implode(',',$cols).") VALUES (".implode(',',$vals).");\n");
        }
      }
      fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
      fclose($fh);
    }

    // Optional gzip
    if ($compress && file_exists($filePath)) {
      $gzPath = $filePath . ".gz";
      $gz = @gzopen($gzPath, 'wb9');
      $in = @fopen($filePath, 'rb');
      if ($gz && $in) {
        while (!feof($in)) { gzwrite($gz, fread($in, 1024 * 512)); }
        fclose($in); gzclose($gz);
        @unlink($filePath);
        $filePath = $gzPath;
      } else {
        if ($in) fclose($in);
        if ($gz) gzclose($gz);
        // If compression failed, keep original .sql
      }
    }

    // Retention
    $deletedCount = cleanup_old($folder, (int)$cfg['retention_days']);

    $dur  = dur($t0);
    $size = file_exists($filePath) ? filesize($filePath) : null;
    $msg  = "Backup completed." . ($deletedCount ? " Deleted {$deletedCount} old file(s)." : "");
    log_row($pdo, $mode, 'success', $msg, $filePath, $size, $dur);
    update_next_run($pdo, $cfg);

    // Optional mail
    if (!empty($cfg['notify_email'])) {
      send_result_email($cfg['notify_email'], true, $msg, $filePath, $size, $dur);
    }
    return [true, $msg];

  } catch (Throwable $e) {
    $dur = dur($t0);
    $msg = "Backup failed: " . $e->getMessage();
    log_row($pdo, $mode, 'failed', $msg, null, null, $dur);
    if (!empty($cfg['notify_email'])) {
      send_result_email($cfg['notify_email'], false, $msg, null, null, $dur);
    }
    update_next_run($pdo, $cfg); // still compute next
    return [false, $msg];
  }
}

/* ------------------------ HELPERS ------------------------- */

function log_row(PDO $pdo, string $mode, string $status, string $msg, ?string $file, ?int $size, int $durMs): void {
  $stmt = $pdo->prepare("
    INSERT INTO backup_log (RunAt, Mode, Status, Message, FilePath, SizeBytes, DurationMs)
    VALUES (NOW(),?,?,?,?,?,?)
  ");
  $stmt->execute([$mode, $status, mb_substr($msg, 0, 490), $file, $size, $durMs]);

  // Keep last_run_at fresh for the UI
  $pdo->exec("UPDATE backup_settings SET last_run_at=NOW() WHERE id=1");
}

function dur(int $t0): int {
  return (int) round((hrtime(true) - $t0) / 1_000_000); // ms
}

function cleanup_old(string $folder, int $retentionDays): int {
  $count = 0;
  if (!is_dir($folder)) return 0;
  $cut = time() - ($retentionDays * 86400);
  foreach (glob($folder . DIRECTORY_SEPARATOR . "rb_stores_db_*.sql*") ?: [] as $f) {
    $mt = @filemtime($f);
    if ($mt !== false && $mt < $cut) { @unlink($f); $count++; }
  }
  return $count;
}

/* ---- next_run_at calculator: SAFE even if settings row missing ---- */
function update_next_run(PDO $pdo, array $cfg): void {
  try {
    if (empty($cfg['is_enabled']) || ($cfg['schedule'] ?? 'none') === 'none') {
      $pdo->exec("UPDATE backup_settings SET next_run_at=NULL WHERE id=1");
      return;
    }
    $tz   = new DateTimeZone('Asia/Colombo');
    $now  = new DateTime('now', $tz);
    $time = $cfg['time_of_day'] ?? '02:00:00';
    [$H,$M,$S] = array_map('intval', explode(':', substr($time,0,8)));

    $next = (clone $now)->setTime($H,$M,$S);
    $sch  = $cfg['schedule'];

    if ($sch === 'daily') {
      if ($next <= $now) $next->modify('+1 day');

    } elseif ($sch === 'weekly') {
      $target = max(1, min(7, (int)($cfg['day_of_week'] ?? 1))); // 1=Mon..7=Sun
      while ((int)$next->format('N') !== $target || $next <= $now) $next->modify('+1 day');

    } elseif ($sch === 'monthly') {
      $dom = max(1, min(28,(int)($cfg['day_of_month'] ?? 1)));
      $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $dom);
      if ($next <= $now) {
        $next->modify('first day of next month');
        $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $dom);
      }
    } else {
      // Unknown schedule -> clear next
      $pdo->exec("UPDATE backup_settings SET next_run_at=NULL WHERE id=1");
      return;
    }

    $stmt = $pdo->prepare("UPDATE backup_settings SET next_run_at=? WHERE id=1");
    $stmt->execute([$next->format('Y-m-d H:i:s')]);
  } catch (Throwable $e) {
    // swallow scheduler errors
  }
}

function send_result_email(string $to, bool $success, string $message, ?string $filePath, ?int $size, int $durMs): void {
  $sub  = $success ? "RB Stores Backup: SUCCESS" : "RB Stores Backup: FAILED";
  $sizeTxt = $size !== null ? number_format($size/1024,1).' KB' : 'N/A';
  $body = "Hello,\n\n".
          "Backup result: ".($success?'SUCCESS':'FAILED')."\n".
          "Time: ".date('Y-m-d H:i:s')."\n".
          "Duration: {$durMs} ms\n".
          "File: ".($filePath ?: 'N/A')."\n".
          "Size: {$sizeTxt}\n".
          "Message: {$message}\n\n".
          "— RB Stores System";
  @mail($to, $sub, $body, "From: no-reply@rbstores.local");
}
