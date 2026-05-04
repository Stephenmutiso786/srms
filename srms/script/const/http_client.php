<?php

function app_http_request(string $method, string $url, $body = null, array $headers = [], int $timeout = 20, ?array $basicAuth = null): array
{
    $method = strtoupper($method);
    $hdrs = [];
    foreach ($headers as $h) {
        $hdrs[] = $h;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($basicAuth && isset($basicAuth['user']) && isset($basicAuth['pass'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth['user'] . ':' . $basicAuth['pass']);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if (!empty($hdrs)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $respBody = curl_exec($ch);
        $err = null;
        if ($respBody === false) {
            $err = curl_error($ch);
        }
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['http_code' => $httpCode, 'body' => $respBody, 'error' => $err];
    }

    // stream_context fallback
    $options = ['http' => ['method' => $method, 'header' => '', 'timeout' => $timeout, 'ignore_errors' => true]];
    if (!empty($hdrs)) { $options['http']['header'] = implode("\r\n", $hdrs) . "\r\n"; }
    if ($basicAuth && isset($basicAuth['user']) && isset($basicAuth['pass'])) {
        $b64 = base64_encode($basicAuth['user'] . ':' . $basicAuth['pass']);
        $options['http']['header'] = ($options['http']['header'] ?? '') . "Authorization: Basic $b64\r\n";
    }
    if ($body !== null) { $options['http']['content'] = $body; }

    $context = stream_context_create($options);
    $resp = @file_get_contents($url, false, $context);
    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
    return ['http_code' => $httpCode, 'body' => $resp, 'error' => null];
}

function app_http_post_json(string $url, string $jsonPayload, array $headers = [], int $timeout = 20): array
{
    $hdrs = $headers;
    $hasContentType = false;
    foreach ($hdrs as $h) {
        if (stripos($h, 'content-type:') === 0) { $hasContentType = true; break; }
    }
    if (!$hasContentType) { $hdrs[] = 'Content-Type: application/json'; }
    return app_http_request('POST', $url, $jsonPayload, $hdrs, $timeout);
}
