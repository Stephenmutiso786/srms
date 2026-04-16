<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/certificate_engine.php');
require_once('const/pdf_branding.php');
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
    $brandingHeader = app_pdf_brand_header_html(null, 'PRIMARY EDUCATION COMPLETION CERTIFICATE', 'Issued as the official school record for Grade 6 completion', 56);
    
    $html = $brandingHeader . '
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
    $brandingHeader = app_pdf_brand_header_html(null, 'GOOD CONDUCT CERTIFICATE', 'Issued to recognize exemplary discipline and school conduct', 56);
    $html = $brandingHeader . '
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

  $studentName = htmlspecialchars(strtoupper(trim((string)$cert['student_name'])), ENT_QUOTES, 'UTF-8');
  $admissionNo = htmlspecialchars((string)($cert['school_id'] ?: $cert['student_id']), ENT_QUOTES, 'UTF-8');
  $issueDate = htmlspecialchars((string)($cert['issue_date'] ?? ''), ENT_QUOTES, 'UTF-8');
  $className = htmlspecialchars(strtoupper(trim((string)($cert['class_name'] ?? ''))), ENT_QUOTES, 'UTF-8');
  $serialNo = htmlspecialchars((string)($cert['serial_no'] ?? ''), ENT_QUOTES, 'UTF-8');
  $notes = htmlspecialchars(trim((string)($cert['notes'] ?? '')), ENT_QUOTES, 'UTF-8');
  $schoolName = htmlspecialchars(strtoupper(trim((string)WBName)), ENT_QUOTES, 'UTF-8');

  $logoPath = 'images/logo/' . WBLogo;
  $logoTag = is_file($logoPath) ? '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" class="logo">' : '';

  $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Kenya Primary School Leaving Certificate</title>

<style>
body {
  margin: 0;
  background: #e5e5e5;
  font-family: "Times New Roman", serif;
}

.page {
  width: 794px;
  height: 1123px;
  margin: auto;
  background: white;
  padding: 50px 70px;
  box-sizing: border-box;
}

.center {
  text-align: center;
}

.logo {
  width: 120px;
  margin-bottom: 5px;
}

.header-small {
  font-size: 11px;
  text-align: right;
}

.title1 {
  font-size: 13px;
  font-weight: bold;
  margin-top: 5px;
}

.title2 {
  font-size: 13px;
  font-weight: bold;
}

.title3 {
  font-size: 13px;
  font-weight: bold;
  text-decoration: underline;
  margin-top: 5px;
}

.row {
  font-size: 12px;
  margin-top: 12px;
}

.line {
  display: inline-block;
  border-bottom: 1px solid black;
  width: 250px;
  height: 12px;
}

.short {
  width: 140px;
}

.long {
  display: block;
  border-bottom: 1px solid black;
  height: 18px;
  margin-top: 6px;
}

.section {
  margin-top: 20px;
  font-size: 12px;
  font-weight: bold;
}

.footer {
  margin-top: 50px;
}

.flex {
  display: flex;
  justify-content: space-between;
  margin-top: 30px;
}

.box {
  width: 45%;
}

.sigline {
  border-bottom: 1px solid black;
  height: 20px;
}

.small {
  font-size: 11px;
}
</style>
</head>

<body>

<div class="page">

  <div class="header-small">SERIAL NO: ' . $serialNo . '</div>

  <div class="center">
    ' . $logoTag . '

    <div class="title1">REPUBLIC OF KENYA</div>
    <div class="title2">MINISTRY OF EDUCATION</div>
    <div class="title3">KENYA PRIMARY SCHOOL LEAVING CERTIFICATE</div>
    <div class="row">Motto: ' . htmlspecialchars((string)WBMotto) . '</div>
    <div class="row">Contacts: ' . htmlspecialchars(trim((string)WBAddress . ' | ' . (string)WBPhone . ' | ' . (string)WBEmail), ENT_QUOTES, 'UTF-8') . '</div>
    <div class="row">Purpose: Official leaver verification and student clearance document</div>
  </div>

  <div class="row">
    THIS IS TO CERTIFY THAT <span class="line">' . $studentName . '</span> INDEX NO <span class="line short">' . $admissionNo . '</span>
  </div>

  <div class="row">
    SCHOOL AND ADMISSION NO <span class="line">' . $schoolName . ' / ' . $admissionNo . '</span>
  </div>

  <div class="row">
    SUB-COUNTY <span class="line short">' . $className . '</span>
    &nbsp;&nbsp;&nbsp;&nbsp;
    COUNTY <span class="line short"></span>
  </div>

  <div class="row">
    HAS SUCCESSFULLY COMPLETED THE APPROVED COURSE OF PRIMARY EDUCATION
  </div>

  <div class="section">
    LEARNING AREAS COVERED FOR CORE COMPETENCIES
  </div>
  <span class="long">' . $notes . '</span>
  <span class="long"></span>

  <div class="section">
    PUPILS PARTICIPATION IN CO-CURRICULAR ACTIVITIES
  </div>
  <span class="long"></span>
  <span class="long"></span>

  <div class="footer">

    <div class="flex">
      <div class="box">
        <div class="small">HEADTEACHER\'S NAME</div>
        <div class="sigline"></div>
      </div>

      <div class="box">
        <div class="small">HEADTEACHER\'S SIGNATURE</div>
        <div class="sigline"></div>
      </div>
    </div>

    <div class="flex">
      <div class="box">
        <div class="small">DATE OF ISSUE</div>
        <div class="sigline">' . $issueDate . '</div>
      </div>

      <div class="box">
        <div class="small">OFFICIAL SCHOOL STAMP</div>
        <div class="sigline"></div>
      </div>
    </div>

  </div>

</div>

</body>
</html>';

  $pdf->writeHTML($html, true, false, true, false, '');

  // QR only, no printed URL.
  $pdf->SetXY(165, 255);
  $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 165, 255, 24, 24);
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
  $brandingHeader = app_pdf_brand_header_html(null, 'MERIT CERTIFICATE', 'Issued for outstanding academic excellence and achievement', 56);
    
  $html = $brandingHeader . '
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
    $brandingHeader = app_pdf_brand_header_html(null, (string)$cert['title'], 'Official school document issued for record, verification, and originality', 56);
    $html = $brandingHeader . '
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

