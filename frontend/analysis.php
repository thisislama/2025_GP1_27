<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

require_once 'db_connection.php';

$userID = (int)$_SESSION['user_id'];
$waveImg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$feedback_success = '';
$feedback_error = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_feedback') {
    $clinical_notes = trim($_POST['clinical_notes'] ?? '');
    $follow_up = trim($_POST['follow_up'] ?? '');
    $prescription_notes = trim($_POST['prescription_notes'] ?? '');
    $severity = $_POST['severity'] ?? 'moderate';
    
    // Combine all feedback into finding_notes with structured format
    $combined_notes = "=== CLINICAL FEEDBACK ===\n";
    $combined_notes .= "Date: " . date('Y-m-d H:i') . "\n";
    $combined_notes .= "Physician: " . $_SESSION['doctorName'] . "\n\n";
    $combined_notes .= "Severity Level: " . ucfirst($severity) . "\n\n";
    $combined_notes .= "Clinical Notes:\n" . $clinical_notes . "\n\n";
    
    if (!empty($follow_up)) {
        $combined_notes .= "Follow-up Plan:\n" . $follow_up . "\n\n";
    }
    
    if (!empty($prescription_notes)) {
        $combined_notes .= "Prescription Notes:\n" . $prescription_notes . "\n\n";
    }
    
    $combined_notes .= "=== END OF FEEDBACK ===";
    
    // Update waveform table with feedback
    $update_sql = "UPDATE waveform SET finding_notes = CONCAT(IFNULL(finding_notes, ''), ?) WHERE waveImg_id = ? AND userID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $combined_notes, $waveImg_id, $userID);
    
    if ($update_stmt->execute()) {
        $feedback_success = "Clinical feedback saved successfully!";
        // Refresh analysis data
        $sql = "SELECT w.*, p.first_name, p.last_name 
                FROM waveform w 
                JOIN Patient p ON w.PID = p.PID 
                WHERE w.waveImg_id = ? AND w.userID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $waveImg_id, $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $analysis = $result->fetch_assoc();
        $notes = $analysis['finding_notes'] ?: 'No notes available.';
    } else {
        $feedback_error = "Failed to save feedback. Please try again.";
    }
    $update_stmt->close();
} else {
    // Get analysis details
    $sql = "SELECT w.*, p.first_name, p.last_name 
            FROM waveform w 
            JOIN Patient p ON w.PID = p.PID 
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
}

// Set variables for display
$imagePath = htmlspecialchars($analysis['filePath']);
$status = $analysis['status'];
$status_class = $status === 'anomaly' ? 'anomaly' : 'normal';
$notes = $analysis['finding_notes'] ?: 'No notes available.';
$patientName = $analysis['first_name'] . ' ' . $analysis['last_name'];

