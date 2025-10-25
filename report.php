<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: 0');

session_start(); // ✅ مهم

date_default_timezone_set('Asia/Riyadh');

$conn = mysqli_connect("localhost","root","root","tanafs");
if (!$conn) { echo json_encode(["error"=>"DB connection failed"]); exit; }

$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
// Patient name
$pN = mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT CONCAT(first_name,' ',last_name) AS name FROM patient WHERE PID=$pid "));

$u = mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT CONCAT(first_name,' ',last_name) AS name FROM `user` WHERE userID = {$sessionUserId} "
));

// آخر ثلاث تحليلات + التوقيت
$aRes = mysqli_query($conn,
  "SELECT anomaly_type, severity_level, `timestamp` AS ts
   FROM waveform_analysis
   WHERE PID=$pid
   ORDER BY `timestamp` DESC
   LIMIT 3");

$waves = [];
if ($aRes) {
  while ($row = mysqli_fetch_assoc($aRes)) {
    $anomaly = isset($row['anomaly_type']) ? trim($row['anomaly_type']) : '';
    if ($anomaly === '' || strcasecmp($anomaly, 'None') === 0) $anomaly = 'Normal';
    $level = isset($row['severity_level']) && $row['severity_level'] !== '' ? $row['severity_level'] : '-';

    $waves[] = [
      "anomaly"    => $anomaly,
      "level"      => $level,
      "timestamp"  => $row['ts']  // ✅ نرجع الوقت لكل موجة
    ];
  }
}

// كل التعليقات + التوقيت + اسم الكاتب
$cRes = mysqli_query($conn, "
  SELECT 
    c.content,
    c.`timestamp` AS ts,
    CONCAT_WS(' ', hp.first_name, hp.last_name) AS by_name
  FROM comment c
  LEFT JOIN healthcareprofessional hp ON hp.userID = c.userID
  WHERE c.PID = $pid
  ORDER BY c.`timestamp` DESC, c.CommentID DESC
");

$comments = [];
if ($cRes) {
  while ($row = mysqli_fetch_assoc($cRes)) {
    $comments[] = [
      "by"        => trim($row['by_name'] ?? '') !== '' ? $row['by_name'] : 'Unknown',
      "timestamp" => $row['ts'],
      "text"      => $row['content'] ?? ''
    ];
  }
}

echo json_encode([
  "patient"     => $pN['name'] ?? 'Unknown',
  "createdBy"   => $u['name'] ?? 'System',
  "date"        => date('Y-m-d H:i:s'),   // ✅ تاريخ إنشاء التقرير الآن

  "lastComment" => $comments[0]['text'] ?? '-', // للعرض المختصر لو احتجتي
  "waves"       => $waves,
  "comments"    => $comments
], JSON_UNESCAPED_UNICODE);
