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
$status = 'normal';
$status_class = 'normal';
$notes = '';
$patientName = '';
$anomaly_type = '';
$suggested_recommendation = '';

// If we have a temp file, show upload view
if ($temp_file && file_exists($temp_file)) {
    $is_temp = true;
    $imagePath = htmlspecialchars($temp_file);
    $status = 'normal';
    $status_class = 'normal';
    $notes = 'Awaiting analysis and patient assignment.';
    $patientName = 'Not assigned yet';
    $anomaly_type = null;
    
    // Generate AI suggestion based on filename or default
    $suggested_recommendation = 'Based on the waveform pattern, consider monitoring patient vitals and reviewing ventilator settings.';
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
    $suggested_recommendation = $analysis['suggested_recommendation'] ?? 'Regular monitoring of patient respiratory patterns recommended.';
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
        
        /* Main container adjustments */
        .container {
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0 auto;
        }
        
        /* Modern Card Design - Optimized size */
        .result-card {
            position: relative;
            top: 6.75em;
            width: 100%;
            max-width: 1200px;
            min-width: 320px;
            padding: 2em;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: var(--transition);
            margin: 0 auto;
            max-height: fit-content;
            height: auto;
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
            margin-bottom: 1.5em;
            padding-bottom: 1em;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .result-header h2 {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        .result-header h2 i {
            color: var(--primary);
            font-size: 1.2em;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 0.4em 1.2em;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            grid-template-columns: 1fr 1fr;
            gap: 1.5em;
            margin-bottom: 1.5em;
        }
        
        /* Image Container */
        .image-container {
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-md);
            background: var(--gray-50);
            padding: 0.5em;
            transition: var(--transition);
            max-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-container:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .image-container img {
            width: 100%;
            height: auto;
            max-height: 230px;
            object-fit: contain;
            border-radius: var(--radius-md);
            display: block;
        }
        
        .image-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 0.8em;
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.95em;
        }
        
        .image-label i {
            color: var(--primary);
        }
        
        /* Combined Info Items */
        .combined-info-item {
            margin: 0.8em 0;
            padding: 1.2em;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }
        
        .combined-info-item:hover {
            background: var(--primary-light);
            transform: translateX(5px);
        }
        
        .combined-info-item p {
            margin: 0.3em 0;
            font-size: 0.95em;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.3em;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-label i {
            font-size: 1em;
        }
        
        /* Recommendation Card */
        .recommendation-card {
            background: linear-gradient(135deg, var(--primary-light), white);
            border-radius: var(--radius-lg);
            padding: 1.2em;
            margin: 1em 0;
            border: 1px solid var(--gray-200);
        }
        
        .recommendation-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 0.8em;
        }
        
        .recommendation-text {
            font-size: 1em;
            color: var(--gray-900);
            line-height: 1.5;
            font-style: italic;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-container {
            background: white;
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            padding: 1.5em;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--primary-light), white);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }
        
        .modal-header h3 {
            font-size: 1.3em;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        .modal-header h3 i {
            color: var(--primary);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: var(--gray-500);
            transition: var(--transition);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal-close:hover {
            background: var(--gray-200);
            color: var(--gray-900);
        }
        
        .modal-body {
            padding: 1.5em;
        }
        
        .modal-footer {
            padding: 1.5em;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 1em;
        }
        
        /* Feedback Section */
        .feedback-section {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1.2em;
            margin: 1.5em 0;
        }
        
        .feedback-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .feedback-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1em;
            padding: 0.8em;
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .feedback-option:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .feedback-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .feedback-option label {
            flex: 1;
            cursor: pointer;
            font-weight: 500;
        }
        
        .feedback-textarea {
            width: 100%;
            padding: 0.8em;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.95em;
            transition: var(--transition);
            font-family: inherit;
            margin-top: 1em;
            resize: vertical;
        }
        
        .feedback-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 118, 252, 0.1);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.8em;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 1.5em;
        }
        
        .btn-primary, .btn-secondary, .btn-outline, .btn-success {
            padding: 0.8em 2em;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            border: none;
            min-width: 150px;
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
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #1b5e20);
            color: white;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(46, 125, 50, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
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
            padding: 0.4em 1.2em;
            border-radius: 50px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Form Elements */
        .patient-select {
            width: 100%;
            padding: 0.8em;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            font-size: 0.95em;
            margin-bottom: 1em;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.7em 1em;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.95em;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .alert {
            padding: 0.8em 1.2em;
            border-radius: var(--radius-lg);
            margin-bottom: 1em;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
            font-size: 0.95em;
        }
        
        .alert-warning {
            background: #eef1f5;
            color: var(--gray-900);
            border-left: 4px solid #96b7ff;
        }
        
        .alert-info {
            background: var(--primary-light);
            color: var(--gray-900);
            border-left: 4px solid var(--primary);
        }
        
        /* Animations */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .result-card {
                width: 95%;
                padding: 1.5em;
            }
            
            .analysis-grid {
                gap: 1.2em;
            }
            
            .image-container {
                max-height: 220px;
            }
            
            .image-container img {
                max-height: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .result-card {
                padding: 1.2em;
                top: 4em;
            }
            
            .result-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .result-header h2 {
                font-size: 1.3em;
            }
            
            .analysis-grid {
                grid-template-columns: 1fr;
                gap: 1em;
            }
            
            .image-container {
                max-height: 200px;
            }
            
            .image-container img {
                max-height: 180px;
            }
            
            .modal-container {
                width: 95%;
                margin: 1em;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary, .btn-outline, .btn-success {
                width: 100%;
                min-width: unset;
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
                        <span>This is a new upload. Please save the analysis to a patient record.</span>
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
                                <i class="fas fa-clipboard-list"></i> Waveform detected to be 
                                <span style="color: <?= $status === 'anomaly' ? 'var(--danger)' : 'var(--success)' ?>; text-transform: uppercase;">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </div>

                            <!--  Suggestion Card -->
                            <div class="recommendation-card">
                                <div class="recommendation-title">
                                    <i class="fas fa-stethoscope"></i>
                                    <span>Suggested Recommendation</span>
                                </div>
                                <div class="recommendation-text">
                                    <?= htmlspecialchars($suggested_recommendation) ?>
                                </div>
                            </div>

                            <!--
                            <div class="info-label">
                                <i class="fas fa-stethoscope"></i> Suggested Recommendation
                            </div>
                            <p style="font-size: 1.1em; font-weight: 600;"><?= htmlspecialchars($suggested_recommendation) ?></p>-->

                            <div class="info-label" style="margin-top: 1.5em;">
                                <i class="fas fa-user-md"></i> Waveform uploaded By Dr. <?= htmlspecialchars($_SESSION['doctorName'] ?? 'System') ?>
                            </div>
                            
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
                <!-- ACTION BUTTONS FOR TEMP UPLOAD -->
                <div class="action-buttons">
                    <button type="button" class="btn-outline" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn-success" onclick="openSaveModal()">
                        <i class="fas fa-save"></i> Save to Patient
                    </button>
                </div>
                <?php else: ?>
                <!-- ACTION SECTION FOR SAVED ANALYSIS -->
                <div class="action-buttons">
                    <button class="btn-outline" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </button>
                    <button class="btn-secondary" onclick="window.location.href='history2.php'">
                        <i class="fas fa-history"></i> View History
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- SAVE MODAL -->
    <div id="saveModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-save"></i> Save Analysis to Patient</h3>
                <button class="modal-close" onclick="closeSaveModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="saveAnalysisForm">
                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($temp_file) ?>">
                    
                    <!-- Patient Selection -->
                    <label class="form-label">Select Patient <span style="color: var(--danger);">*</span></label>
                    <select name="patient_id" class="patient-select" required>
                        <option value="">-- Choose a patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['PID'] ?>">
                                P-<?= $patient['PID'] ?> - <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- Analysis Result (Hidden) -->
                    <input type="hidden" name="status" id="statusInput" value="normal">
                    
                    <div id="anomalyField" style="display: none;">
                        <label class="form-label">Anomaly Type</label>
                        <input type="text" name="anomaly_type" class="form-input" placeholder="e.g., Double trigger, Auto trigger, Ineffective trigger, etc...">
                    </div>
                    
                    <!-- Doctor Feedback Section -->
                    <div class="feedback-section">
                        <div class="feedback-title">
                            <i class="fas fa-comment-medical"></i>
                            <span>Doctor's Feedback on Recommendation</span>
                        </div>
                        
                        <div class="alert alert-info" style="margin-bottom: 1em;">
                            <i class="fas fa-robot"></i>
                            <span> Suggested Recommendation: <?= htmlspecialchars($suggested_recommendation) ?></span>
                        </div>
                        
                        <div class="feedback-option">
                            <input type="radio" name="feedback_helpful" id="feedbackYes" value="yes" checked>
                            <label for="feedbackYes">
                                <strong>Yes, this recommendation is helpful</strong>
                                <br>
                                <small style="color: var(--gray-500);">I agree with the suggestion</small>
                            </label>
                        </div>
                        
                        <div class="feedback-option">
                            <input type="radio" name="feedback_helpful" id="feedbackNo" value="no">
                            <label for="feedbackNo">
                                <strong>No, I would modify this recommendation</strong>
                                <br>
                                <small style="color: var(--gray-500);">The suggestion needs adjustment</small>
                            </label>
                        </div>
                        
                        <div id="modifiedRecommendationField" style="display: none;">
                            <label class="form-label">Your Modified Recommendation</label>
                            <textarea name="modified_recommendation" class="feedback-textarea" rows="3" placeholder="Please provide your modified recommendation based on clinical expertise..."></textarea>
                        </div>
                        
                        <label class="form-label" style="margin-top: 1em;">Additional Clinical Notes</label>
                        <textarea name="finding_notes" class="form-textarea" rows="4" placeholder="Enter your observations, additional recommendations, and clinical notes...">Normal waveform pattern detected. No significant abnormalities found.</textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeSaveModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn-primary" onclick="saveAnalysis()">
                    <i class="fas fa-save"></i> Save Analysis
                </button>
            </div>
        </div>
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
        // Modal functions
        function openSaveModal() {
            document.getElementById('saveModal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        function closeSaveModal() {
            document.getElementById('saveModal').classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('saveModal');
            if (event.target === modal) {
                closeSaveModal();
            }
        }
        
        // Handle feedback radio buttons
        document.addEventListener('DOMContentLoaded', function() {
            const feedbackYes = document.getElementById('feedbackYes');
            const feedbackNo = document.getElementById('feedbackNo');
            const modifiedField = document.getElementById('modifiedRecommendationField');
            
            function updateModifiedField() {
                if (feedbackNo.checked) {
                    modifiedField.style.display = 'block';
                } else {
                    modifiedField.style.display = 'none';
                }
            }
            
            feedbackYes.addEventListener('change', updateModifiedField);
            feedbackNo.addEventListener('change', updateModifiedField);
        });
        
        function selectStatus(status, element) {
            document.getElementById('statusInput').value = status;
            
            document.querySelectorAll('.status-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            element.classList.add('selected');
            
            document.getElementById('anomalyField').style.display = 
                status === 'anomaly' ? 'block' : 'none';
        }

        function saveAnalysis() {
            const form = document.getElementById('saveAnalysisForm');
            const formData = new FormData(form);
            
            // Validate patient selection
            const patientSelect = document.querySelector('select[name="patient_id"]');
            if (!patientSelect.value) {
                alert('Please select a patient');
                return;
            }
            
            // Show loading state
            const saveBtn = document.querySelector('.modal-footer .btn-primary');
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

        // Handle Escape key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSaveModal();
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