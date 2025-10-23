<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "tanafs";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// Simulated login
if (!isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 1;
}
$userID = $_SESSION['userID'];

// Get doctor name
$docRes = $conn->prepare("SELECT first_name, last_name FROM user WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// Handle AJAX
if (isset($_POST['action']) && $_POST['action'] === 'search') {
    $q = "%".trim($_POST['query'])."%";
    // ✅ Changed from patient → hospital_record
    $stmt = $conn->prepare("SELECT PID, first_name, last_name FROM hospital_record WHERE PID LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 10");
    $stmt->bind_param("sss", $q, $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            echo "<div class='result-item'>
                    <div><strong>{$row['PID']}</strong> - {$row['first_name']} {$row['last_name']}</div>
                    <button class='add-btn' onclick=\"addPatient('{$row['PID']}')\">Add</button>
                  </div>";
        }
    } else {
        echo "<div class='result-item' style='color:#888;'>No matches found.</div>";
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $PID = trim($_POST['PID']);
    // ✅ Changed from patient → hospital_record
    $check = $conn->prepare("SELECT PID FROM hospital_record WHERE PID=?");
    $check->bind_param("s", $PID);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$exists) {
        echo "❌ Record not found.";
        exit;
    }

    $dup = $conn->prepare("SELECT COUNT(*) AS c FROM user_patient WHERE PID=? AND userID=?");
    $dup->bind_param("si", $PID, $userID);
    $dup->execute();
    $r = $dup->get_result()->fetch_assoc();
    $dup->close();

    if ($r['c'] > 0) {
        echo "⚠️ Already linked.";
        exit;
    }

    $add = $conn->prepare("INSERT INTO user_patient (PID, userID) VALUES (?, ?)");
    $add->bind_param("si", $PID, $userID);
    if ($add->execute()) echo "✅ Added successfully!";
    else echo "❌ Failed to add.";
    $add->close();
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Patient</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
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
  font-family: "Inter", sans-serif;
  background: var(--bg);
  color: #15314b;
  margin: 0;
  display: flex;
}

