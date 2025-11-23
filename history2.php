<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


if (empty($_SESSION['user_id'])) {
    if (!empty($_POST['action']) || !empty($_POST['ajax'])) {
        http_response_code(401);
        exit(' Unauthorized. Please sign in.');
    }
    header('Location: signin.php');
    exit;
}

$userID = (int)$_SESSION['user_id'];

// Database configuration
require_once __DIR__ . '/db_connection.php';

$docRes = $conn->prepare("SELECT first_name, last_name, role FROM healthcareprofessional WHERE userID = ?");
$docRes->bind_param("i", $userID);
$docRes->execute();
$docData = $docRes->get_result()->fetch_assoc();
$docRes->close();

$first = $docData['first_name'] ?? '';
$last  = $docData['last_name']  ?? '';
$current_user_role = $docData['role'] ?? null;

$current_user_name = trim($first . ' ' . $last);

$fi = $first !== '' ? mb_substr((string)$first, 0, 1) : '';
$li = $last !== '' ? mb_substr((string)$last, 0, 1) : '';
$current_user_initials = strtoupper($fi . $li);


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

// Check connection
if ($conn->connect_error) {
    $error_message = "Database connection failed: " . $conn->connect_error;
} else {
    //search handle
    $search_filter = "";
    $params = [$userID]; // Start with userID as first parameter
    $types = "i"; // Start with integer type for userID

    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $search_term = "%" . $_GET['search'] . "%";
        $search_filter = " AND (
            p.PID LIKE ? 
            OR wa.waveAnalysisID LIKE ? 
            OR p.first_name LIKE ? 
            OR p.last_name LIKE ?
        )";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        $types .= "ssss"; // Add string types for search parameters
    }


    // Handle status filter
    $status_filter = "";
    if (isset($_GET['status']) && !empty($_GET['status']) && $_GET['status'] != 'all') {
        $status = $conn->real_escape_string($_GET['status']);
        $status_filter = " AND wa.status = '$status'";
    }

    // Handle delete functionality
    if (isset($_POST['delete']) && !empty($_POST['selected_rows'])) {
        $selected_ids = array_filter($_POST['selected_rows'], 'is_numeric');

        if (!empty($selected_ids)) {
            $ids_string = implode(',', $selected_ids);
            $delete_sql = "DELETE FROM waveform_analysis WHERE waveAnalysisID IN ($ids_string)";

            if ($conn->query($delete_sql) === TRUE) {
                $_SESSION['success_message'] = "Successfully deleted " . count($selected_ids) . " record(s).";
            } else {
                $_SESSION['error_message'] = "Error deleting records: " . $conn->error;
            }

            // redirect to same page to clear POST data
            header("Location: " . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid selection.";
        }
    }


    // Handle category filter
    $category_filter = "";
    if (isset($_GET['category']) && !empty($_GET['category']) && $_GET['category'] != 'all') {
        $category = $conn->real_escape_string($_GET['category']);
        $category_filter = " AND wa.anomaly_type = '$category'";
    }

    // Handle priority filter
    $priority_filter = "";
    if (isset($_GET['severity']) && !empty($_GET['severity']) && $_GET['severity'] != 'all') {
        $severity = $conn->real_escape_string($_GET['severity']);
        $priority_filter = " AND wa.severity_level = '$severity'";
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
        JOIN patient_doctor_assignments pda ON p.PID = pda.PID
        WHERE pda.userID = ?
        $search_filter $status_filter $category_filter $priority_filter $date_filter
    ";

    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $userID);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();

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
    JOIN patient_doctor_assignments pda ON p.PID = pda.PID
    WHERE pda.userID = ? 
    $search_filter $status_filter $category_filter $priority_filter $date_filter
    ORDER BY wa.timestamp DESC
    LIMIT $offset, $records_per_page
";

// Prepare and execute with userID parameter
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $results = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $error_message = "Error fetching data: " . $conn->error;
}
}
$success_message = $_SESSION['success_message'] ?? '';
if (isset($_SESSION['success_message'])) unset($_SESSION['success_message']);

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanafs History</title>
    <link rel="stylesheet" href="history.css"/>
    <!-- Google Material Symbols  -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"/>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">


