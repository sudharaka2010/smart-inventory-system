<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/customer.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM customer WHERE CustomerID=$id");
    header("Location: customer.php");
    exit();
}

// Fetch customers
$result = $conn->query("SELECT * FROM customer");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Management</title>
</head>
<body>
    <h1>Customer List</h1>
    <a href="add_customer.php">Add New Customer</a>
    <table border="1" cellpadding="10">
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['NAME']); ?></td>
            <td><?php echo htmlspecialchars($row['Email']); ?></td>
            <td><?php echo htmlspecialchars($row['Phone']); ?></td>
            <td><?php echo htmlspecialchars($row['Address']); ?></td>
            <td>
                <a href="edit_customer.php?id=<?php echo $row['CustomerID']; ?>">Edit</a> |
                <a href="?delete=<?php echo $row['CustomerID']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
