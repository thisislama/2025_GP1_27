
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>TANAFS Dashboard</title>
        <link rel="icon" type="image/png" href="/images/fi.png">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: #15314b;
            overflow-y: auto;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            display: block;
        }

            :root {
            --bg: #f2f6fb;
            --accent: #0f65ff;
            --muted: #9aa6c0;
        }

        body {
            background: var(--bg);
            color: #15314b;
        }

        :root {
            --bg: #f2f6fb;
            --accent: #0f65ff;
            --muted: #9aa6c0;
            --radius: 24px;
            --field-h: 3.25rem;
            --field-r: 12px;
            --gap: 16px;
            --pad: 36px;
            --maxw: 800px;
        }
        .nav-link.active::after {
          width: 100%;
       }


        .material-symbols-outlined {
            font-variation-settings: 'wght' 500;
            font-size: 20px
        }

        .material-symbols-outlined:hover {

            color: #eae8e8ff;
        }

        .btn-logout {
            background: linear-gradient(90deg, #0f65ff, #5aa6ff);
            color: white;
            padding: 0.5em 0.975em;
            border-radius: 0.75em;
            font-weight: 600;
            border: none;
            box-shadow: 0 0.5em 1.25em rgba(15, 101, 255, 0.14);
            cursor: pointer;
            font-size: 0.875em;
        }

        /* -------- Main (header + content) -------- */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 50em;
        }

        /* -------- Page content -------- */
        .container {
            width: 100%;
            margin-top: 10%;
            padding: 2.5em;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .left-column {
            display: flex;
            flex-direction: column;
            gap: 19px
        }

        .welcome {
            color: #000000;
            margin-bottom: 6px;
            text-indent: 12px;
            font-family: "Oxygen", sans-serif;
        }

        .welcome h1 {
            font-size: 30px;
            margin: 0;
            font-family: "Oxygen", sans-serif;
        }

        .welcome p {
            margin: 6px 0 0;
            color: rgba(0, 0, 0, 0.9);
            font-family: "Oxygen", sans-serif;
        }


        .upload-card {
            border: none;
            padding: 37px;
            min-height: 25em;
            margin-top: 12%;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            border-radius: 12px ;
            backdrop-filter: blur(4px);
            box-shadow: #505867 1px 1px 1px;
        }
    
        .small-cards {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            min-height: 10em;
            width: 100%;
            backdrop-filter: blur(4px);
            box-shadow: #505867 1px 1px 1px;
        }

        .small-item {
            padding: 12px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .small-item .id {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .right-column {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-top: 12%;
        }
  
        .result-card {
            padding: 22px;
            border-radius: 10px;
            min-height: 35em;
            width: 100%;
            box-shadow: #4c5d7a 1px 1px 1px;
        }

        
        .muted {
            color: var(--muted)
        }

        .btn {
            background: var(--accent);
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer
        }

        .wrapper {
            position: relative;
            width: 100%;
            height: auto;
            min-height: 100vh;
            overflow: visible;
        }
/**NAV */
        img.topimg {
            position: absolute;
            top: -15.4%;
            left: 48%;
            transform: translateX(-50%);
            height: auto;
            width: auto;
            max-width: 90%;
            z-index: 10;
            pointer-events: none;
        }

        img.logo {
            position: absolute;
            top: -8.1%;
            left: 14%;
            width: clamp(100px, 12vw, 180px);
            height: auto;
            z-index: 20;
            pointer-events: none;
        }

        .auth-nav {
            position: absolute;
            top: -7%;
            right: 16.2%;
            display: flex;
            align-items: center;
            gap: 1.6em;
            z-index: 30;
        }

        .nav-link {
            color: #0876FA;
            font-weight: 600;
            text-decoration: none;
            font-size: 1em;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::after {
            content: "";
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #0876FA, #78C1F5);
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-link:hover {
            transform: translateY(-2px);
            color: #055ac0;
        }

        .profile {
            display: flex;
            gap: 0.625em;
            align-items: center;
            padding: 0.375em 0.625em;
        }

        .avatar-icon {
            width: 30px;
            height: 30px;
            display: block;
        }

        .profile-btn {
            all: unset;
            cursor: pointer;
            display: inline-block;
        }

        .btn-logout {
            background: linear-gradient(90deg, #0f65ff, #5aa6ff);
            color: white;
            padding: 0.5em 0.975em;
            border-radius: 0.75em;
            font-weight: 400;
            border: none;
            box-shadow: 0 0.5em 1.25em rgba(15, 101, 255, 0.14);
            cursor: pointer;
            font-size: 0.875em;
        }

        /*===== nav end =====*/
        

        /* ===== Footer ===== */
        .site-footer {
            background: #ffffffff;
            color: #0b1b2b;
            font-family: 'Montserrat', sans-serif;
            margin-top: 3em;
        }

        .footer-grid {
            max-width: 75em;
            margin: 0 auto;
            padding: 2.5em 1.25em;
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 2em;
            align-items: start;
            direction: ltr;
        }

        .footer-col.brand {
            text-align: left;
        }

        .footer-logo {
            height: 5.5em;
            width: auto;
            display: block;
            margin-left: -3em;
        }

        .brand-tag {
            margin-top: 0.75em;
            color: #4c5d7a;
            font-size: 0.95em;
        }

        .footer-title {
            margin: 0 0 1em 0;
            font-size: 1.05em;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #0B83FE;
            text-transform: uppercase;
        }

        .social-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 0.75em;
            align-items: center;
        }

        .social-list li a {
            display: inline-flex;
            width: auto;
            height: auto;
            align-items: center;
            justify-content: center;
            border-radius: 0;
            background: none;
            box-shadow: none;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .social-list li a:hover {
            transform: translateY(-0.2em);
            box-shadow: 0 0.6em 1.4em rgba(0, 0, 0, 0.08);
        }

        .social-list img {
            width: 1.2em;
            height: 1.2em;
        }

        .social-handle {
            display: block;
            margin-top: 0.6em;
            color: #0B83FE;
            font-size: 0.95em;
        }

        .contact-list {
            list-style: none;
            padding: 0;
            margin: 0.25em 0 0 0;
            display: grid;
            gap: 0.6em;
        }

        .contact-link {
            display: flex;
            align-items: center;
            gap: 0.6em;
            text-decoration: none;
            color: #0B83FE;
            padding: 0.5em 0.6em;
            border-radius: 0.6em;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .contact-link:hover {
            background: rgba(255, 255, 255, 0.7);
            transform: translateX(0.2em);
        }

        .contact-link img {
            width: 1.15em;
            height: 1.15em;
        }

        .footer-bar {
            border-top: 0.06em solid rgba(11, 45, 92, 0.12);
            text-align: center;
            padding: 0.9em 1em 1.2em;
        }

        .legal {
            margin: 0.2em 0;
            color: #4c5d7a;
            font-size: 0.9em;
        }

        .legal a {
            color: #27466e;
            text-decoration: none;
        }

        .legal a:hover {
            text-decoration: underline;
        }

        .legal .dot {
            margin: 0 0.5em;
            color: rgba(11, 45, 92, 0.6);
        }

        .copy {
            margin: 0.2em 0 0;
            color: #0B83FE;
            font-size: 0.85em;
        }
        /* ===== footer end ===== */

        .upload-card,
        .small-cards,
        .result-card {
            background: #fff;
            border: 1px solid #e9eef6;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .05);
        }
        .upload-card{
            border: none;
            box-shadow: none;
        }


        .upload-drop {
            width: 30em;
            background: #fff;
            border: 2px dashed rgba(68, 110, 170, 0.7);
            color: #2b4a77;
            cursor: pointer;
        }


        .small-item {
            background: #eef3fb;
            border: 1px solid #e9eef6;
        }

        .small-item .id {
            color: #2b4a77;
        }

        .muted {
            color: var(--muted)
        }

        .result-card .title {
            color: #2b4a77;
            border-bottom: 1px solid #e9eef6;
            opacity: 1;
        }

        .result-output {
            color: #2b4a77;
            opacity: .75
        }

        .btn,
        .btn-logout {
            background: linear-gradient(90deg, #0f65ff, #5aa6ff);
            color: #fff;
        }

        .nav-link,
        .footer-title,
        .social-handle,
        .contact-link {
            color: #0B83FE;
        }

        .welcome, .welcome h1, .welcome p {
            color: #2b4a77;
        }

       

    .uploaded-image-container {
        background: #f8fafd;
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #e9eef6;
        width: 50%;
    }

    .upload-success {
        color: #28a745;
        background: #d4edda;
        padding: 10px;
        border-radius: 5px;
        margin-top: 10em;
        width: 50%;
    }

    .upload-error {
        color: #dc3545;
        background: #f8d7da;
        padding: 10px;
        border-radius: 5px;
        width: 50%;
        margin-top: 10em;
    }


    .hint {
            opacity: 0.4;
            font-size: 12px;
            color: #383d48ff;
            font-weight: 500;
            
        }

    </style>
</head>
<body>


<div class="wrapper">

    <img class="topimg" src="Images/Group 8.png" alt="img">
    <img class="logo" src="Images/Logo.png" alt="Tanafs Logo">

    <nav class="auth-nav" aria-label="User navigation">
        <a class="nav-link active" href="dashboard.php">Dashboard</a>
        <a class="nav-link" href="patients.php">Patients</a>
        <a class="nav-link" href="history2.php">History</a>
        <a href="profile.php" class="profile-btn">
            <div class="profile">
                <img class="avatar-icon" src="images/profile.png" alt="Profile">
            </div>
        </a>

        <form action="Logout.php" method="post" style="display:inline;">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </nav>

    <main class="container">

    
        <!-- LEFT -->
        <section class="left-column">            
            <!-- UPLOAD CARD -->
            <label class="upload-card" for="fileUpload" style="box-shadow: rgba(169,175,188,0.69) -.01em .01em 0.5em .1em">
                <img src="images/wave.jpg" alt="Upload Icon" style="width:100%;"/>
                
            
            </label>

            <div class="small-cards">
                <h3 style="font-weight:700;font-size:16px;">Suggestion and Solutions: </h3>
                <h5 class="hint">Based on the analysis of your medical images, we recommend the following steps to enhance image quality and ensure accurate diagnostics:</h5>
                <div class="small-item">
                    <div>
                        <div class="id">Increase Oxygen Levels</div>
                        <div class="muted" style="font-size:13px">Ensure proper illumination during image capture to reduce shadows and improve clarity.</div>
                    </div>
                    <div style="background:#8fa3bf2f;padding:8px;border-radius:8px;cursor:pointer">
                        <span class="material-symbols-outlined" style="color: rgba(18,36,51,0.65)">arrow_forward</span>
                    </div>
                </div>
                <div class="small-item">
                    <div>
                        <div class="id">Install Tube</div>
                        <div class="muted" style="font-size:13px">Consider recapturing images with better focus and positioning for optimal diagnostic accuracy.</div>
                    </div>
                    <div style="background:#8fa3bf2f;padding:8px;border-radius:8px;cursor:pointer">
                        <span class="material-symbols-outlined" style="color: rgba(18,36,51,0.65)">arrow_forward</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- RIGHT -->
        <section class="right-column">
            <div class="result-card">
                <div class="small-item" style="background:#d4edda;border:none;box-shadow:none;margin-bottom:15px">
                    <div>
                        <div class="id" style="font-weight:700;color:#10500b">Normal</div>
                        <div class="muted" style="font-size:13px; color:#10500b; opacity:0.75">
                            All the waves are within normal parameters.
                        </div>
                    </div>
                    <div style="background:#eff9ec;padding:5px;border-radius:8px;cursor:pointer">
                        <span class="material-symbols-outlined" style="font-size:24px;color: rgba(16, 80, 11, 0.65)">check_circle</span>
                    </div>
                </div>

                <h4>The following results were obtained from the analysis of your medical images:</h4>
                <ul style="margin-top:10px; margin-bottom:10px; list-style: none; padding-left:0;">
                    <li>Noise Level: Low</li>
                    <li>Image Quality Classification: Good</li>
                    <li>Denoising Recommendation: Not required</li>
                </ul>

                <div class="analysis">
                    <h4>Detailed Analysis:</h4>
                    <p>
                        The noise level in your medical images was measured to be
                        <strong>Low</strong>, resulting in an overall
                        <strong>Good</strong> quality classification. Based on this assessment,
                        we recommend <strong>No denoising required</strong> at this time.
                    </p>

                    <div class="chart-container">
                        <img src="images/graph.png" alt="Analysis Chart" style="width:100%; height:auto; margin-top:10px;"/>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>


<footer id="contact" class="site-footer">
    <div class="footer-grid">

        <div class="footer-col brand">
            <img src="images/logo.png" alt="Tanafs logo" class="footer-logo"/>
            <p class="brand-tag">Breathe well, live well</p>
        </div>

        <!-- Social -->
        <nav class="footer-col social" aria-label="Social media">
            <h3 class="footer-title">Social Media</h3>
            <ul class="social-list">
                <li>
                    <a href="#" aria-label="Twitter">
                        <img src="images/twitter.png" alt="Twitter"/>
                    </a>
                </li>
                <li>
                    <a href="#" aria-label="Instagram">
                        <img src="images/instagram.png" alt="Instagram"/>
                    </a>
                </li>
            </ul>
            <span class="social-handle">@official_Tanafs</span>
        </nav>

        <!-- Contact -->
        <div class="footer-col contact">
            <h3 class="footer-title">Contact Us</h3>
            <ul class="contact-list">
                <li>
                    <a href="#" class="contact-link">
                        <img src="images/whatsapp.png" alt="WhatsApp"/>
                        <span>+123 165 788</span>
                    </a>
                </li>
                <li>
                    <a href="mailto:Appointly@gmail.com" class="contact-link">
                        <img src="images/email.png" alt="Email"/>
                        <span>Tanafs@gmail.com</span>
                    </a>
                </li>
            </ul>
        </div>

    </div>

    <div class="footer-bar">
        <p class="legal">
            <a href="#">Terms &amp; Conditions</a>
            <span class="dot">•</span>
            <a href="#">Privacy Policy</a>
        </p>
        <p class="copy">© 2025 Tanafs Company. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
