<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/employee.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM employee WHERE EmployeeID=$id");
    header("Location: employee.php");
    exit();
}

// Fetch employees
$result = $conn->query("SELECT * FROM employee");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Management</title>
</head>
<body>
    <h1>Employee List</h1>
    <a href="add_employee.php">Add New Employee</a>
    <table border="1" cellpadding="10">
        <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Salary</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['NAME']); ?></td>
            <td><?php echo htmlspecialchars($row['Role']); ?></td>
            <td><?php echo $row['Salary']; ?></td>
            <td>
                <a href="edit_employee.php?id=<?php echo $row['EmployeeID']; ?>">Edit</a> |
                <a href="?delete=<?php echo $row['EmployeeID']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
