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
    margin: 0; /* 🔥 يلغي الحواف */
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

$dompdf->stream("TANAFS_Report.pdf", ["Attachment"=>false]);
exit;