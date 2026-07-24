<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');
require_once('const/school.php');
require_once('const/pdf_branding.php');
require_once('tcpdf/tcpdf.php');

app_require_discipline_access();

function app_discipline_notice_slug(string $value): string
{
	$value = preg_replace('/[^A-Za-z0-9]+/', '_', trim($value));
	$value = trim((string)$value, '_');
	return $value !== '' ? $value : 'discipline_notice';
}

function app_build_discipline_notice_pdf(array $case, string $letterBody, string $meetingDate): array
{
	$appRoot = dirname(__DIR__, 3);
	$uploadDir = $appRoot . '/uploads/discipline_letters';
	if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
		return ['ok' => false, 'error' => 'Discipline letter folder is not writable.'];
	}
	@chmod($uploadDir, 0777);
	if (!is_writable($uploadDir)) {
		return ['ok' => false, 'error' => 'Discipline letter folder is not writable.'];
	}

	$studentToken = app_discipline_notice_slug((string)($case['student_name'] ?? 'student'));
	$caseToken = 'case_' . (int)($case['id'] ?? 0);
	$fileName = 'discipline_notice_' . $studentToken . '_' . $caseToken . '.pdf';
	$filePath = rtrim($uploadDir, '/') . '/' . $fileName;

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->SetCreator('SRMS');
	$pdf->SetAuthor((string)(defined('WBName') ? WBName : 'School'));
	$pdf->SetTitle('Discipline Notice');
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(18, 18, 18);
	$pdf->SetAutoPageBreak(true, 18);
	$pdf->AddPage();

	$schoolName = htmlspecialchars((string)(defined('WBName') ? WBName : 'School'));
	$studentName = htmlspecialchars((string)($case['student_name'] ?? ''));
	$className = htmlspecialchars((string)($case['class_name'] ?? ''));
	$admissionNo = htmlspecialchars((string)($case['admission_no'] ?? ''));
	$incidentType = htmlspecialchars((string)($case['incident_type'] ?? ''));
	$reportDate = htmlspecialchars((string)($case['date_reported'] ?? $case['created_at'] ?? date('Y-m-d H:i:s')));
	$body = $letterBody;
	$meetingDateSafe = htmlspecialchars($meetingDate);

	$html = app_pdf_brand_header_html(null, 'OFFICIAL DISCIPLINE NOTICE', 'Parent communication for learner discipline follow-up and meeting request', 44);
	$html .= '<p><strong>Date:</strong> ' . htmlspecialchars(date('Y-m-d')) . '</p>';
	$html .= '<p><strong>OFFICIAL DISCIPLINE NOTICE</strong></p>';
	$html .= '<table cellpadding="5" border="1">
		<tr><td width="30%"><strong>Student</strong></td><td width="70%">' . $studentName . '</td></tr>
		<tr><td><strong>Admission No</strong></td><td>' . $admissionNo . '</td></tr>
		<tr><td><strong>Class</strong></td><td>' . $className . '</td></tr>
		<tr><td><strong>Incident Type</strong></td><td>' . $incidentType . '</td></tr>
		<tr><td><strong>Reported On</strong></td><td>' . $reportDate . '</td></tr>
		<tr><td><strong>Parent Meeting By</strong></td><td>' . $meetingDateSafe . '</td></tr>
	</table>';
	$html .= '<div style="margin-top:14px;">' . $body . '</div>';
	$html .= '<p style="margin-top:26px;">Parent/Guardian Signature: ____________________________</p>';
	$html .= '<p>Deputy Headteacher / Discipline Office: ____________________________</p>';

	$pdf->writeHTML($html, true, false, true, false, '');
	app_pdf_draw_official_footer($pdf, [
		'base_y' => $pdf->getPageHeight() - 28,
		'date_value' => date('Y-m-d'),
		'title' => 'Headteacher',
	]);
	$pdfBlob = $pdf->Output('', 'S');
	if (!is_string($pdfBlob) || $pdfBlob === '') {
		return ['ok' => false, 'error' => 'Failed to generate discipline letter PDF.'];
	}
	if (@file_put_contents($filePath, $pdfBlob) === false || !is_file($filePath)) {
		return ['ok' => false, 'error' => 'Unable to save the discipline letter attachment.'];
	}

	return ['ok' => true, 'file_path' => $filePath, 'file_name' => $fileName];
}

$returnTo = trim((string)($_POST['return_to'] ?? '../discipline.php'));
if ($returnTo === '') { $returnTo = '../discipline.php'; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	app_render_access_error_page('Invalid request method', 'This endpoint only accepts POST requests for sending discipline notices.', 405, [
		'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
	]);
}

