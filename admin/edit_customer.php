<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/customer.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$id = intval($_GET['id']);
$customer = $conn->query("SELECT * FROM customer WHERE CustomerID=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);

    $conn->query("UPDATE customer SET 
                  NAME='$name', Email='$email', Phone='$phone', Address='$address' 
                  WHERE CustomerID=$id");
    header("Location: customer.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Customer</title>
</head>
<body>
    <h1>Edit Customer</h1>
    <form method="POST">
        Name: <input type="text" name="name" value="<?php echo htmlspecialchars($customer['NAME']); ?>" required><br><br>
        Email: <input type="email" name="email" value="<?php echo htmlspecialchars($customer['Email']); ?>" required><br><br>
        Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($customer['Phone']); ?>" required><br><br>
        Address: <textarea name="address" required><?php echo htmlspecialchars($customer['Address']); ?></textarea><br><br>
        <button type="submit">Update Customer</button>
    </form>
</body>
</html>
