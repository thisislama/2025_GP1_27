<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: 0');

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');

session_start(); 

date_default_timezone_set('Asia/Riyadh');

require_once __DIR__ . '/db_connection.php';

$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($sessionUserId <= 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$docCheckSql = "SELECT is_verified FROM healthcareprofessional WHERE userID = ?";
$docStmt = mysqli_prepare($conn, $docCheckSql);
mysqli_stmt_bind_param($docStmt, "i", $sessionUserId);
mysqli_stmt_execute($docStmt);
$docRes = mysqli_stmt_get_result($docStmt);
$docRow = mysqli_fetch_assoc($docRes);
if ($docRes) mysqli_free_result($docRes);
mysqli_stmt_close($docStmt);

if (!$docRow || (int)$docRow['is_verified'] !== 1) {
    echo json_encode(["status" => "error", "message" => "Email not verified"]);
    exit;
}

// قراءة pid و mode
$pid  = isset($_GET['pid'])  ? (int)$_GET['pid']  : 0;
$mode = isset($_GET['mode']) ? $_GET['mode']      : 'patient';

/* ====================== patient name ====================== */
if ($mode === 'patient') {
    $sql = "SELECT first_name, last_name FROM patient WHERE PID = $pid";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        echo json_encode(["name" => $row["first_name"] . " " . $row["last_name"]]);
    } else {
        echo json_encode(["name" => "Unknown"]);
    }
    if ($result) mysqli_free_result($result);
}

/* ====================== analysis table ====================== */
elseif ($mode === 'analysis') {
    // FIX: نرجّع timestamp كما هو من الجدول
    $sql = "SELECT anomaly_type, severity_level, `timestamp`
            FROM waveform_analysis
            WHERE PID = $pid
            ORDER BY `timestamp` DESC";
    $result = mysqli_query($conn, $sql);
    $data = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // تطبيع بسيط للقيم
            $anomaly = isset($row['anomaly_type']) ? trim($row['anomaly_type']) : '';
            if ($anomaly === '' || strcasecmp($anomaly, 'None') === 0) $anomaly = 'Normal';

            $data[] = [
                "anomaly_type"   => $anomaly,
                "severity_level" => ($row['severity_level'] ?? '') !== '' ? $row['severity_level'] : '-',
                "timestamp"      => $row['timestamp'],
            ];
        }
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($result) mysqli_free_result($result);
}

/* ====================== comments (list) ====================== */
elseif ($mode === 'comments') {
    $sql = "
        SELECT 
            c.content,
            c.`timestamp` AS ts,
            CONCAT_WS(' ', hp.first_name, hp.last_name) AS by_name
        FROM comment c
        LEFT JOIN healthcareprofessional hp ON hp.userID = c.userID
        WHERE c.PID = ?
        ORDER BY c.`timestamp` DESC, c.CommentID DESC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(["status"=>"error","message"=>"Prepare failed: ".mysqli_error($conn)]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $pid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $comments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $name = trim($row['by_name'] ?? '');
        $comments[] = [
            "by"   => ($name !== '' ? $name : 'Unknown'),
            "date" => $row['ts'],
            "text" => $row['content'],
        ];
    }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);

    echo json_encode($comments, JSON_UNESCAPED_UNICODE);
}

/* ====================== add comment ====================== */
elseif ($mode === 'add_comment') {
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    if ($sessionUserId <= 0) {
        echo json_encode(["status" => "error", "message" => "Not logged in"]);
        exit;
    }
    if ($pid <= 0) {
        echo json_encode(["status"=>"error","message"=>"Invalid PID"]);
        exit;
    }
    if ($content === '') {
        echo json_encode(["status"=>"error","message"=>"Empty comment"]);
        exit;
    }

    $sql  = "INSERT INTO comment (userID, PID, content, `timestamp`) VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(["status"=>"error","message"=>"Prepare failed: ".mysqli_error($conn)]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "iis", $sessionUserId, $pid, $content);
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_error($conn);
    mysqli_stmt_close($stmt);

    echo json_encode($ok ? ["status"=>"success"] : ["status"=>"error","message"=>"Insert failed: ".$err]);
}


/* ====================== report (latest) ====================== */
elseif ($mode === 'report') {
    $sql = "
        SELECT reportID, note, filePath, `timestamp`
        FROM report
        WHERE PID = $pid
        ORDER BY `timestamp` DESC, reportID DESC
        LIMIT 1
    ";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        echo json_encode([
            "status"    => "success",
            "reportID"  => (int)$row["reportID"],
            "note"      => $row["note"],
            "filePath"  => $row["filePath"],
            "timestamp" => $row["timestamp"]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    if ($result) mysqli_free_result($result);
}

mysqli_close($conn);
