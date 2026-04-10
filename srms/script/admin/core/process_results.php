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

	$rankData = report_rank_students($conn, $classId, $termId);
	$positions = $rankData['positions'];
	$totalStudents = $rankData['total_students'];
	$generatedBy = isset($account_id) ? (int)$account_id : null;

	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$students = $stmt->fetchAll(PDO::FETCH_COLUMN);

	$generated = 0;
	$failed = [];
	foreach ($students as $studentId) {
		try {
			$report = report_compute_for_student($conn, (string)$studentId, $classId, $termId);
			report_store_card($conn, (string)$studentId, $classId, $termId, $report, $positions, $totalStudents, $generatedBy);
			$generated++;
		} catch (Throwable $studentError) {
			$failed[] = 'Student '.$studentId.': '.$studentError->getMessage();
		}
	}
	if ($generated < 1) {
		throw new RuntimeException($failed[0] ?? 'No report cards could be generated.');
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();
		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		$className = (string)$stmt->fetchColumn();
		$title = "Results Released";
		$message = "Report cards for " . ($className !== '' ? $className : "the class") . " (" . ($termName !== '' ? $termName : "term") . ") are now available.";

		$stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, term_id, link, created_by) VALUES (?,?,?,?,?,?,?)");
		$stmt->execute([$title, $message, 'class', $classId, $termId, 'report_card?term=' . $termId, $generatedBy]);
	}

	$message = "Report cards generated successfully for {$generated} student(s).";
	if ($failed) {
		$message .= ' Some records were skipped: ' . implode(' | ', array_slice($failed, 0, 3));
	}
	$_SESSION['reply'] = array (array($failed ? "warning" : "success", $message));
	header("location:../report");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to generate report cards: " . $e->getMessage()));
	header("location:../report");
}
