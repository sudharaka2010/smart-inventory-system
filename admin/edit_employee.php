<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/employee.css">


<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$id = intval($_GET['id']);
$employee = $conn->query("SELECT * FROM employee WHERE EmployeeID=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $role = $conn->real_escape_string($_POST['role']);
    $salary = floatval($_POST['salary']);

    $conn->query("UPDATE employee SET 
                  NAME='$name', Role='$role', Salary=$salary 
                  WHERE EmployeeID=$id");
    header("Location: employee.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Employee</title>
</head>
<body>
    <h1>Edit Employee</h1>
    <form method="POST">
        Name: <input type="text" name="name" value="<?php echo htmlspecialchars($employee['NAME']); ?>" required><br><br>
        Role: <input type="text" name="role" value="<?php echo htmlspecialchars($employee['Role']); ?>" required><br><br>
        Salary: <input type="text" name="salary" value="<?php echo $employee['Salary']; ?>" required><br><br>
        <button type="submit">Update Employee</button>
    </form>
</body>
</html>