// Parse existing feedback for display
$has_feedback = !empty($analysis['finding_notes']) && $analysis['finding_notes'] !== 'No notes available.';
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
            position: absolute;
            top: 15%;
            width: 80em;
            height: fit-content;
            align-items: center;
            align-content: center;
            justify-content: center;
            z-index: 100;
            padding: 5em;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            margin: 0 auto ;
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
            position: relative;
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
        
        /* Modern Status Badge */
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
        
        /* Enhanced Grid Layout */
        .analysis-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 2.5em;
            margin-bottom: 2em;
        }
        
        /* Image Container with Modern Frame */
        .image-container {
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-md);
            position: relative;
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
            transition: var(--transition);
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
            margin: .805em 0;
            padding: 1.55em;
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
        
        /* Analysis Summary Styles */
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
        
        /* Recommendation Section */
        .recommendation-section {
            grid-column: span 2;
            margin-top: 2em;
            padding: 2em;
            background:linear-gradient(-135deg, var(--primary-light), white);
            border-radius: var(--radius-lg);
            border-left: 4px solid #0a76fc;
        }
        .recommendation-section:hover {
            background: var(--primary-light);
            transform: translateX(5px);
        }

        
        .recommendation-title {
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1em;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2em;
        }
        
        .recommendation-title i {
            color: var(--warning);
        }
        
        .recommendation-content {
            color: var(--gray-700);
            line-height: 1.6;
        }
        
        /* Clinical Feedback Section */
        .feedback-section {
            grid-column: span 2;
            margin-top: 2em;
            padding: 2em;
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5em;
        }
        
        .feedback-title {
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2em;
        }
        
        .feedback-title i {
            color: var(--primary);
        }
        
        .feedback-badge {
            padding: 0.3em 1em;
            background: var(--success-light);
            color: var(--success);
            border-radius: 50px;
            font-size: 0.85em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .feedback-content {
            background: var(--gray-50);
            padding: 1.5em;
            border-radius: var(--radius-lg);
            white-space: pre-wrap;
            font-family: inherit;
            line-height: 1.6;
            color: var(--gray-700);
            border-left: 4px solid var(--primary);
            margin-bottom: 1.5em;
        }
        
        .feedback-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--gray-500);
            font-size: 0.9em;
            margin-top: 0.5em;
        }
        
        .btn-feedback {
            background: linear-gradient(135deg, var(--primary), #0056cc);
            color: white;
            padding: 0.8em 1.8em;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            border: none;
            box-shadow: 0 4px 12px rgba(10, 118, 252, 0.2);
        }
        
        .btn-feedback:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(10, 118, 252, 0.3);
        }
        
        .btn-feedback-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 0.8em 1.8em;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .btn-feedback-outline:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }
        
        /* Feedback Form */
        .feedback-form {
            display: none;
            margin-top: 1.5em;
            padding: 1.5em;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
        }
        
        .feedback-form.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        .info-title{
    font-size: 1.2em;
    font-weight: 700;
    color: var(--gray-900);
    display: flex;
    align-items: center;
    gap: 1em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
    
        }
        .info-title i{
    color: var(--primary);
    font-size: 1.4em;


        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5em;
            margin-bottom: 1.5em;
        }
        
        .form-group {
            margin-bottom: 1.2em;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.9em;
        }
        
        .form-label i {
            margin-right: 6px;
            color: var(--primary);
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.8em 1em;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.95em;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 118, 252, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        /*
        .severity-options {
            display: flex;
            gap: 1em;
            flex-wrap: wrap;
        }
        
        .severity-option {
            flex: 1;
            padding: 0.8em;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            min-width: 80px;
        }
        
        .severity-option:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .severity-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
            font-weight: 600;
        }
        
        .severity-option.mild {
            color: #2e7d32;
        }
        
        .severity-option.moderate {
            color: #ed6c02;
        }
        
        .severity-option.severe {
            color: #c62828;
        }
        */
        .form-actions {
            display: flex;
            gap: 1em;
            justify-content: flex-end;
            margin-top: 1.5em;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1em 1.5em;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5em;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }
        
        .alert-success {
            background: var(--success-light);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert-error {
            background: var(--danger-light);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Action Buttons Section */
        .action-section {
            margin-top: 3em;
            padding-top: 2em;
            border-top: 1px solid var(--gray-200);
        }
        
        .save-hint {
            color: var(--gray-500);
            font-size: 0.9em;
            margin-bottom: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .save-hint i {
            color: var(--gray-400);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1em;
            flex-wrap: wrap;
            justify-content: left;
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
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .modal {
            background: white;
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 55em;
            box-shadow: var(--shadow-xl);
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
            opacity: 1;
        }
        
        .modal-header {
            padding: 1.5em 2em;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 1.4em;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-title i {
            color: var(--primary);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0.5em;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .close-modal:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .modal-body {
            padding: 2em;
        }
        
        .modal-description {
            color: var(--gray-700);
            line-height: 1.6;
            margin-bottom: 2em;
        }
        
        /* Link Options in Modal */
        .link-options {
            display: flex;
            flex-direction: column;
            gap: 1em;
            margin-bottom: 2em;
        }
        
        .link-option {
            padding: 1.5em;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            width: 100% !important;
        }
        
        .link-option:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateX(5px);
        }
        
        .link-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .option-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2em;
            flex-shrink: 0;
        }
        
        .option-content {
            flex: 1;
        }
        
        .option-title {
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5em;
        }
        
        .option-description {
            color: var(--gray-700);
            font-size: 0.9em;
            line-height: 1.5;
        }
        
        /* Patient Selection Form */
        .patient-selection-form {
            display: none;
            margin-top: 1.5em;
        }
        
        .patient-selection-form.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .form-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1em;
            font-size: 1.1em;
        }
        
        .patient-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            margin-bottom: 1.5em;
        }
        
        .patient-item {
            padding: .755em;
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .patient-item:hover {
            background: var(--primary-light);
        }
        
        .patient-item.selected {
            background: var(--primary-light);
            border-left: 4px solid var(--primary);
        }
        
        .patient-name {
            font-weight: 600;
            color: var(--gray-900);
            width: fit-content;
        }
        
        .patient-id {
            font-size: 0.9em;
            color: var(--gray-700);
        }
        
        .no-patients {
            text-align: center;
            padding: 2em;
            color: var(--gray-500);
        }
        
        .no-patients i {
            font-size: 2em;
            margin-bottom: 0.5em;
            display: block;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1em;
            padding: auto;
            margin: .85em;
        }

        .modal-btn-primary{
            background: linear-gradient(135deg, var(--primary), #0056cc);
            color: white;
            box-shadow: 0 4px 15px rgba(10, 118, 252, 0.3);
            padding: .85em 2.75em;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: .785em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 1.2em;
            transition: var(--transition);
            border: none;
            margin: 0 auto;
        }

        .modal-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(10, 118, 252, 0.4);
        }

        .modal-btn-outline {
            background: transparent;
            border: 2px solid var(--gray-300);
            color: var(--gray-700);
            padding: .85em 2.75em;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: .785em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 1.2em;
            transition: var(--transition);
            margin: 0 auto;
        }
        
        .modal-btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(10, 118, 252, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(10, 118, 252, 0); }
            100% { box-shadow: 0 0 0 0 rgba(10, 118, 252, 0); }
        }
        
        .status-badge.anomaly {
            animation: pulse 2s infinite;
        }
        
        /* Responsive Design */
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary, .btn-outline {
                width: 100%;
            }
            
            .modal {
                width: 95%;
                margin: 1em;
            }
            
            .modal-header, .modal-body {
                padding: 1.5em;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .severity-options {
                flex-direction: column;
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
                    <span class="status-badge <?= $status_class ?>">
                        <i class="fas fa-<?= $status === 'anomaly' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                        <?= ucfirst($status) ?>
                    </span>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($feedback_success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($feedback_success) ?></span>
                    </div>
                    
                <?php endif; ?>
                
                <?php if (!empty($feedback_error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($feedback_error) ?></span>
                    </div>
                <?php endif; ?>

                <div class="analysis-grid">
                    <!-- LEFT: IMAGE -->
                    <div>
                        <div class="image-label">
                            <i class="fas fa-wave-square"></i> Waveform Visualization
                        </div>
                        <div class="image-container">
                            <img src="<?= $imagePath ?>"
                                 alt="Waveform Analysis">
                        </div>
                    </div>

                    <!-- RIGHT: DETAILS -->
                    <div>
                        <!-- Combined Info Item -->
                        <div class="combined-info-item">
                            <div class="info-title"style="margin-top: 1.5em;"> <i class="fas fa-info-circle"></i> Analysis Summary </div>                            
                            <p> This analysis indicates that the patient's waveform data has been processed to identify any potential anomalies. 
                                The results are based on the latest machine learning algorithms trained on a diverse dataset of cardiac waveforms. </p><br>
                               
                                <div class="info-label" style="margin-top: 1.5em;"><i class="fas fa-exclamation-circle"></i> Anomaly Type </div>
                                <p><?= htmlspecialchars($analysis['anomaly_type'] ?: 'None detected') ?></p>
                            
                            <div class="info-label" style="margin-top: 1.5em;">
                                <i class="fas fa-clock"></i> Analysis Time
                            </div>
                            <p><?= date('Y-m-d H:i', strtotime($analysis['timestamp'])) ?></p>
                        </div>
                    </div>
                </div> 

                <!-- Recommendation Section (Conditional) -->
                <?php if ($status === 'anomaly' && !empty($analysis['anomaly_type'])): ?>
                <div class="recommendation-section">
                    <div class="recommendation-title">
                        <i class="fas fa-stethoscope"></i> Medical Recommendation
                    </div>
                    <div class="recommendation-content">
                        <?php if ($analysis['anomaly_type'] === 'Arrhythmia'): ?>
                        <p>Consider referring the patient for cardiac evaluation. An ECG may provide additional diagnostic information.</p>
                        <?php elseif ($analysis['anomaly_type'] === 'Bradycardia'): ?>
                        <p>Monitor patient for symptoms of low heart rate. Consider evaluation for potential underlying causes.</p>
                        <?php elseif ($analysis['anomaly_type'] != 'normal'): ?>
                        <p>Advise patient to avoid stimulants. Consider follow-up monitoring and potential cardiology referral.</p>
                        <?php else: ?>
                        <p>Based on the detected anomaly, consider additional diagnostic tests or specialist consultation as clinically indicated.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif ($status === 'normal'): ?>
                <div class="recommendation-section" >
                    <div class="recommendation-title">
                        <i class="fas fa-check-circle" style="color: var(--primary);"></i> Follow-up Recommendation
                    </div>
                    <div class="recommendation-content">
                        <p>No significant anomalies detected. Routine follow-up as per standard care protocol is recommended.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Clinical Feedback Section -->
                <div class="feedback-section">
                    <div class="feedback-header">
                        <div class="feedback-title">
                            <i class="fas fa-notes-medical"></i> Clinical Notes & Feedback
                        </div>
                        <?php if (!empty($feedback_success)): ?>
                            <span class="feedback-badge">
                                <i class="fas fa-check-circle"></i> Feedback Added
                            </span>
                            <?php elseif (($has_feedback===NULL)): ?>
                            <span class="feedback-badge" style="background: var(--danger-light); color: var(--danger); border-color: var(--danger);">
                                <i class="fas fa-exclamation-circle"></i> No Feedback Added
                            </span>    
                        <?php endif; ?>
                    </div>
                    
                    <!-- Display Existing Feedback 
                    <?php if ($has_feedback): ?>
                        <div class="feedback-content">
                            <?= nl2br(htmlspecialchars($notes)) ?>
                        </div>
                        <div class="feedback-meta">
                            <span><i class="fas fa-user-md"></i> Added by: <?= $_SESSION['doctorName'] ?></span>
                            <span><i class="fas fa-calendar"></i> Last updated: <?= date('Y-m-d H:i') ?></span>
                        </div>
                    <?php endif; ?>-->
                    
                    <!-- Add/Edit Feedback Button -->
                    <div style="display: flex; gap: 1em; margin-top: <?= $has_feedback ? '1.5em' : '0' ?>;">
                        <button class="btn-feedback" onclick="toggleFeedbackForm()">
                            <i class="fas fa-<?= $has_feedback ? 'edit' : 'plus-circle' ?>"></i>
                            <?= $has_feedback ? 'Add Additional Notes' : 'Add Clinical Feedback' ?>
                        </button>
                        <?php if (!$has_feedback): ?>
                            <button class="btn-feedback-outline" onclick="window.location.href='dashboard.php'">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Feedback Form -->
                    <form id="feedbackForm" method="POST" action="" class="feedback-form">
                        <input type="hidden" name="action" value="save_feedback">
                        
                        <div class="form-row">
                            <!--<div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-exclamation-triangle"></i> Severity Level
                                </label>
                                <div class="severity-options">
                                    <div class="severity-option mild" onclick="selectSeverity('mild', this)">
                                        <i class="fas fa-thermometer-quarter"></i> Mild
                                    </div>
                                    <div class="severity-option moderate selected" onclick="selectSeverity('moderate', this)">
                                        <i class="fas fa-thermometer-half"></i> Moderate
                                    </div>
                                    <div class="severity-option severe" onclick="selectSeverity('severe', this)">
                                        <i class="fas fa-thermometer-full"></i> Severe
                                    </div>
                                </div>
                                <input type="hidden" name="severity" id="severityInput" value="moderate">
                            </div>-->
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-stethoscope"></i> Clinical Notes
                            </label>
                            <textarea class="form-textarea" name="clinical_notes" placeholder="Enter your clinical observations, diagnosis, and recommendations..." ><?= isset($_POST['clinical_notes']) ? htmlspecialchars($_POST['clinical_notes']) : '' ?></textarea>
                        </div>
                        
                         <!--<div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-check"></i> Follow-up Plan
                            </label>
                            <textarea class="form-textarea" name="follow_up" placeholder="Specify follow-up schedule and monitoring requirements..."><?= isset($_POST['follow_up']) ? htmlspecialchars($_POST['follow_up']) : '' ?></textarea>
                        </div>
                        
                       <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-prescription"></i> Prescription Notes
                            </label>
                            <textarea class="form-textarea" name="prescription_notes" placeholder="Enter medication details, dosage, and instructions..."><?= isset($_POST['prescription_notes']) ? htmlspecialchars($_POST['prescription_notes']) : '' ?></textarea>
                        </div>-->
                        
                        <div class="form-actions">
                            <button type="button" class="btn-outline" onclick="toggleFeedbackForm()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Feedback
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Action Section -->
                <div class="action-section">
                    <div class="save-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>Link this result to a patient for better record keeping</span>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn-outline" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </button>
                        <button class="btn-secondary" id="saveRecordBtnMain">
                            <i class="fas fa-save"></i> Save Record
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Save Record Modal -->
    <div class="modal-overlay" id="saveModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-save"></i> Save Analysis Record
                </div>
                <button class="close-modal" id="closeModalBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="link-options">
                    <!-- Option 1: Link to Existing Patient -->
                    <div class="link-option" id="existingPatientOption">
                        <div class="option-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="option-content">
                            <div class="option-title">Link to Existing Patient</div>
                            <div class="option-description">
                                Select from your existing patients to link this analysis result.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-description">
                    Here you can choose to link this analysis result to an existing patient in your records. This will 
                    help you keep track of the patient's history and easily access this analysis in the future.
                </div>
                
                

                <!-- Existing Patient Selection -->
                <div class="patient-selection-form" id="existing-patient-form">
                    <div class="form-title">Select Patient</div>
                    <?php
                    // Fixed SQL query - removed extra parameters
                    $patient_sql = "
                        SELECT p.PID, p.first_name, p.last_name,
                        CASE WHEN pda.userID IS NULL THEN 0 ELSE 1 END AS linked_to_me
                        FROM patient p
                        LEFT JOIN patient_doctor_assignments pda
                            ON pda.PID = p.PID AND pda.userID = ?
                        ORDER BY p.first_name, p.last_name";
                    
                    $patient_stmt = $conn->prepare($patient_sql);
                    $patient_stmt->bind_param("i", $userID);
                    $patient_stmt->execute();
                    $patients_result = $patient_stmt->get_result();
                    ?>
                    
                    <?php if ($patients_result->num_rows > 0): ?>
                        <div class="patient-list">
                            <?php while ($patient = $patients_result->fetch_assoc()): ?>
                                <div class="patient-item" data-patient-id="<?= $patient['PID'] ?>">
                                    <div class="patient-name">
                                        <?php echo "ID: P-" . $patient['PID']; ?>
                                        <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                                        <?php if ($patient['linked_to_me']): ?>
                                            <span style="color: var(--primary); font-size: 0.8em; margin-left: 0.5em;">
                                                <i class="fas fa-link"></i> Linked
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="patient-id">ID: P-<?= $patient['PID'] ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-patients">
                            <i class="fas fa-users"></i>
                            <p>No patients found in the system.</p>
                        </div>
                    <?php endif; ?>
                    <?php $patient_stmt->close(); ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="modal-btn modal-btn-outline" id="cancelModalBtn">
                    Cancel
                </button>
                <button class="modal-btn modal-btn-primary" id="saveRecordModalBtn" disabled>
                    <i class="fas fa-save"></i> Save Record
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
        // Define all functions at the TOP to ensure they're available
        function openSaveModal() {
            console.log('openSaveModal called');
            const modal = document.getElementById('saveModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                console.log('Modal opened');
            } else {
                console.error('Modal element not found');
            }
        }

        function closeSaveModal() {
            const modal = document.getElementById('saveModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
                resetModal();
            }
        }

        function resetModal() {
            modalState.selectedOption = 'existing';
            modalState.selectedPatientId = null;
            
            const patientItems = document.querySelectorAll('.patient-item');
            patientItems.forEach(item => {
                item.classList.remove('selected');
            });
            
            const saveBtn = document.getElementById('saveRecordModalBtn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Record';
            }
        }

        function selectPatient(element, patientId) {
            const patientItems = document.querySelectorAll('.patient-item');
            patientItems.forEach(item => {
                item.classList.remove('selected');
            });
            
            element.classList.add('selected');
            modalState.selectedPatientId = patientId;
            
            updateSaveButton();
        }

        function updateSaveButton() {
            const saveBtn = document.getElementById('saveRecordModalBtn');
            if (!saveBtn) return;
            
            const isValid = modalState.selectedPatientId !== null;
            
            if (isValid) {
                saveBtn.disabled = false;
                saveBtn.style.opacity = '1';
                saveBtn.style.cursor = 'pointer';
            } else {
                saveBtn.disabled = true;
                saveBtn.style.opacity = '0.6';
                saveBtn.style.cursor = 'not-allowed';
            }
        }

        async function saveRecord() {
            if (!modalState.selectedPatientId) {
                showErrorMessage('Please select a patient');
                return;
            }

            const saveBtn = document.getElementById('saveRecordModalBtn');
            if (!saveBtn) return;

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                let formData = new FormData();
                formData.append('waveImg_id', modalState.waveImgId);
                formData.append('action', 'link_existing');
                formData.append('userID', modalState.userId);
                formData.append('patient_id', modalState.selectedPatientId);

                const response = await fetch('save_analysis.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showSuccessMessage('Result linked to patient successfully!');
                    
                    setTimeout(() => {
                        window.location.href = 'history2.php';
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            } catch (error) {
                console.error('Error:', error);
                showErrorMessage('Error: ' + error.message);
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Record';
                saveBtn.disabled = false;
            }
        }

        // Feedback Form Functions
        function toggleFeedbackForm() {
            const form = document.getElementById('feedbackForm');
            form.classList.toggle('show');
            
            // Scroll to form
            if (form.classList.contains('show')) {
                setTimeout(() => {
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        }

        function selectSeverity(severity, element) {
            // Update hidden input
            document.getElementById('severityInput').value = severity;
            
            // Remove selected class from all options
            const options = document.querySelectorAll('.severity-option');
            options.forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
        }

        function showSuccessMessage(message) {
            const successDiv = document.createElement('div');
            successDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #2e7d32;
                color: white;
                padding: 1em 2em;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                z-index: 2000;
                display: flex;
                align-items: center;
                gap: 10px;
                animation: slideIn 0.3s ease;
            `;
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            
            successDiv.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                successDiv.remove();
                style.remove();
            }, 3000);
        }

        function showErrorMessage(message) {
            const errorDiv = document.createElement('div');
            errorDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #c62828;
                color: white;
                padding: 1em 2em;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                z-index: 2000;
                display: flex;
                align-items: center;
                gap: 10px;
                animation: slideIn 0.3s ease;
            `;
            
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(errorDiv);
            
            setTimeout(() => {
                errorDiv.remove();
            }, 3000);
        }

        // Define modal state
        const modalState = {
            selectedOption: 'existing',
            selectedPatientId: null,
            waveImgId: <?= $waveImg_id ?>,
            userId: <?= $userID ?>
        };

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            
            initEventListeners();
            
            document.getElementById('existing-patient-form').classList.add('show');
            
            const statusBadge = document.querySelector('.status-badge');
            if (statusBadge) {
                setTimeout(() => {
                    statusBadge.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        statusBadge.style.transform = 'scale(1)';
                    }, 300);
                }, 500);
            }
            
            // Show feedback form if there are validation errors
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_feedback' && !empty($feedback_error)): ?>
            document.getElementById('feedbackForm').classList.add('show');
            <?php endif; ?>
        });

        function initEventListeners() {
            console.log('Setting up event listeners...');
            
            const saveBtnMain = document.getElementById('saveRecordBtnMain');
            if (saveBtnMain) {
                console.log('Save button found, adding listener');
                saveBtnMain.addEventListener('click', openSaveModal);
            } else {
                console.error('Save button element not found!');
            }
            
            const closeModalBtn = document.getElementById('closeModalBtn');
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', closeSaveModal);
            }
            
            const cancelModalBtn = document.getElementById('cancelModalBtn');
            if (cancelModalBtn) {
                cancelModalBtn.addEventListener('click', closeSaveModal);
            }
            
            const patientItems = document.querySelectorAll('.patient-item');
            patientItems.forEach(item => {
                item.addEventListener('click', function() {
                    const patientId = this.getAttribute('data-patient-id');
                    selectPatient(this, patientId);
                });
            });
            
            const saveRecordModalBtn = document.getElementById('saveRecordModalBtn');
            if (saveRecordModalBtn) {
                saveRecordModalBtn.addEventListener('click', saveRecord);
            }
            
            const modalOverlay = document.getElementById('saveModal');
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeSaveModal();
                    }
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
                    closeSaveModal();
                }
            });
            
            console.log('Event listeners setup complete');
        }
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>