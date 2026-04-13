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
$forceDownload = isset($_GET['download']) && (string)$_GET['download'] !== '0';

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_certificates_table($conn);

  $schoolIdSelect = app_column_exists($conn, 'tbl_students', 'school_id') ? 'st.school_id' : "'' AS school_id";
  $dobSelect = app_column_exists($conn, 'tbl_students', 'dob') ? 'st.dob' : 'NULL AS dob';
  $imageSelect = app_column_exists($conn, 'tbl_students', 'display_image') ? 'st.display_image AS image' : "'' AS image";

  $stmt = $conn->prepare('SELECT cert.*, st.class AS student_class, ' . $schoolIdSelect . ', st.gender, ' . $imageSelect . ', ' . $dobSelect . ',
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

    // Permission check
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
    $certificateType = (string)($cert['certificate_type'] ?? 'general');
    $category = (string)($cert['certificate_category'] ?? 'general');

    // Get student photo
    $studentPhoto = '';
    $image = trim((string)($cert['image'] ?? ''));
    $gender = trim((string)($cert['gender'] ?? 'male'));
    $imagePath = ($image !== '' && strtoupper($image) !== 'DEFAULT') ? ('images/students/' . $image) : ('images/students/' . $gender . '.png');
    if (is_file($imagePath)) {
        $studentPhoto = '<img src="' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') . '" style="width:90px;height:100px;object-fit:cover;border:1px solid #9aa;" />';
    }

    $logoHtml = app_pdf_image_html('images/logo/' . WBLogo, 65, 0, WBName);

    // Parse competencies if available
    $competencies = [];
    if (!empty($cert['competencies_json'])) {
        $parsed = @json_decode($cert['competencies_json'], true);
        if (is_array($parsed) && isset($parsed['competencies'])) {
            $competencies = $parsed['competencies'];
        }
    }

    // Create PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(false, 20);
    $pdf->AddPage();

    // Select template based on certificate type
    switch ($category) {
        case 'primary_completion':
            renderPrimaryCompletionCertificate($pdf, $cert, $studentPhoto, $logoHtml, $competencies, $verifyUrl);
            break;
        case 'junior_completion':
            renderJuniorCompletionCertificate($pdf, $cert, $studentPhoto, $logoHtml, $competencies, $verifyUrl);
            break;
        case 'conduct':
            renderConductCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl);
            break;
        case 'transfer':
            renderTransferCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl);
            break;
        case 'leaving':
            renderLeavingCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl);
            break;
        case 'merit':
            renderMeritCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl);
            break;
        default:
            renderGeneralCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl);
    }

    // Update download counter
    $stmt = $conn->prepare('UPDATE tbl_certificates SET downloads = downloads + 1 WHERE id = ?');
    $stmt->execute([$certificateId]);

    $pdf->Output('certificate-' . $cert['serial_no'] . '.pdf', $forceDownload ? 'D' : 'I');
} catch (Throwable $e) {
    error_log('Certificate PDF Error: ' . $e->getMessage());
    header('location:./');
}

/**
 * Render Primary Completion Certificate (Grade 6)
 */
