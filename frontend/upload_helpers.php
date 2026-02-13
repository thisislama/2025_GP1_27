<?php
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please sign in.']);
    exit;
}

require_once 'db_connection.php';

$userID = (int)$_SESSION['user_id'];

// Check if file was uploaded
if (!isset($_FILES['waveform_file'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

function handleFileUpload($conn, $userID)
{
    $target_dir = "uploads/";

    // Create uploads directory if it doesn't exist
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            return ['error' => 'Failed to create upload directory'];
        }
    }

    // Check if directory is writable
    if (!is_writable($target_dir)) {
        return ['error' => 'Upload directory is not writable'];
    }

    $file = $_FILES['waveform_file'];
    $filename = basename($file["name"]);
    
    // Sanitize filename
    $filename = preg_replace("/[^a-zA-Z0-9.]/", "_", $filename);
    $target_file = $target_dir . time() . "_" . $filename;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Allowed file types
    $allowed_types = ['png', 'jpg', 'jpeg'];
    if (!in_array($fileType, $allowed_types)) {
        return ['error' => 'Only PNG, JPG, JPEG files are allowed. Your file: ' . $fileType];
    }

    // Max size 10MB
    $maxSize = 10 * 1024 * 1024; // 10MB in bytes
    if ($file["size"] > $maxSize) {
        return ['error' => 'File too large (max 10MB). Your file: ' . round($file["size"] / 1024 / 1024, 2) . 'MB'];
    }

    // Upload file
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        $error = error_get_last();
        return ['error' => 'File upload failed. Error: ' . ($error['message'] ?? 'Unknown error')];
    }

    // FIXED: For foreign key constraint, we need a valid PID
    // OPTION A: Create a temporary patient for pending uploads (if you want)
    // OPTION B: Use the first patient assigned to doctor (temporary)
    
    // Let's use the first patient assigned to this doctor as a temporary holder
    $pid_sql = "SELECT PID FROM patient_doctor_assignments WHERE userID = ? LIMIT 1";
    $pid_stmt = $conn->prepare($pid_sql);
    $pid_stmt->bind_param("i", $userID);
    $pid_stmt->execute();
    $pid_result = $pid_stmt->get_result();
    
    if ($pid_result->num_rows === 0) {
        // No patients assigned - we can't proceed
        unlink($target_file);
        return ['error' => 'No patients assigned to you. Please assign a patient first.'];
    }
    
    $patient = $pid_result->fetch_assoc();
    $temp_patient_id = $patient['PID']; // Use first patient as temporary holder
    $pid_stmt->close();

    // Insert into waveform table with a temporary patient ID
    // We'll update this later when doctor chooses the actual patient
    $sql = "INSERT INTO waveform (userID, PID, filePath, timestamp, status, anomaly_type, finding_notes) 
            VALUES (?, ?, ?, NOW(), 'normal', NULL, 'Awaiting patient assignment')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $userID, $temp_patient_id, $target_file);

    if (!$stmt->execute()) {
        // Delete the uploaded file since DB insert failed
        unlink($target_file);
        return ['error' => 'Database insert failed: ' . $stmt->error];
    }

    $waveImg_id = $stmt->insert_id;
    $stmt->close();

    return [
        'success' => true,
        'message' => 'File uploaded successfully! Please assign to a patient.',
        'file_path' => $target_file,
        'waveImg_id' => $waveImg_id,
        'redirect' => 'analysis.php?id=' . $waveImg_id
    ];
}

// Process the upload
$result = handleFileUpload($conn, $userID);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($result);

if (isset($conn)) {
    $conn->close();
}
?>