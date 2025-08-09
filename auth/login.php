<?php
// ---------- BOOTSTRAP ----------
ob_start();

/* ---- HTTPS ENFORCEMENT (works on Heroku) ---- */
$proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? '');
if ($proto !== 'https') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

// Strong session config
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,        // requires HTTPS in prod
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start([
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

// Generate a CSP nonce for inline scripts
$cspNonce = base64_encode(random_bytes(16));

// Security headers
header("Content-Security-Policy: default-src 'self'; ".
       "script-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://code.jquery.com 'nonce-{$cspNonce}'; ".
       "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com; ".
       "font-src  'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; ".
       "img-src 'self' data:; ".
       "connect-src 'self'; ".
       "frame-ancestors 'self'; ");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

require_once('../includes/db_connect.php');

// ---------- CONFIG ----------
const RATE_LIMIT_WINDOW_MIN = 15;   // 15-minute window
const RATE_LIMIT_MAX_ATTEMPTS = 5;  // lock after 5 attempts
$genericError = "Invalid credentials."; // don't leak user/role existence

// Ensure table exists (safe if already created; remove if you prefer manual migration)
$conn->query("
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (username),
  KEY (ip_address),
  KEY (last_attempt)
) ENGINE=InnoDB;
");

// ---------- HELPERS ----------
function client_ip(): string {
    // Simple resolver; customize if behind proxy/elb (use X-Forwarded-For with care)
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rate_limited(mysqli $conn, ?string $username, string $ip): bool {
    $sql = "SELECT attempts, last_attempt
            FROM login_attempts
            WHERE (username <=> ?) AND ip_address = ?
              AND last_attempt >= (NOW() - INTERVAL ? MINUTE)
            ORDER BY last_attempt DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $win = RATE_LIMIT_WINDOW_MIN;
    $stmt->bind_param("ssi", $username, $ip, $win);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return (int)$row['attempts'] >= RATE_LIMIT_MAX_ATTEMPTS;
    }
    return false;
}

function record_attempt(mysqli $conn, ?string $username, string $ip, bool $success): void {
    // If success, clear attempts in window
    if ($success) {
        $sql = "DELETE FROM login_attempts
                WHERE (username <=> ?) AND ip_address = ?
                  AND last_attempt >= (NOW() - INTERVAL ? MINUTE)";
        $stmt = $conn->prepare($sql);
        $win = RATE_LIMIT_WINDOW_MIN;
        $stmt->bind_param("ssi", $username, $ip, $win);
        $stmt->execute();
        return;
    }

    // Upsert attempt
    $sql = "SELECT id, attempts FROM login_attempts
            WHERE (username <=> ?) AND ip_address = ?
              AND last_attempt >= (NOW() - INTERVAL ? MINUTE)
            ORDER BY last_attempt DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $win = RATE_LIMIT_WINDOW_MIN;
    $stmt->bind_param("ssi", $username, $ip, $win);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $attempts = (int)$row['attempts'] + 1;
        $upd = $conn->prepare("UPDATE login_attempts SET attempts=?, last_attempt=NOW() WHERE id=?");
        $upd->bind_param("ii", $attempts, $row['id']);
        $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempts, last_attempt) VALUES (?, ?, 1, NOW())");
        $ins->bind_param("ss", $username, $ip);
        $ins->execute();
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$ip = client_ip();

// ---------- HANDLE POST ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(400);
        $error = "Invalid request.";
    } else {
        // Input
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $selectedRole = trim($_POST['role'] ?? '');

        // Rate limit check
        if (rate_limited($conn, $username, $ip)) {
            $error = "Too many attempts. Please try again later.";
        } else {
            // Fetch user securely
            $stmt = $conn->prepare("SELECT Username, PASSWORD, Role FROM users WHERE Username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            $loginOk = false;
            $redirect = null;

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['PASSWORD'])) {
                    // Optional: enforce selected role matches account role without leaking details
                    if ($selectedRole === $row['Role']) {
                        $loginOk = true;
                        $redirect = ($row['Role'] === 'Admin') ? "../admin/dashboard.php" : "../staff/dashboard.php";
                    }
                }
            }

            if ($loginOk) {
                // Success: record and start clean session
                record_attempt($conn, $username, $ip, true);
                session_regenerate_id(true);
                $_SESSION['username'] = $row['Username'];
                $_SESSION['role'] = $row['Role'];

                // OPTIONAL: If you add 2FA, redirect to /verify-otp.php here instead.
                ob_clean();
                header("Location: " . $redirect);
                exit();
            } else {
                // Failure path: record + generic error
                record_attempt($conn, $username, $ip, false);
                $error = $genericError;
            }
        }
    }
}

