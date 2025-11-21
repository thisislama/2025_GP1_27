<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

unset($_SESSION['show_uploaded_image']);
unset($_SESSION['last_uploaded_image']);
unset($_SESSION['uploaded_filename']);

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
    'patients' => 0
];
$recent_patients = [];
$upload_message = '';


if ($conn->connect_error) {
    $error_message = "Database connection failed: " . $conn->connect_error;
} else {
    // Clear upload message after displaying once
    if (isset($_SESSION['upload_message'])) {
        unset($_SESSION['upload_message']);
    }
    // Get statistics

    // Get total patients assigned to this doctor
    $patients_sql = "SELECT COUNT(DISTINCT PID) AS total_patients FROM patient_doctor_assignments WHERE userID = ?";
    $patients_stmt = $conn->prepare($patients_sql);
    $patients_stmt->bind_param("i", $userID);
    $patients_stmt->execute();
    $patients_result = $patients_stmt->get_result();
    if ($patients_result && $patients_row = $patients_result->fetch_assoc()) {
        $stats['patients'] = $patients_row['total_patients'] ?? 0;
    }
    $patients_stmt->close();

        // Get scans and anomalies for assigned patients
    $scans_sql = "
        SELECT 
            COUNT(wa.waveAnalysisID) AS total_scans,
            SUM(CASE WHEN wa.status = 'anomaly' THEN 1 ELSE 0 END) AS anomalies
        FROM Waveform_analysis wa
        WHERE wa.PID IN (SELECT PID FROM patient_doctor_assignments WHERE userID = ?)
    ";

        $scans_stmt = $conn->prepare($scans_sql);
        $scans_stmt->bind_param("i", $userID);
        $scans_stmt->execute();
        $scans_result = $scans_stmt->get_result();
        if ($scans_result && $scans_row = $scans_result->fetch_assoc()) {
            $stats['total_scans'] = $scans_row['total_scans'] ?? 0;
            $stats['anomaly'] = $scans_row['anomalies'] ?? 0;
        }
        $scans_stmt->close();

   // Get recent patients with details
    $recent_sql = "
    SELECT p.PID, p.first_name, p.last_name, p.status
    FROM Patient p
    INNER JOIN patient_doctor_assignments pda ON p.PID = pda.PID
    WHERE pda.userID = ?
    LIMIT 3
    ";

    $stmt = $conn->prepare($recent_sql);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $recent_result = $stmt->get_result();

if ($recent_result) {
    $recent_patients = $recent_result->fetch_all(MYSQLI_ASSOC);
}


         // Handle file upload
        $upload_result = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['waveform_file'])) {
    $upload_result = handleFileUpload($conn, $userID);
    if (isset($upload_result['success'])) {
        $upload_message = $upload_result['success'];
        $_SESSION['last_uploaded_image'] = $upload_result['file_path'];
    } else {
        $upload_message = $upload_result['error'];
    }
}
}


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
    $allowed_types = ['png', 'jpg', 'jpeg'];
    if (!in_array($fileType, $allowed_types)) {
        return ['error' => "Error: Only PNG, JPG, JPEG files are allowed."];
    }

    // Check file size (10MB max)
    if ($_FILES["waveform_file"]["size"] > 10000000) {
        return ['error' => "Error: File is too large. Maximum size is 10MB."];
    }

    // Upload file
    if (move_uploaded_file($_FILES["waveform_file"]["tmp_name"], $target_file)) {
        // Get a random patient ID for demonstration
        $patient_sql = "SELECT PID FROM Patient ORDER BY RAND() LIMIT 1";
        $patient_result = $conn->query($patient_sql);
        $patient_id = $patient_result->fetch_assoc()['PID'];

        // Insert into Waveform_Img table
        $waveform_sql = "
            INSERT INTO Waveform_Img (userID, filePath, waveformType, timestamp) 
            VALUES (?, ?, ?, NOW())
        ";
        $stmt = $conn->prepare($waveform_sql);
        $waveform_type = getWaveformType($fileType);
        $stmt->bind_param("iss", $userID, $target_file, $waveform_type);

        if ($stmt->execute()) {
            $wave_img_id = $stmt->insert_id;

            // Create analysis entry
            //  $analysis_result = createWaveformAnalysis($conn, $wave_img_id, $patient_id);

            return [
                'success' => "File uploaded successfully!",
                'file_path' => $target_file,
                'file_name' => $filename
            ];
        } else {
            return ['error' => "Error saving file information to database."];
        }
    } else {
        return ['error' => "Error uploading file."];
    }
}

