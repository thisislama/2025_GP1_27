<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendAppMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        $mail->Username   = 'tanafsg27@gmail.com';

        // حطي هنا App Password من Gmail، مو كلمة مرور الإيميل العادية
        $mail->Password   = 'dftbhsbtkxkqyubg';    

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $mail->setFrom('tanafsg27@gmail.com', 'TANAFS Support');
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}