function renderPrimaryCompletionCertificate($pdf, $cert, $studentPhoto, $logoHtml, $competencies, $verifyUrl) {
    $pdf->SetFont('helvetica', '', 11);
    
    $html = '
    <table width="100%" cellpadding="3" cellspacing="0">
      <tr>
        <td width="20%">' . $logoHtml . '</td>
        <td width="80%" style="text-align:center;">
          <div style="font-size:14pt;font-weight:bold;color:#1a3a52;">REPUBLIC OF KENYA</div>
          <div style="font-size:12pt;font-weight:bold;color:#1a3a52;">MINISTRY OF EDUCATION</div>
          <div style="font-size:10pt;">Under the Competency Based Curriculum (CBC)</div>
        </td>
      </tr>
    </table>
    
    <div  style="text-align:center;border:3px solid #d4af37;background:#fffef0;padding:12px;margin-top:10px;">
      <div style="font-size:22pt;font-weight:bold;color:#1a3a52;">PRIMARY EDUCATION COMPLETION CERTIFICATE</div>
      <div style="font-size:11pt;color:#556;">This certifies academic achievement at Grade 6</div>
    </div>
    
    <div style="text-align:center;margin-top:12px;font-size:11pt;">
      <p>This is to certify that</p>
      <div style="font-size:16pt;font-weight:bold;color:#1a3a52;">
        ' . htmlspecialchars((string)$cert['student_name']) . '
      </div>
      <p>Admission No: <strong>' . htmlspecialchars((string)($cert['school_id'] ?: $cert['student_id'])) . '</strong></p>
      <p>has successfully completed Primary Education under the Competency Based Curriculum (CBC)</p>
    </div>
    
    <table width="100%" cellpadding="4" cellspacing="0" style="margin-top:10px;background:#f0f4f8;border:1px solid #ccc;">
      <tr>
        <td width="60%">
          <p style="font-size:10pt;margin:0;"><strong>OVERALL PERFORMANCE:</strong></p>
          <p style="font-size:11pt;margin:2px 0;"><strong>Mean Score:</strong> ' . 
            ($cert['mean_score'] !== null ? number_format((float)$cert['mean_score'], 2) . '%' : 'N/A') . '</p>
          <p style="font-size:11pt;margin:2px 0;"><strong>Grade:</strong> ' . 
            ($cert['merit_grade'] ? htmlspecialchars($cert['merit_grade']) . ' - ' . app_merit_grade_label($cert['merit_grade']) : 'N/A') . '</p>
          ' . (isset($cert['position_in_class']) && $cert['position_in_class'] ? 
          '<p style="font-size:11pt;margin:2px 0;"><strong>Class Position:</strong> ' . (int)$cert['position_in_class'] . '</p>' : '') . '
        </td>
        <td width="40%" style="text-align:center;vertical-align:middle;">
          ' . $studentPhoto . '
        </td>
      </tr>
    </table>' . 
    (count($competencies) > 0 ? '
    <p style="font-size:11pt;font-weight:bold;margin-top:8px;">CBC COMPETENCIES ACHIEVED:</p>
    <table width="100%" cellpadding="4" cellspacing="1" style="background:#ddd;">
      ' . implode('', array_map(function($comp, $key) {
          $level = $comp['achievement_level'] ?? 'Not Assessed';
          $levelBadge = match($level) {
              'excellent' => '<span style="background:#28a745;color:white;padding:2px 6px;border-radius:3px;">Excellent</span>',
              'advanced' => '<span style="background:#007bff;color:white;padding:2px 6px;border-radius:3px;">Advanced</span>',
              'proficient' => '<span style="background:#ffc107;color:black;padding:2px 6px;border-radius:3px;">Proficient</span>',
              'developing' => '<span style="background:#6c757d;color:white;padding:2px 6px;border-radius:3px;">Developing</span>',
              default => '<span style="background:#e9ecef;color:black;padding:2px 6px;border-radius:3px;">Not Assessed</span>'
          };
          $compNames = [
              'communication' => 'Communication & Collaboration',
              'critical_thinking' => 'Critical Thinking',
              'creativity' => 'Creativity',
              'citizenship' => 'Citizenship',
              'digital' => 'Digital Literacy'
          ];
          return '<tr style="background:white;"><td width="60%">' . htmlspecialchars($compNames[$key] ?? $key) . '</td><td>' . $levelBadge . '</td></tr>';
      }, $competencies, array_keys($competencies))) . '
    </table>' : '') . '
    
    <table width="100%" cellpadding="5" cellspacing="0" style="margin-top:12px;">
      <tr>
        <td width="50%">
          <p style="margin:0;"><strong>Class Teacher</strong></p>
          <p style="border-top:1px solid #000;margin:20px 0 0 0;min-height:30px;"></p>
        </td>
        <td width="50%" style="text-align:right;">
          <p style="margin:0;"><strong>Headteacher / Principal</strong></p>
          <p style="border-top:1px solid #000;margin:20px 0 0 0;min-height:30px;"></p>
        </td>
      </tr>
      <tr>
        <td style="font-size:9pt;color:#666;"><strong>SERIAL:</strong> ' . htmlspecialchars((string)$cert['serial_no']) . '</td>
        <td style="text-align:right;font-size:9pt;color:#666;"><strong>DATE:</strong> ' . htmlspecialchars((string)$cert['issue_date']) . '</td>
      </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->SetXY(15, 255);
    $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 15, 255, 25, 25);
}

