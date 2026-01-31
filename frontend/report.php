<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: 0');

session_start();
date_default_timezone_set('Asia/Riyadh');

ini_set('display_errors', '0');
error_reporting(E_ALL);

$conn = mysqli_connect("localhost","root","root","tanafs");
if (!$conn) {
  echo json_encode(["status"=>"error","message"=>"DB connection failed"]);
  exit;
}

$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// -- Patient info
$patient = [
  "pid" => $pid, "name" => "Unknown", "gender" => "-", "phone" => "-", "dob" => "-"
];
if ($pid > 0) {
  $pRes = mysqli_query($conn, "SELECT first_name,last_name,gender,phone,DOB FROM patient WHERE PID=$pid");
  if ($pRes && $row = mysqli_fetch_assoc($pRes)) {
    $patient["name"]   = trim($row['first_name']." ".$row['last_name']);
    $patient["gender"] = $row['gender'] ?? "-";
    $patient["phone"]  = $row['phone'] ?? "-";
    $patient["dob"]    = $row['DOB']   ?? "-";
  }
  if ($pRes) mysqli_free_result($pRes);
}

// -- Creator (healthcare professional) info
$creator = ["name"=>"System","role"=>"-","phone"=>"-"];
if ($sessionUserId > 0) {
  $uRes = mysqli_query($conn, "SELECT CONCAT(first_name,' ',last_name) AS name, role, phone FROM healthcareprofessional WHERE userID={$sessionUserId}");
  if ($uRes && $u = mysqli_fetch_assoc($uRes)) {
    $creator["name"]  = $u["name"]  ?: "System";
    $creator["role"]  = $u["role"]  ?: "-";
    $creator["phone"] = $u["phone"] ?: "-";
  }
  if ($uRes) mysqli_free_result($uRes);
}

// -- Last 3 waves
$waves = [];
$aRes = mysqli_query($conn, "
  SELECT anomaly_type,`timestamp` AS ts
  FROM waveform_analysis
  WHERE PID=$pid
  ORDER BY `timestamp` DESC
");
if ($aRes) {
 while ($row = mysqli_fetch_assoc($aRes)) {
  $anomaly = trim($row['anomaly_type'] ?? '');
  if ($anomaly === '' || strcasecmp($anomaly, 'None') === 0) {
    $anomaly = 'Normal';
  }

  $waves[] = [
    "anomaly"   => $anomaly,
    "timestamp" => $row["ts"]
  ];
}

  mysqli_free_result($aRes);
}

// -- All comments
$comments = [];
$cRes = mysqli_query($conn, "
  SELECT c.content, c.`timestamp` AS ts,
         CONCAT_WS(' ', hp.first_name, hp.last_name) AS by_name
  FROM comment c
  LEFT JOIN healthcareprofessional hp ON hp.userID = c.userID
  WHERE c.PID = $pid
  ORDER BY c.`timestamp` DESC, c.CommentID DESC
");
if ($cRes) {
  while ($row = mysqli_fetch_assoc($cRes)) {
    $comments[] = [
      "by"        => (trim($row['by_name'] ?? '') !== '') ? $row['by_name'] : 'Unknown',
      "timestamp" => $row['ts'],
      "text"      => $row['content'] ?? ''
    ];
  }
  mysqli_free_result($cRes);
}

// -- Response
echo json_encode([
  "status"      => "success",
  "patient"     => $patient["name"],
  "patientId"   => $patient["pid"],
  "gender"      => $patient["gender"],
  "phone"       => $patient["phone"],
  "dob"         => $patient["dob"],
  "createdBy"   => $creator["name"],
  "creatorRole" => $creator["role"],
  "creatorPhone"=> $creator["phone"],
  "date"        => date('Y-m-d H:i:s'),
  "waves"       => $waves,
  "comments"    => $comments
], JSON_UNESCAPED_UNICODE);

mysqli_close($conn);
