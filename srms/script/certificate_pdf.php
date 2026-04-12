<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/certificate_engine.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1') { header('location:./'); exit; }

$certificateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($certificateId < 1) { header('location:./'); exit; }

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_certificates_table($conn);

    $stmt = $conn->prepare('SELECT cert.*, st.class AS student_class, st.school_id, st.gender, st.image,
        concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name,
        c.name AS class_name
        FROM tbl_certificates cert
        JOIN tbl_students st ON st.id = cert.student_id
        LEFT JOIN tbl_classes c ON c.id = cert.class_id
        WHERE cert.id = ? LIMIT 1');
    $stmt->execute([$certificateId]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cert) {
        header('location:./');
        exit;
    }

    $allowed = false;
    if ((int)$level === 0 || (int)$level === 1) {
        $allowed = true;
    } elseif ((int)$level === 3) {
        $allowed = ((string)$cert['student_id'] === (string)$account_id);
    } elseif ((int)$level === 4) {
        if (app_table_exists($conn, 'tbl_parent_students')) {
            $stmt = $conn->prepare('SELECT 1 FROM tbl_parent_students WHERE parent_id = ? AND student_id = ? LIMIT 1');
            $stmt->execute([(int)$account_id, (string)$cert['student_id']]);
            $allowed = (bool)$stmt->fetchColumn();
        }
    } elseif ((int)$level === 2) {
        $allowed = report_teacher_has_class_access($conn, (int)$account_id, (int)$cert['student_class']);
    }

    if (!$allowed) {
        header('location:./');
        exit;
    }

    $verifyUrl = app_certificate_verify_url((string)$cert['verification_code']);

    $studentPhoto = '';
    $image = trim((string)($cert['image'] ?? ''));
    $gender = trim((string)($cert['gender'] ?? 'male'));
    $imagePath = ($image !== '' && strtoupper($image) !== 'DEFAULT') ? ('images/students/' . $image) : ('images/students/' . $gender . '.png');
    if (is_file($imagePath)) {
        $studentPhoto = '<img src="' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') . '" style="width:90px;height:100px;object-fit:cover;border:1px solid #9aa;" />';
    }

    $logoHtml = app_pdf_image_html('images/logo/' . WBLogo, 65, 0, WBName);

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 11);

    $html = '
    <table width="100%" cellpadding="4" cellspacing="0">
      <tr>
        <td width="16%">' . $logoHtml . '</td>
        <td width="84%" style="text-align:right;">
          <div style="font-size:18pt;font-weight:bold;">' . htmlspecialchars(WBName) . '</div>
          <div style="font-size:10pt;">' . htmlspecialchars(WBAddress) . '</div>
          <div style="font-size:10pt;">' . htmlspecialchars(WBEmail) . '</div>
        </td>
      </tr>
    </table>
    <div style="text-align:center;border:1px solid #7aa4bf;background:#e7f4fb;padding:8px;margin-top:8px;">
      <div style="font-size:18pt;font-weight:bold;">' . htmlspecialchars((string)$cert['title']) . '</div>
      <div style="font-size:10pt;">Official school certificate</div>
    </div>
    <table width="100%" cellpadding="5" cellspacing="0" style="margin-top:10px;">
      <tr>
        <td width="75%" style="border:1px solid #aab7c4;">
          <p style="font-size:11pt;">This is to certify that <b>' . htmlspecialchars((string)$cert['student_name']) . '</b> (Admission No: <b>' . htmlspecialchars((string)($cert['school_id'] ?: $cert['student_id'])) . '</b>)
          of <b>' . htmlspecialchars((string)($cert['class_name'] ?? '')) . '</b> has been issued this <b>' . htmlspecialchars((string)$cert['title']) . '</b>
          on <b>' . htmlspecialchars((string)$cert['issue_date']) . '</b>.</p>
          <p style="font-size:10pt;"><b>Serial No:</b> ' . htmlspecialchars((string)$cert['serial_no']) . '<br>
          <b>Verification Code:</b> ' . htmlspecialchars((string)$cert['verification_code']) . '</p>
          <p style="font-size:10pt;">' . nl2br(htmlspecialchars((string)($cert['notes'] ?? ''))) . '</p>
          <p style="font-size:9pt;color:#556;"><b>Certificate Hash:</b> ' . htmlspecialchars(substr((string)$cert['cert_hash'], 0, 32)) . '...</p>
        </td>
        <td width="25%" style="border:1px solid #aab7c4;text-align:center;vertical-align:top;">' . $studentPhoto . '</td>
      </tr>
    </table>
    <table width="100%" cellpadding="5" cellspacing="0" style="margin-top:14px;">
      <tr>
        <td width="50%" style="border-top:1px solid #444;">Authorized Signature</td>
        <td width="50%" style="text-align:right;">Date: ' . date('Y-m-d') . '</td>
      </tr>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 14, 232, 30, 30);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Text(48, 246, 'Scan to verify originality: ' . $verifyUrl);

    $stmt = $conn->prepare('UPDATE tbl_certificates SET downloads = downloads + 1 WHERE id = ?');
    $stmt->execute([$certificateId]);

    $pdf->Output('certificate.pdf', 'I');
} catch (Throwable $e) {
    header('location:./');
}
