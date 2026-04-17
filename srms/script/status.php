<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');

$started = microtime(true);
$status = [
    'app_ok' => true,
    'db_ok' => false,
    'db_error' => '',
    'db_latency_ms' => null,
    'total_latency_ms' => null,
    'time' => date('c'),
    'driver' => DBDriver,
    'app' => APP_NAME,
];

try {
    $dbStarted = microtime(true);
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query('SELECT 1');
    $status['db_ok'] = true;
    $status['db_latency_ms'] = round((microtime(true) - $dbStarted) * 1000, 2);
} catch (Throwable $e) {
    $status['db_ok'] = false;
    $status['db_error'] = 'Database unreachable';
    error_log('[status] ' . $e->getMessage());
}

$status['total_latency_ms'] = round((microtime(true) - $started) * 1000, 2);
$overallOk = ($status['app_ok'] && $status['db_ok']);

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="./">
<title><?php echo h(APP_NAME); ?> - System Status</title>
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="icon" href="images/icon.ico">
<style>
body.status-page { background: linear-gradient(180deg, #edf4f0 0%, #f7fbf9 50%, #ebf2ef 100%); }
.status-wrap { max-width: 980px; margin: 0 auto; padding: 24px 16px 40px; }
.status-hero { border-radius: 20px; color: #fff; padding: 22px; background: linear-gradient(135deg, #085046 0%, #0b7a6f 58%, #0e8fb2 100%); box-shadow: 0 22px 48px rgba(7, 56, 49, .18); }
.status-kicker { font-size: .75rem; text-transform: uppercase; letter-spacing: .1em; font-weight: 800; opacity: .84; }
.status-title { font-size: clamp(1.35rem, 2.8vw, 2rem); font-weight: 900; margin: 4px 0 6px; letter-spacing: -.03em; }
.status-sub { margin: 0; opacity: .95; line-height: 1.6; }
.status-grid { margin-top: 16px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.status-card { background: #fff; border: 1px solid #deeaE5; border-radius: 16px; padding: 16px; box-shadow: 0 10px 22px rgba(12, 44, 35, .08); }
.status-card .label { text-transform: uppercase; letter-spacing: .08em; font-size: .72rem; color: #6f7e8e; }
.status-card .value { margin-top: 5px; font-size: 1.15rem; font-weight: 800; color: #15352f; }
.pill { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 7px 12px; font-weight: 800; font-size: .83rem; }
.pill-ok { background: #e8f7ed; color: #1d7d42; border: 1px solid #cde9d7; }
.pill-fail { background: #fdeceb; color: #b4433a; border: 1px solid #f4cfcb; }
.actions { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }
.actions .btn { border-radius: 10px; font-weight: 700; }
.health-panel { margin-top: 16px; background: #fff; border: 1px solid #deeaE5; border-radius: 16px; padding: 16px; box-shadow: 0 10px 22px rgba(12, 44, 35, .08); }
.json-box { background: #0f1d1a; color: #d8f4ea; border-radius: 12px; padding: 12px; font-family: monospace; font-size: .85rem; overflow-x: auto; }
@media (max-width: 860px) { .status-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .status-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body class="app status-page">
<div class="status-wrap">
    <section class="status-hero">
        <div class="status-kicker">Monitoring</div>
        <h1 class="status-title">System Status</h1>
        <p class="status-sub">Use this page when the site seems down. It checks application and database readiness in real time.</p>
        <div class="actions">
            <a class="btn btn-light btn-sm" href="index.php"><i class="bi bi-box-arrow-left me-1"></i>Back to Login</a>
            <a class="btn btn-outline-light btn-sm" href="api/health" target="_blank"><i class="bi bi-activity me-1"></i>API Basic Health</a>
            <a class="btn btn-outline-light btn-sm" href="api/health?deep=1" target="_blank"><i class="bi bi-heart-pulse me-1"></i>API Deep Health</a>
        </div>
    </section>

    <div class="status-grid">
        <div class="status-card">
            <div class="label">Overall</div>
            <div class="value">
                <?php if ($overallOk): ?>
                    <span class="pill pill-ok"><i class="bi bi-check-circle-fill"></i>Operational</span>
                <?php else: ?>
                    <span class="pill pill-fail"><i class="bi bi-x-octagon-fill"></i>Degraded</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="status-card">
            <div class="label">Application</div>
            <div class="value"><span class="pill pill-ok"><i class="bi bi-check-circle-fill"></i>Running</span></div>
        </div>
        <div class="status-card">
            <div class="label">Database</div>
            <div class="value">
                <?php if ($status['db_ok']): ?>
                    <span class="pill pill-ok"><i class="bi bi-check-circle-fill"></i>Connected</span>
                <?php else: ?>
                    <span class="pill pill-fail"><i class="bi bi-x-octagon-fill"></i>Unavailable</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="status-card">
            <div class="label">DB Latency</div>
            <div class="value"><?php echo $status['db_latency_ms'] !== null ? h($status['db_latency_ms']) . ' ms' : 'N/A'; ?></div>
        </div>
        <div class="status-card">
            <div class="label">Total Latency</div>
            <div class="value"><?php echo h($status['total_latency_ms']); ?> ms</div>
        </div>
        <div class="status-card">
            <div class="label">Time (UTC)</div>
            <div class="value"><?php echo h(gmdate('Y-m-d H:i:s')); ?></div>
        </div>
    </div>

    <section class="health-panel">
        <h3 class="mb-2">Diagnostic Payload</h3>
        <div class="json-box"><?php echo h(json_encode([
            'ok' => $overallOk,
            'app' => $status['app'],
            'driver' => $status['driver'],
            'time' => $status['time'],
            'checks' => [
                'application' => ['ok' => true],
                'database' => [
                    'ok' => $status['db_ok'],
                    'latency_ms' => $status['db_latency_ms'],
                    'error' => $status['db_error'] !== '' ? $status['db_error'] : null,
                ],
            ],
            'latency_ms' => $status['total_latency_ms'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
    </section>
</div>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