.wrapper { position: relative; width: 100%; min-height: 100vh; }
img.topimg { position: absolute; top: -3.6%; left: 48%; transform: translateX(-50%); max-width: 90%; z-index: 10; pointer-events: none; }
img.logo { position: absolute; top: 4.5%; left: 14%; width: clamp(100px, 12vw, 180px); height: auto; z-index: 20; }
.auth-nav { position: absolute; top: 5.4%; right: 16.2%; display: flex; align-items: center; gap: 1.6em; z-index: 30; }
.nav-link { color: #0876FA; font-weight: 600; text-decoration: none; font-size: 1em; transition: all 0.3s ease; position: relative; }
.nav-link::after { content: ""; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, #0876FA, #78C1F5); transition: width 0.3s ease; border-radius: 2px; }
.nav-link:hover::after { width: 100%; }
.profile { display: flex; gap: 0.625em; align-items: center; background: linear-gradient(90deg, #f7fbff, #fff); padding: 0.375em 0.625em; }
.avatar-icon { width:30px; height:30px; display:block; }
.btn-logout { background: linear-gradient(90deg, #0f65ff, #5aa6ff); color: white; padding: 0.5em 0.975em; border-radius: 0.75em; font-weight: 400; border: none; cursor: pointer; font-size: 0.875em; }

/* Main */
.main { flex: 1; padding: 36px; display: flex; flex-direction: column; align-items: center; margin-top: 120px; }
.main h2 { color: #0B83FE; margin-bottom: 30px; }

.search-card{
  width: min(92vw, 560px);         
  padding: 24px 22px;
  border-radius: 16px;
  background: linear-gradient(180deg, #ffffff, #fdfefe);
  box-shadow: var(--panel-shadow);
  border: 1px solid rgba(15,101,255,0.08);
}

.search-card input {
  width: 100%;
  padding: 12px;
  border: 1px solid #ccc;
  border-radius: 8px;
  margin-bottom: 20px;
    margin-top: 20px;
}
#results{
  max-height: 320px;                
  overflow: auto;                 
  padding: 6px 2px 2px;
  margin-top: 6px;
  border-top: 1px solid rgba(0,0,0,0.06);
  display: grid;
  gap: 8px;
}
.result-item{
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid rgba(0,0,0,0.06);
  border-radius: 10px;
  background: #fff;
  transition: transform .12s ease, box-shadow .12s ease, border-color .2s ease;
}
.add-btn{
  padding: 8px 14px;
  border-radius: 10px;
  background: linear-gradient(90deg, #0f65ff, #5aa6ff);
  color: #fff;
  border: 0;
  cursor: pointer;
  font-weight: 600;
}
.search-card { overflow: hidden; }

.search-card input{
  box-sizing: border-box;
  width: 100%;
  outline: none;                 
  border-radius: 12px;
  -webkit-appearance: none;
}

.search-card input:focus{
  border-color: rgba(15,101,255,.35);
  box-shadow: 0 0 0 4px rgba(15,101,255,.08);
}
.add-btn:hover { background: #094dcf; }
.site-footer {
  background: #F6F6F6;
  color: #0b1b2b;
  font-family: 'Montserrat', sans-serif;
  margin-top: 6em; 
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
.footer-grid {
  direction: ltr; 
}

.footer-col.brand {
  text-align: left;  
}

.footer-logo {
  margin-left: 0;
  margin-right: 0; 
}
/* Brand */
.footer-logo {
  height: 5.5em;
  width: auto;
  display: block;
  margin-left: -3em;
}
.brand-tag {
  margin-top: 0.75em;
  color: #4c5d7a;
  font-size: 0.95em;
}

/* Headings */
.footer-title {
  margin: 0 0 1em 0;
  font-size: 1.05em;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #0B83FE;
  text-transform: uppercase;
}

/* Social */
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
.social-list img {
  width: 1.2em;
  height: 1.2em;
}
.social-handle {
  display: block;
  margin-top: 0.6em;
  color: #0B83FE;
  font-size: 0.95em;
}

/* Contact */
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
.contact-link:hover {
  background: rgba(255, 255, 255, 0.7);
  transform: translateX(0.2em);
}
.contact-link img {
  width: 1.15em;
  height: 1.15em;
}

/* Bottom bar */
.footer-bar {
  border-top: 0.06em solid rgba(11, 45, 92, 0.12);
  text-align: center;
  padding: 0.9em 1em 1.2em;
}
.legal {
  margin: 0.2em 0;
  color: #4c5d7a;
  font-size: 0.9em;
}
.legal a {
  color: #27466e;
  text-decoration: none;
}
.legal a:hover {
  text-decoration: underline;
}
.legal .dot {
  margin: 0 0.5em;
  color: rgba(11, 45, 92, 0.6);
}
.copy {
  margin: 0.2em 0 0;
  color: #0B83FE;
  font-size: 0.85em;
}
img.topimg { top: -3.6vh; }
img.logo   { top: 4.9vh; }
.auth-nav  { top: 6vh; }

.main {
  margin-top: clamp(120px, 24vh, 340px); 
}

@media (max-width: 1000px) {
  img.topimg { top: -2vh; max-width: 100%; }
  img.logo   { top: 3.2vh; left: 12%; }
  .auth-nav  { top: 3.8vh; right: 10%; gap: .9em; }
  .main      { margin-top: clamp(150px, 26vh, 300px); }
}

@media (max-width: 720px) {
  .auth-nav {
    position: static;
    padding: .5rem 1rem;
    justify-content: flex-end;
    gap: .6em;
    row-gap: .4em;
  }
  img.topimg {
    position: relative;
    top: 0; left: 0; transform: none;
    width: 100%; height: auto; display: block;
  }
  img.logo { top: 2.2vh; } 
  .main { margin-top: 120px; } 
}

</style>
<script>
function searchPatient(val){
    if(val.trim()===''){document.getElementById('results').innerHTML='';return;}
    const xhr=new XMLHttpRequest();
    xhr.open('POST','',true);
    xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    xhr.onload=function(){document.getElementById('results').innerHTML=this.responseText;}
    xhr.send('action=search&query='+encodeURIComponent(val));
}
function addPatient(pid){
    const xhr=new XMLHttpRequest();
    xhr.open('POST','',true);
    xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    xhr.onload=function(){document.getElementById('message').innerHTML=this.responseText;}
    xhr.send('action=add&PID='+encodeURIComponent(pid));
}
</script>
</head>
<body>
<div class="wrapper">
<img class="topimg" src="images/Group 8.png" alt="bg">
<img class="logo" src="images/logo.png" alt="logo">
<nav class="auth-nav">
  <a class="nav-link" href="patients.php">Patients</a>
  <a class="nav-link" href="dashboard.html">Dashboard</a>
  <a class="nav-link" href="history.html">History</a>
  <div class="profile"><img class="avatar-icon" src="images/profile.png" alt="Profile"></div>
  <button class="btn-logout">Logout</button>
</nav>

<main class="main">
  <h2>Add Patient from Database</h2>
  <div class="search-card">
      <input type="text" placeholder="Search by Name or ID..." onkeyup="searchPatient(this.value)">
      <div id="results"></div>
      <p id="message" style="margin-top:15px;font-weight:600;"></p>
  </div>
</main>

<footer class="site-footer">
  <div class="footer-grid">
    <div class="footer-col brand">
      <img src="images/logo.png" alt="Tanafs logo" class="footer-logo"/>
      <p>Breathe well, live well</p>
    </div>
    <nav class="footer-col social">
      <h3 class="footer-title">Social Media</h3>
      <ul class="social-list">
        <li><a href="#"><img src="images/twitter.png" alt="Twitter"></a></li>
        <li><a href="#"><img src="images/instagram.png" alt="Instagram"></a></li>
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
  <div class="footer-bar">
    <p class="copy">© 2025 Tanafs Company. All rights reserved.</p>
  </div>
</footer>
</div>
</body>
</html>
<?php $conn->close(); ?>
