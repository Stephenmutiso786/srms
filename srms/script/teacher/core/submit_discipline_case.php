<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/notify.php');

if ($res !== '1' || $level !== '2') { header('location:../../'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('location:../discipline'); exit; }

$studentId = trim((string)($_POST['student_id'] ?? ''));
$incidentType = trim((string)($_POST['incident_type'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$severity = strtolower(trim((string)($_POST['severity'] ?? 'medium')));
$allowedSeverity = ['low', 'medium', 'high'];
if (!in_array($severity, $allowedSeverity, true)) {
	$severity = 'medium';
}

if ($studentId === '' || $incidentType === '' || $description === '') {
	$_SESSION['reply'] = array(array('danger', 'Please complete all required fields.'));
	header('location:../discipline');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_cases_table($conn);

	$stmt = $conn->prepare("SELECT st.id, st.class, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name
		FROM tbl_students st
		WHERE st.id = ?
		LIMIT 1");
	$stmt->execute([$studentId]);
	$student = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$student) {
		throw new RuntimeException('Student not found.');
	}

	$classId = (int)($student['class'] ?? 0);
	$studentName = (string)($student['student_name'] ?? $studentId);

	$stmt = $conn->prepare('INSERT INTO tbl_discipline_cases (student_id, teacher_id, class_id, incident_type, description, severity, status, action_taken)
		VALUES (?, ?, NULLIF(?,0), ?, ?, ?, ?, ?)');
	$stmt->execute([$studentId, (int)$account_id, $classId, $incidentType, $description, $severity, 'pending', '']);
	$caseId = (int)$conn->lastInsertId();

	if (app_table_exists($conn, 'tbl_notifications')) {
		$title = 'Discipline Case Logged';
		$message = 'New discipline case for '.$studentName.': '.$incidentType.' ('.ucfirst($severity).').';
		$insertNote = $conn->prepare('INSERT INTO tbl_notifications (title, message, audience, class_id, link, created_by) VALUES (?,?,?,?,?,?)');
		$insertNote->execute([$title, $message, 'students', $classId, 'student/discipline', (int)$account_id]);
		$insertNote->execute([$title, $message, 'parents', $classId, 'parent/discipline', (int)$account_id]);
	}

	$stmt = $conn->prepare("SELECT p.phone, p.email
		FROM tbl_parent_students ps
		JOIN tbl_parents p ON p.id = ps.parent_id
		WHERE ps.student_id = ?");
	$stmt->execute([$studentId]);
	$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$subject = 'Student Discipline Notification';
	$emailBody = '<p>Dear Parent,</p>'
		. '<p>We would like to inform you that your child, <strong>'.htmlspecialchars($studentName).'</strong>, has been involved in the following incident:</p>'
		. '<p><strong>Type:</strong> '.htmlspecialchars($incidentType).'<br>'
		. '<strong>Severity:</strong> '.htmlspecialchars(ucfirst($severity)).'<br>'
		. '<strong>Description:</strong> '.nl2br(htmlspecialchars($description)).'<br>'
		. '<strong>Date:</strong> '.date('Y-m-d H:i:s').'</p>'
		. '<p>Please log in to the school portal for more details.</p>'
		. '<p>Thank you,<br>School Administration</p>';
	$smsMessage = 'Alert: '.$studentName.' involved in a discipline case ('.$incidentType.'). Check portal for details.';

	foreach ($parents as $parent) {
		$phone = trim((string)($parent['phone'] ?? ''));
		$email = trim((string)($parent['email'] ?? ''));
		if ($phone !== '') {
			app_send_sms($conn, $phone, $smsMessage);
		}
		if ($email !== '') {
			app_send_email($conn, $email, $subject, $emailBody);
		}
	}

	$_SESSION['reply'] = array(array('success', 'Discipline case submitted. Parent notifications sent via SMS/Email where configured.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to submit discipline case.'));
}

header('location:../discipline');
exit;
