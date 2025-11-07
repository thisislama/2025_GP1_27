<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/db_connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function redirect_with_error(string $msg) {
    header('Location: signin.php?error=' . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        redirect_with_error('Please enter your email and password.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_error('Invalid email format.');
    }

    try {
        $sql  = "SELECT userID, password FROM healthcareprofessional WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();


        if ($stmt->num_rows !== 1) {
            $stmt->close();
            redirect_with_error('Incorrect login credentials.');
        }

        $stmt->bind_result($user_id, $password_hash);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($password, $password_hash)) {
            redirect_with_error('Incorrect login credentials.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email']   = $email;



        header('Location: dashboard.php');
        exit;

    } catch (Throwable $e) {
        redirect_with_error('An unexpected error occurred. Please try again later.');
    }
}

$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!doctype html>
<html >
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign in</title>

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

    .card header{text-align:center;margin-bottom:28px;}
    .title{font-size:clamp(1.35rem, 2.2vw, 1.6rem);font-weight:700;margin:0 0 6px;}
    .subtitle{color:var(--muted);font-size:.95rem;margin:0;}

    label{display:block;font-size:.875rem;font-weight:600;margin:14px 4px 8px;}
    .field{position:relative;}
    .input{
      width:100%;height:var(--field-h);padding:0 14px;
      background:#fff;border:1px solid var(--ring);border-radius:var(--field-r);
      outline:none;font-size:.95rem;transition:border-color .2s, box-shadow .2s;
    }
    .input:focus{border-color:#9FD0FF;box-shadow:0 0 0 4px #0b84fe33;}

    .row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:10px 2px 4px;}
    .checkbox{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:.9rem;}
    .checkbox input{width:16px;height:16px;}

    .link{color:#0B83FE;text-decoration:none;font-weight:600;font-size:.9rem;}
    .link:hover{text-decoration:underline;}

    .btn{
      width:100%;height:56px;border-radius:12px;border:0;cursor:pointer;
      font-weight:600;font-size:1rem;background:var(--primary);color:#fff;
      transition:background .2s ease, transform .06s ease;margin-top:12px;
    }
    .btn:hover{background:var(--primary-pressed);}
    .btn:active{transform:translateY(1px);}

    .footer-note{text-align:center;color:var(--muted);font-size:.95rem;margin:16px 0 0;}
    .footer-note a{color:#0B83FE;font-weight:600;text-decoration:none;}
    .footer-note a:hover{text-decoration:underline;}
    .error{
      background:#fde8e8;
      color:#991b1b;
      border:1px solid #f8c7c7;
      padding:12px 14px;
      border-radius:12px;
      margin:0 0 14px 0;
      font-size:.95rem;
    }
  </style>
</head>

<body>
  <form class="card" action="signin.php" method="post" autocomplete="on" novalidate>
    <header class="card-head" style="position: relative;">
  <img src="images/logo.png" alt="TANAFS logo" 
       style="position: absolute; left: -5px; top: -50px; width: 100px; height: 100px; object-fit: contain;">      <h1 class="title">Welcome Back!</h1>
      <p class="subtitle">Please enter your details.</p>
    </header>

    <?php if (!empty($error)): ?>
      <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <label for="email">Email</label>
    <div class="field">
      <input class="input" id="email" name="email" type="email" placeholder="Enter your email" required value="<?php echo isset($_GET['prefill_email']) ? htmlspecialchars($_GET['prefill_email'], ENT_QUOTES, 'UTF-8') : '';?>">
    </div>

    <label for="password">Password</label>
    <div class="field">
      <input class="input" id="password" name="password" type="password" placeholder="Enter password" required>
    </div>

    <button class="btn" type="submit">Sign in</button>

    <p class="footer-note">Don't have an account? <a href="signup.php">Sign up</a></p>
  </form>
</body>
</html>
