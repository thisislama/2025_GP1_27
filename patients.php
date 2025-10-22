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
$_SESSION['userID'] = 1; 
$userID = $_SESSION['userID'];

// --- Get doctor name ---
$docRes = $conn->prepare("SELECT first_name, last_name FROM user WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// --- AJAX requests ---
if (isset($_POST['ajax'])) {
    $action = $_POST['ajax'];
    $response = ["type" => "error", "msg" => "⚠️ Unknown error."];

    // 🔹 Search patient
    if ($action === 'searchPatient') {
        $input = trim($_POST['query']);
        $find = $conn->prepare("SELECT PID, first_name, last_name FROM patient WHERE PID=? OR first_name=? OR last_name=?");
        $find->bind_param("sss", $input, $input, $input);
        $find->execute();
        $patient = $find->get_result()->fetch_assoc();
        $find->close();

        if ($patient) {
            $PID = $patient['PID'];

            // Check if already linked with this doctor
            $dup = $conn->prepare("SELECT COUNT(*) AS c FROM user_patient WHERE PID=? AND userID=?");
            $dup->bind_param("si", $PID, $userID);
            $dup->execute();
            $dupRes = $dup->get_result()->fetch_assoc();
            $dup->close();

            if ($dupRes['c'] > 0) {
                $response = ["type" => "info", "msg" => "🩺 This patient is already under your care."];
            } else {
                // Check other doctors linked
                $check = $conn->prepare("SELECT u.first_name, u.last_name 
                                         FROM user_patient up 
                                         INNER JOIN user u ON up.userID = u.userID
                                         WHERE up.PID = ?");
                $check->bind_param("s", $PID);
                $check->execute();
                $linked = $check->get_result()->fetch_all(MYSQLI_ASSOC);
                $check->close();

                if ($linked && count($linked) > 0) {
                    $docs = array_map(fn($d) => "{$d['first_name']} {$d['last_name']}", $linked);
                    $docList = implode(", ", $docs);
                    $response = [
                        "type" => "warn",
                        "msg" => "👨‍⚕️ This patient is under care of: $docList — you can still connect.",
                        "PID" => $PID
                    ];
                } else {
                    $response = [
                        "type" => "ready",
                        "msg" => "✅ Found patient {$patient['first_name']} {$patient['last_name']} — you can connect now.",
                        "PID" => $PID
                    ];
                }
            }
        } else {
            $response = ["type" => "error", "msg" => "❌ Patient not found in database."];
        }
    }

    // 🔹 Connect
    elseif ($action === 'connect') {
        $PID = $_POST['PID'];
        $add = $conn->prepare("INSERT INTO user_patient (PID, userID) VALUES (?, ?)");
        $add->bind_param("si", $PID, $userID);
        if ($add->execute())
            $response = ["type" => "success", "msg" => "✅ Connected successfully!"];
        else
            $response = ["type" => "error", "msg" => "❌ Failed to connect."];
        $add->close();
    }

    // 🔹 Disconnect
    elseif ($action === 'disconnect') {
        $PID = $_POST['PID'];
        $conn->query("DELETE FROM user_patient WHERE PID='$PID' AND userID='$userID'");
        $response = ["type" => "info", "msg" => "🔗 Disconnected successfully!"];
    }

    // 🔹 Delete
    elseif ($action === 'delete') {
        $PID = $_POST['PID'];
        $conn->query("DELETE FROM user_patient WHERE PID='$PID'");
        $conn->query("DELETE FROM patient WHERE PID='$PID'");
        $response = ["type" => "success", "msg" => "🗑️ Patient deleted successfully!"];
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
<title>Tanafs Patients</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
<style>
body{font-family:"Inter",sans-serif;background:#f2f6fb;margin:0;}
.table-container{background:white;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(17,24,39,0.06);}
.table-actions{display:flex;justify-content:space-between;margin-bottom:15px;}
.table-actions input{padding:8px 12px;border-radius:8px;border:1px solid #ccc;width:70%;}
.table-actions button{padding:8px 14px;border:none;border-radius:8px;cursor:pointer;font-weight:600;}
.add-btn{background-color:#0a77e3;color:white;}
.connect-btn{background-color:#eef6ff;color:#0a77e3;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{padding:12px;text-align:center;border-bottom:1px solid #eee;}
th{background-color:#f4f6fc;color:#1f46b6;}
.action-icons span{cursor:pointer;padding:4px;border-radius:6px;margin:0 4px;color:#0f65ff;transition:.2s;}
.action-icons span:hover{background:#eef6ff;}
.modal{display:none;position:fixed;z-index:999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.4);justify-content:center;align-items:center;}
.modal-content{background:#fff;padding:20px;border-radius:14px;width:360px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.15);}
.modal-content input{width:80%;padding:8px;border:1px solid #ccc;border-radius:8px;margin:8px 0;}
.modal-content button{margin-top:10px;padding:8px 16px;border:none;border-radius:8px;background:#0f65ff;color:#fff;cursor:pointer;font-weight:600;}
#connectMsg{margin-top:10px;font-weight:600;}
.success{color:#0a7e1e;} .error{color:#c00;} .warn{color:#b97900;} .info{color:#0b65d9;} .ready{color:#0a7e1e;}
</style>
</head>

<body>
<main style="padding:40px;">
  <div class="table-container">
    <div class="table-actions">
      <input type="text" id="search" placeholder="Search patient...">
      <div>
        <button class="connect-btn" onclick="window.location.href='addPatient.php'">Add</button>
        <button class="add-btn" id="connectBtn">Connect</button>
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
    <input type="text" id="patient_search" placeholder="Enter Patient ID or Name" required>
    <button id="searchBtn">Check</button>
    <div id="connectMsg"></div>
    <div style="margin-top:10px;">
      <button id="confirmConnect" style="display:none;">Connect</button>
      <button type="button" onclick="closeModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
const modal=document.getElementById("connectModal");
document.getElementById("connectBtn").onclick=()=>modal.style.display="flex";
function closeModal(){modal.style.display="none";msg("");document.getElementById('confirmConnect').style.display="none";}
window.onclick=e=>{if(e.target==modal)closeModal();};
function msg(txt,cls){document.getElementById('connectMsg').innerHTML=`<span class="${cls}">${txt}</span>`;}

document.getElementById("searchBtn").onclick=()=>{
  const val=document.getElementById("patient_search").value.trim();
  if(!val)return msg("Please enter something.","error");
  msg("⏳ Searching...","info");
  fetch("",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:`ajax=searchPatient&query=${encodeURIComponent(val)}`})
  .then(r=>r.json()).then(res=>{
    msg(res.msg,res.type);
    if(res.PID){const btn=document.getElementById("confirmConnect");btn.style.display="inline-block";btn.dataset.pid=res.PID;}
    else document.getElementById("confirmConnect").style.display="none";
  });
};

document.getElementById("confirmConnect").onclick=e=>{
  const pid=e.target.dataset.pid;
  msg("⏳ Connecting...","info");
  fetch("",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:`ajax=connect&PID=${pid}`})
  .then(r=>r.json()).then(res=>{
    msg(res.msg,res.type);
    if(res.type==="success")setTimeout(()=>location.reload(),1200);
  });
};

function performAction(action,pid){
  if(!confirm(`Are you sure you want to ${action} this patient?`))return;
  fetch("",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:`ajax=${action}&PID=${pid}`})
  .then(r=>r.json()).then(res=>{
    document.getElementById("message").innerHTML=`<span class="${res.type}">${res.msg}</span>`;
    if(res.type==="success"||res.type==="info")document.getElementById('row-'+pid)?.remove();
  });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
