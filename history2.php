<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (empty($_SESSION['user_id'])) {
    // إن كان الطلب AJAX رجّع 401 بدل التحويل (اختياري)
    if (!empty($_POST['action']) || !empty($_POST['ajax'])) {
        http_response_code(401);
        exit('❌ Unauthorized. Please sign in.');
    }
    header('Location: signin.php');
    exit;
}

$userID = (int)$_SESSION['user_id'];

// Database configuration
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "tanafs";



// Get current user information
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$current_user_name = $_SESSION['first_name'] . " " . $_SESSION['last_name'];
$current_user_initials = strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1));

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: signin.php");
    exit();
}

// Initialize variables
$results = [];
$total_records = 0;
$total_pages = 1;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;
$error_message = "";

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $error_message = "Database connection failed: " . $conn->connect_error;
} else {
    // Handle search filter
    $search_filter = "";
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = $conn->real_escape_string($_GET['search']);
        $search_filter = " AND (p.first_name LIKE '%$search_term%' OR p.last_name LIKE '%$search_term%' OR p.phone LIKE '%$search_term%')";
    }

    // Handle status filter
    $status_filter = "";
    if (isset($_GET['status']) && !empty($_GET['status']) && $_GET['status'] != 'all') {
        $status = $conn->real_escape_string($_GET['status']);
        $status_filter = " AND wa.status = '$status'";
    }

    // Handle date filter
    $date_filter = "";
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $date_from = $conn->real_escape_string($_GET['date_from']);
        $date_filter .= " AND DATE(wa.timestamp) >= '$date_from'";
    }
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $date_to = $conn->real_escape_string($_GET['date_to']);
        $date_filter .= " AND DATE(wa.timestamp) <= '$date_to'";
    }

    // Get total records count for pagination
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM Waveform_analysis wa
        JOIN Patient p ON wa.PID = p.PID
        WHERE 1=1 $search_filter $status_filter $date_filter
    ";

    $count_result = $conn->query($count_sql);
    if ($count_result) {
        $count_row = $count_result->fetch_assoc();
        $total_records = $count_row['total'];
        $total_pages = ceil($total_records / $records_per_page);
    } else {
        $error_message = "Error counting records: " . $conn->error;
    }

    // Get analysis history data
    $sql = "
        SELECT 
            wa.waveAnalysisID,
            p.PID,
            p.first_name,
            p.last_name,
            p.phone,
            p.gender,
            wa.timestamp as analysis_date,
            wa.status,
            wa.severity_level,
            wa.anomaly_type,
            wa.finding_notes
        FROM Waveform_analysis wa
        JOIN Patient p ON wa.PID = p.PID
        WHERE 1=1 $search_filter $status_filter $date_filter
        ORDER BY wa.timestamp DESC
        LIMIT $offset, $records_per_page
    ";

    $result = $conn->query($sql);
    if ($result) {
        $results = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error_message = "Error fetching data: " . $conn->error;
    }
}

