<link rel="stylesheet" href="../assets/css/login.css">

<?php
session_start();
include('../includes/db_connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $selectedRole = $conn->real_escape_string($_POST['role']); // Role selected from frontend

    $sql = "SELECT * FROM Users WHERE Username='$username'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if ($row['Role'] !== $selectedRole) {
            $error = "You are trying to login as $selectedRole, but this account is for {$row['Role']}.";
        } elseif (password_verify($password, $row['PASSWORD'])) {
            $_SESSION['username'] = $row['Username'];
            $_SESSION['role'] = $row['Role'];

            if ($row['Role'] === 'Admin') {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../staff/dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RB Stores Login</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Top Navbar -->
    <header class="top-header">
        <div class="logo">
            <h1>RB Stores</h1>
            <p>Rain Water Solution Management System</p>
        </div>
        <nav class="top-nav">
            <a href="#">About</a>
            <a href="#">Contact</a>
            <a href="#">Support <i class="fas fa-headset"></i></a>
        </nav>
    </header>

    <!-- Login Card -->
    <div class="login-container">
        <h2>Welcome Login Portal</h2>
        <p>Please Enter your details to Sign in</p>

        <div class="role-btns">
            <button id="adminBtn" class="active">Admin</button>
            <div class="role-separator"></div>
            <button id="staffBtn">Staff</button>
        </div>

        <form action="login.php" method="POST">
            <input type="hidden" name="role" id="selectedRole" value="Admin">
            <div class="input-group">
                <input type="text" name="username" placeholder="User Name" required>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" id="password" required>
                <div class="toggle-password" id="togglePassword">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5 16.5 9.51 16.5 12 14.49 16.5 12 16.5zm0-7c-1.38 0-2.5 1.12-2.5 2.5S10.62 14.5 12 14.5s2.5-1.12 2.5-2.5S13.38 9.5 12 9.5z"/>
                    </svg>
                </div>
            </div>
            <div class="forgot-password">
                <a href="#">Forgot Password?</a>
            </div>
            <button type="submit" class="login-btn">Login</button>
             <?php if (isset($error)) echo "<div class='error-message'>$error</div>"; ?>

        </form>
    </div>

    <footer class="footer">
        Code Counter Team Group -15
    </footer>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        const selectedRoleInput = document.getElementById('selectedRole');
        const adminBtn = document.getElementById('adminBtn');
        const staffBtn = document.getElementById('staffBtn');

        togglePassword.addEventListener('click', () => {
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
        });

        adminBtn.addEventListener('click', () => {
            adminBtn.classList.add('active');
            staffBtn.classList.remove('active');
            selectedRoleInput.value = 'Admin';
        });

        staffBtn.addEventListener('click', () => {
            staffBtn.classList.add('active');
            adminBtn.classList.remove('active');
            selectedRoleInput.value = 'Staff';
        });
    </script>
</body>
</html>
