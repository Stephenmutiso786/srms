<?php

function app_certificate_types(): array
{
    return [
        'leaving' => 'Leaving Certificate',
        'completion' => 'Completion Certificate',
        'conduct' => 'Good Conduct Certificate',
        'bonafide' => 'Bonafide Student Certificate',
    ];
}

function app_certificate_hash(array $payload): string
{
    $secret = defined('APP_SECRET') && APP_SECRET !== '' ? APP_SECRET : 'elimu-hub';
    return hash('sha256', json_encode($payload) . '|' . $secret);
}

function app_certificate_serial(string $type, string $studentId): string
{
    $prefix = strtoupper(substr($type, 0, 3));
    return 'CERT-' . $prefix . '-' . date('Y') . '-' . preg_replace('/[^A-Za-z0-9]/', '', $studentId) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

function app_certificate_code(string $studentId): string
{
    return 'CERTV-' . date('Y') . '-' . preg_replace('/[^A-Za-z0-9]/', '', $studentId) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function app_certificate_verify_url(string $code): string
{
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim((string)APP_URL, '/') . '/verify_certificate?code=' . urlencode($code);
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return 'http://' . $host . '/verify_certificate?code=' . urlencode($code);
}
