<?php
session_start();

require_once __DIR__ . '/mail_config.php';  

$pending_email = $_SESSION['pending_email'] ?? null;
$pending_token = $_SESSION['pending_token'] ?? null;

// Handle resend email request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_email'])) {
    if ($pending_email && $pending_token) {
        $success = send_verification_email($pending_email, $pending_name, $pending_token);
        if ($success) {
            $resend_message = "Verification email has been resent!";
            $resend_status = "success";
        } else {
            $resend_message = "Failed to resend email. Please try again.";
            $resend_status = "error";
        }
    } else {
        $resend_message = "Unable to resend email. Session data missing.";
        $resend_status = "error";
    }
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Verify Your Email - TANAFS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

<style>
  * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
  }

  body {
      font-family: 'Inter', Arial, sans-serif;
      background: linear-gradient(135deg, #f5f7fb 0%, #e8eef7 100%);
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 2em;
          background: linear-gradient(135deg, #e4edffff, #81adff56);

  }

  .container {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100vh;
  }

  .verification-card {
      background: white;
      padding: .8em;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.28);
      text-align: center;
      width: 140vh;
      border: 1px solid rgba(11, 131, 254, 0.1);
      background-color: rgba(255, 255, 255, 0.73); 
  }

  .logo {
      margin-bottom: .9em; 
      text-align:right;
  }

  .logo img {
      height: 4.5em;
      width: auto;
    }

  .verification-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      color: #0B83FE;
      font-size: 36px;
  }

  h2 {
      color: #1a365d;
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 16px;
  }

  .subtitle {
      color: #4a5568;
      font-size: 16px;
      font-weight: 400;
      margin-bottom: 24px;
      line-height: 1.5;
  }

  .email-highlight {
      background: #f0f7ff;
      padding: 16px;
      border-radius: 8px;
      border-left: 4px solid #0B83FE;
      margin: 20px 0;
  }

  .email-address {
      color: #0B83FE;
      font-weight: 600;
      font-size: 16px;
      word-break: break-all;
  }

  .instructions {
      background: #e6f1fc71;
      padding: 16px;
      border-radius: 8px;
      margin: 0 auto;
      text-align: left;
      width:100vh; 

  }

  .instructions h4 {
      color: #2d3748;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
  }

  .instructions ul {
      color: #6e798bff;
      font-size: 14px;
      padding-left: 20px;
      line-height: 1.5;
  }

  .instructions li {
      margin-bottom: 6px;
  }

  .btn-group {
      display: flex;
      gap: 12px;
      margin-top: 24px;
  }

  .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
      flex: 1;
      border: none;
      cursor: pointer;
  }

  .btn-primary {
      background: linear-gradient(135deg, #0B83FE, #0066cc);
      color: white;
      box-shadow: 0 2px 8px rgba(11, 131, 254, 0.3);
  }

  .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(11, 131, 254, 0.4);
      background: linear-gradient(135deg, #0066cc, #0052a3);
  }

  .btn-secondary {
      background: #f7fafc;
      color: #4a5568;
      border: 1px solid #e2e8f0;
  }

  .btn-secondary:hover {
      background: #edf2f7;
      border-color: #cbd5e0;
  }

  .footer-text {
      color: #718096;
      font-size: 12px;
      margin-top: 20px;
      line-height: 1.4;
  }

  .resend-link {
      color: #0B83FE;
      text-decoration: none;
      font-weight: 500;
  }

  .resend-link:hover {
      text-decoration: underline;
  }

  /* Animation */
  @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
  }

.verification-card {
    animation: fadeIn 0.6s ease-out;
}
</style>
</head>

<body>

<div class="container">
    <div class="verification-card">
        <!-- Logo -->
        <div class="logo">
            <img src="images/Logo.png" alt="TANAFS Logo">
        </div>
        <div class="verification-icon">
            <img src="images/send.png" style="height:5em;" alt="send">
        </div>

        <h2>Verify Your Email Address</h2>
        <p class="subtitle">We've sent a verification link to your email address</p>

        <?php if ($pending_email): ?>
            <div class="email-highlight">
                <span class="email-address"><?php echo htmlspecialchars($pending_email); ?></span>
            </div>
        <?php endif; ?>

        <div class="instructions">
            <h4>
                <span class="material-symbols-outlined" style="font-size:18px">info</span>
                What to do next:
            </h4>
            <ul>
                <li>Check your email inbox</li>
                <li>Click the verification link in the email</li>
                <li>Return to sign in to access your account</li>
            </ul>
        </div>

          <form method="POST" id="resendForm" style="display: none;">
        <input type="hidden" name="resend_email" value="1">
    </form>

    <div class="btn-group">
        <a href="signin.php" class="btn btn-primary">
            <span class="material-symbols-outlined" style="font-size:18px">login</span>
            Back to Sign In
        </a>
        <button type="button" id="resendBtn" class="btn btn-secondary">
            <span class="material-symbols-outlined" style="font-size:18px">refresh</span>
            Resend Email
        </button>
    </div>

        <p class="footer-text">
            Didn't receive the email? 
            <a href="#" class="resend-link">Click here to resend</a>
            <br>
            Check your spam folder if you can't find the verification email.
        </p>
    </div>
</div>

<script>
  // Resend email functionality
document.getElementById('resendBtn').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px">schedule</span> Sending...';
    btn.disabled = true;
    
    // Call the separate resend endpoint
    fetch('resend_verification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Success state
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px">check</span> Email Sent!';
            btn.style.background = '#e8f5e8';
            btn.style.color = '#2e7d32';
            btn.style.borderColor = '#c8e6c9';
            
            showMessage(data.message, 'success');
        } else {
            // Error state
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px">error</span> Failed';
            btn.style.background = '#ffebee';
            btn.style.color = '#c62828';
            btn.style.borderColor = '#ffcdd2';
            
            showMessage(data.message, 'error');
        }
        
        // Reset button after 3 seconds
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
            btn.disabled = false;
        }, 3000);
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px">error</span> Error';
        btn.style.background = '#ffebee';
        btn.style.color = '#c62828';
        btn.style.borderColor = '#ffcdd2';
        showMessage('Network error. Please check your connection and try again.', 'error');
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
            btn.disabled = false;
        }, 3000);
    });
});

// Function to show messages
function showMessage(text, type) {
    // Remove existing messages
    const existingMsg = document.querySelector('.message-alert');
    if (existingMsg) {
        existingMsg.remove();
    }
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-alert ${type === 'success' ? 'alert-success' : 'alert-error'}`;
    messageDiv.style.cssText = `
        padding: 12px 16px;
        border-radius: 8px;
        margin: 16px 0;
        font-weight: 500;
        text-align: center;
        ${type === 'success' ? 
            'background: #e8f5e8; color: #2e7d32; border: 1px solid #c8e6c9;' : 
            'background: #ffebee; color: #c62828; border: 1px solid #ffcdd2;'
        }
    `;
    messageDiv.textContent = text;
    
    // Insert after the instructions
    const instructions = document.querySelector('.instructions');
    instructions.parentNode.insertBefore(messageDiv, instructions.nextSibling);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}
</script>

</body>
</html>