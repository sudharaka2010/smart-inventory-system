<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/inventory.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$id = intval($_GET['id']);
$item = $conn->query("SELECT * FROM inventoryitem WHERE ItemID=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $desc = $conn->real_escape_string($_POST['description']);
    $qty = intval($_POST['quantity']);
    $price = floatval($_POST['price']);

    $conn->query("UPDATE inventoryitem SET 
                  NAME='$name', Description='$desc', Quantity=$qty, Price=$price 
                  WHERE ItemID=$id");
    header("Location: inventory.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Inventory Item</title>
</head>
<body>
    <h1>Edit Inventory Item</h1>
    <form method="POST">
        Name: <input type="text" name="name" value="<?php echo htmlspecialchars($item['NAME']); ?>" required><br><br>
        Description: <textarea name="description"><?php echo htmlspecialchars($item['Description']); ?></textarea><br><br>
        Quantity: <input type="number" name="quantity" value="<?php echo $item['Quantity']; ?>" required><br><br>
        Price: <input type="text" name="price" value="<?php echo $item['Price']; ?>" required><br><br>
        <button type="submit">Update Item</button>
    </form>
</body>
</html>