</head>
<body>
<header class="ipad-header">
    <div class="ipad-inner">

        <a href="dashboard.php" class="ipad-logo">
            <img src="Images/Logo.png" alt="Tanafs Logo">
        </a>

        <nav class="ipad-nav">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="patients.php" class="nav-link">Patients</a>
            <a href="history2.php" class="nav-link">History</a>

            <a href="profile.php" class="profile-btn">
                <img src="images/profile.png" alt="Profile">
            </a>

            <form action="Logout.php" method="post">
                <button type="submit" class="ipad-logout">Logout</button>
            </form>
        </nav>

    </div>
</header>
<div class="wrapper">

    <img class="topimg" src="Images/Group 8.png" alt="img">
    <img class="logo" src="Images/Logo.png" alt="Tanafs Logo">

    <nav class="auth-nav" aria-label="User navigation">
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <a class="nav-link" href="patients.php">Patients</a>
        <a class="nav-link active" href="history2.php">History</a>
        <a href="profile.php" class="profile-btn">
            <div class="profile">
                <img class="avatar-icon" src="images/profile.png" alt="Profile">
            </div>
        </a>

        <form action="Logout.php" method="post" style="display:inline;">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </nav>


    <main class="main">
        <div class="title">
            <h2 class="heading">History Analysis</h2>
            <p  class="heading2">Track and review the status and output for every analysis request submitted to TANAFS.</p>
           <div style="color:#6b7b8f; margin-right:1.75em; text-align:right; position:relative; top:-2em; font-size:14px;">Total Records: <?php echo $total_records; ?></div>       
        </div>


        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
    <div class="container">
        <!-- Advanced Filters Popup -->
        <div class="filter-popup-overlay" id="filterPopup">
            <div class="filter-popup">
                <div class="filter-header">
                    <h3>Customize Filters</h3>
                    <button type="button" class="close-popup" id="closePopup">&times;</button>
                </div>
                <!--Custom Filter-->
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <option value="all">All Statuses</option>
                                <option value="Normal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Normal') ? 'selected' : ''; ?>>
                                    Normal
                                </option>
                                <option value="Abnormal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Abnormal') ? 'selected' : ''; ?>>
                                    Abnormal
                                </option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="category">Anomaly type</label>
                          <select name="category" id="category">
                                <option value="all">All Anomaly</option>
                                <option value="double trigger" <?php echo (isset($_GET['category']) && $_GET['category'] == 'double trigger') ? 'selected' : ''; ?>>
                                    double trigger
                                </option>
                                <option value="auto trigger" <?php echo (isset($_GET['category']) && $_GET['category'] == 'auto trigger') ? 'selected' : ''; ?>>
                                    auto trigger
                                </option>
                                <option value="ineffective trigger" <?php echo (isset($_GET['category']) && $_GET['category'] == 'ineffective trigger') ? 'selected' : ''; ?>>
                                    ineffective trigger
                                </option>
                                <option value="delayed cycling" <?php echo (isset($_GET['category']) && $_GET['category'] == 'delayed cycling') ? 'selected' : ''; ?>>
                                    delayed cycling
                                </option>
                                <option value="reverse trigger" <?php echo (isset($_GET['category']) && $_GET['category'] == 'reverse trigger') ? 'selected' : ''; ?>>
                                    reverse trigger
                                </option>
                                <option value="flow limited" <?php echo (isset($_GET['category']) && $_GET['category'] == 'flow limited') ? 'selected' : ''; ?>>
                                    flow limited
                                </option>
                                <option value="early cycling" <?php echo (isset($_GET['category']) && $_GET['category'] == 'early cycling') ? 'selected' : ''; ?>>
                                    early cycling
                                </option>
                            </select>

                        </div>

                        <div class="filter-group">
                            <label for="severity">Severity</label>
                            <select name="severity" id="severity">
                                <option value="all">All Severities</option>
                                <option value="low" <?php echo (isset($_GET['severity']) && $_GET['severity'] == 'low') ? 'selected' : ''; ?>>
                                    Low
                                </option>
                                <option value="mild" <?php echo (isset($_GET['severity']) && $_GET['severity'] == 'mild') ? 'selected' : ''; ?>>
                                    Mild
                                </option>
                                <option value="high" <?php echo (isset($_GET['severity']) && $_GET['severity'] == 'high') ? 'selected' : ''; ?>>
                                    High
                                </option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date_from">From Date</label>
                            <input type="date" name="date_from" id="date_from"
                                   value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                        </div>

                        <div class="filter-group">
                            <label for="date_to">To Date</label>
                            <input type="date" name="date_to" id="date_to"
                                   value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="button" class="reset-filters" id="resetFilters">Reset</button>
                        <button type="submit" class="apply-filters">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
        <!--Filter & Search Card-->
            <div class="filter-section">
                <form name="search" method="GET" action="history2.php" id="searchForm" class="search-form">
                    <input id="searchInput" 
                        type="text"
                        name="search"
                        placeholder="Search by Patient ID or Analysis ID..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button style="width:7em" type="submit" id="searchButton" class="filter-btn">Search</button>
                </form>
                
                <div class="actions-right">
                    <button type="button" class="advanced-filter-btn" id="openFilters">
                        <span class="material-symbols-outlined">filter_list</span> Custom Filters
                    </button>
                </div>
            </div>
       <div class="table-container">
    <!--Filter & Search Card-
    <div class="filter-section">
        <form name="search" method="GET" action="history2.php" id="searchForm" class="search-form">
            <input id="searchInput" 
                   type="text"
                   name="search"
                   placeholder="Search by Patient ID or Analysis ID..."
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit" id="searchButton" class="filter-btn">Search</button>
        </form>
        
        <div class="actions-right">
            <button type="button" class="advanced-filter-btn" id="openFilters">
                <span class="material-symbols-outlined">filter_list</span> Custom Filters
            </button>
        </div>
    </div>-->
    
    <!--Delete & Table Card-->
    <form method="POST" id="deleteForm">
        <div class="table-actions">
            <div class="analysis-info">
        <span class="material-symbols-outlined" style="font-size: 18px; color: #6b7b8f; margin-right: 8px;">info</span>
        <span class="info-text">This page shows analysis records. Patient data is managed separately in the Patients section.</span>
    </div>
           <div class="actions-right">
                <button type="submit" name="delete" class="delete-btn" id="deleteBtn"
                        onclick="return confirmDelete()">Delete Selected</button>
            </div>
        </div>

                <table id="historyTable">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>File Number</th>
                        <th>Analysis ID</th>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Severity level</th>
                        <th>Anomaly type</th>
                    </tr>
                    </thead>
                    <tbody id="tableBody">
                    <?php
                    if (!empty($results)) {
                        foreach ($results as $row) {
                            // $patient_id = "P".substr($row['PID'],-4)."A".substr($row['waveAnalysisID'],-2);
                            $patient_id = $row['PID'];
                            $analysis_id = $row['waveAnalysisID'];
                            $full_name = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
                            $phone = htmlspecialchars($row['phone']);
                            $date = date('Y-m-d', strtotime($row['analysis_date']));
                            $time = date('H:i', strtotime($row['analysis_date']));
                            $status = $row['status'];
                            $severity = $row['severity_level'] ?: 'N/A';
                            $anomaly_type = $row['anomaly_type'] ?: 'N/A';

                          

        $tooltip_data = [
            'ineffective trigger' => 'The patient attempts to breathe, but the ventilator fails to detect the effort and does not deliver a breath. This increases patient work of breathing.',
            'auto trigger'        => 'The ventilator delivers a breath without any patient inspiratory effort, often due to high sensitivity, leaks, or cardiac oscillations.',
            'flow limited'        => 'The inspiratory flow delivered by the ventilator is insufficient to meet the patient\'s inspiratory demand, leading to increased work of breathing and patient discomfort.',
            'double trigger'      => 'A type of patient-ventilator asynchrony where a patient initiates two breaths in succession, and the ventilator delivers two breaths in response.',
            'delayed cycling'     => 'The ventilator\'s inspiratory time is longer than the patient\'s inspiratory effort, causing the patient to actively exhale against the ongoing inspiration (breath stacking).',
            'early cycling'       => 'The ventilator cycles off prematurely compared to the patient\'s inspiratory effort, leading to incomplete inspiration and increased inspiratory work.',
            'reverse trigger'     => 'The ventilator initiates a breath, which then triggers a diaphragmatic contraction from the patient (entrainment phenomenon, often with sedation).',
        ];

            $key = strtolower($anomaly_type);
            $tooltip_text = $tooltip_data[$key] ?? 'No additional information available.';



                            echo "
                        <tr>
                        <td><input type='checkbox' name='selected_rows[]' value='{$row['waveAnalysisID']}' class='row-checkbox'></td>
                        <td>P{$patient_id}</td>
                        <td>{$analysis_id}</td>
                        <td>{$full_name}</td>
                        <td>{$date}</td>
                        <td>{$time}</td>
                        <td><span class='status {$status}'>{$status}</span></td>
                        <td><span class='severity {$severity}'>{$severity}</span></td>
                        <td class='tooltip'>" . ucwords($anomaly_type) . "
        <span class='tooltip-container'>
            <span style='font-size:.7rem; margin-right:4px;' class='material-symbols-outlined info'>info</span>
        </span>
        <span class='tooltiptext'>$tooltip_text</span>
    </td>


                      </tr>
                    ";
                        }
                    } else {
                    echo "<tr><td colspan='9' class='no-data'><img style='height:10em; margin:.5em' src='images/nores.png' alt='no-result'>.
                    <br>No analysis history found.
                    </td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
                <!--pagination-->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                   class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
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
        </div>
    </main>

    <!-- Footer -->
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
</div>
<script>
    // Confirm before deleting
    function confirmDelete() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one record to delete.');
            return false;
        }
        return confirm('Are you sure you want to delete ' + checked.length + ' selected record(s)? This action cannot be undone.');
    }

    // Update delete button state
    function updateDeleteButton() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        document.getElementById('deleteBtn').disabled = (checked.length === 0);
    }

    // Update all event listeners to use updateDeleteButton instead of updateDownloadButton
    document.getElementById('selectAll').addEventListener('click', function () {
        const boxes = document.getElementsByClassName('row-checkbox');
        for (let b of boxes) {
            b.checked = this.checked;
        }
        updateDeleteButton();
    });

    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.addEventListener('change', updateDeleteButton);
    });

    // Initialize delete button state
    updateDeleteButton();


