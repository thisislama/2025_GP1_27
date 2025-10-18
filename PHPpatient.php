<?php

header('Content-Type: application/json; charset=utf-8');//tells the browser that the type of content being sent by PHP is JSON.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');//Do not cache this page or use an old copy of it. Always fetch a fresh version from the server each time.
header('Expires: 0');
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
$connection = mysqli_connect("localhost", "root", "root", "tanafs");
if (!$connection) {
    echo json_encode(["error" => "Connection failed: " . mysqli_connect_error()]);// arry have key error  and value connection faild ,json_encoded converts the array into JSON,.mysqli retrieves the error message from MySQL.
    exit;//stop code after send error
}

if (isset($_GET['pid'])) {
    $pid = (int)$_GET['pid'];
} else {
    $pid = 0;  // defult value
}

if (isset($_GET['mode'])) {
    $mode = $_GET['mode'];
} else {
    $mode = 'patient';  // defult value
}


/* ====================== patient name:====================== */
if ($mode === 'patient') {
    $sql = "SELECT first_name, last_name FROM patient WHERE PID = $pid";
    $result = mysqli_query($connection, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        echo json_encode(["name" => $row["first_name"] . " " . $row["last_name"]]);
    } else {
        echo json_encode(["name" => "Unknown"]);
    }
    if ($result) mysqli_free_result($result);
}

/* ====================== analysis table:  ====================== */
elseif ($mode === 'analysis') {
    $sql = "SELECT anomaly_type, severity_level, `timestamp`
            FROM waveform_analysis
            WHERE PID = $pid
            ORDER BY `timestamp` DESC";
    $result = mysqli_query($connection, $sql);
    $data = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) $data[] = $row;
    }
    echo json_encode($data);//converts the $data array into a JSON-formatted string
    if ($result) mysqli_free_result($result);
}

/* ====================== comments ====================== */
elseif ($mode === 'comments') {

    $sql = "
        SELECT 
            c.content,
            c.`date`,
            CONCAT_WS(' ', u.first_name, u.last_name) AS by_name
        FROM comment c
        LEFT JOIN `user` u ON u.userID = c.userID
        WHERE c.PID = $pid
        ORDER BY c.`date` DESC, c.CommentID DESC
    ";
    $result = mysqli_query($connection, $sql);

    if ($result === false) {
        echo json_encode(["status"=>"error","sql_error"=>mysqli_error($connection)]);
        exit;
    }

    $comments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $name = trim($row['by_name'] ?? '');
        $comments[] = [
            "by"   => ($name !== '' ? $name : 'Unknown'),
            "date" => $row['date'],
            "text" => $row['content'],
        ];
    }
    mysqli_free_result($result);

    echo json_encode($comments);
}


elseif ($mode === 'add_comment') {

  $content = isset($_POST['content']) ? trim($_POST['content']) : '';
  $userID  = isset($_POST['userID']) ? (int)$_POST['userID'] : 1; // مؤقتًا 1

  if ($pid <= 0) { 
    echo json_encode(["status"=>"error","message"=>"Invalid PID"]); 
    exit; 
  }
  if ($content === '') { 
    echo json_encode(["status"=>"error","message"=>"Empty comment"]); 
    exit; 
  }

  $date = date('Y-m-d'); 

  
  $sql  = "INSERT INTO comment (userID, PID, content, `date`) VALUES (?, ?, ?, ?)";
  $stmt = mysqli_prepare($connection, $sql);
  if (!$stmt) {
    echo json_encode(["status"=>"error","message"=>"Prepare failed: ".mysqli_error($connection)]);
    exit;
  }

  mysqli_stmt_bind_param($stmt, "iiss", $userID, $pid, $content, $date);
  $ok  = mysqli_stmt_execute($stmt);
  $err = mysqli_error($connection);
  mysqli_stmt_close($stmt);

  echo json_encode($ok ? ["status"=>"success"] : ["status"=>"error","message"=>"Insert failed: ".$err]);
}

/* ====================== report:====================== */
elseif ($mode === 'report') {
    $pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
  

    $sql = "
        SELECT reportID, note, filePath, `timestamp`
        FROM report
        WHERE PID = $pid
        ORDER BY `timestamp` DESC, reportID DESC
        LIMIT 1
    ";
    $result = mysqli_query($connection, $sql);

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



mysqli_close($connection);
