<?php
// Check if JAWSDB_URL is available (Heroku environment)
if (getenv("JAWSDB_URL")) {
    // Parse JawsDB MySQL URL
    $url = parse_url(getenv("JAWSDB_URL"));

    $host = $url["host"];
    $user = $url["user"];
    $pass = $url["pass"];
    $dbname = substr($url["path"], 1);
} else {
    // Local XAMPP MySQL settings
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "rb_stores_db";
}

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
