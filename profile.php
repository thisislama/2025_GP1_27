<?php
session_start();

// Simulate logged-in user
$_SESSION['userID'] = 1;
$userID = $_SESSION['userID'];

// Database connection
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "tanafs"; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// Fetch user data
$sql = "SELECT first_name, last_name, email, role, phone, DOB FROM user WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// Save edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $phone      = trim($_POST['phone']);
    $dob        = trim($_POST['dob']);

    $update = $conn->prepare("UPDATE user SET first_name=?, last_name=?, phone=?, DOB=? WHERE userID=?");
    $update->bind_param("ssssi", $first_name, $last_name, $phone, $dob, $userID);

    if ($update->execute()) {
        $success = "Changes saved successfully.";
        $userData['first_name'] = $first_name;
        $userData['last_name']  = $last_name;
        $userData['phone']      = $phone;
        $userData['DOB']        = $dob;
    } else {
        $error = "❌ Error while saving changes: " . $conn->error;
    }

    $update->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile</title>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --bg: #f2f6fb;
    --card: #ffffff;
    --accent: #0f65ff;
    --muted: #9aa6c0;
    --radius: 14px;
    --shadow: 0 10px 30px rgba(17, 24, 39, 0.06);
}
body {
    font-family: "Inter", sans-serif;
    background: var(--bg);
    color: #15314b;
    margin: 0;
    display: flex;
}

/* Sidebar */
.sidebar {
    width: 88px;
    height: 100vh;
    background: linear-gradient(180deg, #fbfdff, #f3f7ff);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px 12px;
    gap: 24px;
    position: fixed;
}
.sidebar-item {
    width: 60px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    cursor: pointer;
    transition: all .18s ease;
}
.sidebar-item:hover {
    transform: translateX(4px);
    background: rgba(15, 101, 255, 0.08);
}
.sidebar-item.active {
    background: linear-gradient(180deg, rgba(15,101,255,0.08), rgba(15,101,255,0.03));
    border-radius: 20px;
    width: 72px;
    height: 56px;
}
.sidebar-logout {
    margin-top: auto;
    margin-bottom: 40px;
}
.btn-logout {
    background: linear-gradient(90deg, #0f65ff, #5aa6ff);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(15,101,255,0.14);
}

/* Main Area */
.main {
    margin-left: 88px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
header.topbar {
    height: 84px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 0 36px;
    background: linear-gradient(180deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    border-bottom: 1px solid rgba(15,21,40,0.04);
}
.logo-top img { width: 220px; }

/* Page */
.page {
    padding: 40px 36px 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.page h2 {
    color: #1f46b6;
    font-weight: 700;
    margin-bottom: 20px;
}

/* Profile Card */
.profile-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 30px;
    width: 400px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.profile-form {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.profile-form label {
    font-weight: 600;
    font-size: 14px;
    color: #274b7a;
}
.profile-form input[type="text"],
.profile-form input[type="email"],
.profile-form input[type="date"] {
    padding: 10px;
    border-radius: 10px;
    border: 1px solid rgba(15,21,40,0.1);
    background: #f7f9fc;
    font-size: 14px;
    width: 100%;
    color: #15314b;
}
.profile-form input:disabled {
    background: #e5e8ef;
}
.buttons {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    width: 100%;
    gap: 10px;
}
.buttons button {
    flex: 1;
    padding: 12px 0;
    border-radius: 10px;
    border: none;
    background: var(--accent);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}
.buttons button:hover {
    background: #0d50cc;
}

/* Footer */
footer {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 36px;
    background:#ffffff;
    border-top: 1px solid rgba(15,21,40,0.04);
    font-size:14px;
    color:#2f4c6f;
}
footer img { width: 200px; }
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-item active" onclick="location.href='dashboard.html'">
        <span class="material-symbols-outlined">space_dashboard</span>
    </div>
    <div class="sidebar-item" onclick="location.href='patients.php'">
        <span class="material-symbols-outlined">group</span>
    </div>
    <div class="sidebar-item" style="opacity:0.4;cursor:not-allowed;">
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

<!-- Main -->
<main class="main">
<header class="topbar">
    <div class="logo-top"><img src="images/logon2.png" alt="Logo"></div>
</header>

<section class="page">
    <h2>Profile</h2>
    <div class="profile-card">
        <form class="profile-form" method="POST">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($userData['first_name']); ?>" disabled>

            <label>Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($userData['last_name']); ?>" disabled>

            <label>Email</label>
            <input type="email" value="<?= htmlspecialchars($userData['email']); ?>" disabled>

            <label>Role</label>
            <input type="text" value="<?= htmlspecialchars($userData['role']); ?>" disabled>

            <label>Phone</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($userData['phone']); ?>" disabled>

            <label>Date of Birth</label>
            <input type="date" name="dob" value="<?= htmlspecialchars($userData['DOB']); ?>" disabled>

            <div class="buttons">
                <button type="button" id="editBtn">Edit</button>
                <button type="submit" name="save">Save</button>
                <button type="button" onclick="window.location.href='dashboard.html'">Back</button>
            </div>
        </form>

        <?php if (!empty($success)): ?>
            <p style="color:green; font-weight:bold;"><?= $success ?></p>
        <?php elseif (!empty($error)): ?>
            <p style="color:red; font-weight:bold;"><?= $error ?></p>
        <?php endif; ?>
    </div>
</section>

<footer>
    <div>Resources | Support | Developers</div>
    <div><img src="images/logon2.png" alt="Logo"></div>
</footer>
</main>

<script>
const editBtn = document.getElementById('editBtn');
const inputs = document.querySelectorAll('.profile-form input:not([type=email]):not([value*=Role])');
editBtn.addEventListener('click', () => {
    inputs.forEach(i => i.disabled = false);
});
</script>
</body>
</html>
