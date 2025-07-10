<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/transport.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$id = intval($_GET['id']);
$transport = $conn->query("SELECT * FROM transport WHERE TransportID=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $conn->real_escape_string($_POST['status']);
    $delivery_date = $_POST['delivery_date'];

    $conn->query("UPDATE transport SET STATUS='$status', DeliveryDate='$delivery_date' 
                  WHERE TransportID=$id");
    header("Location: transport.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Transport</title>
</head>
<body>
    <h1>Edit Transport</h1>
    <form method="POST">
        Status: 
        <select name="status" required>
            <option value="Upcoming" <?php if($transport['STATUS']=='Upcoming') echo 'selected'; ?>>Upcoming</option>
            <option value="Ongoing" <?php if($transport['STATUS']=='Ongoing') echo 'selected'; ?>>Ongoing</option>
            <option value="Delayed" <?php if($transport['STATUS']=='Delayed') echo 'selected'; ?>>Delayed</option>
            <option value="Completed" <?php if($transport['STATUS']=='Completed') echo 'selected'; ?>>Completed</option>
        </select><br><br>
        Delivery Date: <input type="date" name="delivery_date" value="<?php echo $transport['DeliveryDate']; ?>" required><br><br>
        <button type="submit">Update Transport</button>
    </form>
</body>
</html>
