<?php
// Minimal Accounting API scaffold
require_once(__DIR__ . '/../db/config.php');
require_once(__DIR__ . '/../core/app.php');

header('Content-Type: application/json');

try {
  $action = $_GET['action'] ?? 'summary';
  if ($action === 'summary') {
    // return placeholder summary
    echo json_encode(['ok' => true, 'accounts' => 0, 'entries' => 0]);
    exit;
  }
  if ($action === 'entries') {
    // very small safe query example
    $stmt = $conn->prepare('SELECT id, date, description, debit, credit FROM tbl_gl_entries ORDER BY date DESC LIMIT 100');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'entries' => $rows]);
    exit;
  }
  echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
