<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/supplier.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $address = $conn->real_escape_string($_POST['address']);

    $conn->query("INSERT INTO supplier (NAME, Contact, Address) 
                  VALUES ('$name', '$contact', '$address')");
    header("Location: supplier.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Supplier</title>
</head>
<body>
    <h1>Add New Supplier</h1>
    <form method="POST">
        Name: <input type="text" name="name" required><br><br>
        Contact: <input type="text" name="contact" required><br><br>
        Address: <textarea name="address" required></textarea><br><br>
        <button type="submit">Add Supplier</button>
    </form>
</body>
</html>
