<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


if (empty($_SESSION['user_id'])) {
    if (!empty($_POST['action']) || !empty($_POST['ajax'])) {
        http_response_code(401);
        exit('Unauthorized. Please sign in.');
    }
    header('Location: signin.php');
    exit;
}

$userID = (int)$_SESSION['user_id'];

// --- Database connection ---
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "tanafs";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");



// --- Doctor name ---
$docRes = $conn->prepare("SELECT first_name, last_name FROM healthcareprofessional WHERE userID=?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$_SESSION['doctorName'] = "Dr. " . $docData['first_name'] . " " . $docData['last_name'];
$docRes->close();

// --- Handle AJAX actions ---
if (isset($_POST['ajax']) && !in_array($_POST['ajax'], ['search_hospital', 'import_hospital', 'search_connect', 'connect_existing'])) {
    $action = $_POST['ajax'];
    $response = ["type" => "error", "msg" => "⚠️ Unknown error."];

if ($action === 'search_patients') {
    $q     = trim($_POST['q'] ?? '');
    $scope = trim($_POST['scope'] ?? ''); 
    $like  = '%' . $q . '%';

    $sql = "
        SELECT 
            p.PID, p.first_name, p.last_name, p.gender, p.status, p.phone, p.DOB,
            CASE WHEN pda.userID IS NULL THEN 0 ELSE 1 END AS linked_to_me
        FROM patient p
        LEFT JOIN patient_doctor_assignments pda
            ON pda.PID = p.PID AND pda.userID = ?
        WHERE (? = '' 
               OR p.PID LIKE ? 
               OR p.first_name LIKE ? 
               OR p.last_name LIKE ?)
    ";

    if ($scope === 'connect') {
        $sql .= " AND pda.userID IS NULL ";
    }

    $sql .= " ORDER BY p.PID LIMIT 50 ";

    $st = $conn->prepare($sql);
    $st->bind_param('issss', $userID, $q, $like, $like, $like);
    $st->execute();
    $rs = $st->get_result();

    ob_start();
    if ($rs->num_rows > 0) {
        echo '<table class="mini-table"><thead>
                <tr><th>ID</th><th>First</th><th>Last</th><th>Status</th><th>Action</th></tr>
              </thead><tbody>';
        while ($row = $rs->fetch_assoc()) {
            $pid   = htmlspecialchars($row['PID']);
            $first = htmlspecialchars($row['first_name']);
            $last  = htmlspecialchars($row['last_name']);
            $status= htmlspecialchars($row['status'] ?? '');
            $linked= (int)$row['linked_to_me'] === 1;

            echo '<tr id="mini-'.$pid.'">
                    <td>'.$pid.'</td>
                    <td>'.$first.'</td>
                    <td>'.$last.'</td>
                    <td>'.$status.'</td>
                    <td>';
            if ($linked) {
                echo '<span class="tag-linked">Linked</span>';
            } else {
                echo '<button class="btn-mini-connect" onclick="connectTo(\''.$pid.'\')">Connect</button>';
            }
            echo    '</td>
                  </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div class="empty-note">No matching patients.</div>';
    }
    $html = ob_get_clean();
    echo json_encode(['type'=>'success','html'=>$html]);
    exit;
}


// 🔹 Disconnect 
elseif ($action === 'disconnect') {
    $PID = $_POST['PID'] ?? '';
    $pidParam = ctype_digit($PID) ? (int)$PID : $PID;

    $del = $conn->prepare("DELETE FROM patient_doctor_assignments WHERE PID=? AND userID=?");
    $del->bind_param(ctype_digit($PID) ? "ii" : "si", $pidParam, $userID);
    $del->execute();
    $del->close();

    $response = ["type" => "info", "msg" => "Disconnected successfully!"];
}

elseif ($action === 'delete') {
    $PID = $_POST['PID'] ?? '';
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';

    if (!$confirmed) {
        $response = ["type" => "warn", "msg" => "Deletion not confirmed."];
    } else {
        
        $pidParam = ctype_digit($PID) ? (int)$PID : $PID;
        $pidType  = ctype_digit($PID) ? "i" : "s";

        
        $chk = $conn->prepare("SELECT 1 FROM patient_doctor_assignments WHERE PID=? AND userID=?");
        $chk->bind_param($pidType."i", $pidParam, $userID);
        $chk->execute();
        $hasLink = $chk->get_result()->num_rows > 0;
        $chk->close();

        if (!$hasLink) {
            $response = ["type"=>"error","msg"=>"❌ You are not assigned to this patient."];
        } else {
            
            $conn->begin_transaction();
            try {
               
                $delLink = $conn->prepare("DELETE FROM patient_doctor_assignments WHERE PID=?");
                $delLink->bind_param($pidType, $pidParam);
                $delLink->execute();
                $delLink->close();

               
                $delC = $conn->prepare("DELETE FROM comment WHERE PID=?");
                $delC->bind_param($pidType, $pidParam);
                $delC->execute();
                $delC->close();

                
                $delR = $conn->prepare("DELETE FROM report WHERE PID=?");
                $delR->bind_param($pidType, $pidParam);
                $delR->execute();
                $delR->close();

                
                $delW = $conn->prepare("DELETE FROM waveform_analysis WHERE PID=?");
                $delW->bind_param($pidType, $pidParam);
                $delW->execute();
                $delW->close();


                
                $delP = $conn->prepare("DELETE FROM patient WHERE PID=?");
                $delP->bind_param($pidType, $pidParam);
                $delP->execute();
                $delP->close();

                $conn->commit();
                $response = ["type" => "success", "msg" => "🗑️ Patient deleted from Tanafs successfully."];
            } catch (Throwable $e) {
                $conn->rollback();
                $response = ["type"=>"error","msg"=>"Delete failed: ".$e->getMessage()];
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- Get patients linked to current doctor ---
$sql = "SELECT p.PID, p.first_name, p.last_name, p.gender, p.status, p.phone, p.DOB
        FROM patient p
        INNER JOIN patient_doctor_assignments up ON p.PID = up.PID
        WHERE up.userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

// ======================= AJAX: Search + Import from Hospital =======================
if (isset($_POST['ajax']) && $_POST['ajax'] === 'search_hospital') {
    $PID = trim($_POST['PID'] ?? '');
    if ($PID === '') {
        echo json_encode(['type'=>'error','msg'=>'Missing Hospital ID.']); exit;
    }

    // 1️⃣ Check if exists in Tanafs
    $check = $conn->prepare("SELECT PID FROM patient WHERE PID=? LIMIT 1");
    $check->bind_param("s", $PID);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {

        $chkLink = $conn->prepare("SELECT 1 FROM patient_doctor_assignments WHERE PID=? AND userID=?");
        $chkLink->bind_param("si", $PID, $userID);
        $chkLink->execute();
        $linked = $chkLink->get_result()->num_rows > 0;
        $chkLink->close();

        if ($linked) {
            echo json_encode(['type'=>'linked','msg'=>'This patient is already in your patient list.']);
        } else {
            echo json_encode(['type'=>'exists','msg'=>'This patient already exists in Tanafs. You can connect them instead.']); 
        }
        exit;
    }

    // 2️⃣ If not found in Tanafs, search Hospital JSON
    $jsonPath = "C:/MAMP/htdocs/2025_GP_27/data/patients_record.json";
    if (!is_file($jsonPath)) {
        echo json_encode(['type'=>'error','msg'=>'Hospital record file not found.']); exit;
    }

    $data = json_decode(file_get_contents($jsonPath), true);
    $records = $data['hospital_records'] ?? [];
    $found = null;
    foreach ($records as $r) {
        if ((string)$r['PID'] === $PID) { $found = $r; break; }
    }

    if (!$found) {
        echo json_encode(['type'=>'error','msg'=>'No patient found with this Hospital ID.']); exit;
    }

    echo json_encode(['type'=>'found','data'=>$found]);
    exit;
}


if (isset($_POST['ajax']) && $_POST['ajax'] === 'import_hospital') {
    $PID = trim($_POST['PID'] ?? '');
    if ($PID === '') { echo json_encode(['type'=>'error','msg'=>'Missing Hospital ID.']); exit; }

    $jsonPath = "C:/MAMP/htdocs/2025_GP_27/data/patients_record.json";
    $data = json_decode(file_get_contents($jsonPath), true);
    $records = $data['hospital_records'] ?? [];
    $rec = null;
    foreach ($records as $r) {
        if ((string)$r['PID'] === $PID) { $rec = $r; break; }
    }
    if (!$rec) { echo json_encode(['type'=>'error','msg'=>'Record not found in Hospital data.']); exit; }

    $stmt = $conn->prepare("INSERT INTO patient (PID, first_name, last_name, gender, status, phone, DOB) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss",
        $rec['PID'], $rec['first_name'], $rec['last_name'],
        $rec['gender'], $rec['status'], $rec['phone'], $rec['DOB']
    );
    if ($stmt->execute()) {
        echo json_encode(['type'=>'success','msg'=>'Patient imported successfully into Tanafs.']);
    } else {
        echo json_encode(['type'=>'error','msg'=>'Failed to import patient.']);
    }
    $stmt->close();
    exit;
}

// ======================= AJAX: Connect Patient =======================
if (isset($_POST['ajax']) && $_POST['ajax'] === 'search_connect') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_POST['q'] ?? '');
    if ($q === '') { echo json_encode(['type'=>'error','msg'=>'Please enter a Patient ID or Name.']); exit; }

    $like = '%' . $q . '%';
    $stmt = $conn->prepare("
        SELECT p.PID, p.first_name, p.last_name, 
               CASE WHEN pda.userID IS NULL THEN 0 ELSE 1 END AS linked
        FROM patient p
        LEFT JOIN patient_doctor_assignments pda 
          ON p.PID = pda.PID AND pda.userID = ?
        WHERE p.PID LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
        LIMIT 10
    ");
    $stmt->bind_param("isss", $userID, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) { echo json_encode(['type'=>'error','msg'=>'No matching patients found in Tanafs.']); exit; }

    ob_start();
    echo "<table class='mini-table'><thead><tr><th>ID</th><th>Name</th><th>Action</th></tr></thead><tbody>";
    while ($r = $res->fetch_assoc()) {
        $pid = htmlspecialchars($r['PID']);
        $name = htmlspecialchars($r['first_name'].' '.$r['last_name']);
        if ((int)$r['linked'] === 1) {
            echo "<tr><td>$pid</td><td>$name</td><td><span class='tag-linked'>Linked</span></td></tr>";
        } else {
            echo "<tr><td>$pid</td><td>$name</td>
                  <td><button class='btn-mini-connect' onclick=\"connectNow('$pid')\">Connect</button></td></tr>";
        }
    }
    echo "</tbody></table>";
    $html = ob_get_clean();

    echo json_encode(['type'=>'success','html'=>$html]);
    exit;
}

if (isset($_POST['ajax']) && $_POST['ajax'] === 'connect_existing') {
    header('Content-Type: application/json; charset=utf-8');
    $PID = trim($_POST['PID'] ?? '');
    if ($PID === '') { echo json_encode(['type'=>'error','msg'=>'Missing Patient ID.']); exit; }

    $chk = $conn->prepare("SELECT PID FROM patient WHERE PID=? LIMIT 1");
    $chk->bind_param("s", $PID);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$exists) {
        echo json_encode(['type'=>'error','msg'=>'This patient doesn’t exist in Tanafs. Please import them first.']);
        exit;
    }

    $dup = $conn->prepare("SELECT COUNT(*) c FROM patient_doctor_assignments WHERE PID=? AND userID=?");
    $dup->bind_param("si", $PID, $userID);
    $dup->execute();
    $count = $dup->get_result()->fetch_assoc()['c'] ?? 0;
    $dup->close();

    if ($count > 0) {
        echo json_encode(['type'=>'warn','msg'=>'This patient is already linked to your account.']);
        exit;
    }

    $add = $conn->prepare("INSERT INTO patient_doctor_assignments (PID, userID) VALUES (?, ?)");
    $add->bind_param("si", $PID, $userID);
    if ($add->execute()) {
        echo json_encode(['type'=>'success','msg'=>'Patient connected successfully.']);
    } else {
        echo json_encode(['type'=>'error','msg'=>'Failed to connect patient.']);
    }
    $add->close();
    exit;
}

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

.wrapper { position: relative; width: 100%; min-height: 100vh; }
.nav-link.active::after {
    width: 100%;
}

img.topimg {
  position: absolute;
  top: -5.9%;
  left: 48%;
  transform: translateX(-50%);
  max-width: 90%;
  z-index: 10;
  pointer-events: none;
}

img.logo {
  position: absolute;
  top: 2.9%;
  left: 14%;
  width: clamp(6.25em, 12vw, 11.25em);
  height: auto;
  z-index: 20;
}

.auth-nav {
  position: absolute;
  top: 4.5%;
  right: 16.2%;
  display: flex;
  align-items: center;
  gap: 1.6em;
  z-index: 30;
}

.nav-link {
  color: #0876FA;
  font-weight: 600;
  text-decoration: none;
  font-size: 1em;
  transition: all 0.3s ease;
  position: relative;
}

.nav-link::after {
  content: "";
  position: absolute;
  bottom: -0.25em;
  left: 0;
  width: 0;
  height: 0.125em;
  background: linear-gradient(90deg, #0876FA, #78C1F5);
  transition: width 0.3s ease;
  border-radius: 0.125em;
}

.nav-link:hover::after { width: 100%; }

/* ========== Profile / Buttons ========== */
.profile-btn {
  border: none;
  outline: none;
  background: transparent;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.profile {
  display: flex;
  gap: 0.625em;
  align-items: center;
  padding: 0.375em 0.625em;
}

.avatar-icon { width: 1.875em; height: 1.875em; display: block; border: 0; }

.btn-logout {
  background: linear-gradient(90deg, #0f65ff, #5aa6ff);
  color: #fff;
  padding: 0.5em 0.975em;
  border-radius: 0.75em;
  font-weight: 400;
  border: none;
  cursor: pointer;
  font-size: 0.875em;
}

/* ========== Table Card ========== */
.table-card {
  background: #fff;
  border-radius: 1em;
  padding: 1.25em;
  box-shadow: 0 0.5em 1.25em rgba(0,0,0,0.06);
  width: 90%;
  margin: auto;
}

.table-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.9375em;
  gap: 0; /* للحفاظ على التخطيط */
}

.table-actions input {
  padding: 0.5em 0.75em;
  border-radius: 0.5em;
  border: 0.0625em solid #ccc;
  width: 70%;
}

.table-actions button {
  padding: 0.5em 0.875em;
  border: none;
  border-radius: 0.5em;
  cursor: pointer;
  font-weight: 600;
}

.add-btn { background-color: #0f65ff; color: #fff; }


table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1.25em;
}

th, td {
  padding: 0.75em;
  text-align: center;
  border-bottom: 0.0625em solid #eee;
}

th {
  background-color: #f4f6fc;
  color: #1f46b6;
}

.action-icons span {
  cursor: pointer;
  padding: 0.25em;
  border-radius: 0.375em;
  margin: 0 0.25em;
  color: #0f65ff;
  transition: 0.2s;
}

.action-icons span:hover { background: #eef6ff; }

/* ========== Modal ========== */
.modal {
  display: none;
  position: fixed;
  z-index: 999;
  left: 0; top: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.4);
  justify-content: center;
  align-items: center;
}

.modal-content {
  background: #fff;
  padding: 1.25em;
  border-radius: 0.875em;
  width: 22.5em;
  text-align: center;
  box-shadow: 0 0.25em 1.25em rgba(0,0,0,0.15);
}

.modal-content input {
  width: 80%;
  padding: 0.5em;
  border: 0.0625em solid #ccc;
  border-radius: 0.5em;
  margin: 0.5em 0;
}
.modal-content{
  overflow: hidden;              
}

.modal-content input{
  width: 100%;                   
  max-width: 100%;
  box-sizing: border-box;     
  display: block;
  margin: 0.75em 0;              
}
.modal-content button {
  margin-top: 0.625em;
  padding: 0.5em 1em;
  border: none;
  border-radius: 0.5em;
  background: #0f65ff;
  color: #fff;
  cursor: pointer;
  font-weight: 600;
}

#connectMsg { margin-top: 0.625em; font-weight: 600; }

.success { color: #0a7e1e; }
.error   { color: #c00; }
.warn    { color: #b97900; }
.info    { color: #0b65d9; }

/* ========== Footer ========== */
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

:root{
  --bg:#f2f6fb; --card:#fff; --accent:#0f65ff; --muted:#9aa6c0; --soft-blue:#eef6ff;
  --panel-shadow: 0 0.625em 1.875em rgba(17,24,39,.06);
  --radius: 0.875em;
}

body{
  font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
  background:var(--bg);
  color:#15314b;
}

.auth-nav .search{
  display:flex;
  align-items:center;
  gap: .55rem;
  padding: .45rem .7rem;
  background: linear-gradient(180deg,#ffffff,#f8fbff);
  border: 0.0625em solid rgba(15,101,255,.10);
  border-radius: 0.75em;
  box-shadow: 0 0.375em 1em rgba(17,24,39,.05);
}

.auth-nav .search input{
  border:0; outline:none; background:transparent;
  min-width: 11.25em; /* 180px */
  font-size:.95rem; color:#15314b;
}

.auth-nav .search input::placeholder{ color:#97a6be; }

main h2{
  color:#0B83FE; font-weight:700;
  letter-spacing: 0.0125em; 
  margin-bottom: 1.125em;   
}

.table-card{
  width: min(96vw, 68.75em);         
  background: linear-gradient(180deg,#ffffff,#fdfefe);
  border-radius: 1em;
  border: 0.0625em solid rgba(15,101,255,.08);
  box-shadow: var(--panel-shadow);
  padding: 1.375em 1.375em 1.625em;   
}

.table-actions { gap: 0.875em; }      

.table-actions input{
  width: clamp(16.25em, 50vw, 32.5em); 
  padding: 0.75em 0.875em;             
  border-radius: 0.75em;               
  border: 0.0625em solid rgba(0,0,0,.12);
  background:#fff; outline:none;
  transition: border-color .2s, box-shadow .2s;
}

.table-actions input:focus{
  border-color: rgba(15,101,255,.35);
  box-shadow: 0 0 0 0.25em rgba(15,101,255,.08); 
}

.table-actions button{
  font-weight:600; border-radius:0.75em; 
  padding: 0.625em 0.875em;              
}

.add-btn{
  background: linear-gradient(90deg,#0f65ff,#5aa6ff);
  color:#fff; border:0;
  box-shadow: 0 0.5em 1em rgba(15,101,255,.18); 
  transition: filter .15s, transform .1s;
}
.add-btn:hover{ filter:brightness(.95); transform: translateY(-0.0625em); }

.connect-btn{
  background: var(--soft-blue);
  color:#0f65ff;
  border: 0.0625em solid rgba(15,101,255,.15);
}

.table-card table{ width:100%; border-collapse: collapse; margin-top: 1em; }

.table-card thead th{
  background:#f4f8ff; color:#1f46b6; font-weight:700;
  padding: 0.875em 0.625em; 
  border-bottom: 0.0625em solid #e7eef9;
}

.table-card tbody td{
  padding: 0.75em 0.625em; 
  border-bottom: 0.0625em solid #eef2f7;
  color:#16314b;
}

.table-card tbody tr:hover{ background:#fbfdff; }

.action-icons span{
  color:#0f65ff;
  border-radius: 0.625em; 
  padding: 0.375em;       
  transition: background .15s, transform .1s;
}
.action-icons span:hover{ background:#eef6ff; transform: translateY(-0.0625em); }


.modal{
  display:none; position:fixed; inset:0; z-index:999;
  background: rgba(19,32,56,.35);
  backdrop-filter: blur(0.125em); 
}

.modal-content{
  background:#fff; width: min(92vw, 26.25em); 
  padding: 1.375em 1.25em;                     
  border-radius: 1em;                          
  box-shadow: 0 1.25em 2.5em rgba(17,24,39,.18);
  border: 0.0625em solid rgba(15,101,255,.08);
}

.modal-content h3{ margin: 0 0 0.5em; color:#0B83FE; }

.modal-content input{
  width:100%; padding: 0.75em 0.875em;         
  border-radius: 0.75em;                       
  border: 0.0625em solid rgba(0,0,0,.12);
  outline:none; transition: border-color .2s, box-shadow .2s;
}

.modal-content input:focus{
  border-color: rgba(15,101,255,.35);
  box-shadow: 0 0 0 0.25em rgba(15,101,255,.08); 
}

.modal-content button{
  border-radius: 0.75em; 
  padding: 0.625em 0.875em; 
  font-weight:700; border:0;
}

.modal-content #connectNow{ background: linear-gradient(90deg,#0f65ff,#5aa6ff); color:#fff; }

.modal-content button[type="button"]{
  background:#eef6ff; color:#0f65ff; margin-left: 0.375em; 
}

#connectMsg, .message{ margin-top: 0.625em; font-weight: 600; }
.success{ color:#0a7e1e; } .error{ color:#c00; } .warn{ color:#b97900; } .info{ color:#0b65d9; }

/* ========== Media Queries ========== */
@media (max-width: 62.5em){ 
  .table-card{ width: min(96vw, 56.25em); padding: 1.25em; } 
  .table-actions{ flex-wrap: wrap; row-gap: 0.625em; }       
  .table-actions input{ width: 100%; }
}

@media (max-width: 45em){ 
  .table-card{ padding: 1.125em; } 
  .table-actions{ flex-direction: column; align-items: stretch; }
  .table-actions > div{ display:flex; gap: 0.625em; } 
  .table-actions button{ flex: 1; }
  .table-card table{ font-size: .95rem; }
}

.mini-table{ width:100%; border-collapse:collapse; }
.mini-table th, .mini-table td{ padding:.5em; border-bottom:1px solid #eee; text-align:center; }
.btn-mini-connect{ background:#0f65ff; color:#fff; border:0; padding:.4em .7em; border-radius:.5em; cursor:pointer; }
.tag-linked{ background:#eef6ff; color:#0f65ff; padding:.25em .5em; border-radius:.5em; font-weight:700; }
.empty-note{ color:#777; text-align:center; padding:.75em 0; }
/* إبراز الصف المطابق */
#patientsTable tbody tr.hit {
  outline: 2px solid #0f65ff;
  background: #eef6ff;
}
/* تخفيف بقية الصفوف (اختياري) */
#patientsTable tbody tr.dim {
  opacity: .45;
}
.patient-buttons {
  display: flex;
  gap: 10px;
}
/* 🏥 Add from Hospital Button */
.btn-import {
  background-color: #0f65ff;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s ease;
}
.btn-import:hover {
  background-color: #084dcc;
}

/* 🔗 Connect Button */
.btn-connect {
  background-color: #e8f0fe;
  color: #0f65ff;
  border: 1px solid #c9dcff;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s ease;
}
.btn-connect:hover {
  background-color: #dbe7ff;
}

/* 🔗 Connect Modal specific design */
.connect-modal {
  max-height: 480px;                /* يثبت الحجم الكلي للمودال */
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.connect-result {
  flex: 1;
  overflow-y: auto;                 /* سكرول داخلي */
  margin-top: 10px;
  padding-right: 6px;
  max-height: 320px;                /* منطقة النتائج */
}

.connect-result::-webkit-scrollbar {
  width: 6px;
}
.connect-result::-webkit-scrollbar-thumb {
  background: rgba(15,101,255,0.3);
  border-radius: 3px;
}

/* تعديل الجدول داخل النتائج */
.connect-result table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95em;
}
.connect-result th, .connect-result td {
  padding: 0.55em;
  text-align: center;
  border-bottom: 1px solid #eee;
}
.connect-result th {
  background: #f4f8ff;
  color: #1f46b6;
  font-weight: 700;
  position: sticky;
  top: 0; /* يبقى رأس الجدول ثابت */
  z-index: 1;
}

.btn-mini-connect {
  background: #0f65ff;
  color: #fff;
  border: none;
  padding: 5px 10px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s;
}
.btn-mini-connect:hover { background: #084dcc; }
.tag-linked {
  background: #eef6ff;
  color: #0f65ff;
  padding: 4px 8px;
  border-radius: 8px;
  font-weight: 700;
}
tr.no-result-row td {
  background: #ffe6e6;     
  color: #b30000;          
  border: 1px solid #fc888873;
  border-radius: 10px;
  padding: 12px;
  font-weight: 600;
  text-align: center;
}

/* 🔹 شريط التنبيه الرمادي */
.no-result-bar {
  background: #f1f3f6;
  border: 1px solid #d0d6de;
  border-radius: 10px;
  color: #1a1a1a;
  font-weight: 600;
  padding: 16px;
  margin: 10px auto;
  text-align: center;
  font-size: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  width: 95%;
}.ipad-header {
    display: none;
}

@media (max-width: 1366px) {

    .auth-nav,
    .topimg,
    .logo {
        display: none !important;
    }

    .ipad-header {
        display: block;
        width: 100%;
        background: #ffffff;
        padding: 14px 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: sticky;
        top: 0;
        z-index: 9999;
    }

    .ipad-inner {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ipad-logo img {
        height: 55px;
        width: auto;
    }

    .ipad-nav {
        display: flex;
        align-items: center;
        gap: 1.2em;
    }

    .ipad-nav .nav-link {
        color: #0B83FE;
        text-decoration: none;
        font-weight: 600;
    }

    .ipad-nav .profile-btn img {
        width: 32px;
        height: 32px;
    }

    .ipad-logout {
        background: linear-gradient(90deg, #0f65ff, #5aa6ff);
        color: white;
        padding: 0.4em 0.9em;
        border-radius: 0.7em;
        border: none;
        cursor: pointer;
        font-size: 0.9em;
        font-weight: 500;
    }
}
@media (max-width: 1366px) {

    main {
        margin-top: 120px !important;   
        padding: 0 24px;
        text-align: center;
    }

    .table-card {
        width: 100%;
        max-width: 900px;
        margin: 0 auto;
    }

    .table-actions {
        flex-direction: column;       
        align-items: stretch;
        gap: 0.75rem;
    }

    .table-actions input {
        width: 100% !important;       
    }

    .patient-buttons {
        justify-content: flex-start;  
        gap: 0.75rem;
    }

    .btn-import,
    .btn-connect {
        flex: 1 1 auto;               
        text-align: center;
    }

    #patientsTable th,
    #patientsTable td {
        padding: 10px 6px;
        font-size: 0.92rem;
    }
}
@media (max-width: 1024px) {

    .ipad-header {
        padding: 10px 16px;           
    }

    .ipad-inner {
        gap: 0.75rem;
    }

    .ipad-logo img {
        height: 48px;
    }

    .ipad-nav {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.6rem;                  
        flex-wrap: wrap;             
    }

    .ipad-nav .nav-link {
        font-size: 0.85rem;           
    }

    .ipad-logout {
        padding: 0.35em 0.75em;
        font-size: 0.8rem;
    }
}


</style>
</head>

<body>
  <header class="ipad-header">
    <div class="ipad-inner">

        <a href="dashboard.php" class="ipad-logo">
            <img src="Images/Logo.png" alt="Tanafs Logo">
        </a>

        <nav class="ipad-nav">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="patients.php" class="nav-link">Patients</a>
            <a href="history2.php" class="nav-link">History</a>

            <a href="profile.php" class="profile-btn">
                <img src="images/profile.png" alt="Profile">
            </a>

            <form action="Logout.php" method="post">
                <button type="submit" class="ipad-logout">Logout</button>
            </form>
        </nav>

    </div>
</header>
<div class="wrapper">
  <!-- Header -->
  <img class="topimg" src="Images/Group 8.png" alt="img">
  <img class="logo" src="Images/Logo.png" alt="Tanafs Logo">

   <nav class="auth-nav" aria-label="User navigation">
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <a class="nav-link active" href="patients.php">Patients</a>
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

  <!-- Page Content -->
  <main style="margin-top:130px; text-align:center;">
    <h2>Patients List</h2>

    <div class="table-card">
     <div class="table-actions">
  <input type="text" id="search" placeholder="Search your patients...">
  
  <div class="patient-buttons">
      <button class="btn-import" id="openImportModal">🏥 Add from Hospital</button>
    <button class="btn-connect" id="openConnectModal">🔗 Connect Patient</button>
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
function performAction(action, pid){
  let text = '';
  if (action === 'disconnect') {
    text = 'Are you sure you want to disconnect this patient from your list?\nThis will NOT delete any data.';
  } else if (action === 'delete') {
    text = 'WARNING: This will permanently delete the patient from Tanafs, including comments, reports, and analyses.\nProceed?';
  } else {
    return;
  }

  if (!confirm(text)) return;

  const body = new URLSearchParams();
  body.set('ajax', action);
  body.set('PID', pid);
  if (action === 'delete') body.set('confirm', '1'); // تأكيد للحذف

  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  })
  .then(r => r.json())
  .then(res => {
    // رسالة أعلى الجدول (عندك <p id="message">)
    const m = document.getElementById('message');
    if (m) {
      m.className = 'message ' + (res.type || 'info');
      m.textContent = res.msg || '';
    }

    if (action === 'disconnect' && (res.type === 'info' || res.type === 'success')) {
      const tr = document.getElementById('row-' + pid);
      if (tr) tr.remove();
      return;
    }

    if (action === 'delete' && res.type === 'success') {
      const tr = document.getElementById('row-' + pid);
      if (tr) tr.remove();
    }
  })
  .catch(() => {
    const m = document.getElementById('message');
    if (m) {
      m.className = 'message error';
      m.textContent = 'Request failed.';
    }
  });
}
</script>

<script>
// ================== Patient table quick search by PID / Name / Phone ==================
(function(){
  const input  = document.getElementById('search');
  const table  = document.getElementById('patientsTable');
  const tableCard = document.querySelector('.table-card');
  if (!input || !table) return;

  const rows = Array.from(table.querySelectorAll('tbody tr'));
  const tbody = table.querySelector('tbody');

  // 🔹 إظهار شريط رمادي واضح بدل الجدول
  function showNoResultBar(msg) {
    hideNoResultBar(); // نحذف أي بار قديم
    if (table) table.style.display = 'none';

    const bar = document.createElement('div');
    bar.className = 'no-result-bar';
    bar.textContent = msg || 'No results found.';
    tableCard.insertAdjacentElement('afterbegin', bar);
  }

  // 🔹 إخفاء الشريط الرمادي وإرجاع الجدول
  function hideNoResultBar() {
    const bar = document.querySelector('.no-result-bar');
    if (bar) bar.remove();
    if (table) table.style.display = '';
  }

  // 🔹 مسح التنسيقات السابقة
  function clearMarks(){
    hideNoResultBar();
    rows.forEach(r => { r.classList.remove('hit','dim'); });
  }

  // 🔹 استخراج النصوص من الصف
  function getRowText(tr){
    const cells = tr.querySelectorAll('td');
    return Array.from(cells)
      .slice(0, 6)
      .map(td => (td.textContent || '').trim().toLowerCase());
  }

  // 🔹 تحديد أفضل تطابق
  function bestMatch(q){
    const qraw = q.trim().toLowerCase();
    if (!qraw) return null;

    let exact = rows.find(tr => getRowText(tr).some(v => v === qraw));
    if (exact) return exact;

    let starts = rows.find(tr => getRowText(tr).some(v => v.startsWith(qraw)));
    if (starts) return starts;

    let contains = rows.find(tr => getRowText(tr).some(v => v.includes(qraw)));
    if (contains) return contains;

    return null;
  }

  // 🔹 تمييز الصف
  function focusRow(tr){
    clearMarks();
    tr.classList.add('hit');
    rows.forEach(r => { if (r !== tr) r.classList.add('dim'); });
    tr.scrollIntoView({ behavior:'smooth', block:'center' });
  }

  // 🔹 وظيفة البحث التلقائي
  let tmr = null;
  input.addEventListener('input', () => {
    clearTimeout(tmr);
    const q = input.value.trim();
    if (!q) {
      clearMarks();
      return;
    }

    tmr = setTimeout(() => {
      const match = bestMatch(q);
      if (match) {
        focusRow(match);
      } else {
        clearMarks();
        checkPatientInSystem(q).then(exists => {
          if (exists) {
            showNoResultBar('No patient found in your list. You can connect with this patient.');
          } else {
            showNoResultBar('No patient found in the system.');
          }
        });
      }
    }, 300);
  });

  // 🔹 البحث عند الضغط Enter
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter'){
      const q = input.value.trim();
      const match = bestMatch(q);
      if (match){
        const pid = (match.querySelector('td')?.textContent || '').trim();
        if (pid) {
          window.location.href = `patient.html?pid=${encodeURIComponent(pid)}`;
        }
      } else {
        clearMarks();
        checkPatientInSystem(q).then(exists => {
          if (exists) {
            showNoResultBar('No patient found in your list. You can connect with this patient.');
          } else {
            showNoResultBar('No patient found in the system.');
          }
        });
      }
    }
  });

  // ✅ 🔍 التحقق من وجود المريض داخل TANAFS
  async function checkPatientInSystem(query) {
    try {
      const res = await fetch("", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "ajax=search_connect&q=" + encodeURIComponent(query)
      });
      const data = await res.json();
      if (data.type === "success" && data.html.includes("<table")) {
        return true; // ✅ موجود في النظام
      } else {
        return false; // ❌ غير موجود
      }
    } catch {
      return false;
    }
  }

})(); // ← ختام السكربت
</script>



<script>
// === Row click → go to patient.html?pid=... ===
const tbody = document.querySelector('#patientsTable tbody');
if (tbody) {
  tbody.addEventListener('click', (e) => {
    // لو الضغط كان على أيقونات الإجراءات لا نذهب
    if (e.target.closest('.action-icons')) return;

    const tr = e.target.closest('tr');
    if (!tr) return;

    
    let pid = '';
    if (tr.id && tr.id.startsWith('row-')) {
      pid = tr.id.slice(4);
    } else {
      const firstCell = tr.querySelector('td');
      pid = firstCell ? firstCell.textContent.trim() : '';
    }

    if (pid) {
      window.location.href = `patient.html?pid=${encodeURIComponent(pid)}`;
    }
  });
}


</script>
<script>
document.getElementById("openConnectModal").addEventListener("click", function() {
  const modal = document.getElementById("connectModal");
  if (modal) modal.style.display = "flex";
});

document.getElementById("openImportModal").addEventListener("click", function() {
  const modal = document.getElementById("importModal");
  if (modal) modal.style.display = "flex";
});
</script>
<!-- ======================== 🏥 Add from Hospital Modal ======================== -->
<div class="modal" id="importModal">
  <div class="modal-content">
    <h3>🏥 Import Patient from Hospital</h3>
    <p style="margin-bottom:10px;color:#666;">Enter the patient's ID to import the patient record from the PMS into TANAFS.
</p>

    <!-- Step 1: Search -->
    <div id="importStep1">
      <input type="text" id="hospitalID" placeholder="Search by Hospital ID..." autocomplete="off">
      <button id="btnSearchHospital" style="margin-top:10px;background:#0f65ff;color:white;">Search</button>
    </div>

    <!-- Step 2: Show Result -->
    <div id="importResult" style="display:none;margin-top:15px;text-align:left;"></div>

    <!-- Step 3: Action Buttons -->
    <div style="margin-top:15px;text-align:right;">
      <button type="button" id="closeImport" style="background:#eef6ff;color:#0f65ff;">Close</button>
    </div>
  </div>
</div>

<script>
// ==================== 🧠 Add from Hospital Logic ====================
document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("importModal");
  const openBtn = document.getElementById("openImportModal");
  const closeBtn = document.getElementById("closeImport");
  const searchBtn = document.getElementById("btnSearchHospital");
  const hospitalInput = document.getElementById("hospitalID");
  const resultDiv = document.getElementById("importResult");

  // Open modal
  openBtn.addEventListener("click", () => {
    modal.style.display = "flex";
    hospitalInput.value = "";
    resultDiv.innerHTML = "";
    resultDiv.style.display = "none";
    hospitalInput.focus();
  });

  // Close modal
  closeBtn.addEventListener("click", () => {
    modal.style.display = "none";
  });
  window.addEventListener("click", e => { if (e.target === modal) modal.style.display = "none"; });

  // Step 1: Search in Tanafs and Hospital JSON
  searchBtn.addEventListener("click", () => {
    const pid = hospitalInput.value.trim();
    if (pid === "") {
      resultDiv.innerHTML = "<p style='color:#c00;'>❌ Please enter a Hospital ID.</p>";
      resultDiv.style.display = "block";
      return;
    }

    resultDiv.innerHTML = "<p style='color:#0b65d9;'>⏳ Searching...</p>";
    resultDiv.style.display = "block";

    fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "ajax=search_hospital&PID=" + encodeURIComponent(pid)
    })
      .then(res => res.json())
      .then(data => {
        if (data.type === "exists") {
          resultDiv.innerHTML = `<p style='color:#b97900;'>⚠️ ${data.msg}</p>`;
        } else if (data.type === "found") {
          resultDiv.innerHTML = `
            <p><strong>Patient found:</strong></p>
            <ul style='line-height:1.7em;'>
              <li><b>ID:</b> ${data.data.PID}</li>
              <li><b>Name:</b> ${data.data.first_name} ${data.data.last_name}</li>
              <li><b>Gender:</b> ${data.data.gender}</li>
              <li><b>DOB:</b> ${data.data.DOB}</li>
            </ul>
            <button id="confirmImport" style="background:#0f65ff;color:white;padding:8px 14px;border-radius:8px;border:0;">Import Patient</button>
          `;

          // Step 2: Confirm Import
          document.getElementById("confirmImport").addEventListener("click", () => {
            resultDiv.innerHTML = "<p style='color:#0b65d9;'>⏳ Importing...</p>";
            fetch("patients.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "ajax=import_hospital&PID=" + encodeURIComponent(data.data.PID)
            })
              .then(r => r.json())
              .then(res => {
                if (res.type === "success") {
                  resultDiv.innerHTML = `<p style='color:#0a7e1e;'>✅ ${res.msg}</p>`;
                } else {
                  resultDiv.innerHTML = `<p style='color:#c00;'>❌ ${res.msg}</p>`;
                }
              });
          });
        } else {
          resultDiv.innerHTML = `<p style='color:#c00;'>❌ ${data.msg}</p>`;
        }
      })
      .catch(() => {
        resultDiv.innerHTML = "<p style='color:#c00;'>❌ Network error while searching.</p>";
      });
  });
});
</script>
<!-- ======================== 🔗 Connect Patient Modal ======================== -->
<div class="modal" id="connectModal">
  <div class="modal-content connect-modal">
    <h3>🔗 Connect to a Patient</h3>
    <p style="margin-bottom:10px;color:#666;">
      Start typing the patient ID or name to find and connect them instantly.
    </p>

    <input type="text" id="connectInput" placeholder="Search by ID or Name..." autocomplete="off">

    <div id="connectResult" class="connect-result"></div>

    <div style="margin-top:15px;text-align:right;">
      <button type="button" id="closeConnect" style="background:#eef6ff;color:#0f65ff;">Close</button>
    </div>
  </div>
</div>


<script>
// ==================== 🔗 Connect Patient Logic (Live Search) ====================
document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("connectModal");
  const openBtn = document.getElementById("openConnectModal");
  const closeBtn = document.getElementById("closeConnect");
  const input = document.getElementById("connectInput");
  const resultDiv = document.getElementById("connectResult");
  let typingTimer;

  // فتح المودال
  openBtn.addEventListener("click", () => {
    modal.style.display = "flex";
    input.value = "";
    resultDiv.innerHTML = "";
    resultDiv.style.display = "none";
    input.focus();
  });

  // إغلاق المودال
  closeBtn.addEventListener("click", () => modal.style.display = "none");
  window.addEventListener("click", e => { if (e.target === modal) modal.style.display = "none"; });

  // 🔍 البحث الحي أثناء الكتابة
  input.addEventListener("input", () => {
    clearTimeout(typingTimer);
    const query = input.value.trim();
    if (query.length < 1) {
      resultDiv.style.display = "none";
      resultDiv.innerHTML = "";
      return;
    }

    typingTimer = setTimeout(() => {
      resultDiv.innerHTML = "<p style='color:#0b65d9;'>⏳ Searching...</p>";
      resultDiv.style.display = "block";

      fetch("", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "ajax=search_connect&q=" + encodeURIComponent(query)
      })
        .then(res => res.json())
        .then(data => {
          if (data.type === "success") {
            resultDiv.innerHTML = data.html;
          } else {
            resultDiv.innerHTML = `<p style='color:#c00;'>❌ ${data.msg}</p>`;
          }
        })
        .catch(() => {
          resultDiv.innerHTML = "<p style='color:#c00;'>❌ Network error while searching.</p>";
        });
    }, 300); // ⏱ تأخير بسيط بعد آخر حرف
  });

  
  window.connectNow = function(pid) {
    resultDiv.innerHTML = "<p style='color:#0b65d9;'>⏳ Connecting...</p>";
    fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "ajax=connect_existing&PID=" + encodeURIComponent(pid)
    })
      .then(res => res.json())
      .then(data => {
        if (data.type === "success") {
          resultDiv.innerHTML = `<p style='color:#0a7e1e;'>✅ ${data.msg}</p>`;
        } else {
          resultDiv.innerHTML = `<p style='color:#b97900;'>⚠️ ${data.msg}</p>`;
        }
      })
      .catch(() => {
        resultDiv.innerHTML = "<p style='color:#c00;'>❌ Network error while connecting.</p>";
      });
  };
});

</script>
</body>
</html>
<?php $conn->close(); ?>