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

// --- Simulated login (for testing) ---
if (!isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 1; // Doctor ID
}
$userID = $_SESSION['userID'];

// --- Get doctor name ---
$docRes = $conn->prepare("SELECT first_name, last_name FROM users WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// --- Handle Connect ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect'])) {
    $PID = trim($_POST['PID']);
    $Pname = trim($_POST['Pname']);

    // Find patient by ID or Name
    $find = $conn->prepare("SELECT PID FROM patients WHERE PID = ? OR first_name = ? OR last_name = ?");
    $find->bind_param("sss", $PID, $Pname, $Pname);
    $find->execute();
    $patient = $find->get_result()->fetch_assoc();
    $find->close();

    if ($patient) {
        $realPID = $patient['PID'];

        // Check if already connected
        $dup = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_patient WHERE PID = ? AND userID = ?");
        $dup->bind_param("si", $realPID, $userID);
        $dup->execute();
        $dupRes = $dup->get_result()->fetch_assoc();

        if ($dupRes['cnt'] > 0) {
            echo "<script>alert('⚠️ This patient is already under your care.');</script>";
        } else {
            // Check total doctors connected
            $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_patient WHERE PID = ?");
            $check->bind_param("s", $realPID);
            $check->execute();
            $res = $check->get_result()->fetch_assoc();

            if ($res['cnt'] >= 5) {
                echo "<script>alert('❌ This patient already has 5 doctors.');</script>";
            } else {
                $stmt = $conn->prepare("INSERT INTO user_patient (PID, userID) VALUES (?, ?)");
                $stmt->bind_param("si", $realPID, $userID);
                if ($stmt->execute()) {
                    echo "<script>alert('✅ Connected successfully!');</script>";
                } else {
                    echo "<script>alert('❌ Failed to connect.');</script>";
                }
                $stmt->close();
            }
        }
        $dup->close();
    } else {
        echo "<script>alert('❌ Patient not found.');</script>";
    }
}

