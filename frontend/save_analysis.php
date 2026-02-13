<?php
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'db_connection.php';

$userID = (int)$_SESSION['user_id'];

// Get POST data
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$file_path = isset($_POST['file_path']) ? $_POST['file_path'] : '';
$status = isset($_POST['status']) ? $_POST['status'] : 'pending';
$anomaly_type = isset($_POST['anomaly_type']) ? $_POST['anomaly_type'] : null;
$finding_notes = isset($_POST['finding_notes']) ? $_POST['finding_notes'] : '';

// Validate
if (!$patient_id || !$file_path) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

// Check if file exists
if (!file_exists($file_path)) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Insert into waveform table
$sql = "INSERT INTO waveform (userID, PID, filePath, timestamp, status, anomaly_type, finding_notes) 
        VALUES (?, ?, ?, NOW(), ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iissss", $userID, $patient_id, $file_path, $status, $anomaly_type, $finding_notes);

if ($stmt->execute()) {
    $waveImg_id = $stmt->insert_id;
    echo json_encode([
        'success' => true, 
        'message' => 'Analysis saved successfully!',
        'waveImg_id' => $waveImg_id,
        'redirect' => 'analysis.php?id=' . $waveImg_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>