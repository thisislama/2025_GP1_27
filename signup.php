<?php
// signup.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db_connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function redirect_with_error(string $msg) {
    $form_data = $_POST;

    if (stripos($msg, 'email') !== false) {
        unset($form_data['email']);
    } elseif (stripos($msg, 'phone') !== false) {
        unset($form_data['phone']);
    } elseif (stripos($msg, 'password') !== false) {
        unset($form_data['password']);
    } elseif (stripos($msg, 'role') !== false) {
        unset($form_data['role']);
    } elseif (stripos($msg, 'birth') !== false || stripos($msg, 'age') !== false) {
        unset($form_data['dob']);
    }

    $_SESSION['form_data'] = $form_data;
    $_SESSION['error'] = $msg;

    header('Location: signup.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $role       = trim($_POST['role']       ?? '');
    $email      = trim($_POST['email']      ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $password   = $_POST['password']        ?? '';
    $dob        = trim($_POST['dob']        ?? '');


    // required fields
    if ($first_name === '' || $last_name === '' || $role === '' || $email === '' || $password === '' || $dob === '') {
        redirect_with_error('Please fill in all required fields.');
    }
    // allowed roles
    $allowed_roles = ['ICU nurse', 'Respiratory therapist', 'Intensivists', 'Pulmonologist'];
    if (!in_array($role, $allowed_roles, true)) {
        redirect_with_error('Invalid role selected.');
    }
    // email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_error('Invalid email format.');
    }
    // password length
    if (mb_strlen($password) < 8) {
        redirect_with_error('Password must be at least 8 characters.');
    }
        // phone format (+9665xxxxxxxx OR 05xxxxxxxx)
    if (!preg_match('/^(?:\+9665\d{8}|05\d{8})$/', $phone)) {
        redirect_with_error('Invalid phone number format.');
    }
        // DOB format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    redirect_with_error('Invalid date of birth format (YYYY-MM-DD).');
}

 // valid calendar date + age >= 20
    $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
    // تأكد أن التاريخ صالح فعلاً (مثل 2024-02-30 غير صالح)
    if (!$dob_date || $dob_date->format('Y-m-d') !== $dob) {
        redirect_with_error('Invalid date of birth.');
    }
    $today = new DateTime();
    $age = $today->diff($dob_date)->y;
    if ($age < 20) {
        redirect_with_error('You must be at least 20 years old to register.');
    }

    try {
        $check = $conn->prepare('SELECT userID FROM healthcareprofessional WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $check->close();
            redirect_with_error('This email is already registered.');
        }
        $check->close();
         $checkPhone = $conn->prepare('SELECT userID FROM healthcareprofessional WHERE phone = ? LIMIT 1');
         $checkPhone->bind_param('s', $phone);
         $checkPhone->execute();
        $checkPhone->store_result();
         if ($checkPhone->num_rows > 0) {
            $checkPhone->close();
            redirect_with_error('This phone number is already registered.');
         }
         $checkPhone->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare('
            INSERT INTO healthcareprofessional (first_name, last_name, role, email, phone, password, DOB)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('sssssss', $first_name, $last_name, $role, $email, $phone, $hashed_password, $dob);
        $stmt->execute();
        $new_user_id = $stmt->insert_id;
        $stmt->close();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['email']   = $email;
        $_SESSION['role']    = $role;
        $_SESSION['name']    = $first_name . ' ' . $last_name;

        header('Location: dashboard.php');
        exit;

    } catch (Throwable $e) {
        redirect_with_error('An unexpected error occurred. Please try again later.');
    }
}

$error = $_SESSION['error'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];

unset($_SESSION['error'], $_SESSION['form_data']);

