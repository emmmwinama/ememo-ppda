<?php
require_once __DIR__ . '/libs/libs/mpdf/autoload.php'; 

use Mpdf\Mpdf;

$mpdf = new Mpdf();
$mpdf->WriteHTML('<h1>Hello from mPDF</h1><p>If you see this, autoload works.</p>');
$mpdf->Output();
