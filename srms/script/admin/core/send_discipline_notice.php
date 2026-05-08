<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');
require_once('const/school.php');

app_require_discipline_access();

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

	$emailAttempts = [];
	$smsAttempts = [];
	$emailSuccessCount = 0;
	$smsSuccessCount = 0;

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
		}
	}

	$emailAttemptCount = count($emailAttempts);
	$smsAttemptCount = count($smsAttempts);
	$emailStatus = $emailAttemptCount === 0 ? 'No email recipient' : ($emailSuccessCount === $emailAttemptCount ? 'Sent' : ($emailSuccessCount > 0 ? 'Partially Sent' : 'Failed'));
	$smsStatus = $smsAttemptCount === 0 ? 'No phone recipient' : ($smsSuccessCount === $smsAttemptCount ? 'Sent' : ($smsSuccessCount > 0 ? 'Partially Sent' : 'Failed'));

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

	$insert = $conn->prepare('INSERT INTO tbl_discipline_letters (case_id, letter_body, email_recipient, email_status, emailed_at, created_by) VALUES (?,?,?,?,CURRENT_TIMESTAMP,?)');
	$insert->execute([$caseId, $letterBody, trim((string)($parents[0]['email'] ?? '')), $emailStatus . ' | SMS: ' . $smsStatus, (int)$account_id]);

	$updateFields = ["parent_visit_status = 'Pending'", 'updated_at = CURRENT_TIMESTAMP'];
	$updateParams = [];
	if ($emailSuccessCount > 0) {
		$updateFields[] = 'parent_email_sent_at = CURRENT_TIMESTAMP';
	}
	if ($smsSuccessCount > 0) {
		$updateFields[] = 'parent_sms_sent_at = CURRENT_TIMESTAMP';
	}
	$updateParams[] = $caseId;
	$update = $conn->prepare("UPDATE tbl_discipline_cases SET " . implode(', ', $updateFields) . " WHERE id = ?");
	$update->execute($updateParams);

	if ($emailSuccessCount === 0 && $smsSuccessCount === 0) {
		$message = 'No discipline notice was delivered.';
		if (!empty($failureLines)) {
			$message .= ' ' . implode(' ', $failureLines);
		}
		$_SESSION['reply'] = array(array('danger', $message));
	} elseif (!empty($failureLines)) {
		$summary = 'Notice delivery completed with some failures. ';
		$summary .= 'Email success: ' . $emailSuccessCount . '/' . $emailAttemptCount . '. ';
		$summary .= 'SMS success: ' . $smsSuccessCount . '/' . $smsAttemptCount . '.';
		$_SESSION['reply'] = array(array('danger', $summary, ['html' => implode('<br>', array_map('htmlspecialchars', $failureLines))]));
	} else {
		$_SESSION['reply'] = array(array('success', 'Parent notice delivered successfully.'));
	}
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', $e->getMessage() !== '' ? $e->getMessage() : 'Failed to send notice.'));
}

header('location:' . $returnTo);
exit;
?>
