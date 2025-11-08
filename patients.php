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

// --- Get patients linked to current doctor ---
$sql = "SELECT p.PID, p.first_name, p.last_name, p.gender, p.status, p.phone, p.DOB
        FROM patient p
        INNER JOIN patient_doctor_assignments up ON p.PID = up.PID
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

.wrapper { position: relative; width: 100%; min-height: 100vh; }

img.topimg {
  position: absolute;
  top: -3.6%;
  left: 48%;
  transform: translateX(-50%);
  max-width: 90%;
  z-index: 10;
  pointer-events: none;
}

img.logo {
  position: absolute;
  top: 4.9%;
  left: 14%;
  width: clamp(6.25em, 12vw, 11.25em);
  height: auto;
  z-index: 20;
}

.auth-nav {
  position: absolute;
  top: 6.5%;
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

.success { color: #0a7e1e; }
.error   { color: #c00; }
.info    { color: #0b65d9; }

.site-footer {
  background: #F6F6F6;
  color: #0b1b2b;
  font-family: 'Montserrat', sans-serif;
  margin-top: 6em;
}
</style>
</head>

<body>
<div class="wrapper">
  <img class="topimg" src="Images/Group 8.png" alt="img">
  <img class="logo" src="Images/Logo.png" alt="Tanafs Logo">

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

  <main style="margin-top:130px; text-align:center;">
    <h2 style="color:#1f45b5; font-size:1.8em; margin-bottom:25px;text-align: left; margin-left: 188px;">Patients List</h2>

    <div class="table-card">
      <div class="table-actions">
        <input type="text" id="search" placeholder="Search Patient ..">
        <button class="add-btn" onclick="window.location.href='addPatient.php'">Add</button>
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

  <footer id="contact" class="site-footer">
    <div class="footer-bar">
      <p class="copy">© 2025 Tanafs Company. All rights reserved.</p>
    </div>
  </footer>
</div>

<script>
// 🔍 Search by ID, First, Last, or Phone
document.getElementById('search').addEventListener('input', function(){
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('#patientsTable tbody tr');
  rows.forEach(row => {
    const cols = row.querySelectorAll('td');
    const pid = cols[0].textContent.toLowerCase();
    const first = cols[1].textContent.toLowerCase();
    const last = cols[2].textContent.toLowerCase();
    const phone = cols[5].textContent.toLowerCase();
    if (pid.includes(filter) || first.includes(filter) || last.includes(filter) || phone.includes(filter)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
});

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
  if (action === 'delete') body.set('confirm', '1');

  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  })
  .then(r => r.json())
  .then(res => {
    const m = document.getElementById('message');
    if (m) {
      m.className = 'message ' + (res.type || 'info');
      m.textContent = res.msg || '';
    }

    if ((action === 'disconnect' || action === 'delete') && res.type === 'success') {
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
</body>
</html>
<?php $conn->close(); ?>
