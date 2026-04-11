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
	$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE class_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$classId, $termId]);
	$existingCardId = (int)$stmt->fetchColumn();

	if ($existingCardId < 1) {
		$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ? ORDER BY id ASC LIMIT 1");
		$stmt->execute([$classId]);
		$sampleStudentId = $stmt->fetchColumn();
		if ($sampleStudentId) {
			$rankData = report_rank_students($conn, $classId, $termId);
			$report = report_compute_for_student($conn, (string)$sampleStudentId, $classId, $termId);
			report_store_card($conn, (string)$sampleStudentId, $classId, $termId, $report, $rankData['positions'], (int)$rankData['total_students'], $generatedBy);
		}
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

	$_SESSION['reply'] = array (array("success", "Report cards are ready. Student, parent, and teacher views will generate each learner's report instantly when opened, so you no longer need to wait for a long bulk process."));
	header("location:../report");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to generate report cards: " . $e->getMessage()));
	header("location:../report");
}
