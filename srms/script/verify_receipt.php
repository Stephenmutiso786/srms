<?php
require_once('db/config.php');
require_once('const/school.php');

$code = trim((string)($_GET['code'] ?? ''));
$receipt = null;
$error = '';

try {
    if ($code === '') {
        throw new RuntimeException('Missing verification code.');
    }
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_receipts_table($conn);

    $stmt = $conn->prepare("SELECT r.receipt_number, r.verification_code, r.created_at AS receipt_date,
        p.amount, p.method, p.reference,
        i.student_id,
        concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
        st.school_id,
        COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) AS total_amount,
        COALESCE((SELECT SUM(pp.amount) FROM tbl_payments pp WHERE pp.invoice_id = i.id), 0) AS total_paid
        FROM tbl_receipts r
        JOIN tbl_payments p ON p.id = r.payment_id
        JOIN tbl_invoices i ON i.id = p.invoice_id
        JOIN tbl_students st ON st.id = i.student_id
        WHERE r.verification_code = ?
        LIMIT 1");
    $stmt->execute([$code]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$receipt) {
        throw new RuntimeException('Receipt not found for this verification code.');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Verify Receipt</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
</head>
<body style="background:#f5f7fb;">
<div style="max-width:860px;margin:40px auto;padding:0 16px;">
    <div class="tile">
        <h2 class="tile-title">Receipt Verification</h2>
        <?php if ($error !== ''): ?>
        <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($receipt): ?>
        <?php $balance = max(0, round((float)$receipt['total_amount'] - (float)$receipt['total_paid'], 2)); ?>
        <div class="alert alert-success">This receipt is valid.</div>
        <table class="table table-bordered">
            <tr><th>Receipt Number</th><td><?php echo htmlspecialchars((string)$receipt['receipt_number']); ?></td></tr>
            <tr><th>Verification Code</th><td><?php echo htmlspecialchars((string)$receipt['verification_code']); ?></td></tr>
            <tr><th>Receipt Date</th><td><?php echo htmlspecialchars((string)$receipt['receipt_date']); ?></td></tr>
            <tr><th>Student</th><td><?php echo htmlspecialchars((string)$receipt['student_name']); ?></td></tr>
            <tr><th>Admission Number</th><td><?php echo htmlspecialchars((string)($receipt['school_id'] !== '' ? $receipt['school_id'] : $receipt['student_id'])); ?></td></tr>
            <tr><th>Amount Paid</th><td>KES <?php echo number_format((float)$receipt['amount'], 2); ?></td></tr>
            <tr><th>Payment Method</th><td><?php echo htmlspecialchars(strtoupper((string)$receipt['method'])); ?></td></tr>
            <tr><th>Reference</th><td><?php echo htmlspecialchars((string)$receipt['reference']); ?></td></tr>
            <tr><th>Total Paid on Invoice</th><td>KES <?php echo number_format((float)$receipt['total_paid'], 2); ?></td></tr>
            <tr><th>Balance Remaining</th><td>KES <?php echo number_format($balance, 2); ?></td></tr>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
