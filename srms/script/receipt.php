<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1') {
    header('location:./');
    exit;
}

$receiptId = (int)($_GET['id'] ?? 0);
if ($receiptId < 1) {
    header('location:./');
    exit;
}
$forceDownload = isset($_GET['download']) && (string)$_GET['download'] !== '0';

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!app_table_exists($conn, 'tbl_receipts') || !app_table_exists($conn, 'tbl_payments') || !app_table_exists($conn, 'tbl_invoices')) {
        throw new RuntimeException('Receipts module is not installed.');
    }

    $stmt = $conn->prepare("SELECT r.id, r.receipt_number, r.created_at AS receipt_date,
        p.id AS payment_id, p.amount, p.method, p.reference, p.paid_at,
        i.id AS invoice_id, i.student_id,
        concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
        st.school_id,
        COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) AS total_amount,
        COALESCE((SELECT SUM(pp.amount) FROM tbl_payments pp WHERE pp.invoice_id = i.id), 0) AS total_paid,
        concat_ws(' ', staff.fname, staff.lname) AS receiver_name
        FROM tbl_receipts r
        JOIN tbl_payments p ON p.id = r.payment_id
        JOIN tbl_invoices i ON i.id = p.invoice_id
        JOIN tbl_students st ON st.id = i.student_id
        LEFT JOIN tbl_staff staff ON staff.id = p.received_by
        WHERE r.id = ?
        LIMIT 1");
    $stmt->execute([$receiptId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Receipt not found.');
    }

    $allowed = false;
    if ((int)$level === 0 || (int)$level === 1 || (int)$level === 5) {
        $allowed = true;
    } elseif ((int)$level === 3) {
        $allowed = ((string)$row['student_id'] === (string)$account_id);
    } elseif ((int)$level === 4 && app_table_exists($conn, 'tbl_parent_students')) {
        $stmt = $conn->prepare('SELECT 1 FROM tbl_parent_students WHERE parent_id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([(int)$account_id, (string)$row['student_id']]);
        $allowed = (bool)$stmt->fetchColumn();
    }

    if (!$allowed) {
        header('location:./');
        exit;
    }

    $totalAmount = (float)($row['total_amount'] ?? 0);
    $totalPaid = (float)($row['total_paid'] ?? 0);
    $balance = max(0, round($totalAmount - $totalPaid, 2));

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 11);

    $html = '<table width="100%" cellpadding="4">'
      . '<tr><td style="text-align:center;font-size:16pt;font-weight:bold;">' . htmlspecialchars(WBName) . '</td></tr>'
      . '<tr><td style="text-align:center;font-size:12pt;">OFFICIAL FEE RECEIPT</td></tr>'
      . '</table>'
      . '<hr>'
      . '<table width="100%" cellpadding="4" style="font-size:10pt;">'
      . '<tr><td width="50%"><b>Receipt No:</b> ' . htmlspecialchars((string)$row['receipt_number']) . '</td><td width="50%" style="text-align:right;"><b>Date:</b> ' . htmlspecialchars((string)substr((string)$row['receipt_date'], 0, 10)) . '</td></tr>'
      . '<tr><td><b>Student:</b> ' . htmlspecialchars((string)$row['student_name']) . '</td><td style="text-align:right;"><b>Adm No:</b> ' . htmlspecialchars((string)($row['school_id'] !== '' ? $row['school_id'] : $row['student_id'])) . '</td></tr>'
      . '<tr><td><b>Invoice ID:</b> #' . (int)$row['invoice_id'] . '</td><td style="text-align:right;"><b>Payment ID:</b> #' . (int)$row['payment_id'] . '</td></tr>'
      . '</table>'
      . '<br>'
      . '<table width="100%" cellpadding="5" border="1" style="font-size:10pt;border-collapse:collapse;">'
      . '<tr><td width="45%"><b>Amount Paid</b></td><td width="55%">KES ' . number_format((float)$row['amount'], 2) . '</td></tr>'
      . '<tr><td><b>Payment Method</b></td><td>' . htmlspecialchars(strtoupper((string)$row['method'])) . '</td></tr>'
      . '<tr><td><b>Reference</b></td><td>' . htmlspecialchars((string)$row['reference']) . '</td></tr>'
      . '<tr><td><b>Total Invoice</b></td><td>KES ' . number_format($totalAmount, 2) . '</td></tr>'
      . '<tr><td><b>Total Paid</b></td><td>KES ' . number_format($totalPaid, 2) . '</td></tr>'
      . '<tr><td><b>Balance Remaining</b></td><td>KES ' . number_format($balance, 2) . '</td></tr>'
      . '<tr><td><b>Received By</b></td><td>' . htmlspecialchars((string)($row['receiver_name'] ?? 'Accounts Office')) . '</td></tr>'
      . '</table>'
      . '<br><p style="font-size:9pt;">Thank you.</p>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('receipt-' . (int)$row['id'] . '.pdf', $forceDownload ? 'D' : 'I');
} catch (Throwable $e) {
    header('location:./');
}
