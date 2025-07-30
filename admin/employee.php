
<link rel="stylesheet" href="/rbstorsg/assets/css/employee.css">

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
include 'header.php'; 
include 'sidebar.php'; 
?>

    <main class="main-content">
    <section class="content-section">
        <div class="employee-list-container">
            <h1>Employee List</h1>
            <a href="add_employee.php" class="btn btn-add">+ Add New Employee</a>
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Salary (LKR)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td>
                            <img src="../images/employee/<?php echo htmlspecialchars($row['Image']); ?>" alt="Employee Image" class="employee-img">
                        </td>
                         <td><?php echo htmlspecialchars($row['EmployeeID']); ?></td>
                        <td><?php echo htmlspecialchars($row['NAME']); ?></td>
                        <td><?php echo htmlspecialchars($row['Role']); ?></td>
                        <td><?php echo htmlspecialchars($row['Contact']); ?></td>
                        <td><?php echo htmlspecialchars($row['Address']); ?></td>
                        <td>LKR <?php echo number_format($row['Salary'], 2); ?></td>
                        <td>
                            <a href="edit_employee.php?id=<?php echo $row['EmployeeID']; ?>" class="btn btn-edit">Edit</a>
                            <a href="?delete=<?php echo $row['EmployeeID']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this employee?');">Delete</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</main>


<?php include 'footer.php'; ?>
