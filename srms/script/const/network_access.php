<?php

if (!function_exists('app_network_is_private_ipv4')) {
	function app_network_is_private_ipv4(string $ip): bool
	{
		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			return false;
		}

		$long = ip2long($ip);
		if ($long === false) {
			return false;
		}

		$ranges = [
			['10.0.0.0', '10.255.255.255'],
			['172.16.0.0', '172.31.255.255'],
			['192.168.0.0', '192.168.255.255'],
		];

		foreach ($ranges as $range) {
			$start = ip2long($range[0]);
			$end = ip2long($range[1]);
			if ($start !== false && $end !== false && $long >= $start && $long <= $end) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('app_network_ip_rank')) {
	function app_network_ip_rank(string $ip): int
	{
		if (app_network_is_private_ipv4($ip)) {
			return 1;
		}

		if ($ip === '127.0.0.1') {
			return 3;
		}

		if (strpos($ip, '169.254.') === 0) {
			return 4;
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			return 2;
		}

		return 5;
	}
}

if (!function_exists('app_network_extract_ipv4_list')) {
	function app_network_extract_ipv4_list(string $text): array
	{
		$ips = [];
		if ($text === '') {
			return $ips;
		}

		if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text, $matches)) {
			foreach ((array)($matches[0] ?? []) as $match) {
				$ip = trim((string)$match);
				if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
					continue;
				}
				if ($ip === '0.0.0.0') {
					continue;
				}
				if (preg_match('/^(255\.|127\.0\.1\.1$)/', $ip)) {
					continue;
				}
				if (preg_match('/\.(0|255)$/', $ip)) {
					continue;
				}
				$ips[] = $ip;
			}
		}

		return array_values(array_unique($ips));
	}
}

if (!function_exists('app_network_shell_output')) {
	function app_network_shell_output(string $command): string
	{
		if (!function_exists('shell_exec')) {
			return '';
		}

		$output = @shell_exec($command);
		return is_string($output) ? trim($output) : '';
	}
}

if (!function_exists('app_network_candidate_ips')) {
	function app_network_candidate_ips(): array
	{
		$candidates = [];
		$directCandidates = [
			(string)($_SERVER['SERVER_ADDR'] ?? ''),
			(string)gethostbyname((string)gethostname()),
			(string)php_uname('n'),
		];

		foreach ($directCandidates as $candidate) {
			foreach (app_network_extract_ipv4_list($candidate) as $ip) {
				$candidates[] = $ip;
			}
		}

		$commandOutputs = [
			app_network_shell_output('hostname -I 2>/dev/null'),
			app_network_shell_output('ipconfig 2>/dev/null 2>NUL'),
			app_network_shell_output('ip addr 2>/dev/null'),
			app_network_shell_output('ifconfig 2>/dev/null'),
		];

		foreach ($commandOutputs as $output) {
			foreach (app_network_extract_ipv4_list($output) as $ip) {
				$candidates[] = $ip;
			}
		}

		$candidates = array_values(array_unique(array_filter($candidates, static function ($ip): bool {
			return $ip !== '';
		})));

		$ranked = [];
		foreach ($candidates as $ip) {
			$ranked[app_network_ip_rank((string)$ip)][] = $ip;
		}
		ksort($ranked);

		$sorted = [];
		foreach ($ranked as $items) {
			foreach ($items as $ip) {
				$sorted[] = $ip;
			}
		}

		return $sorted;
	}
}

if (!function_exists('app_network_scheme')) {
	function app_network_scheme(): string
	{
		$proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? ''));
		$https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
		return ($proto === 'https' || $https === 'on' || $https === '1') ? 'https' : 'http';
	}
}

if (!function_exists('app_network_host_without_port')) {
	function app_network_host_without_port(string $host): string
	{
		$host = trim($host);
		if ($host === '') {
			return '';
		}

		if (strpos($host, ',') !== false) {
			$parts = array_values(array_filter(array_map('trim', explode(',', $host))));
			$host = (string)($parts[0] ?? '');
		}

		if ($host !== '' && $host[0] === '[') {
			$endPos = strpos($host, ']');
			if ($endPos !== false) {
				return substr($host, 1, $endPos - 1);
			}
		}

		if (substr_count($host, ':') === 1) {
			$parts = explode(':', $host, 2);
			return trim((string)($parts[0] ?? ''));
		}

		return $host;
	}
}

if (!function_exists('app_network_preferred_request_ip')) {
	function app_network_preferred_request_ip(): string
	{
		$hostCandidates = [
			(string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''),
			(string)($_SERVER['HTTP_HOST'] ?? ''),
		];

		foreach ($hostCandidates as $hostCandidate) {
			$host = app_network_host_without_port($hostCandidate);
			if (app_network_is_private_ipv4($host)) {
				return $host;
			}
		}

		$appUrlHost = app_network_host_without_port((string)(parse_url((string)APP_URL, PHP_URL_HOST) ?? ''));
		if (app_network_is_private_ipv4($appUrlHost)) {
			return $appUrlHost;
		}

		return '';
	}
}

if (!function_exists('app_network_server_port')) {
	function app_network_server_port(): int
	{
		$port = (int)($_SERVER['SERVER_PORT'] ?? 0);
		if ($port > 0) {
			return $port;
		}
		return app_network_scheme() === 'https' ? 443 : 80;
	}
}

if (!function_exists('app_network_script_base_path')) {
	function app_network_script_base_path(): string
	{
		$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
		if ($scriptName === '') {
			return '/script';
		}

		$scriptPos = strpos($scriptName, '/script/');
		if ($scriptPos !== false) {
			$basePath = substr($scriptName, 0, $scriptPos + 7);
			return $basePath === '' ? '/script' : rtrim($basePath, '/');
		}

		if (substr($scriptName, -7) === '/script') {
			return rtrim($scriptName, '/');
		}

		$dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
		return $dir === '' ? '/script' : $dir;
	}
}

if (!function_exists('app_network_access_data')) {
	function app_network_access_data(): array
	{
		$ips = app_network_candidate_ips();
		$preferredIp = app_network_preferred_request_ip();
		$ip = $preferredIp !== '' ? $preferredIp : (string)($ips[0] ?? '127.0.0.1');
		$scheme = app_network_scheme();
		$port = app_network_server_port();
		$path = app_network_script_base_path();
		$portSuffix = (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) ? '' : ':' . $port;
		$url = $scheme . '://' . $ip . $portSuffix . $path;

		return [
			'ip' => $ip,
			'ips' => $ips,
			'scheme' => $scheme,
			'port' => $port,
			'path' => $path,
			'url' => $url,
			'is_private_ip' => app_network_is_private_ipv4($ip),
			'is_loopback' => $ip === '127.0.0.1',
			'generated_at' => date('c'),
		];
	}
}
