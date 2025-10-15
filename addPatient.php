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

// --- Simulated login (remove later when you have real login) ---
if (!isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 1;
}
$userID = $_SESSION['userID'];

// --- Get doctor name ---
$docRes = $conn->prepare("SELECT first_name, last_name FROM users WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// --- Add Patient logic ---
$success = $error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["addPatient"])) {
    $PID = trim($_POST["PID"]);
    $first_name = trim($_POST["first_name"]);
    $last_name  = trim($_POST["last_name"]);
    $gender     = trim($_POST["gender"]);
    $status     = trim($_POST["status"]);
    $phone      = trim($_POST["phone"]);
    $DOB        = trim($_POST["DOB"]);

    if ($PID && $first_name && $last_name) {
        // Check if patient exists
        $check = $conn->prepare("SELECT PID FROM patients WHERE PID=?");
        $check->bind_param("s", $PID);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            // Check if already under this doctor
            $checkLink = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_patient WHERE PID=? AND userID=?");
            $checkLink->bind_param("si", $PID, $userID);
            $checkLink->execute();
            $linkData = $checkLink->get_result()->fetch_assoc();
            $checkLink->close();

            if ($linkData['cnt'] > 0) {
                $error = "⚠️ This patient is already under your care.";
            } else {
                // Count connected doctors
                $count = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_patient WHERE PID=?");
                $count->bind_param("s", $PID);
                $count->execute();
                $c = $count->get_result()->fetch_assoc();
                $count->close();

                if ($c['cnt'] >= 5) {
                    $error = "❌ This patient already has 5 doctors.";
                } else {
                    $error = "ℹ️ This patient exists in the system. Please connect from the Patients page.";
                }
            }
        } else {
            // Add new patient and connect automatically
            $stmt = $conn->prepare("INSERT INTO patients (PID, first_name, last_name, gender, status, phone, DOB) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $PID, $first_name, $last_name, $gender, $status, $phone, $DOB);
            if ($stmt->execute()) {
                $link = $conn->prepare("INSERT INTO user_patient (PID, userID) VALUES (?, ?)");
                $link->bind_param("si", $PID, $userID);
                $link->execute();
                $link->close();
                $success = "✅ Patient added and connected successfully!";
            } else {
                $error = "❌ Failed to add patient.";
            }
            $stmt->close();
        }
    } else {
        $error = "⚠️ Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Patient</title>
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
.container {
    display:flex;justify-content:center;align-items:center;
    height:calc(100vh - 120px);flex-direction:column;text-align:center;
}
h2 {color:#1f46b6;margin-bottom:20px;}
form {background:#fff;padding:30px;border-radius:14px;box-shadow:var(--shadow);
max-width:500px;width:100%;text-align:left;}
label {display:block;margin-bottom:5px;font-weight:600;}
input,select {width:100%;padding:10px;margin-bottom:15px;border:1px solid rgba(0,0,0,0.1);border-radius:10px;}
button {padding:10px 16px;border:none;border-radius:10px;background:#0f65ff;color:white;font-weight:600;cursor:pointer;}
.alert {margin-top:15px;font-weight:600;}
.success {color:#1b8a3d;}
.error {color:#d32f2f;}
.back-btn {
    margin-top:20px;padding:10px 16px;border:none;border-radius:10px;
    background:#9aa6c0;color:white;font-weight:600;cursor:pointer;
}
.back-btn:hover {background:#8b97ad;}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-item" onclick="location.href='dashboard.html'">
        <span class="material-symbols-outlined">space_dashboard</span>
    </div>
    <div class="sidebar-item active" onclick="location.href='patients.php'">
        <span class="material-symbols-outlined">group</span>
    </div>
    <div class="sidebar-item" style="opacity:.4;cursor:not-allowed;">
        <span class="material-symbols-outlined">calendar_month</span>
    </div>
    <div class="sidebar-item" onclick="location.href='history.html'">
        <span class="material-symbols-outlined">analytics</span>
    </div>
    <div class="sidebar-logout">
        <button class="btn-logout"><span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px">logout</span></button>
    </div>
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
    <h2>Add Patient</h2>
    <form method="POST">
        <label>Patient ID</label>
        <input type="text" name="PID" required>

        <label>First Name</label>
        <input type="text" name="first_name" required>

        <label>Last Name</label>
        <input type="text" name="last_name" required>

        <label>Gender</label>
        <select name="gender" required>
            <option value="">Select...</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
        </select>

        <label>Status</label>
        <select name="status" required>
            <option value="Normal">Normal</option>
            <option value="Moderate">Moderate</option>
            <option value="Critical">Critical</option>
        </select>

        <label>Phone</label>
        <input type="text" name="phone">

        <label>Date of Birth</label>
        <input type="date" name="DOB">

        <button type="submit" name="addPatient">Add Patient</button>
    </form>

    <button class="back-btn" onclick="window.location.href='patients.php'">← Back to Patients</button>

    <?php if ($success): ?><p class="alert success"><?= $success ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert error"><?= $error ?></p><?php endif; ?>
</div>
</main>
</body>
</html>
<?php $conn->close(); ?>