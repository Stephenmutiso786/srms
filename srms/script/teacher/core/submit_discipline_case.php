<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

$returnTo = trim((string)($_POST['return_to'] ?? '../discipline.php'));
app_require_discipline_access();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	app_render_access_error_page('Invalid request method', 'This endpoint only accepts POST requests for submitting discipline cases.', 405, [
		'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
	]);
}

$studentId = trim((string)($_POST['student_id'] ?? ''));
$incidentType = trim((string)($_POST['incident_type'] ?? ''));
$category = trim((string)($_POST['category'] ?? 'Moderate'));
$description = trim((string)($_POST['description'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
$dateReported = trim((string)($_POST['date_reported'] ?? ''));
$severity = strtolower(trim((string)($_POST['severity'] ?? 'medium')));
if (!in_array($category, ['Minor', 'Moderate', 'Major', 'Severe'], true)) {
	$category = 'Moderate';
}
if (!in_array($severity, ['low', 'medium', 'high'], true)) {
	$severity = 'medium';
}

if ($studentId === '' || $incidentType === '' || $description === '' || $location === '') {
	$_SESSION['reply'] = array(array('danger', 'Please complete all required fields.'));
	header('location:'.$returnTo);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_management_schema($conn);

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
	$suggestedAction = app_discipline_suggest_action($category);
	$dateReportedSql = $dateReported !== '' ? date('Y-m-d H:i:s', strtotime($dateReported)) : date('Y-m-d H:i:s');
	$history = app_discipline_history_summary($conn, $studentId);

	$stmt = $conn->prepare('INSERT INTO tbl_discipline_cases (student_id, teacher_id, class_id, incident_type, description, severity, status, action_taken, category, location, case_status, action_recommended, behavior_trend, date_reported)
		VALUES (?, ?, NULLIF(?,0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	$stmt->execute([$studentId, (int)$account_id, $classId, $incidentType, $description, $severity, 'pending', '', $category, $location, 'Reported', $suggestedAction, (string)$history['behavior_trend'], $dateReportedSql]);
	$caseId = (int)$conn->lastInsertId();

	$_SESSION['reply'] = array(array('success', 'Discipline case saved successfully.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to submit discipline case.'));
}

header('location:'.$returnTo);
exit;
