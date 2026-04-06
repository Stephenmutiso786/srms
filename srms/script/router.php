<?php
// Router for PHP's built-in server that mimics the Apache .htaccess rewrite rules.
// Run: php -S localhost:8000 router.php

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$requestPath = urldecode($requestPath);

// Serve existing files directly.
if ($requestPath !== '/' && file_exists(__DIR__ . $requestPath)) {
    return false;
}

// If requesting a directory, serve its index.php if present.
if ($requestPath !== '/' && is_dir(__DIR__ . $requestPath) && file_exists(__DIR__ . $requestPath . '/index.php')) {
    require __DIR__ . $requestPath . '/index.php';
    exit;
}

// Apache rule: if "<path>.php" exists, route to it.
$phpPath = __DIR__ . $requestPath . '.php';
if ($requestPath !== '/' && file_exists($phpPath)) {
    require $phpPath;
    exit;
}

require __DIR__ . '/index.php';

