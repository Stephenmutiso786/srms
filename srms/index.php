<?php
$target = 'script/index.php';

if (!headers_sent()) {
	header('Location: ' . $target, true, 302);
	exit;
}

echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"><title>Redirecting...</title></head><body><a href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">Open the application</a></body></html>';