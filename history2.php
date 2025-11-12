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
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "tanafs";

$current_user_id = $_SESSION['user_id'];
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

$first = isset($docData['first_name']) ? $docData['first_name'] : ($_SESSION['first_name'] ?? '');
$last = isset($docData['last_name']) ? $docData['last_name'] : ($_SESSION['last_name'] ?? '');

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

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $error_message = "Database connection failed: " . $conn->connect_error;
} else {
    //search handle
    $search_filter = "";
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $search_term = $conn->real_escape_string($_GET['search']);
        $search_filter = " AND (
        p.PID LIKE '%$search_term%' 
        OR wa.waveAnalysisID LIKE '%$search_term%' 
        OR p.first_name LIKE '%$search_term%' 
        OR p.last_name LIKE '%$search_term%'
    )";
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
        WHERE 1=1 $search_filter $status_filter $category_filter $priority_filter $date_filter
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
        WHERE 1=1 $search_filter $status_filter $category_filter $priority_filter $date_filter
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


        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .material-symbols-outlined {
            font-variation-settings: 'wght' 500;
            font-size: 20px
        }

        .info {
         color:  #848484a0;
         cursor: pointer;
         font-size:0.2em;
         margin-left: 4%;        
        }


        .material-symbols-outlined:hover {
          /*  transform: translateX(4px)*/
        }


        /* ===== Layout ===== */
        .wrapper {
            position: relative;
            width: 100%;
            min-height: 100vh;
            overflow: visible;
        }

        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 50em;
            margin-top: clamp(133px, 11vh, 340px);
        }

        main {
            flex: 1;
            border-top-left-radius: 30px;
            padding: 36px;
            overflow-y: auto;
        }

        /* ===== Header visuals ===== */
        img.topimg {
            position: absolute;
            top: -3%;
            left: 48%;
            transform: translateX(-50%);
            max-width: 90%;
            height: auto;
            width: auto;
            z-index: 10;
            pointer-events: none;
        }

        img.logo {
            position: absolute;
            top: 2.2%;
            left: 14%;
            width: clamp(100px, 12vw, 180px);
            height: auto;
            z-index: 20;
            pointer-events: none;
        }

        .auth-nav {
            position: absolute;
            top: 3.2%;
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
            transition: all .3s ease;
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
            transition: width .3s ease;
            border-radius: 2px;
        }

        .nav-link:hover {
            transform: translateY(-2px);
            color: #055ac0;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .profile {
            display: flex;
            gap: .625em;
            align-items: center;
            padding: .375em .625em;
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
            font-weight: 600;
            border: none;
            box-shadow: 0 0.5em 1.25em rgba(15, 101, 255, 0.14);
            cursor: pointer;
            font-size: 0.875em;
        }

        /* ===== Title ===== */
        .title {
            display: block;
            justify-content: space-between;
            align-items: center;
        }

        .title h2 {
            color: #1f46b6;
            font-size: 1.68rem;
        }

       .title .heading{
            margin-left: 1em;
            font-weight: 700;
            position: relative;
            top: 0.4em;
            margin-bottom: 0.25em;
        }

        .success-message {
            background: #e2f5e9;
            color: #15803d;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #bbf7d0;
        }

        /* ===== Advanced Filters Popup ===== */
        .filter-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .filter-popup {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-height: 80vh;
            overflow-y: auto;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .filter-header h3 {
            color: #1f46b6;
            font-size: 1.3rem;
            margin: 0;
        }

        .close-popup {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 24px;
            color: #9aa6c0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
        }

        .close-popup:hover {
            background: #f3f6fb;
            color: #1f46b6;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 600;
            color: #1f46b6;
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            background: #fff;
            font-size: 14px;
            color: #333;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #0f65ff;
            box-shadow: 0 0 0 2px rgba(15, 101, 255, 0.1);
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .apply-filters {
            background: #1f46b6;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
        }

        .apply-filters:hover {
            background: #0f3a9e;
        }

        .reset-filters {
            background: #f3f6fb;
            color: #1f46b6;
            border: 1px solid #e0e0e0;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
        }

        .reset-filters:hover {
            background: #e8ecf4;
        }

        /* ===== Table Card ===== */
        .table-container {
            background: #fff;
            border-radius: 16px;
            padding: 1.5em;
            box-shadow: 0 4px 10px rgba(0, 0, 0, .05);
            height:fit-content;
        }

        .table-actions {
            display: flex;
            justify-content: space-between;
            gap: 1em;
        }

        .filter-section {
            display: flex;
            gap: 10%;
            align-items: center;
            align-self: start;
            align-content: center; 
            flex-wrap: wrap; 
        }

        .table-actions input,
        .table-actions select {
            padding: 8px 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        #searchInput{
            padding: 8px 10px;
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



        #searchButton{
            background: rgba(196, 216, 220, .51);
            color: #1b2250;
            border-radius: 8px;
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;

        }

        #searchButton:hover {
            background: rgba(26, 125, 255, 0.8);
            color: #fff;
        }



        .filter-btn {
            background: rgba(196, 216, 220, .51);
            color: #1b2250;
            border-radius: 8px;
        }

        .delete-btn {
            background: rgba(184, 33, 33, 0.85);
            color: #fff;
            display: flex;
            position: relative;
            top: -5em;
            right: -70em;

        }

        .delete-btn:disabled {
            background: #b0bcc1;
            cursor: not-allowed;
        }

        .delete-btn:hover:not(:disabled) {
            background: rgba(184, 33, 33, 1);

        }

        .advanced-filter-btn {
            background: #1f46b6;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s;
            position: relative;
            top: -2.4em;
            right: -58em;
        }

        .advanced-filter-btn:hover {
            background: #0f3a9e;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-top: 1em;
            position: relative;
            top: -3em;
        }

        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background: #f4f6fc;
            color: #1f46b6;
            font-weight: 600;
        }

        tr:hover {
            background: #f9fbff;
        }

        /* ===== Status Pills ===== */
        .status {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: .85rem;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
        }

        .status.anomaly {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status.normal {
            background: #e2f5e9;
            color: #15803d;
        }

        .severity {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: .85rem;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
            cursor: pointer;
        }

      

        .severity.high {
            background: #fee2e2;
            color: #b91c1c;
        }

        .severity.mild {
            background: #feede2;
            color: #b95c1c;
        }

        .severity.low {
            background: #e2f5e9;
            color: #15803d;
        }


        /* ===== Pagination ===== */
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
        }

        .pagination button,
        .pagination a {
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

        .pagination .active {
            background: #1976d2;
            color: #fff;
        }

        .pagination button:disabled,
        .pagination a:disabled {
            background: #f3f6fb;
            color: #ccc;
            cursor: not-allowed;
        }

        /* ===== Messages ===== */
        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #fecaca;
        }

        .no-data {
            text-align: center;
            padding: 4.2em;
            color: #6b7b8f;
            text-align: center;
            margin:0 auto;
            max-width: 600px;
            font-style: italic;
        }

       
        /* ===== Responsive ===== */
        @media (max-width: 1280px) {
            .search {
                width: 240px;
            }
        }

        @media (max-width: 1024px) {
            main {
                padding: 20px;
            }

            .table-actions {
                flex-direction: column;
            }

            .filter-section {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .main {
                margin-left: 0;
            }

            .table-actions {
                flex-direction: column;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-section input, .filter-section select {
                width: 100%;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-popup {
                width: 95%;
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .search {
                display: none;
            }

            th, td {
                padding: 8px 4px;
                font-size: 12px;
            }

            .table-actions button {
                padding: 6px 10px;
                font-size: 12px;
            }
        }

        /* Footer grid to single column on mobile */
        @media (max-width: 56.25em) {
            .footer-grid {
                grid-template-columns:1fr;
                gap: 1.5em;
                text-align: center;
            }

            .social-list {
                justify-content: center;
            }

            .contact-link {
                justify-content: center;
            }

            .brand {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
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
            }
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
            position: fixed;
            left: 14%;
            width: clamp(100px, 12vw, 180px);
            height: auto;
            z-index: 20;
            pointer-events: none;
        }

        .auth-nav {
            position: fixed;
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


        .search svg {
            flex: 0 0 18px;
            opacity: .7;
        }

        .search input {
            flex: 1 1 auto;
            border: 0;
            outline: 0;
            background: transparent;
            font-size: 14px;
            color: #384b66;
        }

        .search input::placeholder {
            color: #8fa3bf;
        }

        .search:focus-within {
            border-color: #cfe2ff;
            box-shadow: 0 8px 26px rgba(15, 101, 255, 0.10), 0 0 0 4px rgba(15, 101, 255, 0.10);
            transform: translateY(-1px);
        }

        .search:hover {
            border-color: #d9e6f7;
        }

        /* ===== Footer ===== */
        .site-footer {
            background: #F6F6F6;
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

        :root {
            --bg: #f2f6fb;
            --accent: #0f65ff;
            --muted: #9aa6c0;
        }

        body {
            background: var(--bg);
            color: #15314b;
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

        img.logo {
            position: absolute !important;
            top: -10.2% !important;
            left: 14% !important;
            width: clamp(100px, 12vw, 180px) !important;
            height: auto !important;
            z-index: 20 !important;
            pointer-events: none;
        }

        .auth-nav {
            position: absolute !important;
            top: -9% !important;
            right: 16.2% !important;
            display: flex !important;
            align-items: center !important;
            gap: 1.6em !important;
            z-index: 30 !important;
        }

        main.main {
            margin-top: clamp(133px, 11vh, 340px) !important;
        }

        .heading2 {
            color: #6b7b8f;
            font-size: 0.87em;
            padding: 1em 0;
            margin-left: 1em;
            text-indent:1em;
            line-height: 1.9em;
            max-width:35em ;
        }

        
.tooltip {
  position: relative;
  cursor: pointer;
  font-size:.95rem;
}

.tooltiptext {
  visibility: hidden;
  font-size: 12px;
  width:37em;
  background-color: #757575ff;
  color: #fff;
  text-align: center;
  border-radius: 6px;
  padding: 5px 0;
  position: absolute;
  z-index: 1;
  top: 3.2em;
  left: -7%;
  transform: translateX(-50%);
}

.tooltip:hover .tooltiptext {
  visibility: visible;
}

    </style>

</head>
<body>

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
            <p class="heading2">Track and review the status and output for every analysis request submitted to TANAFS.</p>
           <div style="color:#6b7b8f; text-align:right; position:relative; top:-2em; font-size:14px;">Total Records: <?php echo $total_records; ?></div>

       
        </div>


        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

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
                                <option value="double trigger" <?php echo (isset($_GET['anomaly_type']) && $_GET['anomaly_type'] == 'double trigger') ? 'selected' : ''; ?>>
                                    double trigger
                                </option>
                                <option value="auto trigger" <?php echo (isset($_GET['anomaly_type']) && $_GET['anomaly_type'] == 'auto trigger') ? 'selected' : ''; ?>>
                                    auto trigger
                                </option>
                                <option value="ineffective trigger" <?php echo (isset($_GET['anomaly_type']) && $_GET['anomaly_type'] == 'ineffective trigger') ? 'selected' : ''; ?>>
                                    ineffective trigger
                                </option>
                                <option value="delayed cycling" <?php echo (isset($_GET['anomaly_type']) && $_GET['anomaly_type'] == 'delayed cycling') ? 'selected' : ''; ?>>
                                    delayed cycling
                                </option>
                                <option value="reverse trigger" <?php echo (isset($_GET['anomaly_type']) && $_GET['anomaly_type'] == 'reverse trigger') ? 'selected' : ''; ?>>
                                    reverse trigger
                                </option>
                                <option value="flow limited" <?php echo (isset($_GET['anomaly_type']) && $_GET['anomaly_type'] == 'flow limited') ? 'selected' : ''; ?>>
                                    flow limited
                                </option>
                                <option value="early cycling" <?php echo (isset($_GET['anomaly_type']) && $_GET['anomaly_type'] == 'early cycling') ? 'selected' : ''; ?>>
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

        <div class="table-container">
        <!--Filter & Search Card-->
                    <div class="filter-section">
                        <form name="search" method="GET" action="history2.php" id="searchForm">
                            <input id="searchInput" style="width: 50em"
                                   type="text"
                                   name="search"
                                   placeholder="Search by Patient ID or Analysis ID..."
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="submit" id="searchButton" class="filter-btn">Search</button>
                            <button type="button" class="advanced-filter-btn" id="openFilters">
                                <span class="material-symbols-outlined">filter_list</span> Custom Filters
                            </button>
                        </form>
                    </div>
                         <!--Delete & Table Card-->
            <form method="POST" id="deleteForm">
                <div class="table-actions">
                    <div>
                        <button type="submit" name="delete" class="delete-btn" id="deleteBtn"
                                onclick="return confirmDelete()">Delete Selected</button>
                    </div>
                </div>

                <table id="historyTable">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Patient ID</th>
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
    // Trigger Dyssynchrony Sub-category
    'Trigger Dyssynchrony' => 'Problems related to the initiation of a breath, either the ventilator failing to sense a patient\'s effort, or triggering a breath without patient effort.',
    'Ineffective Trigger' => 'The patient attempts to breathe, but the ventilator fails to detect the effort and does not deliver a breath. This increases patient work of breathing.',
    'Auto Trigger' => 'The ventilator delivers a breath without any patient inspiratory effort, often due to high sensitivity, leaks, or cardiac oscillations.',

    // Flow Dyssynchrony Sub-category
    'Flow Dyssynchrony' => 'A mismatch between the inspiratory flow demand of the patient and the inspiratory flow delivered by the ventilator.',
    'Flow Limited' => 'The inspiratory flow delivered by the ventilator is insufficient to meet the patient\'s inspiratory demand, leading to increased work of breathing and patient discomfort.',

    // Cycling Dyssynchrony Sub-category
    'Cycling Dyssynchrony' => 'Problems related to the termination of the inspiratory phase of the breath, leading to either premature or delayed cycling off.',
    'Double Trigger' => 'type of patient-ventilator asynchrony where a patient initiates two breaths in succession, and the ventilator delivers two breaths in response.',
    'Delayed Cycling' => 'The ventilator\'s inspiratory time is longer than the patient\'s inspiratory effort, causing the patient to actively exhale against the ongoing inspiration. This can lead to breath stacking.',
    'Early Cycling' => 'The ventilator cycles off prematurely (shorter inspiratory time) compared to the patient\'s inspiratory effort, leading to incomplete patient inspiration and increased inspiratory work.',
    'Reverse Trigger' => 'The ventilator initiates a breath, which then triggers a subsequent diaphragmatic contraction from the patient. This is an entrainment phenomenon, often seen with sedation.',
];
$tooltip_text = $tooltip_data[ucfirst($anomaly_type)] ?? 'No additional information available.';


                            echo "
                       <tr>
                        <td><input type='checkbox' name='selected_rows[]' value='{$row['waveAnalysisID']}' class='row-checkbox'></td>
                        <td>{$patient_id}</td>
                        <td>{$analysis_id}</td>
                        <td>{$full_name}</td>
                        <td>{$date}</td>
                        <td>{$time}</td>
                        <td><span class='status {$status}'>{$status}</span></td>
                        <td><span class='severity {$severity}'>{$severity}</span></td>
                        <td class='tooltip'>" . ucfirst($anomaly_type) .
                        "<span class='tooltip-container'>
                        <span style='font-size:.7rem; margin-right:4px;' class='material-symbols-outlined info'>info</span>
                        </span>
                        <span class='tooltiptext'>$tooltip_text</span></td>

                      </tr>
                    ";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='no-data'>No analysis history found</td></tr>";
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

</script>

<?php ?>
</body>
</html>