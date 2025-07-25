<link rel="stylesheet" href="../assets/css/add_supplier.css">
<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $address = $conn->real_escape_string($_POST['address']);
    $email = $conn->real_escape_string($_POST['email']);
    $company = $conn->real_escape_string($_POST['company']);
    $category = $conn->real_escape_string($_POST['category']);
    $status = $conn->real_escape_string($_POST['status']);
    $paymentTerms = $conn->real_escape_string($_POST['payment_terms']);
    $gstNumber = $conn->real_escape_string($_POST['gst_number']);
    $notes = $conn->real_escape_string($_POST['notes']);

    if (!preg_match('/^[0-9]{10}$/', $contact)) {
        $error = "Invalid phone number. Please enter a 10-digit number.";
    } else {
        $conn->query("INSERT INTO supplier 
            (NAME, Contact, Address, Email, CompanyName, Category, Status, PaymentTerms, GSTNumber, Notes)
            VALUES 
            ('$name', '$contact', '$address', '$email', '$company', '$category', '$status', '$paymentTerms', '$gstNumber', '$notes')");
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
                <p class="form-subtitle">Fill in all fields to register a new supplier.</p>

                <?php if (!empty($error)): ?>
                    <p style="color: red; text-align: center;"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form method="POST" class="supplier-form" novalidate>
                    <div class="form-group"><label>Supplier Name</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Contact Number</label>
                        <input type="tel" name="contact" pattern="[0-9]{10}" maxlength="10" required
                            oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                    </div>
                    <div class="form-group"><label>Address</label><textarea name="address" required></textarea></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Company Name</label><input type="text" name="company"></div>
                    <div class="form-group"><label>Category</label><input type="text" name="category"></div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Payment Terms</label><input type="text" name="payment_terms"></div>
                    <div class="form-group"><label>GST Number</label><input type="text" name="gst_number"></div>
                    <div class="form-group"><label>Notes</label><textarea name="notes"></textarea></div>

                    <button type="submit" class="btn-submit">Add Supplier</button>
                </form>
            </div>
        </div>
    </div>
</body>
<?php 
include 'footer.php';