/**
 * Render Junior Completion Certificate (Grade 9)
 */
function renderJuniorCompletionCertificate($pdf, $cert, $studentPhoto, $logoHtml, $competencies, $verifyUrl) {
    // Similar to primary but for Grade 9/Junior Secondary
    renderPrimaryCompletionCertificate($pdf, $cert, $studentPhoto, $logoHtml, $competencies, $verifyUrl);
}

/**
 * Render Good Conduct Certificate
 */
function renderConductCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl) {
    $pdf->SetFont('helvetica', '', 11);
    $html = '
    <table width="100%" cellpadding="4" cellspacing="0" style="text-align:center;">
      <tr>
        <td>' . $logoHtml . '</td>
        <td style="font-size:14pt;font-weight:bold;color:#1a3a52;">GOOD CONDUCT CERTIFICATE</td>
      </tr>
    </table>
    
    <div style="text-align:center;margin-top:15px;">
      <p style="font-size:11pt;">This is to certify that</p>
      <div style="font-size:16pt;font-weight:bold;color:#1a3a52;margin:10px 0;">
        ' . htmlspecialchars((string)$cert['student_name']) . '
      </div>
      <p>Admission No: ' . htmlspecialchars((string)($cert['school_id'] ?: $cert['student_id'])) . '</p>
      <p style="margin-top:15px;">has demonstrated exemplary conduct, discipline, and moral character</p>
      <p style="margin:10px 0;">throughout their academic term.</p>
      <p style="margin-top:10px;">' . nl2br(htmlspecialchars((string)($cert['notes'] ?? ''))) . '</p>
    </div>
    
    <table width="100%" cellpadding="8" cellspacing="0" style="margin-top:20px;">
      <tr>
        <td width="50%" style="text-align:center;">
          <p style="border-top:1px solid #000;padding-top:20px;">Class Teacher</p>
        </td>
        <td width="50%" style="text-align:center;">
          <p style="border-top:1px solid #000;padding-top:20px;">School Principal</p>
        </td>
      </tr>
      <tr>
        <td style="text-align:center;font-size:9pt;color:#666;padding-top:5px;">Date: ' . htmlspecialchars((string)$cert['issue_date']) . '</td>
        <td style="text-align:center;font-size:9pt;color:#666;padding-top:5px;"><strong>' . htmlspecialchars((string)$cert['serial_no']) . '</strong></td>
      </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
}

/**
 * Render Leaving Certificate
 */
function renderLeavingCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl) {
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);

    $studentName = strtoupper(trim((string)$cert['student_name']));
    $admissionNo = (string)($cert['school_id'] ?: $cert['student_id']);
    $issueDate = (string)($cert['issue_date'] ?? '');
    $className = (string)($cert['class_name'] ?? '');
    $serialNo = (string)($cert['serial_no'] ?? '');
    $notes = trim((string)($cert['notes'] ?? ''));
    $schoolName = strtoupper(trim((string)WBName));
    $gender = strtoupper(substr((string)($cert['gender'] ?? ''), 0, 1));
    $dob = trim((string)($cert['dob'] ?? ''));

    $templateCandidates = [
        __DIR__ . '/images/templates/kcpe_leaving_certificate.jpg',
        __DIR__ . '/images/templates/kcpe_leaving_certificate.jpeg',
        __DIR__ . '/images/templates/kcpe_leaving_certificate.png',
    ];
    $templatePath = '';
    foreach ($templateCandidates as $candidate) {
        if (is_file($candidate)) {
            $templatePath = $candidate;
            break;
        }
    }

    if ($templatePath !== '') {
        $pdf->Image($templatePath, 8, 8, 194, 281, '', '', '', false, 300, '', false, false, 0);
    }

    $photoFile = '';
    if (preg_match('/src=\"([^\"]+)\"/i', (string)$studentPhoto, $m) === 1) {
        $photoFile = (string)$m[1];
    }
    if ($photoFile !== '' && is_file($photoFile)) {
        $pdf->Image($photoFile, 18, 73, 32, 36, '', '', '', false, 300, '', false, false, 1);
    }

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8.5);

    // Overlay values onto fixed form lines.
    $pdf->SetXY(32, 59); $pdf->Cell(70, 4, $serialNo, 0, 0, 'L');
    $pdf->SetXY(152, 59); $pdf->Cell(38, 4, $issueDate, 0, 0, 'L');

    $pdf->SetXY(152, 74); $pdf->Cell(38, 4, $admissionNo, 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY(58, 83); $pdf->Cell(132, 5, $studentName, 0, 0, 'L');

    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetXY(31, 98); $pdf->Cell(54, 4, $dob, 0, 0, 'L');
    $pdf->SetXY(91, 98); $pdf->Cell(20, 4, $gender, 0, 0, 'L');
    $pdf->SetXY(150, 98); $pdf->Cell(40, 4, $admissionNo, 0, 0, 'L');

    $pdf->SetXY(31, 113); $pdf->Cell(48, 4, strtoupper($className), 0, 0, 'L');
    $pdf->SetXY(84, 113); $pdf->Cell(106, 4, $schoolName, 0, 0, 'L');

    $pdf->SetXY(52, 128); $pdf->Cell(138, 4, substr($notes, 0, 90), 0, 0, 'L');
    $pdf->SetXY(58, 143); $pdf->Cell(132, 4, '', 0, 0, 'L');
    $pdf->SetXY(89, 158); $pdf->Cell(101, 4, '', 0, 0, 'L');
    $pdf->SetXY(45, 173); $pdf->Cell(145, 4, substr($notes, 0, 120), 0, 0, 'L');

    $pdf->SetXY(54, 214); $pdf->Cell(72, 4, '', 0, 0, 'L');
    $pdf->SetXY(128, 214); $pdf->Cell(24, 4, $issueDate, 0, 0, 'L');
    $pdf->SetXY(126, 226); $pdf->Cell(62, 4, $serialNo, 0, 0, 'L');

    // Keep QR-only verification with no printed URL.
    $pdf->SetXY(160, 247);
    $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 160, 247, 28, 28);
}

/**
 * Render Transfer Certificate
 */
function renderTransferCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl) {
    renderLeavingCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl);
}

/**
 * Render Merit Certificate
 */
function renderMeritCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl) {
    $pdf->SetFont('helvetica', '', 12);
    $meritDesc = $cert['merit_grade'] ? app_merit_grade_description($cert['merit_grade']) : 'Exceptional achievement';
    
    $html = '
    <div style="text-align:center;margin-top:20px;">
      <div style="font-size:24pt;font-weight:bold;color:#d4af37;">★ MERIT CERTIFICATE ★</div>
      <div style="font-size:12pt;color:#666;margin-bottom:15px;">In Recognition of Academic Excellence</div>
    </div>
    
    <table width="100%" cellpadding="5" cellspacing="0" style="margin-top:15px;">
      <tr>
        <td width="60%">
          <p style="font-size:11pt;">This certificate is proudly awarded to</p>
          <div style="font-size:16pt;font-weight:bold;color:#1a3a52;margin:8px 0;">
            ' . htmlspecialchars((string)$cert['student_name']) . '
          </div>
          <p style="font-size:10pt;">For outstanding academic performance</p>
          <p style="font-size:12pt;font-weight:bold;margin:8px 0;">Mean Score: ' . 
            ($cert['mean_score'] ? number_format((float)$cert['mean_score'], 2) . '% (Grade ' . htmlspecialchars($cert['merit_grade']) . ')' : 'Excellent') . '</p>
          <p style="font-size:10pt;color:#666;margin:8px 0;">' . htmlspecialchars($meritDesc) . '</p>
        </td>
        <td width="40%" style="text-align:center;">
          ' . $studentPhoto . '
        </td>
      </tr>
    </table>
    
    <div style="text-align:center;margin-top:20px;">
      <p><strong>Issued: </strong>' . htmlspecialchars((string)$cert['issue_date']) . '</p>
    </div>
    
    <table width="100%" cellpadding="8" cellspacing="0" style="margin-top:15px;">
      <tr>
        <td width="50%" style="text-align:center;border-top:1px solid #000;padding-top:15px;">
          <strong>Headteacher</strong>
        </td>
        <td width="50%" style="text-align:center;border-top:1px solid #000;padding-top:15px;">
          <strong>Chairman, Board of Governors</strong>
        </td>
      </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
}

/**
 * Render General / Default Certificate
 */
function renderGeneralCertificate($pdf, $cert, $studentPhoto, $logoHtml, $verifyUrl) {
    $pdf->SetFont('helvetica', '', 11);
    $html = '
    <table width="100%" cellpadding="4" cellspacing="0">
      <tr>
        <td width="20%">' . $logoHtml . '</td>
        <td width="80%" style="text-align:right;">
          <div style="font-size:14pt;font-weight:bold;">' . htmlspecialchars(WBName) . '</div>
          <div style="font-size:9pt;">' . htmlspecialchars(WBAddress) . '</div>
        </td>
      </tr>
    </table>
    <div style="text-align:center;border:2px solid #7aa4bf;background:#e7f4fb;padding:10px;margin-top:10px;">
      <div style="font-size:18pt;font-weight:bold;">' . htmlspecialchars((string)$cert['title']) . '</div>
      <div style="font-size:9pt;color:#556;">Official School Certificate</div>
    </div>
    <table width="100%" cellpadding="6" cellspacing="0" style="margin-top:15px;">
      <tr>
        <td width="65%" style="border:1px solid #bbb;padding:8px;">
          <p style="font-size:11pt;"><strong>' . htmlspecialchars((string)$cert['student_name']) . '</strong></p>
          <p style="font-size:10pt;"><strong>Admission No:</strong> ' . htmlspecialchars((string)($cert['school_id'] ?: $cert['student_id'])) . '</p>
          <p style="font-size:10pt;"><strong>Class:</strong> ' . htmlspecialchars((string)($cert['class_name'] ?? '')) . '</p>
          <p style="font-size:11pt;margin-top:8px;">' . nl2br(htmlspecialchars((string)($cert['notes'] ?? ''))) . '</p>
          <p style="font-size:9pt;color:#999;margin-top:6px;"><strong>Serial:</strong> ' . htmlspecialchars((string)$cert['serial_no']) . '</p>
        </td>
        <td width="35%" style="border:1px solid #bbb;text-align:center;vertical-align:top;">
          ' . $studentPhoto . '
        </td>
      </tr>
    </table>
    <table width="100%" cellpadding="6" cellspacing="0" style="margin-top:14px;">
      <tr>
        <td width="40%" style="border-top:1px solid #333;text-align:center;padding-top:10px;"><strong>Authorized Signature</strong></td>
        <td width="60%" style="text-align:right;"><strong>Date:</strong> ' . htmlspecialchars((string)$cert['issue_date']) . '</td>
      </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->SetXY(15, 250);
    $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 15, 250, 20, 20);
}

