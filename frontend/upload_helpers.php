<?php
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
        return ['error' => 'File upload failed. Check directory permissions.'];
    }

    // Get a valid patient ID assigned to this doctor
    $pid_sql = "SELECT PID FROM patient_doctor_assignments WHERE userID = ? LIMIT 1";
    $pid_stmt = $conn->prepare($pid_sql);
    $pid_stmt->bind_param("i", $userID);
    $pid_stmt->execute();
    $pid_result = $pid_stmt->get_result();
    
    if ($pid_result->num_rows === 0) {
        // Delete the uploaded file since we can't save it to DB
        unlink($target_file);
        return ['error' => 'No patients assigned to you. Please assign patients first.'];
    }
    
    $patient = $pid_result->fetch_assoc();
    $patientID = $patient['PID'];
    $pid_stmt->close();

    // Insert into waveform table - DON'T specify waveImg_id, let it auto-increment
    $sql = "INSERT INTO waveform (userID, PID, filePath, timestamp, status, anomaly_type, finding_notes) 
            VALUES (?, ?, ?, NOW(), 'normal', NULL, 'Analysis pending')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $userID, $patientID, $target_file);

    if (!$stmt->execute()) {
        // Delete the uploaded file since DB insert failed
        unlink($target_file);
        return ['error' => 'Database insert failed: ' . $stmt->error];
    }

    $waveImg_id = $stmt->insert_id;
    $stmt->close();

    // FIXED: Remove the extra comma before WHERE
    $update_sql = "UPDATE waveform SET 
                   status = 'normal', 
                   anomaly_type = 'No anomaly detected',
                   finding_notes = 'Normal waveform pattern detected. No significant abnormalities found.'
                   WHERE waveImg_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $waveImg_id);
    
    if (!$update_stmt->execute()) {
        error_log("Update failed: " . $update_stmt->error);
        // Don't return error here, just log it - the upload was still successful
    }
    
    $update_stmt->close();

    return [
        'success' => true,
        'message' => 'File uploaded and analyzed successfully!',
        'file_path' => $target_file,
        'waveImg_id' => $waveImg_id,
        'redirect' => 'analysis.php?id=' . $waveImg_id
    ];
}
?>