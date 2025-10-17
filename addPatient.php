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

// --- Simulated login (remove when real login works) ---
if (!isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 1; // test doctor ID
}
$userID = $_SESSION['userID'];

// --- Get doctor name ---
$docRes = $conn->prepare("SELECT first_name, last_name FROM user WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// --- AJAX request handler ---
if (isset($_POST['action']) && $_POST['action'] === 'search') {
    $q = "%".trim($_POST['query'])."%";
    $stmt = $conn->prepare("SELECT PID, first_name, last_name FROM patient WHERE PID LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 10");
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

    // Check if patient exists
    $checkExist = $conn->prepare("SELECT PID FROM patient WHERE PID=?");
    $checkExist->bind_param("s", $PID);
    $checkExist->execute();
    $exists = $checkExist->get_result()->fetch_assoc();
    $checkExist->close();

    if (!$exists) {
        echo "❌ Patient not found in database.";
        exit;
    }

    // Check if already linked to same doctor
    $checkLink = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_patient WHERE PID=? AND userID=?");
    $checkLink->bind_param("si", $PID, $userID);
    $checkLink->execute();
    $cnt = $checkLink->get_result()->fetch_assoc();
    $checkLink->close();

    if ($cnt['cnt'] > 0) {
        echo "ℹ️ You already have this patient.";
        exit;
    }

    // Check if linked to other doctor
    $checkOther = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_patient WHERE PID=?");
    $checkOther->bind_param("s", $PID);
    $checkOther->execute();
    $c = $checkOther->get_result()->fetch_assoc();
    $checkOther->close();

    if ($c['cnt'] > 0) {
        echo "⚠️ This patient is already under another doctor. Please use Connect.";
        exit;
    }

    // Add connection
    $insert = $conn->prepare("INSERT INTO user_patient (PID, userID) VALUES (?, ?)");
    $insert->bind_param("si", $PID, $userID);
    if ($insert->execute()) {
        echo "✅ Patient added successfully!";
    } else {
        echo "❌ Failed to add patient.";
    }
    $insert->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Patient from Database</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<style>
:root {
    --bg:#f2f6fb;--card:#fff;--accent:#0f65ff;--muted:#9aa6c0;
    --shadow:0 10px 30px rgba(17,24,39,0.06);--radius:14px;
}
body {font-family:"Inter",sans-serif;background:var(--bg);color:#15314b;margin:0;display:flex;}
.sidebar {width:88px;height:100vh;background:linear-gradient(180deg,#fbfdff,#f3f7ff);
display:flex;flex-direction:column;align-items:center;padding:24px 12px;gap:24px;position:fixed;}
.sidebar-item {width:60px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--accent);cursor:pointer;transition:.18s;}
.sidebar-item:hover {transform:translateX(4px);background:rgba(15,101,255,0.08);}
.sidebar-item.active {background:linear-gradient(180deg,rgba(15,101,255,0.08),rgba(15,101,255,0.03));border-radius:20px;width:72px;height:56px;}
.sidebar-logout {margin-top:auto;margin-bottom:40px;}
.btn-logout {background:linear-gradient(90deg,#0f65ff,#5aa6ff);color:white;border:none;padding:10px;border-radius:12px;cursor:pointer;box-shadow:0 8px 20px rgba(15,101,255,0.14);}
.main {margin-left:88px;flex:1;display:flex;flex-direction:column;}
header.topbar {height:84px;display:flex;align-items:center;justify-content:space-between;
padding:10px 36px;background:linear-gradient(180deg,rgba(255,255,255,0.9),rgba(255,255,255,0.7));border-bottom:1px solid rgba(15,21,40,0.04);}
.logo-top img {width:220px;}
.profile {display:flex;align-items:center;gap:10px;cursor:pointer;}
.avatar {width:36px;height:36px;border-radius:50%;background:linear-gradient(180deg,#2e9cff,#1a57ff);color:white;display:flex;align-items:center;justify-content:center;font-weight:600;}
.container {display:flex;justify-content:center;align-items:center;height:calc(100vh - 120px);flex-direction:column;text-align:center;}
h2 {color:#1f46b6;margin-bottom:20px;}
.search-box {background:#fff;padding:30px;border-radius:14px;box-shadow:var(--shadow);max-width:500px;width:100%;text-align:left;}
input {width:100%;padding:10px;margin-bottom:15px;border:1px solid rgba(0,0,0,0.1);border-radius:10px;}
.result-item {display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid #eee;}
.result-item:hover {background:#f8fbff;}
.add-btn {background:#0f65ff;color:white;border:none;padding:6px 12px;border-radius:8px;cursor:pointer;font-size:14px;}
#results {max-height:250px;overflow-y:auto;margin-top:10px;}
.message {margin-top:15px;font-weight:600;}
.success {color:#1b8a3d;}
.error {color:#d32f2f;}
</style>
<script>
function searchPatient(val){
    if(val.trim()===''){document.getElementById('results').innerHTML='';return;}
    const xhr = new XMLHttpRequest();
    xhr.open('POST','',true);
    xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    xhr.onload = function(){document.getElementById('results').innerHTML=this.responseText;}
    xhr.send('action=search&query='+encodeURIComponent(val));
}
function addPatient(pid){
    const xhr = new XMLHttpRequest();
    xhr.open('POST','',true);
    xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    xhr.onload = function(){
        const msg=document.getElementById('message');
        msg.textContent=this.responseText;
        msg.classList.add('message');
        msg.scrollIntoView({behavior:'smooth'});
    }
    xhr.send('action=add&PID='+encodeURIComponent(pid));
}
</script>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-item" onclick="location.href='dashboard.html'"><span class="material-symbols-outlined">space_dashboard</span></div>
    <div class="sidebar-item active" onclick="location.href='patients.php'"><span class="material-symbols-outlined">group</span></div>
    <div class="sidebar-item" style="opacity:.4;cursor:not-allowed;"><span class="material-symbols-outlined">calendar_month</span></div>
    <div class="sidebar-item" onclick="location.href='history.html'"><span class="material-symbols-outlined">analytics</span></div>
    <div class="sidebar-logout"><button class="btn-logout"><span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px">logout</span></button></div>
</aside>

<main class="main">
<header class="topbar">
    <div class="logo-top"><img src="images/logon2.png" alt="Logo"></div>
    <div class="profile" onclick="window.location.href='profile.php'">
        <div class="avatar"><?= strtoupper(substr($_SESSION['doctorName'], 0, 2)) ?></div>
        <div><?= htmlspecialchars($_SESSION['doctorName']) ?></div>
    </div>
</header>

<div class="container">
    <h2>Add Patient from Database</h2>
    <div class="search-box">
        <label>Search by Name or File Number</label>
        <input type="text" onkeyup="searchPatient(this.value)" placeholder="Type to search...">
        <div id="results"></div>
    </div>
    <p id="message"></p>
</div>
</main>
</body>
</html>
<?php $conn->close(); ?>
