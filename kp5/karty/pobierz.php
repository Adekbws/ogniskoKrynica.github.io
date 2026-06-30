<?php
declare(strict_types=1);

$apartmentNo = filter_input(INPUT_GET, 'lokal', FILTER_VALIDATE_INT);
if ($apartmentNo === false || $apartmentNo === null || $apartmentNo < 1 || $apartmentNo > 22) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nie znaleziono karty lokalu.';
    exit;
}

$dir = __DIR__ . '/../assets/karty/materialy_mail';
$suffix = '-LU' . $apartmentNo . '.pdf';
$matches = glob($dir . '/*' . $suffix) ?: [];
$path = null;

foreach ($matches as $candidate) {
    if (is_file($candidate)) {
        $path = $candidate;
        break;
    }
}

if ($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nie znaleziono karty lokalu.';
    exit;
}

$filename = 'Apartamenty-Ognisko-lokal-' . $apartmentNo . '.pdf';
$size = filesize($path);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
if (is_int($size)) {
    header('Content-Length: ' . (string) $size);
}
header('Cache-Control: public, max-age=86400');

readfile($path);
