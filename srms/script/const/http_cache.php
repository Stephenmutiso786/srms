<?php
/**
 * http_cache.php
 * 
 * Set optimal HTTP cache headers based on content type and request path.
 * Include this early in page load for maximum benefit.
 * 
 * PERF FIX: Reduces bandwidth and server load by enabling browser/CDN caching.
 * Previously, every request was revalidated against the server!
 */

function app_set_cache_headers(): void {
	// Determine content type and caching strategy
	$requestPath = strtolower($_SERVER['REQUEST_URI'] ?? '');
	$requestPath = explode('?', $requestPath)[0]; // Remove query string
	
	// Static assets: Cache aggressively (1 year)
	if (preg_match('/\.(js|css|img|jpg|jpeg|png|gif|ico|svg|webp|woff|woff2|ttf|eot)$/i', $requestPath)) {
		header('Cache-Control: public, max-age=31536000, immutable');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
		return;
	}
	
	// HTML pages: Cache with revalidation (1 hour with etag check)
	if (preg_match('/\.(php|html)$/i', $requestPath) || empty($requestPath) || $requestPath === '/') {
		// Check if this is an API/dynamic endpoint
		if (strpos($requestPath, '/api/') !== false || strpos($requestPath, '/app/') !== false) {
			// API responses: No cache - always fresh
			header('Cache-Control: no-cache, no-store, must-revalidate, private');
			header('Pragma: no-cache');
			header('Expires: 0');
		} else {
			// Regular HTML pages: Cache with validation (1 hour max)
			header('Cache-Control: public, max-age=3600, must-revalidate');
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
		}
		return;
	}
	
	// Default: Cache for 1 hour
	header('Cache-Control: public, max-age=3600');
}

// Apply headers immediately
app_set_cache_headers();
