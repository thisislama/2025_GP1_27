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


// Initialize variables
$stats = [
    'anomaly' => 0,
    'total_scans' => 0,
    'patients' => 0,
    'confidence' => 0
];
$recent_patients = [];
$upload_message = '';


if ($conn->connect_error) {
    $error_message = "Database connection failed: " . $conn->connect_error;
} else {
    // Get statistics
    // This query returns the total number of unique patients assigned to the current doctor,
    // the total number of waveform analyses (scans), and the number of analyses marked as anomalies.
    $stats_sql = "
        SELECT
            COUNT(DISTINCT da.PID) AS total_patients,
            COUNT(wa.waveAnalysisID) AS total_scans,
            SUM(CASE WHEN wa.status = 'anomaly' THEN 1 ELSE 0 END) AS anomalies
        FROM
            patient_doctor_assignments da
            JOIN Waveform_analysis wa ON da.PID = wa.PID
        WHERE
            da.userID = ?
    ";

    $stats_stmt = $conn->prepare($stats_sql);
    if (!$stats_stmt) {
        $error_message = "Prepare failed: " . $conn->error;
    } else {
        $stats_stmt->bind_param("i", $userID);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
            $stats['patients'] = $stats_row['total_patients'] ?? 0;
            $stats['total_scans'] = $stats_row['total_scans'] ?? 0;
            $stats['anomaly'] = $stats_row['anomalies'] ?? 0;
        }
        $stats_stmt->close();
    }

    // Get recent patients
    $recent_sql = "
        SELECT p.PID, p.first_name, p.last_name, p.status
        FROM patient p
        ORDER BY p.PID DESC
        LIMIT 3
    ";
    

    $recent_result = $conn->query($recent_sql);
    if ($recent_result) {
        $recent_patients = $recent_result->fetch_all(MYSQLI_ASSOC);
    }

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['waveform_file'])) {
        $upload_message = handleFileUpload($conn, $userID);
    }
}

// File upload handler function
function handleFileUpload($conn, $userID)
{
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $filename = basename($_FILES["waveform_file"]["name"]);
    $target_file = $target_dir . time() . "_" . $filename;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check file type
    $allowed_types = ['wav', 'txt', 'csv', 'png', 'jpg', 'jpeg'];
    if (!in_array($fileType, $allowed_types)) {
        return "Error: Only WAV, TXT, CSV, PNG, JPG files are allowed.";
    }

    // Check file size (10MB max)
    if ($_FILES["waveform_file"]["size"] > 10000000) {
        return "Error: File is too large. Maximum size is 10MB.";
    }

    // Upload file
    if (move_uploaded_file($_FILES["waveform_file"]["tmp_name"], $target_file)) {
        // Get a random patient ID for demonstration
        $patient_sql = "SELECT PID FROM Patient ORDER BY RAND() LIMIT 1";
        $patient_result = $conn->query($patient_sql);
        $patient_id = $patient_result->fetch_assoc()['PID'];

        // Insert into Waveform_Img table
        $waveform_sql = "
            INSERT INTO Waveform_Img (userID, filePath, waveformType, date, timestamp) 
            VALUES (?, ?, ?, CURDATE(), NOW())
        ";
        $stmt = $conn->prepare($waveform_sql);
        $waveform_type = getWaveformType($fileType);
        $stmt->bind_param("iss", $userID, $target_file, $waveform_type);

        if ($stmt->execute()) {
            $wave_img_id = $stmt->insert_id;

            // Create analysis entry
            $analysis_result = createWaveformAnalysis($conn, $wave_img_id, $patient_id);

            return "File uploaded successfully! Analysis ID: " . $analysis_result;
        } else {
            return " Error saving file information to database.";
        }
    } else {
        return "Error uploading file.";
    }
}

function getWaveformType($fileType)
{
    $types = [
        'wav' => 'Audio',
        'txt' => 'Text',
        'csv' => 'CSV',
        'png' => 'Image',
        'jpg' => 'Image',
        'jpeg' => 'Image'
    ];
    return $types[$fileType] ?? 'Unknown';
}

