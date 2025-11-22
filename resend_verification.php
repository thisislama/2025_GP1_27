<?php
session_start();

require_once __DIR__ . '/signup.php';  

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (empty($_SESSION['pending_email']) || empty($_SESSION['pending_token'])) {
        throw new Exception('Session data missing. Please try signing up again.');
    }

    $pending_email = $_SESSION['pending_email'];
    $pending_name = $_SESSION['pending_name'] ?? 'User';
    $pending_token = $_SESSION['pending_token'];

    $success = send_verification_email($pending_email, $pending_name, $pending_token);
    
    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Verification email has been resent! Please check your inbox.';
    } else {
        throw new Exception('Failed to send email. Please try again.');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>