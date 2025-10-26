<?php
// login.php (Professional UI + calm animated background + no-page-scroll, sections as overlays)
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Security headers ---
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self' https: data: blob:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https: blob:; script-src 'self' 'unsafe-inline' https:; frame-ancestors 'self';");
}

require_once('../includes/db_connect.php'); // $conn (mysqli)

// --- CSRF ---
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['_csrf'];

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['_csrf'], $_POST['_csrf'])) {
        $error = "Security verification failed. Please try again.";
    } else {
        $username     = trim($_POST['username'] ?? '');
        $password     = $_POST['password'] ?? '';
        $selectedRole = trim($_POST['role'] ?? 'Admin');

        $stmt = $conn->prepare("SELECT Username, PASSWORD, Role FROM Users WHERE Username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $row = $res->fetch_assoc();

                if ($row['Role'] !== $selectedRole) {
                    $error = "You are trying to login as {$selectedRole}, but this account is for {$row['Role']}.";
                } elseif (password_verify($password, $row['PASSWORD'])) {
                    session_regenerate_id(true);
                    $_SESSION['username'] = $row['Username'];
                    $_SESSION['role']     = $row['Role'];
                    header("Location: " . ($row['Role'] === 'Admin' ? "../admin/dashboard.php" : "../staff/dashboard.php"));
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "User not found.";
            }
        } else {
            $error = "Login failed. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>RB Stores Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

<style>
/* ====== Design tokens ====== */
:root{
  --bg1:#0f172a; --bg2:#1e293b;
  --brand1:#4f46e5; --brand2:#3b82f6;
  --panel:rgba(255,255,255,0.08); --panel-border:rgba(255,255,255,0.18);
  --text:#e5e7eb; --muted:#94a3b8; --card-shadow: 0 10px 30px rgba(0,0,0,.35);
  --radius:18px; --safe-top: env(safe-area-inset-top, 0px); --safe-bottom: env(safe-area-inset-bottom, 0px);
}

/* ====== Base / No page scroll ====== */
*{box-sizing:border-box}
html, body{height:100%; overflow:hidden}
html::-webkit-scrollbar, body::-webkit-scrollbar{display:none}
body{
  margin:0; font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,'Noto Sans';
  color:var(--text);
  background:
    radial-gradient(1000px 600px at 20% 10%, #1b2640 0%, transparent 60%),
    radial-gradient(900px 700px at 80% 0%, #0e2a4d 0%, transparent 55%),
    linear-gradient(180deg, var(--bg1), var(--bg2));
  min-height:100dvh; display:flex; flex-direction:column; overflow-x:hidden;
}

/* ====== Background animation ====== */
.blob{
  position:fixed; left:-10%; bottom:10%; width:600px; height:600px;
  background: radial-gradient(circle at 30% 30%, rgba(79,70,229,.35), transparent 55%),
              radial-gradient(circle at 70% 70%, rgba(59,130,246,.25), transparent 60%);
  filter: blur(60px); opacity:.45; animation:drift 28s ease-in-out infinite alternate; pointer-events:none; z-index:0;
}
.blob.b2{right:-12%; left:auto; bottom:20%; width:520px; height:520px; animation-duration:36s; animation-delay:-8s; opacity:.35;}
.blob.b3{left:35%; bottom:-8%; width:680px; height:680px; animation-duration:44s; animation-delay:-14s; opacity:.30;}
@keyframes drift{0%{transform:translate3d(0,0,0) scale(1)}50%{transform:translate3d(6%,-2%,0) scale(1.05)}100%{transform:translate3d(12%,3%,0) scale(1.1)}}

.wave,.wave2,.wave3{
  position:fixed; left:0; right:0; bottom:0; height:180px; z-index:0; pointer-events:none;
  background-repeat:repeat-x; background-size:1200px 180px; opacity:.45; filter:blur(.2px);
}
.wave{
  animation: waveMove 32s linear infinite;
  background-image:url("data:image/svg+xml;utf8,<?xml version='1.0' encoding='UTF-8'?><svg xmlns='http://www.w3.org/2000/svg' width='1200' height='180' viewBox='0 0 1200 180'><path fill='%23ffffff18' d='M0,90 C 250,140 950,10 1200,90 L1200,180 L0,180 Z'/></svg>");
}
.wave2{
  height:200px; bottom:-20px; opacity:.35; animation:waveMove2 44s linear infinite;
  background-image:url("data:image/svg+xml;utf8,<?xml version='1.0' encoding='UTF-8'?><svg xmlns='http://www.w3.org/2000/svg' width='1200' height='200' viewBox='0 0 1200 200'><path fill='%23ffffff22' d='M0,120 C 300,40 900,210 1200,120 L1200,200 L0,200 Z'/></svg>");
}
.wave3{
  height:220px; bottom:-40px; opacity:.28; animation:waveMove3 60s linear infinite;
  background-image:url("data:image/svg+xml;utf8,<?xml version='1.0' encoding='UTF-8'?><svg xmlns='http://www.w3.org/2000/svg' width='1200' height='220' viewBox='0 0 1200 220'><path fill='%23ffffff14' d='M0,140 C 220,80 980,240 1200,140 L1200,220 L0,220 Z'/></svg>");
}
@keyframes waveMove{from{background-position-x:0}to{background-position-x:1200px}}
@keyframes waveMove2{from{background-position-x:0}to{background-position-x:-1200px}}
@keyframes waveMove3{from{background-position-x:0}to{background-position-x:800px}}

/* ====== Header with logo ====== */
.top-header{
  position:relative; z-index:2; display:flex; justify-content:space-between; align-items:center;
  padding: calc(12px + var(--safe-top)) 24px 12px; color:#fff; flex:0 0 auto;
}
.logo {
  display: flex;
  align-items: center;   /* vertical center align */
  gap: 12px;
}

.logo img {
  height: 40px;          /* match text height */
  width: auto;
  display: block;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,.25);
  margin-top: 2px;       /* fine-tune baseline alignment */
}

.top-header .logo h1 {
  font-size: 24px;
  font-weight: 800;
  letter-spacing: .3px;
  line-height: 1.2;
  margin: 0;
}

.top-header .logo p {
  font-size: 12px;
  opacity: .8;
  margin: 2px 0 0 0;
  line-height: 1.2;
}

.top-nav a {
  margin-left: 18px;
  color: #ffffff;
  font-size: 14px;
  text-decoration: none;
  opacity: .9;
  transition: opacity .2s ease, text-shadow .2s ease;
}

.top-nav a:hover {
  opacity: 1;
  text-shadow: 0 0 12px rgba(255,255,255,.35);
}

.top-nav i {
  margin-left: 8px;
  font-size: 16px;
}

/* ✅ Responsive adjustment for mobile */
@media (max-width: 520px) {
  .logo img { height: 32px; margin-top: 1px; }
  .top-header .logo h1 { font-size: 20px; }
  .top-header .logo p { font-size: 11px; }
}


/* ====== Main (no page scroll) ====== */
.main{
  position:relative; z-index:2; flex:1 1 auto; min-height:0; display:grid; place-items:center;
  padding: 16px clamp(12px, 2.5vw, 24px);
}

/* ====== Login card ====== */
.login-container{
  width:min(480px, 92vw);
  background: linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
  border:1px solid var(--panel-border);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  border-radius: var(--radius);
  padding: clamp(20px, 4.5vw, 36px) clamp(16px, 4vw, 28px);
  box-shadow: var(--card-shadow); text-align:center; transform: translateZ(0);
  transition: transform .25s ease; will-change: transform;
  max-height: min(76dvh, 640px); overflow:auto;
}
.login-container{scrollbar-width:none}.login-container::-webkit-scrollbar{display:none}
.login-container h2{font-size:clamp(20px,3.8vw,24px); font-weight:700; color:#fff; margin-bottom:6px}
.login-container p{font-size:clamp(12px,2.8vw,14px); color:var(--muted); margin-bottom:16px}

/* Role buttons */
.role-btns{display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:16px; flex-wrap:wrap}
.role-btns button{
  background:linear-gradient(135deg,var(--brand1),var(--brand2)); color:#fff; border:none; padding:10px 24px; border-radius:999px;
  font-size:15px; font-weight:600; cursor:pointer; transition:transform .15s ease, filter .15s ease, box-shadow .2s ease;
  box-shadow:0 6px 18px rgba(59,130,246,.35)
}
.role-btns button:hover{transform:translateY(-1px); filter:brightness(1.05)}
.role-btns button.active{box-shadow:0 8px 22px rgba(79,70,229,.45)}
.role-separator{width:1px; height:24px; background:rgba(255,255,255,.18)}

/* Inputs */
.input-group{position:relative; margin-bottom:14px; text-align:left}
.input-group input{
  width:100%; padding:12px 44px 12px 14px; border-radius:14px; border:1px solid rgba(255,255,255,.25);
  background:rgba(255,255,255,.1); color:#fff; font-size:15px; outline:none; box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
}
.input-group input::placeholder{color:rgba(255,255,255,.7)}
.input-group input:focus{border-color:rgba(99,102,241,.75); box-shadow:0 0 0 3px rgba(99,102,241,.18)}
.toggle-password{position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; line-height:0}
.toggle-password svg{width:20px; height:20px; fill:#cbd5e1; opacity:.85; transition:opacity .2s ease}
.toggle-password:hover svg{opacity:1}

/* Forgot + Button */
.forgot-password{text-align:right; margin:6px 2px 16px}
.forgot-password a{color:#c7d2fe; font-size:13px; text-decoration:underline dotted; text-underline-offset:3px}
.login-btn{
  width:100%; background:linear-gradient(135deg,var(--brand1),var(--brand2)); color:#fff; border:none; padding:12px 16px;
  border-radius:14px; font-size:16px; font-weight:600; cursor:pointer;
  transition:transform .15s ease, box-shadow .2s ease, filter .15s ease; box-shadow:0 10px 24px rgba(59,130,246,.45)
}
.login-btn:hover{transform:translateY(-1px); filter:brightness(1.04)}
.login-btn:active{transform:translateY(0)}

/* Error */
.error-message{
  color:#fecaca; background-color:rgba(239,68,68,.15); border:1px solid rgba(248,113,113,.45);
  border-radius:10px; padding:10px 12px; margin:12px auto 0; font-size:14px; font-weight:600; text-align:center; max-width:95%;
  box-shadow:0 2px 10px rgba(0,0,0,.12);
}

/* Footer */
.footer{
  position:relative; z-index:2; color:#e2e8f0; font-size:12px; text-align:center;
  padding:10px 6px calc(10px + var(--safe-bottom)); margin-top:auto; opacity:.85; flex:0 0 auto;
}

/* ====== Overlay Sections (About / Contact / Support) ====== */
.section-overlay{
  position:fixed; inset:0; z-index:30; display:none; align-items:center; justify-content:center;
  background:rgba(2,6,23,.55); backdrop-filter: blur(8px);
}
.section-overlay.active{display:flex}
.section-card{
  width:min(720px, 92vw); max-height:80dvh; overflow:auto;
  background: linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.08));
  border:1px solid var(--panel-border); border-radius:20px; padding:22px 18px 18px;
  box-shadow:0 20px 50px rgba(0,0,0,.45);
}
.section-card{scrollbar-width:none}.section-card::-webkit-scrollbar{display:none}
.section-header{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px}
.section-title{font-size:20px; font-weight:700}
.section-close{
  border:none; background:rgba(255,255,255,.12); color:#fff; padding:8px 12px; border-radius:12px; cursor:pointer;
}
.section-close:hover{filter:brightness(1.06)}
.section-content{font-size:14px; color:#e6eaf3; line-height:1.6}
.section-content a{color:#c7d2fe; text-decoration:underline dotted; text-underline-offset:3px}

/* Small devices */
@media (max-width:520px){
  .top-header{padding: calc(10px + var(--safe-top)) 16px 10px}
  .top-nav a{margin-left:12px; font-size:13px}
}

/* Short screens */
@media (max-height:640px){
  .login-container{max-height:70dvh}
  .top-header .logo h1{font-size:20px}
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce){
  .blob,.wave,.wave2,.wave3{animation:none !important}
  .login-container{transition:none}
}
</style>
</head>
<body>
  <!-- Background blobs & waves -->
  <div class="blob"></div><div class="blob b2"></div><div class="blob b3"></div>
  <div class="wave"></div><div class="wave2"></div><div class="wave3"></div>

  <!-- Top Navbar -->
  <header class="top-header" role="banner">
    <div class="logo">
      <!-- 🔧 Update the src path to your logo -->
      <img src="../assets/images/rb.png" alt="RB Stores Logo" />
      <div>
        <h1>RB Stores</h1>
        <p>Rain Water Solution Management System</p>
      </div>
    </div>
    <nav class="top-nav" aria-label="Primary">
      <a href="#about" data-open-section="about">About</a>
      <a href="#contact" data-open-section="contact">Contact</a>
      <a href="#support" data-open-section="support">Support <i class="fas fa-headset" aria-hidden="true"></i></a>
    </nav>
  </header>

  <!-- Main -->
  <main class="main">
    <section class="login-container" id="card" aria-label="Login form">
      <h2>Welcome Login Portal</h2>
      <p>Please enter your details to sign in</p>

      <div class="role-btns" role="tablist" aria-label="Choose role">
        <button id="adminBtn" class="active" type="button" role="tab" aria-selected="true">Admin</button>
        <div class="role-separator" aria-hidden="true"></div>
        <button id="staffBtn" type="button" role="tab" aria-selected="false">Staff</button>
      </div>

      <form action="login.php" method="POST" autocomplete="off" novalidate>
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($CSRF); ?>">
        <input type="hidden" name="role" id="selectedRole" value="Admin">

        <div class="input-group">
          <input type="text" name="username" placeholder="User Name" required aria-label="Username" autocomplete="username">
        </div>

        <div class="input-group">
          <input type="password" name="password" placeholder="Password" id="password" required aria-label="Password" autocomplete="current-password">
          <div class="toggle-password" id="togglePassword" aria-label="Show or hide password" title="Show/Hide Password">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5 16.5 9.51 16.5 12 14.49 16.5 12 16.5zm0-7c-1.38 0-2.5 1.12-2.5 2.5S10.62 14.5 12 14.5s2.5-1.12 2.5-2.5S13.38 9.5 12 9.5z"/>
            </svg>
          </div>
        </div>

        <div class="forgot-password">
          <a href="#">Forgot Password?</a>
        </div>

        <button type="submit" class="login-btn">Login</button>

        <?php if (!empty($error)): ?>
          <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
      </form>
    </section>
  </main>

  <footer class="footer">Code Counter Team Group -15</footer>

  <!-- ====== Overlay Section Markup (hidden by default) ====== -->
  <div class="section-overlay" id="sectionOverlay" aria-hidden="true">
    <div class="section-card" role="dialog" aria-modal="true" aria-labelledby="sectionTitle">
      <div class="section-header">
        <div class="section-title" id="sectionTitle">Section</div>
        <button class="section-close" id="sectionClose" type="button" aria-label="Close">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      <div class="section-content" id="sectionContent">
        <!-- Content injected by JS -->
      </div>
    </div>
  </div>

<script>
/* Password toggle + role toggles */
const togglePassword = document.getElementById('togglePassword');
const passwordField  = document.getElementById('password');
const selectedRoleInput = document.getElementById('selectedRole');
const adminBtn = document.getElementById('adminBtn');
const staffBtn = document.getElementById('staffBtn');
const card = document.getElementById('card');

togglePassword.addEventListener('click', ()=> {
  passwordField.type = (passwordField.type === 'password') ? 'text' : 'password';
});
function setRole(role){
  selectedRoleInput.value = role;
  const isAdmin = role === 'Admin';
  adminBtn.classList.toggle('active', isAdmin);
  staffBtn.classList.toggle('active', !isAdmin);
  adminBtn.setAttribute('aria-selected', isAdmin ? 'true' : 'false');
  staffBtn.setAttribute('aria-selected', !isAdmin ? 'true' : 'false');
}
adminBtn.addEventListener('click', () => setRole('Admin'));
staffBtn.addEventListener('click', () => setRole('Staff'));

/* Gentle parallax (no page scroll impact) */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (!reduceMotion) {
  let raf = null;
  window.addEventListener('mousemove', (e)=>{
    if (raf) return;
    raf = requestAnimationFrame(()=>{
      const { innerWidth:w, innerHeight:h } = window;
      const x = (e.clientX / w - 0.5) * 6;
      const y = (e.clientY / h - 0.5) * -6;
      card.style.transform = `rotateX(${y}deg) rotateY(${x}deg)`;
      raf = null;
    });
  });
  window.addEventListener('mouseleave', ()=>{ card.style.transform = 'none'; });
}

/* ====== In-page Sections (no scrolling, overlay dialog) ====== */
const overlay  = document.getElementById('sectionOverlay');
const closeBtn = document.getElementById('sectionClose');
const titleEl  = document.getElementById('sectionTitle');
const contentEl= document.getElementById('sectionContent');

const sections = {
  about: {
    title: 'About',
    html: `
      <p><strong>RB Stores</strong> is a Rain Water Solution Management System designed by <em>Code Counter Team Group - 15</em>.
      This portal provides secure access for Admin and Staff with modern UI and enhanced security headers.</p>
      <p>Version: 1.0 • © ${new Date().getFullYear()} RB Stores.</p>
    `
  },
  contact: {
    title: 'Contact',
    html: `
      <p>We’d love to hear from you.</p>
      <p><strong>Email:</strong> <a href="mailto:support@rbstores.lk">support@rbstores.lk</a><br>
         <strong>Phone:</strong> +94 77 123 4567<br>
         <strong>Address:</strong> Liberty Plaza, 2nd Floor, Kollupitiya, Colombo</p>
    `
  },
  support: {
    title: 'Support',
    html: `
      <p>Need help logging in or resetting your password?</p>
      <ul>
        <li>Use the <em>Forgot Password</em> link to start a reset.</li>
        <li>For account or role issues, email <a href="mailto:helpdesk@rbstores.lk">helpdesk@rbstores.lk</a>.</li>
        <li>Business hours: Mon–Fri, 9:00–17:00 (Asia/Colombo).</li>
      </ul>
    `
  }
};

function openSection(key){
  const data = sections[key]; if (!data) return;
  titleEl.textContent = data.title;
  contentEl.innerHTML = data.html;
  overlay.classList.add('active');
  overlay.setAttribute('aria-hidden', 'false');
  // basic focus management
  closeBtn.focus();
}
function closeSection(){
  overlay.classList.remove('active');
  overlay.setAttribute('aria-hidden', 'true');
}

// Intercept top-nav clicks (no page scroll)
document.querySelectorAll('[data-open-section]').forEach(a=>{
  a.addEventListener('click', (e)=>{
    e.preventDefault();
    openSection(a.getAttribute('data-open-section'));
  });
});
closeBtn.addEventListener('click', closeSection);
overlay.addEventListener('click', (e)=>{ if (e.target === overlay) closeSection(); });
document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeSection(); });

/* Defensive viewport fix for mobile UI chrome changes */
window.addEventListener('resize', () => {
  document.documentElement.style.setProperty('--vh', window.innerHeight + 'px');
});
</script>
</body>
</html>
