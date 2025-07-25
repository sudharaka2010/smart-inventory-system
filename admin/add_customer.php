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

    $conn->query("INSERT INTO customer (Name, Email, Phone, Address) 
                  VALUES ('$name', '$email', '$phone', '$address')");
    header("Location: customer.php");
    exit();
}

include 'header.php';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Customer</title>
</head>
<body>
    <div class="customer-wrapper">
        <div class="form-container">
            <h1 class="form-title">Add New Customer</h1>
            <form method="POST" class="customer-form" novalidate>
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" id="name" required placeholder="e.g., Kasun Perera">
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" required 
                           placeholder="e.g., kasun@example.com"
                           title="Enter a valid email address">
                </div>

                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" name="phone" id="phone" required 
                           pattern="0[0-9]{9}" maxlength="10"
                           title="Phone number must be 10 digits and start with 0"
                           placeholder="e.g., 0712345678">
                </div>

                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea name="address" id="address" required 
                              placeholder="e.g., No 123, Main Street, Colombo."></textarea>
                </div>

                <button type="submit" class="submit-btn">Add Customer</button>
            </form>
        </div>
    </div>
</body>
</html>

<?php include 'footer.php'; ?>
