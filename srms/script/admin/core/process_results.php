<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../");
	exit;
}
app_require_permission('report.generate', '../report');
app_require_unlocked('reports', '../report');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../report");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);

if ($classId < 1 || $termId < 1) {
	$_SESSION['reply'] = array (array("danger", "Select class and term"));
	header("location:../report");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_results_locks') && !app_results_locked($conn, $classId, $termId)) {
		$_SESSION['reply'] = array (array("danger", "Results are not locked yet. Please lock results before generating report cards."));
		header("location:../report");
		exit;
	}

	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$totalStudents = (int)$stmt->fetchColumn();
	if ($totalStudents < 1) {
		throw new RuntimeException('This class has no registered students yet.');
	}

	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ?");
	$stmt->execute([$classId, $termId]);
	$totalResults = (int)$stmt->fetchColumn();
	if ($totalResults < 1) {
		throw new RuntimeException('No saved exam results were found for the selected class and term.');
	}

	if (!app_table_exists($conn, 'tbl_report_cards')) {
		throw new RuntimeException('Report card support is not installed. Please run migrations.');
	}

	$generatedBy = isset($account_id) ? (int)$account_id : null;
	$meritList = report_class_merit_list($conn, $classId, $termId, $generatedBy);
	if (empty($meritList['rows'])) {
		throw new RuntimeException('No report cards could be generated for the selected class and term.');
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();
		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		$className = (string)$stmt->fetchColumn();
		$title = "Results Released";
		$message = "Report cards for " . ($className !== '' ? $className : "the class") . " (" . ($termName !== '' ? $termName : "term") . ") are now ready for student, parent, and teacher access.";

		$stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, term_id, link, created_by) VALUES (?,?,?,?,?,?,?)");
		$stmt->execute([$title, $message, 'class', $classId, $termId, 'report_card?term=' . $termId, $generatedBy]);
	}

	$_SESSION['reply'] = array (array("success", "Report cards are ready for " . $meritList['total_students'] . " learners. The class merit list has also been recalculated and saved."));
	header("location:../report");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to generate report cards. Please check the term results and try again."));
	header("location:../report");
}
