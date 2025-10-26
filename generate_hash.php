<?php
// Change the password below to what you want to hash
$password = "staff123";


// Generate the bcrypt hash
$hash = password_hash($password, PASSWORD_DEFAULT);

// Display the result
echo "<h2>Generated Hash</h2>";
echo "<p>Plain Password: <b>$password</b></p>";
echo "<p>Bcrypt Hash:</p>";
echo "<textarea rows='2' cols='100'>$hash</textarea>";
?>