// Handle download functionality
if (isset($_POST['download']) && isset($_POST['selected_rows']) && $conn && !$conn->connect_error) {
    $selected_ids = $_POST['selected_rows'];

    // Validate and sanitize selected IDs
    $valid_ids = [];
    foreach ($selected_ids as $id) {
        if (is_numeric($id)) {
            $valid_ids[] = $conn->real_escape_string($id);
        }
    }

    if (!empty($valid_ids)) {
        $ids_string = implode(',', $valid_ids);

        $download_sql = "
            SELECT 
                p.PID,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.phone,
                wa.timestamp as analysis_date,
                wa.status,
                wa.severity_level,
                wa.anomaly_type
            FROM Waveform_analysis wa
            JOIN Patient p ON wa.PID = p.PID
            WHERE wa.waveAnalysisID IN ($ids_string)
        ";

        $download_result = $conn->query($download_sql);
        if ($download_result && $download_result->num_rows > 0) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="analysis_history_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($output, ['Patient ID', 'Patient Name', 'Phone', 'Analysis Date', 'Status', 'Severity', 'Anomaly Type']);

            while ($row = $download_result->fetch_assoc()) {
                fputcsv($output, [
                    "P" . substr($row['PID'], -4),
                    $row['patient_name'],
                    $row['phone'],
                    $row['analysis_date'],
                    $row['status'],
                    $row['severity_level'],
                    $row['anomaly_type']
                ]);
            }

            fclose($output);
            exit();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tanafs History</title>

  <!-- Google Material Symbols  -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#f2f6fb;
      --card:#ffffff;
      --accent:#0f65ff;
      --muted:#9aa6c0;
      --soft-blue:#eef6ff;
      --panel-shadow:0 10px 30px rgba(17,24,39,0.06);
      --radius:14px;
    }

    *{ box-sizing:border-box; margin:0; padding:0; }

    /* ===== Base ===== */
    body{
      font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;
      background:var(--bg);
      color:#15314b;
      display:flex; 
    }

    .material-symbols-outlined{ font-variation-settings:'wght' 500; font-size:20px; }
    .material-symbols-outlined:hover{ transform:translateX(4px); }

    /* ===== Layout ===== */
    .wrapper{
      position:relative;
      width:100%;
      min-height:100vh;
      overflow:visible;
    }

    .main{
      flex:1;
      display:flex;
      flex-direction:column;
      height:50em;
      margin-top:clamp(133px,11vh,340px);
    }

    main{
      flex:1;
      border-top-left-radius:30px;
      padding:36px;
      overflow-y:auto;
    }

    /* ===== Header visuals ===== */
    img.topimg{
      position:absolute; top:-3%; left:48%; transform:translateX(-50%);
      max-width:90%; height:auto; width:auto; z-index:10; pointer-events:none;
    }
    img.logo{
      position:absolute; top:2.2%; left:14%;
      width:clamp(100px,12vw,180px); height:auto; z-index:20; pointer-events:none;
    }

    .auth-nav{
      position:absolute; top:3.2%; right:16.2%;
      display:flex; align-items:center; gap:1.6em; z-index:30;
    }

    .nav-link{
      color:#0876FA; font-weight:600; text-decoration:none; font-size:1em;
      transition:all .3s ease; position:relative;
    }
    .nav-link::after{
      content:""; position:absolute; bottom:-4px; left:0; width:0; height:2px;
      background:linear-gradient(90deg,#0876FA,#78C1F5); transition:width .3s ease; border-radius:2px;
    }
    .nav-link:hover{ transform:translateY(-2px); color:#055ac0; }
    .nav-link:hover::after{ width:100%; }

    .profile{ display:flex; gap:.625em; align-items:center; padding:.375em .625em; }
    .avatar-icon{ width:30px; height:30px; display:block; }
    .profile-btn{ all:unset; cursor:pointer; display:inline-block; }

    .btn-logout{
      background:linear-gradient(90deg,#0f65ff,#5aa6ff);
      color:#fff; padding:.5em .975em; border-radius:.75em; font-weight:400; border:none;
      box-shadow:0 .5em 1.25em rgba(15,101,255,0.14); cursor:pointer; font-size:.875em;
    }

    /* ===== Title ===== */
    .title{
      display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;
    }
    .title h2{ color:#1f46b6; font-size:1.5rem; }

    /* ===== Table Card ===== */
    .table-container{
      background:#fff; border-radius:16px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,.05);
    }

    .table-actions{
      display:flex; justify-content:space-between; margin-bottom:15px; gap:15px;
    }

    .filter-section{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

    .table-actions input,
    .table-actions select{
      padding:8px 12px; border-radius:8px; border:1px solid #ccc; font-size:14px;
    }

    .table-actions button{
      padding:8px 14px; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:14px;
    }

    .download-btn{ background:#1f46b6; color:#fff; }
    .download-btn:disabled{ background:#ccc; cursor:not-allowed; }
    .filter-btn{ background:rgba(196,216,220,.51); color:#1b2250; border-radius:8px; }

    table{
      width:100%; border-collapse:collapse; text-align:center; margin-top:30px;
    }
    th,td{
      padding:12px; text-align:center; border-bottom:1px solid #f0f0f0;
    }
    th{ background:#f4f6fc; color:#1f46b6; font-weight:600; }
    tr:hover{ background:#f9fbff; }

    /* ===== Status Pills ===== */
    .status{
      padding:6px 12px; border-radius:12px; font-size:.85rem; font-weight:500;
      display:inline-block; min-width:80px;
    }
    .status.completed{ background:#e2f5e9; color:#15803d; }
    .status.pending{ background:#fff4e5; color:#b45309; }
    .status.failed{ background:#fee2e2; color:#b91c1c; }
    .status.normal{ background:#e2f5e9; color:#15803d; }
    .status.critical{ background:#fee2e2; color:#b91c1c; }
    .status.abnormal{ background:#fff4e5; color:#b45309; }

    /* ===== Pagination ===== */
    .pagination{
      display:flex; gap:8px; justify-content:center; align-items:center; margin-top:20px;
    }
    .pagination button,
    .pagination a{
      border:none; background:#f3f6fb; padding:6px 12px; border-radius:6px; cursor:pointer;
      text-decoration:none; color:inherit; display:inline-block; font-size:14px;
    }
    .pagination .active{ background:#1976d2; color:#fff; }
    .pagination button:disabled,
    .pagination a:disabled{ background:#f3f6fb; color:#ccc; cursor:not-allowed; }

    /* ===== Messages ===== */
    .error-message{
      background:#fee2e2; color:#b91c1c; padding:12px; border-radius:8px; margin-bottom:15px;
      text-align:center; border:1px solid #fecaca;
    }
    .no-data{ text-align:center; padding:40px; color:#6b7b8f; font-style:italic; }

    /* ===== Footer===== */
    .site-footer{
      background:#F6F6F6; color:#0b1b2b; font-family:'Montserrat',sans-serif; margin-top:auto;
    }
    .footer-grid{
      max-width:75em; margin:0 auto; padding:2.5em 1.25em;
      display:grid; grid-template-columns:1.2fr 1fr 1fr; gap:2em; align-items:start; direction:ltr;
    }
    .footer-col.brand{ text-align:left; }
    .footer-logo{ height:5.5em; width:auto; display:block; margin-left:-3em; }
    .brand-tag{ margin-top:.75em; color:#4c5d7a; font-size:.95em; }

    .footer-title{
      margin:0 0 1em 0; font-size:1.05em; font-weight:700; letter-spacing:.0125em; color:#0B83FE; text-transform:uppercase;
    }

    .social-list{ list-style:none; padding:0; margin:0; display:flex; gap:.75em; align-items:center; }
    .social-list li a{
      display:inline-flex; align-items:center; justify-content:center;
      transition:transform .2s ease, opacity .2s ease;
    }
    .social-list li a:hover{ transform:translateY(-.2em); }
    .social-list img{ width:1.2em; height:1.2em; }
    .social-handle{ display:block; margin-top:.6em; color:#0B83FE; font-size:.95em; }

    .contact-list{ list-style:none; padding:0; margin:.25em 0 0 0; display:grid; gap:.6em; }
    .contact-link{
      display:flex; align-items:center; gap:.6em; text-decoration:none; color:#0B83FE;
      padding:.5em .6em; border-radius:.6em; transition:background .2s ease, transform .2s ease;
    }
    .contact-link:hover{ background:rgba(255,255,255,.7); transform:translateX(.2em); }
    .contact-link img{ width:1.15em; height:1.15em; }

    .footer-bar{ border-top:.06em solid rgba(11,45,92,.12); text-align:center; padding:.9em 1em 1.2em; }
    .legal{ margin:.2em 0; color:#4c5d7a; font-size:.9em; }
    .legal a{ color:#27466e; text-decoration:none; }
    .legal a:hover{ text-decoration:underline; }
    .legal .dot{ margin:0 .5em; color:rgba(11,45,92,.6); }
    .copy{ margin:.2em 0 0; color:#0B83FE; font-size:.85em; }

    /* ===== Responsive ===== */
    @media (max-width:1280px){
      .search{ width:240px; }
    }
    @media (max-width:1024px){
      main{ padding:20px; }
      .table-actions{ flex-direction:column; }
      .filter-section{ justify-content:center; }
    }
    @media (max-width:768px){
      body{ flex-direction:column; }
      .main{ margin-left:0; }
      .table-actions{ flex-direction:column; }
      .filter-section{ flex-direction:column; align-items:stretch; }
      .filter-section input, .filter-section select{ width:100%; }
    }
    @media (max-width:480px){
      .search{ display:none; }
      th,td{ padding:8px 4px; font-size:12px; }
      .table-actions button{ padding:6px 10px; font-size:12px; }
    }

    /* Footer grid to single column on mobile */
    @media (max-width:56.25em){
      .footer-grid{ grid-template-columns:1fr; gap:1.5em; text-align:center; }
      .social-list{ justify-content:center; }
      .contact-link{ justify-content:center; }
      .brand{ display:flex; flex-direction:column; align-items:center; }
    }

    @media (min-width: 768px) and (max-width: 1024px) {
  .auth-nav {
    top: 2.1%;
    right: 12%;
    gap: 1.2em;
  }

  img.logo {
    top: 2.1%;
    left: 11%;
    width: clamp(5em, 14vw, 10em);
  }

  img.topimg {
    top: -2%;
    max-width: 100%;
  }}

  </style>
</head>
<body>

  <div class="wrapper">
    <img class="topimg" src="images/Group 8.png" alt="img">
    <img class="logo" src="images/Logo.png" alt="Tanafs Logo">

    <nav class="auth-nav" aria-label="User navigation">
      <a class="nav-link" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="patients.php">Patients</a>
      <a href="profile.php" class="profile-btn">
        <div class="profile">
          <img class="avatar-icon" src="images/profile.png" alt="Profile">
        </div>
      </a>
<form action="Logout.php" method="post" style="display:inline;">
  <button type="submit" class="btn-logout">Logout</button>
</form>    </nav>

    <main class="main">
      <div class="title">
        <h2>History Analysis</h2>
        <div style="color:#6b7b8f; font-size:14px;">Total Records: <?php echo $total_records; ?></div>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>

      <div class="table-container">
        <form method="get" class="table-actions">
          <div class="filter-section">
            <input type="text" name="search" style="width:33em" placeholder="Search by name or phone..."
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <select name="status">
              <option value="all">All Status</option>
              <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status']=='completed')?'selected':''; ?>>Completed</option>
              <option value="pending"   <?php echo (isset($_GET['status']) && $_GET['status']=='pending')?'selected':''; ?>>Pending</option>
              <option value="failed"    <?php echo (isset($_GET['status']) && $_GET['status']=='failed')?'selected':''; ?>>Failed</option>
              <option value="normal"    <?php echo (isset($_GET['status']) && $_GET['status']=='normal')?'selected':''; ?>>Normal</option>
              <option value="critical"  <?php echo (isset($_GET['status']) && $_GET['status']=='critical')?'selected':''; ?>>Critical</option>
              <option value="abnormal"  <?php echo (isset($_GET['status']) && $_GET['status']=='abnormal')?'selected':''; ?>>Abnormal</option>
            </select>
            <input type="date" name="date_from" value="<?php echo isset($_GET['date_from'])?htmlspecialchars($_GET['date_from']):''; ?>">
            <input type="date" name="date_to"   value="<?php echo isset($_GET['date_to'])?htmlspecialchars($_GET['date_to']):''; ?>">
            <button type="submit" class="filter-btn">Apply Filters</button>
            <a href="?" class="filter-btn" style="text-decoration:none; font-weight:600; font-size:15px; display:inline-block; padding:8px 9px;">Clear</a>
          </div>
        </form>

        <form method="post" id="downloadForm">
          <div class="table-actions">
            <div></div>
            <div>
              <button type="submit" name="download" class="download-btn" id="downloadBtn">Download Selected</button>
            </div>
          </div>

          <table id="historyTable">
            <thead>
              <tr>
                <th><input type="checkbox" id="selectAll"></th>
                <th>ID</th>
                <th>Patient Name</th>
                <th>Phone Number</th>
                <th>Date</th>
                <th>Status</th>
                <th>Severity</th>
              </tr>
            </thead>
            <tbody id="tableBody">
              <?php
                if (!empty($results)) {
                  foreach ($results as $row) {
                    $patient_id = "P".substr($row['PID'],-4)."A".substr($row['waveAnalysisID'],-2);
                    $full_name  = htmlspecialchars($row['first_name']." ".$row['last_name']);
                    $phone      = htmlspecialchars($row['phone']);
                    $date       = date('Y-m-d', strtotime($row['analysis_date']));
                    $status     = $row['status'];
                    $severity   = $row['severity_level'] ?: 'N/A';

                    $status_class = strtolower($status);
                    if (!in_array($status_class, ['completed','pending','failed','normal','critical','abnormal'])) {
                      $status_class = 'pending';
                    }

                    echo "
                      <tr>
                        <td><input type='checkbox' name='selected_rows[]' value='{$row['waveAnalysisID']}' class='row-checkbox'></td>
                        <td>{$patient_id}</td>
                        <td>{$full_name}</td>
                        <td>{$phone}</td>
                        <td>{$date}</td>
                        <td><span class='status {$status_class}'>".ucfirst($status)."</span></td>
                        <td>".ucfirst($severity)."</td>
                      </tr>
                    ";
                  }
                } else {
                  echo "<tr><td colspan='7' class='no-data'>No analysis history found</td></tr>";
                }
              ?>
            </tbody>
          </table>

          <?php if ($total_pages > 1): ?>
            <div class="pagination">
              <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">Previous</a>
              <?php endif; ?>

              <?php for ($i=1; $i<=$total_pages; $i++): ?>
                <?php if ($i==1 || $i==$total_pages || ($i >= $page-2 && $i <= $page+2)): ?>
                  <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$i])); ?>" class="<?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php elseif ($i==$page-3 || $i==$page+3): ?>
                  <span>...</span>
                <?php endif; ?>
              <?php endfor; ?>

              <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">Next</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </main>

    <!-- Footer -->
    <footer id="contact" class="site-footer">
      <div class="footer-grid">
        <div class="footer-col brand">
          <img src="images/logo.png" alt="Tanafs logo" class="footer-logo" />
          <p class="brand-tag">Breathe well, live well</p>
        </div>
        <nav class="footer-col social" aria-label="Social media">
          <h3 class="footer-title">Social Media</h3>
          <ul class="social-list">
            <li><a href="#"><img src="images/twitter.png" alt="Twitter" /></a></li>
            <li><a href="#"><img src="images/instagram.png" alt="Instagram" /></a></li>
          </ul>
          <span class="social-handle">@official_Tanafs</span>
        </nav>
        <div class="footer-col contact">
          <h3 class="footer-title">Contact Us</h3>
          <ul class="contact-list">
            <li>
              <a href="#" class="contact-link"><img src="images/whatsapp.png" alt="WhatsApp" /><span>+123 165 788</span></a>
            </li>
            <li>
              <a href="mailto:Tanafs@gmail.com" class="contact-link"><img src="images/email.png" alt="Email" /><span>Tanafs@gmail.com</span></a>
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
  </div>

  <script>
    document.getElementById('selectAll').addEventListener('click', function(){
      const boxes=document.getElementsByClassName('row-checkbox');
      for (let b of boxes){ b.checked=this.checked; }
      updateDownloadButton();
    });

    function updateDownloadButton(){
      const checked=document.querySelectorAll('.row-checkbox:checked');
      document.getElementById('downloadBtn').disabled=(checked.length===0);
    }

    document.querySelectorAll('.row-checkbox').forEach(cb=>{
      cb.addEventListener('change', updateDownloadButton);
    });

    document.getElementById('downloadForm').addEventListener('submit', function(e){
      if (document.querySelectorAll('.row-checkbox:checked').length===0){
        e.preventDefault(); alert('Please select at least one record to download.');
      }
    });

    let searchTimeout;
    document.querySelector('input[name="search"]').addEventListener('input', function(){
      clearTimeout(searchTimeout);
      searchTimeout=setTimeout(()=>{ this.form.submit(); }, 500);
    });

    updateDownloadButton();
  </script>

  <?php ?>
</body>
</html>