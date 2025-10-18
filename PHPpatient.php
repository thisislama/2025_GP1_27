<?php

header('Content-Type: application/json; charset=utf-8');//tells the browser that the type of content being sent by PHP is JSON.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');//Do not cache this page or use an old copy of it. Always fetch a fresh version from the server each time.
header('Expires: 0');

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
   

   
    $sql = "SELECT c.content, c.date, c.createdBy, u.first_name, u.last_name
            FROM comment c
            LEFT JOIN user u ON u.userID = c.userID
            WHERE c.PID = $pid
            ORDER BY c.date DESC, c.CommentID DESC
            ";
    $result = mysqli_query($connection, $sql);

    $comments = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $by = $row['createdBy'];
            if (!$by) {
                $fn = $row['first_name'] ?? '';
                $ln = $row['last_name'] ?? '';
                $by = trim("$fn $ln") ?: 'Unknown';
            }
            $comments[] = [
                "by"   => $by,
                "date" => $row['date'],
                "text" => $row['content'],
            ];
        }
    }
    echo json_encode($comments);
    if ($result) mysqli_free_result($result);
}

elseif ($mode === 'add_comment') {
  
  $content = isset($_POST['content']) ? trim($_POST['content']) : '';
  $userID  = isset($_POST['userID']) ? (int)$_POST['userID'] : 1; // مؤقتًا 1

 

 
  $createdBy = 'Unknown';
  $uRes = mysqli_query($connection, "SELECT CONCAT(first_name,' ',last_name) AS full_name FROM user WHERE userID = $userID LIMIT 1");
  if ($uRes && mysqli_num_rows($uRes) > 0) {
    $uRow = mysqli_fetch_assoc($uRes);
    $createdBy = trim($uRow['full_name']) ?: 'Unknown';
  }
  if ($uRes) mysqli_free_result($uRes);

 
  $date = date('Y-m-d');
  $sql  = "INSERT INTO comment (userID, PID, createdBy, content, `date`) VALUES (?, ?, ?, ?, ?)";
  $stmt = mysqli_prepare($connection, $sql);
  if (!$stmt) {
    echo json_encode(["status"=>"error","message"=>"Prepare failed: ".mysqli_error($connection)]);
    exit;
  }

  mysqli_stmt_bind_param($stmt, "iisss", $userID, $pid, $createdBy, $content, $date);
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
