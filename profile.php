<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (empty($_SESSION['user_id'])) {
    if (!empty($_POST['action']) || !empty($_POST['ajax'])) {
        http_response_code(401);
        exit('❌ Unauthorized. Please sign in.');
    }
    header('Location: signin.php');
    exit;
}

$userID = (int)$_SESSION['user_id'];

// Database connection
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "tanafs";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $dob        = trim($_POST['dob']        ?? '');

    if ($first_name === '' || $last_name === '') {
        $error = 'Please provide first and last name.';
    } elseif ($phone !== '' && !preg_match('/^(?:\+9665\d{8}|05\d{8})$/', $phone)) {
        $error = 'Invalid phone number format.';
    } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $error = 'Invalid date of birth format (YYYY-MM-DD).';
    }

    if ($error === '') {
        $update = $conn->prepare("
            UPDATE healthcareprofessional
            SET first_name = ?, last_name = ?, phone = ?, DOB = ?
            WHERE userID = ?
        ");
        $update->bind_param("ssssi", $first_name, $last_name, $phone, $dob, $userID);

        if ($update->execute()) {
            $success = "✅ Changes saved successfully.";
        } else {
            $error = "❌ Error while saving changes.";
        }
        $update->close();
    }
}

$sql = "SELECT first_name, last_name, email, role, phone, DOB
        FROM healthcareprofessional
        WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) {
    session_unset();
    session_destroy();
    header('Location: signin.php');
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tanafs Profile</title>

<!-- Google Material Symbols -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
  --bg: #f2f6fb;
  --card: #ffffff;
  --accent: #0f65ff;
  --muted: #9aa6c0;
  --soft-blue: #eef6ff;
  --panel-shadow: 0 10px 30px rgba(17, 24, 39, 0.06);
  --radius: 14px;
}
body {
  font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  background: var(--bg);
  color: #15314b;
  display: flex;
}
.wrapper {
  position: relative;
  width: 100%;
  min-height: 100vh;
}
img.topimg { position: absolute; top: -4.1%; left: 48%; transform: translateX(-50%); height: auto; width: auto; max-width: 90%; z-index: 10; pointer-events: none; }
img.logo { position: absolute; top: 2%; left: 14%; width: clamp(100px, 12vw, 180px); z-index: 20; pointer-events: none; }
.auth-nav { position: absolute; top: 2.9%; right: 16.2%; display: flex; align-items: center; gap: 1.6em; z-index: 30; }
.nav-link { color: #0876FA; font-weight: 600; text-decoration: none; font-size: 1em; transition: all 0.3s ease; position: relative; }
.nav-link::after { content: ""; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, #0876FA, #78C1F5); transition: width 0.3s ease; border-radius: 2px; }
.nav-link:hover::after { width: 100%; }
.nav-link:hover { transform: translateY(-2px); color: #055ac0; }
.profile { display: flex; gap: 0.625em; align-items: center;  padding: 0.375em 0.625em; }
.avatar-icon { width: 30px; height: 30px; display: block; }
.btn-logout { background: linear-gradient(90deg, #0f65ff, #5aa6ff); color: white; padding: 0.5em 0.975em; border-radius: 0.75em; font-weight: 400; border: none; box-shadow: 0 0.5em 1.25em rgba(15,101,255,0.14); cursor: pointer; font-size: 0.875em; }
profile-card{
  width: 100%;
  max-width: 34rem;                 
  margin-inline: auto;
  padding: 1.25rem 1.25rem;
}

.profile-card input{
  box-sizing: border-box;
  padding-inline: 0.9rem;
  text-align: left;
  direction: ltr;                 
}
.profile-card input[type="date"]::-webkit-date-and-time-value{
  text-align: left;
}

.actions{
  display: flex;
  gap: .75rem;
  justify-content: space-between;
}
.actions button{
  flex: 1 1 0;                      
  min-width: 7rem;               
}
/* ===== Main ===== */
main {
  flex: 1;
  background-color: #f9faff;
  border-top-left-radius: 30px;
  padding: 36px;
  padding-top: 130px;
  overflow-y: auto;
}
.title {
  text-align: center;
  margin-bottom: 20px;
}
.title h2 {
  color: #0B83FE;
  font-size: 1.5rem;
}

/* ===== Profile Card ===== */
.profile-card {
  background-color: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  width: 450px;
  margin: 0 auto;
  text-align: center;
}
.profile-card img {
  width: 90px;
  height: 90px;
  margin-bottom: 15px;
}
.profile-card label {
  display: block;
  font-weight: 600;
  text-align: left;
  margin-top: 10px;
  color: #1f3e73;
}
.profile-card input {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ccc;
  background: #f8faff;
  margin-top: 5px;
}
.profile-card input:disabled {
  background: #eef2f7;
}
.actions {
  display: flex;
  justify-content: space-between;
  margin-top: 20px;
}
.actions button {
  padding: 8px 14px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}
.edit-btn { background-color: #f0f0f0; color: #1b2250; }
.save-btn { background-color: #0a77e3; color: white; }
.back-btn { background-color: #eef6ff; color: #0a77e3; }

/* ========== Footer ========== */
.site-footer {
  background: #F6F6F6;
  color: #0b1b2b;
  font-family: 'Montserrat', sans-serif;
  margin-top: 0;
}

.footer-grid {
  max-width: 75em;
  margin: 0 auto;
  padding: 2.5em 1.25em;
  display: grid;
  grid-template-columns: 1.2fr 1fr 1fr;
  gap: 2em;
  align-items: start;
  direction: ltr;
}

.footer-col.brand { text-align: left; }

.footer-logo { height: 5.5em; width: auto; display: block; margin-left: -3em; }

.brand-tag { margin-top: 0.75em; color: #4c5d7a; font-size: 0.95em; }

.footer-title {
  margin: 0 0 1em 0;
  font-size: 1.05em;
  font-weight: 700;
  letter-spacing: 0.0125em; 
  color: #0B83FE;
  text-transform: uppercase;
}

.social-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  gap: 0.75em;
  align-items: center;
}

.social-list li a {
  display: inline-flex;
  width: auto;
  height: auto;
  align-items: center;
  justify-content: center;
  border-radius: 0;
  background: none;
  box-shadow: none;
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.social-list li a:hover {
  transform: translateY(-0.2em);
  box-shadow: 0 0.6em 1.4em rgba(0, 0, 0, 0.08);
}

.social-list img { width: 1.2em; height: 1.2em; }

.social-handle { display: block; margin-top: 0.6em; color: #0B83FE; font-size: 0.95em; }

.contact-list {
  list-style: none;
  padding: 0;
  margin: 0.25em 0 0 0;
  display: grid;
  gap: 0.6em;
}

.contact-link {
  display: flex;
  align-items: center;
  gap: 0.6em;
  text-decoration: none;
  color: #0B83FE;
  padding: 0.5em 0.6em;
  border-radius: 0.6em;
  transition: background 0.2s ease, transform 0.2s ease;
}

.contact-link:hover { background: rgba(255, 255, 255, 0.7); transform: translateX(0.2em); }

.contact-link img { width: 1.15em; height: 1.15em; }

.footer-bar {
  border-top: 0.06em solid rgba(11, 45, 92, 0.12);
  text-align: center;
  padding: 0.9em 1em 1.2em;
}

.legal { margin: 0.2em 0; color: #4c5d7a; font-size: 0.9em; }

.legal a { color: #27466e; text-decoration: none; }
.legal a:hover { text-decoration: underline; }

.legal .dot { margin: 0 0.5em; color: rgba(11, 45, 92, 0.6); }

.copy { margin: 0.2em 0 0; color: #0B83FE; font-size: 0.85em; }
.actions{ display:flex; justify-content:space-between; gap:.6em; margin-top:20px; }
.actions button{ padding:10px 14px; border:none; border-radius:12px; font-weight:700; cursor:pointer; }
.save-btn{ background:linear-gradient(90deg,#0f65ff,#5aa6ff); color:#fff; box-shadow:0 8px 16px rgba(15,101,255,.18); }
.edit-btn{ background:#f0f3f8; color:#15314b; }
.back-btn{ background:#eef6ff; color:#0f65ff; border:1px solid rgba(15,101,255,.15); }
body {
  margin: 0;
  background: #f9faff; 
}
  @media (min-width: 768px) and (max-width: 1024px) {
  .auth-nav {
    top: 4%;
    right: 11%;
    gap: 1.2em;
  }

  img.logo {
    top: 3%;
    left: 11%;
    width: clamp(5em, 14vw, 10em);
  }

  img.topimg {
    top: -1%;
    max-width: 100%;
  }}

    </style>
</head>
<body>


<div class="wrapper">
  <img class="topimg" src="images/Group 8.png" alt="">
  <img class="logo" src="images/logo.png" alt="Logo">

   <nav class="auth-nav" aria-label="User navigation">
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <a class="nav-link" href="patients.php">Patients</a>
        <a class="nav-link" href="history2.php">History</a>
        <a href="profile.php" class="profile-btn">
            <div class="profile">
                <img class="avatar-icon" src="images/profile.png" alt="Profile">
            </div>
        </a>

        <form action="Logout.php" method="post" style="display:inline;">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </nav>

  <main>
  <div class="title"><h2>Healthcare Professional Profile</h2></div>

  <div class="profile-card">
    <form method="POST">
      <label>First Name</label>
      <input type="text" name="first_name" value="<?= htmlspecialchars($userData['first_name']); ?>" disabled>
      <label>Last Name</label>
      <input type="text" name="last_name" value="<?= htmlspecialchars($userData['last_name']); ?>" disabled>
      <label>Email</label>
      <input type="email" value="<?= htmlspecialchars($userData['email']); ?>" disabled>
      <label>Role</label>
      <input type="text" value="<?= htmlspecialchars($userData['role']); ?>" disabled>
      <label>Phone</label>
      <input type="text" name="phone" value="<?= htmlspecialchars($userData['phone']); ?>" disabled>
      <label>Date of Birth</label>
      <input type="date" name="dob" value="<?= htmlspecialchars($userData['DOB']); ?>" disabled>

      <div class="actions" style="justify-content:center; gap:1.5rem;">
        <button type="button" class="edit-btn" id="editBtn" style="min-width:110px;">Edit</button>
        <button type="submit" name="save" class="save-btn" style="min-width:110px;">Save</button>
      </div>
    </form>

    <?php if (!empty($success)): ?>
      <p style="color:green;font-weight:bold;margin-top:10px;"><?= $success ?></p>
    <?php elseif (!empty($error)): ?>
      <p style="color:red;font-weight:bold;margin-top:10px;"><?= $error ?></p>
    <?php endif; ?>
  </div>
</main>


 <!-- Footer -->
  <footer id="contact" class="site-footer">
    <div class="footer-grid">
      <div class="footer-col brand">
        <img src="images/logo.png" alt="Tanafs logo" class="footer-logo" />
        <p class="brand-tag">Breathe well, live well</p>
      </div>
      <nav class="footer-col social">
        <h3 class="footer-title">Social Media</h3>
        <ul class="social-list">
          <li><a href="#"><img src="images/twitter.png" alt="Twitter" /></a></li>
          <li><a href="#"><img src="images/instagram.png" alt="Instagram" /></a></li>
        </ul>
        <span class="social-handle">@official_Tanafs</span>
      </nav>
      <div class="footer-col contact">
        <h3 class="footer-title">Contact Us</h3>
        <ul class="contact-list">
          <li><a href="#" class="contact-link"><img src="images/whatsapp.png" alt="WhatsApp" /><span>+123 165 788</span></a></li>
          <li><a href="mailto:Tanafs@gmail.com" class="contact-link"><img src="images/email.png" alt="Email" /><span>Tanafs@gmail.com</span></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bar">
      <p class="legal"><a href="#">Terms &amp; Conditions</a><span class="dot">•</span><a href="#">Privacy Policy</a></p>
      <p class="copy">© 2025 Tanafs Company. All rights reserved.</p>
    </div>
  </footer>
</div>

<script>
document.getElementById('editBtn').addEventListener('click', () => {
  document.querySelectorAll('input[name]').forEach(i => i.disabled = false);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
 
  document.getElementById('editBtn').addEventListener('click', () => {
    document.querySelectorAll('.profile-card form input[name]').forEach(i => i.disabled = false);
  });

  const profileForm = document.querySelector('.profile-card form');
  profileForm.addEventListener('submit', function(e) {
    const first = profileForm.querySelector('input[name="first_name"]').value.trim();
    const last  = profileForm.querySelector('input[name="last_name"]').value.trim();
    const phone = profileForm.querySelector('input[name="phone"]').value.trim();
    const dob   = profileForm.querySelector('input[name="dob"]').value.trim();

    if (first === '' || last === '' || phone === '' || dob === '') {
      e.preventDefault();
      alert('Please fill in all required fields before saving.');
    }
  });
});
</script>
</body>
</html>
