<?php
// verify_email.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$token = $_GET['token'] ?? '';
if (!$token) {
    exit('No verification token provided.');
}

try {
    $stmt = $conn->prepare('
        SELECT userID, verification_expires, is_verified 
        FROM healthcareprofessional 
        WHERE verification_token = ? 
        LIMIT 1
    ');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$row = $res->fetch_assoc()) {
        echo '❌ Invalid or expired token. <a href="signin.php">Sign in</a>';
        exit;
    }

    $uid      = (int)$row['userID'];
    $expires  = $row['verification_expires'];
    $verified = (int)$row['is_verified'];

    // لو أصلاً مفعّل
    if ($verified === 1) {
        echo '✅ Email already verified. <a href="signin.php">Sign in</a>';
        exit;
    }

    // تحقق من انتهاء الصلاحية
    if ($expires && strtotime($expires) < time()) {
        echo '❌ Verification link has expired. Please sign up again with the same email.';
        exit;
    }

    // فعّل الحساب وامسح التوكن
    $up = $conn->prepare('
        UPDATE healthcareprofessional
        SET is_verified = 1, verification_token = NULL, verification_expires = NULL
        WHERE userID = ?
    ');
    $up->bind_param('i', $uid);
    $up->execute();

    echo '✅ Email verified successfully. <a href="signin.php">Sign in</a>';

} catch (Throwable $e) {
    // error_log($e->getMessage());
    echo 'Server error.';
}
