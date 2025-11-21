<?php
session_start();

$pending_email = $_SESSION['pending_email'] ?? null;
unset($_SESSION['pending_email']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Email Verification</title>

<style>
body{
  font-family: Arial, sans-serif;
  background:#f5f7fb;
  margin:0;
  display:flex;
  justify-content:center;
  align-items:center;
  height:100vh;
}
.box{
  background:white;
  padding:30px 40px;
  border-radius:12px;
  box-shadow:0 5px 20px rgba(0,0,0,.1);
  text-align:center;
  max-width:420px;
}
h2{margin-top:0;color:#0B83FE}
p{color:#444;font-size:1rem;margin:15px 0}
.email{
  color:#0B83FE;
  font-weight:700;
}
.btn{
  display:inline-block;
  padding:10px 18px;
  background:#0B83FE;
  color:white;
  border-radius:8px;
  text-decoration:none;
  margin-top:20px;
}
</style>
</head>

<body>

<div class="box">
  <h2>Verification Email Sent</h2>

  <?php if ($pending_email): ?>
      <p>
      A verification email has been sent to:
      <br>
      <span class="email"><?php echo htmlspecialchars($pending_email); ?></span>
      </p>
  <?php else: ?>
      <p>Please check your email to verify the account.</p>
  <?php endif; ?>

  <p>Click the verification link inside your email to activate your account.</p>

  <a href="signin.php" class="btn">Back to Sign in</a>
</div>

</body>
</html>
