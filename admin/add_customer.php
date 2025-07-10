<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/customer.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);

    $conn->query("INSERT INTO customer (NAME, Email, Phone, Address) 
                  VALUES ('$name', '$email', '$phone', '$address')");
    header("Location: customer.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Customer</title>
</head>
<body>
    <h1>Add New Customer</h1>
    <form method="POST">
        Name: <input type="text" name="name" required><br><br>
        Email: <input type="email" name="email" required><br><br>
        Phone: <input type="text" name="phone" required><br><br>
        Address: <textarea name="address" required></textarea><br><br>
        <button type="submit">Add Customer</button>
    </form>
</body>
</html>
