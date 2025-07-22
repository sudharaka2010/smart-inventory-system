
<link rel="stylesheet" href="../assets/css/add_supplier.css">
<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $address = $conn->real_escape_string($_POST['address']);

    // Server-side phone validation (10 digits only)
    if (!preg_match('/^[0-9]{10}$/', $contact)) {
        $error = "Invalid phone number. Please enter a 10-digit number.";
    } else {
        $conn->query("INSERT INTO supplier (NAME, Contact, Address) 
                      VALUES ('$name', '$contact', '$address')");
        header("Location: supplier.php");
        exit();
    }
}

include 'header.php';
include 'sidebar.php';
?>



<body>
    <div class="page-wrapper">
        <div class="form-container">
            <div class="form-card">
                <h2 class="form-title">Add New Supplier</h2>
                <p class="form-subtitle">Please fill in the details below to register a new supplier.</p>

                <!-- Show server-side error -->
                <?php if (!empty($error)): ?>
                    <p style="color: red; text-align: center;"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form method="POST" class="supplier-form" novalidate>
                    <div class="form-group">
                        <label for="name">Supplier Name</label>
                        <input type="text" id="name" name="name" placeholder="Enter supplier name" required>
                    </div>

                    <div class="form-group">
                        <label for="contact">Contact</label>
                        <input type="tel" id="contact" name="contact"
                               placeholder="Enter 10-digit contact number"
                               pattern="[0-9]{10}" maxlength="10"
                               title="Enter a valid 10-digit phone number"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'');"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" placeholder="Enter supplier address" required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Add Supplier</button>
                </form>
            </div>
        </div>
    </div>
</body>
