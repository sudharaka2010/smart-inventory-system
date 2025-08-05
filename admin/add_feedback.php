<link rel="stylesheet" href="../assets/css/add_feedback.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

$customers = $conn->query("SELECT CustomerID, Name FROM Customer");
$success = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerID = intval($_POST['customer_id']);
    $comments = trim($_POST['comments']);
    $rating = intval($_POST['rating']);
    $dateSubmitted = date('Y-m-d');

    if ($customerID && $comments && ($rating >= 1 && $rating <= 5)) {
        $stmt = $conn->prepare("INSERT INTO Feedback (CustomerID, Comments, Rating, DateSubmitted) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $customerID, $comments, $rating, $dateSubmitted);

        if ($stmt->execute()) {
            $success = "✅ Feedback submitted successfully!";
        } else {
            $error = "❌ Database error. Please try again.";
        }
    } else {
        $error = "❌ Please fill all fields correctly.";
    }
}
?>

<div class="main-content">
    <div class="container">
        <h1 class="page-title">Submit Customer Feedback</h1>

        <?php if ($success): ?>
            <p class="success-msg"><?= $success ?></p>
        <?php elseif ($error): ?>
            <p class="error-msg"><?= $error ?></p>
        <?php endif; ?>

        <!-- Feedback Form -->
        <form method="POST" class="feedback-form">
            <div class="form-group">
                <label for="customer_id">Customer</label>
                <select name="customer_id" id="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php while ($row = $customers->fetch_assoc()): ?>
                        <option value="<?= $row['CustomerID'] ?>"><?= htmlspecialchars($row['Name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="comments">Comments</label>
                <textarea name="comments" id="comments" rows="4" placeholder="Write feedback here..." required></textarea>
            </div>

            <div class="form-group">
                <label for="rating">Rating</label>
                <select name="rating" id="rating" required>
                    <option value="">-- Select Rating --</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?> / 5</option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="submit" class="btn-submit">Submit Feedback</button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
