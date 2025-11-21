<?php
// forgot_password.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/mail_config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function redirect_back(string $msg) {
    $_SESSION['fp_msg'] = $msg;
    header('Location: forgot_password.php');
    exit;
}

$message = $_SESSION['fp_msg'] ?? '';
unset($_SESSION['fp_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_back('Please enter a valid email address.');
    }

    try {
        // نتحقق هل فيه مستخدم بهالإيميل
        $stmt = $conn->prepare('
            SELECT userID, first_name 
            FROM healthcareprofessional 
            WHERE email = ? 
            LIMIT 1
        ');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $uid   = (int)$row['userID'];
            $name  = $row['first_name'];

            // نولّد التوكن وتاريخ الإنتهاء (ساعة مثلاً)
            $token  = bin2hex(random_bytes(32));
            $expire = date('Y-m-d H:i:s', time() + 3600); // ساعة

            $up = $conn->prepare('
                UPDATE healthcareprofessional
                SET reset_token = ?, reset_expires = ?
                WHERE userID = ?
            ');
            $up->bind_param('ssi', $token, $expire, $uid);
            $up->execute();

            // نبني رابط الريسيت
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $reset_link = "{$scheme}://{$host}{$base}/reset_password.php?token=" . urlencode($token);

            $subject = 'Reset your TANAFS password';
            $body = '
              <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6">
                <h2>Reset your password</h2>
                <p>Hello '.htmlspecialchars($name, ENT_QUOTES, "UTF-8").',</p>
                <p>We received a request to reset your TANAFS password.</p>
                <p>Click the button below to create a new password. This link will expire in 1 hour.</p>
                <p><a href="'.$reset_link.'" style="background:#0B83FE;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600">Reset Password</a></p>
                <p>If the button does not work, copy this link:</p>
                <p style="word-break:break-all">'.$reset_link.'</p>
                <p>If you did not request this, you can safely ignore this email.</p>
              </div>
            ';

            // نرسل الإيميل
            sendAppMail($email, $name, $subject, $body);
        }

        // دائماً نرجّع نفس الرسالة عشان ما نكشف إذا الإيميل موجود أو لا
        redirect_back('If this email is registered, you will receive a reset link shortly.');

    } catch (Throwable $e) {
        redirect_back('An unexpected error occurred. Please try again later.');
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg-1:#0875fa29;
      --card:#ffffff;
      --text:#1f2937;
      --muted:#6b7280;
      --primary:#0B83FE;
      --primary-pressed:#0970d7;
      --ring:#D1E6FE;
      --radius:24px;
      --maxw:640px;
      --pad:36px;
      --field-h:3.25rem;
      --field-r:12px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      color:var(--text);
      background:
        radial-gradient(1200px 800px at 15% 0%, var(--bg-1), transparent 60%),
        radial-gradient(1200px 900px at 100% 100%, #ffffff, transparent 60%),
        linear-gradient(160deg, #D1E6FE 0%, #ffffff 100%);
      min-height:100vh;
      display:flex;
      justify-content:center;
      align-items:flex-start;
      padding:clamp(100px, 12vh, 160px) 24px 80px;
    }
    .card{
      width:min(var(--maxw), 94vw);
      background:var(--card);
      border-radius:var(--radius);
      box-shadow:0 20px 50px rgba(31,41,55,.10), 0 8px 20px rgba(31,41,55,.06);
      padding:clamp(24px, 3vw, var(--pad));
      margin-top:clamp(40px, 6vh, 80px);
    }
    header{text-align:center;margin-bottom:24px;}
    .title{font-size:1.4rem;font-weight:700;margin:0 0 6px;}
    .subtitle{color:var(--muted);font-size:.95rem;margin:0;}
    label{display:block;font-size:.875rem;font-weight:600;margin:14px 4px 8px;}
    .input{
      width:100%;height:var(--field-h);padding:0 14px;
      background:#fff;border:1px solid var(--ring);border-radius:var(--field-r);
      outline:none;font-size:.95rem;transition:border-color .2s, box-shadow .2s;
    }
    .input:focus{border-color:#9FD0FF;box-shadow:0 0 0 4px #0b84fe33;}
    .btn{
      width:100%;height:56px;border-radius:12px;border:0;cursor:pointer;
      font-weight:600;font-size:1rem;background:var(--primary);color:#fff;
      transition:background .2s ease, transform .06s ease;margin-top:12px;
    }
    .btn:hover{background:var(--primary-pressed);}
    .btn:active{transform:translateY(1px);}
    .msg{
      margin:0 0 14px 0;
      padding:12px 14px;
      border-radius:12px;
      font-size:.95rem;
      background:#eff6ff;
      color:#1d4ed8;
      border:1px solid #bfdbfe;
    }
    .back-link{
      display:block;
      text-align:center;
      margin-top:12px;
      font-size:.9rem;
      color:var(--muted);
    }
    .back-link a{
      color:#0B83FE;
      text-decoration:none;
      font-weight:600;
    }
  </style>
</head>
<body>
  <form class="card" action="forgot_password.php" method="post" novalidate>
    <header style="position:relative;">
      <img src="images/logo.png" alt="TANAFS logo"
           style="position:absolute; left:-5px; top:-50px; width:100px; height:100px; object-fit:contain;">
      <h1 class="title">Forgot your password?</h1>
      <p class="subtitle">Enter your email address to reset your password.</p>
    </header>

    <?php if (!empty($message)): ?>
      <div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <label for="email">Email</label>
    <input class="input" type="email" id="email" name="email" required
           placeholder="Enter your registered email">

    <button class="btn" type="submit">Send reset link</button>

    <p class="back-link">Remembered your password? <a href="signin.php">Back to Sign in</a></p>
  </form>
</body>
</html>
