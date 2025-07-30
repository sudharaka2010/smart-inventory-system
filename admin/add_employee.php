<link rel="stylesheet" href="../assets/css/add_employee.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $role = $conn->real_escape_string($_POST['role']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $address = $conn->real_escape_string($_POST['address']);
    $salary = floatval($_POST['salary']);

    // Handle image upload
    $image = $_FILES['image']['name'];
    $target = "../images/employee/" . basename($image);
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        // Image uploaded successfully
    } else {
        echo "<p class='error'>Image upload failed. Please try again.</p>";
    }

    $conn->query("INSERT INTO employee (Name, Role, Contact, Address, Salary, Image) 
                  VALUES ('$name', '$role', '$contact', '$address', $salary, '$image')");
    header("Location: employee.php");
    exit();
}

include 'header.php'; 
include 'sidebar.php'; 
?>

<head>
    <meta charset="UTF-8">
    <title>Add Employee</title>
</head>
<body>
    <div class="form-container">
        <div class="form-card">
            <h1 class="form-title">Add New Employee</h1>
            <p class="form-subtitle">Fill in the details below to register a new employee.</p>
            <form method="POST" enctype="multipart/form-data" class="employee-form">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" id="name" required placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <input type="text" name="role" id="role" required placeholder="Enter employee role">
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number:</label>
                    <input type="text" name="contact" id="contact" required placeholder="Enter contact number">
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea name="address" id="address" rows="3" required placeholder="Enter address"></textarea>
                </div>
                <div class="form-group">
                    <label for="salary">Salary (LKR):</label>
                    <input type="number" name="salary" id="salary" step="0.01" required placeholder="Enter salary">
                </div>
                <div class="form-group">
                    <label for="image">Upload Image:</label>
                    <input type="file" name="image" id="image" accept="image/*" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
</body>
<?php include 'footer.php'; ?>
