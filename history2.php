<?php
// Start session and check authentication
session_start();

// Database configuration
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "tanafs";


$_SESSION['userID'] = 1;
$userID = $_SESSION['userID'];

/* Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Get current user information
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$current_user_name = $_SESSION['first_name'] . " " . $_SESSION['last_name'];
$current_user_initials = strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1));

/* Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}*/

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

    <!-- Google Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #f2f6fb;
            --card: #ffffff;
            --accent: #0f65ff;
            --muted: #9aa6c0;
            --soft-blue: #eef6ff;
            --panel-shadow: 0 10px 30px rgba(17, 24, 39, 0.06);
            --radius: 14px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            background: var(--bg);
            color: #15314b;
            display: flex;
        }

        .material-symbols-outlined {
            font-variation-settings: 'wght' 500;
            font-size: 20px;
        }

        .btn-logout {
            background: linear-gradient(90deg, #0f65ff, #5aa6ff);
            color: white;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            box-shadow: 0 8px 20px rgba(15, 101, 255, 0.14);
            cursor: pointer;
            font-size: 14px;
            height: 40px;
        }

        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header.topbar {
            height: fit-content;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7));
            border-bottom: 1px solid rgba(15, 21, 40, 0.54);
        }

        .top-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search {
            background: var(--card);
            padding: 10px 12px;
            border-radius: 999px;
            width: 360px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--panel-shadow);
            border: 1px solid rgba(7, 12, 30, 0.03);
        }

        .search input {
            border: 0;
            outline: 0;
            font-size: 14px;
            width: 100%;
            color: #6b7b8f;
            background: transparent;
        }

        .logo-top {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--accent);
            font-size: 20px;
            margin-right: 185px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .icon-round {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            background: linear-gradient(180deg, #fff, #f3f6ff);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(15, 21, 40, 0.04);
            box-shadow: 0 6px 16px rgba(20, 30, 60, 0.03);
        }

        .profile {
            display: flex;
            gap: 10px;
            align-items: center;
            background: linear-gradient(90deg, #f7fbff, #fff);
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(15, 21, 40, 0.03);
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: linear-gradient(180deg, #2e9cff, #1a57ff);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        main {
            flex: 1;
            background-color: #f9faff;
            border-top-left-radius: 30px;
            padding: 36px;
            overflow-y: auto;
        }

        .title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .title h2 {
            color: #1f46b6;
            font-size: 1.5rem;
        }

        .table-container {
            background-color: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .table-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            gap: 15px;
        }

        .filter-section {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .table-actions input, .table-actions select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .table-actions button {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .download-btn {
            background-color: #1f46b6;
            color: white;
        }

        .download-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .filter-btn {
            background-color: rgba(196, 216, 220, 0.51);
            color: #1b2250;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-top: 30px;
        }

        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background-color: #f4f6fc;
            color: #1f46b6;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f9fbff;
        }

        .status {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
        }

        .status.completed { background-color: #e2f5e9; color: #15803d; }
        .status.pending { background-color: #fff4e5; color: #b45309; }
        .status.failed { background-color: #fee2e2; color: #b91c1c; }
        .status.normal { background-color: #e2f5e9; color: #15803d; }
        .status.critical { background-color: #fee2e2; color: #b91c1c; }
        .status.abnormal { background-color: #fff4e5; color: #b45309; }

        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 20px;
            align-items: center;
        }

        .pagination button, .pagination a {
            border: none;
            background: #f3f6fb;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: inline-block;
            font-size: 14px;
        }

        .pagination button.active, .pagination a.active {
            background: #1976d2;
            color: #fff;
        }

        .pagination button:disabled, .pagination a:disabled {
            background: #f3f6fb;
            color: #ccc;
            cursor: not-allowed;
        }

        footer {
            background-color: white;
            text-align: center;
            padding: 10px;
            font-size: 0.85rem;
            color: #555;
            margin-top: auto;
        }

        .error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #fecaca;
        }

        .success-message {
            background-color: #e2f5e9;
            color: #15803d;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #bbf7d0;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7b8f;
            font-style: italic;
        }

        /* Responsive styles */
        @media (max-width: 1280px) {
            .search { width: 240px; }
            .logo-top img { width: 200px !important; }
        }
        @media (max-width: 1024px) {
            main { padding: 20px; }
            .table-actions { flex-direction: column; }
            .filter-section { justify-content: center; }
        }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .main { margin-left: 0; }
            .table-actions { flex-direction: column; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-section input, .filter-section select { width: 100%; }
        }
        @media (max-width: 480px) {
            .search { display: none; }
            .top-actions .profile div:nth-child(2) { display: none; }
            th, td { padding: 8px 4px; font-size: 12px; }
            .table-actions button { padding: 6px 10px; font-size: 12px; }
        }
    </style>
</head>
<body>

<!-- MAIN -->
<main class="main">
    <header class="topbar" role="banner">
        <div class="top-left">
            <form method="post" style="display: inline;">
                <button type="submit" name="logout" class="btn-logout">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">logout</span>
                </button>
            </form>
            <form method="get" class="search" role="search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="M21 21l-4.35-4.35" stroke="#6b7b8f" stroke-width="1.6" stroke-linecap="round"/>
                    <circle cx="11" cy="11" r="5.2" stroke="#6b7b8f" stroke-width="1.6"/>
                </svg>
                <input type="text" name="search" placeholder="Search ..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Search"/>
            </form>
        </div>

        <div class="logo-top" aria-hidden>
            <div class="logo-lungs"><img src="images/logon2.png" alt="logon" style="width: 280px"></div>
        </div>

        <div class="top-actions">
            <div class="icon-round" title="Notifications">
                <span class="material-symbols-outlined" style="font-size:20px">notifications</span>
            </div>

            <div class="profile" title="<?php echo htmlspecialchars($current_user_name); ?>">
                <div class="avatar"><?php echo $current_user_initials; ?></div>
                <div style="font-size:14px;color:#2b4a77"><?php echo htmlspecialchars($current_user_name); ?></div>
            </div>
        </div>
    </header>

    <!-- Page Title -->
    <main>
        <div class="title">
            <h2>History Analysis</h2>
            <div style="color: #6b7b8f; font-size: 14px;">
                Total Records: <?php echo $total_records; ?>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <form method="get" class="table-actions">
                <div class="filter-section">
                    <input type="text" name="search" style="width: 33em" placeholder="Search by name or phone..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <select name="status" >
                        <option value="all">All Status</option>
                        <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'failed') ? 'selected' : ''; ?>>Failed</option>
                        <option value="normal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'normal') ? 'selected' : ''; ?>>Normal</option>
                        <option value="critical" <?php echo (isset($_GET['status']) && $_GET['status'] == 'critical') ? 'selected' : ''; ?>>Critical</option>
                        <option value="abnormal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                    </select>
                    <input type="date" name="date_from" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>" placeholder="From Date">
                    <input type="date" name="date_to" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>" placeholder="To Date">
                    <button type="submit" class="filter-btn">Apply Filters</button>
                    <a href="?" class="filter-btn" style="text-decoration: none; font-weight: 600;  font-size:15px;display: inline-block; padding: 8px 9px;">Clear</a>
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
                            $patient_id = "P" . substr($row['PID'], -4) . "A" . substr($row['waveAnalysisID'], -2);
                            $full_name = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
                            $phone = htmlspecialchars($row['phone']);
                            $date = date('Y-m-d', strtotime($row['analysis_date']));
                            $status = $row['status'];
                            $severity = $row['severity_level'] ?: 'N/A';

                            // Determine status class
                            $status_class = strtolower($status);
                            if (!in_array($status_class, ['completed', 'pending', 'failed', 'normal', 'critical', 'abnormal'])) {
                                $status_class = 'pending';
                            }

                            echo "
                            <tr>
                                <td><input type='checkbox' name='selected_rows[]' value='{$row['waveAnalysisID']}' class='row-checkbox'></td>
                                <td>{$patient_id}</td>
                                <td>{$full_name}</td>
                                <td>{$phone}</td>
                                <td>{$date}</td>
                                <td><span class='status {$status_class}'>" . ucfirst($status) . "</span></td>
                                <td>" . ucfirst($severity) . "</td>
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
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                   class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>

    <footer>
        © 2025 TANAFS. All Rights Reserved.
    </footer>
</main>

<script>
    // JavaScript for table functionality
    document.getElementById('selectAll').addEventListener('click', function() {
        const checkboxes = document.getElementsByClassName('row-checkbox');
        for (let checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
        updateDownloadButton();
    });

    // Update download button state based on selection
    function updateDownloadButton() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        const downloadBtn = document.getElementById('downloadBtn');
        downloadBtn.disabled = checkboxes.length === 0;
    }

    // Add event listeners to all checkboxes
    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateDownloadButton);
    });

    // Prevent form submission if no checkboxes are selected for download
    document.getElementById('downloadForm').addEventListener('submit', function(e) {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one record to download.');
        }
    });

    // Auto-submit search form when typing (with delay)
    let searchTimeout;
    document.querySelector('input[name="search"]').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });

    // Initialize download button state
    updateDownloadButton();
</script>

<?php
// Close database connection
if (isset($conn) && $conn) {
    $conn->close();
}
?>
</body>
</html>