$old_first = htmlspecialchars($form_data['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
$old_last  = htmlspecialchars($form_data['last_name']  ?? '', ENT_QUOTES, 'UTF-8');
$old_role  = htmlspecialchars($form_data['role']       ?? '', ENT_QUOTES, 'UTF-8');
$old_email = htmlspecialchars($form_data['email']      ?? '', ENT_QUOTES, 'UTF-8');
$old_phone = htmlspecialchars($form_data['phone']      ?? '', ENT_QUOTES, 'UTF-8');
$old_dob   = htmlspecialchars($form_data['dob']        ?? '', ENT_QUOTES, 'UTF-8');

?>

<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign Up</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-1:#0875fa29; --card:#ffffff; --text:#1f2937; --muted:#6b7280;
      --primary:#0B83FE; --primary-pressed:#0970d7; --ring:#D1E6FE; --radius:24px;
      --field-h:3.25rem; --field-r:12px; --gap:16px; --pad:36px; --maxw:800px;
    }
    *{box-sizing:border-box} html,body{height:100%}
    body{
      margin:0; font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      color:var(--text);
      background:
        radial-gradient(1200px 800px at 10% -10%, var(--bg-1), transparent 60%),
        radial-gradient(1200px 900px at 100% 100%, #ffffff, transparent 60%),
        linear-gradient(160deg, #D1E6FE 0%, #ffffff 100%);
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      min-height:100vh; padding:clamp(60px, 8vh, 100px) 24px 60px;
    }
    .wrap{ width:min(var(--maxw), 95vw); margin-inline:auto; margin-top:clamp(40px, 6vh, 80px); margin-bottom:clamp(40px, 5vh, 80px); }
    .card{ background:var(--card); border-radius:var(--radius); box-shadow:0 20px 50px rgba(31,41,55,.10),0 8px 20px rgba(31,41,55,.06); padding:clamp(24px, 3vw, var(--pad)); }
    header.card-head{text-align:center;margin-bottom:24px;}
    .title{ font-size:clamp(1.4rem, 2.5vw, 1.7rem); font-weight:700; margin:0 0 6px; }
    .subtitle{ color:var(--muted); font-size:.95rem; margin:0; }
    form{width:100%}
    .grid{ display:grid; grid-template-columns:1fr 1fr; gap:var(--gap); margin-top:12px; }
    .grid-full{ display:grid; grid-template-columns:1fr; gap:var(--gap); margin-top:12px; }
    @media (max-width:640px){ .grid{grid-template-columns:1fr;} .wrap{width:100%;} }
    label{ display:block; font-size:.875rem; font-weight:600; margin:6px 2px 6px; }
    .field{position:relative;}
    .input, select.input{
      width:100%; height:var(--field-h); padding:0 14px; background:#fff; border:1px solid var(--ring);
      border-radius:var(--field-r); outline:none; font-size:0.95rem; transition:border-color .2s, box-shadow .2s;
    }
    .input:focus{ border-color:#9FD0FF; box-shadow:0 0 0 4px #0b84fe33; }
    .actions{ margin-top:24px; display:grid; gap:10px; }
    .btn{ width:100%; height:3.5rem; border-radius:12px; border:0; cursor:pointer; font-weight:600; font-size:1rem; transition:transform .06s ease, background .2s ease, box-shadow .2s ease; }
    .btn.primary{ background:var(--primary); color:#fff; }
    .btn.primary:hover{ background:var(--primary-pressed); }
    .btn:active{ transform:translateY(1px); }
    .footer-note{ text-align:center; color:var(--muted); font-size:.95rem; margin:8px 0 0; }
    .footer-note a{ color:var(--primary); font-weight:600; text-decoration:none; }
    .footer-note a:hover{text-decoration:underline;}
    .error{
      background:#fde8e8; color:#991b1b; border:1px solid #f8c7c7;
      padding:12px 14px; border-radius:12px; margin:0 0 14px 0; font-size:.95rem;
    }
  </style>
</head>

<body>
  <div class="wrap">
    <form class="card" action="signup.php" method="post" novalidate>
      <header class="card-head">
        <h1 class="title">Create Account</h1>
        <p class="subtitle">Please fill in your information to sign up.</p>
      </header>

      <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
<div class="grid">
  <div>
    <label for="first_name">First Name</label>
    <div class="field">
      <input class="input" id="first_name" name="first_name" type="text"
             placeholder="Enter your first name" required
             value="<?php echo $old_first; ?>">
    </div>
  </div>
  <div>
    <label for="last_name">Last Name</label>
    <div class="field">
      <input class="input" id="last_name" name="last_name" type="text"
             placeholder="Enter your last name" required
             value="<?php echo $old_last; ?>">
    </div>
  </div>
</div>

<div class="grid-full">
  <div>
    <label for="role">Role</label>
    <div class="field">
      <select class="input" id="role" name="role" required>
        <option value="">Select your role</option>
        <option value="ICU nurse" <?php if ($old_role==='ICU nurse') echo 'selected'; ?>>ICU nurse</option>
        <option value="Respiratory therapist" <?php if ($old_role==='Respiratory therapist') echo 'selected'; ?>>Respiratory therapist</option>
        <option value="Intensivists" <?php if ($old_role==='Intensivists') echo 'selected'; ?>>Intensivists</option>
        <option value="Pulmonologist" <?php if ($old_role==='Pulmonologist') echo 'selected'; ?>>Pulmonologist</option>
      </select>
    </div>
  </div>
</div>

<div class="grid">
  <div>
    <label for="email">Email</label>
    <div class="field">
      <input class="input" id="email" name="email" type="email"
             placeholder="Enter your email" required
             value="<?php echo $old_email; ?>">
    </div>
  </div>
  <div>
    <label for="phone">Phone Number</label>
    <div class="field">
      <input class="input" id="phone" name="phone" type="tel"
             placeholder="+966 5xxxxxxxx" required
             value="<?php echo $old_phone; ?>">
    </div>
  </div>
</div>

<div class="grid">
  <div>
    <label for="password">Password</label>
    <div class="field">
      <input class="input" id="password" name="password" type="password"
             placeholder="Enter password" required minlength="8">
      <!-- ملاحظة: لا نعيد عرض كلمة المرور حفاظًا على الأمان -->
    </div>
  </div>
  <div>
    <label for="dob">Date of Birth</label>
    <div class="field">
      <input class="input" id="dob" name="dob" type="date" required
             value="<?php echo $old_dob; ?>">
    </div>
  </div>
</div>


      <div class="actions">
        <button class="btn primary" type="submit">Sign Up</button>
        <p class="footer-note">Already have an account? <a href="signin.php">Sign in</a></p>
      </div>
    </form>
  </div>
</body>
</html>
