<?php
chdir('../../../');
require_once('srms/script/db/config.php');
header('Content-Type: application/json; charset=utf-8');
try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $out = [];
    $out['students'] = app_table_exists($conn,'tbl_students') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn() : 0;
    $out['teachers'] = app_table_exists($conn,'tbl_staff') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn() : 0;
    $out['payments'] = app_table_exists($conn,'tbl_payments') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_payments")->fetchColumn() : 0;
    $out['invoices'] = app_table_exists($conn,'tbl_invoices') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_invoices")->fetchColumn() : 0;
    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
