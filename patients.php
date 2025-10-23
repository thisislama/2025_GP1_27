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

// Simulated login
$_SESSION['userID'] = 1;
$userID = $_SESSION['userID'];

// --- Doctor name ---
$docRes = $conn->prepare("SELECT first_name, last_name FROM user WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// --- Handle AJAX actions ---
if (isset($_POST['ajax'])) {
    $action = $_POST['ajax'];
    $response = ["type" => "error", "msg" => "⚠️ Unknown error."];

    // 🔹 Connect (simplified)
    if ($action === 'connect') {
        $input = trim($_POST['PID']);
        $find = $conn->prepare("SELECT PID, first_name, last_name FROM patient WHERE PID=? OR first_name=? OR last_name=?");
        $find->bind_param("sss", $input, $input, $input);
        $find->execute();
        $patient = $find->get_result()->fetch_assoc();
        $find->close();

        if (!$patient) {
            $response = ["type" => "error", "msg" => "❌ Patient not found in Tanafs. Please add the patient through Add Patient."];
        } else {
            $PID = $patient['PID'];
            $same = $conn->prepare("SELECT COUNT(*) AS c FROM user_patient WHERE PID=? AND userID=?");
            $same->bind_param("si", $PID, $userID);
            $same->execute();
            $sameRes = $same->get_result()->fetch_assoc();
            $same->close();

            if ($sameRes['c'] > 0) {
                $response = ["type" => "info", "msg" => "⚠️ This patient is already under your care."];
            } else {
                $check = $conn->prepare("SELECT u.first_name, u.last_name FROM user_patient up INNER JOIN user u ON up.userID = u.userID WHERE up.PID = ?");
                $check->bind_param("s", $PID);
                $check->execute();
                $linked = $check->get_result()->fetch_all(MYSQLI_ASSOC);
                $check->close();

                if ($linked && count($linked) > 0) {
                    $docs = array_map(fn($d) => "{$d['first_name']} {$d['last_name']}", $linked);
                    $docList = implode(", ", $docs);
                    $add = $conn->prepare("INSERT INTO user_patient (PID, userID) VALUES (?, ?)");
                    $add->bind_param("si", $PID, $userID);
                    $add->execute();
                    $add->close();
                    $response = ["type" => "success", "msg" => "⚕️ This patient is under care of: $docList — connected successfully!"];
                } else {
                    $add = $conn->prepare("INSERT INTO user_patient (PID, userID) VALUES (?, ?)");
                    $add->bind_param("si", $PID, $userID);
                    $add->execute();
                    $add->close();
                    $response = ["type" => "success", "msg" => "✅ Connected successfully!"];
                }
            }
        }
    }

    // 🔹 Disconnect
    elseif ($action === 'disconnect') {
        $PID = $_POST['PID'];
        $conn->query("DELETE FROM user_patient WHERE PID='$PID' AND userID='$userID'");
        $response = ["type" => "info", "msg" => "Disconnected successfully!"];
    }

    // 🔹 Delete
    elseif ($action === 'delete') {
        $PID = $_POST['PID'];
        $conn->query("DELETE FROM user_patient WHERE PID='$PID'");
        $conn->query("DELETE FROM patient WHERE PID='$PID'");
        $response = ["type" => "success", "msg" => "️Patient deleted successfully!"];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- Get patients linked to current doctor ---
$sql = "SELECT p.PID, p.first_name, p.last_name, p.gender, p.status, p.phone, p.DOB
        FROM patient p
        INNER JOIN user_patient up ON p.PID = up.PID
        WHERE up.userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Patients List - TANAFS</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

<style>
<?php include 'dashboard-style.css';  ?>

.table-card {
  background:#fff;
  border-radius:16px;
  padding:20px;
  box-shadow:0 8px 20px rgba(0,0,0,0.06);
  width:90%;
  margin:auto;
}
.table-actions {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:15px;
}
.table-actions input {
  padding:8px 12px;
  border-radius:8px;
  border:1px solid #ccc;
  width:70%;
}
.table-actions button {
  padding:8px 14px;
  border:none;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
}
.add-btn {
  background-color:#0f65ff;
  color:white;
}
.connect-btn {
  background-color:#eef6ff;
  color:#0f65ff;
}
table {
  width:100%;
  border-collapse:collapse;
  margin-top:20px;
}
th,td {
  padding:12px;
  text-align:center;
  border-bottom:1px solid #eee;
}
th {
  background-color:#f4f6fc;
  color:#1f46b6;
}
.action-icons span {
  cursor:pointer;
  padding:4px;
  border-radius:6px;
  margin:0 4px;
  color:#0f65ff;
  transition:.2s;
}
.action-icons span:hover {
  background:#eef6ff;
}
.modal {
  display:none;
  position:fixed;
  z-index:999;
  left:0;
  top:0;
  width:100%;
  height:100%;
  background:rgba(0,0,0,0.4);
  justify-content:center;
  align-items:center;
}
.modal-content {
  background:#fff;
  padding:20px;
  border-radius:14px;
  width:360px;
  text-align:center;
  box-shadow:0 4px 20px rgba(0,0,0,0.15);
}
.modal-content input {
  width:80%;
  padding:8px;
  border:1px solid #ccc;
  border-radius:8px;
  margin:8px 0;
}
.modal-content button {
  margin-top:10px;
  padding:8px 16px;
  border:none;
  border-radius:8px;
  background:#0f65ff;
  color:#fff;
  cursor:pointer;
  font-weight:600;
}
#connectMsg {
  margin-top:10px;
  font-weight:600;
}
.success {color:#0a7e1e;}
.error {color:#c00;}
.warn {color:#b97900;}
.info {color:#0b65d9;}
</style>
</head>

<body>
<div class="wrapper">
  <!-- Header -->
  <img class="topimg" src="Images/Group 8.png" alt="img">
  <img class="logo" src="Images/Logo.png" alt="Tanafs Logo">

  <nav class="auth-nav" aria-label="User navigation">
    <div class="search" role="search" aria-label="Site search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
        <path d="M21 21l-4.35-4.35" stroke="#6b7b8f" stroke-width="1.6" stroke-linecap="round"></path>
        <circle cx="11" cy="11" r="5.2" stroke="#6b7b8f" stroke-width="1.6"></circle>
      </svg>
      <input placeholder="Search ..." aria-label="Search">
    </div>
    <a class="nav-link" href="patients.php">Patients</a>
    <a class="nav-link" href="history2.php">History</a>
    <button class="profile-btn">
      <div class="profile">
        <img class="avatar-icon" src="images/profile.png" alt="Profile"/>
      </div>
    </button>
    <button class="btn-logout">Logout</button>
  </nav>

  <!-- Page Content -->
  <main style="margin-top:130px; text-align:center;">
    <h2 style="color:#1f45b5; font-size:1.8em; margin-bottom:25px;text-align: left;">Patients List</h2>

    <div class="table-card">
      <div class="table-actions">
        <input type="text" id="search" placeholder="Search patient...">
        <div>
          <button class="connect-btn" id="connectBtn">Connect</button>
          <button class="add-btn" onclick="window.location.href='addPatient.php'">Add</button>
        </div>
      </div>

      <table id="patientsTable">
        <thead><tr><th>ID</th><th>First</th><th>Last</th><th>Gender</th><th>Status</th><th>Phone</th><th>DOB</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if ($result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
          <tr id="row-<?= $row['PID'] ?>">
            <td><?= htmlspecialchars($row['PID']) ?></td>
            <td><?= htmlspecialchars($row['first_name']) ?></td>
            <td><?= htmlspecialchars($row['last_name']) ?></td>
            <td><?= htmlspecialchars($row['gender']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['DOB']) ?></td>
            <td class="action-icons">
              <span class="material-symbols-outlined" onclick="performAction('disconnect','<?= $row['PID'] ?>')">link_off</span>
              <span class="material-symbols-outlined" onclick="performAction('delete','<?= $row['PID'] ?>')">delete</span>
            </td>
          </tr>
        <?php endwhile; else: ?><tr><td colspan="8">No patients linked to you.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <p id="message" class="message"></p>
    </div>
  </main>

  <!-- Connect Modal -->
  <div class="modal" id="connectModal">
    <div class="modal-content">
      <h3>Connect to a Patient</h3>
      <input type="text" id="patient_input" placeholder="Enter Patient ID or Name" required>
      <div style="margin-top:10px;">
        <button id="connectNow">Connect</button>
        <button type="button" onclick="closeModal()">Cancel</button>
      </div>
      <div id="connectMsg"></div>
    </div>
  </div>

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
const modal=document.getElementById("connectModal");
document.getElementById("connectBtn").onclick=()=>modal.style.display="flex";
function closeModal(){modal.style.display="none";msg("");}
window.onclick=e=>{if(e.target==modal)closeModal();};
function msg(txt,cls="info"){document.getElementById('connectMsg').innerHTML=`<span class="${cls}">${txt}</span>`;}

document.getElementById("connectNow").onclick=()=>{
  const val=document.getElementById("patient_input").value.trim();
  if(!val)return msg("Please enter a patient ID or name.","error");
  msg("⏳ Processing...","info");
  fetch("",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:`ajax=connect&PID=${encodeURIComponent(val)}`})
  .then(r=>r.json()).then(res=>{msg(res.msg,res.type);if(res.type==="success")setTimeout(()=>location.reload(),1500);});
};

function performAction(action,pid){
  if(!confirm(`Are you sure you want to ${action} this patient?`))return;
  fetch("",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:`ajax=${action}&PID=${pid}`})
  .then(r=>r.json()).then(res=>{
    document.getElementById("message").innerHTML=`<span class="${res.type}">${res.msg}</span>`;
    if(res.type==="success"||res.type==="info")document.getElementById('row-'+pid)?.remove();
  });
}

document.getElementById('search').addEventListener('keyup',function(){
  const term=this.value.toLowerCase();
  document.querySelectorAll('#patientsTable tbody tr').forEach(row=>{
    row.style.display=row.textContent.toLowerCase().includes(term)?'':'none';
  });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