// --- Get patients list ---
$sql = "SELECT PID, first_name, last_name, gender, status, phone, DOB FROM patients";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patients</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<style>
:root {
    --bg: #f2f6fb;
    --card: #ffffff;
    --accent: #0f65ff;
    --muted: #9aa6c0;
    --shadow: 0 10px 30px rgba(17,24,39,0.06);
    --radius: 14px;
}
body {
    font-family: "Inter", sans-serif;
    background: var(--bg);
    color: #15314b;
    margin: 0;
    display: flex;
}
.sidebar {
    width: 88px;
    height: 100vh;
    background: linear-gradient(180deg,#fbfdff,#f3f7ff);
    display:flex;flex-direction:column;align-items:center;
    padding:24px 12px;gap:24px;position:fixed;
}
.sidebar-item {
    width:60px;height:48px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    color:var(--accent);cursor:pointer;transition:.18s;
}
.sidebar-item:hover {transform:translateX(4px);background:rgba(15,101,255,0.08);}
.sidebar-item.active {
    background:linear-gradient(180deg,rgba(15,101,255,0.08),rgba(15,101,255,0.03));
    border-radius:20px;width:72px;height:56px;
}
.sidebar-logout {margin-top:auto;margin-bottom:40px;}
.btn-logout {
    background:linear-gradient(90deg,#0f65ff,#5aa6ff);
    color:white;border:none;padding:10px;
    border-radius:12px;cursor:pointer;
    box-shadow:0 8px 20px rgba(15,101,255,0.14);
}
.main {margin-left:88px;flex:1;display:flex;flex-direction:column;}
header.topbar {
    height:84px;display:flex;align-items:center;justify-content:space-between;
    padding:10px 36px;background:linear-gradient(180deg,rgba(255,255,255,0.9),rgba(255,255,255,0.7));
    border-bottom:1px solid rgba(15,21,40,0.04);
}
.logo-top img {width:220px;}
.profile {display:flex;align-items:center;gap:10px;cursor:pointer;}
.avatar {
    width:36px;height:36px;border-radius:50%;
    background:linear-gradient(180deg,#2e9cff,#1a57ff);
    color:white;display:flex;align-items:center;justify-content:center;font-weight:600;
}
.action-bar {
    display:flex;justify-content:space-between;align-items:center;padding:20px 36px 0;
}
.action-group {display:flex;gap:16px;align-items:center;}
.action-btn {
    background:#e8f0ff;color:#1976d2;border:none;
    padding:10px 16px;border-radius:10px;font-weight:600;
    cursor:pointer;display:flex;align-items:center;gap:6px;transition:.2s;
}
.action-btn:hover {background:#dbe7ff;}
.search-box {
    display:flex;align-items:center;background:white;border:1px solid rgba(15,21,40,0.08);
    border-radius:12px;padding:6px 12px;box-shadow:0 2px 6px rgba(15,21,40,0.05);width:250px;
}
.search-box input {border:none;outline:none;background:transparent;width:100%;font-size:14px;}
.search-box span {color:#6b7b8f;font-size:20px;}
table {
    width:calc(100% - 72px);margin:20px auto;background:white;
    border-collapse:collapse;border-radius:12px;box-shadow:var(--shadow);overflow:hidden;
}
th,td {padding:12px;text-align:center;border-bottom:1px solid #eee;}
th {background:#f3f6fb;}
.status {padding:4px 8px;border-radius:12px;font-size:13px;font-weight:500;}
.status.normal {background:#eaf9ee;color:#1b8a3d;}
.status.moderate {background:#fff7e6;color:#e6a100;}
.status.critical {background:#fdeaea;color:#d32f2f;}
footer {
    display:flex;justify-content:space-between;align-items:center;
    padding:10px 36px;background:#fff;border-top:1px solid rgba(15,21,40,0.04);
    font-size:14px;color:#2f4c6f;
}
footer img {width:200px;}
.modal {
    display:none;position:fixed;z-index:999;left:0;top:0;width:100%;height:100%;
    background:rgba(0,0,0,0.4);justify-content:center;align-items:center;
}
.modal-content {
    background:#fff;padding:20px;border-radius:14px;width:360px;text-align:center;
    box-shadow:0 4px 20px rgba(0,0,0,0.15);
}
.modal-content input {
    width:80%;padding:8px;border:1px solid #ccc;border-radius:8px;margin:8px 0;
}
.modal-content button {
    margin-top:10px;padding:8px 16px;border:none;border-radius:8px;
    background:#0f65ff;color:#fff;cursor:pointer;font-weight:600;
}
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
        <button class="btn-logout">
            <span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px">logout</span>
        </button>
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

<div class="action-bar">
    <h2 style="color:#1f46b6;">Patients</h2>
    <div class="action-group">
        <div class="search-box">
            <span class="material-symbols-outlined">search</span>
            <input type="text" id="searchInput" placeholder="Search patient...">
        </div>
        <button class="action-btn" onclick="window.location.href='addPatient.php'">
            <span class="material-symbols-outlined">add</span> Add
        </button>
        <button class="action-btn" id="connectBtn">
            <span class="material-symbols-outlined">link</span> Connect
        </button>
    </div>
</div>

<table id="patientsTable">
    <thead>
        <tr>
            <th>ID</th><th>First Name</th><th>Last Name</th>
            <th>Gender</th><th>Status</th><th>Phone</th><th>DOB</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['ID']) ?></td>
                <td><?= htmlspecialchars($row['first_name']) ?></td>
                <td><?= htmlspecialchars($row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['gender']) ?></td>
                <td><span class="status <?= strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><?= htmlspecialchars($row['DOB']) ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="7">No patients found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<footer>
    <div>Resources | Support | Developers</div>
    <div><img src="images/logon2.png" alt="Logo"></div>
</footer>
</main>

<!-- Connect Modal -->
<div class="modal" id="connectModal">
    <div class="modal-content">
        <h3>Connect to a Patient</h3>
        <form method="POST">
            <input type="text" name="PID" placeholder="Enter Patient ID">
            <input type="text" name="Pname" placeholder="Or Patient Name">
            <button type="submit" name="connect">Connect</button>
            <button type="button" onclick="closeModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll("#patientsTable tbody tr").forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(filter) ? "" : "none";
    });
});
const modal = document.getElementById("connectModal");
document.getElementById("connectBtn").addEventListener("click", ()=> modal.style.display="flex");
function closeModal(){modal.style.display="none";}
window.onclick = e => {if(e.target==modal)closeModal();}
</script>
</body>
</html>
<?php $conn->close(); ?>
