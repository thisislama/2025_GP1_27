<?php
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once __DIR__ . '/mail_config.php';

header('Content-Type: application/json');

function send_verification_email_resend(string $toEmail, string $toName, string $token): bool
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    $verify_link = "{$scheme}://{$host}{$base}/verify_email.php?token=" . urlencode($token);

    $subject = 'Verify your TANAFS email';

    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');

    $body = '
      <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;text-align:center">
        <h2>Confirm your email</h2>
        <p>Hello '.$safeName.',</p>
        <p>Thank you for registering in <strong>TANAFS</strong>.</p>
        <p>Please verify your email by clicking the button below:</p>
        <p>
          <a href="'.$verify_link.'" 
             style="background:#0B83FE;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600">
             Verify Email
          </a>
        </p>
        <p>If the button does not work, copy this link:</p>
        <p style="word-break:break-all">'.$verify_link.'</p>
      </div>';

    return sendAppMail($toEmail, $toName, $subject, $body);
}

$response = ['success' => false, 'message' => ''];

try {
    if (empty($_SESSION['pending_email']) || empty($_SESSION['pending_token'])) {
        throw new Exception('Session data missing.');
    }

    $email = $_SESSION['pending_email'];
    $token = $_SESSION['pending_token'];
    $name  = $_SESSION['pending_name'] ?? 'User';

    $success = send_verification_email_resend($email, $name, $token);

    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Verification email has been resent!';
    } else {
        $response['message'] = 'Failed to send email.';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;