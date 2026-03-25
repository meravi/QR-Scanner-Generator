<?php

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$fileName = basename((string) ($_GET['download'] ?? ''));

if ($fileName === '' || !preg_match('/\A[a-zA-Z0-9._-]+\.png\z/', $fileName)) {
    http_response_code(400);
    exit('Invalid file name.');
}

$baseDirectory = __DIR__ . '/userQr';
$filePath = $baseDirectory . '/' . $fileName;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: image/png');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: attachment; filename="' . $fileName . '"');

readfile($filePath);
exit;
