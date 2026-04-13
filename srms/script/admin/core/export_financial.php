<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../'); exit; }
app_require_permission('finance.view', '../');

$export_type = trim((string)($_GET['export'] ?? ''));
$format = trim((string)($_GET['format'] ?? 'csv'));

if (!$export_type || !in_array($format, ['csv', 'xlsx'], true)) {
    header('location:../financial_reports');
    exit;
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $filename = 'financial_' . $export_type . '_' . date('YmdHis');
    $data = [];

    // === EXPORT: Class-wise Breakdown ===
    if ($export_type === 'classwise') {
        $stmt = $conn->prepare("
            SELECT 
                c.name as 'Class Name',
                COUNT(DISTINCT i.id) as 'Students',
                COUNT(DISTINCT i.id) FILTER (WHERE i.status = 'open') as 'Unpaid',
                COALESCE(SUM(il.amount), 0) as 'Total Billed',
                COALESCE(SUM(p.amount), 0) as 'Total Paid',
                COALESCE(SUM(il.amount), 0) - COALESCE(SUM(p.amount), 0) as 'Outstanding',
                ROUND(100.0 * COALESCE(SUM(p.amount), 0) / NULLIF(SUM(il.amount), 0), 2) as 'Collection %'
            FROM tbl_classes c
            LEFT JOIN tbl_invoices i ON i.class_id = c.id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN (SELECT invoice_id, SUM(amount) as amount FROM tbl_payments GROUP BY invoice_id) p ON p.invoice_id = i.id
            GROUP BY c.id, c.name
            ORDER BY c.name
        ");
    }

    // === EXPORT: Term-wise Comparison ===
    elseif ($export_type === 'termwise') {
        $stmt = $conn->prepare("
            SELECT 
                t.name as 'Term',
                t.year as 'Year',
                COUNT(DISTINCT i.id) as 'Total Invoices',
                COUNT(DISTINCT i.id) FILTER (WHERE i.status = 'paid') as 'Paid',
                COALESCE(SUM(il.amount), 0) as 'Total Amount',
                COALESCE(SUM(p.amount), 0) as 'Collected',
                COALESCE(SUM(il.amount), 0) - COALESCE(SUM(p.amount), 0) as 'Outstanding',
                ROUND(100.0 * COALESCE(SUM(p.amount), 0) / NULLIF(SUM(il.amount), 0), 2) as 'Collection %'
            FROM tbl_terms t
            LEFT JOIN tbl_invoices i ON i.term_id = t.id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN (SELECT invoice_id, SUM(amount) as amount FROM tbl_payments GROUP BY invoice_id) p ON p.invoice_id = i.id
            GROUP BY t.id, t.name, t.year
            ORDER BY t.year DESC, t.id DESC
        ");
    }

    // === EXPORT: Payment Methods ===
    elseif ($export_type === 'methods') {
        $stmt = $conn->prepare("
            SELECT 
                p.method as 'Payment Method',
                COUNT(*) as 'Transaction Count',
                COALESCE(SUM(p.amount), 0) as 'Total Amount',
                ROUND(100.0 * COALESCE(SUM(p.amount), 0) / NULLIF((SELECT SUM(amount) FROM tbl_payments), 0), 2) as 'Percentage %'
            FROM tbl_payments p
            GROUP BY p.method
            ORDER BY p.method
        ");
    }

    // === EXPORT: Aging Analysis ===
    elseif ($export_type === 'aging') {
        $stmt = $conn->prepare("
            SELECT 
                CASE 
                    WHEN CURRENT_DATE - i.due_date BETWEEN 0 AND 29 THEN '0-30 days overdue'
                    WHEN CURRENT_DATE - i.due_date BETWEEN 30 AND 59 THEN '30-60 days overdue'
                    WHEN CURRENT_DATE - i.due_date BETWEEN 60 AND 89 THEN '60-90 days overdue'
                    WHEN CURRENT_DATE - i.due_date > 90 THEN '90+ days overdue'
                    ELSE 'Not Due'
                END as 'Age Bucket',
                COUNT(DISTINCT i.id) as 'Invoice Count',
                COUNT(DISTINCT s.id) as 'Student Count',
                COALESCE(SUM(il.amount - COALESCE(p.amount, 0)), 0) as 'Amount Overdue'
            FROM tbl_invoices i
            LEFT JOIN tbl_students s ON s.id = i.student_id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN (SELECT invoice_id, SUM(amount) as amount FROM tbl_payments GROUP BY invoice_id) p ON p.invoice_id = i.id
            WHERE i.status = 'open' AND i.due_date < CURRENT_DATE
            GROUP BY 'Age Bucket'
        ");
    }

    // === EXPORT: Top Defaulters ===
    elseif ($export_type === 'defaulters') {
        $stmt = $conn->prepare("
            SELECT 
                s.fname || ' ' || s.lname as 'Student Name',
                s.id as 'Admission',
                c.name as 'Class',
                COALESCE(SUM(il.amount - COALESCE(p.amount, 0)), 0) as 'Total Outstanding',
                COUNT(DISTINCT i.id) as 'Open Invoices',
                MAX(i.due_date) as 'Earliest Due Date',
                CURRENT_DATE - MAX(i.due_date) as 'Days Overdue'
            FROM tbl_students s
            LEFT JOIN tbl_invoices i ON i.student_id = s.id AND i.status = 'open'
            LEFT JOIN tbl_classes c ON c.id = i.class_id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN (SELECT invoice_id, SUM(amount) as amount FROM tbl_payments GROUP BY invoice_id) p ON p.invoice_id = i.id
            WHERE i.id IS NOT NULL
            GROUP BY s.id, s.fname, s.lname, c.name
            HAVING COALESCE(SUM(il.amount - COALESCE(p.amount, 0)), 0) > 0
            ORDER BY 'Total Outstanding' DESC
            LIMIT 100
        ");
    }

    if (!isset($stmt)) {
        header('location:../financial_reports');
        exit;
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        if (!empty($results)) {
            // Write headers
            fputcsv($output, array_keys($results[0]));

            // Write data
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    elseif ($format === 'xlsx') {
        // Simple XLSX generation (using CSV-in-XLSX for now, can upgrade to full XLSX later)
        // For now, just serve as CSV with .xlsx extension
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');

        // Use phpexcel or create basic XML if needed
        // For MVP, convert to CSV format
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        if (!empty($results)) {
            fputcsv($output, array_keys($results[0]));
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

} catch (Throwable $e) {
    error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
    header('location:../financial_reports?error=export_failed');
    exit;
}
