<?php
// Router for PHP's built-in server that mimics the Apache .htaccess rewrite rules.
// Run: php -S localhost:8000 router.php

$docRoot = realpath(__DIR__) ?: __DIR__;
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$requestPath = urldecode($requestPath);
$requestPath = is_string($requestPath) ? ('/' . ltrim($requestPath, '/')) : '/';

if (strpos($requestPath, "\0") !== false) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

/**
 * Resolve a request path to a real file system path and ensure it stays under doc root.
 */
function router_path_within_root(string $docRoot, string $path): ?string
{
    $resolved = realpath($docRoot . $path);
    if ($resolved === false) {
        return null;
    }

    if ($resolved === $docRoot || strpos($resolved, $docRoot . DIRECTORY_SEPARATOR) === 0) {
        return $resolved;
    }

    return null;
}

// Serve existing files directly.
$resolvedRequest = ($requestPath !== '/') ? router_path_within_root($docRoot, $requestPath) : null;
if ($resolvedRequest !== null && is_file($resolvedRequest)) {
    return false;
}

// If requesting a directory, serve its index.php if present.
if ($resolvedRequest !== null && is_dir($resolvedRequest) && is_file($resolvedRequest . '/index.php')) {
    require $resolvedRequest . '/index.php';
    exit;
}

// Apache rule: if "<path>.php" exists, route to it.
$resolvedPhpPath = ($requestPath !== '/') ? router_path_within_root($docRoot, $requestPath . '.php') : null;
if ($resolvedPhpPath !== null && is_file($resolvedPhpPath)) {
    require $resolvedPhpPath;
    exit;
}

require $docRoot . '/index.php';