$caseId = (int)($_POST['case_id'] ?? 0);
if ($caseId < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid discipline case selected.'));
	header('location:' . $returnTo);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_management_schema($conn);

	$stmt = $conn->prepare("SELECT d.*, st.school_id AS admission_no,
			concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
			c.name AS class_name
		FROM tbl_discipline_cases d
		JOIN tbl_students st ON st.id = d.student_id
		LEFT JOIN tbl_classes c ON c.id = d.class_id
		WHERE d.id = ? LIMIT 1");
	$stmt->execute([$caseId]);
	$case = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$case) {
		throw new RuntimeException('Discipline case not found.');
	}

	$stmt = $conn->prepare("SELECT p.email, p.phone, concat_ws(' ', p.fname, p.lname) AS parent_name
		FROM tbl_parent_students ps
		JOIN tbl_parents p ON p.id = ps.parent_id
		WHERE ps.student_id = ?");
	$stmt->execute([(string)$case['student_id']]);
	$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (empty($parents)) {
		throw new RuntimeException('No linked parent contact found.');
	}

	$schoolName = trim((string)(defined('WBName') ? WBName : 'School'));
	$studentName = (string)($case['student_name'] ?? '');
	$className = (string)($case['class_name'] ?? '');
	$incidentType = (string)($case['incident_type'] ?? '');
	$description = (string)($case['description'] ?? '');
	$actionTaken = trim((string)($case['action_taken'] ?? ''));
	if ($actionTaken === '') {
		$actionTaken = app_discipline_suggest_action((string)($case['category'] ?? 'Moderate'));
	}
	$reportDate = (string)($case['date_reported'] ?? $case['created_at'] ?? date('Y-m-d H:i:s'));
	$meetingDate = date('Y-m-d', strtotime('+3 days'));

	$subject = 'Disciplinary Notice - ' . $schoolName;
	$letterBody = '<p>Dear Parent/Guardian,</p>'
		. '<p><strong>RE: DISCIPLINARY CASE INVOLVING YOUR CHILD</strong></p>'
		. '<p>This is to inform you that your child, <strong>' . htmlspecialchars($studentName) . '</strong> of <strong>' . htmlspecialchars($className) . '</strong>, was involved in a disciplinary incident on ' . htmlspecialchars($reportDate) . '.</p>'
		. '<p><strong>Nature of Offense:</strong><br>' . nl2br(htmlspecialchars($description !== '' ? $description : $incidentType)) . '</p>'
		. '<p><strong>Action Taken:</strong><br>' . htmlspecialchars($actionTaken) . '</p>'
		. '<p>You are kindly requested to visit the school and meet the Deputy Headteacher on or before <strong>' . htmlspecialchars($meetingDate) . '</strong>.</p>'
		. '<p>Thank you for your cooperation.</p>'
		. '<p>Yours sincerely,<br>Deputy Headteacher<br>' . htmlspecialchars($schoolName) . '</p>';
	$pdfResult = app_build_discipline_notice_pdf($case, $letterBody, $meetingDate);

	$emailAttempts = [];
	$smsAttempts = [];
	$whatsappAttempts = [];
	$emailSuccessCount = 0;
	$smsSuccessCount = 0;
	$whatsappSuccessCount = 0;

	foreach ($parents as $parent) {
		$email = trim((string)($parent['email'] ?? ''));
		$phone = trim((string)($parent['phone'] ?? ''));
		if ($email !== '') {
			$emailResult = app_send_email($conn, $email, $subject, $letterBody);
			$emailAttempts[] = [
				'recipient' => $email,
				'ok' => !empty($emailResult['ok']),
				'provider' => (string)($emailResult['provider'] ?? ''),
				'error' => trim((string)($emailResult['error'] ?? '')),
			];
			if (!empty($emailResult['ok'])) {
				$emailSuccessCount++;
			}
		}
		if ($phone !== '') {
			$sms = 'Disciplinary Notice: ' . $studentName . ' was involved in ' . $incidentType . '. Action: ' . $actionTaken . '. Visit school by ' . $meetingDate . '.';
			$smsResult = app_send_sms($conn, $phone, $sms);
			$smsAttempts[] = [
				'recipient' => $phone,
				'ok' => !empty($smsResult['ok']),
				'provider' => (string)($smsResult['provider'] ?? ''),
				'error' => trim((string)($smsResult['error'] ?? '')),
			];
			if (!empty($smsResult['ok'])) {
				$smsSuccessCount++;
			}
			if (!empty($pdfResult['ok'])) {
				$caption = 'Disciplinary notice for ' . $studentName . ' (' . $className . '). Please review the attached letter and visit school by ' . $meetingDate . '.';
				$whatsappResult = app_send_whatsapp_document(
					$conn,
					$phone,
					$caption,
					(string)$pdfResult['file_path'],
					(string)$pdfResult['file_name'],
					[
						'discipline_case_id' => $caseId,
						'student_id' => (string)$case['student_id'],
						'channel' => 'discipline_notice',
					]
				);
				$whatsappAttempts[] = [
					'recipient' => $phone,
					'ok' => !empty($whatsappResult['ok']),
					'provider' => (string)($whatsappResult['provider'] ?? ''),
					'error' => trim((string)($whatsappResult['error'] ?? '')),
				];
				if (!empty($whatsappResult['ok'])) {
					$whatsappSuccessCount++;
				}
			} else {
				$whatsappAttempts[] = [
					'recipient' => $phone,
					'ok' => false,
					'provider' => '',
					'error' => trim((string)($pdfResult['error'] ?? 'Unable to generate discipline letter attachment')),
				];
			}
		}
	}

	$emailAttemptCount = count($emailAttempts);
	$smsAttemptCount = count($smsAttempts);
	$whatsappAttemptCount = count($whatsappAttempts);
	$emailStatus = $emailAttemptCount === 0 ? 'No email recipient' : ($emailSuccessCount === $emailAttemptCount ? 'Sent' : ($emailSuccessCount > 0 ? 'Partially Sent' : 'Failed'));
	$smsStatus = $smsAttemptCount === 0 ? 'No phone recipient' : ($smsSuccessCount === $smsAttemptCount ? 'Sent' : ($smsSuccessCount > 0 ? 'Partially Sent' : 'Failed'));
	$whatsappStatus = $whatsappAttemptCount === 0 ? 'No phone recipient' : ($whatsappSuccessCount === $whatsappAttemptCount ? 'Sent' : ($whatsappSuccessCount > 0 ? 'Partially Sent' : 'Failed'));

	$failureLines = [];
	foreach ($emailAttempts as $attempt) {
		if (!$attempt['ok']) {
			$failureLines[] = 'Email to ' . $attempt['recipient'] . ' failed' . ($attempt['provider'] !== '' ? ' via ' . $attempt['provider'] : '') . ': ' . ($attempt['error'] !== '' ? $attempt['error'] : 'Unknown error');
		}
	}
	foreach ($smsAttempts as $attempt) {
		if (!$attempt['ok']) {
			$failureLines[] = 'SMS to ' . $attempt['recipient'] . ' failed' . ($attempt['provider'] !== '' ? ' via ' . $attempt['provider'] : '') . ': ' . ($attempt['error'] !== '' ? $attempt['error'] : 'Unknown error');
		}
	}
	foreach ($whatsappAttempts as $attempt) {
		if (!$attempt['ok']) {
			$failureLines[] = 'WhatsApp to ' . $attempt['recipient'] . ' failed' . ($attempt['provider'] !== '' ? ' via ' . $attempt['provider'] : '') . ': ' . ($attempt['error'] !== '' ? $attempt['error'] : 'Unknown error');
		}
	}

	$insert = $conn->prepare('INSERT INTO tbl_discipline_letters (case_id, letter_body, email_recipient, email_status, emailed_at, created_by) VALUES (?,?,?,?,CURRENT_TIMESTAMP,?)');
	$deliverySummary = 'E:' . ($emailSuccessCount > 0 ? 'OK' : 'NO') . ' S:' . ($smsSuccessCount > 0 ? 'OK' : 'NO') . ' W:' . ($whatsappSuccessCount > 0 ? 'OK' : 'NO');
	$insert->execute([$caseId, $letterBody, trim((string)($parents[0]['email'] ?? '')), $deliverySummary, (int)$account_id]);

	$updateFields = ["parent_visit_status = 'Pending'", 'updated_at = CURRENT_TIMESTAMP'];
	$updateParams = [];
	if ($emailSuccessCount > 0) {
		$updateFields[] = 'parent_email_sent_at = CURRENT_TIMESTAMP';
	}
	if ($smsSuccessCount > 0) {
		$updateFields[] = 'parent_sms_sent_at = CURRENT_TIMESTAMP';
	}
	if ($whatsappSuccessCount > 0 && app_column_exists($conn, 'tbl_discipline_cases', 'parent_whatsapp_sent_at')) {
		$updateFields[] = 'parent_whatsapp_sent_at = CURRENT_TIMESTAMP';
	}
	$updateParams[] = $caseId;
	$update = $conn->prepare("UPDATE tbl_discipline_cases SET " . implode(', ', $updateFields) . " WHERE id = ?");
	$update->execute($updateParams);

	if ($emailSuccessCount === 0 && $smsSuccessCount === 0 && $whatsappSuccessCount === 0) {
		$message = 'No discipline notice was delivered.';
		if (!empty($failureLines)) {
			$message .= ' ' . implode(' ', $failureLines);
		}
		$_SESSION['reply'] = array(array('danger', $message));
	} elseif (!empty($failureLines)) {
		$summary = 'Notice delivery completed with some failures. ';
		$summary .= 'Email success: ' . $emailSuccessCount . '/' . $emailAttemptCount . '. ';
		$summary .= 'SMS success: ' . $smsSuccessCount . '/' . $smsAttemptCount . '.';
		$summary .= ' WhatsApp success: ' . $whatsappSuccessCount . '/' . $whatsappAttemptCount . '.';
		$_SESSION['reply'] = array(array('danger', $summary, ['html' => implode('<br>', array_map('htmlspecialchars', $failureLines))]));
	} else {
		$_SESSION['reply'] = array(array('success', 'Parent notice delivered successfully by email, SMS, and WhatsApp where available.'));
	}
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', $e->getMessage() !== '' ? $e->getMessage() : 'Failed to send notice.'));
}

header('location:' . $returnTo);
exit;
?>
