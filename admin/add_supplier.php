<link rel="stylesheet" href="../assets/css/add_supplier.css">
<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($conn->real_escape_string($_POST['name']));
    $contact = trim($conn->real_escape_string($_POST['contact']));
    $address = trim($conn->real_escape_string($_POST['address']));
    $email = trim($conn->real_escape_string($_POST['email']));
    $company = trim($conn->real_escape_string($_POST['company']));
    $category = trim($conn->real_escape_string($_POST['category']));
    $status = trim($conn->real_escape_string($_POST['status']));
    $paymentTerms = trim($conn->real_escape_string($_POST['payment_terms']));
    $gstNumber = trim($conn->real_escape_string($_POST['gst_number']));
    $notes = trim($conn->real_escape_string($_POST['notes']));

    if (!preg_match('/^[0-9]{10}$/', $contact)) {
        $error = "Invalid phone number. Please enter a valid 10-digit number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("INSERT INTO supplier 
            (NAME, Contact, Address, Email, CompanyName, Category, Status, PaymentTerms, GSTNumber, Notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssss", $name, $contact, $address, $email, $company, $category, $status, $paymentTerms, $gstNumber, $notes);

        if ($stmt->execute()) {
            $success = "Supplier added successfully!";
        } else {
            $error = "Error adding supplier. Please try again.";
        }
        $stmt->close();
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
                <p class="form-subtitle">Please fill out all required fields.</p>

                <?php if ($error): ?>
                    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
                <?php elseif ($success): ?>
                    <p class="success-msg"><?= htmlspecialchars($success) ?></p>
                <?php endif; ?>

                <form method="POST" class="supplier-form" novalidate>
                    <div class="form-group">
                        <label>Supplier Name <span>*</span></label>
                        <input type="text" name="name" required>
                    </div>

                    <div class="form-group">
                        <label>Contact Number <span>*</span></label>
                        <input type="tel" name="contact" pattern="[0-9]{10}" maxlength="10" required
                            oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                    </div>

                    <div class="form-group">
                        <label>Address <span>*</span></label>
                        <textarea name="address" rows="2" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Email <span>*</span></label>
                        <input type="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company">
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category">
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Payment Terms</label>
                        <input type="text" name="payment_terms">
                    </div>

                    <div class="form-group">
                        <label>GST Number</label>
                        <input type="text" name="gst_number">
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Add Supplier</button>
                </form>
            </div>
        </div>
    </div>
</body>

<?php include 'footer.php'; ?>
