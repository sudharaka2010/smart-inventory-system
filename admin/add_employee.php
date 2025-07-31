<link rel="stylesheet" href="../assets/css/add_employee.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name        = $conn->real_escape_string(trim($_POST['name']));
    $role        = $conn->real_escape_string(trim($_POST['role']));
    $contact     = $conn->real_escape_string(trim($_POST['contact']));
    $address     = $conn->real_escape_string(trim($_POST['address']));
    $salary      = floatval($_POST['salary']);
    $salaryType  = $conn->real_escape_string(trim($_POST['salary_type']));

    // Validate contact number
    if (!preg_match('/^\d{10}$/', $contact)) {
        $error = "Contact number must be exactly 10 digits.";
    } elseif ($salary <= 0) {
        $error = "Salary must be a positive number.";
    } elseif (!in_array($salaryType, ['Daily', 'Weekly', 'Monthly'])) {
        $error = "Invalid salary type selected.";
    }

    // Handle image upload
    if (empty($error) && isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $imageName = time() . '_' . basename($_FILES['image']['name']);
        $targetDir = "../images/employee/";
        $targetFile = $targetDir . $imageName;
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileType, $allowed)) {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
        } else {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $stmt = $conn->prepare("INSERT INTO employee (Name, Role, Contact, Address, Salary, SalaryType, Image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiss", $name, $role, $contact, $address, $salary, $salaryType, $imageName);
                $stmt->execute();
                $stmt->close();

                header("Location: employee.php?added=1");
                exit();
            } else {
                $error = "Image upload failed. Please try again.";
            }
        }
    } elseif (empty($error)) {
        $error = "Please upload a valid image.";
    }
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

            <?php if (!empty($error)): ?>
                <p class="form-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="employee-form" novalidate>
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
            <input
            type="tel"
            name="contact"
            id="contact"
            pattern="^0\d{9}$"
            maxlength="10"
            minlength="10"
            title="Enter a valid 10-digit Sri Lankan phone number starting with 0"
            required
            placeholder="e.g., 0712345678">
        </div>

                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea name="address" id="address" rows="3" required placeholder="Enter address"></textarea>
                </div>

                <div class="form-group">
                    <label for="salary">Salary (LKR):</label>
                    <input type="number" name="salary" id="salary" min="0" step="0.01" required placeholder="Enter salary amount">
                </div>

                <div class="form-group">
                    <label for="salary_type">Salary Type:</label>
                    <select name="salary_type" id="salary_type" required>
                        <option value="">Select Salary Type</option>
                        <option value="Daily">Daily</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Monthly">Monthly</option>
                    </select>
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