// New CSRF per render to avoid token reuse
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta charset="UTF-8" />
    <title>RB Stores Login</title>
    <link rel="stylesheet" href="../assets/css/login.css" />
    <!-- Font Awesome (allowed by CSP) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body>
    <header class="top-header">
        <div class="logo">
        <h1>RB Stores</h1>
            <p>Rain Water Solution Management System</p>
        </div>

        <button class="nav-toggle" aria-expanded="false" aria-controls="topnav" aria-label="Open menu">
            ☰
        </button>

        <nav id="topnav" class="top-nav">
            <a href="#">About</a>
            <a href="#">Contact</a>
            <a href="#">Support</a>
        </nav>
    </header>


    <div class="login-container">
        <h2>Welcome Login Portal</h2>
        <p>Please enter your details to sign in</p>

        <div class="role-btns">
            <button id="adminBtn" class="active" type="button">Admin</button>
            <div class="role-separator"></div>
            <button id="staffBtn" type="button">Staff</button>
        </div>

        <form action="login.php" method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="role" id="selectedRole" value="Admin" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" />

            <div class="input-group">
                <input type="text" name="username" placeholder="User Name" minlength="3" maxlength="50" required />
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" id="password" required />
                <div class="toggle-password" id="togglePassword" aria-label="Toggle password visibility" title="Show/Hide password">
                    <!-- eye icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5 16.5 9.51 16.5 12 14.49 16.5 12 16.5zm0-7c-1.38 0-2.5 1.12-2.5 2.5S10.62 14.5 12 14.5s2.5-1.12 2.5-2.5S13.38 9.5 12 9.5z"/>
                    </svg>
                </div>
            </div>

            <div class="forgot-password">
                <a href="#">Forgot Password?</a>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">Login</button>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </form>
    </div>

    <footer class="footer">
        Code Counter Team Group -15
    </footer>

    <!-- Inline JS with CSP nonce -->
    <script nonce="<?= $cspNonce ?>">
        (function () {
            const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');
            const selectedRoleInput = document.getElementById('selectedRole');
            const adminBtn = document.getElementById('adminBtn');
            const staffBtn = document.getElementById('staffBtn');
            const loginBtn = document.getElementById('loginBtn');

            togglePassword?.addEventListener('click', () => {
                passwordField.type = (passwordField.type === 'password') ? 'text' : 'password';
            });

            adminBtn?.addEventListener('click', () => {
                adminBtn.classList.add('active');
                staffBtn.classList.remove('active');
                selectedRoleInput.value = 'Admin';
            });

            staffBtn?.addEventListener('click', () => {
                staffBtn.classList.add('active');
                adminBtn.classList.remove('active');
                selectedRoleInput.value = 'Staff';
            });

            // Simple UX: disable button on submit to avoid double-posts
            const form = document.querySelector('form');
            form?.addEventListener('submit', () => {
                loginBtn.disabled = true;
                loginBtn.style.opacity = '0.7';
            });
        })();
    </script>

    <script nonce="<?= $cspNonce ?>">
        const header = document.querySelector('.top-header');
        const btn = document.querySelector('.nav-toggle');
        btn?.addEventListener('click', () => {
            const open = header.classList.toggle('nav-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent = open ? '✕' : '☰';
        });
</script>

</body>
</html>