function getWaveformType($fileType)
{
    $types = [
        'png' => 'Image',
        'jpg' => 'Image',
        'jpeg' => 'Image'
    ];
    return $types[$fileType] ?? 'Unknown';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>TANAFS Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
    <link rel="stylesheet" href="dash.css"/>
</head>
<body>
    <!-- Header for iPad & medium screens only -->
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

            <h2 style="color:#1f45b5; font-size:1.65em;margin:5%">
                Welcome back <br>
                <span style="color:rgba(89,115,195,0.76);font-size: .80em;margin-left: 1.7em">
                 <?php echo  $_SESSION['doctorName'] ?>
                </span>
            </h2>
            <!--
            <?php if (!empty($upload_message)): ?>
                <div class="upload-message <?php echo strpos($upload_message, 'Error') !== false ? 'upload-error' : 'upload-success'; ?>"
                     style="border-radius: 5px; text-align: center;">
                    <?php echo htmlspecialchars($upload_message); ?>
                </div>
            <?php endif; ?>
            -->
            <!-- UPLOAD CARD -->
        <form method="post" enctype="multipart/form-data" class="upload-card" style="box-shadow: rgba(169,175,188,0.69) -.01em .01em 0.5em .1em">
              
            <input id="fileUpload" type="file" name="waveform_file" accept=".jpeg,.png,.jpg"/>
              <label for="fileUpload" class="upload-drop" id="dropzone">
                <div style="font-size:28px;opacity:0.95">
                    <span class="material-symbols-outlined">upload</span>
                </div>
                <div class="hint">Upload your waveform, Here!</div>
                <div style="font-size:13px;color:#0b84feb3;margin-top:8px">Drag &amp; drop or click to select a file</div>
                <div style="font-size:13px;color:rgba(145,148,151,0.7);margin-top:8px"> Only JPEG, PNG, JPG files are allowed. </div>
                </label>
        </form>

            <div class="small-cards">
                <h3 style="margin-bottom:12px;color:#2b4a77;font-weight:700">Recent Patients</h3>
                <?php if (!empty($recent_patients)): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="small-item"
                             onclick="window.location.href='patient.html?pid=<?php echo $patient['PID']; ?>'">
                            <div style="gap:6px; display:flex; flex-direction:column">
                                <div class="fileNum" style="opacity: .95;">File Number - <span class="id" style="font-size:15px;">P<?php echo substr($patient['PID'], -4); ?></span></div>
                               <!-- <div class="id"> P<?php echo substr($patient['PID'], -4); ?></div>-->
                                <div class="muted" style="font-size:13px">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    <!--<br>Status: <?php echo htmlspecialchars($patient['status']); ?>-->
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
                                    echo "<div class='id'>File Number: P" . substr($patient['PID'], -4) . "</div>";
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
                        <div class="value"><?php echo $stats['patients'] ?></div>
                        <div class="under"><?php echo $stats['patients'] ?>  total patients assigned to you</div>

                    </div>
                    <div style="background:linear-gradient(150deg,rgb(101,0,255),#7750b8);padding:10px;border-radius:8px;color:#fff;font-weight:700">
                        <span  style="font-size: 1.65em;text-align: center" class="material-symbols-outlined">group</span>
                    </div>
                </div>

            </div>

            <div class="result-card">
                <div class="title">Analysis Overview</div>
                <?php if (isset($_SESSION['last_uploaded_image']) && file_exists($_SESSION['last_uploaded_image'])): ?>
                    <div style="text-align:center; margin:15px 0;">
                        <h4 style="color: #2b4a77; margin-bottom: 10px;">Uploaded Waveform</h4>
                        <img src="<?php echo htmlspecialchars($_SESSION['last_uploaded_image']); ?>"
                             alt="Uploaded Waveform"
                             style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 1px solid #e9eef6; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <p style="font-size: 12px; color: var(--muted); margin-top: 8px;">
                            <?php echo htmlspecialchars(basename($_SESSION['last_uploaded_image'])); ?>
                        </p>
                    </div>
                   
                <?php endif; ?>

                <div class="result-output" id="resultArea">
                    <?php
                        if (isset($_SESSION['last_uploaded_image'])) {
                            echo "Latest analysis completed. File: " . htmlspecialchars(basename($_SESSION['last_uploaded_image']));
                        } else {
                            if ($stats['total_scans'] > 0) {
                                echo "Total analyses: {$stats['total_scans']} | Anomalies detected: {$stats['anomaly']}";
                            } else {
                                echo "Your result will show here!";
                            }
                        }
                    ?>
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
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileUpload');
    const resultArea = document.getElementById('resultArea');

    // Only use click event - no drag & drop to avoid conflicts
    dropzone.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        fileInput.click();
    });

    // Handle file selection
    fileInput.addEventListener('change', (e) => {
        const f = e.target.files[0];
        if (!f) return;
         
        resultArea.textContent = 'Uploading: ' + f.name;
        
        // Auto-submit form after short delay
        setTimeout(() => {
            fileInput.closest('form').submit();
        }, 500);
    });

  
</script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>