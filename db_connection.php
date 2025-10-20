<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "127.0.0.1";  // استخدم 127.0.0.1 بدل localhost
$username   = "root";
$password   = "root";       // كلمة المرور الافتراضية في MAMP على Windows وmacOS
$dbname     = "tanafs";
$port       = 3306;

try {
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
