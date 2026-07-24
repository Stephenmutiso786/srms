<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || !in_array((int)$level, [0, 1, 9], true)) {
  header('location:../');
  exit;
}

function app_data_camp_extract_code(string $url, string $key = 'code'): string
{
  $query = (string)parse_url($url, PHP_URL_QUERY);
  if ($query === '') {
    return '';
  }

  parse_str($query, $params);
  return trim((string)($params[$key] ?? ''));
}

function app_data_camp_redirect(string $target): void
{
  if (trim($target) === '') {
    header('location:data_camp');
    exit;
  }

  header('location:' . $target);
  exit;
}

function app_data_camp_app_url(string $path): string
{
  $baseDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/srms/script/admin/data_camp_open.php'), 2)), '/');
  return $baseDir . '/' . ltrim($path, '/');
}

function app_data_camp_render_metadata_view(array $record, array $payload): void
{
  $prettyPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if (!is_string($prettyPayload) || $prettyPayload === '') {
    $prettyPayload = '{}';
  }
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Archived Record</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f4f7fb; color:#1f2937; margin:0; padding:24px; }
    .card { max-width:980px; margin:0 auto; background:#fff; border-radius:16px; box-shadow:0 16px 48px rgba(15,23,42,.08); padding:24px; }
    h1 { margin:0 0 8px; font-size:28px; }
    .meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; margin:20px 0; }
    .meta div { background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:12px 14px; }
    .label { display:block; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; margin-bottom:6px; }
    pre { background:#0f172a; color:#e2e8f0; border-radius:12px; padding:18px; overflow:auto; white-space:pre-wrap; word-break:break-word; }
    .muted { color:#64748b; }
  </style>
</head>
<body>
  <div class="card">
    <h1><?php echo htmlspecialchars((string)($record['title'] ?? 'Archived Record')); ?></h1>
    <p class="muted"><?php echo htmlspecialchars((string)($record['description'] ?? 'Stored archive metadata for this system event.')); ?></p>
    <div class="meta">
      <div><span class="label">Record Type</span><?php echo htmlspecialchars((string)($record['record_type'] ?? '')); ?></div>
      <div><span class="label">Status</span><?php echo htmlspecialchars((string)($record['status'] ?? '')); ?></div>
      <div><span class="label">Created At</span><?php echo htmlspecialchars((string)($record['created_at'] ?? '')); ?></div>
      <div><span class="label">Entity ID</span><?php echo htmlspecialchars((string)($record['entity_id'] ?? '')); ?></div>
      <div><span class="label">Student ID</span><?php echo htmlspecialchars((string)($record['student_id'] ?? '')); ?></div>
      <div><span class="label">Class ID</span><?php echo htmlspecialchars((string)($record['class_id'] ?? '')); ?></div>
    </div>
    <h3>Stored Metadata</h3>
    <pre><?php echo htmlspecialchars($prettyPayload); ?></pre>
  </div>
</body>
</html>
  <?php
  exit;
}

$recordId = (int)($_GET['id'] ?? 0);
if ($recordId < 1) {
  header('location:data_camp');
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_data_camp_schema($conn);

  $stmt = $conn->prepare("SELECT * FROM tbl_data_camp_records WHERE id = ? LIMIT 1");
  $stmt->execute([$recordId]);
  $record = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$record) {
    $_SESSION['reply'] = array(array('warning', 'The requested archive record could not be found.'));
    header('location:data_camp');
    exit;
  }

  $filePath = trim((string)($record['file_path'] ?? ''));
  if ($filePath !== '') {
    $normalizedPath = ltrim($filePath, '/');
    if (is_file($normalizedPath)) {
      app_data_camp_redirect(app_data_camp_app_url($normalizedPath));
    }
    app_data_camp_redirect($filePath);
  }

  $payload = app_data_camp_payload_array($record);

  $recordType = strtolower(trim((string)($record['record_type'] ?? '')));
  $sourceUrl = trim((string)($record['source_url'] ?? ''));
  $entityId = trim((string)($record['entity_id'] ?? ''));

  if ($recordType === 'report_card') {
    app_data_camp_redirect(app_data_camp_app_url('admin/data_camp_report_pdf.php?id=' . $recordId));
  }

  if ($recordType === 'certificate') {
    $certificateId = (int)$entityId;
    if ($certificateId > 0) {
      app_data_camp_redirect(app_data_camp_app_url('certificate_pdf.php?id=' . $certificateId));
    }

    $verificationCode = trim((string)($payload['verification_code'] ?? ''));
    if ($verificationCode === '' && $sourceUrl !== '') {
      $verificationCode = app_data_camp_extract_code($sourceUrl, 'code');
    }
    if ($verificationCode !== '') {
      app_data_camp_redirect(app_data_camp_app_url('verify_certificate?code=' . urlencode($verificationCode)));
    }
  }

  if ($sourceUrl !== '') {
    if ($recordType === 'report_card') {
      $verificationCode = app_data_camp_extract_code($sourceUrl, 'code');
      if ($verificationCode !== '') {
        app_data_camp_redirect(app_data_camp_app_url('verify_report_pdf.php?code=' . urlencode($verificationCode)));
      }
    }
    app_data_camp_redirect(app_data_camp_app_url($sourceUrl));
  }

  app_data_camp_render_metadata_view($record, $payload);
} catch (Throwable $e) {
  error_log('[admin/data_camp_open] ' . $e->getMessage());
  $_SESSION['reply'] = array(array('danger', 'Failed to open the archived record.'));
  header('location:data_camp');
  exit;
}
