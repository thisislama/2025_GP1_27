<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (empty($_SESSION['user_id'])) {
    if (!empty($_POST['action'])) { http_response_code(401); echo "❌ Unauthorized. Please sign in."; exit; }
    header("Location: signin.php"); exit;
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

// Get doctor name
$docRes = $conn->prepare("SELECT first_name, last_name FROM healthcareprofessional WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$docRes->close();

if (!$docData) { session_unset(); session_destroy(); header("Location: signin.php"); exit; }

$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];

define('PATIENTS_JSON', 'C:/MAMP/htdocs/2025_GP_27/data/patients_record.json');

function loadPatientsFromJson($path = PATIENTS_JSON){
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];

    $rows = isset($data['hospital_records']) && is_array($data['hospital_records'])
        ? $data['hospital_records'] : $data;

    return array_map(function($r){
        return [
            'PID'        => (string)($r['PID'] ?? ''),
            'first_name' => trim((string)($r['first_name'] ?? '')),
            'last_name'  => trim((string)($r['last_name']  ?? '')),
            'gender'     => trim((string)($r['gender']     ?? '')),
            'status'     => trim((string)($r['status']     ?? '')),
            'phone'      => trim((string)($r['phone']      ?? '')),
            'DOB'        => trim((string)($r['DOB']        ?? '')),
        ];
    }, $rows);
}
if (isset($_POST['action']) && $_POST['action'] === 'search') {
    $query = trim($_POST['query'] ?? '');
    $results = [];
    if ($query !== '') {
        $all = loadPatientsFromJson();
        $q = mb_strtolower($query, 'UTF-8');
        foreach ($all as $row) {
            $pid = $row['PID']; $fn = $row['first_name']; $ln = $row['last_name'];
            if (
                strpos(mb_strtolower($pid, 'UTF-8'), $q) !== false ||
                strpos(mb_strtolower($fn , 'UTF-8'), $q) !== false ||
                strpos(mb_strtolower($ln , 'UTF-8'), $q) !== false
            ) {
                $results[] = $row;
                if (count($results) >= 10) break; 
            }
        }
    }

    if ($results) {
        foreach ($results as $row) {
            $pid  = htmlspecialchars($row['PID']);
            $name = htmlspecialchars($row['first_name'].' '.$row['last_name']);
            echo "<div class='result-item'>
                    <div><strong>{$pid}</strong> - {$name}</div>
                    <button class='add-btn' onclick=\"addPatient('{$pid}')\">Add</button>
                  </div>";
        }
    } else {
        echo "<div class='result-item' style='color:#888;'>No matches found.</div>";
    }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $PID = trim($_POST['PID'] ?? '');
    if ($PID === '') { echo "❌ Missing PID."; exit; }

    $pidParam = ctype_digit($PID) ? (int)$PID : $PID;
    $get = $conn->prepare("SELECT PID, first_name, last_name, gender, status, phone, DOB FROM patient WHERE PID=? LIMIT 1");
    $get->bind_param(ctype_digit($PID) ? "i" : "s", $pidParam);
    $get->execute();
    $dbPatient = $get->get_result()->fetch_assoc();
    $get->close();

    $dup = $conn->prepare("SELECT COUNT(*) AS c FROM patient_doctor_assignments WHERE PID=? AND userID=?");
    $dup->bind_param(ctype_digit($PID) ? "ii" : "si", $pidParam, $userID);
    $dup->execute();
    $c = $dup->get_result()->fetch_assoc();
    $dup->close();

    if ($dbPatient && !empty($c['c'])) {
        $full = sprintf(
            "👤 %s %s | PID: %s | Gender: %s | Status: %s | Phone: %s | DOB: %s",
            $dbPatient['first_name'] ?? '', $dbPatient['last_name'] ?? '',
            $dbPatient['PID'] ?? '', $dbPatient['gender'] ?? '',
            $dbPatient['status'] ?? '', $dbPatient['phone'] ?? '',
            $dbPatient['DOB'] ?? ''
        );
        echo "ℹ️ Patient is already under your care.<br>$full";
        exit;
    }

    if ($dbPatient && empty($c['c'])) {
        $link = $conn->prepare("INSERT INTO patient_doctor_assignments (PID, userID) VALUES (?, ?)");
        $link->bind_param(ctype_digit($PID) ? "ii" : "si", $pidParam, $userID);
        if ($link->execute()) {
echo "✅ Connected successfully to existing patient!<br>
<a href='patients.php' style='
  display:inline-block;
  margin-top:12px;
  background:#0f65ff;
  color:white;
  padding:8px 16px;
  border-radius:8px;
  text-decoration:none;
  font-weight:600;
'>Return to Patients</a>";
        } else {
            echo "❌ Failed to connect to existing patient.";
        }
        $link->close();
        exit;
    }

    $rec = null;
    foreach (loadPatientsFromJson() as $r) {
        if ((string)$r['PID'] === (string)$PID) { $rec = $r; break; }
    }
    if (!$rec) { echo "❌ Record not found in JSON."; exit; }

    $colsQ = $conn->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patient'
    ");
    $colsQ->execute();
    $rs = $colsQ->get_result();
    $tableCols = [];
    while ($cRow = $rs->fetch_assoc()) $tableCols[] = $cRow['COLUMN_NAME'];
    $colsQ->close();

    $candidate = [
        'PID'        => $PID,
        'first_name' => $rec['first_name'] ?? '',
        'last_name'  => $rec['last_name']  ?? '',
        'gender'     => $rec['gender']     ?? '',
        'status'     => $rec['status']     ?? '',
        'phone'      => $rec['phone']      ?? '',
        'DOB'        => $rec['DOB']        ?? '',
    ];

    $cols = []; $vals = [];
    foreach ($candidate as $col => $val) {
        if (in_array($col, $tableCols, true)) { $cols[] = "`$col`"; $vals[] = $val; }
    }
    if (!$cols) { echo "❌ No valid columns to insert."; exit; }

    $placeholders = implode(',', array_fill(0, count($vals), '?'));
    $sql = "INSERT INTO `patient` (".implode(',', $cols).") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($vals));
    $stmt->bind_param($types, ...$vals);
    if (!$stmt->execute()) { echo "❌ Failed to insert into patient."; $stmt->close(); exit; }
    $stmt->close();

echo "✅ Added to patient successfully!<br>
<a href='patients.php' style='
  display:inline-block;
  margin-top:12px;
  background:#0f65ff;
  color:white;
  padding:8px 16px;
  border-radius:8px;
  text-decoration:none;
  font-weight:600;
'>Return to Patients</a>";
exit;

}
// ========== AJAX HANDLERS ==========
if (isset($_POST['ajax']) && $_POST['ajax'] === 'search_patients') {
    $q = trim($_POST['q'] ?? '');
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("
        SELECT p.PID, p.first_name, p.last_name, p.status,
               CASE WHEN pda.userID IS NULL THEN 0 ELSE 1 END AS linked
        FROM patient p
        LEFT JOIN patient_doctor_assignments pda 
          ON p.PID = pda.PID AND pda.userID = ?
        WHERE (? = '' OR p.PID LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)
        ORDER BY p.PID
            LIMIT 50
    ");
    $stmt->bind_param('issss', $userID, $q, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    ob_start();
    echo '<table class="mini-table"><thead><tr>
          <th>ID</th><th>First</th><th>Last</th><th>Status</th><th>Action</th>
          </tr></thead><tbody>';
    if ($res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $pid = htmlspecialchars($r['PID']);
            $fn = htmlspecialchars($r['first_name']);
            $ln = htmlspecialchars($r['last_name']);
            $st = htmlspecialchars($r['status']);
            $linked = (int)$r['linked'] === 1;
            echo "<tr id='mini-$pid'>
                    <td>$pid</td>
                    <td>$fn</td>
                    <td>$ln</td>
                    <td>$st</td>
                    <td>";
            if ($linked) {
                echo "<span class='tag-linked'>Linked</span>";
            } else {
                echo "<button class='btn-mini-connect' onclick=\"connectTo('$pid')\">Connect</button>";
            }
            echo "</td></tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='empty-note'>No patients found.</td></tr>";
    }
    echo "</tbody></table>";
    echo json_encode(['type' => 'success', 'html' => ob_get_clean()]);
    exit;
}

if (isset($_POST['ajax']) && $_POST['ajax'] === 'connect') {
    $PID = trim($_POST['PID'] ?? '');
    if ($PID === '') { echo json_encode(['type'=>'error','msg'=>'❌ Missing PID']); exit; }

    $check = $conn->prepare("SELECT PID FROM patient WHERE PID=?");
    $check->bind_param("s", $PID);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$exists) { echo json_encode(['type'=>'error','msg'=>'❌ Patient not found']); exit; }

    $dup = $conn->prepare("SELECT COUNT(*) c FROM patient_doctor_assignments WHERE PID=? AND userID=?");
    $dup->bind_param("si", $PID, $userID);
    $dup->execute();
    $c = $dup->get_result()->fetch_assoc()['c'] ?? 0;
    $dup->close();
    if ($c > 0) { echo json_encode(['type'=>'info','msg'=>'⚠️ Already linked']); exit; }

    $add = $conn->prepare("INSERT INTO patient_doctor_assignments (PID, userID) VALUES (?, ?)");
    $add->bind_param("si", $PID, $userID);
    if ($add->execute()) {
        echo json_encode(['type'=>'success','msg'=>'✅ Linked successfully!']);
    } else {
        echo json_encode(['type'=>'error','msg'=>'❌ Failed to link']); 
    }
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
.profile { display: flex; gap: 0.625em; align-items: center;  padding: 0.375em 0.625em; }
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
/* ===== Connect Modal Style ===== */
.modal{
  display:none; position:fixed; inset:0; z-index:999;
  background: rgba(19,32,56,.35);
  backdrop-filter: blur(0.125em);
  justify-content:center; align-items:center;
}
.modal-content{
  background:#fff; width:min(92vw,26.25em);
  padding:1.375em 1.25em;
  border-radius:1em;
  box-shadow:0 1.25em 2.5em rgba(17,24,39,.18);
  border:1px solid rgba(15,101,255,.08);
  text-align:center;
}
.modal-content h3{margin:0 0 0.5em;color:#0B83FE;}
.modal-content input{
  width:100%;padding:0.75em 0.875em;
  border-radius:0.75em;
  border:0.0625em solid rgba(0,0,0,.12);
  outline:none;transition:border-color .2s,box-shadow .2s;
}
.modal-content input:focus{
  border-color:rgba(15,101,255,.35);
  box-shadow:0 0 0 0.25em rgba(15,101,255,.08);
}
.modal-content button{
  border-radius:0.75em;padding:0.625em 0.875em;
  font-weight:700;border:0;cursor:pointer;
}
.modal-content button[type="button"]{
  background:#eef6ff;color:#0f65ff;margin-left:0.375em;
}
#connectMsg{margin-top:0.625em;font-weight:600;}
.success{color:#0a7e1e;} .error{color:#c00;}
.warn{color:#b97900;} .info{color:#0b65d9;}
.mini-table{width:100%;border-collapse:collapse;}
.mini-table th,.mini-table td{
  padding:.5em;border-bottom:1px solid #eee;text-align:center;
}
.btn-mini-connect{
  background:#0f65ff;color:#fff;border:0;
  padding:.4em .7em;border-radius:.5em;cursor:pointer;
}
.tag-linked{
  background:#eef6ff;color:#0f65ff;
  padding:.25em .5em;border-radius:.5em;font-weight:700;
}
.empty-note{color:#777;text-align:center;padding:.75em 0;}

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

<main class="main">
  <h2 style="text-align:center;">Add Patients to your account <br>from Patient Management System</h2>
  <div class="search-card">
      <input type="text" placeholder="Search by ID..." onkeyup="searchPatient(this.value)">
      <div id="results"></div>
      <p id="message" style="margin-top:15px;font-weight:600;"></p>
          

      <!-- 🔹 Connect Button تحت البحث على اليمين -->
      <div style="text-align:right; margin-top:15px;">
        <button id="connectBtn" class="add-btn" style="background:#eef6ff;color:#0f65ff;border:1px solid rgba(15,101,255,.15);box-shadow:none;">Connect</button>
      </div>

      <!-- 🔹 Connect Modal -->
      <div class="modal" id="connectModal">
        <div class="modal-content">
          <h3>Connect to a Patient</h3>
          <input type="text" id="patient_input" placeholder="Type ID or name..." autocomplete="off">
          <div id="connectMsg"></div>
          <div id="searchResults" style="margin-top:10px; max-height:380px; overflow:auto;"></div>
          <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end">
            <button type="button" onclick="closeModal()">Close</button>
          </div>
        </div>
      </div>

  </div>
</main>

<footer  id="contact" class="site-footer">
  <div class="footer-grid">

    <div class="footer-col brand">
      <img src="images/logo.png" alt="Tanafs logo" class="footer-logo" />
      <p class="brand-tag">Breathe well, live well</p>
    </div>

    <!-- Social -->
    <nav class="footer-col social" aria-label="Social media">
      <h3 class="footer-title">Social Media</h3>
      <ul class="social-list">
        <li>
          <a href="#" aria-label="Twitter">
            <img src="images/twitter.png" alt="Twitter" />
          </a>
        </li>
        <li>
          <a href="#" aria-label="Instagram">
            <img src="images/instagram.png" alt="Instagram" />
          </a>
        </li>
      </ul>
      <span class="social-handle">@official_Tanafs</span>
    </nav>

    <!-- Contact -->
    <div class="footer-col contact">
      <h3 class="footer-title">Contact Us</h3>
      <ul class="contact-list">
        <li>
          <a href="#" class="contact-link">
            <img src="images/whatsapp.png" alt="WhatsApp" />
            <span>+123 165 788</span>
          </a>
        </li>
        <li>
          <a href="mailto:Appointly@gmail.com" class="contact-link">
            <img src="images/email.png" alt="Email" />
            <span>Tanafs@gmail.com</span>
          </a>
        </li>
      </ul>
    </div>

  </div>

  <div class="footer-bar">
    <p class="legal">
      <a href="#">Terms &amp; Conditions</a>
      <span class="dot">•</span>
      <a href="#">Privacy Policy</a>
    </p>
    <p class="copy">© 2025 Tanafs Company. All rights reserved.</p>
  </div>
</footer>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById("connectModal");
  const input = document.getElementById("patient_input");
  const resultsBox = document.getElementById("searchResults");
  const msgBox = document.getElementById("connectMsg");
  const btn = document.getElementById("connectBtn");

  // فتح المودال
  btn.addEventListener('click', () => {
    modal.style.display = "flex";
    resultsBox.innerHTML = "";
    input.value = "";
    msg("");
    input.focus();

    // 🟢 إظهار كل المرضى مباشرة عند فتح المودال
    doSearch("");
  });

  // إغلاق المودال
  window.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  function closeModal() {
    modal.style.display = "none";
    msg("");
    resultsBox.innerHTML = "";
  }

  function msg(txt, cls = "info") {
    msgBox.innerHTML = txt ? `<span class="${cls}">${txt}</span>` : '';
  }

  // البحث داخل المودال
  let tmr = null;
  if (input) {
    input.addEventListener('input', () => {
      clearTimeout(tmr);
      tmr = setTimeout(() => doSearch(input.value.trim()), 300);
    });
  }

  // دالة البحث — تجيب الكل إذا q فاضي
  function doSearch(q) {
    msg("⏳ Loading patients...", "info");
    fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "ajax=search_patients&q=" + encodeURIComponent(q)
    })
      .then(r => r.json())
      .then(res => {
        if (res.type === "success") {
          resultsBox.innerHTML = res.html;
          msg("");
        } else {
          resultsBox.innerHTML = "";
          msg("❌ Error while loading patients", "error");
        }
      })
      .catch(() => {
        resultsBox.innerHTML = "";
        msg("❌ Network error", "error");
      });
  }

  // تنفيذ عملية connect
  window.connectTo = function (pid) {
    msg("⏳ Connecting...", "info");
    fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "ajax=connect&PID=" + encodeURIComponent(pid)
    })
      .then(r => r.json())
      .then(res => {
        msg(res.msg, res.type);
        if (res.type === "success" || res.type === "info") {
          const row = document.getElementById("mini-" + pid);
          if (row) {
            const lastTd = row.querySelector("td:last-child");
            if (lastTd) lastTd.innerHTML = '<span class="tag-linked">Linked</span>';
          }
        }
      })
      .catch(() => msg("❌ Connection error", "error"));
  }

  const closeBtn = modal.querySelector('button[type="button"]');
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
});
</script>

</body>
</html>
<?php $conn->close(); ?>