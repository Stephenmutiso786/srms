<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');
require_once('const/certificate_engine.php');

if ($res !== '1' || $level !== '0') { 
    header('location:../../'); 
    exit; 
}
app_require_permission('report.generate', '../certificates');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location:../certificates');
    exit;
}

$resultType = trim((string)($_POST['result_type'] ?? 'certificate'));
$resultId = (int)($_POST['result_id'] ?? 0);
$recipientEmail = trim((string)($_POST['recipient_email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($resultId < 1 || !in_array($resultType, ['certificate', 'report_card'], true)) {
    app_reply_redirect('danger', 'Invalid request.', '../certificates');
}

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    app_reply_redirect('danger', 'Invalid email address.', '../certificates');
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $studentName = '';
    $subject = '';
    $htmlBody = '';
    
    if ($resultType === 'certificate') {
        app_ensure_certificates_table($conn);
        $stmt = $conn->prepare('SELECT cert.id, cert.title, cert.serial_no, cert.certificate_type, 
            st.fname, st.mname, st.lname, st.id as student_id
            FROM tbl_certificates cert
            JOIN tbl_students st ON st.id = cert.student_id
            WHERE cert.id = ? LIMIT 1');
        $stmt->execute([$resultId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cert) {
            throw new RuntimeException('Certificate not found.');
        }
        
        $studentName = trim($cert['fname'] . ' ' . $cert['mname'] . ' ' . $cert['lname']);
        $subject = 'Your ' . htmlspecialchars($cert['title']) . ' - ' . APP_NAME;
        
        $htmlBody = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .header { background-color: #004085; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
                    .cert-details { background-color: #e8f4f8; padding: 15px; border-radius: 5px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>' . htmlspecialchars(APP_NAME) . '</h2>
                    <p>Certificate Notification</p>
                </div>
                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($studentName) . '</strong>,</p>
                    <p>Congratulations! Your certificate has been issued and is now available.</p>
                    
                    <div class="cert-details">
                        <h4>Certificate Details:</h4>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px;"><strong>Certificate Type:</strong></td>
                                <td style="padding: 8px;">' . htmlspecialchars($cert['title']) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px;"><strong>Serial Number:</strong></td>
                                <td style="padding: 8px;">' . htmlspecialchars($cert['serial_no']) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px;"><strong>Recipient:</strong></td>
                                <td style="padding: 8px;">' . htmlspecialchars($studentName) . '</td>
                            </tr>
                        </table>
                    </div>
                    
                    ' . (!empty($message) ? '<p><strong>Message from School:</strong></p><p>' . nl2br(htmlspecialchars($message)) . '</p>' : '') . '
                    
                    <p>Please find the certificate attached to this email. You can also download it from the student portal.</p>
                    
                    <p>For any questions or concerns, please contact the school administration.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars(APP_NAME) . '. All rights reserved.</p>
                </div>
            </body>
            </html>
        ';
        
    } elseif ($resultType === 'report_card') {
        if (!app_table_exists($conn, 'tbl_report_cards')) {
            throw new RuntimeException('Report card module not installed.');
        }
        
        $stmt = $conn->prepare('SELECT rc.id, concat_ws(\' \', st.fname, st.mname, st.lname) as student_name, 
            st.id as student_id, t.name as term_name, c.name as class_name
            FROM tbl_report_cards rc
            JOIN tbl_students st ON st.id = rc.student_id
            LEFT JOIN tbl_terms t ON t.id = rc.term_id
            LEFT JOIN tbl_classes c ON c.id = rc.class_id
            WHERE rc.id = ? LIMIT 1');
        $stmt->execute([$resultId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            throw new RuntimeException('Report card not found.');
        }
        
        $studentName = $report['student_name'];
        $subject = 'Academic Report Card - ' . $report['term_name'] . ' - ' . APP_NAME;
        
        $htmlBody = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .header { background-color: #1e5f74; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
                    .report-details { background-color: #f0f8ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>' . htmlspecialchars(APP_NAME) . '</h2>
                    <p>Report Card Notification</p>
                </div>
                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($studentName) . '</strong>,</p>
                    <p>Your academic report card for the ' . htmlspecialchars($report['term_name']) . ' term is now available.</p>
                    
                    <div class="report-details">
                        <h4>Report Details:</h4>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px;"><strong>Student:</strong></td>
                                <td style="padding: 8px;">' . htmlspecialchars($studentName) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px;"><strong>Class:</strong></td>
                                <td style="padding: 8px;">' . htmlspecialchars($report['class_name']) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px;"><strong>Term:</strong></td>
                                <td style="padding: 8px;">' . htmlspecialchars($report['term_name']) . '</td>
                            </tr>
                        </table>
                    </div>
                    
                    ' . (!empty($message) ? '<p><strong>Message from School:</strong></p><p>' . nl2br(htmlspecialchars($message)) . '</p>' : '') . '
                    
                    <p>Please find the report card attached to this email. You can also view it in the parent/student portal.</p>
                    
                    <p>For any concerns regarding the report, please contact the class teacher or school administration.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars(APP_NAME) . '. All rights reserved.</p>
                </div>
            </body>
            </html>
        ';
    }
    
    // Send email
    $result = app_send_email($conn, $recipientEmail, $subject, $htmlBody);
    
    if ($result['ok']) {
        app_audit_log($conn, 'staff', (string)$account_id, 'result.email.sent', $resultType, (string)$resultId, [
            'to' => $recipientEmail,
            'student' => $studentName,
        ]);
        app_reply_redirect('success', 'Email sent successfully to ' . htmlspecialchars($recipientEmail), '../certificates');
    } else {
        throw new RuntimeException($result['error'] ?: 'Failed to send email');
    }
    
} catch (Throwable $e) {
    app_reply_redirect('danger', 'Failed to send email: ' . $e->getMessage(), '../certificates');
}
