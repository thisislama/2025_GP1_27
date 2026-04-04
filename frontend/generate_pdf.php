<?php
require __DIR__ . "/../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$pid = (int)($_GET['pid'] ?? 0);

// نخلي الصفحة تعرف انها PDF
$_GET['pdf'] = 1;

ob_start();
include __DIR__ . "/ReportPage.php";
$html = ob_get_clean();
$html = '<html><head>
<meta charset="UTF-8">
<style>
@page {
    size: A4;
    margin: 0; 
}

html, body {
    margin: 0;
    padding: 0;
}
</style>
</head><body>' . $html . '</body></html>';
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'DejaVu Sans'); // 🔥 مهم
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper("A4","portrait");
$dompdf->render();

/* =========================
   🔹 1. نطلع محتوى الـ PDF
========================= */
$output = $dompdf->output();

/* =========================
/* =========================
   🔹 2. نحفظه في السيرفر
========================= */
$fileName = "report_" . $pid . "_" . time() . ".pdf";

$dir = "C:/MAMP/htdocs/2025_GP_27/frontend/reports/"; // 🔥 مسار ثابت

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$filePath = $dir . $fileName;

file_put_contents($filePath, $output);

if (!file_exists($filePath)) {
    die("❌ ما انحفظ");
}
/* =========================
/* =========================
   🔹 3. نحفظه في الداتابيس
========================= */

$conn = mysqli_connect("localhost","root","root","tanafs");

$note = "Auto generated report";

// 🔥 لازم تعرفه قبل
$relativePath = "frontend/reports/" . $fileName;

// تحقق هل فيه تقرير قديم
$check = mysqli_query($conn, "SELECT filePath FROM report WHERE PID = $pid LIMIT 1");

if ($row = mysqli_fetch_assoc($check)) {

    // حذف الملف القديم
    $oldPath = "C:/MAMP/htdocs/2025_GP_27/" . $row['filePath'];
    if (file_exists($oldPath)) {
        unlink($oldPath);
    }

    // تحديث
    $stmt = mysqli_prepare($conn, "
        UPDATE report SET filePath=?, timestamp=NOW()
        WHERE PID=?
    ");

    mysqli_stmt_bind_param($stmt, "si", $relativePath, $pid);
    mysqli_stmt_execute($stmt);

} else {

    // أول مرة
    $stmt = mysqli_prepare($conn, "
        INSERT INTO report (PID, note, filePath, timestamp)
        VALUES (?, ?, ?, NOW())
    ");

    mysqli_stmt_bind_param($stmt, "iss", $pid, $note, $relativePath);
    mysqli_stmt_execute($stmt);
}
/* =========================
   🔹 4. عرض الـ PDF
========================= */
$dompdf->stream($fileName, ["Attachment"=>false]);

exit;