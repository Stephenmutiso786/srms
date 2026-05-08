<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
$isSuperAdmin = !empty($super_admin);
if ($res !== "1" || (((int)$level !== 0 && !$isSuperAdmin) || !app_current_user_has_any_permission(['finance.manage']))) {
    app_reply_redirect('danger', 'Unauthorized.', '../expense_approvals');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_reply_redirect('danger', 'Invalid request.', '../expense_approvals');
}
$expenseId = (int)($_POST['expense_id'] ?? 0);
$decision = trim((string)($_POST['decision'] ?? ''));
$approvalNotes = trim((string)($_POST['approval_notes'] ?? ''));
if ($expenseId < 1 || !in_array($decision, ['approved', 'rejected'], true)) {
    app_reply_redirect('danger', 'Invalid expense approval request.', '../expense_approvals');
}
try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_finance_tables($conn);
    $stmt = $conn->prepare("UPDATE tbl_finance_expenses SET status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP, approval_notes = ? WHERE id = ?");
    $stmt->execute([$decision, (int)$account_id, $approvalNotes !== '' ? $approvalNotes : null, $expenseId]);
    app_audit_log($conn, 'staff', (string)$account_id, 'finance.expense.review', 'tbl_finance_expenses', (string)$expenseId, ['decision' => $decision]);
    app_reply_redirect('success', 'Expense ' . $decision . ' successfully.', '../expense_approvals');
} catch (Throwable $e) {
    app_reply_redirect('danger', 'Failed to review expense: ' . $e->getMessage(), '../expense_approvals');
}
