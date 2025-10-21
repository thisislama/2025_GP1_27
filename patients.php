<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Database connection ---
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "tanafs";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// --- Simulated login ---
if (!isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 1; 
}
$userID = $_SESSION['userID'];

// --- Get doctor name ---
$docRes = $conn->prepare("SELECT first_name, last_name FROM user WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// --- AJAX delete or disconnect ---
if (isset($_POST['action'])) {
    $PID = $_POST['PID'];
    if ($_POST['action'] == 'delete') {
        $conn->query("DELETE FROM user_patient WHERE PID='$PID'");
        $conn->query("DELETE FROM patient WHERE PID='$PID'");
        echo "✅ Patient deleted successfully!";
    } elseif ($_POST['action'] == 'disconnect') {
        $conn->query("DELETE FROM user_patient WHERE PID='$PID' AND userID='$userID'");
        echo "🔗 Disconnected successfully!";
    }
    exit;
}

// --- Get only patients linked to this doctor ---
$sql = "
SELECT p.PID, p.first_name, p.last_name, p.gender, p.status, p.phone, p.DOB
FROM patient p
INNER JOIN user_patient up ON p.PID = up.PID
WHERE up.userID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tanafs Patients</title>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>

<style>
:root {
  --bg: #f2f6fb;
  --card: #ffffff;
  --accent: #0f65ff;
  --muted: #9aa6c0;
  --shadow: 0 10px 30px rgba(17,24,39,0.06);
}
body {font-family:"Inter",sans-serif;background:var(--bg);color:#15314b;margin:0;}
.wrapper {position:relative;width:100%;min-height:100vh;}
img.topimg {position:absolute;top:-3.4%;left:48%;transform:translateX(-50%);max-width:90%;pointer-events:none;}
img.logo {position:absolute;top:2.6%;left:14%;width:clamp(100px,12vw,180px);pointer-events:none;}
.auth-nav {position:absolute;top:3.4%;right:16.2%;display:flex;align-items:center;gap:1.6em;}
.nav-link {color:#0876FA;font-weight:600;text-decoration:none;transition:.3s;}
.nav-link:hover {color:#055ac0;transform:translateY(-2px);}
.profile {display:flex;gap:.625em;align-items:center;background:linear-gradient(90deg,#f7fbff,#fff);padding:.375em .625em;}
.avatar-icon{width:30px;height:30px;}
.btn-logout {background:linear-gradient(90deg,#0f65ff,#5aa6ff);color:white;padding:.5em .975em;border-radius:.75em;border:none;cursor:pointer;}

/* main */
.main {flex:1;background-color:#f9faff;border-top-left-radius:30px;padding:36px;margin-top:90px;}
.title {display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.title h2 {color:#0B83FE;}
.table-container {background:white;border-radius:16px;padding:20px;box-shadow:var(--shadow);}
.table-actions {display:flex;justify-content:space-between;margin-bottom:15px;}
.table-actions input {padding:8px 12px;border-radius:8px;border:1px solid #ccc;width:70%;}
.table-actions button {padding:8px 14px;border:none;border-radius:8px;cursor:pointer;font-weight:600;}
.add-btn {background-color:#0a77e3;color:white;}
.connect-btn {background-color:#eef6ff;color:#0a77e3;}
table {width:100%;border-collapse:collapse;margin-top:20px;}
th,td {padding:12px;text-align:center;border-bottom:1px solid #eee;}
th {background-color:#f4f6fc;color:#1f46b6;}
tr:hover {background-color:#f9fbff;}
.status {padding:6px 12px;border-radius:12px;font-size:0.85rem;font-weight:500;}
.status.stable {background:#e2f5e9;color:#15803d;}
.status.critical {background:#fee2e2;color:#b91c1c;}
.status.recovered {background:#e6f2ff;color:#0f65ff;}
.action-icons span {cursor:pointer;padding:4px;border-radius:6px;margin:0 4px;color:#0f65ff;transition:.2s;}
.action-icons span:hover {background:#eef6ff;}
.message {margin-top:10px;font-weight:600;text-align:center;}

/* footer */
.site-footer {background:#F6F6F6;margin-top:40px;color:#0b1b2b;}
.footer-grid {max-width:75em;margin:0 auto;padding:2.5em 1.25em;display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:2em;}
.footer-logo {height:5em;}
.footer-title {color:#0B83FE;font-weight:700;margin-bottom:10px;}
.social-list {list-style:none;display:flex;gap:.75em;}
.contact-list {list-style:none;display:grid;gap:.5em;}
.contact-link {display:flex;align-items:center;gap:.6em;color:#0B83FE;text-decoration:none;}
.footer-bar {border-top:1px solid rgba(11,45,92,0.12);text-align:center;padding:1em;}
.copy {color:#0B83FE;font-size:.9em;}
</style>
</head>

<body>
<div class="wrapper">
<img class="topimg" src="images/Group 8.png" alt="img">
<img class="logo" src="images/logo.png" alt="Logo">

<nav class="auth-nav">
  <a class="nav-link" href="dashboard.html">Dashboard</a>
  <a class="nav-link" href="patients.php" style="color:#055ac0;">Patients</a>
  <button class="profile-btn">
    <div class="profile"><img class="avatar-icon" src="images/profile.png" alt="Profile" /></div>
  </button>
  <button class="btn-logout">Logout</button>
</nav>

<main class="main">
  <div class="title"><h2>Patients List</h2></div>

  <div class="table-container">
    <div class="table-actions">
      <input type="text" id="search" placeholder="Search patient...">
      <div>
        <button class="connect-btn" onclick="window.location.href='addPatient.php'">Add</button>
        <button class="add-btn" id="connectBtn">Connect</button>
      </div>
    </div>

    <table id="patientsTable">
      <thead>
        <tr>
          <th>ID</th><th>First Name</th><th>Last Name</th><th>Gender</th><th>Status</th><th>Phone</th><th>DOB</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
        <tr id="row-<?= $row['PID'] ?>">
          <td><?= htmlspecialchars($row['PID']) ?></td>
          <td><?= htmlspecialchars($row['first_name']) ?></td>
          <td><?= htmlspecialchars($row['last_name']) ?></td>
          <td><?= htmlspecialchars($row['gender']) ?></td>
          <td><span class="status <?= strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td><?= htmlspecialchars($row['DOB']) ?></td>
          <td class="action-icons">
            <span class="material-symbols-outlined" onclick="confirmAction('disconnect','<?= $row['PID'] ?>')">link_off</span>
            <span class="material-symbols-outlined" onclick="confirmAction('delete','<?= $row['PID'] ?>')">delete</span>
          </td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="8">No patients linked to you.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <p id="message" class="message"></p>
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
document.getElementById("search").addEventListener("keyup", function() {
  const filter = this.value.toLowerCase();
  document.querySelectorAll("#patientsTable tbody tr").forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(filter) ? "" : "none";
  });
});

function confirmAction(action, pid) {
  let msg = action === 'delete' 
      ? 'Are you sure you want to permanently delete this patient?' 
      : 'Are you sure you want to disconnect this patient?';
  if (confirm(msg)) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
      document.getElementById("message").innerText = this.responseText;
      if (this.responseText.includes('✅') || this.responseText.includes('🔗')) {
        document.getElementById('row-' + pid).remove();
      }
    };
    xhr.send("action=" + action + "&PID=" + pid);
  }
}
</script>
</body>
</html>
<?php $conn->close(); ?>
