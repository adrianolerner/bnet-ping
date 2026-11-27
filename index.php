<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/.well-known/security.txt') !== false || strpos($requestUri, '/security.txt') !== false) {
    header('Content-Type: text/plain; charset=utf-8');
    if (file_exists(__DIR__ . '/.well-known/security.txt')) {
        readfile(__DIR__ . '/.well-known/security.txt');
    } elseif (file_exists(__DIR__ . '/public/.well-known/security.txt')) {
        readfile(__DIR__ . '/public/.well-known/security.txt');
    }
    exit();
}

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /public/index.php" . $qs);
exit();
?>
