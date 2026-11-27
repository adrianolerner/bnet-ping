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

if (strpos($requestUri, '/robots.txt') !== false) {
    header('Content-Type: text/plain; charset=utf-8');
    if (file_exists(__DIR__ . '/robots.txt')) {
        readfile(__DIR__ . '/robots.txt');
    } elseif (file_exists(__DIR__ . '/public/robots.txt')) {
        readfile(__DIR__ . '/public/robots.txt');
    }
    exit();
}

if (strpos($requestUri, '/sitemap.xml') !== false) {
    header('Content-Type: application/xml; charset=utf-8');
    if (file_exists(__DIR__ . '/sitemap.xml')) {
        readfile(__DIR__ . '/sitemap.xml');
    } elseif (file_exists(__DIR__ . '/public/sitemap.xml')) {
        readfile(__DIR__ . '/public/sitemap.xml');
    }
    exit();
}

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /public/index.php" . $qs);
exit();
?>