function createWaveformAnalysis($conn, $wave_img_id, $patient_id)
{
    // Simulate analysis results
    $statuses = ['normal', 'abnormal', 'critical'];
    $severities = ['low', 'medium', 'high'];
    $anomalies = ['Double Trigger', 'Auto Trigger', 'Flow Dysnchrony', 'Ineffective Trigger'];

    $status = $statuses[array_rand($statuses)];
    $severity = $severities[array_rand($severities)];
    $anomaly = $anomalies[array_rand($anomalies)];

    $analysis_sql = "
        INSERT INTO Waveform_analysis 
        (waveImg_id, PID, flow, volume, pressure, status, severity_level, anomaly_type, finding_notes, timestamp) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmt = $conn->prepare($analysis_sql);
    $flow = rand(80, 120);
    $volume = rand(400, 800);
    $pressure = rand(100, 140);
    $notes = "Automated analysis completed. Status: $status, Severity: $severity";

    $stmt->bind_param("iidddssss", $wave_img_id, $patient_id, $flow, $volume, $pressure,
        $status, $severity, $anomaly, $notes);

    if ($stmt->execute()) {
        return $stmt->insert_id;
    }
    return "Unknown";
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>TANAFS Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>


    <style>

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: #15314b;
            overflow-y: auto;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            display: block;
        }

        :root {
            --bg: #f2f6fb;
            --accent: #0f65ff;
            --muted: #9aa6c0;
            --radius: 24px;
            --field-h: 3.25rem;
            --field-r: 12px;
            --gap: 16px;
            --pad: 36px;
            --maxw: 800px;
        }
        .nav-link.active::after {
    width: 100%;
}


        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .material-symbols-outlined {
            font-variation-settings: 'wght' 500;
            font-size: 20px
        }

        .material-symbols-outlined:hover {

            color: #eae8e8ff;
        }

        .btn-logout {
            background: linear-gradient(90deg, #0f65ff, #5aa6ff);
            color: white;
            padding: 0.5em 0.975em;
            border-radius: 0.75em;
            font-weight: 600;
            border: none;
            box-shadow: 0 0.5em 1.25em rgba(15, 101, 255, 0.14);
            cursor: pointer;
            font-size: 0.875em;
        }

        /* -------- Main (header + content) -------- */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 50em;
        }

        /* -------- Page content -------- */
        .container {
            width: 100%;
            margin: 5px;
            margin-top: 90px;
            padding: 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .left-column {
            display: flex;
            flex-direction: column;
            gap: 19px
        }

        .welcome {
            color: #000000;
            margin-bottom: 6px;
            text-indent: 12px;
            font-family: "Oxygen", sans-serif;
        }

        .welcome h1 {
            font-size: 30px;
            margin: 0;
            font-family: "Oxygen", sans-serif;
        }

        .welcome p {
            margin: 6px 0 0;
            color: rgba(0, 0, 0, 0.9);
            font-family: "Oxygen", sans-serif;
        }


        .upload-card {
            border: none;
            padding: 37px;
            min-height: 230px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            border-radius: 12px ;
            backdrop-filter: blur(4px);
            box-shadow: #505867 1px 1px 1px;
        }

        .upload-card input[type=file] {
            display: none
        }
/*dashed*/
        .upload-drop {
            width: 100%;
            height: 230px;
            border-radius: 12px;
            border: 2px dashed;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .upload-drop .hint {
            opacity: 0.9;
            font-size: 18px;
        }

        .small-cards {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px;
            border-radius: 12px
        }

        .small-item {
            padding: 12px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .small-item .id {
            font-weight: 700
        }

        .right-column {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-top: 125.7px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns:repeat(2, 1fr);
            gap: 14px;
        }

        .stat {
            padding: 14px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: #4c5d7a 1px 1px 1px;
        }

        .stat .value {
            font-size: 22px;
            font-weight: 700;
        }

        .stat .label {
            color: rgba(255, 0, 0, 0.93);
            font-weight: 700;
        }

        .stat .under{
            font-size: 0.8em;
            font-weight: 600;
            color: #4c5d7a;
            margin-top: 0.77em;
        }

        .result-card {
            padding: 22px;
            border-radius: 10px;
            min-height: 260px;
            box-shadow: #4c5d7a 1px 1px 1px;
        }

        .result-card .title {
            border-bottom: 1px solid;
            padding-bottom: 8px;
            margin-bottom: 12px;
            font-weight: 600;
            opacity: 0.65;
        }

        .result-output {
            font-weight: 600;
            text-align: center;
            padding-top: 36px;
            opacity: 0.55
        }

        @media (max-width: 1000px) {
            .container {
                grid-template-columns:1fr;
            }

            .container {
                max-width: 920px;
                grid-template-columns:1fr;
            }

            .upload-drop {
                height: 160px
            }

            .stats-grid {
                grid-template-columns:repeat(2, 1fr)
            }
        }

        @media (max-width: 720px) {
            .topbar {
                padding: 10px
            }

            .container {
                padding: 8px
            }

            .welcome h1 {
                font-size: 22px
            }

            .upload-drop {
                height: 140px
            }
        }

        .muted {
            color: var(--muted)
        }

        .btn {
            background: var(--accent);
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer
        }

        .wrapper {
            position: relative;
            width: 100%;
            height: auto;
            min-height: 100vh;
            overflow: visible;
        }

        img.topimg {
            position: absolute;
            top: -15.4%;
            left: 48%;
            transform: translateX(-50%);
            height: auto;
            width: auto;
            max-width: 90%;
            z-index: 10;
            pointer-events: none;
        }

        img.logo {
            position: absolute;
            top: -7.1%;
            left: 14%;
            width: clamp(100px, 12vw, 180px);
            height: auto;
            z-index: 20;
            pointer-events: none;
        }

        .auth-nav {
            position: absolute;
            top: -6%;
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
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #0876FA, #78C1F5);
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-link:hover {
            transform: translateY(-2px);
            color: #055ac0;
        }

        .profile {
            display: flex;
            gap: 0.625em;
            align-items: center;
            padding: 0.375em 0.625em;
        }

        .avatar-icon {
            width: 30px;
            height: 30px;
            display: block;
        }

        .profile-btn {
            all: unset;
            cursor: pointer;
            display: inline-block;
        }

        .btn-logout {
            background: linear-gradient(90deg, #0f65ff, #5aa6ff);
            color: white;
            padding: 0.5em 0.975em;
            border-radius: 0.75em;
            font-weight: 400;
            border: none;
            box-shadow: 0 0.5em 1.25em rgba(15, 101, 255, 0.14);
            cursor: pointer;
            font-size: 0.875em;
        }

        .search {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            width: min(320px, 40vw);
            background: #ffffff;
            border: 1px solid #e7eef8;
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(20, 40, 80, 0.06);
            transition: box-shadow .2s ease, border-color .2s ease, transform .08s ease;
        }

        .search svg {
            flex: 0 0 18px;
            opacity: .7;
        }

        .search input {
            flex: 1 1 auto;
            border: 0;
            outline: 0;
            background: transparent;
            font-size: 14px;
            color: #384b66;
        }

        .search input::placeholder {
            color: #8fa3bf;
        }

        .search:focus-within {
            border-color: #cfe2ff;
            box-shadow: 0 8px 26px rgba(15, 101, 255, 0.10), 0 0 0 4px rgba(15, 101, 255, 0.10);
            transform: translateY(-1px);
        }

        .search:hover {
            border-color: #d9e6f7;
        }

        /* ===== Footer ===== */
        .site-footer {
            background: #F6F6F6;
            color: #0b1b2b;
            font-family: 'Montserrat', sans-serif;
            margin-top: 3em;
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

        .footer-col.brand {
            text-align: left;
        }

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

        .footer-title {
            margin: 0 0 1em 0;
            font-size: 1.05em;
            font-weight: 700;
            letter-spacing: 0.02em;
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

        @media (max-width: 56.25em) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 1.5em;
                text-align: center;
            }

            .social-list {
                justify-content: center;
            }

            .contact-link {
                justify-content: center;
            }

            .brand {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
        }

        /* ===== Responsive ===== */
        @media (max-width: 56.25em) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 1.5em;
                text-align: center;
            }

            .social-list {
                justify-content: center;
            }

            .contact-link {
                justify-content: center;
            }

            .brand {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
        }

        :root {
            --bg: #f2f6fb;
            --accent: #0f65ff;
            --muted: #9aa6c0;
        }

        body {
            background: var(--bg);
            color: #15314b;
        }

        .upload-card,
        .small-cards,
        .stat,
        .result-card {
            background: #fff;
            border: 1px solid #e9eef6;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .05);
        }
        .upload-card{
            border: none;
            box-shadow: none;

        }


        .upload-drop {
            width: 30em;
            background: #fff;
            border: 2px dashed rgba(68, 110, 170, 0.7);
            color: #2b4a77;
            cursor: pointer;
        }

        .upload-drop .hint {
            color: #2b4a77;

        }

        .small-item {
            background: #eef3fb;
            border: 1px solid #e9eef6;
        }

        .small-item .id {
            color: #2b4a77;
        }

        .muted {
            color: var(--muted)
        }

        .stat .label {
            color: #195695;
        }

        .stat .value {
            color: #143340e6;
        }

        .result-card .title {
            color: #2b4a77;
            border-bottom: 1px solid #e9eef6;
            opacity: 1;
        }

        .result-output {
            color: #2b4a77;
            opacity: .75
        }

        .search {
            background: #fff;
            border: 1px solid #e7eef8;
            box-shadow: 0 6px 20px rgba(20, 40, 80, .06);
        }

        .search input {
            color: #384b66;
        }

        .search input::placeholder {
            color: #8fa3bf;
        }

        .btn,
        .btn-logout {
            background: linear-gradient(90deg, #0f65ff, #5aa6ff);
            color: #fff;
        }

        .nav-link,
        .footer-title,
        .social-handle,
        .contact-link {
            color: #0B83FE;
        }

        .welcome, .welcome h1, .welcome p {
            color: #2b4a77;
        }

        @media (min-width: 768px) and (max-width: 1024px) {
            .auth-nav {
                top: -5%;
                right: 11%;
                gap: 1.2em;
            }

            img.logo {
                top: -5.1%;
                left: 11%;
                width: clamp(5em, 14vw, 10em);
            }

            img.topimg {
                top: -9%;
                max-width: 100%;
            }
        }


    </style>
</head>
<body>


<div class="wrapper">

    <img class="topimg" src="Images/Group 8.png" alt="img">
    <img class="logo" src="Images/Logo.png" alt="Tanafs Logo">

    <nav class="auth-nav" aria-label="User navigation">
<a class="nav-link active" href="dashboard.php">Dashboard</a>
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

    <main class="container">
        <!-- LEFT -->
        <section class="left-column">

            <h2 style="color:#1f45b5; font-size:1.65em;margin:40px 0 0px 1.2em;">
                Welcome back <br>
                <span style="color:rgba(89,115,195,0.76);font-size: .80em;margin-left: 1.7em">
    <?php echo  $_SESSION['doctorName'] ?>
  </span>
            </h2>
            <label class="upload-card" for="fileUpload" style="box-shadow: rgba(169,175,188,0.69) -.01em .01em 0.5em .1em">
                <form method="post" enctype="multipart/form-data" class="upload-card">
                    <input id="fileUpload" type="file" accept=".csv,.png,.jpg"/>
                    <div class="upload-drop" id="dropzone">
                        <div style="font-size:28px;opacity:0.95">
                            <span class="material-symbols-outlined">upload</span>
                        </div>
                        <div class="hint">Upload your waveform, Here!</div>
                        <div style="font-size:13px;color:#0b84feb3;margin-top:8px">Drag &amp; drop or click to
                            select a file
                        </div>
                        <div style="font-size:13px;color:rgba(145,148,151,0.7);margin-top:8px"> Only CSV, PNG, JPG files are allowed. </div>
                    </div>
                    <?php if (!empty($upload_message)): ?>
                        <div class="upload-message <?php echo strpos($upload_message, '') !== false ? 'upload-error' : 'upload-success'; ?>">
                            <?php echo htmlspecialchars($upload_message); ?>
                        </div>
                    <?php endif; ?>
                </form>
            </label>

            <div class="small-cards">
                <?php if (!empty($recent_patients)): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="small-item"
                             onclick="window.location.href='patient.html?pid=<?php echo $patient['PID']; ?>'">
                            <div>
                                <div class="id">P<?php echo substr($patient['PID'], -4); ?></div>
                                <div class="muted" style="font-size:13px">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    <br>Status: <?php echo htmlspecialchars($patient['status']); ?>
                                </div>
                            </div>
                            <div style="background:#8fa3bf2f;padding:8px;border-radius:8px;cursor:pointer"><a href="patient.html?pid=<?php echo $patient['PID']; ?>">
                                    ️</a>
                                <span class="material-symbols-outlined"
                                      style="color: rgba(18,36,51,0.65)">arrow_forward</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="small-item">
                        <div>
                            <?php
                            // Display a message when there are no recent patients
                            if(empty($recent_patients)){
                                echo "<div class='id'>No Recent Patients</div>";
                                echo "<div class='muted' style='font-size:13px'>Add patient through patients page to see patient data</div>";
                            }
                            else{
                                foreach ($recent_patients as $patient) {
                                    echo "<div class='id'>P" . substr($patient['PID'], -4) . "</div>";
                                    echo "<div class='muted' style='font-size:14px; '>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "<br>Status: " . htmlspecialchars($patient['status']) . "</div>";
                                }

                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- RIGHT -->
        <section class="right-column">
            <div class="stats-grid">
                <div class="stat">
                    <div>
                        <div class="label" style="margin-bottom:8px;color: #232735">Anomaly</div>
                        <div class="value"><?php echo $stats['anomaly'] ?? '0' ?></div>
                        <div class="under"><span class="material-symbols-outlined" style="margin-top: 8px;margin-right: 6px; color: #14b530">trending_up</span><?php echo $stats['anomaly'] ?? '0'?>% of total scans</div>
                    </div>

                    <div style="background:linear-gradient(150deg,rgb(218,35,35),rgb(214,103,103));padding:10px;border-radius:8px;color:#fff;font-weight:700">
                        <span style="font-size: 1.75em;text-align: center" class="material-symbols-outlined">warning</span>
                    </div>
                </div>

                <div class="stat">
                    <div>
                        <div class="label" style="margin-bottom:8px;color: #232735">Analysis</div>
                        <div class="value"><?php echo $stats['total_scans']; ?></div>
                        <div class="under"><?php echo $stats['total_scans']; ?> analyses you applied for</div>
                    </div>
                    <div style="background:linear-gradient(150deg,rgb(151,255,2),#5b8c2f);padding:10px;border-radius:8px;color:#fff;font-weight:700">
                        <span  style="font-size: 1.65em;text-align: center" class="material-symbols-outlined">scan</span>
                    </div>
                </div>

                <div class="stat" style="width: 205%">
                    <div>
                        <div class="label"  style="margin-bottom:8px;color: #232735">Patients</div>
                        <div class="value"><?php echo $stats['patients']; ?></div>
                        <div class="under"><?php echo $stats['patients']; ?>  total patients assigned to you</div>

                    </div>
                    <div style="background:linear-gradient(150deg,rgb(101,0,255),#7750b8);padding:10px;border-radius:8px;color:#fff;font-weight:700">
                        <span  style="font-size: 1.65em;text-align: center" class="material-symbols-outlined">group</span>
                    </div>
                </div>

                <!--    <div class="stat">
                  <div>
                        <div class="label">AI confidence</div>
                        <div class="value"><?php echo $stats['confidence']; ?>%</div>
                        <div class="under"><?php echo $stats['total_scans']; ?> completed</div>
                    </div>
                    <div style="background:linear-gradient(150deg,rgb(0,255,140),#6b9a85);padding:10px;border-radius:8px;color:#fff;font-weight:700">
                        <span  style="font-size: 1.65em;text-align: center" class="material-symbols-outlined">check_circle</span>
                    </div>
                </div>-->
            </div>

            <div class="result-card">
                <div class="title">Analysis Overview</div>
                <div class="result-output" id="resultArea">
                    <?php echo $stats['total_scans'] > 0 ?
                        "Total analyses: {$stats['total_scans']} | Anomalies detected: {$stats['anomaly']}" :
                        "You're result will show here!";
                    ?>
                </div>
                <div class="chart-container">
                    <canvas id="analysisChart"></canvas>
                </div>
            </div>
            </div>
        </section>
    </main>

</div>


<footer id="contact" class="site-footer">
    <div class="footer-grid">

        <div class="footer-col brand">
            <img src="images/logo.png" alt="Tanafs logo" class="footer-logo"/>
            <p class="brand-tag">Breathe well, live well</p>
        </div>

        <!-- Social -->
        <nav class="footer-col social" aria-label="Social media">
            <h3 class="footer-title">Social Media</h3>
            <ul class="social-list">
                <li>
                    <a href="#" aria-label="Twitter">
                        <img src="images/twitter.png" alt="Twitter"/>
                    </a>
                </li>
                <li>
                    <a href="#" aria-label="Instagram">
                        <img src="images/instagram.png" alt="Instagram"/>
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
                        <img src="images/whatsapp.png" alt="WhatsApp"/>
                        <span>+123 165 788</span>
                    </a>
                </li>
                <li>
                    <a href="mailto:Appointly@gmail.com" class="contact-link">
                        <img src="images/email.png" alt="Email"/>
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

<script>
    // Tab switching (visual only)
    document.querySelectorAll('.nav button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.nav button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        })
    });

    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileUpload');
    const resultArea = document.getElementById('resultArea');

    function showResult(text) {
        resultArea.textContent = text;
    }

    // File selection
    fileInput.addEventListener('change', (e) => {
        const f = e.target.files[0];
        if (!f) return;
        showResult('Uploaded: ' + f.name);
    });

    // Drag & drop
    ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'rgba(255,255,255,0.4)'
        });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'rgba(255,255,255,0.12)'
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const f = e.dataTransfer.files[0];
        if (!f) return;
        // assign to file input (so subsequent clicks show same file selected)
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(f);
        fileInput.files = dataTransfer.files;
        showResult('Uploaded: ' + f.name);
    });

    // Make the label clickable to open file dialog
    dropzone.addEventListener('click', () => fileInput.click());



    function showResult(text) {
        resultArea.textContent = text;
    }

    // Chart.js for analytics
    const ctx = document.getElementById('analysisChart').getContext('2d');
    const analysisChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Normal', 'Abnormal', 'Critical'],
            datasets: [{
                data: [<?php echo max(0, $stats['total_scans'] - $stats['anomaly']); ?>,
                    <?php echo $stats['anomaly']; ?>,
                    <?php echo min($stats['anomaly'], 2); ?>],
                backgroundColor: [
                    '#4CAF50',
                    '#FF9800',
                    '#F44336'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            },
            cutout: '70%'
        }
    });

    // Global search functionality
    document.getElementById('globalSearch').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = this.value.trim();
            if (searchTerm) {
                window.location.href = `patients.php?search=${encodeURIComponent(searchTerm)}`;
            }
        }
    });
</script>

</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>