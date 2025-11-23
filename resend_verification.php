<?php
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once __DIR__ . '/mail_config.php';
$logo_path = __DIR__ . '/images/logo.png';
$logo_data = base64_encode(file_get_contents($logo_path));
$logo_src = 'data:image/png;base64,' . $logo_data;

// Define the function here instead of requiring signup.php
function send_verification_email(string $toEmail, string $toName, string $token): bool {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $verify_link = "{$scheme}://{$host}{$base}/verify_email.php?token=" . urlencode($token);

    $subject = 'Verify your TANAFS email';
    $body = '
      <div style="font-family:Inter;line-height:1.6; text-align:center">
        <h2>Confirm your email</h2>
        <img src="'.$logo_src.'" alt="TANAFS Logo" style="height:50px; width:auto;">
        <p>Hello '.htmlspecialchars($toName, ENT_QUOTES, 'UTF-8').',</p>
        <p>Thank you for registering in <strong>TANAFS</strong>.</p>
        <p>Please verify your email by clicking the button below:</p>
        <p><a href="'.$verify_link.'" style="background:#0B83FE;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600">Verify Email</a></p>
        <p>If the button doesn\'t work, copy this link:</p>
        <p style="word-break:break-all">'.$verify_link.'</p>
      </div>';

    return sendAppMail($toEmail, $toName, $subject, $body);
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (empty($_SESSION['pending_email']) || empty($_SESSION['pending_token'])) {
        throw new Exception('Session data missing.');
    }

    $success = send_verification_email(
        $_SESSION['pending_email'], 
        $_SESSION['pending_name'] ?? 'User', 
        $_SESSION['pending_token']
    );
    
    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Verification email has been resent!';
    } else {
        throw new Exception('Failed to send email.');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;