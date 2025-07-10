<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/employee.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $role = $conn->real_escape_string($_POST['role']);
    $salary = floatval($_POST['salary']);

    $conn->query("INSERT INTO employee (NAME, Role, Salary) 
                  VALUES ('$name', '$role', $salary)");
    header("Location: employee.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Employee</title>
</head>
<body>
    <h1>Add New Employee</h1>
    <form method="POST">
        Name: <input type="text" name="name" required><br><br>
        Role: <input type="text" name="role" required><br><br>
        Salary: <input type="text" name="salary" required><br><br>
        <button type="submit">Add Employee</button>
    </form>
</body>
</html>
