<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

require_once 'db_connection.php';

$userID = (int)$_SESSION['user_id'];
$waveImg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$temp_file = isset($_GET['upload']) ? $_GET['upload'] : '';

$analysis = null;
$is_temp = false;
$imagePath = '';
$status = 'pending';
$status_class = 'pending';
$notes = '';
$patientName = '';
$anomaly_type = '';

// If we have a temp file, show upload view
if ($temp_file && file_exists($temp_file)) {
    $is_temp = true;
    $imagePath = htmlspecialchars($temp_file);
    $status = 'pending';
    $status_class = 'pending';
    $notes = 'Awaiting analysis and patient assignment.';
    $patientName = 'Not assigned yet';
    $anomaly_type = null;
} 
// Otherwise try to get from database
else if ($waveImg_id > 0) {
    $sql = "SELECT w.*, p.first_name, p.last_name 
            FROM waveform w 
            LEFT JOIN Patient p ON w.PID = p.PID 
            WHERE w.waveImg_id = ? AND w.userID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $waveImg_id, $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die('Analysis not found or unauthorized.');
    }

    $analysis = $result->fetch_assoc();
    $stmt->close();
    
    $imagePath = htmlspecialchars($analysis['filePath']);
    $status = $analysis['status'] ?: 'pending';
    $status_class = $status === 'anomaly' ? 'anomaly' : ($status === 'normal' ? 'normal' : 'pending');
    $notes = $analysis['finding_notes'] ?: 'No notes available.';
    $patientName = $analysis['first_name'] ? $analysis['first_name'] . ' ' . $analysis['last_name'] : 'Not linked yet';
    $anomaly_type = $analysis['anomaly_type'];
}

// Get patients for dropdown
$patients_sql = "SELECT p.PID, p.first_name, p.last_name 
                 FROM Patient p
                 INNER JOIN patient_doctor_assignments pda ON p.PID = pda.PID
                 WHERE pda.userID = ?
                 ORDER BY p.first_name";
