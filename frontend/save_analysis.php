<?php
session_start();
require_once 'db_connection.php';

if (empty($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    $userID = (int)$_SESSION['user_id'];
    $waveImgId = (int)$_POST['waveImg_id'];
    $action = $_POST['action'];
    
    // Verify ownership of waveform
    $checkSql = "SELECT waveImg_id FROM waveform WHERE waveImg_id = ? AND userID = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $waveImgId, $userID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception('Unauthorized access to analysis result.');
    }
    
    if ($action === 'link_existing') {
        $patientId = (int)$_POST['patient_id'];
        
        // Verify patient belongs to user
        $patientSql = "SELECT PID FROM Patient WHERE PID = ? AND userID = ?";
        $patientStmt = $conn->prepare($patientSql);
        $patientStmt->bind_param("ii", $patientId, $userID);
        $patientStmt->execute();
        
        if ($patientStmt->get_result()->num_rows === 0) {
            throw new Exception('Patient not found or unauthorized.');
        }
        
        // Update waveform with patient ID
        $updateSql = "UPDATE waveform SET PID = ? WHERE waveImg_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $patientId, $waveImgId);
        
        if ($updateStmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Analysis linked to patient successfully.';
        } else {
            throw new Exception('Failed to link analysis to patient.');
        }
        
    } elseif ($action === 'create_new') {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
        
        if (empty($firstName) || empty($lastName)) {
            throw new Exception('First name and last name are required.');
        }
        
        // Create new patient
        $patientSql = "INSERT INTO Patient (first_name, last_name, age, gender, notes, userID) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $patientStmt = $conn->prepare($patientSql);
        $patientStmt->bind_param("ssissi", $firstName, $lastName, $age, $gender, $notes, $userID);
        
        if ($patientStmt->execute()) {
            $newPatientId = $conn->insert_id;
            
            // Update waveform with new patient ID
            $updateSql = "UPDATE waveform SET PID = ? WHERE waveImg_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ii", $newPatientId, $waveImgId);
            
            if ($updateStmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'New patient created and analysis linked successfully.';
                $response['patient_id'] = $newPatientId;
            } else {
                throw new Exception('Failed to link analysis to new patient.');
            }
        } else {
            throw new Exception('Failed to create new patient.');
        }
    } else {
        throw new Exception('Invalid action specified.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);