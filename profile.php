<?php
session_start();
$_SESSION['userID'] = 1;
$userID = $_SESSION['userID'];

// Database connection
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "tanafs";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// Fetch user data
$sql = "SELECT first_name, last_name, email, role, phone, DOB FROM user WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// Save edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $phone      = trim($_POST['phone']);
    $dob        = trim($_POST['dob']);

    $update = $conn->prepare("UPDATE user SET first_name=?, last_name=?, phone=?, DOB=? WHERE userID=?");
    $update->bind_param("ssssi", $first_name, $last_name, $phone, $dob, $userID);
    if ($update->execute()) {
        $success = "✅ Changes saved successfully.";
        $userData['first_name'] = $first_name;
        $userData['last_name']  = $last_name;
        $userData['phone']      = $phone;
        $userData['DOB']        = $dob;
    } else {
        $error = "❌ Error while saving changes.";
    }
    $update->close();
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
img.topimg { position: absolute; top: -3%; left: 48%; transform: translateX(-50%); height: auto; width: auto; max-width: 90%; z-index: 10; pointer-events: none; }
img.logo { position: absolute; top: 3%; left: 14%; width: clamp(100px, 12vw, 180px); z-index: 20; pointer-events: none; }
.auth-nav { position: absolute; top: 3.4%; right: 16.2%; display: flex; align-items: center; gap: 1.6em; z-index: 30; }
.nav-link { color: #0876FA; font-weight: 600; text-decoration: none; font-size: 1em; transition: all 0.3s ease; position: relative; }
.nav-link::after { content: ""; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, #0876FA, #78C1F5); transition: width 0.3s ease; border-radius: 2px; }
.nav-link:hover::after { width: 100%; }
.nav-link:hover { transform: translateY(-2px); color: #055ac0; }
.profile { display: flex; gap: 0.625em; align-items: center; background: linear-gradient(90deg, #f7fbff, #fff); padding: 0.375em 0.625em; }
.avatar-icon { width: 30px; height: 30px; display: block; }
.btn-logout { background: linear-gradient(90deg, #0f65ff, #5aa6ff); color: white; padding: 0.5em 0.975em; border-radius: 0.75em; font-weight: 400; border: none; box-shadow: 0 0.5em 1.25em rgba(15,101,255,0.14); cursor: pointer; font-size: 0.875em; }

/* ===== Main ===== */
main {
  flex: 1;
  background-color: #f9faff;
  border-top-left-radius: 30px;
  padding: 36px;
  padding-top: 140px;
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

/* ===== Footer ===== */
.site-footer {
  background: #F6F6F6;
  color: #0b1b2b;
  margin-top: 3em;
}
.footer-grid {
  max-width: 75em;
  margin: 0 auto;
  padding: 2.5em 1.25em;
  display: grid;
  grid-template-columns: 1.2fr 1fr 1fr;
  gap: 2em;
  align-items: start;
}
.footer-logo { height: 5.5em; margin-left: -3em; }
.footer-title { color: #0B83FE; font-weight: 700; margin-bottom: 1em; }
.social-list { list-style: none; display: flex; gap: .8em; }
.social-list img { width: 1.2em; }
.contact-link { display: flex; gap: .6em; color: #0B83FE; text-decoration: none; }
.footer-bar { text-align: center; border-top: 1px solid rgba(11,45,92,0.12); padding: 1em; color: #4c5d7a; }
.copy { color: #0B83FE; font-size: 0.85em; }
</style>
</head>
<body>
<div class="wrapper">
  <img class="topimg" src="images/Group 8.png" alt="">
  <img class="logo" src="images/logo.png" alt="Logo">

  <nav class="auth-nav">
    <a class="nav-link" href="patients.php">Patients</a>
    <a class="nav-link" href="dashboard.html">Dashboard</a>
    <a class="nav-link" href="history.html">History</a>
    <button class="profile-btn"><div class="profile"><img class="avatar-icon" src="images/profile.png" alt="Profile"></div></button>
    <button class="btn-logout">Logout</button>
  </nav>

  <main>
    <div class="title"><h2>Doctor Profile</h2></div>

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

        <div class="actions">
          <button type="button" class="edit-btn" id="editBtn">Edit</button>
          <button type="submit" name="save" class="save-btn">Save</button>
          <button type="button" class="back-btn" onclick="window.location.href='dashboard.html'">Back</button>
        </div>
      </form>

      <?php if (!empty($success)): ?>
        <p style="color:green;font-weight:bold;margin-top:10px;"><?= $success ?></p>
      <?php elseif (!empty($error)): ?>
        <p style="color:red;font-weight:bold;margin-top:10px;"><?= $error ?></p>
      <?php endif; ?>
    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-grid">
      <div class="footer-col brand">
        <img src="images/logo.png" alt="Tanafs logo" class="footer-logo" />
        <p>Breathe well, live well</p>
      </div>
      <nav class="footer-col social">
        <h3 class="footer-title">Social Media</h3>
        <ul class="social-list">
          <li><a href="#"><img src="images/twitter.png" alt="Twitter" /></a></li>
          <li><a href="#"><img src="images/instagram.png" alt="Instagram" /></a></li>
        </ul>
      </nav>
      <div class="footer-col contact">
        <h3 class="footer-title">Contact Us</h3>
        <ul class="contact-list">
          <li><a href="#" class="contact-link"><img src="images/whatsapp.png" alt="WhatsApp"/><span>+123 165 788</span></a></li>
          <li><a href="mailto:Tanafs@gmail.com" class="contact-link"><img src="images/email.png" alt="Email"/><span>Tanafs@gmail.com</span></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bar"><p class="copy">© 2025 Tanafs Company. All rights reserved.</p></div>
  </footer>
</div>

<script>
document.getElementById('editBtn').addEventListener('click', () => {
  document.querySelectorAll('input[name]').forEach(i => i.disabled = false);
});
</script>
</body>
</html>
