<?php
$conn = mysqli_connect("localhost","root","root","tanafs");
if (!$conn) { header('Content-Type: application/json'); echo json_encode(["error"=>"DB connection failed"]); exit; }

$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;


// Patient name
$pN = mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT CONCAT(first_name,' ',last_name) AS name FROM patient WHERE PID=$pid "));

//Doctor name
$u = mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT CONCAT(first_name,' ',last_name) AS name FROM user"));

// last three analsis wevform
$aRes = mysqli_query($conn,
  "SELECT anomaly_type, severity_level
   FROM waveform_analysis
   WHERE PID=$pid
   ORDER BY `timestamp` DESC
   LIMIT 3");

$waves = [];
if ($aRes) {
  while($row = mysqli_fetch_assoc($aRes)) {
    $waves[] = [
      "anomaly" => $row["anomaly_type"] ?: "Normal",
      "level"   => $row["severity_level"] ?: "-"
    ];
  }
}

// Last comment
$c = mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT content
   FROM comment
   WHERE PID=$pid
   ORDER BY `date` DESC, CommentID DESC
   LIMIT 1"));

$lastStatus = $waves[0]['anomaly'] ?? 'Normal';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  "patient"     => $pN['name'] ?? 'Unknown',
  "createdBy"   => $u['name'] ?? 'System',
  "date"        => date('Y-m-d'),
  "lastStatus"  => $lastStatus,
  "lastComment" => $c['content'] ?? '-',
  "waves"       => $waves
]);
