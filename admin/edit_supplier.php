<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/supplier.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$id = intval($_GET['id']);
$supplier = $conn->query("SELECT * FROM supplier WHERE SupplierID=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $address = $conn->real_escape_string($_POST['address']);

    $conn->query("UPDATE supplier SET 
                  NAME='$name', Contact='$contact', Address='$address' 
                  WHERE SupplierID=$id");
    header("Location: supplier.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Supplier</title>
</head>
<body>
    <h1>Edit Supplier</h1>
    <form method="POST">
        Name: <input type="text" name="name" value="<?php echo htmlspecialchars($supplier['NAME']); ?>" required><br><br>
        Contact: <input type="text" name="contact" value="<?php echo htmlspecialchars($supplier['Contact']); ?>" required><br><br>
        Address: <textarea name="address" required><?php echo htmlspecialchars($supplier['Address']); ?></textarea><br><br>
        <button type="submit">Update Supplier</button>
    </form>
</body>
</html>