$patients_stmt = $conn->prepare($patients_sql);
$patients_stmt->bind_param("i", $userID);
$patients_stmt->execute();
$patients_result = $patients_stmt->get_result();
$patients = $patients_result->fetch_all(MYSQLI_ASSOC);
$patients_stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>TANAFS Dashboard - Analysis Result</title>
    <link rel="icon" type="image/png" href="/images/fi.png">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="dash.css"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        /* Modern Variables */
        :root {
            --primary: #0a76fc;
            --primary-light: #e8f2ff;
            --secondary: #2e7d32;
            --danger: #c62828;
            --danger-light: #fdecea;
            --success: #2e7d32;
            --success-light: #e8f5e9;
            --warning: #f57c00;
            --gray-50: #f8f9fa;
            --gray-100: #f1f3f4;
            --gray-200: #e8eaed;
            --gray-300: #dadce0;
            --gray-400: #bdc1c6;
            --gray-500: #9aa0a6;
            --gray-700: #5f6368;
            --gray-900: #202124;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 60px rgba(0,0,0,0.15);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-2xl: 32px;
            --transition: all 0.3s ease;
        }
        
        /* Modern Card Design */
        .result-card {
            position: relative;
            top: 15%;
            width: 75em;
            max-width: 95%;
            height: fit-content;
            padding: 3em;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            margin: 0 auto;
            align-items: center;
            align-content: center;
            align-self: center;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .result-card:hover {
            box-shadow: 0 15px 50px rgba(0,0,0,0.12);
        }
        
        /* Decorative Background Elements */
        .result-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), #2e7d32);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2em;
            padding-bottom: 1.5em;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .result-header h2 {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .result-header h2 i {
            color: var(--primary);
            font-size: 1.4em;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 0.5em 1.5em;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .status-badge.anomaly {
            background: linear-gradient(135deg, #ff6b6b, #c62828);
            color: white;
        }
        
        .status-badge.normal {
            background: linear-gradient(135deg, #51cf66, #2e7d32);
            color: white;
        }
        
        .status-badge.pending {
            background: linear-gradient(135deg, #ffb74d, #f57c00);
            color: white;
        }
        
        /* Enhanced Grid Layout */
        .analysis-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 2.5em;
            margin-bottom: 2em;
        }
        
        /* Image Container */
        .image-container {
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-md);
            background: var(--gray-50);
            padding: .75em;
            transition: var(--transition);
        }
        
        .image-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .image-container img {
            width: 100%;
            height: auto;
            border-radius: var(--radius-md);
            display: block;
        }
        
        .image-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1em;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .image-label i {
            color: var(--primary);
        }
        
        /* Combined Info Items */
        .combined-info-item {
            margin: 1.2em 0;
            padding: 2em;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }
        
        .combined-info-item:hover {
            background: var(--primary-light);
            transform: translateX(5px);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.8em;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-label i {
            font-size: 1.1em;
        }
        
        /* Analysis Summary */
        .analysis-summary {
            text-align: center;
            padding: 1.5em;
            background: linear-gradient(135deg, var(--primary-light), white);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5em;
        }
        
        .summary-label {
            font-size: 1em;
            color: var(--gray-700);
            font-weight: 600;
            margin-bottom: 1.5em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-content {
            display: flex;
            justify-content: space-around;
            gap: 1em;
        }
        
        .summary-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.8em;
            flex: 1;
        }
        
        .summary-item i {
            font-size: 1.5em;
            color: var(--primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }
        
        .summary-item span {
            font-size: 0.9em;
            color: var(--gray-700);
            font-weight: 500;
        }
        
        /* Save Section */
        .save-section {
            margin-top: 2em;
            padding: 2em;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
        }
        
        .patient-select {
            width: 100%;
            padding: 1em;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            font-size: 1em;
            margin-bottom: 1.5em;
        }
        
        .status-select {
            display: flex;
            gap: 1em;
            margin: 1.5em 0;
        }
        
        .status-option {
            flex: 1;
            padding: 1em;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .status-option:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .status-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
            font-weight: 600;
        }
        
        .status-option.normal i { color: var(--success); }
        .status-option.anomaly i { color: var(--danger); }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.8em 1em;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 1em;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 118, 252, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .alert {
            padding: 1em 1.5em;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5em;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }
        
        .alert-warning {
            background: #eef1f5;
            color: var(--gray-900);
            border-left: 4px solid #96b7ff;
        }
        
        .action-buttons {
            display: flex;
            gap: 1em;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 2em;
        }
        
        .btn-primary, .btn-secondary, .btn-outline {
            padding: 1em 2.5em;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 1em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: var(--transition);
            border: none;
            min-width: 180px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #0056cc);
            color: white;
            box-shadow: 0 4px 15px rgba(10, 118, 252, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(10, 118, 252, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), #1b5e20);
            color: white;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(46, 125, 50, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--gray-300);
            color: var(--gray-700);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #dbdbdb, #adafb75a);
            color: var(--gray-700);
            padding: 0.5em 1.5em;
            border-radius: 50px;
            font-size: 0.9em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 1200px) {
            .result-card {
                width: 95%;
                padding: 2em;
            }
            
            .analysis-grid {
                grid-template-columns: 1fr;
                gap: 2em;
            }
            
            .summary-content {
                flex-wrap: wrap;
            }
            
            .summary-item {
                flex: 0 0 calc(50% - 1em);
            }
        }
        
        @media (max-width: 768px) {
            .result-card {
                padding: 1.5em;
                top: 10%;
            }
            
            .result-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .summary-content {
                flex-direction: column;
                gap: 1.5em;
            }
            
            .summary-item {
                flex-direction: row;
                text-align: left;
                gap: 1em;
            }
            
            .status-select {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary, .btn-outline {
                width: 100%;
            }
        }
    </style>
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
                    <div class="user-info-minimal">
                        <div class="user-name"><?php echo $_SESSION['doctorName'] ?></div>
                        <div class="user-role"><?php echo $_SESSION['role'] ?></div>
                    </div>
                </div>
            </a>
            <form action="Logout.php" method="post" style="display:inline;">
                <button type="submit" class="btn-logout">
                    <span class="material-symbols-outlined" style="font-size: 2em; margin-right:1.24em;">logout</span>
                </button>
            </form>
        </nav>
        
        <main class="container">
            <div class="result-card">
                <div class="result-header">
                    <h2><i class="fas fa-chart-line"></i> Waveform Analysis Result</h2>
                    <?php if ($is_temp): ?>
                        <span class="badge-pending"><i class="fas fa-clock"></i> Pending Assignment</span>
                    <?php else: ?>
                        <span class="status-badge <?= $status_class ?>">
                            <i class="fas fa-<?= $status === 'anomaly' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                            <?= ucfirst($status) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($is_temp): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <span>This is a new upload. Please select a patient and save the analysis.</span>
                    </div>
                <?php endif; ?>

                <div class="analysis-grid">
                    <!-- LEFT: IMAGE -->
                    <div>
                        <div class="image-label">
                            <i class="fas fa-wave-square"></i> Waveform Visualization
                        </div>
                        <div class="image-container">
                            <img src="<?= $imagePath ?>" alt="Waveform Analysis">
                        </div>
                    </div>

                    <!-- RIGHT: DETAILS -->
                    <div>
                        <!-- Combined Info Item -->
                        <div class="combined-info-item">
                            <div class="info-label">
                                <i class="fas fa-stethoscope"></i> Patient
                            </div>
                            <p style="font-size: 1.1em; font-weight: 600;"><?= htmlspecialchars($patientName) ?></p>
                            
                            <?php if (!$is_temp && $anomaly_type): ?>
                                <div class="info-label" style="margin-top: 1.5em;">
                                    <i class="fas fa-exclamation-circle"></i> Anomaly Type
                                </div>
                                <p><?= htmlspecialchars($anomaly_type) ?></p>
                            <?php endif; ?>
                            
                            <div class="info-label" style="margin-top: 1.5em;">
                                <i class="fas fa-clock"></i> <?= $is_temp ? 'Upload' : 'Analysis' ?> Time
                            </div>
                            <p><?= $is_temp ? date('Y-m-d H:i') : date('Y-m-d H:i', strtotime($analysis['timestamp'])) ?></p>
                            
                            <?php if (!$is_temp): ?>
                                <div class="info-label" style="margin-top: 1.5em;">
                                    <i class="fas fa-notes-medical"></i> Clinical Notes
                                </div>
                                <p><?= nl2br(htmlspecialchars($notes)) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($is_temp): ?>
                <!-- SAVE SECTION FOR TEMP UPLOAD -->
                <div class="save-section">
                    <h3 style="margin-bottom: 1.5em; color: var(--gray-900);">
                        <i class="fas fa-save"></i> Save Analysis to Patient
                    </h3>
                    
                    <form id="saveAnalysisForm">
                        <input type="hidden" name="file_path" value="<?= htmlspecialchars($temp_file) ?>">
                        
                        <label class="form-label">Select Patient <span style="color: var(--danger);">*</span></label>
                        <select name="patient_id" class="patient-select" required>
                            <option value="">-- Choose a patient --</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= $patient['PID'] ?>">
                                    P-<?= $patient['PID'] ?> - <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label class="form-label">Analysis Result</label>
                    <!--     <div class="status-select">
                           <div class="status-option normal selected" onclick="selectStatus('normal', this)">
                                <i class="fas fa-check-circle"></i> Normal
                            </div>
                            <div class="status-option anomaly" onclick="selectStatus('anomaly', this)">
                                <i class="fas fa-exclamation-triangle"></i> Anomaly Detected
                            </div>
                        </div>-->
                        <input type="hidden" name="status" id="statusInput" value="normal">
                        
                        <div id="anomalyField" style="display: none;">
                            <label class="form-label">Anomaly Type</label>
                            <input type="text" name="anomaly_type" class="form-input" placeholder="e.g., Double trigger, Auto trigger, Ineffective trigger, etc...">
                        </div>
                        
                        <label class="form-label">Clinical Notes</label>
                        <textarea name="finding_notes" class="form-textarea" rows="4" placeholder="Enter your observations and recommendations...">Normal waveform pattern detected. No significant abnormalities found.</textarea>
                        
                        <div class="action-buttons">
                            <button type="button" class="btn-outline" onclick="window.location.href='dashboard.php'">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn-primary" onclick="saveAnalysis()">
                                <i class="fas fa-save"></i> Save Analysis
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <!-- ACTION SECTION FOR SAVED ANALYSIS -->
                <div class="action-section">
                    <div class="action-buttons">
                        <button class="btn-outline" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </button>
                        <button class="btn-secondary" onclick="window.location.href='history2.php'">
                            <i class="fas fa-history"></i> View History
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <footer id="contact" class="site-footer">
        <div class="footer-grid">
            <div class="footer-col brand">
                <img src="images/logo.png" alt="Tanafs logo" class="footer-logo"/>
                <p class="brand-tag">Breathe well, live well</p>
            </div>

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
                        <a href="mailto:Tanafs@gmail.com" class="contact-link">
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
        function selectStatus(status, element) {
            // Update hidden input
            document.getElementById('statusInput').value = status;
            
            // Remove selected class from all options
            document.querySelectorAll('.status-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Show/hide anomaly field
            document.getElementById('anomalyField').style.display = 
                status === 'anomaly' ? 'block' : 'none';
        }

        function saveAnalysis() {
            const form = document.getElementById('saveAnalysisForm');
            const formData = new FormData(form);
            
            // Show loading state
            const saveBtn = document.querySelector('.btn-primary');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;
            
            fetch('save_analysis.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ ' + data.message);
                    window.location.href = data.redirect;
                } else {
                    alert('Error: ' + data.message);
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>