</script>
<script>
    // Select All functionality
    document.getElementById('selectAll').addEventListener('click', function () {
        const boxes = document.getElementsByClassName('row-checkbox');
        for (let b of boxes) {
            b.checked = this.checked;
        }
        updateDeleteButton();
    });


    // Add event listeners to row checkboxes
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.addEventListener('change', updateDeleteButton);
    });


    // Filter popup functionality
    const filterPopup = document.getElementById('filterPopup');
    const openFiltersBtn = document.getElementById('openFilters');
    const closePopupBtn = document.getElementById('closePopup');

    // Open filter popup
    openFiltersBtn.addEventListener('click', function () {
        filterPopup.style.display = 'flex';
    });

    // Close filter popup
    closePopupBtn.addEventListener('click', function () {
        filterPopup.style.display = 'none';
    });

    // Close popup when clicking outside
    filterPopup.addEventListener('click', function (e) {
        if (e.target === filterPopup) {
            filterPopup.style.display = 'none';
        }
    });

    // Reset filters to default values
    document.getElementById('resetFilters').addEventListener('click', function () {
        document.getElementById('status').value = 'all';
        document.getElementById('category').value = 'all';
        document.getElementById('severity').value = 'all';
        document.getElementById('date_from').value = '';
        document.getElementById('date_to').value = '';
    });

    updateDeleteButton();
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');

        if (searchForm && searchInput && searchButton) {
            // Submit search form when clicking Search
            searchButton.addEventListener('click', function (e) {
                e.preventDefault();
                const searchTerm = searchInput.value.trim();
                const params = new URLSearchParams(window.location.search);
                if (searchTerm) {
                    params.set('search', searchTerm);
                    params.set('page', 1);
                } else {
                    params.delete('search');
                    params.set('page', 1);
                }
                window.location.href = window.location.pathname + '?' + params.toString();
            });

            // Allow pressing Enter key to trigger search
            searchInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchButton.click();
                }
            });
        }
    });
</script>


<?php ?>
</body>
</html>