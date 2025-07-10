<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">

<?php
include('../includes/auth.php');

// Only Admin can access
if ($_SESSION['role'] != 'Admin') {
    echo "Unauthorized Access!";
    exit();
}

// Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "rb_stores_db";

// File name for backup
$backupFile = 'rb_stores_db_backup_' . date("Y-m-d_H-i-s") . '.sql';

// Command to export DB
$command = "mysqldump --user=$user --password=$pass --host=$host $dbname > $backupFile";

// Execute command
system($command, $output);

if (file_exists($backupFile)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
    header('Content-Length: ' . filesize($backupFile));
    readfile($backupFile);
    unlink($backupFile); // Delete after download
    exit();
} else {
    echo "Failed to create backup.";
}
?>
