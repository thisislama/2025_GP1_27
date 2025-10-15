<?php
session_start();

// افتراض أن userID محفوظ في السيشن بعد تسجيل الدخول
 $_SESSION['userID'] = 1;
/*if (!isset($_SESSION['userID'])) {
   die("User not logged in.");
}*/



$userID = $_SESSION['userID'];

// Database connection
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "tanafs"; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Fetch user data
$sql = "SELECT first_name, last_name, email, role, phone, DOB FROM users WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// When clicking Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $phone      = trim($_POST['phone']);
    $dob        = trim($_POST['dob']);

    $update = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, DOB=? WHERE userID=?");
    $update->bind_param("ssssi", $first_name, $last_name, $phone, $dob, $userID);

    if ($update->execute()) {
        $success = "✅ Changes saved successfully.";
        // Update local data
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
<title>Profile - <?php echo htmlspecialchars($userData['first_name'] . " " . $userData['last_name']); ?></title>

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
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 88px;
    height: 100vh;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px 12px;
}
.main {
    margin-left: 88px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
header.topbar {
    width: 100%;
    height: 84px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px 36px;
    background: linear-gradient(180deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    border-bottom: 1px solid rgba(15,21,40,0.04);
    font-size: 20px;
    font-weight: 700;
    color: var(--accent);
}
.page {
    padding: 26px 36px 60px;
    display: flex;
    justify-content: center;
}
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
@media (max-width: 480px) {
    .profile-card { width: 90%; padding: 20px; }
}
</style>
</head>
<body>

<aside class="sidebar"></aside>

<main class="main">
<header class="topbar">
    <?php echo htmlspecialchars($userData['first_name'] . " " . $userData['last_name']); ?> Profile
</header>

<section class="page">
    <div class="profile-card">
        <form class="profile-form" method="POST">
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" id="first_name" 
                   value="<?php echo htmlspecialchars($userData['first_name']); ?>" disabled>

            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" id="last_name" 
                   value="<?php echo htmlspecialchars($userData['last_name']); ?>" disabled>

            <label for="email">Email</label>
            <input type="email" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>" disabled>

            <label for="role">Role</label>
            <input type="text" id="role" value="<?php echo htmlspecialchars($userData['role']); ?>" disabled>

            <label for="phone">Phone</label>
            <input type="text" name="phone" id="phone" 
                   value="<?php echo htmlspecialchars($userData['phone']); ?>" disabled>

            <label for="dob">Date of Birth</label>
            <input type="date" name="dob" id="dob" 
                   value="<?php echo htmlspecialchars($userData['DOB']); ?>" disabled>

            <div class="buttons">
                <button type="button" id="editBtn">Edit</button>
                <button type="submit" name="save" id="saveBtn">Save</button>
                <button type="button" onclick="window.location.href='dsh3.html'">Back</button>
            </div>
        </form>

        <?php if (!empty($success)): ?>
            <p style="color:green; font-weight:bold;"><?php echo $success; ?></p>
        <?php elseif (!empty($error)): ?>
            <p style="color:red; font-weight:bold;"><?php echo $error; ?></p>
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
const inputs = document.querySelectorAll('.profile-form input:not([type=email]):not(#role)');

editBtn.addEventListener('click', () => {
    inputs.forEach(i => i.disabled = false);
});
</script>

</body>
</html>
