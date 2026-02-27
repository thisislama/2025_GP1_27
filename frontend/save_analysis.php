<?php
session_start();
header('Content-Type: application/json');

try {
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    require_once 'db_connection.php';

    $userID = (int)$_SESSION['user_id'];

    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $file_path = isset($_POST['file_path']) ? $_POST['file_path'] : '';
    $anomaly_type = isset($_POST['anomaly_type']) ? $_POST['anomaly_type'] : null;
    $finding_notes = isset($_POST['finding_notes']) ? $_POST['finding_notes'] : '';

    if (!$patient_id || !$file_path) {
        throw new Exception('Missing required data');
    }

    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }

    // Set status based on anomaly_type
    $status = (strtolower($anomaly_type) === 'normal flow' || strtolower($anomaly_type) === 'normal volume' || strtolower($anomaly_type) === 'normal pressure') ? 'normal' : 'abnormal';

    $sql = "INSERT INTO waveform (userID, PID, filePath, timestamp, status, anomaly_type, finding_notes) 
            VALUES (?, ?, ?, NOW(), ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
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
        throw new Exception('Failed to save: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
