<?php
// reset_password.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$token   = $_GET['token'] ?? '';
$message = '';
$state   = 'form'; // form | error | success
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['token'] ?? '';
    $pass     = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($token === '') {
        $state   = 'error';
        $message = 'Invalid reset token.';
    }
    elseif ($pass === '' || $confirm === '') {
        $state   = 'error';
        $message = 'Please fill in both password fields.';
    }
    elseif ($pass !== $confirm) {
        $state   = 'error';
        $message = 'Passwords do not match.';
    }
    elseif (strlen($pass) < 8 || !preg_match('/[!@#$%^&*()_+\-=\[\]{};\'"\\|,.<>\/?]/', $pass)) {
        $state   = 'error';
        $message = 'Password must be at least 8 characters and contain at least one symbol.';
    }
    else {
        try {
            $stmt = $conn->prepare('SELECT userID, reset_expires FROM healthcareprofessional WHERE reset_token = ? LIMIT 1');
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $res = $stmt->get_result();

            if (!$row = $res->fetch_assoc()) {
                $state   = 'error';
                $message = 'Invalid or expired reset link.';
            } else {
                $uid     = (int)$row['userID'];
                $expires = $row['reset_expires'];

                if ($expires && strtotime($expires) < time()) {
                    $state   = 'error';
                    $message = 'This reset link has expired. Please request a new one.';
                } else {
                    $new_hash = password_hash($pass, PASSWORD_DEFAULT);

                    $up = $conn->prepare('
                        UPDATE healthcareprofessional
                        SET password = ?, reset_token = NULL, reset_expires = NULL
                        WHERE userID = ?
                    ');
                    $up->bind_param('si', $new_hash, $uid);
                    $up->execute();

                    $state   = 'success';
                    $message = 'Your password has been reset successfully. You can now sign in.';
                }
            }
        } catch (Throwable $e) {
            $state   = 'error';
            $message = 'Server error. Please try again later.';
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
      <link rel="icon" type="image/png" href="/images/fi.png">
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Reset Password</title>
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
    header{text-align:center;margin-bottom:24px;position:relative;}
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
    }
    .msg.error{
      background:#fde8e8; color:#991b1b; border:1px solid #f8c7c7;
    }
    .msg.success{
      background:#ecfdf3; color:#166534; border:1px solid #bbf7d0;
    }
    .hint{
      color:#6b7280;
      font-size:0.85rem;
      margin-top:6px;
    }
    .back-link{
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
    .input-error {
  border-color: #f97373 !important;
  box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.25);
}

.match-error {
  color: #dc2626;
  font-size: 0.9rem;
  margin-top: 4px;
}

  </style>
</head>
<body>
  <form class="card" action="reset_password.php" method="post" novalidate>
    <header>
      <img src="images/logo.png" alt="TANAFS logo"
           style="position:absolute; left:-5px; top:-50px; width:100px; height:100px; object-fit:contain;">
      <h1 class="title">Reset Password</h1>
      <p class="subtitle">Create a new password for your TANAFS account.</p>
    </header>

    <?php if (!empty($message)): ?>
      <div class="msg <?php echo $state === 'success' ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <?php if ($state === 'form'): ?>
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

  <label for="password">New Password</label>
<input class="input" id="password" name="password" type="password"
       autocomplete="new-password" required minlength="8"
       placeholder="Enter new password">
<p class="hint">
  Must be at least <strong>8 characters</strong> and include
  <strong>one symbol</strong> (e.g., !@#$%).
</p>

<label for="confirm_password">Confirm New Password</label>
<input class="input" id="confirm_password" name="confirm_password" type="password"
       autocomplete="new-password" required
       placeholder="Confirm new password">

<p id="matchError" class="match-error" style="display:none;">
  ✖ Passwords do not match
</p>


  <button class="btn" type="submit">Save new password</button>
<?php endif; ?>


    <p class="back-link">
      <a href="signin.php">Back to Sign in</a>
    </p>
  </form>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const pwd     = document.getElementById('password');
  const confirm = document.getElementById('confirm_password');
  const msg     = document.getElementById('matchError');

  function checkMatch() {
    const p  = pwd.value.trim();
    const cp = confirm.value.trim();

    if (p === '' && cp === '') {
      msg.style.display = 'none';
      pwd.classList.remove('input-error');
      confirm.classList.remove('input-error');
      return;
    }

    if (cp === '') {
      msg.style.display = 'none';
      confirm.classList.remove('input-error');
      return;
    }

    if (p !== cp) {
      msg.textContent = '✖ Passwords do not match';
      msg.style.display = 'block';
      pwd.classList.add('input-error');
      confirm.classList.add('input-error');
    } else {
      msg.style.display = 'none';
      pwd.classList.remove('input-error');
      confirm.classList.remove('input-error');
    }
  }

  pwd.addEventListener('input', checkMatch);
  confirm.addEventListener('input', checkMatch);
});
</script>


</body>
</html>
